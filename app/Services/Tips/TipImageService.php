<?php

namespace App\Services\Tips;

use App\Models\Tip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TipImageService
{
    private const MAX_EDGE = 1920;

    private const WEBP_QUALITY = 82;

    public function store(UploadedFile $image, int $authorId): string
    {
        Validator::make(['image' => $image], ['image' => [
            'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
            'dimensions:max_width=4096,max_height=4096',
        ]])->validate();

        $realPath = $image->getRealPath();
        $sourceBytes = $realPath ? @file_get_contents($realPath) : false;
        if ($sourceBytes === false) {
            $this->fail('No se pudo leer el archivo temporal de la imagen del Tip.', $image);
        }

        if (! function_exists('imagecreatefromstring')) {
            $this->fail('La extension GD no tiene disponible imagecreatefromstring para procesar imagenes de Tips.', $image);
        }

        $source = @imagecreatefromstring($sourceBytes);
        if ($source === false) {
            $this->fail('GD no pudo decodificar la imagen del Tip recibida.', $image);
        }

        $source = $this->applyExifOrientation($source, $image);
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($target === false) {
            imagedestroy($source);
            $this->fail('GD no pudo preparar el lienzo destino para la imagen del Tip.', $image);
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);

        if (! imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
            imagedestroy($source);
            imagedestroy($target);
            $this->fail('GD no pudo redimensionar la imagen del Tip.', $image);
        }

        if (! function_exists('imagewebp')) {
            imagedestroy($source);
            imagedestroy($target);
            $this->fail('La extension GD de este servidor no tiene soporte imagewebp para guardar imagenes de Tips.', $image);
        }

        ob_start();
        $encoded = imagewebp($target, null, self::WEBP_QUALITY);
        $contents = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        if (! $encoded || ! is_string($contents) || $contents === '') {
            $this->fail('GD no pudo comprimir la imagen del Tip como WebP.', $image);
        }

        $path = "tips/{$authorId}/".Str::uuid().'.webp';
        if (! Storage::disk('local')->put($path, $contents)) {
            $this->fail('Laravel no pudo guardar la imagen del Tip en el disco local.', $image);
        }

        return $path;
    }

    private function applyExifOrientation(\GdImage $source, UploadedFile $image): \GdImage
    {
        if ($image->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $source;
        }

        $exif = @exif_read_data($image->getRealPath());
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $source;
        }

        $oriented = imagerotate($source, $angle, 0);
        if ($oriented === false) {
            return $source;
        }

        imagedestroy($source);

        return $oriented;
    }

    private function fail(string $message, UploadedFile $image): never
    {
        throw new RuntimeException($message.' Contexto: '.json_encode($this->imageContext($image), JSON_UNESCAPED_SLASHES));
    }

    private function imageContext(UploadedFile $image): array
    {
        $realPath = $image->getRealPath();
        $detected = $realPath ? @getimagesize($realPath) : false;

        return [
            'client_name' => $image->getClientOriginalName(),
            'client_extension' => $image->getClientOriginalExtension(),
            'client_mime' => $image->getClientMimeType(),
            'detected_mime' => $image->getMimeType(),
            'size_bytes' => $image->getSize(),
            'is_valid_upload' => $image->isValid(),
            'upload_error' => $image->getError(),
            'temp_readable' => $realPath ? is_readable($realPath) : false,
            'getimagesize' => $detected === false ? false : [
                'width' => $detected[0] ?? null,
                'height' => $detected[1] ?? null,
                'mime' => $detected['mime'] ?? null,
            ],
            'gd' => $this->gdContext(),
        ];
    }

    private function gdContext(): array
    {
        if (! function_exists('gd_info')) {
            return ['loaded' => false];
        }

        $info = gd_info();

        return [
            'loaded' => true,
            'version' => $info['GD Version'] ?? null,
            'jpeg' => (bool) ($info['JPEG Support'] ?? false),
            'png' => (bool) ($info['PNG Support'] ?? false),
            'webp' => (bool) ($info['WebP Support'] ?? false),
            'avif' => (bool) ($info['AVIF Support'] ?? false),
            'imagecreatefromstring' => function_exists('imagecreatefromstring'),
            'imagewebp' => function_exists('imagewebp'),
            'exif_read_data' => function_exists('exif_read_data'),
        ];
    }

    public function delete(?string $disk, ?string $path): void
    {
        if (! $path) {
            return;
        }
        try {
            if ($disk !== 'local' || ! str_starts_with($path, 'tips/') || str_contains($path, '..')) {
                throw new RuntimeException('Ruta de imagen fuera del módulo Tips.');
            }
            if (! Storage::disk($disk)->delete($path)) {
                throw new RuntimeException('No se pudo eliminar la imagen de Tips.');
            }
        } catch (Throwable $exception) {
            Log::warning('Tips image cleanup requires retry', ['disk' => $disk, 'path' => $path, 'error' => $exception->getMessage()]);
        }
    }

    public function responseForPanel(User $actor, Tip $tip)
    {
        $tip = $tip->fresh();
        abort_unless($tip, 404);
        Gate::forUser($actor)->authorize('view', $tip);
        abort_unless($tip->image_disk === 'local' && $tip->image_path
            && str_starts_with($tip->image_path, 'tips/') && ! str_contains($tip->image_path, '..'), 404);
        abort_unless(Storage::disk('local')->exists($tip->image_path), 404);

        return Storage::disk('local')->response($tip->image_path, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

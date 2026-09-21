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

        $sourceBytes = file_get_contents($image->getRealPath());
        $source = $sourceBytes === false ? false : @imagecreatefromstring($sourceBytes);
        if ($source === false) {
            throw new RuntimeException('No se pudo procesar la imagen del Tip.');
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
            throw new RuntimeException('No se pudo preparar la imagen del Tip.');
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);

        if (! imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
            imagedestroy($source);
            imagedestroy($target);
            throw new RuntimeException('No se pudo redimensionar la imagen del Tip.');
        }

        ob_start();
        $encoded = imagewebp($target, null, self::WEBP_QUALITY);
        $contents = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        if (! $encoded || ! is_string($contents) || $contents === '') {
            throw new RuntimeException('No se pudo comprimir la imagen del Tip.');
        }

        $path = "tips/{$authorId}/".Str::uuid().'.webp';
        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('No se pudo guardar la imagen del Tip.');
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

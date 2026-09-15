<?php

namespace App\Services\Tips;

use App\Models\Tip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class TipImageService
{
    public function store(UploadedFile $image, int $authorId): string
    {
        Validator::make(['image' => $image], ['image' => [
            'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
            'dimensions:max_width=4096,max_height=4096',
        ]])->validate();

        $path = $image->store("tips/{$authorId}", 'local');
        if (! $path) {
            throw new RuntimeException('No se pudo guardar la imagen del Tip.');
        }

        return $path;
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

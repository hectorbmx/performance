<?php

namespace App\Http\Resources;

use App\Enums\TipCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AppTipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $body = preg_replace('/\s+/', ' ', trim((string) $this->body));
        $category = $this->category?->value ?? (string) $this->category;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type?->value ?? (string) $this->type,
            'scope' => $this->scope?->value ?? (string) $this->scope,
            'category' => [
                'key' => $category,
                'label' => TipCategory::labels()[$category] ?? $category,
            ],
            'excerpt' => Str::limit($body, 180),
            'image_url' => $this->image_path ? route('app.tips.image', $this->id) : null,
            'published_at' => $this->published_at?->utc()->toISOString(),
            'expires_at' => $this->expires_at?->utc()->toISOString(),
            'updated_at' => $this->updated_at?->utc()->toISOString(),
        ];
    }
}

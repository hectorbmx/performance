<?php

namespace App\Http\Requests\Tips;

use App\Enums\TipCategory;
use App\Enums\TipType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAppTipsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', Rule::in(TipCategory::values())],
            'type' => ['nullable', Rule::in(TipType::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'category' => $validated['category'] ?? null,
            'type' => $validated['type'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 20),
        ];
    }
}

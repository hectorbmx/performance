<?php

namespace App\Http\Requests\Tips;

use App\Support\Tips\TipContentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTipRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($tip = $this->route('tip')) {
            \Illuminate\Support\Facades\Gate::authorize('update', $tip);
        }
        return $this->user()?->hasAnyRole(['admin', 'coach']) ?? false;
    }

    public function rules(): array
    {
        return array_merge(TipContentRules::rules(), [
            'intent' => ['nullable', Rule::in($this->routeIs('coach.*') ? ['submit'] : ['publish'])],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'remove_image' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ]);
    }
}

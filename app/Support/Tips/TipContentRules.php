<?php

namespace App\Support\Tips;

use App\Enums\TipCategory;
use App\Enums\TipType;
use Illuminate\Validation\Rule;

class TipContentRules
{
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
            'type' => ['required', Rule::in(TipType::values())],
            'category' => ['required', Rule::in(TipCategory::values())],
        ];
    }
}

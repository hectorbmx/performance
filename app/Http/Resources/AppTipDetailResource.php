<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AppTipDetailResource extends AppTipResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'body' => $this->body,
            'content_format' => 'plain_text',
        ]);
    }
}

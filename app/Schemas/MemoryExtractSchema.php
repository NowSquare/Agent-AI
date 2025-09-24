<?php

namespace App\Schemas;

final class MemoryExtractSchema
{
    public static function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.key' => ['required', 'string'],
            'items.*.value' => ['nullable'],
            'items.*.scope' => ['required', 'in:conversation,user,account'],
            'items.*.ttl_category' => ['required', 'in:volatile,seasonal,durable,legal'],
            'items.*.confidence' => ['required', 'numeric', 'min:0', 'max:1'],
            'items.*.provenance' => ['nullable', 'string'],
        ];
    }
}

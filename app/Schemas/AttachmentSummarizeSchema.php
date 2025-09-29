<?php

namespace App\Schemas;

final class AttachmentSummarizeSchema
{
    /**
     * Validation rules for tool-enforced `attachment_summarize` outputs.
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'gist' => ['required', 'string'],
            'key_points' => ['required', 'array'],
            'key_points.*' => ['string'],
            'table_hint' => ['nullable', 'array'],
        ];
    }
}



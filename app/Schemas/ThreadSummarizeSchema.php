<?php

namespace App\Schemas;

final class ThreadSummarizeSchema
{
    public static function rules(): array
    {
        return [
            'summary' => ['required', 'string'],
            'key_entities' => ['required', 'array'],
            'key_entities.*' => ['string'],
            'open_questions' => ['required', 'array'],
            'open_questions.*' => ['string'],
        ];
    }
}

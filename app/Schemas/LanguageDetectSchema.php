<?php

namespace App\Schemas;

final class LanguageDetectSchema
{
    /**
     * Validation rules for language_detect tool output.
     */
    public static function rules(): array
    {
        return [
            'language' => ['required', 'string', 'max:10'], // bcp-47 short form OK
            'confidence' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }
}

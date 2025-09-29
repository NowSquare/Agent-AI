<?php

namespace App\Schemas;

final class ClarifyDraftSchema
{
    /**
     * Validation rules for tool-enforced `clarify_email_draft` and `options_email_draft` outputs.
     */
    public static function rules(): array
    {
        return [
            'subject' => ['required', 'string'],
            'text' => ['required', 'string'],
            'html' => ['required', 'string'],
        ];
    }
}



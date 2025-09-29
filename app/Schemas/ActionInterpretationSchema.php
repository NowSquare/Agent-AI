<?php

namespace App\Schemas;

final class ActionInterpretationSchema
{
    /**
     * Validation rules for tool-enforced `action_interpret` outputs.
     * Plain: Model must return a structured intent with confidence and optional clarification.
     */
    public static function rules(): array
    {
        return [
            'action_type' => ['required', 'string'],
            'parameters' => ['required', 'array'],
            'scope_hint' => ['nullable', 'string'],
            'confidence' => ['required', 'numeric', 'min:0', 'max:1'],
            'needs_clarification' => ['required', 'boolean'],
            'clarification_prompt' => ['nullable', 'string'],
        ];
    }
}



<?php

namespace App\Http\Requests;

use App\Models\Memory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewMemoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only allow in non-production or with valid API token
        return !app()->environment('production') || $this->hasValidApiToken();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', Rule::in([
                Memory::SCOPE_CONVERSATION,
                Memory::SCOPE_USER,
                Memory::SCOPE_ACCOUNT,
            ])],
            'scope_id' => ['required', 'string', 'max:26'], // ULID
            'key' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Check if request has valid API token.
     */
    private function hasValidApiToken(): bool
    {
        $token = $this->bearerToken();
        if (!$token) {
            return false;
        }

        // TODO: Implement proper token validation
        return true;
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Memory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForgetMemoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow if request has valid signature or API token
        return $this->hasValidSignature() || $this->hasValidApiToken();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required_without:scope',
                'string',
                'exists:memories,id',
            ],
            'scope' => [
                'required_without:id',
                'string',
                Rule::in([
                    Memory::SCOPE_CONVERSATION,
                    Memory::SCOPE_USER,
                    Memory::SCOPE_ACCOUNT,
                ]),
            ],
            'scope_id' => [
                'required_with:scope',
                'string',
                'max:26', // ULID
            ],
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

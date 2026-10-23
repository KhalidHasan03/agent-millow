<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\Auth\AuthService;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:255'],
            'issue_token' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => app(AuthService::class)->normalizeEmail((string) $this->input('email')),
            ]);
        }
    }

    public function deviceName(): string
    {
        $name = $this->string('device_name')->toString();

        if ($name === '') {
            $name = (string) config('agent_millow.tokens.default_name', 'api-client');
        }

        return mb_substr($name, 0, 255);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Enter a valid email address.',
        ];
    }
}

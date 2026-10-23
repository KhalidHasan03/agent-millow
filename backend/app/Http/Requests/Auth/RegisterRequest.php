<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\PasswordPolicy;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'confirmed', new PasswordPolicy],
            'device_name' => ['sometimes', 'string', 'max:255'],
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
}

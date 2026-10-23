<?php

declare(strict_types=1);

namespace App\Http\Requests\Tools;

use Illuminate\Foundation\Http\FormRequest;

final class ProposeToolCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tool' => ['required', 'string', 'max:120'],
            'input' => ['required', 'array'],
            'conversation_id' => ['sometimes', 'nullable', 'integer', 'exists:conversations,id'],
        ];
    }
}

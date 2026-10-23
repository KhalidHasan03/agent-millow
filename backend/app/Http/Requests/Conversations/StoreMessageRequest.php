<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMessageRequest extends FormRequest
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
        $max = (int) config('agent_millow.conversations.max_message_length', 32000);

        return [
            'content' => ['required', 'string', 'min:1', 'max:'.$max],
            'client_message_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'generate' => ['sometimes', 'boolean'],
        ];
    }
}

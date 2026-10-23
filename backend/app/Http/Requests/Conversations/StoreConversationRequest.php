<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversations;

use App\Providers\Llm\ProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreConversationRequest extends FormRequest
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
        $registry = app(ProviderRegistry::class);
        $providers = $registry->names();
        $selectedProvider = $this->input('provider');

        if (! is_string($selectedProvider) || $selectedProvider === '') {
            $selectedProvider = (string) config('agent_millow.providers.default', 'fake');
        }

        $modelRules = ['sometimes', 'nullable', 'string', 'max:120'];

        if ($this->filled('model')) {
            $modelRules[] = Rule::in($registry->allowedModels($selectedProvider));
        }

        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'system_prompt' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'provider' => ['sometimes', 'string', Rule::in($providers)],
            'model' => $modelRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider.in' => 'The selected provider is not available.',
            'model.in' => 'The selected model is not available for this provider.',
        ];
    }
}

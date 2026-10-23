<?php

declare(strict_types=1);

namespace App\Http\Requests\Memories;

use App\Enums\MemoryCategory;
use App\Enums\MemorySource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMemoryRequest extends FormRequest
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
        $maxTitle = (int) config('agent_millow.memory.max_title_length', 200);
        $maxContent = (int) config('agent_millow.memory.max_content_length', 10000);
        $maxKey = (int) config('agent_millow.memory.max_key_length', 120);

        return [
            'title' => ['required', 'string', 'min:1', 'max:'.$maxTitle],
            'content' => ['required', 'string', 'min:1', 'max:'.$maxContent],
            'key' => ['sometimes', 'nullable', 'string', 'max:'.$maxKey],
            'category' => ['sometimes', 'string', Rule::enum(MemoryCategory::class)],
            'source' => ['sometimes', 'string', Rule::enum(MemorySource::class)],
            'confidence' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'sensitive' => ['sometimes', 'boolean'],
            'pinned' => ['sometimes', 'boolean'],
        ];
    }
}

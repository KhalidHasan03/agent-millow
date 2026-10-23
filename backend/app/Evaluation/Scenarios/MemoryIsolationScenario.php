<?php

declare(strict_types=1);

namespace App\Evaluation\Scenarios;

use App\Evaluation\Contracts\EvaluationScenario;
use App\Evaluation\EvaluationCheck;
use App\Evaluation\EvaluationContext;
use App\Models\Memory;
use App\Models\MemoryEvent;
use App\Services\Memories\MemoryService;
use Illuminate\Support\Facades\Gate;

final class MemoryIsolationScenario implements EvaluationScenario
{
    public function key(): string
    {
        return 'memory-isolation';
    }

    public function label(): string
    {
        return 'Memory isolation';
    }

    /**
     * @return list<EvaluationCheck>
     */
    public function run(EvaluationContext $context): array
    {
        $memory = Memory::factory()->for($context->owner)->create([
            'key' => 'evaluation_key',
            'title' => 'Owner preference',
            'content' => 'Only the owner may read this.',
            'sensitive' => true,
        ]);

        $otherMemory = Memory::factory()->for($context->other)->create([
            'key' => 'evaluation_other_key',
            'title' => 'Other preference',
            'content' => 'Other user memory.',
        ]);

        $service = app(MemoryService::class);
        $ownerPage = $service->paginate($context->owner);
        $otherPage = $service->paginate($context->other);

        $ownerIds = array_map(static fn (Memory $item): int => (int) $item->id, $ownerPage->items());
        $otherIds = array_map(static fn (Memory $item): int => (int) $item->id, $otherPage->items());

        $service->inspect($memory, $context->owner);

        $event = MemoryEvent::query()
            ->where('memory_id', $memory->id)
            ->where('action', 'inspected')
            ->first();

        return [
            in_array((int) $memory->id, $ownerIds, true)
                ? EvaluationCheck::pass('owner_sees_memory', 'The owner can list their own memory.')
                : EvaluationCheck::fail('owner_sees_memory', 'The owner cannot list their own memory.'),
            ! in_array((int) $memory->id, $otherIds, true) && ! in_array((int) $otherMemory->id, $ownerIds, true)
                ? EvaluationCheck::pass('cross_user_list_isolated', 'Memory listings are scoped to the authenticated user.')
                : EvaluationCheck::fail('cross_user_list_isolated', 'A user can see another user\'s memory.'),
            Gate::forUser($context->other)->denies('view', $memory)
                && Gate::forUser($context->other)->denies('update', $memory)
                && Gate::forUser($context->other)->denies('delete', $memory)
                ? EvaluationCheck::pass('cross_user_policy_denied', 'Cross-user memory view, update, and delete are denied.')
                : EvaluationCheck::fail('cross_user_policy_denied', 'Cross-user memory access was allowed.'),
            Gate::forUser($context->owner)->allows('view', $memory)
                ? EvaluationCheck::pass('owner_policy_allowed', 'The owner is authorized to view the memory.')
                : EvaluationCheck::fail('owner_policy_allowed', 'The owner was denied access to their memory.'),
            $event !== null && $event->user_id === $context->owner->id
                ? EvaluationCheck::pass('inspection_audited', 'Owner inspection creates a scoped memory event.')
                : EvaluationCheck::fail('inspection_audited', 'Memory inspection was not audited correctly.'),
        ];
    }
}

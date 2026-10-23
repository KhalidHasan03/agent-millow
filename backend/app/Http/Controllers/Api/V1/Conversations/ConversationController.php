<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Conversations;

use App\DataTransferObjects\Conversations\CreateConversationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\StoreConversationRequest;
use App\Http\Requests\Conversations\UpdateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Services\Conversations\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $filters = [
            'per_page' => $request->integer('per_page', 20),
            'status' => $request->input('status'),
        ];

        $page = $this->conversations->paginate($request->user(), $filters);

        return ConversationResource::collection($page)->response();
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $this->authorize('create', Conversation::class);

        $conversation = $this->conversations->create(
            $request->user(),
            CreateConversationData::fromRequest($request->validated()),
        );

        return (new ConversationResource($conversation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return (new ConversationResource($conversation))->response();
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $updated = $this->conversations->update($conversation, $request->validated(), $request->user());

        return (new ConversationResource($updated))->response();
    }

    public function destroy(Request $request, Conversation $conversation): Response
    {
        $this->authorize('delete', $conversation);

        $this->conversations->delete($conversation, $request->user());

        return response()->noContent();
    }
}

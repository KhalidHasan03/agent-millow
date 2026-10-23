<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Conversations;

use App\DataTransferObjects\Conversations\SendMessageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Conversations\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
    ) {}

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $filters = ['per_page' => $request->integer('per_page', 30)];

        $page = $this->messages->paginate($conversation, $filters);

        return MessageResource::collection($page)->response();
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $user = $request->user();
        $data = SendMessageData::fromRequest($request->validated());

        $userMessage = $this->messages->appendUserMessage($conversation, $user, $data);

        $assistant = null;

        if ($data->generate) {
            $assistant = $this->messages->generateReply($conversation->refresh(), $user);
        }

        return response()->json([
            'data' => [
                'user_message' => new MessageResource($userMessage),
                'assistant_message' => $assistant !== null ? new MessageResource($assistant) : null,
            ],
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Conversation $conversation, Message $message): Response
    {
        $this->authorize('delete', $message);

        if ((int) $message->conversation_id !== (int) $conversation->getKey()) {
            abort(404);
        }

        $message->delete();

        return response()->noContent();
    }
}

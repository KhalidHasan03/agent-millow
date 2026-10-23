<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Memories;

use App\DataTransferObjects\Memories\CreateMemoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Memories\StoreMemoryRequest;
use App\Http\Requests\Memories\UpdateMemoryRequest;
use App\Http\Resources\MemoryResource;
use App\Models\Memory;
use App\Services\Memories\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MemoryController extends Controller
{
    public function __construct(
        private readonly MemoryService $memories,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Memory::class);

        $filters = [
            'per_page' => $request->integer('per_page', 30),
            'category' => $request->input('category'),
            'source' => $request->input('source'),
            'pinned' => $request->has('pinned') ? $request->boolean('pinned') : null,
            'q' => $request->input('q'),
        ];

        $page = $this->memories->paginate($request->user(), $filters);

        return MemoryResource::collection($page)->response();
    }

    public function store(StoreMemoryRequest $request): JsonResponse
    {
        $this->authorize('create', Memory::class);

        $memory = $this->memories->create(
            $request->user(),
            CreateMemoryData::fromRequest($request->validated()),
            $request,
        );

        return (new MemoryResource($memory))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Memory $memory): JsonResponse
    {
        $this->authorize('view', $memory);

        $inspected = $this->memories->inspect($memory, $request->user(), $request);

        return (new MemoryResource($inspected))->response();
    }

    public function update(UpdateMemoryRequest $request, Memory $memory): JsonResponse
    {
        $this->authorize('update', $memory);

        $updated = $this->memories->update($memory, $request->validated(), $request->user(), $request);

        return (new MemoryResource($updated))->response();
    }

    public function destroy(Request $request, Memory $memory): Response
    {
        $this->authorize('delete', $memory);

        $this->memories->delete($memory, $request->user(), $request);

        return response()->noContent();
    }
}

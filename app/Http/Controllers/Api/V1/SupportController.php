<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Profile\StoreSupportTicketRequest;
use App\Http\Resources\Api\V1\SupportTicketResource;
use App\Services\Support\SupportCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends BaseApiController
{
    public function __construct(protected SupportCenterService $supportCenterService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->success(
            data: $this->supportCenterService->screenData($user, $request->string('q')->toString() ?: null),
            message: 'Help and support content loaded successfully.',
        );
    }

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->success(
            data: SupportTicketResource::collection($this->supportCenterService->ticketsForUser($user))->resolve(),
            message: 'Support tickets loaded successfully.',
        );
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $ticket = $this->supportCenterService->createTicket($user, $request->validated());

        return $this->success(
            data: (new SupportTicketResource($ticket))->resolve(),
            message: 'Support ticket created successfully.',
            status: 201,
        );
    }
}

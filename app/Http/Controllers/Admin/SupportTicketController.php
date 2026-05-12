<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Requests\Admin\Support\UpdateSupportTicketRequest;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends BaseAdminController
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function index(Request $request): View
    {
        $tickets = SupportTicket::query()
            ->with(['user', 'assignedAdmin'])
            ->when($request->string('q')->toString() !== '', function ($query) use ($request): void {
                $search = $request->string('q')->toString();
                $query->where(function ($builder) use ($search): void {
                    $builder->where('subject', 'like', '%'.$search.'%')
                        ->orWhere('message', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->string('status')->toString() !== '', fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->string('priority')->toString() !== '', fn ($query) => $query->where('priority', $request->string('priority')->toString()))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.support.index', [
            'tickets' => $tickets,
            'statusOptions' => SupportTicketStatus::values(),
            'priorityOptions' => SupportTicketPriority::values(),
            'filters' => $request->only(['q', 'status', 'priority']),
        ]);
    }

    public function show(SupportTicket $supportTicket): View
    {
        $supportTicket->load(['user', 'assignedAdmin']);
        $admins = Admin::query()->orderBy('name')->get();

        return view('admin.support.show', [
            'ticket' => $supportTicket,
            'admins' => $admins,
            'statusOptions' => SupportTicketStatus::values(),
            'priorityOptions' => SupportTicketPriority::values(),
        ]);
    }

    public function update(UpdateSupportTicketRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $payload = $request->validated();
        $resolvedStates = [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value];

        $supportTicket->fill([
            'status' => $payload['status'],
            'priority' => $payload['priority'],
            'assigned_admin_id' => $payload['assigned_admin_id'] ?? null,
            'admin_notes' => $payload['admin_notes'] ?? null,
            'last_admin_reply_at' => now(),
            'resolved_at' => in_array($payload['status'], $resolvedStates, true) ? now() : null,
        ])->save();

        $this->activityLogService->log(
            admin: $this->admin(),
            action: 'support_ticket.updated',
            description: 'Updated support ticket "'.$supportTicket->subject.'".',
            target: $supportTicket,
            metadata: $payload,
        );

        return redirect()
            ->back()
            ->with('status', 'Support ticket updated successfully.');
    }
}

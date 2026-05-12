@extends('layouts.admin', [
    'title' => 'Support Ticket',
    'heading' => $ticket->subject,
    'description' => 'Update ownership, resolution state, and internal notes for this customer support case.',
])

@section('body')
    <div class="two-column">
        <section class="section-card">
            <div class="detail-grid">
                <div class="detail-item"><span>User</span><strong>{{ $ticket->user?->email ?? 'Guest' }}</strong></div>
                <div class="detail-item"><span>Status</span><strong>{{ str_replace('_', ' ', $ticket->status?->value ?? 'open') }}</strong></div>
                <div class="detail-item"><span>Priority</span><strong>{{ str_replace('_', ' ', $ticket->priority?->value ?? 'medium') }}</strong></div>
                <div class="detail-item"><span>Assigned</span><strong>{{ $ticket->assignedAdmin?->name ?? 'Unassigned' }}</strong></div>
            </div>

            <div class="field">
                <label>Customer Message</label>
                <textarea readonly>{{ $ticket->message }}</textarea>
            </div>
        </section>

        <section class="section-card">
            <form method="POST" action="{{ route('admin.support.update', $ticket) }}" class="stack">
                @csrf
                @method('PATCH')

                <div class="field-inline">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status }}" @selected($ticket->status?->value === $status)>{{ str_replace('_', ' ', $status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field-inline">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority">
                        @foreach ($priorityOptions as $priority)
                            <option value="{{ $priority }}" @selected($ticket->priority?->value === $priority)>{{ str_replace('_', ' ', $priority) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field-inline">
                    <label for="assigned_admin_id">Assigned Admin</label>
                    <select id="assigned_admin_id" name="assigned_admin_id">
                        <option value="">Unassigned</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected($ticket->assigned_admin_id === $admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="admin_notes">Internal Notes</label>
                    <textarea id="admin_notes" name="admin_notes">{{ old('admin_notes', $ticket->admin_notes) }}</textarea>
                </div>

                <button type="submit">Save Ticket</button>
            </form>
        </section>
    </div>
@endsection

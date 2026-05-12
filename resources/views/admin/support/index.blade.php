@extends('layouts.admin', [
    'title' => 'Support Tickets',
    'heading' => 'Support Tickets',
    'description' => 'Prioritize customer issues, route ownership, and resolve subscription, import, and offline support cases.',
])

@section('body')
    <section class="section-card">
        <form method="GET" class="filter-form">
            <div class="field-inline">
                <label for="q">Search</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="Subject, message, or user email">
            </div>
            <div class="field-inline">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', $status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field-inline">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <option value="">All priorities</option>
                    @foreach ($priorityOptions as $priority)
                        <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ str_replace('_', ' ', $priority) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit">Apply Filters</button>
        </form>
    </section>

    <section class="section-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Ticket</th>
                    <th>User</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th>Updated</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td>
                            <strong><a href="{{ route('admin.support.show', $ticket) }}">{{ $ticket->subject }}</a></strong>
                            <div class="muted">{{ \Illuminate\Support\Str::limit($ticket->message, 90) }}</div>
                        </td>
                        <td>{{ $ticket->user?->email ?? 'Guest' }}</td>
                        <td><span class="status-badge status-{{ str_replace('_', '-', $ticket->priority?->value ?? 'medium') }}">{{ str_replace('_', ' ', $ticket->priority?->value ?? 'medium') }}</span></td>
                        <td><span class="status-badge status-{{ str_replace('_', '-', $ticket->status?->value ?? 'open') }}">{{ str_replace('_', ' ', $ticket->status?->value ?? 'open') }}</span></td>
                        <td>{{ $ticket->assignedAdmin?->name ?? 'Unassigned' }}</td>
                        <td>{{ $ticket->updated_at?->format('M d, Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No support tickets matched the current filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $tickets->links() }}
    </section>
@endsection

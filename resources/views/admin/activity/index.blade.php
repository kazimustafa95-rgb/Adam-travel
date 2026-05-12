@extends('layouts.admin', [
    'title' => 'Activity Logs',
    'heading' => 'Activity Logs',
    'description' => 'Audit privileged admin actions across status changes, moderation, retries, and content updates.',
])

@section('body')
    <section class="section-card">
        <form method="GET" class="filter-form">
            <div class="field-inline">
                <label for="q">Search</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="Description, action, or target">
            </div>
            <div class="field-inline">
                <label for="action">Action</label>
                <input id="action" name="action" type="text" value="{{ $filters['action'] ?? '' }}" placeholder="user.status_updated">
            </div>
            <button type="submit">Apply Filters</button>
        </form>
    </section>

    <section class="section-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>When</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Description</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('M d, Y H:i') }}</td>
                        <td>{{ $log->admin?->name ?? 'System' }}</td>
                        <td><span class="code">{{ $log->action }}</span></td>
                        <td>{{ $log->target_type ? $log->target_type.' #'.$log->target_id : ($log->target_label ?? '—') }}</td>
                        <td>{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No activity logs matched the current filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </section>
@endsection

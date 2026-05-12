@extends('layouts.admin', [
    'title' => 'Users',
    'heading' => 'Users',
    'description' => 'Search accounts, inspect usage footprint, and update account status with audit visibility.',
])

@section('body')
    <section class="section-card">
        <form method="GET" class="filter-form">
            <div class="field-inline">
                <label for="q">Search</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="Name or email">
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

            <button type="submit">Apply Filters</button>
        </form>
    </section>

    <section class="section-card">
        <div class="section-header">
            <div>
                <h2>User Directory</h2>
                <p>{{ $users->total() }} total users across all pages.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Status</th>
                    <th>Plan</th>
                    <th>Saved</th>
                    <th>Imports</th>
                    <th>Trips</th>
                    <th>Last Seen</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    @php($planCode = $user->subscriptions->first()?->plan?->code ?? 'free')
                    <tr>
                        <td>
                            <strong><a href="{{ route('admin.users.show', $user) }}">{{ $user->name }}</a></strong>
                            <div class="muted">{{ $user->email }}</div>
                        </td>
                        <td><span class="status-badge status-{{ str_replace('_', '-', $user->status?->value ?? 'active') }}">{{ str_replace('_', ' ', $user->status?->value ?? 'active') }}</span></td>
                        <td><span class="code">{{ $planCode }}</span></td>
                        <td>{{ $user->saved_places_count }}</td>
                        <td>{{ $user->imports_count }}</td>
                        <td>{{ $user->owned_trips_count }}</td>
                        <td>{{ $user->last_seen_at?->format('M d, Y H:i') ?? 'Never' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.users.status.update', $user) }}" class="inline-form">
                                @csrf
                                @method('PATCH')
                                <select name="status">
                                    @foreach ($statusOptions as $status)
                                        <option value="{{ $status }}" @selected($user->status?->value === $status)>{{ str_replace('_', ' ', $status) }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="button-small">Update</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No users matched the current filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </section>
@endsection

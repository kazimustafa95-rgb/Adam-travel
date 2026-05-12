@extends('layouts.admin', [
    'title' => 'Trips',
    'heading' => 'Trips',
    'description' => 'Inspect collaborative planning containers, scheduling progress, and trip lifecycle states.',
])

@section('body')
    <section class="section-card">
        <form method="GET" class="filter-form">
            <div class="field-inline">
                <label for="q">Search</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="Trip title, slug, or owner email">
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
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Trip</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th>Members</th>
                    <th>Pool</th>
                    <th>Days</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($trips as $trip)
                    <tr>
                        <td>
                            <strong><a href="{{ route('admin.trips.show', $trip) }}">{{ $trip->title }}</a></strong>
                            <div class="muted">{{ $trip->slug }}</div>
                        </td>
                        <td>{{ $trip->owner?->email ?? 'Unknown' }}</td>
                        <td><span class="status-badge status-{{ str_replace('_', '-', $trip->status?->value ?? 'draft') }}">{{ str_replace('_', ' ', $trip->status?->value ?? 'draft') }}</span></td>
                        <td>{{ $trip->members_count }}</td>
                        <td>{{ $trip->pool_count }}</td>
                        <td>{{ $trip->itinerary_days_count }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.trips.update', $trip) }}" class="inline-form">
                                @csrf
                                @method('PATCH')
                                <select name="status">
                                    @foreach ($statusOptions as $status)
                                        <option value="{{ $status }}" @selected($trip->status?->value === $status)>{{ str_replace('_', ' ', $status) }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="button-small">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No trips matched the current filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $trips->links() }}
    </section>
@endsection

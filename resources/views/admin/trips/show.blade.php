@extends('layouts.admin', [
    'title' => 'Trip Detail',
    'heading' => $trip->title,
    'description' => 'Review members, pool items, itinerary structure, AI runs, and offline package artifacts for this trip.',
])

@section('toolbar')
    <form method="POST" action="{{ route('admin.trips.update', $trip) }}" class="inline-form">
        @csrf
        @method('PATCH')
        <select name="status">
            @foreach (\App\Enums\TripStatus::values() as $status)
                <option value="{{ $status }}" @selected($trip->status?->value === $status)>{{ str_replace('_', ' ', $status) }}</option>
            @endforeach
        </select>
        <button type="submit">Update Status</button>
    </form>
@endsection

@section('body')
    <section class="section-card">
        <div class="detail-grid">
            <div class="detail-item"><span>Owner</span><strong>{{ $trip->owner?->email ?? 'Unknown' }}</strong></div>
            <div class="detail-item"><span>Status</span><strong>{{ str_replace('_', ' ', $trip->status?->value ?? 'draft') }}</strong></div>
            <div class="detail-item"><span>Members</span><strong>{{ $trip->members->count() }}</strong></div>
            <div class="detail-item"><span>Pool Items</span><strong>{{ $trip->pool->count() }}</strong></div>
            <div class="detail-item"><span>Itinerary Days</span><strong>{{ $trip->itineraryDays->count() }}</strong></div>
            <div class="detail-item"><span>Offline Packages</span><strong>{{ $trip->offlinePackages->count() }}</strong></div>
        </div>
    </section>

    <div class="two-column">
        <section class="section-card">
            <div class="section-header"><div><h2>Members</h2></div></div>
            <ul class="list-reset">
                @foreach ($trip->members as $member)
                    <li class="list-row">
                        <div>
                            <strong>{{ $member->user?->name ?? 'Unknown User' }}</strong>
                            <div class="muted">{{ $member->user?->email ?? 'No email' }}</div>
                        </div>
                        <span class="status-badge status-active">{{ $member->role?->value ?? $member->role }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="section-card">
            <div class="section-header"><div><h2>AI Runs</h2></div></div>
            <ul class="list-reset">
                @forelse ($trip->aiRuns as $run)
                    <li class="list-row">
                        <div>
                            <strong>{{ $run->type?->value ?? 'run' }} · {{ $run->model }}</strong>
                            <div class="muted">{{ $run->requestedBy?->email ?? 'Unknown' }}</div>
                        </div>
                        <span class="status-badge status-{{ str_replace('_', '-', $run->status?->value ?? 'pending') }}">{{ str_replace('_', ' ', $run->status?->value ?? 'pending') }}</span>
                    </li>
                @empty
                    <li class="muted">No AI runs have been recorded for this trip.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection

@extends('layouts.admin', [
    'title' => 'User Detail',
    'heading' => $user->name,
    'description' => 'Inspect the account footprint, recent imports, current plan, devices, and support history for this user.',
])

@section('body')
    <div class="two-column">
        <section class="section-card">
            <div class="detail-grid">
                <div class="detail-item"><span>Email</span><strong>{{ $user->email }}</strong></div>
                <div class="detail-item"><span>Status</span><strong>{{ str_replace('_', ' ', $user->status?->value ?? 'active') }}</strong></div>
                <div class="detail-item"><span>Saved Places</span><strong>{{ $user->saved_places_count }}</strong></div>
                <div class="detail-item"><span>Imports</span><strong>{{ $user->imports_count }}</strong></div>
                <div class="detail-item"><span>Owned Trips</span><strong>{{ $user->owned_trips_count }}</strong></div>
                <div class="detail-item"><span>Support Tickets</span><strong>{{ $user->support_tickets_count }}</strong></div>
            </div>

            <form method="POST" action="{{ route('admin.users.status.update', $user) }}" class="filter-form">
                @csrf
                @method('PATCH')
                <div class="field-inline">
                    <label for="status">Account Status</label>
                    <select id="status" name="status">
                        @foreach (\App\Enums\AccountStatus::values() as $status)
                            <option value="{{ $status }}" @selected($user->status?->value === $status)>{{ str_replace('_', ' ', $status) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit">Save Status</button>
            </form>
        </section>

        <section class="section-card">
            <div class="section-header"><div><h2>Plan and Devices</h2></div></div>
            @php($subscription = $user->subscriptions->first())
            <div class="detail-grid">
                <div class="detail-item"><span>Current Plan</span><strong>{{ $subscription?->plan?->name ?? 'Free' }}</strong></div>
                <div class="detail-item"><span>Subscription Status</span><strong>{{ str_replace('_', ' ', $subscription?->status?->value ?? 'free') }}</strong></div>
                <div class="detail-item"><span>Last Seen</span><strong>{{ $user->last_seen_at?->format('M d, Y H:i') ?? 'Never' }}</strong></div>
                <div class="detail-item"><span>Onboarding</span><strong>{{ $user->onboarding_completed_at ? 'Completed' : 'Pending' }}</strong></div>
            </div>

            <ul class="list-reset">
                @forelse ($user->devices as $device)
                    <li class="list-row">
                        <div>
                            <strong>{{ $device->device_name }}</strong>
                            <div class="muted">{{ $device->device_platform }} · {{ $device->last_ip ?? 'No IP' }}</div>
                        </div>
                        <span class="muted">{{ $device->last_seen_at?->format('M d, Y H:i') ?? $device->last_synced_at?->format('M d, Y H:i') ?? 'Unknown' }}</span>
                    </li>
                @empty
                    <li class="muted">No device records available.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="two-column">
        <section class="section-card">
            <div class="section-header"><div><h2>Recent Imports</h2></div></div>
            <ul class="list-reset">
                @forelse ($recentImports as $import)
                    <li class="list-row">
                        <div>
                            <a href="{{ route('admin.imports.show', $import) }}"><strong>Import #{{ $import->id }}</strong></a>
                            <div class="muted">{{ $import->source_url ?: \Illuminate\Support\Str::limit((string) $import->raw_text, 80) }}</div>
                        </div>
                        <span class="status-badge status-{{ str_replace('_', '-', $import->status?->value ?? 'pending') }}">{{ str_replace('_', ' ', $import->status?->value ?? 'pending') }}</span>
                    </li>
                @empty
                    <li class="muted">No recent imports.</li>
                @endforelse
            </ul>
        </section>

        <section class="section-card">
            <div class="section-header"><div><h2>Recent Trips</h2></div></div>
            <ul class="list-reset">
                @forelse ($recentTrips as $trip)
                    <li class="list-row">
                        <div>
                            <a href="{{ route('admin.trips.show', $trip) }}"><strong>{{ $trip->title }}</strong></a>
                            <div class="muted">{{ $trip->members_count }} members · {{ $trip->pool_count }} pool places</div>
                        </div>
                        <span class="status-badge status-{{ str_replace('_', '-', $trip->status?->value ?? 'draft') }}">{{ str_replace('_', ' ', $trip->status?->value ?? 'draft') }}</span>
                    </li>
                @empty
                    <li class="muted">No owned trips yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection

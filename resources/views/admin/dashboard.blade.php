@extends('layouts.admin', [
    'title' => 'Dashboard',
    'heading' => 'Dashboard',
    'description' => 'Monitor the operational health of users, imports, trips, moderation, and customer support from one admin surface.',
])

@section('toolbar')
    <span class="pill">Admin Surface Live</span>
@endsection

@section('body')
    <div class="stat-grid">
        @foreach ($metrics as $metric)
            <article class="stat-card">
                <div class="metric-label">{{ $metric['label'] }}</div>
                <div class="metric-value">{{ number_format($metric['value']) }}</div>
                <div class="metric-copy">{{ $metric['description'] }}</div>
            </article>
        @endforeach
    </div>

    <div class="split-grid">
        <section class="section-card">
            <div class="section-header">
                <div>
                    <h2>Recent Imports</h2>
                    <p>The newest import attempts across the platform, including blocked and recoverable cases.</p>
                </div>
                <a href="{{ route('admin.imports.index') }}" class="button-secondary button">View all</a>
            </div>

            <div class="table-wrap">
                <table class="data-table table-compact">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($recentImports as $import)
                        <tr>
                            <td><a href="{{ route('admin.imports.show', $import) }}">#{{ $import->id }}</a></td>
                            <td>{{ $import->user?->email ?? 'Unknown' }}</td>
                            <td><span class="status-badge status-{{ str_replace('_', '-', $import->status?->value ?? 'pending') }}">{{ str_replace('_', ' ', $import->status?->value ?? 'pending') }}</span></td>
                            <td>{{ $import->created_at?->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No imports yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section-card">
            <div class="section-header">
                <div>
                    <h2>Recent Support</h2>
                    <p>Tickets that are likely to need quick operational follow-through.</p>
                </div>
                <a href="{{ route('admin.support.index') }}" class="button-secondary button">Open inbox</a>
            </div>

            <ul class="list-reset">
                @forelse ($recentTickets as $ticket)
                    <li class="list-row">
                        <div>
                            <a href="{{ route('admin.support.show', $ticket) }}"><strong>{{ $ticket->subject }}</strong></a>
                            <div class="muted">{{ $ticket->user?->email ?? 'Guest user' }}</div>
                        </div>
                        <span class="status-badge status-{{ str_replace('_', '-', $ticket->status?->value ?? 'open') }}">{{ str_replace('_', ' ', $ticket->status?->value ?? 'open') }}</span>
                    </li>
                @empty
                    <li class="muted">No support tickets yet.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="split-grid">
        <section class="section-card">
            <div class="section-header">
                <div>
                    <h2>Recent Trips</h2>
                    <p>Newest collaborative trip containers and their current planning footprint.</p>
                </div>
                <a href="{{ route('admin.trips.index') }}" class="button-secondary button">View trips</a>
            </div>

            <div class="table-wrap">
                <table class="data-table table-compact">
                    <thead>
                    <tr>
                        <th>Trip</th>
                        <th>Owner</th>
                        <th>Members</th>
                        <th>Pool</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($recentTrips as $trip)
                        <tr>
                            <td><a href="{{ route('admin.trips.show', $trip) }}">{{ $trip->title }}</a></td>
                            <td>{{ $trip->owner?->email ?? 'Unknown' }}</td>
                            <td>{{ $trip->members_count }}</td>
                            <td>{{ $trip->pool_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No trips yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section-card">
            <div class="section-header">
                <div>
                    <h2>Recent Admin Activity</h2>
                    <p>Audit trail for important operational changes and privileged actions.</p>
                </div>
                <a href="{{ route('admin.activity.index') }}" class="button-secondary button">Audit log</a>
            </div>

            <ul class="list-reset">
                @forelse ($recentActivity as $log)
                    <li class="list-row">
                        <div>
                            <strong>{{ $log->description }}</strong>
                            <div class="muted">{{ $log->admin?->name ?? 'System' }} | {{ $log->created_at?->format('M d, Y H:i') }}</div>
                        </div>
                        <span class="code">{{ $log->action }}</span>
                    </li>
                @empty
                    <li class="muted">No admin activity has been logged yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection

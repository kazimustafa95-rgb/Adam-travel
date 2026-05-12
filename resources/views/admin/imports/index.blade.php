@extends('layouts.admin', [
    'title' => 'Imports',
    'heading' => 'Imports',
    'description' => 'Track import throughput, unblock manual review cases, and retry failed extraction jobs.',
])

@section('body')
    <section class="section-card">
        <form method="GET" class="filter-form">
            <div class="field-inline">
                <label for="q">Search</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="URL, text, or user email">
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
                    <th>ID</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Confidence</th>
                    <th>Candidates</th>
                    <th>Saved Places</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($imports as $import)
                    <tr>
                        <td><a href="{{ route('admin.imports.show', $import) }}">#{{ $import->id }}</a></td>
                        <td>{{ $import->user?->email ?? 'Unknown' }}</td>
                        <td><span class="status-badge status-{{ str_replace('_', '-', $import->status?->value ?? 'pending') }}">{{ str_replace('_', ' ', $import->status?->value ?? 'pending') }}</span></td>
                        <td>{{ $import->confidence_score !== null ? number_format((float) $import->confidence_score, 2) : '—' }}</td>
                        <td>{{ $import->candidates_count }}</td>
                        <td>{{ $import->saved_places_count }}</td>
                        <td>{{ $import->created_at?->format('M d, Y H:i') }}</td>
                        <td>
                            @if (in_array($import->status?->value, ['failed', 'manual_review', 'completed'], true))
                                <form method="POST" action="{{ route('admin.imports.retry', $import) }}">
                                    @csrf
                                    <button type="submit" class="button-small">Retry</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No imports matched the current filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $imports->links() }}
    </section>
@endsection

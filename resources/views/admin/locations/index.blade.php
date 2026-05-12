@extends('layouts.admin', [
    'title' => 'Locations',
    'heading' => 'Locations',
    'description' => 'Moderate the normalized place catalog that powers saved places, imports, and trip planning.',
])

@section('body')
    <section class="section-card">
        <form method="GET" class="filter-form">
            <div class="field-inline">
                <label for="q">Search</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="Name, city, or country code">
            </div>
            <div class="field-inline">
                <label for="hidden">Moderation State</label>
                <select id="hidden" name="hidden">
                    <option value="">All</option>
                    <option value="0" @selected(($filters['hidden'] ?? '') === '0')>Visible</option>
                    <option value="1" @selected(($filters['hidden'] ?? '') === '1')>Hidden</option>
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
                    <th>Name</th>
                    <th>City</th>
                    <th>Country</th>
                    <th>Saved Places</th>
                    <th>Visibility</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($locations as $location)
                    <tr>
                        <td>{{ $location->name }}</td>
                        <td>{{ $location->city ?? '—' }}</td>
                        <td>{{ $location->country_code ?? '—' }}</td>
                        <td>{{ $location->saved_places_count }}</td>
                        <td><span class="status-badge status-{{ $location->is_moderated_hidden ? 'hidden' : 'active' }}">{{ $location->is_moderated_hidden ? 'hidden' : 'visible' }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('admin.locations.update', $location) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_moderated_hidden" value="{{ $location->is_moderated_hidden ? '0' : '1' }}">
                                <button type="submit" class="button-small {{ $location->is_moderated_hidden ? 'button-secondary' : 'button-danger' }}">{{ $location->is_moderated_hidden ? 'Unhide' : 'Hide' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No locations matched the current filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $locations->links() }}
    </section>
@endsection

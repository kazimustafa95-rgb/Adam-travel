@extends('layouts.admin', [
    'title' => 'CMS Pages',
    'heading' => 'CMS Pages',
    'description' => 'Maintain legal and help-center content that the mobile app can expose to users.',
])

@section('body')
    <section class="section-card">
        <form method="GET" class="filter-form">
            <div class="field-inline">
                <label for="q">Search</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="Title or slug">
            </div>
            <button type="submit">Apply Filters</button>
        </form>
    </section>

    <section class="section-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Published</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($pages as $page)
                    <tr>
                        <td>{{ $page->title }}</td>
                        <td><span class="code">{{ $page->slug }}</span></td>
                        <td><span class="status-badge status-{{ $page->is_published ? 'published' : 'draft' }}">{{ $page->is_published ? 'published' : 'draft' }}</span></td>
                        <td>{{ $page->updated_at?->format('M d, Y H:i') }}</td>
                        <td><a href="{{ route('admin.cms-pages.edit', $page) }}" class="button-secondary button button-small">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No CMS pages matched the current filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $pages->links() }}
    </section>
@endsection

@extends('layouts.admin', [
    'title' => 'Edit CMS Page',
    'heading' => $page->title,
    'description' => 'Update the published content and visibility state for this CMS document.',
])

@section('body')
    <section class="section-card">
        <form method="POST" action="{{ route('admin.cms-pages.update', $page) }}" class="stack">
            @csrf
            @method('PATCH')

            <div class="field">
                <label for="title">Title</label>
                <input id="title" name="title" type="text" value="{{ old('title', $page->title) }}" required>
            </div>

            <div class="field">
                <label for="content">Content</label>
                <textarea id="content" name="content" required>{{ old('content', $page->content) }}</textarea>
            </div>

            <label class="checkbox" for="is_published">
                <input id="is_published" name="is_published" type="checkbox" value="1" {{ old('is_published', $page->is_published) ? 'checked' : '' }}>
                Published and available to the app
            </label>

            <button type="submit">Save Page</button>
        </form>
    </section>
@endsection

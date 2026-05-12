@extends('layouts.admin', [
    'title' => 'Import Detail',
    'heading' => 'Import #'.$import->id,
    'description' => 'Review extracted candidates, saved output, and the AI request trace for this import job.',
])

@section('toolbar')
    <form method="POST" action="{{ route('admin.imports.retry', $import) }}">
        @csrf
        <button type="submit">Retry Import</button>
    </form>
@endsection

@section('body')
    <section class="section-card">
        <div class="detail-grid">
            <div class="detail-item"><span>User</span><strong>{{ $import->user?->email ?? 'Unknown' }}</strong></div>
            <div class="detail-item"><span>Status</span><strong>{{ str_replace('_', ' ', $import->status?->value ?? 'pending') }}</strong></div>
            <div class="detail-item"><span>Source Type</span><strong>{{ $import->source_type?->value ?? 'unknown' }}</strong></div>
            <div class="detail-item"><span>Confidence</span><strong>{{ $import->confidence_score !== null ? number_format((float) $import->confidence_score, 2) : '—' }}</strong></div>
        </div>

        <div class="two-column">
            <div class="field">
                <label>Source URL</label>
                <input type="text" value="{{ $import->source_url ?? '—' }}" readonly>
            </div>
            <div class="field">
                <label>Normalized Text</label>
                <textarea readonly>{{ $import->normalized_text ?? $import->raw_text }}</textarea>
            </div>
        </div>
    </section>

    <div class="two-column">
        <section class="section-card">
            <div class="section-header"><div><h2>Candidates</h2></div></div>
            <div class="table-wrap">
                <table class="data-table table-compact">
                    <thead>
                    <tr>
                        <th>Place</th>
                        <th>Category</th>
                        <th>City</th>
                        <th>Coordinates</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($import->candidates as $candidate)
                        <tr>
                            <td>{{ $candidate->place_name }}</td>
                            <td>{{ $candidate->category }}</td>
                            <td>{{ $candidate->city ?? '—' }}</td>
                            <td>{{ $candidate->latitude && $candidate->longitude ? $candidate->latitude.', '.$candidate->longitude : 'Missing' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No candidates stored for this import.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section-card">
            <div class="section-header"><div><h2>AI Request Trace</h2></div></div>
            <ul class="list-reset">
                @forelse ($aiRequests as $requestTrace)
                    <li class="list-row">
                        <div>
                            <strong>{{ $requestTrace->model }}</strong>
                            <div class="muted">{{ $requestTrace->provider }} · {{ $requestTrace->status }}</div>
                        </div>
                        <span class="code">{{ $requestTrace->created_at?->format('M d H:i') }}</span>
                    </li>
                @empty
                    <li class="muted">No AI requests were logged for this import.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection

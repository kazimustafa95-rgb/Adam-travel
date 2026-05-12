@extends('layouts.admin', [
    'title' => 'App Settings',
    'heading' => 'App Settings',
    'description' => 'Manage runtime configuration for proximity, offline packaging, and other app-level controls.',
])

@section('body')
    <section class="section-card">
        <form method="GET" class="filter-form">
            <div class="field-inline">
                <label for="q">Search</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="Key or group name">
            </div>
            <button type="submit">Apply Filters</button>
        </form>
    </section>

    <section class="section-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Key</th>
                    <th>Group</th>
                    <th>Current Value</th>
                    <th>Update</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($settings as $setting)
                    @php($currentValue = data_get($setting->value, 'value'))
                    @php($valueType = is_bool($currentValue) ? 'boolean' : (is_int($currentValue) ? 'integer' : (is_array($currentValue) ? 'json' : 'string')))
                    <tr>
                        <td><span class="code">{{ $setting->key }}</span></td>
                        <td>{{ $setting->group_name }}</td>
                        <td class="code">{{ is_array($currentValue) ? json_encode($currentValue) : var_export($currentValue, true) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.app-settings.update', $setting) }}" class="settings-editor">
                                @csrf
                                @method('PATCH')
                                <select name="value_type">
                                    <option value="string" @selected($valueType === 'string')>string</option>
                                    <option value="integer" @selected($valueType === 'integer')>integer</option>
                                    <option value="boolean" @selected($valueType === 'boolean')>boolean</option>
                                    <option value="json" @selected($valueType === 'json')>json</option>
                                </select>
                                <input type="text" name="value" value="{{ is_array($currentValue) ? json_encode($currentValue) : (is_bool($currentValue) ? ($currentValue ? 'true' : 'false') : $currentValue) }}">
                                <button type="submit" class="button-small">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No settings matched the current filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $settings->links() }}
    </section>
@endsection

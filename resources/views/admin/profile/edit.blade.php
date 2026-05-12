@extends('layouts.admin', [
    'title' => 'Profile Settings',
    'heading' => 'Profile Settings',
    'description' => 'Manage your admin identity, sign-in email, and password security from one place.',
])

@section('body')
    <div class="two-column">
        <section class="section-card">
            <div class="section-header">
                <div>
                    <h2>Admin Identity</h2>
                    <p>Update the name and email address used throughout the admin console and audit history.</p>
                </div>
            </div>

            @if ($errors->profileUpdate->any())
                <div class="error-box">
                    @foreach ($errors->profileUpdate->all() as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.profile.update') }}" class="stack">
                @csrf
                @method('PATCH')

                <div class="field">
                    <label for="name">Display Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $admin->name) }}" required>
                </div>

                <div class="field">
                    <label for="email">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $admin->email) }}" required>
                </div>

                <button type="submit">Save Profile</button>
            </form>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Role</span>
                    <strong>{{ str_replace('_', ' ', $admin->role?->value ?? 'super_admin') }}</strong>
                </div>
                <div class="detail-item">
                    <span>Last Login</span>
                    <strong>{{ $admin->last_login_at?->format('M d, Y H:i') ?? 'First session pending' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Account Created</span>
                    <strong>{{ $admin->created_at?->format('M d, Y H:i') ?? 'Unknown' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Admin UUID</span>
                    <strong class="code">{{ $admin->uuid }}</strong>
                </div>
            </div>
        </section>

        <section class="section-card">
            <div class="section-header">
                <div>
                    <h2>Password Security</h2>
                    <p>Rotate your password with current-password verification before any sensitive changes are accepted.</p>
                </div>
            </div>

            @if ($errors->passwordUpdate->any())
                <div class="error-box">
                    @foreach ($errors->passwordUpdate->all() as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.profile.password.update') }}" class="stack">
                @csrf
                @method('PATCH')

                <div class="field">
                    <label for="current_password">Current Password</label>
                    <input id="current_password" type="password" name="current_password" required>
                </div>

                <div class="field">
                    <label for="password">New Password</label>
                    <input id="password" type="password" name="password" required>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm New Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required>
                </div>

                <button type="submit">Update Password</button>
            </form>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Session Guard</span>
                    <strong>admin</strong>
                </div>
                <div class="detail-item">
                    <span>Password Policy</span>
                    <strong>Laravel default strong password rules</strong>
                </div>
                <div class="detail-item">
                    <span>Audit Logging</span>
                    <strong>Enabled for profile and password changes</strong>
                </div>
                <div class="detail-item">
                    <span>Recommended Rotation</span>
                    <strong>Immediately after role or credential changes</strong>
                </div>
            </div>
        </section>
    </div>
@endsection

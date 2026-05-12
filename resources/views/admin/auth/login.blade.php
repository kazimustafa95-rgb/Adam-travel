@extends('layouts.admin', ['title' => 'Admin Login', 'authPage' => true])

@section('body')
    <div class="auth-shell shell">
        <div class="topbar">
            <div class="brand">
                <strong>Adam Travel Admin</strong>
                <span>Secure back-office access for operations and moderation</span>
            </div>
            <span class="pill">Super Admin</span>
        </div>

        <div class="content">
            <div class="hero" style="margin-bottom: 18px;">
                <div>
                    <h1>Sign in</h1>
                    <p>Use your admin credentials to manage users, imports, moderation, and app content.</p>
                </div>
            </div>

            @if ($errors->any())
                <div class="error-box">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}">
                @csrf

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <label class="checkbox" for="remember">
                    <input id="remember" name="remember" type="checkbox" value="1" {{ old('remember') ? 'checked' : '' }}>
                    Keep me signed in on this device
                </label>

                <button type="submit">Enter Admin Panel</button>
            </form>
        </div>
    </div>
@endsection

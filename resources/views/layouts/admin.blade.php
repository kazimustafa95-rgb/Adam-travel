<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Adam Travel Admin' }}</title>
    <style>
        :root {
            --brand: #0ea5b7;
            --brand-dark: #0b7e8c;
            --ink: #10212c;
            --muted: #5f7483;
            --bg: #eef5f7;
            --surface: #ffffff;
            --surface-alt: #f7fbfc;
            --border: #d7e2e8;
            --sidebar: #0f2330;
            --sidebar-muted: #9fb4c0;
            --sidebar-active: rgba(14, 165, 183, 0.18);
            --shadow: 0 20px 45px rgba(16, 33, 44, 0.08);
            --radius: 22px;
            --success-bg: #ecfdf5;
            --success-text: #0f8a5f;
            --warning-bg: #fff7ed;
            --warning-text: #b45309;
            --danger-bg: #fff1f2;
            --danger-text: #be123c;
            --info-bg: #eff6ff;
            --info-text: #1d4ed8;
        }

        * { box-sizing: border-box; }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(14, 165, 183, 0.14), transparent 28%),
                linear-gradient(180deg, #e7f4f7 0%, var(--bg) 220px);
            color: var(--ink);
        }

        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font: inherit; }

        .page {
            min-height: 100vh;
            padding: 0;
        }

        .auth-page {
            padding: 28px 18px 40px;
        }

        .container {
            width: 100%;
            min-height: 100vh;
        }

        .auth-container {
            width: min(1320px, 100%);
            min-height: auto;
            margin: 0 auto;
        }

        .shell {
            background: var(--surface);
            border: 0;
            border-radius: 0;
            box-shadow: none;
            overflow: hidden;
            min-height: 100vh;
        }

        .admin-frame {
            display: grid;
            grid-template-columns: 304px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            background: linear-gradient(180deg, #102836 0%, var(--sidebar) 100%);
            color: #fff;
            padding: 36px 24px 44px;
            display: flex;
            flex-direction: column;
            gap: 24px;
            min-height: 100vh;
            position: sticky;
            top: 0;
            align-self: start;
        }

        .sidebar-brand {
            display: grid;
            gap: 6px;
        }

        .sidebar-brand strong {
            font-size: 20px;
            letter-spacing: 0.01em;
        }

        .sidebar-brand span {
            color: var(--sidebar-muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .sidebar-group {
            display: grid;
            gap: 8px;
        }

        .sidebar-label {
            color: var(--sidebar-muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 0 12px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 16px;
            color: #e7f4f8;
            font-size: 14px;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.07);
            transform: translateX(2px);
        }

        .nav-link.active {
            background: linear-gradient(90deg, rgba(14, 165, 183, 0.24), rgba(14, 165, 183, 0.12));
            color: #ffffff;
            box-shadow: inset 0 0 0 1px rgba(14, 165, 183, 0.18);
        }

        .main-panel {
            background: var(--surface-alt);
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            padding: 28px 34px 26px;
            border-bottom: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            position: sticky;
            top: 0;
            z-index: 15;
        }

        .topbar-copy {
            display: grid;
            gap: 6px;
        }

        .eyebrow {
            color: var(--brand-dark);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .topbar h1 {
            margin: 0;
            font-size: 30px;
        }

        .topbar p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            max-width: 760px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .admin-chip,
        .pill,
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .admin-chip {
            background: #eef7f8;
            color: var(--brand-dark);
        }

        .pill {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .content {
            padding: 28px 34px 42px;
            display: grid;
            gap: 22px;
        }

        .section-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            display: grid;
            gap: 18px;
            box-shadow: 0 18px 40px rgba(9, 28, 39, 0.06);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .section-header h2,
        .section-header h3 {
            margin: 0;
            font-size: 20px;
        }

        .section-header p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .stat-grid,
        .card-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .stat-card,
        .card {
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px;
            background: #fff;
            display: grid;
            gap: 10px;
        }

        .metric-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .metric-value {
            font-size: 32px;
            font-weight: 700;
        }

        .metric-copy {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .split-grid {
            display: grid;
            gap: 22px;
            grid-template-columns: 1.2fr 1fr;
        }

        .table-wrap { overflow-x: auto; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .data-table th,
        .data-table td {
            text-align: left;
            padding: 13px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        .data-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }

        .data-table tr:last-child td { border-bottom: 0; }
        .table-compact { min-width: 0; }
        .table-compact td, .table-compact th { padding-left: 0; padding-right: 0; }
        .muted { color: var(--muted); }
        .stack { display: grid; gap: 16px; }
        .two-column { display: grid; gap: 20px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .detail-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }

        .detail-item {
            display: grid;
            gap: 6px;
            padding: 14px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        .detail-item span {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
            letter-spacing: 0.06em;
        }

        .detail-item strong {
            font-size: 15px;
            line-height: 1.5;
        }

        .filter-form,
        .inline-form {
            display: grid;
            gap: 12px;
            align-items: end;
        }

        .filter-form {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .settings-editor {
            display: grid;
            gap: 10px;
            min-width: 280px;
        }

        .field,
        .field-inline {
            display: grid;
            gap: 8px;
        }

        .field label,
        .field-inline label {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #fff;
            padding: 12px 14px;
            color: var(--ink);
        }

        textarea {
            min-height: 160px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid rgba(14, 165, 183, 0.22);
            border-color: var(--brand);
        }

        .button,
        button {
            appearance: none;
            border: 0;
            cursor: pointer;
            border-radius: 14px;
            padding: 11px 16px;
            background: var(--brand);
            color: #fff;
            font-weight: 700;
            transition: background 0.2s ease;
        }

        .button:hover,
        button:hover {
            background: var(--brand-dark);
        }

        .button-secondary {
            background: #eef7f8;
            color: var(--brand-dark);
        }

        .button-danger {
            background: #e11d48;
            color: #fff;
        }

        .button-danger:hover { background: #be123c; }
        .button-small { padding: 9px 12px; font-size: 12px; }

        .flash {
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
            font-size: 14px;
        }

        .error-box {
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #9f1239;
            font-size: 14px;
        }

        .auth-shell {
            width: min(460px, 100%);
            margin: 8vh auto 0;
            min-height: auto;
            border: 1px solid rgba(215, 226, 232, 0.9);
            border-radius: 30px;
            box-shadow: var(--shadow);
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 18px;
        }

        .checkbox input {
            width: 16px;
            height: 16px;
        }

        .status-active,
        .status-completed,
        .status-resolved,
        .status-ready,
        .status-published,
        .status-open {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .status-processing,
        .status-pending,
        .status-in-progress,
        .status-trialing,
        .status-active-subscription,
        .status-draft {
            background: var(--info-bg);
            color: var(--info-text);
        }

        .status-manual-review,
        .status-awaiting-confirmation,
        .status-suspended,
        .status-high,
        .status-urgent,
        .status-archived {
            background: var(--warning-bg);
            color: var(--warning-text);
        }

        .status-failed,
        .status-disabled,
        .status-hidden,
        .status-canceled,
        .status-expired,
        .status-closed {
            background: var(--danger-bg);
            color: var(--danger-text);
        }

        .list-reset {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 12px;
        }

        .list-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .list-row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .code {
            font-family: Consolas, Monaco, monospace;
            font-size: 12px;
        }

        @media (max-width: 1100px) {
            .admin-frame { grid-template-columns: 1fr; }

            .sidebar {
                padding-bottom: 14px;
                min-height: auto;
                position: relative;
            }

            .split-grid,
            .two-column {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .auth-page { padding: 14px; }
            .topbar, .content { padding: 18px; }
            .topbar h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="page {{ ($authPage ?? false) === true ? 'auth-page' : 'admin-page' }}">
        <div class="container {{ ($authPage ?? false) === true ? 'auth-container' : 'admin-container' }}">
            @if (($authPage ?? false) === true)
                @yield('body')
            @else
                <div class="shell admin-frame">
                    <aside class="sidebar">
                        <div class="sidebar-brand">
                            <strong>Adam Travel Admin</strong>
                            <span>Operations, moderation, billing context, and system configuration for the travel utility platform.</span>
                        </div>

                        <div class="sidebar-group">
                            <div class="sidebar-label">Overview</div>
                            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
                        </div>

                        <div class="sidebar-group">
                            <div class="sidebar-label">Operations</div>
                            <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Users</a>
                            <a href="{{ route('admin.imports.index') }}" class="nav-link {{ request()->routeIs('admin.imports.*') ? 'active' : '' }}">Imports</a>
                            <a href="{{ route('admin.locations.index') }}" class="nav-link {{ request()->routeIs('admin.locations.*') ? 'active' : '' }}">Locations</a>
                            <a href="{{ route('admin.trips.index') }}" class="nav-link {{ request()->routeIs('admin.trips.*') ? 'active' : '' }}">Trips</a>
                            <a href="{{ route('admin.support.index') }}" class="nav-link {{ request()->routeIs('admin.support.*') ? 'active' : '' }}">Support</a>
                            <a href="{{ route('admin.activity.index') }}" class="nav-link {{ request()->routeIs('admin.activity.*') ? 'active' : '' }}">Activity Logs</a>
                        </div>

                        <div class="sidebar-group">
                            <div class="sidebar-label">Content</div>
                            <a href="{{ route('admin.cms-pages.index') }}" class="nav-link {{ request()->routeIs('admin.cms-pages.*') ? 'active' : '' }}">CMS Pages</a>
                            <a href="{{ route('admin.app-settings.index') }}" class="nav-link {{ request()->routeIs('admin.app-settings.*') ? 'active' : '' }}">App Settings</a>
                        </div>

                        <div class="sidebar-group">
                            <div class="sidebar-label">Account</div>
                            <a href="{{ route('admin.profile.edit') }}" class="nav-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}">Profile Settings</a>
                        </div>
                    </aside>

                    <main class="main-panel">
                        <div class="topbar">
                            <div class="topbar-copy">
                                <span class="eyebrow">Operations Console</span>
                                <h1>{{ $heading ?? ($title ?? 'Admin Panel') }}</h1>
                                @if (! empty($description ?? null))
                                    <p>{{ $description }}</p>
                                @endif
                            </div>

                            <div class="topbar-right">
                                @yield('toolbar')

                                <a href="{{ route('admin.profile.edit') }}" class="admin-chip">{{ auth('admin')->user()?->name }}</a>

                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="button-secondary">Log out</button>
                                </form>
                            </div>
                        </div>

                        <div class="content">
                            @include('admin.partials.flash')
                            @yield('body')
                        </div>
                    </main>
                </div>
            @endif
        </div>
    </div>
</body>
</html>

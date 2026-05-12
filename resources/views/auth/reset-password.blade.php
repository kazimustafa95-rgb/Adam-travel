<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password</title>
    <style>
        :root {
            --brand: #0ea5b7;
            --brand-dark: #0b7e8c;
            --bg: #f4f7f9;
            --surface: #ffffff;
            --text: #11212b;
            --muted: #5a7180;
            --border: #d7e2e8;
            --danger-bg: #fff1f2;
            --danger-text: #9f1239;
            --success-bg: #ecfdf5;
            --success-text: #0f8a5f;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: "Segoe UI", Arial, sans-serif;
            background: linear-gradient(180deg, #eaf7fa 0%, var(--bg) 220px);
            color: var(--text);
        }

        .panel {
            width: min(460px, 100%);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 20px 40px rgba(17, 33, 43, 0.08);
        }

        h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }

        p {
            margin: 0 0 22px;
            color: var(--muted);
            line-height: 1.6;
        }

        .field {
            display: grid;
            gap: 8px;
            margin-bottom: 16px;
        }

        label {
            font-size: 14px;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid var(--border);
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid rgba(14, 165, 183, 0.25);
            border-color: var(--brand);
        }

        button {
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 14px 18px;
            background: var(--brand);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover {
            background: var(--brand-dark);
        }

        .notice {
            display: none;
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 14px;
            line-height: 1.5;
        }

        .notice.visible {
            display: block;
        }

        .notice.error {
            background: var(--danger-bg);
            color: var(--danger-text);
        }

        .notice.success {
            background: var(--success-bg);
            color: var(--success-text);
        }
    </style>
</head>
<body>
    <main class="panel">
        <h1>Reset your password</h1>
        <p>Set a new password for your Adam Travel account. Once this succeeds, you can sign back in from the mobile app or admin tools that use the same credentials.</p>

        <div id="notice" class="notice" role="alert"></div>

        <form id="reset-form">
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ $email }}" required>
            </div>

            <div class="field">
                <label for="password">New password</label>
                <input id="password" name="password" type="password" required>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required>
            </div>

            <input type="hidden" id="token" name="token" value="{{ $token }}">

            <button type="submit">Update Password</button>
        </form>
    </main>

    <script>
        const form = document.getElementById('reset-form');
        const notice = document.getElementById('notice');

        const showNotice = (type, message) => {
            notice.className = `notice visible ${type}`;
            notice.textContent = message;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const payload = {
                email: document.getElementById('email').value,
                token: document.getElementById('token').value,
                password: document.getElementById('password').value,
                password_confirmation: document.getElementById('password_confirmation').value,
            };

            const response = await fetch('/api/v1/auth/reset-password', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json();

            if (!response.ok) {
                const errors = result.errors ? Object.values(result.errors).flat().join(' ') : result.message;
                showNotice('error', errors || 'The password could not be reset.');
                return;
            }

            form.reset();
            document.getElementById('email').value = payload.email;
            document.getElementById('token').value = payload.token;
            showNotice('success', result.message || 'Password reset successfully.');
        });
    </script>
</body>
</html>

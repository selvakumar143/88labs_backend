<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password</title>
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }

        p {
            margin: 0 0 18px;
            color: #6b7280;
            font-size: 14px;
        }

        label {
            display: block;
            margin: 12px 0 6px;
            font-size: 14px;
            font-weight: 600;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
        }

        .field-row {
            position: relative;
        }

        .toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #374151;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            padding: 4px 6px;
            width: auto;
            margin: 0;
        }

        button {
            width: 100%;
            margin-top: 16px;
            border: 0;
            border-radius: 8px;
            padding: 11px 14px;
            font-weight: 700;
            font-size: 14px;
            background: #111827;
            color: #fff;
            cursor: pointer;
        }

        .msg {
            margin-top: 14px;
            font-size: 14px;
            line-height: 1.4;
        }

        .ok { color: #166534; }
        .err { color: #b91c1c; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Set Your Password</h1>
            <p>Enter a new password and confirm it to activate your account.</p>

            <form id="setPasswordForm">
                <label for="email">Email</label>
                <input id="email" type="email" value="{{ $email }}" required>

                <label for="password">New Password</label>
                <div class="field-row">
                    <input id="password" type="password" required minlength="6">
                    <button type="button" class="toggle-btn" data-target="password">Show</button>
                </div>

                <label for="password_confirmation">Confirm New Password</label>
                <div class="field-row">
                    <input id="password_confirmation" type="password" required minlength="6">
                    <button type="button" class="toggle-btn" data-target="password_confirmation">Show</button>
                </div>

                <button type="submit">Submit</button>
                <div id="message" class="msg"></div>
            </form>
        </div>
    </div>

    <script>
        const token = @json($token);
        const form = document.getElementById('setPasswordForm');
        const msg = document.getElementById('message');
        const redirectUrl = 'https://88labs.netlify.app/login';

        document.querySelectorAll('.toggle-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                btn.textContent = isHidden ? 'Hide' : 'Show';
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            msg.className = 'msg';
            msg.textContent = 'Submitting...';

            const payload = {
                email: document.getElementById('email').value.trim(),
                token,
                password: document.getElementById('password').value,
                password_confirmation: document.getElementById('password_confirmation').value,
            };

            try {
                const res = await fetch('/api/client/set-password', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json();
                if (res.ok) {
                    msg.className = 'msg ok';
                    msg.textContent = (data.message || 'Password set successfully.') + ' Redirecting to login...';
                    form.reset();
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 1200);
                    return;
                }

                msg.className = 'msg err';
                msg.textContent = data.message || 'Unable to set password.';
            } catch (error) {
                msg.className = 'msg err';
                msg.textContent = 'Network error. Please try again.';
            }
        });
    </script>
</body>
</html>

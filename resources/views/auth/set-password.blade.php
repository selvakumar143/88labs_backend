<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $formTitle ?? 'Set Password' }}</title>
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #334155;
            color: #ffffff;
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
            background: rgba(15, 23, 42, 0.92);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.22);
        }

        .logo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 18px;
        }

        .logo {
            display: block;
            max-width: 100px;
            width: 100%;
            height: auto;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 24px;
            text-align: center;
        }

        p {
            margin: 0 0 18px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 14px;
            text-align: center;
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
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.6);
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
            color: #ffffff;
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
            background: #456fff;
            color: #fff;
            cursor: pointer;
        }

        .msg {
            margin-top: 14px;
            font-size: 14px;
            line-height: 1.4;
        }

        .ok { color: #86efac; }
        .err { color: #fca5a5; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="logo-wrap">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="logo">
            </div>
            <h1>{{ $formTitle ?? 'Set Your Password' }}</h1>
            <p>{{ $formSubtitle ?? 'Enter a new password and confirm it to continue.' }}</p>

            <form id="setPasswordForm">
                <label for="email">Email</label>
                <input id="email" type="email" value="{{ $email }}" required readonly>

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
        const redirectUrl = @json($redirectUrl ?? 'https://88labs.netlify.app/login');
        const submitEndpoint = @json($submitEndpoint ?? '/api/client/set-password');

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
                const res = await fetch(submitEndpoint, {
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

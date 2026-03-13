<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    @php
        $logoSrc = $templateData['logo_src'] ?? null;
        $heading = $templateData['heading'] ?? $subjectLine;
        $greeting = $templateData['greeting'] ?? null;
        $lines = $templateData['lines'] ?? [];
        $actionText = $templateData['action_text'] ?? null;
        $actionUrl = $templateData['action_url'] ?? null;
        $footerLines = $templateData['footer_lines'] ?? [];
    @endphp

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f7fb;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background-color:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 32px 16px;background:linear-gradient(135deg,#0f172a,#2563eb);color:#ffffff;">
                            @if ($logoSrc)
                                <p style="margin:0 0 16px;">
                                    <img src="{{ $logoSrc }}" alt="88Labs" style="display:block;width:46px;height:auto;">
                                </p>
                            @endif
                            <h1 style="margin:0;font-size:28px;line-height:1.2;">{{ $heading }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            @if ($greeting)
                                <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">{{ $greeting }}</p>
                            @endif

                            @foreach ($lines as $line)
                                <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">{{ $line }}</p>
                            @endforeach

                            @if ($actionText && $actionUrl)
                                <p style="margin:24px 0;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 22px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">
                                        {{ $actionText }}
                                    </a>
                                </p>
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.7;color:#4b5563;">If the button does not work, copy and paste this link into your browser:</p>
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.7;word-break:break-all;">
                                    <a href="{{ $actionUrl }}" style="color:#2563eb;">{{ $actionUrl }}</a>
                                </p>
                            @endif

                            @foreach ($footerLines as $line)
                                <p style="margin:0 0 12px;font-size:14px;line-height:1.7;color:#4b5563;">{{ $line }}</p>
                            @endforeach
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Newsletter-Anmeldung bestätigen</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;color:#172033;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Bestätige deine Newsletter-Anmeldung für {{ $clubName }}.
</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f4f7fb;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #dbe4f0;border-radius:16px;overflow:hidden;">
                <tr>
                    <td style="padding:24px 32px;background:#0c172a;border-bottom:4px solid #58a6ff;">
                        <div style="font-size:26px;line-height:32px;font-weight:800;letter-spacing:.2px;color:#ffffff;">Airmius</div>
                        <div style="margin-top:4px;font-size:13px;line-height:20px;color:#b9c7da;">Vereinskommunikation</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:36px 32px 16px;">
                        <div style="display:inline-block;padding:6px 10px;border-radius:999px;background:#eaf4ff;color:#1565c0;font-size:12px;line-height:16px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">E-Mail bestätigen</div>
                        <h1 style="margin:18px 0 14px;font-size:28px;line-height:36px;color:#101827;">Fast geschafft!</h1>
                        <p style="margin:0 0 16px;font-size:16px;line-height:26px;color:#46556b;">{{ filled($subscriberName) ? 'Hallo '.$subscriberName.',' : 'Hallo,' }}</p>
                        <p style="margin:0;font-size:16px;line-height:26px;color:#46556b;">
                            du möchtest den Newsletter von <strong style="color:#172033;">{{ $clubName }}</strong> abonnieren.
                            Bestätige bitte deine E-Mail-Adresse, damit wir die Anmeldung abschließen können.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:20px 32px 28px;">
                        <a href="{{ $confirmationUrl }}" target="_blank" rel="noopener" style="display:inline-block;padding:15px 28px;border-radius:10px;background:#2487e8;color:#ffffff;text-decoration:none;font-size:16px;line-height:20px;font-weight:700;">Newsletter-Anmeldung bestätigen</a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 32px 32px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f7f9fc;border-radius:10px;">
                            <tr>
                                <td style="padding:18px 20px;font-size:13px;line-height:20px;color:#66758a;">
                                    <strong style="color:#344258;">Der Button funktioniert nicht?</strong><br>
                                    Öffne diesen Link direkt in deinem Browser:<br>
                                    <a href="{{ $confirmationUrl }}" target="_blank" rel="noopener" style="color:#156fc3;text-decoration:underline;word-break:break-all;">{{ $confirmationUrl }}</a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:20px 0 0;font-size:13px;line-height:21px;color:#738096;">
                            Der Bestätigungslink ist {{ $expiresHours }} Stunden gültig. Falls du diese Anmeldung nicht selbst gestartet hast, kannst du diese E-Mail einfach ignorieren.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px;background:#f7f9fc;border-top:1px solid #e4eaf2;font-size:12px;line-height:19px;color:#7b8799;">
                        Diese Nachricht wurde über Airmius im Auftrag von {{ $clubName }} versendet.<br>
                        Bitte antworte nicht mit vertraulichen Daten auf diese E-Mail.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

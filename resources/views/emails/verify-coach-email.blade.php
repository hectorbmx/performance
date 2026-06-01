<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activa tu cuenta de Training Flow</title>
</head>
<body style="margin:0; padding:0; background:#111827; font-family:Arial, Helvetica, sans-serif; color:#e5e7eb;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#111827; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px; overflow:hidden; border-radius:18px; border:1px solid rgba(75,85,99,.65); background:#172033;">
                    <tr>
                        <td style="padding:34px 34px 24px; background:linear-gradient(135deg,#111827 0%,#1a2332 62%,#111827 100%);">
                            <p style="margin:0 0 18px; color:#93c5fd; font-size:12px; letter-spacing:4px; text-transform:uppercase;">Training Flow</p>
                            <h1 style="margin:0; color:#ffffff; font-size:30px; line-height:1.15; letter-spacing:-.02em;">Activa tu cuenta</h1>
                            <p style="margin:14px 0 0; color:#cbd5e1; font-size:16px; line-height:1.6;">
                                Hola {{ $coachName ?: 'coach' }}, confirma tu correo para entrar a tu panel y empezar a gestionar atletas, planes y entrenamientos.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 34px 32px; background:#172033;">
                            <div style="padding:22px; border-radius:14px; background:rgba(17,24,39,.68); border:1px solid rgba(75,85,99,.7);">
                                <p style="margin:0 0 20px; color:#d1d5db; font-size:15px; line-height:1.6;">
                                    Usa el botón de abajo para verificar tu dirección de correo. Este enlace vence en {{ $expirationMinutes }} minutos por seguridad.
                                </p>

                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 22px;">
                                    <tr>
                                        <td style="border-radius:12px; background:#2563eb;">
                                            <a href="{{ $verificationUrl }}"
                                               style="display:inline-block; padding:15px 24px; color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; border-radius:12px; box-shadow:0 14px 26px rgba(37,99,235,.28);">
                                                Verificar mi correo
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:0; color:#9ca3af; font-size:13px; line-height:1.6;">
                                    Si no creaste una cuenta en Training Flow, puedes ignorar este mensaje.
                                </p>
                            </div>

                            <p style="margin:22px 0 0; color:#6b7280; font-size:12px; line-height:1.6;">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                <a href="{{ $verificationUrl }}" style="color:#93c5fd; word-break:break-all;">{{ $verificationUrl }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contrasena creada | Athlete Core</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #f8fafc;
            background: #050914;
        }
        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 16px;
            background:
                linear-gradient(rgba(3, 7, 18, .82), rgba(3, 7, 18, .86)),
                url("https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=1500&q=80") center / cover no-repeat;
        }
        .card {
            width: min(100%, 430px);
            padding: 32px 30px;
            border: 1px solid rgba(147, 197, 253, .16);
            border-radius: 22px;
            background: rgba(15, 23, 42, .94);
            box-shadow: 0 28px 70px rgba(0, 0, 0, .55);
            text-align: center;
        }
        .logo { width: 64px; height: 64px; object-fit: contain; margin: 0 auto 18px; display: block; }
        h1 { margin: 0; font-size: 24px; letter-spacing: .12em; font-weight: 800; }
        .copy { margin: 18px 0 0; color: #cbd5e1; font-size: 15px; line-height: 1.6; }
        .copy strong { color: #fff; }
        .notice {
            margin-top: 22px;
            padding: 14px;
            border: 1px solid rgba(147, 197, 253, .16);
            border-radius: 14px;
            background: rgba(2, 6, 23, .68);
            color: #bfdbfe;
            font-size: 14px;
            line-height: 1.45;
        }
        .actions { margin-top: 22px; display: grid; gap: 12px; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 13px;
            color: #fff;
            background: linear-gradient(135deg, #2563eb, #60a5fa);
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .08em;
            text-decoration: none;
            text-transform: uppercase;
            box-shadow: 0 12px 34px rgba(37, 99, 235, .32);
        }
        .small { margin: 0; color: #94a3b8; font-size: 13px; line-height: 1.45; }
        ::selection { background: rgba(96, 165, 250, .35); color: #fff; }
    </style>
</head>
<body data-app-open-url="{{ $appOpenUrl }}">
    <main class="page">
        <section class="card">
            <img src="{{ asset('images/L3.png') }}" alt="Athlete Core" class="logo">
            <h1>CONTRASENA CREADA</h1>
            <p class="copy">
                Tu acceso quedo listo para <strong>{{ $email }}</strong>.
                Estamos abriendo la app para que inicies sesion con tu correo y contrasena.
            </p>
            <div class="actions">
                <a class="button" href="{{ $appOpenUrl }}">Abrir app</a>
                <p class="small">Si no se abre automaticamente, toca el boton. Si la app no esta instalada, instalala y entra con este correo.</p>
            </div>
            <div class="notice">
                Esta cuenta es de atleta y no tiene acceso al panel web de coach.
            </div>
        </section>
    </main>
    <script>
        window.setTimeout(function () {
            var appOpenUrl = document.body.dataset.appOpenUrl;
            if (appOpenUrl) {
                window.location.href = appOpenUrl;
            }
        }, 450);
    </script>
</body>
</html>

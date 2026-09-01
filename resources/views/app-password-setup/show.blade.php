<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crear contrasena | Athlete Core</title>
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
            padding: 30px;
            border: 1px solid rgba(147, 197, 253, .16);
            border-radius: 22px;
            background: rgba(15, 23, 42, .94);
            box-shadow: 0 28px 70px rgba(0, 0, 0, .55);
        }
        .brand { text-align: center; margin-bottom: 28px; }
        .logo { width: 64px; height: 64px; object-fit: contain; margin: 0 auto 16px; display: block; }
        h1 { margin: 0; font-size: 25px; letter-spacing: .14em; font-weight: 800; }
        .eyebrow { margin: 8px 0 0; color: #93c5fd; font-size: 12px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; }
        .field { margin-bottom: 18px; }
        label { display: block; margin-bottom: 8px; color: #e2e8f0; font-size: 14px; font-weight: 650; }
        input {
            width: 100%;
            height: 48px;
            padding: 0 14px;
            border: 1px solid rgba(147, 197, 253, .22);
            border-radius: 13px;
            outline: none;
            background: rgba(2, 6, 23, .72);
            color: #ffffff;
            font-size: 15px;
        }
        input:focus { border-color: #60a5fa; box-shadow: 0 0 0 4px rgba(96, 165, 250, .15); }
        input[readonly] { color: #cbd5e1; background: rgba(15, 23, 42, .72); }
        .hint { margin: 4px 0 20px; color: #cbd5e1; font-size: 14px; line-height: 1.55; }
        .error { margin-top: 8px; color: #fca5a5; font-size: 13px; }
        button {
            width: 100%;
            height: 50px;
            border: 0;
            border-radius: 13px;
            color: white;
            background: linear-gradient(135deg, #2563eb, #60a5fa);
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 12px 34px rgba(37, 99, 235, .32);
        }
        button:hover { filter: brightness(1.08); }
        ::selection { background: rgba(96, 165, 250, .35); color: #fff; }
    </style>
</head>
<body>
    <main class="page">
        <section class="card">
            <div class="brand">
                <img src="{{ asset('images/L3.png') }}" alt="Athlete Core" class="logo">
                <h1>ATHLETE CORE</h1>
                <p class="eyebrow">Crear acceso a la app</p>
            </div>

            <form method="POST" action="{{ route('app.password-setup.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="field">
                    <label for="email">Correo electronico</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $email) }}" readonly required autocomplete="username">
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="password">Contrasena</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password">
                    @error('password') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirmar contrasena</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>

                <p class="hint">Este enlace crea tu contrasena de acceso. Despues podras iniciar sesion en la app con este correo.</p>

                <button type="submit">Crear contrasena</button>
            </form>
        </section>
    </main>
</body>
</html>

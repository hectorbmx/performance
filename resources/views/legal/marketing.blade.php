<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Coach SaaS | Plataforma de Entrenamiento</title>
  <meta name="description" content="Plataforma profesional para entrenadores y atletas. Gestiona entrenamientos, métricas y progreso en un solo lugar.">
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin:0; background:#ffffff; color:#111; }
    .wrap { max-width: 1000px; margin:0 auto; padding:40px 20px; }
    h1 { font-size: 36px; margin-bottom: 12px; }
    h2 { font-size: 22px; margin-top:40px; }
    p { line-height:1.6; font-size:16px; }
    .hero { padding:80px 20px; text-align:center; background:#f8f9fa; border-bottom:1px solid #eee; }
    .btn { display:inline-block; margin-top:20px; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:600; background:#000; color:#fff; }
    .features { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; margin-top:40px; }
    .card { border:1px solid #eee; border-radius:12px; padding:20px; }
    footer { margin-top:60px; padding-top:20px; border-top:1px solid #eee; font-size:14px; color:#666; }
  </style>
</head>
<body>

  <section class="hero">
    <div class="wrap">
      <h1>Coach SaaS</h1>
      <p>La plataforma profesional para entrenadores y atletas.</p>
      <p>Gestiona sesiones, registra resultados y visualiza el progreso en tiempo real.</p>
      <a href="/support" class="btn">Soporte</a>
    </div>
  </section>

  <main class="wrap">

    <h2>¿Qué ofrece Coach SaaS?</h2>

    <div class="features">
      <div class="card">
        <h3>Gestión de entrenamientos</h3>
        <p>Asigna sesiones personalizadas y controla el avance de tus atletas.</p>
      </div>

      <div class="card">
        <h3>Seguimiento de métricas</h3>
        <p>Registra repeticiones, peso, tiempo, distancia y progreso físico.</p>
      </div>

      <div class="card">
        <h3>Integración con Salud</h3>
        <p>Sincronización opcional con Apple Health para visualizar actividad diaria.</p>
      </div>

      <div class="card">
        <h3>Panel profesional</h3>
        <p>Control administrativo y seguimiento de clientes desde una sola plataforma.</p>
      </div>
    </div>

    <footer>
      © {{ date('Y') }} Coach SaaS · 
      <a href="/privacy">Política de Privacidad</a> · 
      <a href="/support">Soporte</a>
    </footer>

  </main>

</body>
</html>

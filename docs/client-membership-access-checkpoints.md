# Roadmap: Control De Acceso Por Membresia Del Atleta

## Objetivo

Evitar que un atleta con membresia vencida siga usando la app movil solo porque conserva un token Sanctum valido.

Resultado esperado:

- Un atleta con membresia vigente puede iniciar sesion y usar las rutas operativas de la app.
- Un atleta con membresia vencida fuera de gracia no puede consultar entrenamientos ni registrar progreso.
- Un atleta vencido todavia puede cerrar sesion, consultar su estado minimo y acceder a flujos necesarios para renovar.
- La regla de acceso vive en un solo punto reusable, no duplicada en controllers.

## Hallazgo Base

El bloqueo actual existe en `Api/V1/App/AuthController::login()`, antes de crear el token.

La regla de login valida:

- `user_apps.is_active = true`
- cliente asociado
- membresia `status = active`
- `starts_at <= hoy`
- `ends_at >= hoy` o `grace_until >= hoy`

Problema:

- Despues del login, las rutas de app solo usan `auth:sanctum`.
- Si el token fue creado antes del vencimiento, el atleta puede seguir llamando rutas protegidas.
- `app/src/app/guards/auth.guard.ts` solo valida que exista token local; no valida membresia.

## Decision Tecnica Recomendada

Crear un middleware backend para validar acceso operativo del atleta en cada request sensible.

No depender de `/app/me` para bloquear acceso. `/app/me` sirve para refrescar estado y mejorar UX, pero no garantiza seguridad porque el cliente puede llamar directamente cualquier endpoint autenticado.

Seguir la regla de arquitectura del proyecto:

- Extraer la regla a un servicio reusable antes de conectarla a middleware y login.
- Evitar duplicar queries de membresia en `AuthController`, middleware y futuros endpoints.
- Si la regla empieza a cubrir demasiados casos de negocio, pausar y decidir si conviene separar un modulo mayor de acceso del atleta.

## Contrato De Acceso Propuesto

Estados recomendados:

- `active`: tiene membresia activa y vigente por fechas.
- `grace`: la membresia ya vencio por `ends_at`, pero `grace_until` sigue vigente.
- `expired`: no hay membresia vigente ni gracia vigente.
- `no_membership`: no existe membresia usable.
- `inactive_user`: el usuario app o cliente esta inactivo.

Respuesta sugerida al bloquear:

```json
{
  "ok": false,
  "code": "membership_expired",
  "message": "Tu membresia vencio. Renueva para continuar.",
  "access_state": "expired"
}
```

## Rutas A Permitir Con Membresia Vencida

Estas rutas deben quedar solo con `auth:sanctum`:

- `POST /app/logout`
- `GET /app/me`
- `GET /app/memberships`
- `POST /app/memberships/future`
- `POST /app/register-device`

Motivo:

- El atleta debe poder cerrar sesion.
- La app necesita saber el estado actual para mostrar mensaje o pantalla correcta.
- El atleta debe poder ver historial/planes y renovar.
- Registrar device no debe desbloquear servicio, pero puede mantener notificaciones de renovacion o avisos.

## Rutas A Bloquear Si No Hay Acceso Vigente

Estas rutas deben usar `auth:sanctum` + middleware de membresia:

- `GET /app/trainings`
- `GET /app/training-assignments/{assignment}`
- `POST /app/training-assignments/{assignment}/start`
- `POST /app/training-assignments/{assignment}/complete`
- `POST /app/training-assignments/{assignment}/lifting-sets`
- `POST /app/training-assignments/{assignment}/sections/{section}/complete`
- `POST /app/training-sections/{section}/results`
- `PUT /app/training-sections/{section}/results`
- `GET /app/training-sessions/{session}`
- `POST /app/training-sessions/{trainingSession}/start`
- `PATCH /app/athlete/trainings/assignments/{id}/status`
- `GET /app/me/profile`
- `PATCH /app/me/profile`
- `PATCH /app/me/health-profile`
- `POST /app/me/body-records`
- `POST /app/me/metric-records`
- `GET /app/health-metrics`
- `POST /app/health-metrics/sync`
- `GET /app/streak`

Decision pendiente:

- Confirmar si perfil, metricas de salud y streak deben bloquearse con membresia vencida o si se consideran datos personales accesibles aun sin servicio activo.

## Checkpoint 1: Servicio Reusable De Acceso

Estado: completado.

Cambios:

- Crear `app/Services/ClientMembershipAccessService.php`.
- Centralizar busqueda de usuario app, cliente y membresia vigente.
- Exponer metodos claros, por ejemplo:
  - `forUserApp(UserApp $userApp): ClientMembershipAccessResult`
  - `currentMembershipFor(UserApp $userApp): ?ClientMembership`
  - `canAccessService(UserApp $userApp): bool`
- Reusar la misma regla de fechas que hoy usa `login()`.

Criterios de aceptacion:

- La logica de vigencia ya no queda amarrada al controller.
- Puede distinguir `active`, `grace`, `expired`, `no_membership` e `inactive_user`.
- No crea ni modifica membresias.

Validacion minima:

- `php -l app/Services/ClientMembershipAccessService.php`
- Prueba puntual con tinker o test aislado usando casos de fechas.

## Checkpoint 2: Reusar El Servicio En Login Y Me

Estado: completado.

Cambios:

- Reemplazar la query manual de `AuthController::login()` por el servicio.
- Mantener el comportamiento actual: no crear token si no hay acceso vigente.
- Ajustar `AuthController::me()` para devolver `access_state` y la membresia calculada por el servicio.
- Evitar duplicar calculo previo de notificaciones/membresia dentro de `me()`.

Criterios de aceptacion:

- Login sigue bloqueando membresia vencida.
- Login permite periodo de gracia.
- `/app/me` informa el estado actual aun cuando la membresia ya no permite servicio.

Validacion minima:

- `php -l app/Http/Controllers/Api/V1/App/AuthController.php`
- Login manual con atleta vigente.
- Login manual con atleta vencido.

## Checkpoint 3: Middleware De Acceso Operativo

Estado: completado.

Cambios:

- Crear `app/Http/Middleware/EnsureClientMembershipIsActive.php`.
- Inyectar o resolver `ClientMembershipAccessService`.
- Bloquear con `403` y `code = membership_expired` cuando no haya acceso.
- Registrar alias en `app/Http/Kernel.php`, por ejemplo `client.membership`.

Criterios de aceptacion:

- El middleware no depende de UI ni de rutas especificas.
- El middleware no bloquea por error a coaches autenticados en rutas de coach.
- La respuesta es consistente para que Ionic pueda manejarla.

Validacion minima:

- `php -l app/Http/Middleware/EnsureClientMembershipIsActive.php`
- `php -l app/Http/Kernel.php`

## Checkpoint 4: Separar Rutas Permitidas Y Rutas Operativas

Estado: completado.

Cambios:

- Reorganizar `routes/api.php`.
- Mantener grupo base `auth:sanctum` para identidad, logout, membresias, renovacion y device.
- Crear subgrupo con middleware `client.membership` para entrenamientos, progreso y datos operativos.

Criterios de aceptacion:

- Un token viejo vencido ya no puede usar rutas operativas.
- Un token viejo vencido si puede llamar `/app/me`, `/app/memberships` y logout.
- No se afectan rutas de coach API protegidas por `coach.api`.

Validacion minima:

- `php -l routes/api.php`
- Request manual a `/api/v1/app/me` con token vencido.
- Request manual a `/api/v1/app/trainings` con token vencido debe responder `403`.
- Request manual a `/api/v1/app/memberships` con token vencido debe responder `200`.

## Checkpoint 5: UX Movil Para Bloqueo

Estado: completado.

Cambios:

- Manejar respuesta `403` con `code = membership_expired` en `ApiService` o `AuthService`.
- Redirigir a una pantalla apropiada: membresias, login o pantalla de acceso vencido.
- Mostrar mensaje claro sin dejar al atleta atrapado en entrenamiento.
- Conservar opcion de logout.

Criterios de aceptacion:

- Si una ruta operativa responde bloqueo, la app muestra mensaje entendible.
- El atleta puede ir a renovar o cerrar sesion.
- No hay loops de navegacion entre guard, tabs y login.

Validacion minima:

- `ng build`
- Prueba manual con token vencido guardado.

## Checkpoint 6: QA Integral

Casos:

- Atleta con membresia vigente entra y usa entrenamientos.
- Atleta con membresia en gracia entra y usa entrenamientos.
- Atleta vencido intenta login y recibe `403`.
- Atleta que ya tenia token antes de vencer intenta `/app/trainings` y recibe `403`.
- Atleta vencido con token puede llamar `/app/me`.
- Atleta vencido con token puede abrir membresias/renovacion.
- Atleta inactivo queda bloqueado.
- Cliente sin membresia queda bloqueado.
- Coach API no se ve afectado.

Evidencia minima:

- Respuestas JSON de login, `/app/me`, `/app/trainings` y `/app/memberships`.
- Confirmacion de que no se ejecutaron migraciones destructivas ni cambios de base.
- Captura o registro de app movil mostrando el mensaje de membresia vencida.

## Archivos Principales

Backend:

- `coach/app/Services/ClientMembershipAccessService.php`
- `coach/app/Http/Middleware/EnsureClientMembershipIsActive.php`
- `coach/app/Http/Kernel.php`
- `coach/routes/api.php`
- `coach/app/Http/Controllers/Api/V1/App/AuthController.php`
- `coach/app/Models/ClientMembership.php`

App movil:

- `app/src/app/services/api.service.ts`
- `app/src/app/services/auth.service.ts`
- `app/src/app/guards/auth.guard.ts`
- `app/src/app/pages/subscription-history/subscription-history.page.ts`
- `app/src/app/pages/login/login.page.ts`

## Orden De Trabajo Sugerido

1. Checkpoint 1: servicio reusable.
2. Checkpoint 2: login y `/app/me`.
3. Checkpoint 3: middleware.
4. Checkpoint 4: rutas.
5. Checkpoint 5: UX movil.
6. Checkpoint 6: QA integral.

Motivo:

Primero se crea una fuente unica de verdad. Despues se conectan los puntos de entrada existentes. Al final se ajusta la app para una experiencia clara, sin confiar la seguridad al frontend.

## Pendientes De Decision

- Definir si `grace_until` permite uso completo de la app o solo renovacion.
- Definir si perfil, salud, metricas y streak se bloquean junto con entrenamientos.
- Definir si `/app/me` debe regresar `200` con `access_state=expired` o `403` en membresia vencida. Recomendacion: `200`, para que la app pueda explicar el estado.
- Definir si al detectar `membership_expired` la app debe cerrar sesion automaticamente o mantener token para permitir renovacion. Recomendacion: mantener token y limitar rutas operativas.

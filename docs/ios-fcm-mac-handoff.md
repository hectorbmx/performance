# Handoff Mac: Push Notifications iOS / FCM

Fecha: 2026-09-03

## Contexto

Backend Laravel en produccion ya puede hablar con Firebase. El error inicial de Firebase era:

```text
Unable to determine the Firebase Project ID
```

Ese problema quedo corregido al subir el service account JSON al contenedor y agregar al `.env`:

```env
FIREBASE_CREDENTIALS=storage/app/firebase/firebase-service-account.json
FIREBASE_PROJECT_ID=performance-26b25
```

Despues de `php artisan config:clear` y `php artisan cache:clear`, una prueba con token fake ya cambio al error esperado:

```text
The registration token is not a valid FCM registration token
```

Eso confirma que el backend ya llega a FCM. El bloqueo actual esta en iOS: el usuario real `samuel_as13@hotmail.com` no tiene registros en `user_devices`.

## Evidencia Actual

Usuario de prueba en produccion:

```text
email: samuel_as13@hotmail.com
user_apps.id: 5
client_id: 5
```

Consulta ejecutada en produccion:

```bash
php artisan tinker --execute '$u = \App\Models\UserApp::where("email", "samuel_as13@hotmail.com")->first(); $rows = $u ? DB::table("user_devices")->where("user_id", $u->id)->orderByDesc("id")->get(["id","user_id","platform","token","is_enabled","last_seen_at"]) : collect(); echo json_encode(["user" => $u ? $u->only(["id","client_id","email"]) : null, "devices" => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);'
```

Resultado:

```json
{
  "user": {
    "id": 5,
    "client_id": 5,
    "email": "samuel_as13@hotmail.com"
  },
  "devices": []
}
```

Tambien se vio en la app una notificacion interna tipo "Nuevo entrenamiento para ti", pero no fue push real del sistema. Esa notificacion viene de `app/me` y se muestra en la campana/lista interna.

## Estado Firebase / Apple

Proyecto Firebase:

```text
Project ID: performance-26b25
Project number / sender ID: 313092640386
```

App iOS registrada en Firebase:

```text
Bundle ID: com.performanceCoachBarret.app
```

App Store Connect:

```text
App Store ID: 6796584249
Bundle ID: com.performanceCoachBarret.app
```

APNs:

```text
Key ID: 2NG9H8C6V8
Team ID: 44583X3BRM
```

La APNs Auth Key `.p8` fue subida en Firebase para produccion y desarrollo.

## Problema Tecnico Probable

La app Ionic tiene `@capacitor/push-notifications`, pero no hay evidencia de configuracion nativa de Firebase Messaging en iOS.

Archivos revisados:

```text
app/src/app/app.component.ts
app/src/app/services/auth.service.ts
app/ios/App/App/AppDelegate.swift
app/package.json
```

Hallazgos:

- `app.component.ts` llama `PushNotifications.checkPermissions()`, `requestPermissions()` y `PushNotifications.register()`.
- El listener `registration` llama `registerPushToken(token.value)` y postea a `/api/v1/app/register-device` si ya hay auth token.
- `AuthService.login()` reintenta `registerPendingPushToken()` despues de login de atleta.
- `AppDelegate.swift` no importa ni configura Firebase.
- `package.json` no muestra un plugin especifico de Firebase Messaging, solo `@capacitor/push-notifications`.

En iOS, `@capacitor/push-notifications` puede entregar token APNs, pero el backend Laravel esta enviando por FCM/Kreait. Para ese contrato necesitamos guardar un FCM registration token valido o cambiar toda la entrega a APNs directo. La opcion recomendada para mantener lo que ya existe en backend es agregar soporte real de Firebase Messaging en iOS.

## Regla De Implementacion

No cambiar el contrato del backend ahora. Mantener:

```text
user_devices.token = FCM registration token
AppNotificationService = fuente unica de envio FCM
push_notifications = historial unico
```

No borrar registros de produccion ni limpiar tablas durante pruebas.

## Paso 1: Verificar Configuracion Local iOS

En Mac, desde la raiz del repo Ionic:

```bash
cd /ruta/al/coachSaaS/app
git status --short
npx cap sync ios
npx cap open ios
```

En Xcode confirmar:

- Target `App`.
- `Signing & Capabilities`.
- Bundle Identifier exacto:

```text
com.performanceCoachBarret.app
```

- Capability `Push Notifications` presente.
- Capability `Background Modes` presente.
- En `Background Modes`, `Remote notifications` marcado.
- `GoogleService-Info.plist` existe dentro de `ios/App/App/` y esta agregado al target `App`.

## Paso 2: Revisar Logs Antes De Cambiar Codigo

Ejecutar en Xcode con el usuario:

```text
samuel_as13@hotmail.com
```

Buscar en consola:

```text
FCM TOKEN
registrationError
No se pudo registrar el token push
Push init error
```

Interpretacion:

- Si aparece `registrationError`, copiar el error exacto.
- Si aparece token pero no se crea `user_devices`, revisar respuesta HTTP del POST `app/register-device`.
- Si no aparece token, falta configuracion nativa de Firebase Messaging o el listener/register esta mal secuenciado.

## Paso 3: Fix Recomendado En Ionic/iOS

Objetivo: que iOS genere un FCM token real y luego reutilizar el endpoint existente `/api/v1/app/register-device`.

Recomendacion de bajo acoplamiento:

1. Crear un servicio Ionic dedicado, por ejemplo:

```text
src/app/services/push-registration.service.ts
```

Responsabilidades:

- Instalar listeners una sola vez.
- Pedir permisos.
- Registrar push.
- Guardar token pendiente en Preferences.
- Reintentar despues de login.
- Postear a `app/register-device`.

2. Dejar `AppComponent` delgado:

```ts
ngOnInit() {
  window.addEventListener('app:membership-expired', this.handleMembershipExpired);
  this.pushRegistration.init();
}
```

3. Dejar `AuthService.login()` llamando un metodo publico tipo:

```ts
await this.pushRegistration.registerPendingToken();
```

Si se evita inyectar `PushRegistrationService` en `AuthService` para no crear dependencia circular, usar un evento de app o un metodo llamado desde la pagina de login despues de login exitoso.

4. Mover los listeners antes de `PushNotifications.register()`.

Orden recomendado:

```ts
await PushNotifications.removeAllListeners();

PushNotifications.addListener('registration', token => {
  // guardar y registrar
});

PushNotifications.addListener('registrationError', error => {
  console.error('Push registration error', error);
});

PushNotifications.addListener('pushNotificationReceived', ...);
PushNotifications.addListener('pushNotificationActionPerformed', ...);

await PushNotifications.register();
```

5. Agregar Firebase Messaging nativo para iOS.

Como este proyecto usa Capacitor 8 con SPM (`ios/App/CapApp-SPM/Package.swift`), revisar compatibilidad antes de elegir camino:

- Opcion A: usar un plugin Capacitor Firebase Messaging compatible con Capacitor 8.
- Opcion B: integrar `FirebaseMessaging` en iOS nativo y reenviar el FCM token hacia JS.

Preferencia: plugin mantenido y compatible con Capacitor 8 para no mantener mucho codigo Swift propio.

No instalar Firebase SDK manualmente desde la pantalla de Firebase si el proyecto ya resuelve dependencias por Capacitor/SPM sin decidir antes el camino. Evitar mezclar SPM manual con la estrategia de Capacitor si no es necesario.

## Paso 4: Validacion Contra Produccion

Despues de instalar/configurar el soporte FCM iOS y recompilar:

1. Abrir app desde Xcode.
2. Aceptar permisos de notificaciones.
3. Iniciar sesion con:

```text
samuel_as13@hotmail.com
```

4. En produccion, dentro del contenedor Laravel `/var/www`, revisar devices:

```bash
php artisan tinker --execute '$u = \App\Models\UserApp::where("email", "samuel_as13@hotmail.com")->first(); $rows = $u ? DB::table("user_devices")->where("user_id", $u->id)->orderByDesc("id")->get(["id","user_id","platform","token","is_enabled","last_seen_at"]) : collect(); echo json_encode(["user" => $u ? $u->only(["id","client_id","email"]) : null, "devices" => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);'
```

Esperado:

```text
devices contiene al menos 1 fila ios con token largo real
```

5. Enviar prueba FCM al usuario real:

```bash
php artisan tinker --execute '$u = \App\Models\UserApp::where("email", "samuel_as13@hotmail.com")->firstOrFail(); $n = app(\App\Services\AppNotificationService::class)->sendToUserApp($u, "training_assigned", "Prueba FCM real", "Probando envio real a iOS", ["action" => "open_training", "training_session_id" => 1, "source" => "assigned"]); echo json_encode($n->fresh()->only(["id","user_id","type","status","provider","data","error"]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);'
```

Esperado:

- `status=sent`, si el token es FCM valido y APNs esta correcto.
- Si falla, guardar el error exacto. No borrar la notificacion.

## Pendiente Paralelo: Campana Interna

La campana interna muestra notificaciones desde `AuthService.notifications()`, pero el HTML actual:

```text
app/src/app/tab1/tab1.page.html
```

renderiza:

```html
<ion-item *ngFor="let n of notifications()" class="notif-item">
```

No tiene `(click)`, por eso tocar "Nuevo entrenamiento para ti" no navega.

Fix recomendado despues de estabilizar FCM:

- Crear/reusar un servicio de navegacion de notificaciones que entienda `action=open_training`, `source`, `training_session_id`.
- Usarlo tanto en `pushNotificationActionPerformed` como en la campana interna.
- Para `source=assigned`, resolver `assignment_id` con:

```text
GET /api/v1/app/training-sessions/{trainingSession}/assignment
```

- Para `source=free`, navegar a:

```text
/training-details/free/{training_session_id}
```

## Checkpoint Al Terminar

Actualizar estos documentos:

```text
app/docs/push-notifications-implementation-checkpoints.md
coach/docs/push-notifications-implementation-checkpoints.md
```

Registrar:

- Plugin/camino usado para FCM iOS.
- Cambios nativos en Xcode/SPM/CocoaPods.
- Resultado de `user_devices` para Samuel.
- Resultado del envio FCM real.
- Si se corrigio o no la campana interna.

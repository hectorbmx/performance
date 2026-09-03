# Handoff Mac/Windows: Push Notifications iOS / FCM

Fecha: 2026-09-03

## Resumen Ejecutivo

El flujo iOS -> backend -> FCM ya quedo vivo para el usuario real `samuel_as13@hotmail.com`.

Antes, el backend Laravel ya podia hablar con Firebase, pero iOS no generaba un FCM registration token valido. La app solo usaba `@capacitor/push-notifications`, que en iOS trabaja con APNs y no garantizaba el token FCM requerido por Kreait/Laravel.

Ahora la app movil usa `@capacitor-firebase/messaging`, genera un FCM token real, lo registra en `/api/v1/app/register-device`, y produccion ya guardo una fila `ios` en `user_devices`.

El envio FCM real tambien llego al iPhone como push del sistema.

## Estado Validado

Usuario de prueba en produccion:

```text
email: samuel_as13@hotmail.com
user_apps.id: 5
client_id: 5
```

Device guardado en produccion:

```json
{
  "id": 2,
  "user_id": 5,
  "platform": "ios",
  "token": "dyevUZqGkEEHv_RCSMEDuZ:APA91bE5OPfmyHmWZ8wFm7Vl5YuehyeC4G468ujOv1rnvn_ClpMec-vSvfo5abGyLVThf3wWAf86OnommvGKwYBMDpZ25f_tOougLdO5e92g4eMPGsUWnZE",
  "is_enabled": 1,
  "last_seen_at": "2026-09-03 04:58:15"
}
```

Log iOS validado:

```text
FCM registration token >>> dyevUZqGkEEHv_RCSMEDuZ:APA91bE5...
```

Envio real validado:

```text
El push FCM llego al iPhone como notificacion del sistema.
```

Notas:

- Que el push llegue no implica que la campana interna se actualice automaticamente.
- La campana interna se alimenta de `AuthService.notifications()`, que viene de `GET /api/v1/app/me`.
- Si la app esta en background/cerrada, iOS no siempre ejecuta listeners JS hasta que el usuario abre o toca la notificacion.

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

## Cambios Hechos En App iOS/Ionic

Dependencias:

```text
Agregado: @capacitor-firebase/messaging ^8.5.1
Agregado: firebase ^12.18.0
Actualizado: @capacitor/cli ^8.5.1
Removido: @capacitor/push-notifications
```

Motivo de actualizar `@capacitor/cli`:

```text
El proyecto usa Capacitor 8 con SPM.
@capacitor-firebase/messaging requiere packageOptions symlink para evitar colision de identidad SPM.
Ese soporte requiere Capacitor CLI 8.4.0+.
```

Archivos relevantes:

```text
package.json
package-lock.json
capacitor.config.ts
src/app/app.component.ts
src/app/services/auth.service.ts
src/app/services/push-registration.service.ts
ios/App/App/AppDelegate.swift
ios/App/CapApp-SPM/Package.swift
ios/App/App.xcodeproj/project.xcworkspace/xcshareddata/swiftpm/Package.resolved
```

### capacitor.config.ts

Se alineo:

```text
appId = com.performanceCoachBarret.app
```

Se agrego config para Firebase Messaging:

```ts
FirebaseMessaging: {
  presentationOptions: ['alert', 'badge', 'sound'],
}
```

Se agrego configuracion SPM:

```ts
experimental: {
  ios: {
    spm: {
      packageOptions: {
        '@capacitor-firebase/messaging': {
          symlink: true,
        },
      },
    },
  },
}
```

### AppDelegate.swift

Se agrego:

```swift
import FirebaseCore
```

En `didFinishLaunchingWithOptions`:

```swift
FirebaseApp.configure()
```

Tambien se agregaron los handlers requeridos por el plugin:

```swift
didRegisterForRemoteNotificationsWithDeviceToken
didFailToRegisterForRemoteNotificationsWithError
didReceiveRemoteNotification fetchCompletionHandler
```

Esto corrigio el error:

```text
The default Firebase app has not yet been configured. Add FirebaseApp.configure()
```

### PushRegistrationService

Se creo:

```text
src/app/services/push-registration.service.ts
```

Responsabilidades actuales:

- Instalar listeners una sola vez.
- Pedir permisos con `FirebaseMessaging.checkPermissions()` / `requestPermissions()`.
- Obtener token FCM real con `FirebaseMessaging.getToken()`.
- Escuchar `tokenReceived`.
- Guardar `pending_push_token` en Preferences.
- Registrar token en `app/register-device` si ya hay auth token.
- Reintentar registro despues del login de atleta.
- Refrescar `app/me` al recibir push en foreground.
- Mostrar toast al recibir push en foreground.
- Manejar toque de push con `notificationActionPerformed`.

### AppComponent

Se adelgazo:

```ts
ngOnInit() {
  window.addEventListener('app:membership-expired', this.handleMembershipExpired);
  this.pushRegistration.init();
}
```

### AuthService

Despues del login de atleta ya no duplica el POST del push token.

Ahora dispara:

```ts
window.dispatchEvent(new Event('app:client-login'));
```

`PushRegistrationService` escucha ese evento y ejecuta:

```ts
registerPendingToken()
```

## Comandos Ejecutados / Requeridos En Mac

Instalacion:

```bash
npm install @capacitor-firebase/messaging firebase @capacitor/cli@^8.4.0
```

Remocion del plugin anterior:

```bash
npm uninstall @capacitor/push-notifications
```

Sincronizacion iOS:

```bash
npx cap sync ios
```

Verificacion TypeScript:

```bash
npx tsc --noEmit
```

Resultado:

```text
OK
```

Nota importante:

En el entorno de Codex, `npm run build` cae con codigo `134` durante `ng build` sin mostrar error de Angular. En terminal local del Mac debe ejecutarse:

```bash
npm run build
npx cap copy ios
```

Luego en Xcode:

```text
Product > Clean Build Folder
Run en iPhone real
```

## Comandos De Validacion Backend

Revisar devices de Samuel en produccion, dentro del contenedor Laravel `/var/www`:

```bash
php artisan tinker --execute '$u = \App\Models\UserApp::where("email", "samuel_as13@hotmail.com")->first(); $rows = $u ? DB::table("user_devices")->where("user_id", $u->id)->orderByDesc("id")->get(["id","user_id","platform","token","is_enabled","last_seen_at"]) : collect(); echo json_encode(["user" => $u ? $u->only(["id","client_id","email"]) : null, "devices" => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);'
```

Enviar prueba real:

```bash
php artisan tinker --execute '$u = \App\Models\UserApp::where("email", "samuel_as13@hotmail.com")->firstOrFail(); $n = app(\App\Services\AppNotificationService::class)->sendToUserApp($u, "training_assigned", "Prueba FCM real", "Probando envio real a iOS", ["action" => "open_training", "training_session_id" => 1, "source" => "assigned"]); echo json_encode($n->fresh()->only(["id","user_id","type","status","provider","data","error"]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);'
```

Resultado validado manualmente:

```text
El push llego al iPhone.
```

## Estado Actual De La Campana Interna

La campana interna muestra notificaciones desde:

```text
AuthService.notifications()
```

Ese estado viene de:

```text
GET /api/v1/app/me
```

HTML actual:

```text
src/app/tab1/tab1.page.html
```

Renderiza:

```html
<ion-item *ngFor="let n of notifications()" class="notif-item">
```

Todavia no tiene `(click)`, por eso tocar "Nuevo entrenamiento para ti" dentro de la campana no navega.

## Checkpoints Pendientes

### Checkpoint App 1: Navegacion al tocar push

Estado:

```text
Implementado en codigo, pendiente QA manual con Xcode/iPhone.
```

Cambios ya hechos:

- `PushRegistrationService` escucha `notificationActionPerformed`.
- Normaliza `event.notification.data`.
- Si `data.action != open_training`, navega a `/tabs/tab1`.
- Si viene `assignment_id` y `source != free`, navega directo a `/training-details/{assignment_id}`.
- Si `source=free`, navega a `/training-details/free/{training_session_id}`.
- Si `source=assigned` sin `assignment_id`, llama:

```text
GET /api/v1/app/training-sessions/{trainingSession}/assignment
```

Y navega a:

```text
/training-details/{assignment_id}
```

QA requerido:

1. En Mac:

```bash
npm run build
npx cap copy ios
```

2. En Xcode:

```text
Product > Clean Build Folder
Run en iPhone real
```

3. Enviar push real desde backend.
4. Tocar la notificacion del sistema.
5. Confirmar en consola:

```text
Push accion ejecutada:
```

6. Confirmar navegacion al entrenamiento correcto.

### Checkpoint App 2: Refrescar campana al volver a la app

Objetivo:

Actualizar `AuthService.notifications()` cuando el usuario vuelve a la app desde background/foreground, aunque no haya llegado listener JS de foreground.

Implementacion sugerida:

- Usar listener de `@capacitor/app` para `appStateChange`.
- Cuando `isActive=true` y el actor sea atleta/client, llamar `auth.me()`.
- Evitar loops o llamadas excesivas con debounce/throttle simple.

Criterio de aceptacion:

- Despues de recibir una push con la app en background, al abrir la app la campana refleja las notificaciones de `app/me`.

### Checkpoint App 3: Navegacion desde campana interna

Objetivo:

Tocar una notificacion interna debe usar la misma logica que tocar una push.

Implementacion sugerida:

- Crear/reusar un servicio de navegacion de notificaciones.
- Reusar la logica de `action=open_training`, `source`, `training_session_id`, `assignment_id`.
- En `src/app/tab1/tab1.page.html`, agregar `(click)` al `ion-item`.
- En `src/app/tab1/tab1.page.ts`, cerrar modal y navegar.

Criterio de aceptacion:

- Tocar "Nuevo entrenamiento para ti" en la campana abre el detalle correcto.

### Checkpoint Backend 1: Incluir assignment_id cuando sea posible

Objetivo:

Reducir el lookup movil para entrenamientos asignados.

Estado actual:

- El payload productivo manda `training_session_id`, `scheduled_for`, `source`, `type`, `notification_id`.
- Para asignados puede no mandar `assignment_id`.
- La app ya tiene fallback con `GET /api/v1/app/training-sessions/{trainingSession}/assignment`.

Recomendacion para el agente backend Windows:

- Revisar `AppNotificationService::trainingNotificationPayload()`.
- Cuando el envio sea para un UserApp/Client especifico y exista un `training_assignment`, agregar:

```text
assignment_id
```

- Mantener `training_session_id` siempre, para compatibilidad.

Criterio de aceptacion:

- Push asignado incluye `assignment_id` cuando el backend puede resolverlo sin ambiguedad.
- La app puede navegar directo sin lookup.
- Si no hay `assignment_id`, el endpoint de resolucion sigue funcionando como fallback.

### Checkpoint Backend 2: Alinear endpoint de prueba

Objetivo:

Que `/api/v1/app/test/push` valide el mismo contrato que produccion.

Problema actual documentado:

- `PushTestController` envia directo por Firebase.
- Usa `training_id`.
- Puede no mandar `action=open_training`.
- No necesariamente reutiliza `AppNotificationService`.

Recomendacion:

- Reutilizar `AppNotificationService` o el mismo builder de payload productivo.
- Mandar payload con:

```text
action=open_training
training_session_id
source
scheduled_for
notification_id
assignment_id cuando aplique
```

Criterio de aceptacion:

- Una prueba exitosa de `/app/test/push` valida entrega FCM y navegacion movil real.

### Checkpoint Backend 3: Semantica de leido / visto

Objetivo:

Definir si la campana interna debe marcar notificaciones como vistas/leidas.

Pendiente:

- Confirmar si existe endpoint para marcar `push_notifications` como leidas.
- Si no existe, decidir si se agrega:

```text
POST /api/v1/app/notifications/{notification}/read
POST /api/v1/app/notifications/read-all
```

Criterio de aceptacion:

- La campana puede diferenciar nuevas vs leidas.
- Tocar una notificacion puede marcarla como leida sin romper la navegacion.

## No Hacer Por Ahora

- No cambiar el contrato principal del backend fuera de `AppNotificationService`.
- No borrar registros de produccion ni limpiar `user_devices`.
- No crear tablas paralelas para historial de notificaciones.
- No volver a instalar `@capacitor/push-notifications` mientras se use `@capacitor-firebase/messaging`.
- No mezclar SPM manual de Firebase desde Xcode con la estrategia de Capacitor/SPM salvo que haya un bloqueo especifico.

## Resultado Esperado Del Siguiente Agente

El agente backend en Windows debe poder continuar desde aqui:

1. Confirmar que `user_devices` tiene token iOS real.
2. Confirmar que `AppNotificationService` envia FCM real.
3. Mejorar payload backend agregando `assignment_id` cuando aplique.
4. Alinear `/app/test/push` con el contrato productivo.
5. Coordinar con app para QA de navegacion desde push y campana.

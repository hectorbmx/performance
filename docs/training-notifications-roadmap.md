# Roadmap: Notificaciones Moviles Para Entrenamientos

## Objetivo

Cuando un coach cree un entrenamiento, la app movil debe recibir una notificacion push y la notificacion debe quedar disponible en la lista interna de notificaciones del atleta.

Reglas de destinatarios:

- Entrenamiento libre: notificar a todos los atletas activos del coach.
- Entrenamiento asignado: notificar solo a los atletas objetivo, incluyendo atletas seleccionados directamente y atletas pertenecientes a grupos asignados.
- Si un atleta aparece directo y tambien por grupo, debe recibir una sola notificacion.

## Estado Actual Detectado

Backend Laravel:

- Ya existen las tablas `user_devices` y `push_notifications`.
- Ya existe `AppNotificationService` con metodos para notificaciones de entrenamiento libre y asignado.
- La API de coach (`Api\V1\Coach\TrainingController`) ya dispara notificaciones al crear o actualizar entrenamientos.
- El flujo web Blade (`Coach\TrainingSessionController`) crea entrenamientos y asignaciones, pero no dispara notificaciones.
- Kreait/Firebase esta instalado y `.env` apunta a `FIREBASE_CREDENTIALS=storage/app/firebase/firebase-service-account.json`.

Ionic/Capacitor:

- La app ya solicita permisos push y registra token FCM/APNs via Capacitor.
- El token se envia a `/api/v1/app/register-device` si el usuario ya tiene sesion.
- Si no hay sesion, el token queda como `pending_push_token` y se registra despues del login.
- `app/me` ya refresca la lista interna de notificaciones.
- El listener foreground actualmente muestra un `alert`; falta convertirlo en comportamiento final de producto.

## Contrato De Notificacion

### Libre

- Tipo: `training_free_created`
- Destinatarios: todos los clientes activos del coach.
- Titulo sugerido: `Nuevo entrenamiento libre`
- Cuerpo sugerido: `Tu coach agrego un nuevo entrenamiento: {titulo}`
- Payload:
  - `action`: `open_training`
  - `training_session_id`: id del entrenamiento
  - `scheduled_for`: fecha programada
  - `source`: `free`

### Asignado

- Tipo: `training_assigned`
- Destinatarios: clientes asignados directamente y clientes activos dentro de grupos asignados.
- Titulo sugerido: `Nuevo entrenamiento para ti`
- Cuerpo sugerido: `Tu coach te asigno un entrenamiento: {titulo}`
- Payload:
  - `action`: `open_training`
  - `training_session_id`: id del entrenamiento
  - `scheduled_for`: fecha programada
  - `source`: `assigned`

## Plan De Accion

### Fase 1: Base limpia

- Esperar a que los cambios pendientes se integren a produccion.
- Revisar estado de Git en `coach` y `app`.
- Confirmar que no hay cambios ajenos que afecten `TrainingSessionController`, `AppNotificationService`, `app.component.ts` o `auth.service.ts`.

### Fase 2: Backend como fuente unica

- Centralizar la resolucion de destinatarios en `AppNotificationService`.
- Agregar un metodo unico para notificar entrenamiento creado, por ejemplo `notifyTrainingCreated`.
- Mantener la creacion de registros en `push_notifications`, aunque el atleta no tenga dispositivos activos.
- Enviar push despues de que la transaccion de creacion del entrenamiento haya terminado correctamente.
- No bloquear la creacion del entrenamiento si Firebase falla; registrar `failed` y error.

### Fase 3: Conectar los flujos de creacion

- Ajustar `Api\V1\Coach\TrainingController` para usar el metodo unico del servicio.
- Ajustar `Coach\TrainingSessionController::store` para disparar notificaciones tambien desde el panel web Blade.
- En entrenamiento libre, resolver todos los atletas activos del coach.
- En entrenamiento asignado, resolver atletas directos y grupos, deduplicados.

### Fase 4: App movil

- Reemplazar el `alert` de `pushNotificationReceived` por una actualizacion de estado:
  - refrescar `app/me`, o
  - insertar la notificacion entrante en el estado local.
- En `pushNotificationActionPerformed`, navegar al detalle del entrenamiento cuando `data.action=open_training`.
- Si falta `training_session_id`, abrir el home.
- Mantener el badge/lista interna sincronizada al entrar al home.

### Fase 5: QA

- Registrar un dispositivo real o emulador con un atleta activo.
- Probar `/app/test/push` usando `user_apps.id`.
- Crear entrenamiento libre desde web y confirmar que todos los atletas activos reciben notificacion.
- Crear entrenamiento asignado a un atleta y confirmar que solo ese atleta recibe notificacion.
- Crear entrenamiento asignado a grupo y confirmar que reciben los atletas del grupo.
- Confirmar que un atleta duplicado por cliente directo y grupo recibe una sola notificacion.
- Confirmar que si no hay token activo, se guarda la notificacion sin romper la creacion del entrenamiento.
- Confirmar que tocar la notificacion abre el detalle del entrenamiento.

## Checklist De Produccion

- `FIREBASE_CREDENTIALS` configurado en produccion.
- Archivo service account presente en el servidor.
- Migraciones de `user_devices`, `push_notifications` y foreign keys hacia `user_apps` aplicadas.
- `google-services.json` correcto en Android.
- Certificados/APNs/Firebase correctos para iOS si se valida iPhone.
- Creacion de entrenamiento no depende de que Firebase responda exitosamente.

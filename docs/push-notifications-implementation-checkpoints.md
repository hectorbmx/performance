# Push Notifications: Hallazgos y Roadmap de Implementacion

## Objetivo

Cerrar la implementacion de notificaciones push para entrenamientos sin introducir rutas paralelas ni contratos inconsistentes entre el panel web, la API de coach y la app movil.

Resultado esperado:

- Cuando un coach crea un entrenamiento libre, todos sus atletas activos reciben una notificacion.
- Cuando un coach crea un entrenamiento asignado, solo los atletas objetivo reciben una notificacion.
- Si un atleta esta asignado directo y por grupo, recibe una sola notificacion.
- La notificacion queda guardada en `push_notifications`, aunque el atleta no tenga dispositivo activo.
- Tocar la notificacion abre el detalle correcto en la app.

## Hallazgos del Barrido

### 1. Hay dos flujos de creacion de entrenamientos

- API movil/web coach: `coach/app/Http/Controllers/Api/V1/Coach/TrainingController.php`
- Panel web Blade: `coach/app/Http/Controllers/Coach/TrainingSessionController.php`

Ambos crean/actualizan entrenamientos, secciones y asignaciones, pero no comparten la misma implementacion. El flujo API ya llama `AppNotificationService`; el flujo Blade todavia no.

Riesgo: conectar push en un solo flujo deja comportamiento distinto segun desde donde se cree el entrenamiento.

### 2. La resolucion de destinatarios esta duplicada

La API tiene:

- `syncAssignments()`
- `notificationClientIds()`
- `currentRecipientClientIds()`

El controller Blade hace asignaciones directas a `training_assignments` y `group_training_assignments` manualmente.

Riesgo: una regla de destinatarios corregida en API no se replica automaticamente en web.

### 3. `update()` del controller Blade sincroniza clientes dos veces

En `TrainingSessionController::update()` primero sincroniza clientes sin `scheduled_for` y luego vuelve a sincronizar con `scheduled_for`.

Riesgo: no necesariamente rompe hoy, pero hace ruido para detectar "nuevos destinatarios" al mandar notificaciones despues de cambios.

### 4. El endpoint de prueba no usa el mismo contrato que produccion

Produccion usa payload:

- `action=open_training`
- `training_session_id`
- `source`

`PushTestController` usa `training_id` y no manda `action`.

Riesgo: una prueba exitosa de `/app/test/push` no valida la navegacion real de la app.

### 5. La app necesita `assignment_id` para asignados

Rutas actuales:

- Asignado: `/training-details/:assignmentId`
- Libre: `/training-details/free/:sessionId`

El payload push actual manda `training_session_id`, no `assignment_id`.

Riesgo: con `source=assigned`, la app no sabe abrir el detalle asignado sin resolver primero el assignment correspondiente.

### 6. Hay dos implementaciones de envio FCM

- `AppNotificationService::sendToUserApp()`
- `PushTestController::send()`

Riesgo: el test puede divergir del envio real en payload, manejo de errores y formato de datos.

## Decision Tecnica Recomendada

Antes de conectar mas llamadas push, convertir `AppNotificationService` en la fuente unica para:

- resolver destinatarios,
- deduplicar atletas,
- construir payloads,
- guardar `push_notifications`,
- enviar FCM sin bloquear la creacion del entrenamiento.

No mover toda la creacion de entrenamientos en esta fase. La extraccion completa de `syncAssignments()` puede ser una mejora posterior si se vuelve necesaria. Para este alcance, el punto critico es que la resolucion de destinatarios y el contrato push no dependan del controller.

## Roadmap con Checkpoints

### Fase 0: Barrido y punto de arranque

Estado verificado:

- `AppNotificationService` ya existe y guarda registros en `push_notifications`, pero todavia expone metodos separados para entrenamientos libres y asignados.
- `Api/V1/Coach/TrainingController` ya dispara push al crear y actualizar, pero aun conserva resolucion de destinatarios dentro del controller.
- `Coach/TrainingSessionController` crea, copia y actualiza entrenamientos desde Blade, pero no dispara push y contiene logica propia de asignaciones.
- `PushTestController` envia directo por Firebase y usa `training_id`, por lo que no valida el contrato real de produccion.
- `app.component.ts` registra push tokens, conserva `pending_push_token` y todavia usa un `alert()` temporal al recibir pushes en foreground.
- `auth.service.ts` refresca `app/me` y persiste `notifications`, por lo que ya hay una entrada reusable para sincronizar la lista interna.
- Las rutas moviles ya distinguen `/training-details/:assignmentId` y `/training-details/free/:sessionId`; el payload asignado todavia necesita resolver como llegar al `assignment_id`.

Regla para empezar:

- No agregar nuevas llamadas push directamente en controllers hasta centralizar contrato, destinatarios y payload en un modulo reusable.
- Si el servicio actual queda mezclando demasiadas responsabilidades, pausar antes de codificar y decidir si se separa un modulo independiente para contrato/destinatarios/envio.

### Checkpoint 1: Contrato unico de notificacion

Cambios:

- Agregar a `AppNotificationService` un metodo unico, por ejemplo:
  - `notifyTrainingCreated(TrainingSession $training, int $coachId, array $clientIds = [], array $groupIds = []): void`
- Mover o replicar de forma centralizada la resolucion de grupos/directos hacia el servicio.
- Mantener los metodos actuales como wrappers si ayuda a reducir el cambio inicial.

Criterios de aceptacion:

- Entrenamiento libre resuelve todos los clientes activos del coach.
- Entrenamiento asignado resuelve directos + grupos.
- Deduplica atletas repetidos.
- No se crean notificaciones duplicadas para el mismo atleta dentro de una misma ejecucion.

Validacion minima:

- `php -l app/Services/AppNotificationService.php`
- Prueba puntual con datos falsos o tinker para confirmar IDs deduplicados.

### Checkpoint 2: Alinear la API de coach al contrato unico

Cambios:

- Reemplazar llamadas separadas en `Api/V1/Coach/TrainingController::store()`:
  - `notifyFreeTrainingCreated()`
  - `notifyTrainingAssigned()`
- Usar el metodo unico despues de la transaccion.
- En `update()`, conservar la regla actual de notificar solo transiciones/nuevos destinatarios, pero apoyarse en metodos compartidos para resolver destinatarios.

Criterios de aceptacion:

- Crear entrenamiento desde API sigue mandando notificaciones.
- Firebase sigue sin bloquear la creacion si falla.
- No se altera el payload esperado por la app.

Validacion minima:

- `php -l app/Http/Controllers/Api/V1/Coach/TrainingController.php`
- Prueba manual de create assigned/free por API si hay sesion/token disponible.

### Checkpoint 3: Conectar el panel web Blade

Cambios:

- Inyectar o resolver `AppNotificationService` en `Coach/TrainingSessionController`.
- En `store()`, mandar notificacion despues de que la transaccion termine correctamente.
- Usar el mismo contrato que la API:
  - `visibility=free`: todos los atletas activos.
  - `visibility=assigned`: directos + grupos.

Criterios de aceptacion:

- Crear entrenamiento desde web guarda `push_notifications`.
- Crear entrenamiento libre desde web notifica a todos los atletas activos.
- Crear entrenamiento asignado desde web respeta directos/grupos y deduplica.

Validacion minima:

- `php -l app/Http/Controllers/Coach/TrainingSessionController.php`
- Crear entrenamiento web con un atleta sin dispositivo y confirmar que no falla.
- Revisar tabla `push_notifications` para confirmar registro.

### Checkpoint 4: Limpiar ruido de asignaciones del controller Blade

Cambios:

- Quitar el doble `sync()` de clientes en `TrainingSessionController::update()`.
- Mantener un solo punto que sincronice `status` y `scheduled_for`.
- Revisar que grupos conserven `scheduled_for`.

Criterios de aceptacion:

- Actualizar entrenamiento asignado no pierde fecha de asignacion.
- Cambiar a libre limpia directos y grupos.
- Cambiar de libre a asignado conserva nuevos destinatarios.

Validacion minima:

- Prueba web de update assigned/free.
- Revisar `training_assignments` y `group_training_assignments`.

### Checkpoint 5: Alinear `/app/test/push`

Cambios:

- Cambiar payload de prueba para usar:
  - `action=open_training`
  - `training_session_id`
  - `source`
  - `notification_id` si aplica
- Ideal: que `PushTestController` use `AppNotificationService` o una funcion compartida de payload.

Criterios de aceptacion:

- Una prueba de `/app/test/push` valida el mismo contrato que produccion.
- La app recibe datos equivalentes a una notificacion real.

Validacion minima:

- `php -l app/Http/Controllers/Api/V1/App/PushTestController.php`
- Enviar push a un dispositivo registrado.

### Checkpoint 6: Navegacion movil desde push

Cambios:

- Reemplazar el `alert()` temporal en `app/src/app/app.component.ts`.
- En foreground:
  - refrescar `app/me`, o
  - insertar la notificacion entrante en estado local.
- En `pushNotificationActionPerformed`:
  - si `source=free` y hay `training_session_id`, navegar a `/training-details/free/:sessionId`.
  - si `source=assigned`, resolver `assignment_id` antes de navegar o ajustar backend para incluirlo.

Decision pendiente:

- Opcion A: incluir `assignment_id` en payload cuando el entrenamiento es asignado.
- Opcion B: crear/usar endpoint de lookup por `training_session_id` para obtener el assignment del atleta autenticado.

Recomendacion: opcion A para directos si ya existe assignment; opcion B o creacion diferida para grupales si el assignment se materializa cuando el atleta consulta entrenamientos.

Criterios de aceptacion:

- Tocar una push libre abre detalle libre.
- Tocar una push asignada abre detalle asignado.
- Si falta informacion, abre Home sin romper la app.
- La lista interna de notificaciones queda actualizada.

Validacion minima:

- `ng build`
- Prueba en dispositivo/emulador con app instalada.

### Checkpoint 7: QA integral

Casos:

- Registrar token con atleta autenticado.
- Crear entrenamiento libre desde API.
- Crear entrenamiento libre desde web.
- Crear entrenamiento asignado directo desde API.
- Crear entrenamiento asignado directo desde web.
- Crear entrenamiento asignado a grupo.
- Probar atleta duplicado directo + grupo.
- Probar atleta sin dispositivo activo.
- Probar Firebase fallando o credenciales ausentes.
- Tocar notificacion y validar navegacion.

Evidencia minima:

- Registro en `push_notifications`.
- Estado `sent`, `queued` o `failed` segun caso.
- Error guardado si Firebase falla.
- Creacion de entrenamiento exitosa aunque FCM falle.

## Orden de Trabajo Sugerido

1. Checkpoint 1
2. Checkpoint 2
3. Checkpoint 3
4. Checkpoint 5
5. Checkpoint 6
6. Checkpoint 4
7. Checkpoint 7

Motivo: primero se estabiliza el contrato, luego se conectan los productores de notificaciones, despues se alinea la prueba y finalmente se mejora la experiencia movil. La limpieza del doble sync puede hacerse antes si aparece como bloqueo, pero no debe mezclar cambios grandes de UI o Firebase.

## Archivos Principales

Backend:

- `coach/app/Services/AppNotificationService.php`
- `coach/app/Http/Controllers/Api/V1/Coach/TrainingController.php`
- `coach/app/Http/Controllers/Coach/TrainingSessionController.php`
- `coach/app/Http/Controllers/Api/V1/App/PushTestController.php`
- `coach/routes/api.php`

App movil:

- `app/src/app/app.component.ts`
- `app/src/app/services/auth.service.ts`
- `app/src/app/services/training-api.service.ts`
- `app/src/app/pages/training-details/training-details.page.ts`
- `app/src/app/app.routes.ts`

## Pendientes de Decision

- Definir si el payload asignado debe incluir `assignment_id`.
- Definir si las notificaciones de `update()` deben enviarse solo a nuevos destinatarios o tambien cuando cambia fecha/titulo.
- Definir si las notificaciones libres deben crear assignment al tocar la push o solo al iniciar el entrenamiento, como ocurre actualmente.
- Definir si `/app/test/push` queda disponible solo en entorno local/staging o protegido por rol/admin.

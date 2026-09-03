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

Ambos crean/actualizan entrenamientos, secciones y asignaciones, pero no comparten la misma implementacion. Al iniciar este roadmap, el flujo API ya llamaba `AppNotificationService` y el flujo Blade todavia no; Blade quedo conectado en Checkpoint 3.

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

### Punto de reanudacion - 2026-09-02 23:45

Ultimo estado antes de apagar:

- Checkpoint 1 completado: contrato unico de entrenamiento en `AppNotificationService`.
- Checkpoint 2 completado: API de coach usa `notifyTrainingCreated()` y ya no duplica `notificationClientIds()`.
- Checkpoint 3 completado: panel web Blade crea notificaciones despues de confirmar la transaccion.
- Checkpoint 4 completado: `TrainingSessionController::update()` ya no ejecuta doble sync de atletas asignados.
- QA Tinker no destructivo completado: se crearon registros `push_notifications` de prueba para entrenamiento libre/asignado y se valido deduplicacion directo + grupo.

Archivos modificados pendientes de commit:

- `coach/app/Services/AppNotificationService.php`
- `coach/app/Http/Controllers/Api/V1/Coach/TrainingController.php`
- `coach/app/Http/Controllers/Coach/TrainingSessionController.php`
- `coach/docs/push-notifications-implementation-checkpoints.md`
- `app/docs/push-notifications-implementation-checkpoints.md`

Siguiente paso al reiniciar:

- Ejecutar Checkpoint 5: alinear `/api/v1/app/test/push`.
- Alcance recomendado: cambiar `PushTestController` para validar el mismo contrato que produccion y reutilizar `AppNotificationService` o una funcion compartida de payload/envio.
- No empezar todavia Checkpoint 6 ni notificaciones de membresia/no-entreno hasta cerrar Checkpoint 5.

Pendientes de QA manual:

- Crear/actualizar entrenamiento web `assigned/free` y revisar `training_assignments`, `group_training_assignments` y `push_notifications`.
- Validar entrega real FCM con un `UserApp` que tenga `user_devices.token` activo.
- Validar navegacion movil desde push en Checkpoint 6.

Decisiones futuras ya identificadas:

- Notificaciones de modificacion para fecha/titulo/contenido a atletas ya existentes.
- Payload asignado con `assignment_id` vs lookup autenticado por `training_session_id`.
- Productores programados separados para membresia proxima a vencer y entrenamiento asignado no marcado como entrenado.

### Fase 0: Barrido y punto de arranque

Estado verificado:

- `AppNotificationService` ya existe y guarda registros en `push_notifications`, pero todavia expone metodos separados para entrenamientos libres y asignados.
- `Api/V1/Coach/TrainingController` ya dispara push al crear y actualizar, pero aun conserva resolucion de destinatarios dentro del controller.
- `Coach/TrainingSessionController` crea, copia y actualiza entrenamientos desde Blade, pero no dispara push y contiene logica propia de asignaciones.
- `PushTestController` envia directo por Firebase y usa `training_id`, por lo que no valida el contrato real de produccion.
- `app.component.ts` registra push tokens, conserva `pending_push_token` y todavia usa un `alert()` temporal al recibir pushes en foreground.
- `auth.service.ts` refresca `app/me` y persiste `notifications`, por lo que ya hay una entrada reusable para sincronizar la lista interna.
- Las rutas moviles ya distinguen `/training-details/:assignmentId` y `/training-details/free/:sessionId`; el payload asignado todavia necesita resolver como llegar al `assignment_id`.

#### Barrido Fase 0 - 2026-09-02

Estado Git:

- `coach`: rama `main`, limpio contra `origin/main`.
- `app`: rama `master`, limpio contra `origin/master`.

Backend verificado:

- Al iniciar la Fase 1, `AppNotificationService` era el punto real de guardado/envio push, pero exponia `notifyTrainingAssigned()` y `notifyFreeTrainingCreated()` por separado; `notifyTrainingCreated()` fue agregado en Checkpoint 1.
- `sendToUserApp()` crea el registro en `push_notifications` antes de consultar dispositivos; si no hay tokens activos, deja la notificacion en `queued` y no bloquea el flujo.
- El payload productivo ya incluye `action=open_training`, `training_session_id`, `scheduled_for`, `source`, `type` y `notification_id`, pero para asignados todavia no incluye `assignment_id`.
- `Api/V1/Coach/TrainingController::store()` crea el entrenamiento dentro de transaccion y luego llama `AppNotificationService`; para asignados aun calcula destinatarios desde el controller con `notificationClientIds()`.
- `Api/V1/Coach/TrainingController::update()` conserva la regla de notificar solo transiciones/nuevos destinatarios, pero tambien depende de `currentRecipientClientIds()` y `notificationClientIds()` dentro del controller.
- Al iniciar Checkpoint 3, `Coach/TrainingSessionController::store()` creaba entrenamientos desde Blade y registraba asignaciones directas/grupales, pero no llamaba `AppNotificationService`; quedo conectado despues de la transaccion.
- `Coach/TrainingSessionController::update()` mantiene doble sincronizacion de atletas asignados: primero sin `scheduled_for` y despues con `scheduled_for`.
- `/api/v1/app/test/push` sigue autenticado por Sanctum, pero construye FCM directo desde `PushTestController`, usa `training_id` y no valida `action=open_training`.
- Kreait/Firebase esta instalado en `composer.json` y existen migraciones para `user_devices`, `push_notifications` y alineacion de llaves hacia `user_apps`.

App movil verificada:

- `app.component.ts` registra token nativo, guarda `pending_push_token` y lo envia a `/app/register-device` si ya hay token de sesion.
- `AuthService` tambien registra `pending_push_token` despues del login y persiste `notifications` desde `app/me`.
- `pushNotificationReceived` todavia usa `alert()` temporal y no refresca `app/me` ni actualiza estado local.
- `pushNotificationActionPerformed` solo escribe en consola; todavia no navega segun `action`, `source` o `training_session_id`.
- Las rutas siguen disponibles como `/training-details/:assignmentId` para asignados y `/training-details/free/:sessionId` para libres.
- El detalle libre ya puede crear/obtener assignment al abrir `/training-details/free/:sessionId`, por lo que el flujo libre puede navegar solo con `training_session_id`.

Conclusion para Fase 1:

- La Fase 1 debe empezar en backend, centralizando contrato y resolucion de destinatarios en `AppNotificationService`.
- No conviene conectar Blade ni cambiar navegacion movil antes de cerrar el metodo unico y la estrategia de `assignment_id` para asignados.
- Decision ejecutada en Checkpoint 1: agregar `notifyTrainingCreated(TrainingSession $training, int $coachId, array $clientIds = [], array $groupIds = []): void` y helpers publicos/protegidos para resolver destinatarios deduplicados.

Regla para empezar:

- No agregar nuevas llamadas push directamente en controllers hasta centralizar contrato, destinatarios y payload en un modulo reusable.
- Si el servicio actual queda mezclando demasiadas responsabilidades, pausar antes de codificar y decidir si se separa un modulo independiente para contrato/destinatarios/envio.

### Checkpoint 1: Contrato unico de notificacion

Estado 2026-09-02: implementado en backend.

Cambios:

- Agregado a `AppNotificationService` un metodo unico:
  - `notifyTrainingCreated(TrainingSession $training, int $coachId, array $clientIds = [], array $groupIds = []): void`
- Centralizada la resolucion de destinatarios en:
  - `trainingRecipientClientIds()`
  - `activeCoachClientIds()`
  - `assignedTrainingClientIds()`
  - `activeCoachClientIdsByIds()`
  - `activeCoachClientIdsForGroups()`
- Centralizada la construccion de payload de entrenamientos en `trainingNotificationPayload()`.
- Centralizada la entrega de notificaciones de entrenamiento en `sendTrainingCreatedNotifications()`.
- Los metodos actuales `notifyFreeTrainingCreated()` y `notifyTrainingAssigned()` se mantienen como wrappers para no romper los controllers existentes antes de Fase 2.

Criterios de aceptacion:

- Entrenamiento libre resuelve todos los clientes activos del coach desde el servicio.
- Entrenamiento asignado resuelve directos + grupos desde el servicio.
- Deduplica IDs de atletas repetidos antes de buscar `UserApp`.
- Deduplica `UserApp` por `client_id` antes de enviar, evitando mas de una notificacion por atleta dentro de la misma ejecucion.
- Mantiene el comportamiento existente de guardar `push_notifications` aunque no haya dispositivos activos.

Validacion minima:

- Ejecutado: `php -l app/Services/AppNotificationService.php` sin errores.
- No se ejecuto PHPUnit porque la suite usa `RefreshDatabase` sobre MySQL `coach_testing`; requiere autorizacion explicita antes de operaciones destructivas.

Pendiente para Fase 2:

- Reemplazar en `Api/V1/Coach/TrainingController` las llamadas separadas por `notifyTrainingCreated()`.
- Decidir mas adelante si `trainingRecipientClientIds()` debe quedarse en este servicio o moverse a un resolver dedicado si crece la logica de asignaciones.

### Checkpoint 2: Alinear la API de coach al contrato unico

Estado 2026-09-02: implementado en backend.

Cambios:

- Reemplazadas llamadas separadas en `Api/V1/Coach/TrainingController::store()`:
  - `notifyFreeTrainingCreated()`
  - `notifyTrainingAssigned()`
- `store()` usa `notifyTrainingCreated()` despues de la transaccion, pasando `assigned_client_ids` y `assigned_group_ids` al servicio.
- `update()` conserva la regla actual de notificar solo transiciones/nuevos destinatarios.
- La resolucion de destinatarios actuales y nuevos en `update()` ahora se apoya en `AppNotificationService::trainingRecipientClientIds()`.
- Se elimino de `TrainingController` el helper duplicado `notificationClientIds()`.

Criterios de aceptacion:

- Crear entrenamiento desde API sigue mandando notificaciones mediante el contrato unico.
- Firebase sigue sin bloquear la creacion si falla, porque el envio continua centralizado en `AppNotificationService::sendToUserApp()`.
- No se altera el payload esperado por la app en esta fase.
- El controller ya no duplica la query de clientes directos + grupos para notificaciones.

Validacion minima:

- Ejecutado: `php -l app/Http/Controllers/Api/V1/Coach/TrainingController.php` sin errores.
- Ejecutado previamente para esta fase: `php -l app/Services/AppNotificationService.php` sin errores.
- No se ejecuto PHPUnit porque la suite usa `RefreshDatabase` sobre MySQL `coach_testing`; requiere autorizacion explicita antes de operaciones destructivas.

Pendiente para fases posteriores:

- La modificacion de fecha/titulo para atletas ya existentes todavia no dispara notificacion; por ahora `update()` mantiene la regla previa de solo nuevos destinatarios.
- Si el atleta asignado necesita abrir detalle directo desde push, falta resolver `assignment_id` en el payload o por lookup autenticado.
- El flujo Blade quedo conectado en Checkpoint 3.

### Checkpoint 3: Conectar el panel web Blade

Estado 2026-09-02: implementado en backend.

Cambios:

- Inyectado `AppNotificationService` en `Coach/TrainingSessionController::store()`.
- `store()` ahora devuelve el `TrainingSession` desde `DB::transaction()` y hace el redirect despues de notificar.
- La notificacion se manda despues de que la transaccion termina correctamente.
- Usa el mismo contrato que la API mediante `notifyTrainingCreated()`:
  - `visibility=free`: todos los atletas activos.
  - `visibility=assigned`: directos + grupos.

Criterios de aceptacion:

- Crear entrenamiento desde web queda conectado al flujo que guarda `push_notifications`.
- Crear entrenamiento libre desde web notifica a todos los atletas activos mediante el servicio central.
- Crear entrenamiento asignado desde web respeta directos/grupos y deduplica mediante el servicio central.
- Si la transaccion falla, no se manda notificacion.
- Se conservan las redirecciones existentes a listado general o calendario del atleta segun `return_client_id`.

Validacion minima:

- Ejecutado: `php -l app/Http/Controllers/Coach/TrainingSessionController.php` sin errores.
- Pendiente QA manual: crear entrenamiento web con un atleta sin dispositivo y confirmar que no falla.
- Pendiente QA manual: revisar tabla `push_notifications` para confirmar registro.

### Checkpoint 4: Limpiar ruido de asignaciones del controller Blade

Estado 2026-09-02: implementado en backend.

Cambios:

- Quitado el primer `assignedClients()->sync()` que solo guardaba `status`.
- Eliminado el `update()` masivo posterior sobre `training_assignments.scheduled_for`.
- Se mantiene un solo `assignedClients()->sync()` que guarda `status = scheduled` y `scheduled_for`.
- La sincronizacion de `GroupTrainingAssignment` conserva `scheduled_for` al crear grupos nuevos y al actualizar los existentes.

Criterios de aceptacion:

- Actualizar entrenamiento asignado ya no ejecuta doble sync de atletas.
- Cambiar a libre sigue limpiando directos y grupos.
- Cambiar de libre a asignado conserva destinatarios con `status` y `scheduled_for`.

Validacion minima:

- Ejecutado: `php -l app/Http/Controllers/Coach/TrainingSessionController.php` sin errores.
- Pendiente QA manual: prueba web de update assigned/free.
- Pendiente QA manual: revisar `training_assignments` y `group_training_assignments`.

### QA Tinker - 2026-09-02

Prueba no destructiva ejecutada con `php artisan tinker --execute` usando datos controlados:

- Coach de prueba: `codex-push-coach@example.test` (`coach_id=8`).
- Atletas de prueba: `client_id` 9, 10 y 11 con `UserApp` activo.
- Grupo de prueba: `Codex Push Test Group` (`group_id=1`) con los tres atletas.
- Entrenamiento libre creado: `training_session_id=9`.
- Entrenamiento asignado creado: `training_session_id=10`.
- Caso asignado probado con duplicado directo y grupo: directos `[9, 10, 10]` + grupo `[1]`.

Resultado:

- `trainingRecipientClientIds()` resolvio destinatarios asignados como `[9, 10, 11]`.
- Se crearon 6 registros en `push_notifications`:
  - 3 `training_free_created`.
  - 3 `training_assigned`.
- Todos quedaron `queued` porque no habia dispositivos activos con token para esos `UserApp`.
- El payload guardado incluyo `action=open_training`, `training_session_id`, `scheduled_for` y `source`.
- No se borraron registros ni se ejecuto reset/migracion destructiva.

### Checkpoint 5: Alinear `/app/test/push`

Estado 2026-09-03: implementado en backend.

Cambios:

- `PushTestController` ya no construye FCM directo con `CloudMessage`.
- `PushTestController` ahora inyecta `AppNotificationService` y usa `sendToUserApp()`.
- Cambiado payload de prueba para usar:
  - `action=open_training`
  - `training_session_id`
  - `source`
  - `notification_id` si aplica
- Se mantiene `training_id` como alias legacy de `training_session_id` para no romper pruebas anteriores.
- El endpoint ahora crea registro en `push_notifications` aunque el atleta no tenga dispositivo activo, igual que produccion.

Criterios de aceptacion:

- Una prueba de `/app/test/push` valida el mismo contrato que produccion.
- La app recibe datos equivalentes a una notificacion real.
- El endpoint ya no diverge del envio productivo en persistencia, payload base ni manejo de ausencia de tokens.

Validacion minima:

- Ejecutado: `php -l app/Http/Controllers/Api/V1/App/PushTestController.php` sin errores.
- Ejecutado Tinker no destructivo contra `PushTestController::send()` con `user_app_id=7`, `training_session_id=10`, `source=assigned`.
- Resultado Tinker: respuesta `200`, notificacion `id=12`, tipo `training_assigned`, estado `queued`, payload `action=open_training`, `training_session_id=10`, `source=assigned`.
- Pendiente QA real: enviar push a un dispositivo registrado con token activo para validar `sent`/`failed` de FCM.

### Checkpoint 6: Navegacion movil desde push

Estado 2026-09-02: implementado backend + app movil, pendiente QA en dispositivo.

Cambios:

- Reemplazado el `alert()` temporal en `app/src/app/app.component.ts`.
- En foreground, la app muestra toast y refresca `app/me` para actualizar el estado local de notificaciones.
- En `pushNotificationActionPerformed`, la app lee `data.action=open_training`.
- Si `source=free` y hay `training_session_id`, navega a `/training-details/free/:sessionId`.
- Si `source=assigned`, llama el lookup autenticado `GET /api/v1/app/training-sessions/{trainingSession}/assignment` con `scheduled_for` opcional y navega a `/training-details/:assignmentId`.
- Si faltan datos o el lookup falla, cae a `/tabs/tab1` sin romper la app.
- Agregado `TrainingApiService.resolveAssignment()`.
- Agregado `TrainingSessionsController::resolveAssignment()` en Laravel.
- Agregada ruta protegida por `client.membership`: `GET /api/v1/app/training-sessions/{trainingSession}/assignment`.

Decision pendiente:

- Decision tomada: usar endpoint de lookup autenticado por `training_session_id` y `scheduled_for` opcional.

Motivo: desacopla el payload push de IDs de assignment, funciona mejor para directos y grupos, y permite materializar el assignment grupal si todavia no existe.

Criterios de aceptacion:

- Tocar una push libre abre detalle libre.
- Tocar una push asignada abre detalle asignado despues de resolver `assignment_id`.
- Si falta informacion, abre Home sin romper la app.
- La lista interna de notificaciones queda actualizada mediante `app/me` cuando la push llega en foreground.

Validacion minima:

- Ejecutado: `php -l app/Http/Controllers/Api/V1/App/Client/TrainingSessionsController.php` sin errores.
- Ejecutado: `php -l routes/api.php` sin errores.
- Ejecutado: `php artisan route:list --path=api/v1/app/training-sessions`; la ruta `/assignment` aparece registrada.
- Ejecutado: `ng build` con el Node bundled; compila correctamente con warnings preexistentes de imports no usados/budgets SCSS.
- Ejecutado Tinker no destructivo contra `TrainingSessionsController::resolveAssignment()` con `user_app_id=7`, `training_session_id=10`, `scheduled_for=2026-09-04`; respuesta `200`, `assignment_id=10`.
- Pendiente QA real: tocar notificacion en dispositivo/emulador con app instalada.

### Checkpoint 7: QA integral

Estado 2026-09-02: QA Laravel amplio ejecutado con datos controlados; pendiente QA FCM/dispositivo real.

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

#### QA integral Laravel - 2026-09-03

Prueba no destructiva ejecutada con Tinker usando script local:

- Script: `coach/.codex-tmp/push_integral_qa.php`.
- Run: `20260903021257`.
- Coach creado: `coach_id=9`.
- Atletas activos creados: `client_id` 12, 13, 14 y 15.
- Atleta inactivo creado: `client_id=16`.
- Grupo creado: `group_id=2`, con atletas 13, 14 y el inactivo 16.
- Entrenamientos creados:
  - Libre: `training_session_id=11`.
  - Asignado directo: `training_session_id=12`.
  - Asignado grupo/directo duplicado: `training_session_id=13`.
  - Caso update solo nuevos destinatarios: `training_session_id=14`.

Resultados verificados:

- Libre creo 4 notificaciones `training_free_created` para los 4 atletas activos del coach.
- Asignado directo con duplicado e inactivo creo 2 notificaciones para destinatarios activos unicos.
- Asignado grupo/directo resolvio destinatarios `[12, 13, 14]`, deduplicando directo + grupo y excluyendo inactivo.
- Caso update resolvio previo `[12]`, nuevo `[12, 15]` y notifico solo diff `[15]`.
- Lookup asignado respondio `200` para `training_session_id=13`, `scheduled_for=2026-09-06`, devolviendo `assignment_id=11`.
- `/app/test/push` respondio `200` y creo `push_notifications.id=23` con payload `action=open_training`, `training_session_id=12`, `source=assigned`.
- Total de notificaciones creadas en esta corrida: 11.
- Conteo por tipo: 4 `training_free_created`, 7 `training_assigned`.
- Conteo por estado: 11 `queued`.
- No se borraron registros ni se ejecuto reset/migracion destructiva.

Pendiente:

- Registrar/usar un `user_devices.token` real para validar estado `sent` o `failed` contra FCM.
- Probar en dispositivo/emulador que tocar push libre/asignada navega al detalle correcto.

## Plan Extendido De Notificaciones

### Estado actual implementado

Entrenamientos:

- Creacion de entrenamiento libre desde API y Blade.
- Creacion de entrenamiento asignado desde API y Blade.
- Notificacion a nuevos destinatarios cuando API actualiza asignaciones.
- Deduplicacion de atletas directos + grupos.
- Persistencia en `push_notifications` aunque no haya dispositivo activo.
- Endpoint `/app/test/push` alineado al contrato real.
- Navegacion movil desde push libre/asignada con lookup autenticado de `assignment_id`.

Membresia:

- `AppNotificationService::forUserApp()` ya genera avisos internos derivados de membresia al consultar `app/me`.
- `ClientMembershipAccessService` ya centraliza el estado de acceso del atleta.
- `reminder_days_before` ya existe en planes/membresias y puede reutilizarse como configuracion del periodo de aviso.

No entreno / cumplimiento:

- `training_assignments.status` ya registra estados como `scheduled`, `in_progress`, `completed`, `skipped`, `cancelled`.
- `scheduled_for` ya existe en asignaciones directas y grupales.
- Aun no existe productor programado que genere push por entrenamiento no marcado como entrenado.

### Reglas de reuso y desacoplamiento

- Todo registro/envio push debe pasar por `AppNotificationService::sendToUserApp()` o por un servicio dedicado que lo use internamente.
- No agregar FCM directo en controllers, comandos, jobs ni endpoints de prueba.
- Los controllers solo deben publicar eventos de dominio o llamar metodos de aplicacion delgados; no deben construir queries complejas de destinatarios si ya existe un resolver.
- Separar productores por dominio:
  - entrenamientos,
  - membresias,
  - cumplimiento/no entreno,
  - feedback/comentarios si se agrega despues.
- Reutilizar `push_notifications` como historial unico del atleta; si se necesita idempotencia, agregar una clave/metadata antes de crear tablas paralelas.
- Reutilizar `user_devices` para entrega FCM; si no hay tokens activos, crear el registro `queued` y no bloquear el flujo.
- Las notificaciones programadas deben vivir en comandos/jobs idempotentes y no en requests de lectura como `app/me`.
- Cualquier productor programado debe evitar duplicados por atleta + tipo + entidad + fecha.
- La app movil debe navegar por contratos estables (`action`, `training_session_id`, `source`, `scheduled_for`) y resolver IDs internos por API cuando sea necesario.
- Antes de mover logica a una clase nueva, verificar si el servicio existente ya expresa bien la responsabilidad; si crece demasiado, extraer un servicio por dominio.

### Familias nuevas a implementar

Entrenamientos:

- `training_updated`: avisar cuando cambian datos relevantes para atletas ya existentes.
- `training_rescheduled`: avisar cuando cambia `scheduled_at` / `scheduled_for`.
- `training_cancelled`: avisar cuando se elimina/cancela un entrenamiento asignado o libre.
- `training_reminder`: recordatorio antes del entrenamiento si se decide configurar horario.

Membresia:

- `membership_expiring`: push real cuando la membresia esta dentro de `reminder_days_before`.
- `membership_grace`: push real cuando la membresia entra en periodo de gracia.
- `membership_expired`: push real cuando ya no hay acceso operativo.
- `membership_payment_pending`: push real si hay pago pendiente y no se ha recordado recientemente.

Cumplimiento / no entreno:

- `training_not_started`: el atleta no inicio un entrenamiento asignado despues de una hora configurable.
- `training_not_completed`: el atleta inicio o tenia asignado un entrenamiento y no lo marco como completado al cierre del dia o al dia siguiente.

Feedback / coach:

- `training_feedback_created`: el coach dejo feedback o revision sobre un entrenamiento.
- `coach_message_created`: si se agrega mensajeria, mensaje nuevo del coach.

### Checkpoints futuros

#### Checkpoint 8: Clasificar tipos y contrato extendido

Objetivo:

- Definir tipos oficiales, payload minimo, prioridad y destino de navegacion para entrenamientos, membresias y no-entreno.

Cambios:

- Documentar matriz `type/action/source/entity_id/scheduled_for`.
- Confirmar que la app puede manejar cada `action` sin rutas ambiguas.
- Definir reglas de no duplicado por tipo.

Validacion:

- Revisar `AppNotificationService::storedNotifications()` y `app.component.ts`.
- No requiere cambios de base salvo que se decida idempotencia persistente.

#### Checkpoint 9: Servicio de notificaciones de entrenamiento actualizado

Objetivo:

- Separar notificaciones de entrenamiento creado/modificado/reprogramado/cancelado sin cargar mas el controller.

Cambios:

- Crear o extraer `TrainingNotificationService` si `AppNotificationService` queda demasiado grande.
- Implementar `notifyTrainingUpdated()` y/o `notifyTrainingRescheduled()`.
- Mantener `notifyTrainingCreated()` compatible.

Validacion:

- `php -l` de servicios/controladores.
- Tinker no destructivo con cambio de fecha/titulo.

#### Checkpoint 10: Configuracion de avisos de membresia

Objetivo:

- Permitir que el coach defina el periodo de aviso por plan/membresia reutilizando `reminder_days_before`.

Cambios:

- Revisar UI Laravel/Ionic coach donde se crean/actualizan planes.
- Confirmar que `reminder_days_before` se muestra y persiste.
- No duplicar configuracion si ya existe en `CoachClientPlan`.

Validacion:

- `php -l` backend.
- Build app si se toca Ionic coach.
- Prueba manual de crear/editar plan.

#### Checkpoint 11: Productor programado de membresias

Objetivo:

- Crear push real para membresias proximas a vencer, vencidas o en gracia.

Cambios:

- Crear comando Laravel idempotente, por ejemplo `notifications:memberships`.
- Usar `ClientMembershipAccessService` y `reminder_days_before`.
- Crear registros en `push_notifications` solo si no existe uno equivalente reciente.

Validacion:

- `php artisan list` o `route/command` equivalente.
- Tinker/comando no destructivo con datos controlados.
- Verificar que no se dupliquen avisos al ejecutar dos veces.

#### Checkpoint 12: Productor programado de no-entreno

Objetivo:

- Avisar al atleta cuando no marco como entrenado un entrenamiento asignado.

Cambios:

- Crear comando Laravel idempotente, por ejemplo `notifications:training-compliance`.
- Buscar `training_assignments` con `scheduled_for` vencido y `status` no completado/cancelado/skipped.
- Definir ventana inicial conservadora: avisar al dia siguiente de `scheduled_for`.
- Excluir entrenamientos libres salvo decision explicita.

Validacion:

- Tinker/comando no destructivo con asignaciones `scheduled` pasadas.
- Ejecutar dos veces y confirmar que no duplica.

#### Checkpoint 13: Navegacion movil para membresia y no-entreno

Objetivo:

- Asegurar que la app maneje `open_membership` y nuevos tipos de entrenamiento/no-entreno.

Cambios:

- Extender `app.component.ts` para nuevas acciones.
- Reutilizar `AuthService.me()` para refrescar estado local.
- Navegar a membresias o detalle de entrenamiento segun payload.

Validacion:

- `ng build`.
- QA en dispositivo/emulador.

#### Checkpoint 14: QA integral FCM real

Objetivo:

- Validar entrega real con token activo y navegacion en dispositivo.

Casos:

- Token iOS/Android registrado.
- Push de entrenamiento libre.
- Push de entrenamiento asignado.
- Push de membresia proxima a vencer.
- Push de no-entreno.
- Estados `sent`/`failed` con error persistido.

Validacion:

- Captura/registro de `user_devices`.
- Captura/registro de `push_notifications`.
- Prueba tocando notificaciones en dispositivo.

## Orden de Trabajo Sugerido

1. Checkpoint 1
2. Checkpoint 2
3. Checkpoint 3
4. Checkpoint 4
5. Checkpoint 5
6. Checkpoint 6
7. Checkpoint 7
8. Checkpoint 8
9. Checkpoint 9
10. Checkpoint 10
11. Checkpoint 11
12. Checkpoint 12
13. Checkpoint 13
14. Checkpoint 14

Motivo: primero se estabiliza el contrato, luego se conectan los productores de notificaciones, despues se limpia el flujo web que podria generar ruido en cambios de asignacion, luego se alinea la prueba y finalmente se mejora la experiencia movil. Despues del QA base, se abre el plan extendido por dominios para evitar mezclar entrenamientos, membresias y cumplimiento en un solo bloque.

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

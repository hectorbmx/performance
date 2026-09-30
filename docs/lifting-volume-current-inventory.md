# Inventario Actual: Volumen Y Reps En Lifting

Fecha del barrido: 2026-09-29.

Este documento registra lo que ya existe en CoachSaaS/Training Flow para construir el contador de volumen y reps de lifting. Es una foto de estado para iniciar el Checkpoint 0 del plan `docs/lifting-volume-counter-checkpoints.md`.

## Resumen Ejecutivo

El sistema ya tiene la base tecnica principal para calcular volumen de reps:

- Prescripcion lifting por seccion, ejercicio, row, porcentaje, reps y sets.
- Logs por set ejecutado con status y reps reales.
- Maximos del atleta guardados como metricas del perfil.
- Endpoint de detalle que ya devuelve los bloques lifting a la app.
- Endpoint para guardar cada set marcado por el atleta.
- Servicio de progreso que ya considera logs lifting para completar secciones.
- App Ionic ya renderiza bloques lifting y permite marcar set completado/fallado.
- App Ionic ya tiene calculadora de porcentaje conectada al perfil del atleta.

La base local inspeccionada no tiene bloques lifting ni logs reales cargados, aunque si tiene metricas de entrenamiento configuradas. Para validar calculos hace falta crear o identificar un assignment con lifting real.

## Conteos Locales De Solo Lectura

Comando usado:

```bash
$env:APPDATA='C:\xampp\htdocs\coachSaaS\coach\.codex-tmp'; php artisan tinker --execute "echo json_encode([...]);"
```

Resultado de tablas especificas:

```json
{
  "training_section_exercise_blocks": 0,
  "training_section_lifting_rows": 0,
  "training_lifting_set_logs": 0,
  "training_metrics": 5,
  "client_metric_records": 0,
  "coach_training_metrics": 5
}
```

Resultado de contexto general:

```json
{
  "training_sessions": 15,
  "training_assignments": 13,
  "training_sections": 10,
  "training_section_results": 0,
  "training_section_completions": 0,
  "clients": 12,
  "user_apps": 12
}
```

Lectura:

- Hay entrenamientos/asignaciones locales.
- No hay datos lifting locales para probar volumen todavia.
- No hay marcas de maximos de atletas en `client_metric_records`.
- Si se implementa el servicio ahora, las pruebas necesitan fixtures o crear datos controlados.

## Migraciones Aplicadas

`php artisan migrate:status --no-interaction` confirma como `Ran`:

- `2026_07_28_120000_add_behavior_to_training_type_catalogs_table`
- `2026_07_28_120100_create_training_section_exercise_blocks_table`
- `2026_07_28_120200_create_training_section_lifting_rows_table`
- `2026_07_28_120300_create_training_lifting_set_logs_table`
- `2026_01_20_003150_create_training_metrics_table`
- `2026_01_20_003320_create_coach_training_metrics_table`
- `2026_01_20_003428_create_client_metric_records_table`

No hace falta una migracion para el MVP de reps/volumen basico.

## Tablas Disponibles Para Lifting

### `training_section_exercise_blocks`

Migracion: `database/migrations/2026_07_28_120100_create_training_section_exercise_blocks_table.php`.

Campos utiles:

- `training_section_id`
- `exercise_catalog_id` nullable
- `exercise_name`
- `notes`
- `order`

Modelo: `App\Models\TrainingSectionExerciseBlock`.

Relaciones:

- `section()`
- `rows()` ordenado por `order`

Uso para volumen:

- Identifica el ejercicio o movimiento.
- Es el punto donde despues se puede mapear familia: snatch, clean, squat, pull, accessory.

### `training_section_lifting_rows`

Migracion: `database/migrations/2026_07_28_120200_create_training_section_lifting_rows_table.php`.

Campos utiles:

- `exercise_block_id`
- `percentage`
- `reps`
- `sets`
- `rest_seconds`
- `notes`
- `order`

Modelo: `App\Models\TrainingSectionLiftingRow`.

Relaciones:

- `exerciseBlock()`
- `setLogs()` ordenado por `set_number`

Uso para volumen:

- Fuente de reps programadas: `row.reps * row.sets`.
- Fuente de intensidad programada: `row.percentage`.
- Fuente de volumen relativo programado y ejecutado.

### `training_lifting_set_logs`

Migracion: `database/migrations/2026_07_28_120300_create_training_lifting_set_logs_table.php`.

Campos utiles:

- `training_assignment_id`
- `lifting_row_id`
- `set_number`
- `status`
- `actual_reps`
- `failure_reason`
- `notes`
- `logged_at`

Restriccion importante:

- Unico por `training_assignment_id`, `lifting_row_id`, `set_number`.

Modelo: `App\Models\TrainingLiftingSetLog`.

Statuses existentes:

- `completed`
- `failed`
- `skipped`

Uso para volumen:

- Fuente de reps realizadas.
- Fuente de sets ejecutados/completados/fallados.
- Permite reconstruir cada set individualmente.

## Maximos Del Atleta

Tablas:

- `training_metrics`
- `coach_training_metrics`
- `client_metric_records`

Modelos:

- `App\Models\TrainingMetric`
- `App\Models\CoachTrainingMetric`
- `App\Models\ClientMetricRecord`

Endpoint app:

- `GET /api/v1/app/me/profile`
- `POST /api/v1/app/me/metric-records`

Controlador:

- `App\Http\Controllers\Api\V1\App\AuthController@meProfile`
- `App\Http\Controllers\Api\V1\App\AuthController@storeMetricRecord`

Lo que ya hace:

- Carga metricas habilitadas por coach.
- Busca el ultimo `client_metric_records` por metrica.
- Devuelve `metrics[]` con `id`, `code`, `name`, `unit`, `type`, `last`.
- Permite guardar nuevas marcas por `training_metric_id` o `metric_code`.

Semillas/catalogo:

- `TrainingMetricsSeeder` incluye `back_squat_1rm`, `clean_1rm`, `snatch_1rm`.
- `TenantBaseCatalogService` tambien define metricas base.
- Memoria previa indica que algunos seeders no siempre estan cableados en `DatabaseSeeder`; verificar en cada ambiente antes de asumir datos.

## Rutas App Existentes

Verificado con `php artisan route:list`.

### Entrenamientos/asignaciones

```text
GET  api/v1/app/training-assignments/{assignment}
POST api/v1/app/training-assignments/{assignment}/start
POST api/v1/app/training-assignments/{assignment}/complete
POST api/v1/app/training-assignments/{assignment}/lifting-sets
POST api/v1/app/training-assignments/{assignment}/sections/{section}/complete
```

### Perfil/metrica atleta

```text
GET   api/v1/app/me/profile
PATCH api/v1/app/me/profile
PATCH api/v1/app/me/health-profile
POST  api/v1/app/me/body-records
POST  api/v1/app/me/metric-records
```

## Backend: Detalle De Entrenamiento

Archivo:

- `app/Http/Controllers/Client/TrainingAssignmentsController.php`

Lo que hace `show()`:

- Valida que el assignment pertenezca al `client_id` autenticado.
- Carga secciones del training session.
- Incluye `libraryVideos`.
- Incluye `liftingBlocks.rows`.
- Carga logs por row usando `$assignment->liftingSetLogs()->get()->groupBy('lifting_row_id')`.
- Expande `set_statuses` desde `1` hasta `row.sets`.
- Mezcla logs existentes por `set_number`.
- Devuelve `percentage`, `reps`, `sets`, `rest_seconds`, `actual_reps`, `status`, `failure_reason`, `logged_at`.

Punto de extension natural:

- Agregar `lifting_volume_summary` al `data` del response usando un servicio dedicado.

## Backend: Guardado De Sets

Archivo:

- `app/Http/Controllers/Client/TrainingAssignmentsController.php`

Metodo:

- `saveLiftingSet()`

Lo que ya valida:

- Usuario autenticado con `client_id`.
- Assignment pertenece al atleta.
- Assignment no esta `cancelled` ni `skipped`.
- `lifting_row_id` existe.
- `set_number` no excede `row.sets`.
- Row pertenece al mismo training session del assignment.

Comportamiento importante:

- Si `status=completed` y `actual_reps` viene null, rellena `actual_reps = row.reps`.
- Guarda idempotente con `updateOrCreate`.
- Sincroniza progreso con `TrainingAssignmentProgressService::syncStatus()`.

Punto importante para el contador:

- Ya hay suficiente informacion para calcular reps realizadas sin cambiar el flujo de guardado.

## Backend: Progreso

Archivo:

- `app/Services/TrainingAssignmentProgressService.php`

Lo que ya hace:

- Cuenta secciones con resultados normales.
- Cuenta secciones completadas manualmente.
- Cuenta secciones lifting como completadas cuando todos sus sets tienen log con status `completed`, `failed` o `skipped`.
- Actualiza assignment a `in_progress` o `completed` segun progreso.

Limitacion:

- Calcula progreso por secciones, no reps/volumen.
- No distingue volumen ligero/pesado.

Punto de extension:

- No modificar este servicio para volumen si no hace falta.
- Crear servicio separado, por ejemplo `LiftingVolumeSummaryService`.

## Backend Coach: Builder Lifting

Archivo:

- `app/Http/Controllers/Coach/TrainingSessionController.php`

Lo que ya existe:

- Validacion de `sections.*.lifting_blocks`.
- Validacion de `exercise_name`, `percentage`, `reps`, `sets`, `rest_seconds`.
- `syncLiftingBlocks()`.
- `syncLiftingRows()`.
- Copia de entrenamientos clona bloques y rows lifting.

Puntos de cuidado:

- `exercise_catalog_id` existe pero es nullable y no hay evidencia de catalogo activo para mapear maximos todavia.
- El MVP debe mapear por `exercise_name` normalizado, igual que la calculadora actual.

## App Ionic: DTO Y API

Archivo:

- `app/src/app/services/training-api.service.ts`

Ya existen tipos:

- `TrainingLiftingSetStatusDTO`
- `TrainingLiftingRowDTO`
- `TrainingLiftingBlockDTO`
- `SaveLiftingSetPayload`

Ya existe metodo:

- `saveLiftingSet(assignmentId, payload)`

Ya existe mapeo:

- `mapLiftingBlocks()` convierte `percentage`, `reps`, `sets`, `set_statuses`.
- `show()` asignado y `showFree()` mapean `lifting_blocks`.

Punto de extension:

- Agregar tipo `LiftingVolumeSummaryDTO`.
- Extender `TrainingDetailDTO` con `lifting_volume_summary`.
- Mostrar tarjeta compacta en `training-details`.

## App Ionic: Detalle De Entrenamiento

Archivo:

- `app/src/app/pages/training-details/training-details.page.ts`
- `app/src/app/pages/training-details/training-details.page.html`
- `app/src/app/pages/training-details/training-details.page.scss`

Ya renderiza:

- Bloques lifting.
- Filas con `% · sets x reps`.
- Sets individuales.
- Botones de completado/fallado.

Ya guarda:

- `status=completed` con `actual_reps=row.reps`.
- `status=failed` con `actual_reps=row.reps - 1`.
- `failure_reason=athlete_marked_failed`.

Limitacion UX:

- El atleta no puede capturar manualmente reps reales al fallar; se asume `row.reps - 1`.
- Esto es suficiente para MVP inicial, pero no para analitica fina.

## App Ionic: Calculadora De Porcentaje Ya Agregada

La pantalla `training-details` ya tiene:

- Boton flotante.
- Modal de calculadora.
- Carga de metricas desde `ProfileService.getMyProfile()`.
- Preseleccion por nombre de ejercicio:
  - `back squat` / `backsquat` -> `back_squat_1rm`
  - `snatch` -> `snatch_1rm`
  - `clean` -> `clean_1rm`
- Campo editable para `100%`.
- Redondeo `Exacto`, `0.5 kg`, `1 kg`, `2.5 kg`.
- Tabla `100, 95, 90, 85, 80, 75, 70, 65, 60, 55, 50`.

Relevancia para volumen:

- Ya existe logica frontend para inferir metrica desde `exercise_name`.
- Esa logica puede convertirse despues en helper compartido o, preferentemente, replicarse en backend para tonelaje oficial.

## Documentos Existentes Relacionados

- `docs/lifting-set-execution-roadmap.md`
  - Roadmap original de ejecucion por sets.
  - Indica que ya se agregaron tablas/modelos/rutas y queda QA pendiente.
- `docs/lifting-performance-model-roadmap.md`
  - Roadmap amplio para volumen, intensidad y recomendaciones.
  - Incluye formulas de volumen relativo, tonelaje, zonas y analitica futura.
- `docs/lifting-volume-counter-checkpoints.md`
  - Plan MVP creado para este trabajo.

## Lo Que Ya Tenemos Para El MVP

- Datos programados:
  - ejercicio
  - porcentaje
  - reps
  - sets
  - descanso
- Datos realizados:
  - status por set
  - reps reales por set
  - motivo de fallo
- Maximos:
  - metricas configuradas por coach
  - ultimos valores por atleta cuando existan
- UI:
  - render de lifting
  - guardado de set
  - calculadora de porcentajes
- Backend:
  - endpoints de detalle/guardado
  - progreso basado en logs

## Lo Que Falta Para El Contador

### Imprescindible

- Servicio backend de resumen de volumen por assignment.
- Tests/fixtures para calcular:
  - reps programadas
  - reps realizadas
  - adherencia
  - volumen relativo
- Agregar nodo `lifting_volume_summary` al payload.
- Extender DTO en app.
- Mostrar tarjeta de resumen.

### Recomendado Despues

- Tonelaje estimado usando maximos.
- Warnings cuando falten maximos.
- Zonas de intensidad.
- Resumen semanal.
- Agrupacion por familia de movimiento.

### No Disponible Aun

- Datos locales reales de bloques lifting/logs para probar con la DB actual.
- Mapeo formal `exercise_name`/`exercise_catalog_id` -> `training_metric_id`.
- Captura manual de reps reales al fallar.
- Snapshot persistido de analitica.

## Riesgos Y Decisiones Pendientes

### Mapeo De Ejercicios

Actualmente se puede mapear por texto normalizado, pero no es una fuente robusta de verdad.

Decision MVP:

- Usar aliases conservadores por nombre.
- Reportar `missing_max_metrics` o `unmapped_exercises`.

Decision futura:

- Crear `lifting_exercise_metric_maps` o terminar catalogo de ejercicios.

### Reps Falladas

La app hoy manda `row.reps - 1` cuando marca fallado.

Decision MVP:

- Aceptar este comportamiento para el primer contador.

Decision futura:

- Permitir al atleta capturar reps reales al marcar fallo.

### Tonelaje

No debe bloquear el contador porque la DB local puede no tener maximos del atleta.

Decision MVP:

- Calcular reps y volumen relativo siempre.
- Calcular tonelaje solo si hay maximo.

## Siguiente Paso Recomendado

Ejecutar Checkpoint 1 de `lifting-volume-counter-checkpoints.md`:

- Crear `LiftingVolumeSummaryService`.
- Alimentarlo con fixtures de prueba, porque la DB local no tiene bloques lifting/logs.
- No tocar UI todavia hasta tener un contrato backend estable.

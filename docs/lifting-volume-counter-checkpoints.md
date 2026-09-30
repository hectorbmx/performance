# Plan De Ataque: Contador De Volumen Y Reps En Lifting

## Objetivo

Agregar un contador simple y confiable para que Training Flow muestre volumen de trabajo de lifting a partir de lo que ya existe:

- Prescripcion del coach en bloques lifting.
- Sets marcados por el atleta.
- Maximos del perfil cuando existan.

El MVP debe responder primero preguntas practicas:

- Cuantas reps estaban programadas.
- Cuantas reps hizo realmente el atleta.
- Que porcentaje de adherencia tuvo.
- Cuanto volumen relativo acumulo.
- Cuanto tonelaje estimado acumulo cuando hay maximo disponible.

No crear un score unico todavia. No meter RPE/readiness en esta fase.

## Reglas De Alcance

- No guardar peso objetivo en la prescripcion base.
- El peso se calcula desde `percentage` y el maximo del atleta.
- Si falta maximo, mostrar reps y volumen relativo; el tonelaje queda como no disponible.
- Mantener el trabajo por checkpoints pequenos.
- Cada checkpoint debe compilar y dejar una salida verificable.
- No usar migraciones destructivas ni reset de base de datos.

## Fuentes Actuales

- `training_section_exercise_blocks.exercise_name`
- `training_section_lifting_rows.percentage`
- `training_section_lifting_rows.reps`
- `training_section_lifting_rows.sets`
- `training_lifting_set_logs.status`
- `training_lifting_set_logs.actual_reps`
- `training_metrics.code`
- `client_metric_records.value`

## Formula MVP

### Reps Programadas

```text
reps_programadas = row.reps * row.sets
```

### Reps Realizadas

```text
completed = actual_reps si existe, si no row.reps
failed = actual_reps si existe, si no 0
skipped/null = 0
```

### Adherencia

```text
adherencia_reps = reps_realizadas / reps_programadas
```

### Volumen Relativo

```text
volumen_relativo = row.percentage * reps_realizadas
```

### Tonelaje Estimado

```text
peso_estimado = maximo * (row.percentage / 100)
tonelaje = peso_estimado * reps_realizadas
```

## Checkpoint 0: Auditoria Sin Cambios

### Tareas

- Confirmar ejemplos reales de assignments con bloques lifting.
- Confirmar que los logs se guardan al marcar sets desde la app.
- Listar metricas disponibles por coach: `back_squat_1rm`, `clean_1rm`, `snatch_1rm`.
- Revisar casos sin maximo registrado.

### Entregable

- Comandos Tinker o SQL de lectura.
- 1 assignment de prueba con sets pendientes.
- 1 assignment de prueba con sets completados/fallados.

### Validacion

- No se modifican archivos.
- No se modifica base de datos.

## Checkpoint 1: Servicio De Calculo Por Assignment

Estado: implementado.

### Tareas

- Crear un servicio backend, por ejemplo `LiftingVolumeSummaryService`.
- Recibir un `TrainingAssignment`.
- Recorrer secciones, bloques, rows y logs.
- Calcular:
  - sets programados
  - sets ejecutados
  - sets completados
  - sets fallados
  - reps programadas
  - reps realizadas
  - adherencia de reps
  - volumen relativo

### Entregable

- Servicio PHP aislado.
- Metodo principal: `forAssignment(TrainingAssignment $assignment): array`.

### Validacion

- Test unitario o feature con fixture pequena.
- Validar que `completed` sin `actual_reps` use `row.reps`.
- Validar que `failed` con `actual_reps` use las reps reales.
- Validar que pending/null no sume reps realizadas.

### Resultado

- Servicio creado: `app/Services/LiftingVolumeSummaryService.php`.
- Test creado: `tests/Feature/LiftingVolumeSummaryServiceTest.php`.
- Validacion ejecutada:
  - `php -l app\Services\LiftingVolumeSummaryService.php`
  - `php -l tests\Feature\LiftingVolumeSummaryServiceTest.php`
  - `php artisan test --filter=LiftingVolumeSummaryServiceTest --do-not-cache-result`

## Checkpoint 2: Payload En Detalle De Entrenamiento

Estado: implementado.

### Tareas

- Reutilizar el servicio en `TrainingAssignmentsController@show`.
- Agregar `lifting_volume_summary` al payload.
- Mantener compatibilidad con el payload actual.

### Contrato Sugerido

```json
{
  "lifting_volume_summary": {
    "sets_prescribed": 8,
    "sets_executed": 6,
    "sets_completed": 5,
    "sets_failed": 1,
    "reps_prescribed": 18,
    "reps_executed": 14,
    "rep_adherence_pct": 78,
    "relative_volume": 1120
  }
}
```

### Validacion

- Request autenticado al detalle devuelve el nuevo nodo.
- Entrenamientos sin lifting devuelven ceros o `null` controlado.
- La app actual no rompe si ignora el nuevo nodo.

### Resultado

- `TrainingAssignmentsController@show` agrega `data.lifting_volume_summary`.
- El nodo conserva compatibilidad: la app puede ignorarlo si todavia no lo consume.
- Test creado: `tests/Feature/TrainingAssignmentDetailVolumeSummaryTest.php`.
- Se cubren dos escenarios:
  - detalle autenticado sin lifting devuelve resumen en ceros.
  - detalle autenticado con rows/logs lifting devuelve sets, reps, adherencia y volumen relativo calculados.
- Ajuste adicional: `TrainingAssignmentProgressService` dejo de usar `CONCAT()` para contar sets distintos y ahora usa pares distintos `lifting_row_id/set_number`, compatible con SQLite y MySQL.
- Validacion ejecutada:
  - `php -l app\Services\TrainingAssignmentProgressService.php`
  - `php -l tests\Feature\TrainingAssignmentDetailVolumeSummaryTest.php`
  - `php artisan test --filter=TrainingAssignmentDetailVolumeSummaryTest --do-not-cache-result`

## Checkpoint 3: Datos Para Grafica En Laravel

Estado: implementado.

### Tareas

- Agregar `sets_pending` al resumen.
- Agregar `chart.reps` para comparar reps programadas vs realizadas.
- Agregar `chart.sets` para comparar sets completados, fallados, saltados y pendientes.
- Agregar `zones` por intensidad:
  - `<60`
  - `60-69`
  - `70-79`
  - `80-89`
  - `90-94`
  - `95+`
  - `unknown`
- Agregar `by_exercise` para revisar si los datos agrupados por movimiento son utiles antes de moverlos a la app.

### Contrato Sugerido

```json
{
  "sets_pending": 2,
  "chart": {
    "reps": [
      { "label": "Programadas", "value": 18 },
      { "label": "Realizadas", "value": 14 }
    ],
    "sets": [
      { "label": "Completados", "value": 5 },
      { "label": "Fallados", "value": 1 },
      { "label": "Saltados", "value": 0 },
      { "label": "Pendientes", "value": 2 }
    ]
  },
  "zones": [
    {
      "key": "80_89",
      "label": "80-89%",
      "prescribed_reps": 12,
      "executed_reps": 10,
      "adherence_pct": 83
    }
  ],
  "by_exercise": [
    {
      "exercise_name": "Back Squat",
      "sets_prescribed": 8,
      "sets_executed": 6,
      "reps_prescribed": 18,
      "reps_executed": 14,
      "relative_volume": 1120
    }
  ]
}
```

### Validacion

- Test del servicio con porcentajes nulos, zonas y sets pendientes.
- Test del endpoint confirmando `chart`, `zones` y `by_exercise`.
- La app actual sigue funcionando aunque ignore los campos nuevos.

### Resultado

- `LiftingVolumeSummaryService` ahora entrega datos listos para graficas dentro de `lifting_volume_summary`.
- Campos agregados:
  - `sets_pending`
  - `chart.reps`
  - `chart.sets`
  - `zones`
  - `by_exercise`
- Las zonas se devuelven siempre en orden fijo:
  - `lt_60`
  - `60_69`
  - `70_79`
  - `80_89`
  - `90_94`
  - `95_plus`
  - `unknown`
- El endpoint `GET /api/v1/app/training-assignments/{assignment}` hereda el contrato porque ya usa el servicio.
- Validacion ejecutada:
  - `php -l app\Services\LiftingVolumeSummaryService.php`
  - `php -l tests\Feature\LiftingVolumeSummaryServiceTest.php`
  - `php -l tests\Feature\TrainingAssignmentDetailVolumeSummaryTest.php`
  - `php artisan test --filter=LiftingVolumeSummaryServiceTest --do-not-cache-result`
  - `php artisan test --filter=TrainingAssignmentDetailVolumeSummaryTest --do-not-cache-result`

## Checkpoint 4: Pestana De Rendimiento En Perfil Del Atleta

### Tareas

- Agregar una pestana de solo lectura en `coach/clients/{id}/edit`.
- Mostrar datos lifting recabados por atleta.
- Mostrar ultimos entrenamientos con lifting.
- Mostrar reps programadas vs realizadas.
- Mostrar sets completados/fallados/saltados.
- Mostrar volumen relativo.
- Mostrar zonas de intensidad.
- Mostrar ejercicios mas trabajados.

### Contrato Inicial De Datos

La primera version de la pestana usara un arreglo `$liftingPerformance` con:

- `has_data`: indica si el atleta tiene entrenamientos lifting analizables.
- `summary`: totales agregados del atleta.
- `recent_assignments`: ultimos entrenamientos con lifting y su resumen por assignment.
- `zones`: suma de reps programadas/realizadas por zona de intensidad.
- `by_exercise`: suma por ejercicio para revisar si el agrupamiento por nombre es util.

Resumen sugerido:

```php
[
    'has_data' => true,
    'summary' => [
        'assignments_count' => 3,
        'sets_prescribed' => 24,
        'sets_executed' => 20,
        'sets_completed' => 16,
        'sets_failed' => 3,
        'sets_skipped' => 1,
        'sets_pending' => 4,
        'reps_prescribed' => 72,
        'reps_executed' => 58,
        'rep_adherence_pct' => 81,
        'relative_volume' => 4620.0,
    ],
    'recent_assignments' => [],
    'zones' => [],
    'by_exercise' => [],
]
```

### Validacion

- El coach puede abrir la pestana sin romper las pestanas actuales.
- Atleta sin datos lifting muestra estado vacio.
- Atleta con datos lifting muestra resumen legible para revisar si el modelo sirve.

## Checkpoint 5: UI MVP En App

### Tareas

- Extender el DTO de `training-api.service.ts`.
- Mostrar una tarjeta compacta en `training-details`.
- Mostrar:
  - reps realizadas / programadas
  - adherencia
  - volumen relativo

### UI Sugerida

```text
Volumen lifting
14 / 18 reps
78% adherencia
Volumen relativo 1120
```

### Validacion

- `tsc -p tsconfig.app.json --noEmit`.
- `ng build`.
- Prueba manual en `/training-details/{assignment}`.

## Checkpoint 6: Tonelaje Con Maximos Del Perfil

### Tareas

- Mapear ejercicio a metrica base:
  - back squat -> `back_squat_1rm`
  - backsquat -> `back_squat_1rm`
  - snatch -> `snatch_1rm`
  - clean -> `clean_1rm`
- Obtener ultimo maximo del atleta por metrica.
- Calcular tonelaje estimado por row cuando exista maximo.
- Agregar warning cuando falte maximo.

### Contrato Sugerido

```json
{
  "estimated_tonnage": 1780,
  "tonnage_unit": "kg",
  "missing_max_metrics": ["snatch_1rm"]
}
```

### Validacion

- Atleta con maximo genera tonelaje.
- Atleta sin maximo conserva reps y volumen relativo.
- No se bloquea el detalle por falta de maximo.

## Checkpoint 7: Vista Semanal Basica

### Tareas

- Crear endpoint de resumen semanal para el atleta autenticado.
- Agrupar assignments por semana.
- Sumar:
  - reps programadas
  - reps realizadas
  - volumen relativo
  - tonelaje estimado
  - adherencia promedio ponderada

### UI Sugerida

```text
Semana actual
Reps 128 / 142
Adherencia 90%
Volumen relativo 9840
Tonelaje 12.4 t
```

### Validacion

- Semana sin entrenamientos devuelve ceros.
- Rango semanal respeta timezone/configuracion actual del backend.

## Checkpoint 8: Separacion Por Movimiento

### Tareas

- Definir alias iniciales por familia:
  - snatch
  - clean
  - jerk
  - squat
  - pull
  - accessory
- Agrupar volumen por familia.
- Mostrar distribucion simple en la app o panel.

### Validacion

- Ejercicios no mapeados caen en `unknown` o `accessory`.
- No mezclar visualmente squats con olympic lifts como si fueran equivalentes.

## Checkpoint 9: Snapshot Opcional

### Criterio Para Hacerlo

Solo crear tablas de snapshot si el calculo en vivo se vuelve lento o si el dashboard semanal/mensual necesita historico rapido.

### Tablas Posibles

- `lifting_session_analytics`
- `lifting_weekly_analytics`

### Validacion

- El snapshot se puede recalcular.
- No es la unica fuente de verdad.
- Los logs por set siguen siendo la fuente reconstruible.

## Fuera Del MVP

- RPE.
- RIR.
- Readiness.
- Recomendaciones automaticas.
- Training Load Score unico.
- Predicciones de progreso.
- Graficas complejas antes de validar los calculos base.

## Orden Recomendado

1. Checkpoint 0.
2. Checkpoint 1.
3. Checkpoint 2.
4. Checkpoint 3.
5. Checkpoint 4.
6. Checkpoint 5.
7. Checkpoint 6.
8. Checkpoint 7.
9. Checkpoint 8.
10. Checkpoint 9 solo si hace falta rendimiento.

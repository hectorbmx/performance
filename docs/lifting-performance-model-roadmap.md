# Roadmap: Modelo De Recomendacion Para Volumen E Intensidad En Lifting

## Objetivo

Crear un motor matematico que ayude al entrenador a ajustar volumen, intensidad y distribucion semanal de entrenamiento de halterofilia con base en:

- Maximos ya registrados del atleta (`training_metrics` + `client_metric_records`).
- Prescripcion del coach en bloques lifting (`percentage`, `reps`, `sets`, `rest_seconds`).
- Ejecucion real por set (`training_lifting_set_logs.actual_reps`, `status`, `failure_reason`).
- Objetivo definido por el coach: mejorar un maximo especifico, preparar test, aumentar volumen tecnico o reducir fatiga.

El sistema no debe presentarse como una prediccion absoluta de resultado. Debe operar como un asistente de decision:

- Calcula carga realizada.
- Detecta tendencias de cumplimiento/fatiga.
- Sugiere ajustes conservadores.
- Explica por que recomienda subir, mantener o bajar volumen/intensidad.

## Contexto Actual En DB

### Maximos Del Atleta

Ya existe una estructura reutilizable para maximos y marcas:

- `training_metrics`
  - `code`: ejemplo esperado `snatch_1rm`, `clean_1rm`, `back_squat_1rm`.
  - `name`: nombre visible.
  - `unit`: `kg`, `lb`, etc.
  - `type`: actualmente soporta semantica como `max`.
  - `coach_id`: metricas propias del coach o globales.
- `client_metric_records`
  - `client_id`
  - `training_metric_id`
  - `value`
  - `recorded_at`
  - `source`
  - `notes`

Esta tabla debe ser la fuente de verdad para el 1RM/base de carga por atleta y ejercicio.

### Prescripcion Lifting

El modelo lifting ya guarda:

- `training_section_exercise_blocks`
  - `exercise_catalog_id` nullable.
  - `exercise_name`.
- `training_section_lifting_rows`
  - `percentage`
  - `reps`
  - `sets`
  - `rest_seconds`
  - `notes`
- `training_lifting_set_logs`
  - `status`
  - `actual_reps`
  - `failure_reason`
  - `logged_at`

Decision previa que se mantiene: el coach no captura peso objetivo en la row. El peso se calcula con `%` y el maximo/base del atleta cuando exista.

## Investigacion Y Fundamento

### 1. Carga De Sesion En Halterofilia

Una forma practica de cuantificar carga en weightlifting es el volumen total relativo:

```text
relative_volume_load = %1RM * sets * reps
```

En nuestro sistema:

```text
relative_volume_load = row.percentage * reps_ejecutadas
```

Si existe maximo/base:

```text
estimated_weight = maximo_ejercicio * (row.percentage / 100)
tonnage = estimated_weight * reps_ejecutadas
```

Referencia: el estudio "Monitoring and quantifying the training load in weightlifting" usa TVL como `%1RM x sets x repetitions` y encontro relacion entre carga total, sRPE y cambios de HRV.

Fuente: https://www.jstage.jst.go.jp/article/rjsp/13/0/13_2058/_article/-char/en

### 2. Rango De Intensidad Y Volumen

Prilepin's chart se usa como matriz de referencia para relacionar:

- Rango de intensidad.
- Reps por set.
- Total de reps aceptables por sesion.

No debe usarse como regla rigida, pero si como guardrail inicial para detectar sesiones con exceso o defecto de volumen por zona.

Fuente: https://vbtcoach.com/charts/prilepin-chart/

### 3. Dosis-Respuesta

La literatura de entrenamiento de fuerza muestra que el efecto de volumen/intensidad no es lineal:

- Mas volumen no siempre implica mejor adaptacion.
- Hay puntos de meseta.
- Intensidad y duracion influyen en el desarrollo de fuerza.

Fuentes:

- https://doi.org/10.1007/s40279-026-02432-5
- https://pmc.ncbi.nlm.nih.gov/articles/pmid/37414459/
- https://pubmed.ncbi.nlm.nih.gov/16503695/

### 4. Recomendacion De Producto

El primer MVP debe ser un modelo basado en reglas + score interpretable. Un modelo predictivo estadistico solo sera util cuando existan suficientes historiales por atleta y ejercicio.

## Conceptos Del Modelo

### Metricas Base Por Sesion

Para cada assignment completado o en progreso:

```text
sets_prescritos = SUM(row.sets)
sets_ejecutados = COUNT(logs con status completed|failed|skipped)
sets_completados = COUNT(logs status completed)
sets_fallados = COUNT(logs status failed)
reps_prescritas = SUM(row.reps * row.sets)
reps_ejecutadas = SUM(COALESCE(log.actual_reps, row.reps si completed))
completion_rate = sets_completados / sets_prescritos
rep_completion_rate = reps_ejecutadas / reps_prescritas
relative_volume = SUM(row.percentage * reps_ejecutadas)
avg_intensity = SUM(row.percentage * reps_ejecutadas) / NULLIF(reps_ejecutadas, 0)
```

Si hay maximo:

```text
estimated_tonnage = SUM(maximo_ejercicio * (row.percentage / 100) * reps_ejecutadas)
```

### Zonas De Intensidad

Agrupar reps y volumen por zona:

- `zone_technique`: `<60%`
- `zone_base`: `60-69%`
- `zone_strength`: `70-79%`
- `zone_heavy`: `80-89%`
- `zone_peak`: `90%+`

Cada ejercicio debe tener:

- reps por zona.
- volumen relativo por zona.
- tonelaje por zona si existe maximo.
- fallos por zona.

### Fatigue / Readiness Proxy

Sin HRV ni RPE inicial, usar senales del sistema:

```text
failure_rate = failed_sets / executed_sets
rep_drop_rate = 1 - (reps_ejecutadas / reps_prescritas)
heavy_failure_rate = failed_sets_80_plus / executed_sets_80_plus
session_density = relative_volume / duration_minutes
```

Si se integran `client_daily_health_metrics` despues:

- sueno
- energia
- dolor
- estres
- peso corporal

entonces el score puede ponderar readiness.

### Score De Adaptacion Inicial

Propuesta MVP:

```text
adaptation_score =
  0.35 * completion_rate
  + 0.25 * rep_completion_rate
  + 0.20 * intensity_balance_score
  + 0.20 * consistency_score
  - fatigue_penalty
```

Donde:

```text
fatigue_penalty =
  min(0.30, failure_rate * 0.50 + heavy_failure_rate * 0.30 + rep_drop_rate * 0.20)
```

El score no predice el resultado final. Clasifica la respuesta del atleta a la carga reciente.

## Motor De Recomendacion

### Inputs Del Coach

El coach debe poder definir:

- Atleta.
- Ejercicio objetivo: `snatch`, `clean`, `clean_and_jerk`, `back_squat`, etc.
- Maximo actual detectado desde `client_metric_records`.
- Resultado objetivo:
  - ejemplo: `Snatch 85kg`.
  - ejemplo: `Back squat +5kg`.
- Fecha objetivo o numero de semanas.
- Prioridad:
  - tecnica
  - fuerza base
  - pico/test
  - descarga

### Output Del Motor

El motor debe responder:

- Maximo actual usado.
- Objetivo.
- Gap absoluto y relativo.
- Tendencia ultimas 2-6 semanas.
- Volumen recomendado por zona para la proxima semana.
- Advertencias:
  - demasiado fallo en 80%+
  - poca exposicion pesada
  - exceso de volumen relativo
  - cumplimiento bajo
  - sin suficientes datos
- Sugerencia textual para el coach.

Ejemplo:

```text
Atleta: Hector
Objetivo: Snatch 85kg en 6 semanas
Maximo actual: 80kg
Gap: +6.25%

Ultimas 3 semanas:
- Cumplimiento sets: 92%
- Reps ejecutadas: 89%
- Volumen relativo semanal: estable +4%
- Fallos en 85%+: altos (18%)

Recomendacion:
- Mantener volumen total.
- Reducir reps en 90%+ por una semana.
- Subir exposicion en 75-85% con sets de 2-3 reps.
- Re-test no recomendado esta semana.
```

## Estrategia Algoritmica Por Fases

### Fase 0: Auditoria De Datos

- [ ] Confirmar codigos actuales de metricas max en `training_metrics`.
- [ ] Listar por coach los codigos equivalentes a movimientos lifting.
- [ ] Definir tabla de alias:
  - `snatch`
  - `clean`
  - `clean_jerk`
  - `front_squat`
  - `back_squat`
  - `jerk`
  - `pull`
- [ ] Mapear `exercise_name` manual a `training_metric.code` cuando no exista `exercise_catalog_id`.
- [ ] Verificar si los maximos estan en kg o lb por coach/atleta.

Entregable:

- Comando o reporte de diagnostico con:
  - metricas disponibles.
  - atletas con maximos.
  - assignments lifting con logs.
  - ejercicios sin mapeo a maximo.

### Fase 1: Modelo De Datos De Analitica

Crear entidades de soporte sin romper el modelo actual.

#### `lifting_exercise_metric_maps`

Mapea ejercicios a metricas de maximo.

Campos:

- `id`
- `coach_id`
- `exercise_catalog_id` nullable
- `exercise_name_normalized` nullable
- `training_metric_id`
- `movement_group` nullable
- `created_at`
- `updated_at`

Reglas:

- Si hay `exercise_catalog_id`, usarlo como match preferente.
- Si no hay catalogo, normalizar `exercise_name`.
- El coach puede corregir mapeos.

#### `lifting_session_analytics`

Snapshot calculado por assignment/atleta/sesion.

Campos sugeridos:

- `id`
- `training_assignment_id`
- `client_id`
- `training_session_id`
- `computed_at`
- `sets_prescribed`
- `sets_executed`
- `sets_completed`
- `sets_failed`
- `reps_prescribed`
- `reps_executed`
- `completion_rate`
- `rep_completion_rate`
- `relative_volume`
- `estimated_tonnage` nullable
- `avg_intensity`
- `heavy_failure_rate`
- `payload_json`

#### `lifting_exercise_analytics`

Snapshot calculado por ejercicio dentro de assignment.

Campos sugeridos:

- `id`
- `training_assignment_id`
- `client_id`
- `exercise_key`
- `training_metric_id` nullable
- `max_value_used` nullable
- `max_recorded_at` nullable
- `reps_by_zone_json`
- `relative_volume_by_zone_json`
- `tonnage_by_zone_json` nullable
- `failure_by_zone_json`
- `summary_json`

### Fase 2: Servicio De Calculo

Crear `LiftingAnalyticsService`.

Responsabilidades:

- Recibir un `TrainingAssignment`.
- Cargar sections, lifting blocks, rows y logs.
- Resolver maximo por atleta y ejercicio:
  - via `lifting_exercise_metric_maps`.
  - ultimo `client_metric_records` para `training_metric_id`.
- Calcular metricas base.
- Guardar snapshots.
- Recalcular al guardar logs.

Metodos:

```php
computeAssignment(TrainingAssignment $assignment): LiftingSessionAnalytics
computeExerciseBreakdown(TrainingAssignment $assignment): Collection
resolveAthleteMax(Client $client, TrainingSectionExerciseBlock $block): ?ClientMetricRecord
```

### Fase 3: Motor De Recomendacion MVP

Crear `LiftingRecommendationService`.

Inputs:

- `client_id`
- `target_training_metric_id`
- `target_value`
- `target_date` nullable
- `strategy`: `technique`, `strength`, `peak`, `deload`
- historial de 2-8 semanas.

Outputs:

- `recommendation_score`
- `risk_level`: `low`, `moderate`, `high`
- `next_week_volume_targets`
- `zone_targets`
- `messages`
- `evidence`

Reglas iniciales:

#### Si cumplimiento alto y fallos bajos

```text
completion_rate >= 0.90
heavy_failure_rate <= 0.10
```

Sugerir:

- mantener o subir volumen relativo 3-7%.
- mantener exposiciones pesadas si objetivo es fuerza/pico.

#### Si cumplimiento bajo

```text
completion_rate < 0.80
```

Sugerir:

- reducir volumen 5-15%.
- mover reps de 85%+ hacia 70-80%.

#### Si fallos altos en intensidad pesada

```text
heavy_failure_rate > 0.15
```

Sugerir:

- bajar reps en 85%+.
- conservar tecnica en 70-80%.
- evitar test esta semana.

#### Si hay poca exposicion al rango objetivo

Para objetivo de nuevo maximo:

```text
reps_80_89 muy bajas
reps_90_plus = 0 durante 2-3 semanas
```

Sugerir:

- introducir singles/doubles controlados en 85-90%.

### Fase 4: Simulador Para Coach

Pantalla web nueva:

Ruta sugerida:

```text
/coach/athletes/{client}/lifting-model
```

Secciones:

- Header de atleta y maximos detectados.
- Selector de objetivo.
- Selector de fecha/semana objetivo.
- Cards:
  - maximo actual
  - objetivo
  - gap
  - cumplimiento reciente
  - volumen relativo
  - tonelaje estimado
- Graficas:
  - volumen relativo semanal.
  - reps por zona de intensidad.
  - fallos por zona.
  - maximo historico por metrica.
- Panel de recomendacion:
  - accion sugerida.
  - justificacion.
  - riesgos.

### Fase 5: Integracion Con Builder De Entrenamientos

Agregar asistente en create/edit training:

- Boton `Sugerir volumen`.
- Selecciona atleta o grupo.
- Selecciona objetivo.
- El motor propone rows:
  - `%`
  - reps
  - series
  - descanso
  - notas

Reglas:

- El coach siempre confirma manualmente.
- El motor nunca guarda cambios automaticamente.
- Mostrar advertencia si faltan maximos.
- Si el grupo tiene atletas con maximos distintos, prescribir en porcentaje, no kg.

### Fase 6: Aprendizaje Con Historial

Cuando haya suficiente data:

Requisito minimo por atleta/ejercicio:

- 6-8 semanas de logs.
- 3+ sesiones por movimiento o familia.
- 1+ maximo previo y 1+ maximo nuevo/test.

Modelo inicial:

- Regresion regularizada o gradient boosting simple sobre features agregadas.
- Objetivo:
  - probabilidad de completar proximo bloque.
  - probabilidad de PR/test exitoso.
  - rango esperado de mejora.

Features:

- volumen relativo ultimas N semanas.
- tonelaje estimado ultimas N semanas.
- reps por zona.
- fallo por zona.
- tendencia de maximos.
- frecuencia semanal.
- descanso promedio.
- adherencia.
- readiness si existe.

Guardar resultados como explicables:

```text
No usar "el algoritmo predice 90kg".
Usar "probabilidad estimada de completar esta progresion: media/alta/baja".
```

## API Propuesta

### Recalculo Analitico

```http
POST /coach/athletes/{client}/lifting-analytics/recompute
```

### Obtener Dashboard

```http
GET /coach/athletes/{client}/lifting-analytics
```

Params:

- `from`
- `to`
- `metric_id`
- `exercise_key`

### Obtener Recomendacion

```http
POST /coach/athletes/{client}/lifting-recommendations
```

Payload:

```json
{
  "target_training_metric_id": 12,
  "target_value": 85,
  "target_date": "2026-09-15",
  "strategy": "peak"
}
```

Response:

```json
{
  "ok": true,
  "data": {
    "current_max": 80,
    "target_value": 85,
    "gap_pct": 6.25,
    "risk_level": "moderate",
    "recommendation_score": 0.74,
    "zone_targets": {
      "60_69": { "relative_volume": 900, "reps": 14 },
      "70_79": { "relative_volume": 1260, "reps": 18 },
      "80_89": { "relative_volume": 820, "reps": 10 },
      "90_plus": { "relative_volume": 180, "reps": 2 }
    },
    "messages": [
      "Mantener volumen total; los fallos pesados estan cerca del limite.",
      "Priorizar doubles tecnicos entre 80-85 antes de subir a 90+."
    ],
    "evidence": {
      "completion_rate_4w": 0.91,
      "heavy_failure_rate_4w": 0.14,
      "relative_volume_trend_4w": 0.04
    }
  }
}
```

## UI Web Recomendado

### Vista 1: Dashboard Lifting Del Atleta

Ubicacion:

```text
Coach > Atletas > Perfil > Lifting
```

Componentes:

- Maximos actuales.
- Tendencia de maximos.
- Volumen semanal por ejercicio.
- Distribucion por zona de intensidad.
- Cumplimiento y fallos.
- CTA `Crear recomendacion`.

### Vista 2: Simulador De Objetivo

Inputs:

- movimiento objetivo.
- resultado objetivo.
- fecha objetivo.
- estrategia.

Outputs:

- recomendacion.
- graficas.
- bloques sugeridos.
- boton `Usar como borrador` en entrenamiento.

### Vista 3: Auditoria De Datos

Para evitar recomendaciones con datos malos:

- ejercicios sin maximo.
- ejercicios sin mapeo.
- atletas sin historial suficiente.
- logs incompletos.

## Riesgos Y Guardrails

- No prometer prediccion exacta.
- Mostrar nivel de confianza:
  - `bajo`: pocos datos.
  - `medio`: historial suficiente sin tests recientes.
  - `alto`: historial consistente y maximos actualizados.
- Si falta maximo, usar solo volumen relativo y avisar.
- Si `exercise_name` no mapea a metrica, pedir al coach corregir.
- Si el atleta registra muchos fallos, sugerir ajuste conservador.
- No modificar entrenamientos automaticamente.

## QA Esperada

- [ ] Atleta con maximos y logs genera volumen relativo y tonelaje.
- [ ] Atleta sin maximos genera volumen relativo y warning.
- [ ] Ejercicio manual no mapeado aparece en auditoria.
- [ ] Una sesion con fallos en 90%+ eleva riesgo.
- [ ] Una semana con cumplimiento alto sugiere mantener/subir ligero.
- [ ] Recomendacion nunca guarda cambios sin confirmacion del coach.
- [ ] Dashboard filtra por ejercicio y rango de fechas.
- [ ] El recalculo es idempotente.

## Orden De Implementacion Recomendado

1. Fase 0: auditoria de metricas y mapeos.
2. Fase 1: snapshots de analitica.
3. Fase 2: `LiftingAnalyticsService`.
4. Fase 3: `LiftingRecommendationService` basado en reglas.
5. Fase 4: dashboard web.
6. Fase 5: asistente en builder.
7. Fase 6: modelo estadistico cuando haya historial suficiente.

## Estado

- Estado: `Roadmap propuesto`.
- Fecha: `2026-07-30`.
- Repos involucrados:
  - Laravel web/backend: `coach/`.
  - Ionic app: `app/` solo si despues se quiere mostrar recomendaciones al atleta.
- Proxima accion recomendada:
  - Ejecutar Fase 0 y generar reporte real de metricas `max` existentes, codigos disponibles y assignments lifting con logs.

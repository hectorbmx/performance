# Motor De Asistencia Weightlifting

## Proposito

Definir una ruta funcional y tecnica para convertir el calendario y la pestana de rendimiento de Training Flow en un copiloto de programacion para coaches de Weightlifting.

El motor no debe crear entrenamientos ni reemplazar al coach. Su funcion es:

- cuantificar carga
- comparar contra historial reciente
- mostrar contexto
- anticipar impacto antes de guardar
- emitir observaciones explicables

La decision final siempre pertenece al coach.

## Idea Central

El coach programa los entrenamientos. El sistema calcula en tiempo real:

- reps programadas
- reps realizadas
- tonelaje programado
- tonelaje realizado
- intensidad media
- zonas de intensidad
- carga por familia de movimiento
- adherencia
- cambios contra semanas previas

Con esto, el coach puede responder:

- que carga lleva el atleta esta semana
- que pasara si agrega una sesion manana
- si una familia esta acumulando demasiada carga
- si la semana sube mucho respecto al historial del mismo atleta

## Principios

- Coach-first: el coach decide.
- Athlete-specific: comparar contra el historial del mismo atleta.
- Explainable: toda alerta debe explicar metrica, periodo y razon.
- Plan vs actual: separar programado de realizado.
- Incremental: calcular impacto antes de guardar.
- Configurable: reglas y umbrales no deben quedar hardcodeados.
- No black box: los calculos deben ser reproducibles.

## Metricas Base

### Reps

```text
reps_programadas = suma de reps definidas en sets programados
reps_realizadas = suma de reps efectivamente ejecutadas
```

### Tonelaje

```text
tonelaje = suma(peso_del_set * reps_del_set)
```

Debe existir tonelaje programado y realizado.

### Intensidad Relativa

```text
%1RM = peso_programado / maximo_referencia * 100
```

### Intensidad Media Ponderada

```text
intensidad_media = suma(%1RM * reps) / suma(reps)
```

### Zonas De Intensidad

- `<60%`
- `60-69%`
- `70-79%`
- `80-89%`
- `90-94%`
- `95%+`

### Adherencia

```text
adherencia_reps = reps_realizadas / reps_programadas * 100
adherencia_tonelaje = tonelaje_realizado / tonelaje_programado * 100
```

### Complementarias

- sets programados
- sets completados
- sets fallados
- sets saltados
- sets pendientes
- reps >=80%
- reps >=90%
- distribucion por familia
- cambio vs semana anterior
- cambio vs promedio movil de 4 semanas

## Familias De Movimiento

Familias analiticas sugeridas:

- Snatch
- Clean
- Jerk
- Pulls
- Squats
- Accessories

El calendario no debe listar todas las variantes individuales. Para calendario y resumen semanal conviene agrupar:

```text
Power Snatch, Hang Snatch, Block Snatch -> Snatch
Power Clean, Hang Clean, Block Clean -> Clean
Back Squat, Front Squat, Box Squat -> Squats
```

## Integracion Con Calendario

### Columna Carga Semanal

En desktop, agregar una columna al final de cada fila semanal del calendario.

Ejemplo:

```text
Carga semanal
Reps: 322
Kg: 28,540
Int: 73.1%

Snatch: 64 reps · 3,980 kg
Clean: 52 reps · 4,420 kg
Jerk: 36 reps · 2,960 kg
Pulls: 40 reps · 5,300 kg
Squats: 70 reps · 9,850 kg
```

En tablet/mobile, mostrar este contenido en boton o panel para no saturar el calendario.

### Programado Vs Realizado

Debe poder verse:

```text
Snatch: 64 / 58 reps
Squat: 9,850 / 8,900 kg
```

Formato:

```text
programado / realizado
```

### Preview Antes De Guardar

Al crear o editar una sesion, mostrar impacto incremental sin persistir cambios.

Ejemplo:

```text
Front Squat 5x3 @80%
+15 reps
+1,920 kg

Squat semanal: 70 -> 85 reps
Tonelaje Squat: 9,850 -> 11,770 kg
Tonelaje total: 28,540 -> 30,460 kg
```

## Motor De Revision Y Alertas

El motor debe ser deterministico. Recibe metricas calculadas y genera observaciones.

No debe generar ejercicios.

### Severidad

- Informativo
- Atencion
- Elevado
- Muy elevado

### Reglas Iniciales

- tonelaje vs promedio 4 semanas
- reps vs promedio 4 semanas
- reps >=90% vs promedio 4 semanas
- volumen e intensidad suben al mismo tiempo
- concentracion alta por familia
- cambio abrupto por familia

Los umbrales deben ser configurables. No deben quedar como verdades universales.

## Modelo De Datos Propuesto

Responsabilidades sugeridas:

```text
exercises
exercise_families
athlete_maximums / client_metric_records
training_sessions
training_exercises
training_sets
training_blocks
load_rule_definitions
load_alerts
```

En el esquema actual, se debe adaptar a:

- `training_section_exercise_blocks`
- `training_section_lifting_rows`
- `training_lifting_set_logs`
- `training_metrics`
- `client_metric_records`

## Maximos Y Vigencia

Decision importante pendiente:

Un entrenamiento historico deberia usar el maximo valido en la fecha de la sesion, no necesariamente el maximo actual.

Hoy Training Flow usa el ultimo valor disponible. Para una version mas robusta se necesita vigencia historica:

```text
client_id
training_metric_id
value
recorded_at
valid_from
valid_to
source
```

## Relacion Con Lo Ya Construido

Ya existe:

- pestana `Rendimiento`
- reps programadas/realizadas
- sets por estado
- adherencia
- volumen relativo
- tonelaje estimado
- intensidad media
- zonas de intensidad
- warnings de maximos faltantes o ejercicios sin metrica

Pendiente para alinearlo con esta especificacion:

- carga semanal en calendario
- programado vs realizado en el mismo resumen semanal
- familias de movimiento
- historial semanal/mensual
- comparacion contra promedio 4 semanas
- preview de impacto antes de guardar
- motor de reglas/alertas
- IA explicativa despues del motor

## Plan De Implementacion Sugerido

### Fase 1: Normalizacion

- catalogo de ejercicios WL
- familias de movimiento
- metricas/maximos bien normalizados
- soporte para fallback de datos viejos

### Fase 2: Motor Metrico

- reps
- tonelaje
- intensidad media
- zonas
- adherencia
- programado vs realizado

### Fase 3: Calendario

- columna `Carga semanal`
- resumen por familia
- detalle semanal

### Fase 4: Preview

- impacto antes de guardar sesion
- impacto antes de guardar ejercicio
- recalculo sin persistir

### Fase 5: Historial

- promedio 4 semanas
- deltas
- grafica semanal/mensual

### Fase 6: Alertas

- reglas configurables
- severidad
- explicacion
- no bloquear guardado

### Fase 7: Rendimiento

- graficas
- familias
- timeline
- filtros por periodo

### Fase 8: IA Explicativa

- explicar observaciones
- resumir cambios vs historial
- responder preguntas usando datos calculados
- nunca inventar calculos ni reglas

## Criterios De Aceptacion MVP

- El calendario muestra carga semanal en desktop.
- La carga semanal muestra reps, tonelaje e intensidad media.
- El resumen semanal muestra familias principales.
- Los datos salen de sets fuente.
- Se distingue programado y realizado.
- Al crear/editar una sesion se ve impacto antes de guardar.
- Se compara contra 4 semanas previas cuando hay historial.
- Las observaciones muestran valor actual, baseline y diferencia.
- Ninguna alerta bloquea al coach.
- Un ejercicio sin maximo no inventa intensidad.
- Rendimiento y calendario usan el mismo motor.

## Riesgos Y Decisiones Pendientes

- Que maximo aplica a pulls, variantes y complejos.
- Si una rep fallada cuenta como intento, realizada o metrica separada.
- Como contar tonelaje en complejos.
- Que accesorios suman tonelaje.
- Umbrales iniciales de alerta.
- Periodizacion y contexto de bloque.
- Semanas con datos incompletos pueden distorsionar baseline.

## Nota Sobre IA

La IA debe venir despues del motor matematico.

Flujo objetivo:

```text
Coach programa
Backend calcula
Motor compara
Reglas generan observaciones
IA explica
Coach decide
```

La IA no debe:

- inventar tonelaje
- inventar maximos
- bloquear decisiones
- diagnosticar fatiga o lesion
- crear entrenamientos automaticos en el MVP

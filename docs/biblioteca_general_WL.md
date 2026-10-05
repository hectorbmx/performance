# Biblioteca General WL

## Idea

Crear una biblioteca general de ejercicios de Weightlifting para dejar de depender de nombres escritos libremente en los entrenamientos.

La idea es que el sistema tenga un catalogo base con ejercicios comunes y que cada coach active los ejercicios que usa. El coach tambien podria agregar ejercicios propios si necesita variantes especiales.

Esto ayudaria a que el dashboard de rendimiento calcule mejor:

- tonelaje
- intensidad media
- distribucion por familia de movimiento
- volumen por ejercicio
- warnings de metricas faltantes

## Problema Actual

Hoy los bloques lifting dependen mucho de `exercise_name` como texto libre.

Ejemplos reales:

- `BackSquat`
- `Back squat`
- `Box Bak Squat`
- `RECUPERACIONES`
- `Clean full`

Esto obliga a mantener alias manuales y puede generar errores en el tonelaje o ejercicios sin mapa.

## Propuesta

### 1. Catalogo Base De Ejercicios

Crear un catalogo global/seedeado con ejercicios comunes de Weightlifting.

Campos sugeridos:

```text
id
name
slug
code
category
variant
metric_family
movement_family
percentage_reference
default_metric_code
is_unilateral
uses_percentage
is_system
is_global
active
is_active
```

No conviene guardar solamente `id | name`, porque este catalogo debe ayudar a calcular metricas y agrupar estadisticas.

Ejemplos de metadata:

```text
Snatch
category: snatch
variant: classic
metric_family: olympic_lift
movement_family: snatch
percentage_reference: snatch_1rm

Power Snatch
category: snatch
variant: power
metric_family: olympic_lift
movement_family: snatch
percentage_reference: snatch_1rm

Snatch Pull
category: snatch_pull
variant: pull
metric_family: pull
movement_family: pull
percentage_reference: snatch_1rm

Back Squat
category: squat
variant: back
metric_family: strength
movement_family: squat
percentage_reference: back_squat_1rm
```

Ejemplos:

```text
Back Squat
Front Squat
Box Squat
Snatch
Power Snatch
Hang Snatch
Clean
Power Clean
Hang Clean
Jerk
Split Jerk
Clean Pull
Snatch Pull
Recuperaciones Front Squat
Recuperaciones Back Squat
```

Familias iniciales:

```text
squat
snatch
clean
jerk
pull
accessory
unknown
```

Metricas base:

```text
back_squat_1rm
front_squat_1rm
snatch_1rm
clean_1rm
jerk_1rm
clean_jerk_1rm
```

### Base Inicial Para Seeder WL

Esta lista puede servir como primer catalogo global. No pretende cubrir todos los ejercicios de gimnasio, sino los movimientos mas comunes y utiles para Weightlifting.

#### Snatch

- Snatch
- Power Snatch
- Muscle Snatch
- Squat Snatch
- Hang Snatch
- Hang Power Snatch
- Hang Muscle Snatch
- High Hang Snatch
- High Hang Power Snatch
- Low Hang Snatch
- Low Hang Power Snatch
- Block Snatch
- Block Power Snatch
- Snatch from High Blocks
- Snatch from Low Blocks
- Deficit Snatch
- No-Foot Snatch
- No-Hook Snatch
- No-Hook No-Foot Snatch
- Pause Snatch
- Tempo Snatch
- Tall Snatch
- Snatch Balance
- Drop Snatch
- Heaving Snatch Balance

#### Clean

- Clean
- Power Clean
- Muscle Clean
- Squat Clean
- Hang Clean
- Hang Power Clean
- High Hang Clean
- High Hang Power Clean
- Low Hang Clean
- Low Hang Power Clean
- Block Clean
- Block Power Clean
- Clean from High Blocks
- Clean from Low Blocks
- Deficit Clean
- No-Foot Clean
- No-Hook Clean
- No-Hook No-Foot Clean
- Pause Clean
- Tempo Clean
- Tall Clean

#### Jerk

- Split Jerk
- Power Jerk
- Push Jerk
- Squat Jerk
- Jerk from Rack
- Jerk from Blocks
- Jerk from Behind the Neck
- Power Jerk from Behind the Neck
- Split Jerk from Behind the Neck
- Pause Jerk
- Tall Jerk
- Jerk Balance
- Press in Split
- Push Press in Split

#### Clean And Jerk

Estos se conservan como movimientos propios porque el coach frecuentemente los programa como unidad.

- Clean & Jerk
- Power Clean & Jerk
- Clean + Power Jerk
- Clean + Push Jerk
- Clean + Squat Jerk
- Hang Clean & Jerk
- Hang Power Clean & Jerk
- Block Clean & Jerk

No conviene crear todas las combinaciones posibles como ejercicios fijos. Los complejos deben manejarse de forma independiente.

#### Snatch Pulls

- Snatch Pull
- Snatch High Pull
- Snatch Pull from Hang
- Snatch Pull from Blocks
- Snatch Pull from Deficit
- Pause Snatch Pull
- Tempo Snatch Pull
- Floating Snatch Pull
- Snatch Deadlift
- Snatch Deadlift from Deficit
- Snatch Deadlift to Knee
- Pause Snatch Deadlift

#### Clean Pulls

- Clean Pull
- Clean High Pull
- Clean Pull from Hang
- Clean Pull from Blocks
- Clean Pull from Deficit
- Pause Clean Pull
- Tempo Clean Pull
- Floating Clean Pull
- Clean Deadlift
- Clean Deadlift from Deficit
- Clean Deadlift to Knee
- Pause Clean Deadlift

#### Squats

- Back Squat
- Front Squat
- Overhead Squat
- Pause Back Squat
- Pause Front Squat
- Tempo Back Squat
- Tempo Front Squat
- Box Squat
- Pin Back Squat
- Pin Front Squat
- 1 1/4 Back Squat
- 1 1/4 Front Squat
- Anderson Back Squat
- Anderson Front Squat

#### Pressing / Overhead Strength

- Strict Press
- Push Press
- Behind the Neck Press
- Behind the Neck Push Press
- Snatch Grip Press
- Snatch Grip Push Press
- Press in Snatch
- Sots Press
- Clean Grip Sots Press
- Snatch Grip Sots Press
- Seated Press
- Seated Behind the Neck Press
- Z Press

#### Deadlift / General Strength

Separados de los pulls especificos de Weightlifting.

- Conventional Deadlift
- Romanian Deadlift
- Snatch Grip Romanian Deadlift
- Clean Grip Romanian Deadlift
- Stiff-Leg Deadlift
- Good Morning
- Snatch Grip Good Morning
- Clean Grip Good Morning

#### Olympic Lifting Accessories

- Snatch Grip Shrug
- Clean Grip Shrug
- Snatch High Pull
- Clean High Pull
- Muscle Snatch from Hang
- Muscle Clean from Hang
- Snatch Grip Overhead Hold
- Jerk Support
- Front Rack Hold
- Snatch Grip Deadlift Hold
- Clean Grip Deadlift Hold

#### Unilateral / Leg Strength

Si Training Flow apunta a preparacion completa del weightlifter, estos tambien pueden entrar como base.

- Bulgarian Split Squat
- Split Squat
- Front Rack Split Squat
- Walking Lunge
- Reverse Lunge
- Front Rack Lunge
- Step-Up
- Lateral Lunge
- Single-Leg Romanian Deadlift

#### Posterior Chain / Bodybuilding

No conviene intentar cubrir todos los ejercicios de gimnasio. Solo incluir complementarios habituales para Weightlifting.

- Back Extension
- Reverse Hyperextension
- Nordic Hamstring Curl
- Glute Ham Raise
- Hip Thrust
- Barbell Row
- Pendlay Row
- Pull-Up
- Chin-Up
- Weighted Pull-Up
- Dips
- Bench Press
- Close-Grip Bench Press

### Complejos

Los complejos no deberian resolverse creando cientos de ejercicios fijos.

Ejemplo:

```text
Power Clean + Front Squat + Jerk
1 + 2 + 1 @ 80%
```

No conviene guardar esto como un ejercicio fijo llamado:

```text
Power Clean + Front Squat + Jerk
```

Lo correcto seria construir complejos dinamicamente usando movimientos base del catalogo.

Idea futura:

```text
complex_id
component_exercise_id
component_order
reps_per_round
percentage_reference
```

Asi el coach puede programar combinaciones sin inflar el catalogo global.

### 2. Configuracion Por Coach

Agregar una seccion en configuracion, posiblemente cerca de:

```text
/coach/config/settings/coach-metrics
```

Nombre sugerido:

```text
Ejercicios lifting
```

El coach podria:

- activar/desactivar ejercicios del catalogo base
- crear ejercicios propios
- asignar familia de movimiento
- asignar metrica 1RM usada para tonelaje
- ordenar ejercicios

Ejemplo:

```text
Back Squat                  activo   squat   back_squat_1rm
Front Squat                 activo   squat   front_squat_1rm
Box Squat                   activo   squat   back_squat_1rm
Recuperaciones Front Squat  activo   squat   front_squat_1rm
Clean Pull                  activo   pull    clean_1rm
```

### 3. Builder De Entrenamientos

Cuando el coach marque:

```text
Usar esquema lifting en esta seccion
```

el ejercicio deberia seleccionarse desde los ejercicios activos del coach, no escribirse libremente como fuente principal.

La tabla actual ya tiene:

```text
training_section_exercise_blocks.exercise_catalog_id
```

Ese campo podria usarse como la referencia principal.

Tambien conviene mantener el nombre como snapshot visible:

```text
exercise_catalog_id
exercise_name
```

Regla sugerida:

- Para entrenamientos nuevos, usar `exercise_catalog_id`.
- Guardar `exercise_name` como snapshot para no romper historico si cambia el nombre del catalogo.
- Para entrenamientos viejos sin `exercise_catalog_id`, seguir usando fallback por texto y alias.

## Beneficios

- Reduce errores por variantes de nombres.
- Mejora calculo de tonelaje.
- Mejora agrupacion por familia de movimiento.
- Evita warnings innecesarios de ejercicios sin mapa.
- Permite analitica mas confiable por ejercicio y movimiento.
- Permite que cada coach adapte su vocabulario sin romper la base global.

## Riesgos

Es una fase mas grande que un ajuste visual.

Toca:

- migraciones
- seeders
- pantalla de configuracion
- builder de entrenamientos
- logica de analitica
- compatibilidad con entrenamientos existentes

No conviene mezclarlo con cambios pequenos del dashboard.

## Plan Sugerido

### Fase A: Auditoria

- Revisar si `exercise_catalog_id` ya se usa en algun flujo.
- Revisar si existe algun catalogo de ejercicios previo.
- Identificar entrenamientos existentes con bloques lifting y nombres reales.

### Fase B: Modelo Y Seeder

- Crear tabla/catalogo si no existe.
- Crear seeder base de ejercicios WL.
- Definir familias y metricas default.

### Fase C: Configuracion Coach

- Agregar pantalla para activar ejercicios.
- Permitir ejercicios personalizados.
- Permitir asignar metrica y familia.

### Fase D: Builder Lifting

- Cambiar bloque lifting para seleccionar ejercicio desde catalogo activo.
- Guardar `exercise_catalog_id`.
- Mantener `exercise_name` como snapshot.

### Fase E: Analitica

- Usar catalogo como fuente principal para:
  - tonelaje
  - intensidad
  - familias de movimiento
  - ejercicios sin mapa
- Mantener fallback por texto para datos viejos.

## Decision Recomendada

Conviene implementarlo antes de invertir demasiado en graficas avanzadas.

El dashboard actual ya prueba que los datos fluyen, pero para que sea confiable a largo plazo se necesita normalizar ejercicios y dejar de depender solo de texto libre.

## Seguimiento De Adecuaciones Pendientes

Esta seccion resume lo que se avanzo en la pestana de rendimiento y lo que quedo pendiente para continuar despues.

### Ya Implementado En La Pestana Rendimiento

- La pestana `Rendimiento` ya carga datos reales en produccion.
- Se corrigio el 500 agregando fallback defensivo en el calculo de rendimiento.
- Se corrigio el problema de scroll/sidebar.
- Se agregaron KPIs superiores:
  - Sesiones
  - Reps realizadas
  - Tonelaje
  - Intensidad media
  - Adherencia
- Se compacto la fila de KPIs para mostrarlos en un solo row.
- Se agrego calculo de:
  - reps programadas
  - reps ejecutadas
  - sets completados/fallados/saltados/pendientes
  - adherencia
  - volumen relativo
  - tonelaje estimado
  - intensidad media
- Se agregaron zonas de intensidad:
  - `<60`
  - `60-69`
  - `70-79`
  - `80-89`
  - `90-94`
  - `95+`
  - `Sin %`
- Se agrego distribucion porcentual por zona usando reps ejecutadas.
- Se mejoro el aviso de datos incompletos:
  - `Falta maximo: Front Squat 1RM`
  - `Ejercicio sin metrica: Box Bak Squat`
- Se agregaron alias temporales:
  - `box back squat` -> `back_squat_1rm`
  - `box bak squat` -> `back_squat_1rm`
  - `box squat` -> `back_squat_1rm`
  - `recuperaciones front squat` -> `front_squat_1rm`
  - `recuperaciones back squat` -> `back_squat_1rm`
- `RECUPERACIONES` generico quedo como ejercicio silencioso:
  - cuenta reps/adherencia si aplica
  - no suma tonelaje
  - no aparece como warning

### Decisiones Tomadas

- No mapear `RECUPERACIONES` generico automaticamente porque no sabemos si era front squat, back squat u otra variante.
- Para proximos entrenamientos conviene usar nombres especificos:
  - `RECUPERACIONES FRONT SQUAT`
  - `RECUPERACIONES BACK SQUAT`
- El tonelaje actual es estimado desde porcentajes y maximos registrados.
- Si falta el maximo de una metrica, se muestra aviso y ese ejercicio no suma tonelaje.
- Se mantiene fallback por texto mientras no exista catalogo formal.

### Pendiente Inmediato Del Dashboard

#### Fase 3: Ejercicios Trabajados

- Ordenar ejercicios por `estimated_tonnage` descendente.
- Si un ejercicio no tiene tonelaje, usar reps ejecutadas como fallback de orden.
- Mostrar metrica usada por ejercicio, por ejemplo:
  - `Basado en Back Squat 1RM`
  - `Basado en Clean 1RM`
- Mostrar tonelaje cero como `—` cuando hay reps pero falta maximo.
- Agregar etiqueta pequena `sin maximo` cuando aplique.
- Preparar cada row para futuro detalle/historico del ejercicio.

#### Fase 4: Familias De Movimiento

- Agrupar ejercicios por familia:
  - `snatch`
  - `clean`
  - `jerk`
  - `pull`
  - `squat`
  - `accessory`
  - `unknown`
- Calcular por familia:
  - reps ejecutadas
  - tonelaje
  - distribucion porcentual
  - intensidad media
- Mostrar tabla/barras de volumen por movimiento.

#### Fase 5: Grafica Historica

- Crear estructura `timeline`.
- Agrupar por semana.
- Agrupar por mes.
- Calcular por periodo:
  - reps
  - tonelaje
  - intensidad media
  - adherencia
- Mostrar primera grafica simple en Laravel.
- Agregar filtros:
  - Semana
  - Mes
  - 3 meses
  - 6 meses
- Agregar selector:
  - Tonelaje
  - Reps
  - Intensidad

### Pendiente Tecnico Antes Del Catalogo

- Revisar codigos reales de metricas activas por coach.
- Normalizar `front_squat_1rm`:
  - nombre visible: `Front Squat 1RM`
  - codigo: `front_squat_1rm`
  - unidad: `kg`
  - tipo: `max`
- Revisar si existen metricas duplicadas o personalizadas con codigos como `FS`.
- Decidir si se permitiran unidades distintas a `kg` en el tonelaje o si se convertira todo a kg.

### Pendiente Tecnico Para Catalogo WL

- Auditar uso actual de `training_section_exercise_blocks.exercise_catalog_id`.
- Definir si el catalogo sera una tabla nueva o si se reutilizara alguna existente.
- Crear seeder base de ejercicios WL.
- Crear configuracion por coach para activar ejercicios.
- Cambiar builder lifting para usar select de ejercicios activos.
- Guardar `exercise_catalog_id` en nuevos bloques.
- Mantener `exercise_name` como snapshot visible.
- Mantener fallback por texto para entrenamientos viejos.

# Plan: calendario de actividad del atleta

Creado: 2026-09-16.

## Objetivo

Crear un calendario mensual para el atleta donde pueda ver, de forma simple, que dias entreno, que dias tenia entrenamiento asignado y no entreno, y que dias hizo actividad libre aunque no tuviera entrenamiento programado.

Esta primera version no medira porcentaje de ejecucion ni detalle por secciones. La meta es medir consistencia basica: si el atleta entreno o no entreno segun lo esperado para cada dia.

## Regla MVP

Cada dia del mes tendra un estado calculado por Laravel:

```txt
completed
Tenia entrenamiento asignado y entreno.

missed
Tenia entrenamiento asignado y no entreno.

scheduled
Tiene entrenamiento asignado en una fecha futura y todavia no aplica marcarlo como negativo.

free_completed
No tenia entrenamiento asignado, pero ejecuto un entrenamiento libre.

rest
No tenia entrenamiento asignado y no ejecuto ningun entrenamiento libre.
```

Si un dia tiene entrenamiento asignado y ademas hizo un entrenamiento libre, el estado principal sera `completed`, porque el atleta si tuvo actividad ese dia. El detalle del dia puede mostrar ambas cosas.

Si un dia tiene varios entrenamientos asignados, para esta primera fase se usara una regla simple:

```txt
Si completo al menos uno: completed.
Si no completo ninguno y el dia ya paso: missed.
Si no completo ninguno y es hoy o futuro: scheduled.
```

Mas adelante se podra evolucionar a conteos como "2 de 3 completados" o porcentajes por seccion.

## Alcance inicial

La primera fase debe entregar:

- Endpoint mensual para el atleta autenticado.
- Calculo de estado por cada dia del mes.
- Resumen del mes.
- Servicio Ionic para consumir el endpoint.
- Componente visual aislado y reutilizable.
- Integracion inicial desde la app del atleta.
- Detalle simple al seleccionar un dia.

No incluye:

- Porcentaje de ejecucion por secciones.
- Pesos por tipo de seccion.
- Estadisticas avanzadas.
- Ranking, streaks nuevos o comparativas.
- Edicion manual de dias.

## Contrato esperado de API

Endpoint propuesto:

```txt
GET /api/v1/app/activity-calendar?month=2026-09
```

Respuesta esperada:

```json
{
  "ok": true,
  "month": "2026-09",
  "summary": {
    "completed_days": 10,
    "missed_days": 3,
    "free_completed_days": 2,
    "rest_days": 15,
    "training_days": 12
  },
  "days": [
    {
      "date": "2026-09-01",
      "status": "completed",
      "assigned_count": 1,
      "assigned_completed_count": 1,
      "free_completed_count": 0
    },
    {
      "date": "2026-09-02",
      "status": "missed",
      "assigned_count": 1,
      "assigned_completed_count": 0,
      "free_completed_count": 0
    },
    {
      "date": "2026-09-03",
      "status": "scheduled",
      "assigned_count": 1,
      "assigned_completed_count": 0,
      "assigned_in_progress_count": 0,
      "free_completed_count": 0,
      "has_activity": false
    },
    {
      "date": "2026-09-04",
      "status": "free_completed",
      "assigned_count": 0,
      "assigned_completed_count": 0,
      "free_completed_count": 1
    },
    {
      "date": "2026-09-05",
      "status": "rest",
      "assigned_count": 0,
      "assigned_completed_count": 0,
      "free_completed_count": 0
    }
  ]
}
```

## Arquitectura propuesta

Laravel debe calcular el estado del dia. Ionic solo debe pintar lo que recibe.

Esto permite que en una fase futura el backend cambie la regla interna de "entreno/no entreno" a "porcentaje de ejecucion" sin rehacer el calendario visual.

### Backend

Componente sugerido:

```txt
AthleteActivityCalendarService
```

Responsabilidades:

- Resolver el atleta autenticado.
- Obtener asignaciones del mes.
- Obtener ejecuciones de entrenamientos libres del mes.
- Cruzar informacion por fecha.
- Calcular el estado de cada dia.
- Calcular el resumen mensual.

### Frontend

Servicio sugerido:

```txt
AthleteActivityCalendarService
```

Responsabilidad:

- Consumir `GET /api/v1/app/activity-calendar`.
- Exponer tipos TypeScript.
- Entregar datos listos para el componente.

Componente sugerido:

```txt
app/src/app/components/athlete-activity-calendar/
```

El componente debe ser visual y reusable. No debe decidir reglas de negocio.

Inputs propuestos:

```ts
days: ActivityCalendarDay[];
month: string;
loading: boolean;
selectedDate?: string | null;
```

Outputs propuestos:

```ts
monthChange: EventEmitter<string>;
daySelected: EventEmitter<ActivityCalendarDay>;
```

## Checkpoints

### CP1 - Auditoria de datos

Objetivo: identificar la fuente real para saber si un entrenamiento fue ejecutado.

Esperamos encontrar:

- Modelo o tabla de entrenamientos asignados.
- Modelo o tabla de entrenamientos libres ejecutados.
- Como se marca hoy una asignacion como completada.
- Como se marca hoy un entrenamiento libre como ejecutado.
- Diferencia actual entre abrir entrenamiento, guardar progreso y completar entrenamiento.

Salida esperada:

- Regla exacta de lectura documentada.
- Decision de si se puede reutilizar lo existente o si falta una marca simple de completion.

Estado: completado en auditoria inicial.

Hallazgos:

- Ya existe `training_assignments` como tabla central para el atleta, con `client_id`, `training_session_id`, `scheduled_for` y `status`.
- `status` soporta `scheduled`, `in_progress`, `completed`, `skipped` y `cancelled`.
- El flujo de entrenamientos asignados usa `POST /api/v1/app/training-assignments/{assignment}/start` para pasar a `in_progress`.
- El flujo de completar entrenamiento usa `POST /api/v1/app/training-assignments/{assignment}/complete` para pasar a `completed`.
- Las secciones sin resultado se marcan en `training_section_completions`.
- Las secciones con resultado se marcan en `training_section_results`.
- Los bloques de levantamiento/repeticiones se registran en `training_lifting_set_logs`.
- `TrainingAssignmentProgressService` ya unifica progreso desde resultados, completions y lifting logs, y sincroniza `training_assignments.status` a `in_progress` o `completed`.
- Los entrenamientos grupales se materializan como filas en `training_assignments` para el atleta cuando se consulta el feed.
- Los entrenamientos libres tambien se materializan como filas en `training_assignments` cuando el atleta inicia un entrenamiento libre desde `POST /api/v1/app/training-sessions/{trainingSession}/start`.

Regla recomendada para MVP:

```txt
Fuente principal: training_assignments.

Dia con entrenamiento asignado:
- Existe assignment con visibility assigned o derivado de grupo.
- Si status es completed o in_progress => completed para el calendario MVP.
- Si status es scheduled => missed cuando el dia ya paso o cuando se evalua como dia vencido.
- Si status es skipped o cancelled => no contarlo como entrenado; definir en CP2 si pinta como missed o neutro.

Dia con entrenamiento libre:
- Existe assignment materializado para training_sessions.visibility = free.
- Si status es completed o in_progress => free_completed cuando no habia asignado ese dia.
```

Decision de CP1:

No hace falta crear una tabla nueva para esta fase. Se puede reutilizar `training_assignments` como fuente principal y dejar las tablas de resultados/secciones/sets como soporte del status existente.

Riesgo/decision para CP2:

En entrenamientos libres, el start actual usa `scheduled_for = training_sessions.scheduled_at` si existe; si no, usa la fecha actual. Para el calendario del atleta hay que decidir si el dia libre debe contarse por `training_assignments.scheduled_for` o por el dia real de ejecucion (`created_at`/`updated_at`). Para una lectura consistente con el feed actual, la primera opcion es `scheduled_for`; para una lectura estricta de "lo ejecute hoy", podria necesitarse ajustar el start libre o guardar una fecha explicita de ejecucion.

### CP2 - Contrato backend

Objetivo: definir el contrato final del endpoint mensual.

Esperamos:

- Validacion de `month` en formato `YYYY-MM`.
- Respuesta con todos los dias del mes.
- Scoping al atleta autenticado.
- Respeto a las reglas actuales de membresia para entrenamientos.
- Estados estables: `completed`, `missed`, `scheduled`, `free_completed`, `rest`.

Salida esperada:

- Endpoint definido y listo para implementar.

Estado: completado como contrato de implementacion.

Ruta final propuesta:

```txt
GET /api/v1/app/activity-calendar?month=2026-09
```

Middleware:

```txt
auth:sanctum
client.membership
```

Decision: este calendario pertenece al modulo de entrenamientos, no al modulo de anuncios. Por lo tanto, un atleta con membresia vencida no debe acceder a este endpoint, siguiendo la regla actual de WODs y entrenamientos asignados.

Validacion:

```txt
month: nullable|string|date_format:Y-m
```

Si `month` no llega, usar el mes actual segun timezone de la app/servidor. Para el MVP se usara el timezone configurado en Laravel. En una fase posterior se puede aceptar timezone explicito desde Ionic si hace falta.

Respuesta:

```json
{
  "ok": true,
  "month": "2026-09",
  "range": {
    "from": "2026-09-01",
    "to": "2026-09-30"
  },
  "summary": {
    "completed_days": 10,
    "missed_days": 3,
    "scheduled_days": 4,
    "free_completed_days": 2,
    "rest_days": 15,
    "training_days": 12,
    "activity_days": 12
  },
  "days": [
    {
      "date": "2026-09-01",
      "status": "completed",
      "assigned_count": 1,
      "assigned_completed_count": 1,
      "assigned_in_progress_count": 0,
      "free_completed_count": 0,
      "has_activity": true
    },
    {
      "date": "2026-09-02",
      "status": "missed",
      "assigned_count": 1,
      "assigned_completed_count": 0,
      "assigned_in_progress_count": 0,
      "free_completed_count": 0,
      "has_activity": false
    },
    {
      "date": "2026-09-03",
      "status": "scheduled",
      "assigned_count": 1,
      "assigned_completed_count": 0,
      "assigned_in_progress_count": 0,
      "free_completed_count": 0,
      "has_activity": false
    },
    {
      "date": "2026-09-04",
      "status": "free_completed",
      "assigned_count": 0,
      "assigned_completed_count": 0,
      "assigned_in_progress_count": 0,
      "free_completed_count": 1,
      "has_activity": true
    },
    {
      "date": "2026-09-05",
      "status": "rest",
      "assigned_count": 0,
      "assigned_completed_count": 0,
      "assigned_in_progress_count": 0,
      "free_completed_count": 0,
      "has_activity": false
    }
  ]
}
```

Estados finales:

```txt
completed
Hay al menos un entrenamiento asignado para ese dia y el atleta tuvo actividad en alguno de esos asignados.

missed
Hay al menos un entrenamiento asignado para ese dia y no hay actividad en asignados ni libres. Solo aplica a dias anteriores a hoy.

scheduled
Hay al menos un entrenamiento asignado para una fecha futura o para hoy y todavia no hay actividad. Evita pintar en rojo algo que aun no vence.

free_completed
No hay entrenamiento asignado para ese dia, pero hay actividad en un entrenamiento libre.

rest
No hay entrenamiento asignado y no hay actividad libre.
```

Reglas de calculo:

```txt
Fuente principal:
training_assignments unido con training_sessions.

Fecha del dia:
training_assignments.scheduled_for.

Actividad:
status in ('in_progress', 'completed').

Asignado:
training_sessions.visibility = 'assigned'
o assignment materializado desde grupo para una training_session assigned.

Libre ejecutado:
training_sessions.visibility = 'free'
y existe training_assignment para el atleta con status in ('in_progress', 'completed').
```

Prioridad si hay varios datos el mismo dia:

```txt
1. Si assigned_count > 0 y hay actividad asignada o libre: completed.
2. Si assigned_count > 0, no hay actividad y date < today: missed.
3. Si assigned_count > 0, no hay actividad y date >= today: scheduled.
4. Si assigned_count = 0 y free_completed_count > 0: free_completed.
5. Si no hay asignado ni actividad: rest.
```

Nota para el dia actual:

Para evitar pintar negativo muy temprano, el dia actual sin actividad se considera `scheduled`. Si el atleta entrena durante el dia, cambia a `completed`. En una fase posterior se puede agregar una hora de corte si el negocio quiere marcar el dia actual como `missed` despues de cierta hora.

Decision sobre entrenamientos libres:

Para esta fase, usar `training_assignments.scheduled_for` tambien en libres. Esto mantiene el calendario consistente con el feed actual. No se agrega `executed_at` todavia.

Riesgo aceptado:

Si un entrenamiento libre tiene `training_sessions.scheduled_at` historico y el atleta lo inicia hoy, el assignment puede quedar con esa fecha historica. Si esto se vuelve problema, la mejora correcta sera ajustar el start libre para guardar una fecha real de ejecucion o agregar `executed_at`. No se hara en CP2 porque este checkpoint es contrato.

Casos minimos de contrato:

```txt
1. Sin month: devuelve mes actual.
2. Month invalido: 422.
3. Atleta sin client_id: 422.
4. Atleta con membresia vencida: 403 membership_expired por middleware.
5. Mes sin datos: devuelve todos los dias como rest.
6. Asignado completed/in_progress: dia completed.
7. Asignado scheduled de dia pasado: dia missed.
8. Asignado scheduled de hoy o futuro: dia scheduled.
9. Libre in_progress/completed sin asignado: dia free_completed.
10. Asignado scheduled + libre in_progress/completed: dia completed.
```

### CP3 - Servicio de dominio Laravel

Objetivo: aislar el calculo fuera del controller.

Esperamos:

- Servicio dedicado para construir el calendario mensual.
- Logica centralizada para asignados, libres y resumen.
- Codigo preparado para evolucionar a porcentajes despues.

Salida esperada:

- Servicio testeable y reutilizable.

Estado: completado.

Implementacion:

- Servicio creado en `app/Services/AthleteActivityCalendarService.php`.
- Metodo principal: `month(int $clientId, ?string $month = null): array`.
- Entrada: `client_id` y mes opcional en formato `YYYY-MM`.
- Salida: `ok`, `month`, `range`, `summary` y `days`.

Reglas implementadas:

- Lee `training_assignments` unido con `training_sessions`.
- Filtra por `client_id`, `scheduled_for` dentro del mes y sesiones no eliminadas.
- Usa `training_assignments.scheduled_for` como fecha del calendario.
- Considera actividad cuando `status` es `in_progress` o `completed`.
- Distingue asignados con `training_sessions.visibility = assigned`.
- Distingue libres ejecutados con `training_sessions.visibility = free` y status activo.
- Devuelve todos los dias del mes, aunque no haya registros.
- Calcula estados: `completed`, `missed`, `scheduled`, `free_completed`, `rest`.
- Calcula resumen: `completed_days`, `missed_days`, `scheduled_days`, `free_completed_days`, `rest_days`, `training_days`, `activity_days`.

Prueba agregada:

- `tests/Feature/AthleteActivityCalendarServiceTest.php`.

Validacion:

```txt
php artisan test --filter=AthleteActivityCalendarServiceTest --do-not-cache-result
Resultado: PASS, 1 test, 11 assertions.

php -l app/Services/AthleteActivityCalendarService.php
Resultado: No syntax errors detected.

php -l tests/Feature/AthleteActivityCalendarServiceTest.php
Resultado: No syntax errors detected.
```

Pendiente para CP4:

- Crear controller/request/ruta API.
- Conectar `client.membership`.
- Validar `month`.
- Exponer el servicio al consumo Ionic.

### CP4 - API Laravel

Objetivo: exponer el calendario mensual.

Esperamos:

- Controller API para atleta.
- Request validation.
- Resource o DTO de respuesta.
- Tests de casos base.

Casos minimos:

- Dia con entrenamiento asignado y completado.
- Dia con entrenamiento asignado y no completado.
- Dia sin asignado pero con libre ejecutado.
- Dia sin asignado y sin actividad.
- Atleta no puede ver informacion de otro atleta.

Salida esperada:

- Endpoint listo para Ionic.

Estado: completado.

Implementacion:

- Controller creado en `app/Http/Controllers/Api/V1/App/Client/ActivityCalendarController.php`.
- Ruta agregada en `routes/api.php`.
- Endpoint final: `GET /api/v1/app/activity-calendar`.
- Ubicacion: dentro del grupo `auth:sanctum` + `client.membership`.
- Validacion: `month` nullable con formato `Y-m`.
- El controller resuelve `client_id` desde el atleta autenticado y delega el calculo a `AthleteActivityCalendarService`.

Prueba agregada:

- `tests/Feature/AppActivityCalendarApiTest.php`.

Casos cubiertos:

- Atleta activo consulta el calendario mensual.
- `month` invalido responde 422 con error de validacion.
- Atleta vencido queda bloqueado por `client.membership` con `membership_expired`.

Validacion:

```txt
php artisan test --filter=ActivityCalendar --do-not-cache-result
Resultado: PASS, 4 tests, 37 assertions.

php -l app/Http/Controllers/Api/V1/App/Client/ActivityCalendarController.php
Resultado: No syntax errors detected.

php -l tests/Feature/AppActivityCalendarApiTest.php
Resultado: No syntax errors detected.

php -l app/Services/AthleteActivityCalendarService.php
Resultado: No syntax errors detected.

php -l tests/Feature/AthleteActivityCalendarServiceTest.php
Resultado: No syntax errors detected.

php -l routes/api.php
Resultado: No syntax errors detected.

php artisan route:list --path=api/v1/app/activity-calendar
Resultado: GET|HEAD api/v1/app/activity-calendar -> ActivityCalendarController@index.
```

Pendiente para CP5:

- Crear servicio Ionic para consumir `GET /api/v1/app/activity-calendar`.
- Definir tipos TypeScript alineados con este contrato.

### CP5 - Servicio Ionic

Objetivo: crear la capa de consumo en la app.

Esperamos:

- Tipos TypeScript para respuesta mensual.
- Metodo `month(month: string)`.
- Manejo de loading/error desde la pagina, no desde el componente visual.

Salida esperada:

- Servicio reusable sin API cruda en componentes.

Estado: completado.

Implementacion:

- Servicio creado en `app/src/app/services/athlete-activity-calendar.service.ts`.
- Spec creada en `app/src/app/services/athlete-activity-calendar.service.spec.ts`.
- Metodo principal: `month(month?: string | null): Promise<ActivityCalendarResponse>`.
- Endpoint consumido: `GET app/activity-calendar`.

Tipos TypeScript expuestos:

- `ActivityCalendarDayStatus`.
- `ActivityCalendarRangeDTO`.
- `ActivityCalendarSummaryDTO`.
- `ActivityCalendarDayDTO`.
- `ActivityCalendarResponse`.

Reglas del servicio:

- Si no se pasa mes, llama el endpoint sin query params para que Laravel use el mes actual.
- Si se pasa mes, limpia espacios y manda `{ month: 'YYYY-MM' }`.
- El componente/pagina que use este servicio recibira el contrato ya tipado y no tendra que consumir `ApiService` directamente.

Validacion:

```txt
ng build
Resultado: compilacion exitosa.

Warnings no bloqueantes:
- Stencil empty-glob.
- Budgets SCSS existentes en tab1, user-profile y training-details.
```

Pendiente para CP6:

- Construir componente visual aislado `athlete-activity-calendar`.
- Recibir datos por inputs y emitir seleccion/cambio de mes por outputs.

### CP6 - Componente visual aislado

Objetivo: construir un calendario reutilizable.

Esperamos:

- Grid mensual.
- Navegacion mes anterior/siguiente.
- Estado visual por dia.
- Leyenda basica.
- Evento al seleccionar dia.

Estados visuales sugeridos:

```txt
completed       verde
missed          rojo suave
free_completed  azul o verde con indicador distinto
rest            gris/vacio
today           borde
selected        anillo o fondo resaltado
```

Salida esperada:

- Componente desacoplado de reglas de negocio.

Estado: completado.

Implementacion:

- Componente standalone creado en `app/src/app/components/athlete-activity-calendar/`.
- Archivos:
  - `athlete-activity-calendar.component.ts`
  - `athlete-activity-calendar.component.html`
  - `athlete-activity-calendar.component.scss`

Inputs implementados:

```ts
days: ActivityCalendarDayDTO[];
month: string;
loading: boolean;
selectedDate: string | null;
```

Outputs implementados:

```ts
monthChange: EventEmitter<string>;
daySelected: EventEmitter<ActivityCalendarDayDTO>;
```

Responsabilidades del componente:

- Renderizar el mes recibido.
- Construir grid mensual iniciando en lunes.
- Mostrar dias vacios al inicio/final para completar semanas.
- Pintar estados visuales: `completed`, `missed`, `scheduled`, `free_completed`, `rest`.
- Mostrar dia actual.
- Mostrar dia seleccionado.
- Emitir mes anterior/siguiente en formato `YYYY-MM`.
- Emitir el dia seleccionado.
- Mostrar leyenda visual.

El componente no consume API y no calcula reglas de negocio. Solo pinta el contrato recibido desde Ionic/Laravel.

Validacion:

```txt
ng build
Resultado: compilacion exitosa.

Warnings no bloqueantes:
- Stencil empty-glob.
- Budgets SCSS existentes en user-profile, tab1 y training-details.
```

Pendiente para CP7:

- Crear pagina o integracion inicial.
- Decidir acceso desde Home o Perfil.
- Cargar datos usando `AthleteActivityCalendarService`.
- Pasar datos al componente y manejar seleccion de dia.

### CP7 - Integracion inicial

Objetivo: decidir donde vive primero en la app.

Recomendacion:

- Crear una pagina propia o seccion de historial.
- Acceder desde una card en Home o Perfil.
- No agregarlo como tab principal hasta validar uso real.

Salida esperada:

- Navegacion clara hacia el calendario.

Estado: completado.

Decision:

- Se creo una pagina propia en Ionic: `/activity-calendar`.
- No se agrego como tab principal para no cargar la navegacion base.
- El acceso inicial se agrego desde Home (`tab1`) como una tarjeta reutilizando estilos existentes de la seccion de Tips, para no crecer el SCSS de Home por encima del budget.

Implementacion:

- Pagina creada en `app/src/app/pages/activity-calendar/`.
- Archivos:
  - `activity-calendar.page.ts`
  - `activity-calendar.page.html`
  - `activity-calendar.page.scss`
- Ruta agregada en `app/src/app/app.routes.ts`.
- Acceso agregado en `app/src/app/tab1/tab1.page.html`.
- Metodo de navegacion agregado en `app/src/app/tab1/tab1.page.ts`.

Responsabilidades de la pagina:

- Cargar datos con `AthleteActivityCalendarService`.
- Manejar `loading`, `errorMsg`, `month` y `selectedDay`.
- Pasar datos al componente `app-athlete-activity-calendar`.
- Manejar `monthChange` y `daySelected`.
- Mostrar resumen mensual.
- Mostrar detalle simple del dia seleccionado.

Validacion:

```txt
ng build
Resultado: compilacion exitosa.

Warnings no bloqueantes:
- Stencil empty-glob.
- Budgets SCSS existentes en tab1, user-profile y training-details.
```

Nota:

No se hizo QA visual en navegador/dispositivo en este checkpoint. La validacion fue de compilacion.

### CP8 - Detalle del dia

Objetivo: explicar el estado del dia al atleta.

Ejemplo dia entrenado:

```txt
Estado: Entrenaste
Asignados: 1
Completados: 1
Libres realizados: 0
```

Ejemplo dia negativo:

```txt
Estado: No entrenaste
Tenias 1 entrenamiento asignado.
```

Salida esperada:

- El atleta entiende por que el dia se pinto de cierto color.

Estado: completado.

Implementacion:

- El detalle del dia vive en la pagina `app/src/app/pages/activity-calendar/`.
- Archivos ajustados:
  - `activity-calendar.page.ts`
  - `activity-calendar.page.html`
  - `activity-calendar.page.scss`

Comportamiento:

- Al seleccionar un dia del calendario, la pagina muestra:
  - Fecha amigable.
  - Estado en texto humano.
  - Badge visual del estado.
  - Explicacion del motivo.
  - Conteos de asignados, asignados con actividad y libres.
- El dia actual se selecciona por defecto si existe en el mes cargado.
- Los estados tecnicos no se muestran crudos al atleta; se traducen a textos como `Entrenado`, `Pendiente`, `Programado`, `Libre` y `Descanso`.

Decision MVP:

- El detalle muestra conteos y explicacion, no lista de entrenamientos individuales.
- Para listar nombres de entrenamientos por dia haria falta ampliar el contrato del backend o hacer una consulta adicional. Se deja fuera de esta fase para mantener el MVP simple.

Validacion:

```txt
ng build
Resultado: compilacion exitosa.

Warnings no bloqueantes:
- Stencil empty-glob.
- Budgets SCSS existentes en user-profile, tab1 y training-details.
```

### CP9 - Validacion

Objetivo: cerrar el MVP con evidencia.

Validaciones esperadas:

- Mes actual carga correctamente.
- Cambiar de mes funciona.
- Dia asignado completado sale positivo.
- Dia asignado no completado sale negativo.
- Dia libre ejecutado sale positivo.
- Dia sin nada queda neutro.
- Atleta con membresia vencida sigue las reglas actuales de acceso a entrenamientos.

## Decision pendiente principal

Antes de implementar hay que confirmar la fuente real de "entreno":

```txt
Que dato actual significa que el atleta ejecuto un entrenamiento?
```

Opciones posibles:

- Registro de completion por assignment.
- Resultado guardado por seccion.
- Registro de entrenamiento libre ejecutado.
- Otra tabla existente.
- Si no existe, crear una marca simple de completion.

La implementacion debe empezar por CP1 para evitar inventar una tabla si ya hay una fuente confiable.

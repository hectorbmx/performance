# Plan de ejecucion: crear entrenamientos desde calendario del atleta

## Objetivo

Cuando el coach este en el calendario de un atleta (`/coach/clients/{client}/trainings`) y presione el boton `+` en una fecha, el sistema debe crear un entrenamiento asignado directamente a ese atleta, regresar al calendario del atleta despues de guardar y mostrar el entrenamiento en ese calendario.

## Alcance

- Cambiar el flujo del boton `+` dentro del calendario del atleta.
- Preseleccionar el atleta en el formulario general de creacion de entrenamiento.
- Forzar el contexto inicial a `visibility = assigned` cuando se llega desde un atleta.
- Guardar la asignacion directa en `training_assignments`.
- Redirigir de vuelta al calendario del atleta despues de guardar.
- Revisar si el calendario del atleta debe mostrar tambien entrenamientos asignados por grupo.

## Fuera de alcance para esta fase

- No crear tablas nuevas.
- No migraciones.
- No borrar, refrescar, truncar, reiniciar ni recrear base de datos.
- No cambiar el flujo general de `/coach/trainings/create` cuando se abre desde el calendario general.
- No implementar drag and drop.
- No convertir el formulario general en formulario exclusivo del atleta.

## Estado actual detectado

- La ruta del calendario del atleta es:
  - `GET /coach/clients/{client}/trainings`
  - Controlador: `App\Http\Controllers\Coach\CoachClientTrainingController@index`
  - Vista: `resources/views/coach/clients/trainings/index.blade.php`

- El boton `+` del calendario del atleta apunta al formulario general:
  - `route('coach.trainings.create', ['date' => $key])`

- El formulario general solo recibe `date`:
  - `TrainingSessionController@create`
  - `$date = $request->get('date');`

- El formulario general inicia como `Libre` por default:
  - `old('visibility', 'free')`

- El guardado ya soporta asignaciones si llegan `assigned_clients[]`.

- Despues de guardar, el flujo actual redirige al calendario/listado general:
  - `route('coach.trainings.index')`

- El calendario del atleta hoy filtra solo asignaciones directas por `assignedClients`.
  - No incluye aun entrenamientos asignados por grupo.

## Comportamiento esperado

1. Desde `/coach/clients/{client}/trainings`, el boton `+` de cada fecha debe abrir:
   - `/coach/trainings/create?date=YYYY-MM-DD&client_id={client_id}`

2. El formulario de creacion debe:
   - validar que `client_id` pertenece al coach autenticado;
   - cargar ese cliente como `$presetClient`;
   - abrir `visibility` en `assigned`;
   - mostrar el bloque de asignacion;
   - renderizar el atleta como chip seleccionado;
   - incluir `assigned_clients[]` con el ID del atleta;
   - incluir `return_client_id` para saber a donde regresar.

3. Al guardar:
   - crear `training_sessions.visibility = assigned`;
   - crear `training_assignments.client_id = {client_id}`;
   - usar `scheduled_for = training_sessions.scheduled_at`;
   - redirigir a `/coach/clients/{client}/trainings?view=calendar&month=YYYY-MM`;
   - mostrar toast de exito por el `session('success')` global.

4. En el calendario del atleta:
   - el entrenamiento creado debe verse en la fecha correspondiente;
   - debe abrir edicion al hacer clic en el entrenamiento;
   - no debe mostrarse como entrenamiento libre dentro del contexto del atleta.

## Checkpoints

### Checkpoint 1: parametrizar el boton `+` del calendario del atleta

Estado: completado.

Archivo:
- `resources/views/coach/clients/trainings/index.blade.php`

Cambiar el enlace actual:

```php
route('coach.trainings.create', ['date' => $key])
```

Por un enlace con contexto:

```php
route('coach.trainings.create', [
    'date' => $key,
    'client_id' => $client->id,
])
```

Criterios de aceptacion:
- Desde el calendario del atleta, el `+` conserva la fecha.
- La URL incluye `client_id`.
- Desde el calendario general no cambia nada.

Validacion:
- Revisar HTML generado.
- Abrir manualmente una fecha desde `/coach/clients/{client}/trainings`.

### Checkpoint 2: validar y cargar `$presetClient` en `create`

Estado: completado.

Archivo:
- `app/Http/Controllers/Coach/TrainingSessionController.php`

Agregar lectura opcional de `client_id` en `create`.

Reglas:
- Si no llega `client_id`, comportamiento actual intacto.
- Si llega `client_id`, buscar cliente activo del coach autenticado.
- Si el cliente no pertenece al coach o no existe, abortar con `404` o ignorar de forma segura.

Variable esperada para la vista:

```php
$presetClient
```

Criterios de aceptacion:
- `create` sigue funcionando sin `client_id`.
- `create?client_id={id}` carga el atleta si pertenece al coach.
- No se permite preseleccionar atletas de otro coach.

Validacion:
- `php -l app/Http/Controllers/Coach/TrainingSessionController.php`
- Prueba manual abriendo create con y sin `client_id`.

### Checkpoint 3: preseleccionar visibilidad y atleta en el formulario

Estado: completado.

Archivo:
- `resources/views/coach/trainings/create.blade.php`

Ajustes:
- Si existe `$presetClient`, el select `visibility` debe iniciar en `assigned`.
- El bloque `assignBlock` debe estar visible al cargar.
- El chip del atleta debe aparecer en `assignedClientsPills`.
- Debe existir:

```html
<input type="hidden" name="assigned_clients[]" value="{presetClient.id}">
```

- Agregar hidden para retorno:

```html
<input type="hidden" name="return_client_id" value="{presetClient.id}">
```

Criterios de aceptacion:
- El usuario no tiene que buscar al atleta manualmente.
- El usuario puede quitar el atleta si decide cambiar asignacion, pero entonces aplica la regla existente de "asignado requiere al menos uno".
- El flujo general sin `client_id` sigue iniciando como `Libre`.

Validacion:
- `php artisan view:cache`
- `php artisan view:clear`
- Prueba manual visual del formulario.

### Checkpoint 4: validar `return_client_id` en `store`

Estado: completado.

Archivo:
- `app/Http/Controllers/Coach/TrainingSessionController.php`

Agregar validacion opcional:

```php
'return_client_id' => ['nullable', 'integer']
```

Despues de validar datos:
- Si llega `return_client_id`, confirmar que el cliente pertenece al coach.
- No confiar en el hidden input sin validacion.

Criterios de aceptacion:
- Un `return_client_id` invalido no permite redirigir a cliente ajeno.
- El guardado general no cambia si no llega `return_client_id`.

Validacion:
- `php -l app/Http/Controllers/Coach/TrainingSessionController.php`

### Checkpoint 5: redirigir al calendario del atleta despues de guardar

Estado: completado.

Archivo:
- `app/Http/Controllers/Coach/TrainingSessionController.php`

Cuando el entrenamiento se cree desde contexto de atleta:

```php
return redirect()
    ->route('coach.clients.trainings.index', [
        'client' => $returnClientId,
        'view' => 'calendar',
        'month' => Carbon::parse($training->scheduled_at)->format('Y-m'),
    ])
    ->with('success', 'Entrenamiento creado correctamente.');
```

Criterios de aceptacion:
- Desde calendario del atleta, guardar regresa al calendario del atleta.
- Desde calendario general, guardar sigue yendo al calendario/listado general actual.
- El toast global de exito aparece.

Validacion:
- Crear manualmente un entrenamiento desde calendario del atleta.
- Confirmar redireccion y toast.

### Checkpoint 6: revisar consulta del calendario del atleta

Estado: completado.

Archivo:
- `app/Http/Controllers/Coach/CoachClientTrainingController.php`

Estado actual:
- Consulta solo entrenamientos con asignacion directa:

```php
whereHas('assignedClients', ...)
```

Decision tomada:
- Opcion B: mostrar todo lo que le toca al atleta:
  - asignaciones directas en `training_assignments`;
  - asignaciones por grupos donde el atleta pertenezca al grupo.

Criterios de aceptacion para opcion B:
- Si el atleta esta en un grupo con entrenamiento asignado, aparece en su calendario.
- No duplica entrenamientos si estan asignados directo y por grupo.
- Sigue filtrando por `coach_id`.

Validacion:
- Crear/usar un grupo con atleta.
- Asignar entrenamiento al grupo.
- Confirmar que aparece en calendario del atleta.

### Checkpoint 7: validacion final no destructiva

Estado: completado.

Comandos permitidos:

```bash
php -l app/Http/Controllers/Coach/TrainingSessionController.php
php -l app/Http/Controllers/Coach/CoachClientTrainingController.php
php artisan view:cache
php artisan view:clear
```

No ejecutar:

```bash
php artisan migrate:fresh
php artisan db:wipe
php artisan migrate:refresh
php artisan test
vendor/bin/phpunit
```

Salvo autorizacion explicita y confirmacion de base aislada.

### Checkpoint 8: prueba manual esperada

Flujo:

1. Entrar a `/coach/clients/{client}/trainings`.
2. Presionar `+` en una fecha.
3. Confirmar que abre `/coach/trainings/create?date=YYYY-MM-DD&client_id={client}`.
4. Confirmar que el formulario abre en `Asignado`.
5. Confirmar que el atleta ya aparece seleccionado.
6. Completar datos minimos del entrenamiento.
7. Guardar.
8. Confirmar redireccion al calendario del atleta.
9. Confirmar que el entrenamiento aparece en la fecha seleccionada.
10. Confirmar que en calendario general aparece como `Asignado`, no como flujo libre.

## Riesgos y cuidados

- Si el usuario quita el atleta preseleccionado y no agrega otro atleta/grupo, debe bloquearse el submit por la regla existente.
- Si se permite asignacion por grupo en el calendario del atleta, hay que evitar duplicados cuando el entrenamiento tambien esta asignado directo.
- El parametro `client_id` no debe usarse sin validar propiedad por `coach_id`.
- El hidden `return_client_id` solo debe controlar redireccion, no permisos.
- No modificar datos existentes durante validaciones automaticas.

## Resultado esperado al terminar

El calendario del atleta queda como un flujo contextual:
- el `+` crea entrenamientos para ese atleta;
- el entrenamiento nace asignado;
- el atleta queda preseleccionado;
- al guardar se regresa al calendario del atleta;
- el entrenamiento se ve en el calendario del atleta.

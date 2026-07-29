# Roadmap: Lifting Builder Y Registro Por Serie

## Objetivo

Crear un flujo especializado para entrenamientos tipo `Lifting` donde el coach capture ejercicios en formato tipo Excel y el atleta marque la ejecucion de cada serie desde la app.

El objetivo no es reemplazar las secciones actuales de entrenamiento, sino agregar una estructura dinamica para prescripciones de lifting que permita medir cumplimiento, fallos por porcentaje y progresion por ejercicio.

## Concepto De Producto

Cuando el coach seleccione un tipo de entrenamiento `Lifting`, el editor debe ofrecer bloques por ejercicio.

Cada bloque tiene:

- Ejercicio: preferentemente seleccionado desde el catalogo de ejercicios cuando exista.
- Nombre manual de ejercicio como fallback.
- Notas generales del ejercicio o indicacion tecnica.
- Tabla de rows tipo Excel.

Columnas iniciales de la tabla:

- `%`
- `Reps`
- `Series`
- `Descanso`
- `Notas`

No se agrega columna de peso objetivo porque el peso se deriva del porcentaje y del RM o referencia del atleta. El registro del atleta puede guardar reps reales y motivo de fallo, pero no se debe mezclar el peso objetivo en la prescripcion base de este MVP.

Nota visual recibida para app movil:

- En la app el atleta puede ver un peso calculado, por ejemplo `100kg x 3`, siempre que exista RM/base de carga para resolver el porcentaje.
- Ese peso no viene de una columna capturada por el coach en el builder lifting.
- Si no existe base de carga, la app debe mostrar la prescripcion como `% x reps`.

## Ejemplo

### Powers desde bancos

Notas:

- Mismos pesos para los dos movimientos.
- Enfocate en el despegue explosivo y el segundo jalon.

| % | Reps | Series | Descanso | Notas |
|---:|---:|---:|---|---|
| 60 | 3 | 3 | 2:00 | |
| 70 | 3 | 3 | 2:00 | |
| 75 | 3 | 3 | 2:30 | |
| 80 | 2 | 4 | 3:00 | |

### Front squat

| % | Reps | Series | Descanso | Notas |
|---:|---:|---:|---|---|
| 70 | 3 | 3 | 2:00 | |
| 80 | 3 | 3 | 2:30 | |
| 85 | 3 | 1 | 3:00 | |
| 90 | 2 | 3 | 3:00 | |
| 93 | 1 | 3 | 3:30 | |

## Experiencia Coach

### Crear/editar entrenamiento

- La cabecera conserva datos generales del entrenamiento.
- El builder lifting se activa por seccion con un check tipo `Usar esquema lifting en esta seccion`.
- Si una seccion activa lifting, mostrar un modulo de ejercicios con tablas dentro de esa seccion.
- Mantener secciones tradicionales para tipos no lifting.
- Permitir agregar, duplicar, borrar y reordenar ejercicios.
- Permitir agregar, duplicar, borrar y reordenar rows dentro de cada ejercicio.
- Permitir pegar rows desde texto/Excel si el alcance lo permite en una fase posterior.
- Validar que cada row tenga porcentaje, reps y series validas.
- Descanso puede capturarse como texto corto en UI, pero persistirse normalizado en segundos cuando sea posible.

### Relacion con catalogo de ejercicios

El catalogo de ejercicios esta documentado en `docs/exercises-dataset-integration-roadmap.md`, pero no debe bloquear este MVP.

El bloque de ejercicio debe soportar:

- `exercise_catalog_id` nullable.
- `exercise_name` obligatorio cuando no haya ejercicio seleccionado.

Cuando el catalogo este disponible, el buscador llenara `exercise_catalog_id` y `exercise_name`. Mientras tanto, el coach puede escribir el nombre manualmente.

## Experiencia Atleta

La app no debe mostrar una tabla compleja. Debe transformar cada row en series marcables.

Ejemplo:

`80% · 4 series x 2 reps · descanso 3:00`

- Serie 1: completada / fallada.
- Serie 2: completada / fallada.
- Serie 3: completada / fallada.
- Serie 4: completada / fallada.

Si falla una serie:

- Reps logradas.
- Motivo opcional: pesado, tecnica, dolor, fatiga, otro.
- Nota opcional.

Debe existir una accion rapida para marcar un row completo cuando todas las series fueron completadas.

## Modelo De Datos Propuesto

### `training_section_exercise_blocks`

Bloques de ejercicio dentro de una seccion.

Campos:

- `id`
- `training_section_id`
- `exercise_catalog_id` nullable
- `exercise_name`
- `notes` nullable
- `order` unsigned integer default 1
- `created_at`
- `updated_at`

Indices:

- index `training_section_id`, `order`
- foreign key a `training_sections` con cascade delete
- foreign key nullable a `exercise_catalogs` si existe la tabla

### `training_section_lifting_rows`

Rows de prescripcion tipo Excel para un ejercicio.

Campos:

- `id`
- `exercise_block_id`
- `percentage` decimal nullable
- `reps` unsigned integer
- `sets` unsigned integer
- `rest_seconds` unsigned integer nullable
- `notes` nullable
- `order` unsigned integer default 1
- `created_at`
- `updated_at`

Indices:

- index `exercise_block_id`, `order`
- foreign key a `training_section_exercise_blocks` con cascade delete

### `training_lifting_set_logs`

Registro real de ejecucion del atleta por serie.

Campos:

- `id`
- `training_assignment_id`
- `lifting_row_id`
- `set_number`
- `status` enum/string: `completed`, `failed`, `skipped`
- `actual_reps` unsigned integer nullable
- `failure_reason` nullable
- `notes` nullable
- `logged_at` nullable
- `created_at`
- `updated_at`

Indices:

- unique `training_assignment_id`, `lifting_row_id`, `set_number`
- index `training_assignment_id`
- index `lifting_row_id`

## Contrato API Movil

El detalle de entrenamiento debe incluir bloques lifting cuando existan.

Shape sugerido:

```json
{
  "sections": [
    {
      "id": 55,
      "name": "Bloque principal",
      "lifting_blocks": [
        {
          "id": 10,
          "exercise_catalog_id": null,
          "exercise_name": "Front squat",
          "notes": "Mantener torso vertical",
          "rows": [
            {
              "id": 101,
              "percentage": 70,
              "reps": 3,
              "sets": 3,
              "rest_seconds": 120,
              "notes": null,
              "set_statuses": [
                { "set_number": 1, "status": "completed", "actual_reps": 3 },
                { "set_number": 2, "status": "failed", "actual_reps": 2 },
                { "set_number": 3, "status": null, "actual_reps": null }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

Reglas:

- Solo devolver bloques de entrenamientos visibles/asignados al atleta.
- Mantener compatibilidad con secciones existentes sin lifting.
- No requerir que el catalogo de ejercicios este implementado para devolver `exercise_name`.

## Fases

### Fase 0: Decision De Alcance

- [x] Definir columnas MVP: `%`, `Reps`, `Series`, `Descanso`, `Notas`.
- [x] Excluir peso objetivo de la prescripcion base.
- [x] Permitir ejercicio manual mientras el catalogo no este listo.
- [ ] Confirmar nombre visual del tipo: `Lifting`.
- [x] Definir si el builder aparece solo para tipo exacto `Lifting` o por flag configurable del tipo.
  - Decision ajustada: el builder se activa por seccion mediante checkbox; `training_type_catalogs.behavior` queda como metadata util, pero no controla la visibilidad del builder en la cabecera.

### Fase 1: Persistencia Laravel

- [x] Crear migraciones.
- [x] Crear modelos.
- [x] Agregar relaciones en `TrainingSection`.
- [ ] Agregar factories/fixtures minimos si aplica.
- [x] Validar migracion y rollback.

Checkpoint Fase 1:

- Agregada migracion `2026_07_28_120000_add_behavior_to_training_type_catalogs_table.php`.
- Agregadas tablas `training_section_exercise_blocks`, `training_section_lifting_rows`, `training_lifting_set_logs`.
- Agregados modelos `TrainingSectionExerciseBlock`, `TrainingSectionLiftingRow`, `TrainingLiftingSetLog`.
- Agregadas relaciones `TrainingSection::liftingBlocks()` y `TrainingAssignment::liftingSetLogs()`.
- Agregado helper `TrainingTypeCatalog::usesLiftingBuilder()`.
- Validado `php -l` en modelos y migraciones nuevas.
- Validado `php artisan migrate`, `php artisan migrate:rollback --step=4` y `php artisan migrate` final.

### Fase 2: Editor Coach

- [ ] Mostrar builder lifting al seleccionar tipo `Lifting`.
- [ ] Agregar CRUD dinamico de ejercicios.
- [ ] Agregar CRUD dinamico de rows.
- [x] Guardar bloques y rows en `TrainingSessionController@store`.
- [x] Cargar bloques y rows en `edit`.
- [x] Sincronizar cambios en `TrainingSessionController@update`.
- [ ] Mantener secciones, videos y resultados actuales sin regresion.

Checkpoint Fase 2.1 Backend:

- `TrainingSessionController@store` acepta `sections.*.lifting_blocks`.
- `TrainingSessionController@update` acepta y sincroniza `sections.*.lifting_blocks`.
- El update solo borra/sincroniza bloques lifting si el payload `lifting_blocks` viene presente, para no afectar la UI actual que todavia no lo envia.
- `edit` carga `sections.liftingBlocks.rows`.
- `create` y `edit` exponen `types.behavior` para que la UI pueda activar el builder sin comparar por nombre.
- `store` valida `training_type_catalog_id` contra tipos del coach.
- Validado `php -l app\Http\Controllers\Coach\TrainingSessionController.php`.
- Validado `php artisan route:list --path=coach/trainings`.
- Prueba transaccional por `tinker` no se completo por quoting de PowerShell/PsySH; retomar con feature test o endpoint real cuando exista UI/payload.

Checkpoint Fase 2.2 Create UI:

- `resources/views/coach/trainings/create.blade.php` muestra un checkbox `Usar esquema lifting en esta seccion` dentro de cada seccion.
- Cuando el checkbox de una seccion esta activo, muestra el builder solo para esa seccion.
- El builder permite agregar ejercicios y rows con columnas `%`, `Reps`, `Series`, `Descanso`, `Notas`.
- El JS genera nombres compatibles con backend: `sections[x][lifting_blocks][y][rows][z]`.
- Si el checkbox de la seccion esta apagado, los inputs del builder quedan deshabilitados para no enviar payload accidental.
- Descanso acepta segundos o formato `m:ss`; en blur se normaliza a segundos.
- Validado `php -l resources\views\coach\trainings\create.blade.php`.
- Validado `php artisan view:cache` y luego `php artisan view:clear`.
- Pendiente: replicar/hidratar el builder en `edit.blade.php` con ids de bloques y rows existentes.

Checkpoint QA entrenamiento 4 y ajustes visuales:

- Revisado entrenamiento `4` con `tinker`: se guardaron 3 secciones.
- Seccion `Warm up`: descripcion `calienta`, sin bloques lifting.
- Seccion `Snatch`: descripcion `Vamos por oleadas de reps con cierto descanso`, bloque `snatch` con 4 rows.
- Seccion `Clean`: bloque `clean` con 3 rows.
- `TrainingSessionController@index` ahora usa `calendar` como vista predeterminada para `/coach/trainings`.
- Agregado modal de carga al submit en `create.blade.php` y `edit.blade.php`.
- Corregido layout de cabecera en `edit.blade.php`: portada y notas ahora ocupan columnas definidas y no se comprimen.
- `edit.blade.php` muestra los bloques lifting existentes con ids de bloque y row para conservarlos al guardar.
- Validado `php -l` en controller, create y edit.
- Validado `php artisan view:cache` y luego `php artisan view:clear`.

### Fase 3: API Atleta

- [x] Cargar `sections.liftingBlocks.rows` en detalle de entrenamiento/asignacion.
- [x] Exponer contrato compatible en detalle de entrenamiento/asignacion.
- [ ] Incluir campos opcionales para display mobile si se puede calcular carga: `calculated_weight`, `weight_unit` o equivalente.
- [ ] Mantener esos campos nullable para no bloquear atletas sin RM/base de carga.
- [x] Crear endpoint para guardar logs por row o por set.
- [x] Sincronizar progreso del assignment considerando logs lifting.

Checkpoint Fase 3.2 API escritura:

- Agregado `POST /api/v1/app/training-assignments/{assignment}/lifting-sets`.
- Payload esperado: `lifting_row_id`, `set_number`, `status`; opcionales `actual_reps`, `failure_reason`, `notes`.
- `status` acepta `completed`, `failed`, `skipped`.
- El endpoint valida que la row pertenezca al entrenamiento de la asignacion del atleta autenticado.
- `completed` sin `actual_reps` rellena reps prescritas de la row.
- El guardado es idempotente por `training_assignment_id`, `lifting_row_id`, `set_number`.
- `TrainingAssignmentProgressService` ahora cuenta secciones lifting como completadas cuando todos sus sets tienen estado.
- Una asignacion `completed` todavia puede corregir sets; se bloquean solo asignaciones `cancelled` o `skipped`.

Checkpoint Fase 3.1 API lectura:

- `GET /api/app/training-assignments/{assignment}` devuelve `sections[].lifting_blocks`.
- En asignaciones, cada row devuelve `set_statuses` expandido desde `1` hasta `sets`, mezclando logs existentes de `training_lifting_set_logs` cuando existan.
- `GET /api/app/training-sessions/{session}` devuelve el mismo esquema para entrenamientos libres, con `set_statuses` sin estado porque aun no existe assignment hasta iniciar el entrenamiento.
- Validado `php -l` en `TrainingAssignmentsController.php` y `TrainingSessionsController.php`.

### Fase 4: App Ionic

- [ ] Actualizar DTOs de entrenamiento.
- [ ] Renderizar bloques lifting en detalle.
- [ ] Convertir rows en checklist por serie.
- [ ] Seguir la referencia visual mobile recibida en `C:\Users\hecto\Downloads\nueva.png`.
- [ ] Permitir marcar row completo.
- [ ] Permitir marcar fallo por serie con reps reales y motivo.
- [ ] Guardar avances y refrescar progreso.

### Fase 5: Reportes

- [ ] Resumen por entrenamiento y atleta.
- [ ] Cumplimiento por ejercicio.
- [ ] Cumplimiento por porcentaje.
- [ ] Fallos por motivo.
- [ ] Base para prediccion futura de resultados.

## QA Esperada

- [ ] Crear entrenamiento normal y confirmar que no aparece builder lifting.
- [ ] Crear entrenamiento lifting con un ejercicio y varias rows.
- [ ] Editar entrenamiento lifting sin perder rows.
- [ ] El atleta ve rows como series marcables.
- [ ] Marcar todas las series como completadas.
- [ ] Marcar una serie fallada con reps reales.
- [ ] Confirmar que el coach puede consultar el resultado.
- [ ] Confirmar que progreso no rompe entrenamientos existentes.

## Estado

- Estado: `Fase 3.1 API lectura ejecutada`.
- Rama Laravel: `feature/lifting-set-execution`.
- Rama Ionic: `feature/lifting-set-execution`.
- Ultima actualizacion: `2026-07-28`.
- Proxima accion recomendada: crear endpoint para que la app guarde `completed`/`failed` por set y despues conectar UI Ionic.

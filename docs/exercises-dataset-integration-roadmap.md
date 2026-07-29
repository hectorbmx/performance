# Roadmap: Integracion De Exercises Dataset

## Objetivo

Integrar el repositorio `hasaneyldrm/exercises-dataset` como catalogo base de ejercicios para que los coaches puedan buscar, filtrar y reutilizar ejercicios estructurados dentro de sus entrenamientos.

El dataset externo aporta:

- 1,324 ejercicios.
- Metadata por ejercicio: nombre, parte del cuerpo, equipo, musculo objetivo, grupo muscular y musculos secundarios.
- Instrucciones en varios idiomas, incluyendo espanol.
- Imagen thumbnail y GIF animado por ejercicio.
- Archivo JSON principal: `data/exercises.json`.
- Schema JSON: `data/exercises.schema.json`.

La integracion debe complementar la biblioteca actual de videos de YouTube, no reemplazarla.

## Resultado Esperado

Al terminar la integracion, el coach podra:

- Abrir un catalogo global de ejercicios desde el panel.
- Buscar ejercicios por nombre.
- Filtrar por parte del cuerpo, equipo y musculo objetivo.
- Ver imagen/GIF, instrucciones y metadata del ejercicio.
- Agregar uno o varios ejercicios a una seccion de entrenamiento.
- Personalizar notas, series, repeticiones, duracion o descanso por ejercicio asignado.

El atleta podra:

- Ver los ejercicios dentro del detalle del entrenamiento en la app movil.
- Consultar instrucciones en espanol.
- Ver imagen o GIF de referencia cuando este disponible.
- Leer las notas especificas del coach para ese ejercicio.

## Estado Actual Del Proyecto

Backend Laravel:

- Existe `TrainingSession` como entidad principal de entrenamiento.
- Existe `TrainingSection` para dividir entrenamientos en secciones.
- `training_sections` ya soporta `video_url` y `video_path`.
- Existe `LibraryVideo` para biblioteca de videos por coach o global.
- Existe pivote `training_section_library_videos` para adjuntar videos de biblioteca a secciones.
- La API movil ya expone entrenamientos/secciones al atleta.

Panel coach Blade:

- Existe vista de biblioteca en `resources/views/coach/library/index.blade.php`.
- El coach puede agregar videos de YouTube.
- El editor de entrenamientos ya maneja secciones.

App Ionic:

- Ya consume entrenamientos y detalles.
- Hay servicios dedicados para entrenamientos y biblioteca.

## Decision De Arquitectura

No conviene cargar el dataset directamente desde GitHub en la app movil ni en el navegador del coach.

La opcion recomendada es:

- Importar el dataset al backend Laravel.
- Guardar metadata en tablas propias.
- Servir busquedas y detalles mediante endpoints internos.
- Guardar assets localmente o en storage compatible con produccion.
- Exponer a la app movil solo el contrato necesario.

Motivos:

- Evita dependencia runtime de GitHub.
- Permite controlar permisos, filtros y performance.
- Permite desactivar ejercicios problematicos.
- Permite extender el catalogo con datos propios.
- Permite versionar actualizaciones del dataset.

## Alcance Funcional

### Incluido

- Tabla de catalogo global de ejercicios.
- Importador desde JSON.
- Registro de version/fuente del dataset.
- Buscador y filtros en panel coach.
- Asociacion de ejercicios a secciones de entrenamiento.
- Campos de programacion por ejercicio dentro de una seccion.
- API para que la app movil reciba ejercicios asociados.
- Checklist legal/licencia antes de habilitar assets en produccion.

### No Incluido En MVP

- Recomendaciones automaticas por IA.
- Generador completo de rutinas.
- Tracking avanzado por ejercicio independiente.
- Analitica de popularidad.
- Edicion global del catalogo por cada coach.
- Traducciones generadas por la app.
- Transcodificacion o procesamiento de GIFs.

## Modelo De Datos Propuesto

### `exercise_catalogs`

Catalogo global importado desde el dataset.

Campos sugeridos:

- `id`
- `external_source` string, default `hasaneyldrm/exercises-dataset`
- `external_id` string, unico por fuente
- `name` string
- `body_part` string nullable
- `category` string nullable
- `equipment` string nullable
- `target` string nullable
- `muscle_group` string nullable
- `secondary_muscles` json nullable
- `instructions` json nullable
- `instruction_steps` json nullable
- `instructions_es` text nullable
- `instruction_steps_es` json nullable
- `image_path` string nullable
- `gif_path` string nullable
- `media_id` string nullable
- `attribution` string nullable
- `source_created_at` timestamp nullable
- `is_active` boolean default true
- `metadata` json nullable
- `created_at`
- `updated_at`

Indices:

- unique `external_source`, `external_id`
- index `name`
- index `body_part`
- index `equipment`
- index `target`
- index `is_active`

### `training_section_exercises`

Ejercicios seleccionados para una seccion especifica.

Campos sugeridos:

- `id`
- `training_section_id`
- `exercise_catalog_id`
- `order` unsigned integer default 1
- `notes` string nullable
- `sets` unsigned integer nullable
- `reps` string nullable
- `duration_seconds` unsigned integer nullable
- `rest_seconds` unsigned integer nullable
- `load_prescription` string nullable
- `tempo` string nullable
- `is_optional` boolean default false
- `created_at`
- `updated_at`

Indices:

- unique `training_section_id`, `exercise_catalog_id`
- index `training_section_id`, `order`
- foreign keys con cascade delete desde `training_sections`

### `exercise_dataset_imports`

Bitacora de importaciones.

Campos sugeridos:

- `id`
- `source`
- `source_url`
- `source_commit` nullable
- `dataset_version` nullable
- `records_seen`
- `records_created`
- `records_updated`
- `records_deactivated`
- `assets_copied`
- `status`
- `error_message` nullable
- `started_at`
- `finished_at`
- `created_at`
- `updated_at`

## Modelos Laravel

### `ExerciseCatalog`

Responsabilidades:

- Representar un ejercicio global.
- Exponer scopes de busqueda/filtro.
- Resolver URLs publicas de imagen/GIF.
- Mantener casts JSON.

Relaciones:

- `sections()` belongsToMany `TrainingSection` mediante `training_section_exercises`.

Scopes sugeridos:

- `active()`
- `search($term)`
- `bodyPart($value)`
- `equipment($value)`
- `target($value)`

### `TrainingSectionExercise`

Responsabilidades:

- Representar la configuracion del ejercicio dentro de la seccion.
- Guardar notas y prescripcion del coach.

Relaciones:

- `section()` belongsTo `TrainingSection`
- `exercise()` belongsTo `ExerciseCatalog`

### Ajuste A `TrainingSection`

Agregar relacion:

- `exercises()` belongsToMany `ExerciseCatalog` con pivote:
  - `order`
  - `notes`
  - `sets`
  - `reps`
  - `duration_seconds`
  - `rest_seconds`
  - `load_prescription`
  - `tempo`
  - `is_optional`

## Importador

Crear comando:

```bash
php artisan exercises:import {path?} {--copy-assets} {--deactivate-missing} {--source-commit=}
```

Uso recomendado en local:

```bash
php artisan exercises:import storage/app/imports/exercises-dataset/data/exercises.json --copy-assets
```

Responsabilidades:

- Leer `data/exercises.json`.
- Validar que sea un array.
- Validar campos minimos:
  - `id`
  - `name`
  - `instructions`
  - `image`
  - `gif_url`
- Mapear instrucciones completas y pasos.
- Priorizar `instructions.es` e `instruction_steps.es`.
- Guardar metadata cruda en `metadata` cuando sea util.
- Hacer `updateOrCreate` por fuente + `external_id`.
- Copiar assets si se usa `--copy-assets`.
- Registrar resumen en `exercise_dataset_imports`.

Rutas sugeridas para assets:

- `storage/app/public/exercises/images/{external_id}-{media_id}.jpg`
- `storage/app/public/exercises/videos/{external_id}-{media_id}.gif`

Contrato para no duplicar assets:

- Si el archivo existe y coincide la ruta esperada, no copiar de nuevo.
- Si falta el asset, importar metadata y dejar `image_path` o `gif_path` en null.
- Registrar faltantes en la salida del comando.

## Licencia Y Riesgo Legal

Antes de usar los assets visuales en produccion:

- Revisar `LICENSE`.
- Revisar `NOTICE.md`.
- Confirmar si thumbnails/GIFs pueden usarse en un SaaS comercial.
- Mantener la atribucion del dataset y de Gym visual donde aplique.
- Si no hay claridad comercial, activar solo metadata e instrucciones en MVP y dejar assets deshabilitados.

Decision pendiente:

- `PENDIENTE`: confirmar permiso de uso comercial de imagenes/GIFs.

## Backend Web Coach

### Rutas Propuestas

```php
Route::get('/coach/exercises', [ExerciseCatalogController::class, 'index'])->name('coach.exercises.index');
Route::get('/coach/exercises/{exercise}', [ExerciseCatalogController::class, 'show'])->name('coach.exercises.show');
Route::get('/coach/exercises-search', [ExerciseCatalogController::class, 'search'])->name('coach.exercises.search');
```

Para secciones de entrenamiento:

```php
Route::post('/coach/training-sections/{section}/exercises', [TrainingSectionExerciseController::class, 'store'])->name('coach.sections.exercises.store');
Route::put('/coach/training-sections/{section}/exercises/{exercise}', [TrainingSectionExerciseController::class, 'update'])->name('coach.sections.exercises.update');
Route::delete('/coach/training-sections/{section}/exercises/{exercise}', [TrainingSectionExerciseController::class, 'destroy'])->name('coach.sections.exercises.destroy');
```

### Controladores

`ExerciseCatalogController`:

- `index`: pagina catalogo con filtros.
- `show`: detalle del ejercicio.
- `search`: JSON para autocompletar desde el editor de entrenamientos.

`TrainingSectionExerciseController`:

- `store`: adjuntar ejercicio a seccion.
- `update`: editar prescripcion.
- `destroy`: quitar ejercicio de seccion.

Autorizacion:

- Solo coaches autenticados.
- La seccion debe pertenecer a un entrenamiento del coach autenticado.
- El catalogo global puede ser visible para todos los coaches activos.

## UI Coach

### Catalogo De Ejercicios

Ubicacion sugerida:

- Nueva entrada en sidebar coach: `Ejercicios`.
- Alternativa: segunda pestana dentro de `Biblioteca`.

Vista esperada:

- Barra de busqueda.
- Filtros compactos:
  - Parte del cuerpo.
  - Equipo.
  - Musculo objetivo.
- Grid/lista de ejercicios.
- Card por ejercicio:
  - Thumbnail.
  - Nombre.
  - Equipo.
  - Objetivo.
  - Badge de parte del cuerpo.
  - Accion `Ver`.

Detalle:

- GIF si esta permitido por licencia y existe.
- Instrucciones en espanol.
- Lista de pasos.
- Metadata.
- Atribucion visible si aplica.

### Editor De Entrenamientos

En cada seccion:

- Agregar bloque `Ejercicios`.
- Buscador con autocompletar.
- Boton para agregar ejercicio.
- Lista ordenable o con campo `order`.
- Campos por ejercicio:
  - series
  - reps
  - duracion
  - descanso
  - carga/peso sugerido
  - tempo
  - notas
  - opcional

Comportamiento:

- Agregar ejercicio no debe sobrescribir texto ya escrito por el coach sin confirmacion.
- Si la seccion no tiene nombre, se puede sugerir el nombre del primer ejercicio.
- Si la seccion no tiene descripcion, se puede sugerir una descripcion basada en instrucciones.
- Quitar ejercicio no debe borrar la seccion.

## API Movil

Actualizar respuesta de detalle de entrenamiento para incluir ejercicios por seccion.

Contrato sugerido:

```json
{
  "id": 10,
  "name": "Fuerza tren superior",
  "sections": [
    {
      "id": 55,
      "name": "Bloque principal",
      "description": "...",
      "exercises": [
        {
          "id": 123,
          "name": "barbell bench press",
          "body_part": "chest",
          "equipment": "barbell",
          "target": "pectorals",
          "image_url": "https://...",
          "gif_url": "https://...",
          "instructions_es": "...",
          "instruction_steps_es": ["...", "..."],
          "prescription": {
            "order": 1,
            "sets": 4,
            "reps": "8-10",
            "duration_seconds": null,
            "rest_seconds": 90,
            "load_prescription": "RPE 8",
            "tempo": "3-1-1",
            "notes": "Controla la bajada",
            "is_optional": false
          }
        }
      ]
    }
  ]
}
```

Reglas:

- Solo devolver ejercicios de entrenamientos visibles/asignados al atleta.
- No exponer metadata innecesaria.
- Devolver `image_url` y `gif_url` como null si assets no estan habilitados.
- Mantener compatibilidad con `video_url`, `video_path` y `libraryVideos`.

## App Ionic

Cambios esperados:

- Actualizar DTO/modelo de entrenamiento para incluir `sections[].exercises`.
- En detalle de entrenamiento, renderizar ejercicios debajo de cada seccion.
- Mostrar:
  - nombre
  - thumbnail/GIF
  - series/reps/duracion/descanso
  - instrucciones desplegables
  - notas del coach
- Cuidar fallback visual si no hay imagen/GIF.

No bloquear MVP:

- No hace falta editar ejercicios desde la app movil.
- No hace falta cache offline en primera fase.

## Performance

Riesgos:

- 1,324 registros no son muchos para backend, pero thumbnails/GIFs pueden pesar.
- GIFs pueden afectar carga movil si se muestran todos en listas.

Decisiones recomendadas:

- En listados web, usar thumbnail.
- En detalle, cargar GIF bajo demanda.
- Paginar busquedas.
- En API movil, devolver GIF solo en detalle de entrenamiento.
- Si se copian assets a storage, usar `Storage::url` y cache HTTP/CDN en produccion.

## Seguridad

- No confiar en rutas del JSON sin normalizarlas.
- Evitar path traversal al copiar assets.
- Validar extensiones permitidas: jpg, jpeg, png, gif.
- Guardar nombres destino controlados por la app.
- No permitir que coaches editen registros globales directamente.
- Auditar endpoints para evitar que un coach modifique secciones de otro coach.

## Fases De Implementacion

### Fase 0: Preparacion Y Decision Legal

Objetivo:

- Confirmar alcance exacto y condiciones de uso.

Tareas:

- [ ] Revisar `LICENSE` del dataset.
- [ ] Revisar `NOTICE.md`.
- [ ] Decidir si se importan assets visuales en MVP.
- [ ] Decidir si el catalogo sera global solamente o si luego habra ejercicios propios por coach.
- [ ] Definir si el menu sera `Ejercicios` o una pestana dentro de `Biblioteca`.

Criterios de aceptacion:

- [ ] Queda documentada la decision legal.
- [ ] Queda documentado si `--copy-assets` se usara en produccion.

Checkpoint:

- Estado: `pendiente`.
- Notas:
  - Si hay duda legal, avanzar MVP sin assets visuales comerciales.

### Fase 1: Persistencia Backend

Objetivo:

- Crear estructura de datos base para ejercicios.

Tareas:

- [ ] Crear migracion `exercise_catalogs`.
- [ ] Crear migracion `training_section_exercises`.
- [ ] Crear migracion `exercise_dataset_imports`.
- [ ] Crear modelo `ExerciseCatalog`.
- [ ] Crear modelo `TrainingSectionExercise`.
- [ ] Agregar relacion `TrainingSection::exercises()`.
- [ ] Agregar casts JSON.
- [ ] Agregar factories o fixtures minimos si aplica.

Criterios de aceptacion:

- [ ] `php artisan migrate` ejecuta correctamente.
- [ ] Las relaciones cargan ejercicios por seccion.
- [ ] Las tablas pueden revertirse con rollback.

Checkpoint:

- Estado: `pendiente`.
- Archivos esperados:
  - `database/migrations/*create_exercise_catalogs_table.php`
  - `database/migrations/*create_training_section_exercises_table.php`
  - `database/migrations/*create_exercise_dataset_imports_table.php`
  - `app/Models/ExerciseCatalog.php`
  - `app/Models/TrainingSectionExercise.php`

### Fase 2: Importador

Objetivo:

- Importar `data/exercises.json` de forma repetible.

Tareas:

- [ ] Crear comando `ExercisesImportCommand`.
- [ ] Leer archivo local recibido por argumento.
- [ ] Validar estructura minima.
- [ ] Mapear metadata e instrucciones.
- [ ] Implementar `updateOrCreate`.
- [ ] Registrar bitacora en `exercise_dataset_imports`.
- [ ] Implementar opcion `--copy-assets`.
- [ ] Implementar opcion `--deactivate-missing`.
- [ ] Mostrar resumen final en consola.

Criterios de aceptacion:

- [ ] Primera importacion crea registros.
- [ ] Segunda importacion no duplica registros.
- [ ] Importacion con assets faltantes no rompe si el JSON es valido.
- [ ] Se registran conteos creados/actualizados.

Checkpoint:

- Estado: `pendiente`.
- Comando esperado:
  - `php artisan exercises:import storage/app/imports/exercises-dataset/data/exercises.json`

### Fase 3: Catalogo Web Coach

Objetivo:

- Permitir al coach explorar el catalogo global.

Tareas:

- [ ] Crear `ExerciseCatalogController`.
- [ ] Agregar rutas web protegidas por auth/coach.
- [ ] Crear vista index.
- [ ] Crear vista show o modal de detalle.
- [ ] Agregar busqueda por nombre.
- [ ] Agregar filtros por `body_part`, `equipment`, `target`.
- [ ] Mostrar estado vacio.
- [ ] Mostrar atribucion si se usan assets.
- [ ] Agregar entrada en sidebar o pestana de biblioteca.

Criterios de aceptacion:

- [ ] Un coach autenticado puede abrir el catalogo.
- [ ] Busqueda y filtros devuelven resultados correctos.
- [ ] La vista no carga GIFs masivamente en el listado.
- [ ] Un coach no necesita tener ejercicios propios para ver el catalogo global.

Checkpoint:

- Estado: `pendiente`.
- Ruta sugerida:
  - `/coach/exercises`

### Fase 4: Integracion Con Editor De Entrenamientos

Objetivo:

- Adjuntar ejercicios del catalogo a secciones de entrenamiento.

Tareas:

- [ ] Crear `TrainingSectionExerciseController`.
- [ ] Agregar endpoint de busqueda JSON para autocompletar.
- [ ] Actualizar create/edit de entrenamientos para mostrar bloque de ejercicios por seccion.
- [ ] Guardar ejercicios asociados al crear entrenamiento.
- [ ] Guardar ejercicios asociados al editar entrenamiento.
- [ ] Permitir actualizar prescripcion por ejercicio.
- [ ] Permitir quitar ejercicio sin borrar seccion.
- [ ] Mantener soporte actual de videos de biblioteca.

Criterios de aceptacion:

- [ ] Crear entrenamiento con seccion y ejercicios asociados.
- [ ] Editar entrenamiento conserva ejercicios existentes.
- [ ] Quitar un ejercicio solo afecta la relacion pivote.
- [ ] Las secciones siguen soportando `video_url`, `video_path` y `libraryVideos`.
- [ ] Validacion impide modificar secciones de otro coach.

Checkpoint:

- Estado: `pendiente`.
- Archivos probables:
  - `app/Http/Controllers/Coach/TrainingSessionController.php`
  - `resources/views/coach/trainings/create.blade.php`
  - `resources/views/coach/trainings/edit.blade.php`

### Fase 5: API Para App Movil

Objetivo:

- Exponer ejercicios asociados al atleta en el detalle del entrenamiento.

Tareas:

- [ ] Revisar controlador actual de detalle de entrenamientos del atleta.
- [ ] Cargar `sections.exercises` con pivote.
- [ ] Resolver URLs publicas de assets.
- [ ] Devolver instrucciones en espanol.
- [ ] Mantener respuesta compatible con clientes antiguos.
- [ ] Agregar prueba o fixture de API si el proyecto lo permite.

Criterios de aceptacion:

- [ ] El atleta ve ejercicios solo de entrenamientos permitidos.
- [ ] La API devuelve prescripcion del coach.
- [ ] Si no hay assets habilitados, `image_url` y `gif_url` salen null.
- [ ] La respuesta existente no rompe la app actual.

Checkpoint:

- Estado: `pendiente`.
- Archivos probables:
  - `app/Http/Controllers/Api/V1/App/Client/TrainingsController.php`
  - `app/Http/Controllers/Api/V1/App/Client/TrainingSessionsController.php`

### Fase 6: App Ionic

Objetivo:

- Renderizar ejercicios dentro del detalle de entrenamiento.

Tareas:

- [ ] Actualizar DTOs/modelos TypeScript.
- [ ] Actualizar servicio que consume entrenamientos.
- [ ] Mostrar ejercicios agrupados por seccion.
- [ ] Mostrar prescripcion del coach.
- [ ] Mostrar pasos/instrucciones en acordeon o bloque desplegable.
- [ ] Agregar fallback sin imagen/GIF.
- [ ] Probar en viewport movil.

Criterios de aceptacion:

- [ ] El detalle de entrenamiento muestra ejercicios.
- [ ] La UI no se rompe cuando una seccion no tiene ejercicios.
- [ ] La UI no se rompe cuando el ejercicio no tiene asset.
- [ ] Las instrucciones largas no se enciman con otros elementos.

Checkpoint:

- Estado: `pendiente`.
- Archivos probables:
  - `app/src/app/pages/training-details/training-details.page.ts`
  - `app/src/app/pages/training-details/training-details.page.html`
  - `app/src/app/pages/training-details/training-details.page.scss`
  - `app/src/app/services/training-api.service.ts`

### Fase 7: QA Integral

Objetivo:

- Verificar flujo completo coach -> entrenamiento -> atleta.

Casos de prueba:

- [ ] Importar dataset desde JSON.
- [ ] Confirmar conteo de ejercicios importados.
- [ ] Abrir catalogo web como coach.
- [ ] Buscar por nombre.
- [ ] Filtrar por equipo.
- [ ] Filtrar por musculo objetivo.
- [ ] Crear entrenamiento con una seccion y un ejercicio.
- [ ] Crear entrenamiento con varias secciones y varios ejercicios.
- [ ] Editar prescripcion de ejercicio.
- [ ] Quitar ejercicio.
- [ ] Ver detalle desde app movil.
- [ ] Confirmar que atleta sin acceso no puede ver entrenamiento.
- [ ] Confirmar compatibilidad con secciones que solo tienen video.
- [ ] Confirmar que listados no cargan GIFs de forma masiva.

Checkpoint:

- Estado: `pendiente`.
- Resultado esperado:
  - Flujo validado de punta a punta.

## Checklist De Produccion

- [ ] Licencia revisada y documentada.
- [ ] Decision de assets aprobada.
- [ ] Storage publico configurado si se usan imagenes/GIFs.
- [ ] `php artisan storage:link` ejecutado si aplica.
- [ ] Migraciones aplicadas.
- [ ] Importacion ejecutada.
- [ ] Conteo de registros validado.
- [ ] Cache/config limpia si aplica.
- [ ] API movil validada con usuario atleta real.
- [ ] Fallback sin assets probado.

## Riesgos Y Mitigaciones

### Riesgo: uso comercial de assets no permitido

Mitigacion:

- Separar metadata de assets.
- Permitir MVP sin mostrar thumbnails/GIFs.
- Mantener campo de atribucion.

### Riesgo: UI lenta por GIFs

Mitigacion:

- Usar thumbnails en listados.
- Cargar GIF solo en detalle.
- Paginar busquedas.

### Riesgo: duplicar ejercicios al actualizar dataset

Mitigacion:

- `external_source + external_id` como llave unica.
- Importador idempotente.

### Riesgo: romper entrenamientos existentes

Mitigacion:

- Agregar tablas nuevas.
- No cambiar contrato existente de videos.
- Mantener campos actuales de seccion.

### Riesgo: permisos incorrectos entre coaches

Mitigacion:

- Validar ownership de `TrainingSection`.
- El catalogo es global, pero la relacion se crea solo en secciones del coach autenticado.

## Orden Recomendado De Ejecucion

1. Fase 0: licencia y decision de assets.
2. Fase 1: persistencia.
3. Fase 2: importador.
4. Fase 3: catalogo web.
5. Fase 4: editor de entrenamientos.
6. Fase 5: API movil.
7. Fase 6: app Ionic.
8. Fase 7: QA integral.

## Estado General

- Estado: `roadmap creado`.
- Ultima actualizacion: `2026-07-28`.
- Proxima accion recomendada:
  - Ejecutar Fase 0 y luego iniciar Fase 1 si se aprueba el alcance.


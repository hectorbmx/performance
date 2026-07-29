# Roadmap Ionic: Registro De Series Para Lifting

## Objetivo

Agregar en la app del atleta una experiencia simple para ejecutar entrenamientos tipo `Lifting`, usando los bloques y rows capturados por el coach en Laravel.

La app debe mostrar los ejercicios como checklist por serie, no como tabla tipo Excel.

## Contrato Esperado Desde Backend

Cada seccion puede traer `lifting_blocks`.

Cada bloque tiene:

- `id`
- `exercise_catalog_id` nullable
- `exercise_name`
- `notes`
- `rows`

Cada row tiene:

- `id`
- `percentage`
- `reps`
- `sets`
- `rest_seconds`
- `notes`
- `set_statuses`

Cada estado tiene:

- `set_number`
- `status`: `completed`, `failed`, `skipped`
- `actual_reps`
- `failure_reason`
- `notes`

## Experiencia Atleta

Para cada ejercicio:

```text
Front squat
Mantener torso vertical.

70% · 3 series x 3 reps · descanso 2:00
[Serie 1] [Serie 2] [Serie 3]

80% · 3 series x 3 reps · descanso 2:30
[Serie 1] [Serie 2] [Serie 3]
```

Acciones:

- Marcar serie completada.
- Marcar serie fallada.
- Marcar row completo.
- Editar fallo para registrar reps logradas.
- Agregar motivo y nota opcional.

## Referencia Visual Mobile

Referencia recibida: `C:\Users\hecto\Downloads\nueva.png`.

La pantalla objetivo para lifting debe sentirse como una vista de ejecucion por ejercicio:

- Header oscuro con boton volver, titulo del ejercicio y menu de acciones.
- Hero/imagen superior del ejercicio cuando exista asset o imagen de biblioteca.
- Badge de intensidad, por ejemplo `INTENSIDAD 60%`.
- Resumen visible del row actual, por ejemplo `3 Series x 3 Reps`.
- Tarjeta principal del ejercicio con nombre, nota corta y un icono relacionado a lifting.
- Lista de series en bloques compactos:
  - Label `SET 1`, `SET 2`, `SET 3`.
  - Texto principal calculado, por ejemplo `100kg x 3`, solo si backend/app puede calcular peso desde RM y porcentaje.
  - Boton circular de completado.
  - Boton circular de fallo.
- Panel de notas del movimiento:
  - Chips de motivo rapido: `Fatiga`, `Tecnica`, `Dolor`, `Peso`.
  - Textarea de comentario.
- CTA inferior grande: `COMPLETAR TODO`.

Notas de producto:

- Aunque el coach no captura peso en la tabla, la app puede mostrar peso calculado si existe RM/base de carga para el atleta.
- Si no hay RM o no puede calcularse el peso, mostrar `% x reps` en vez de `kg x reps`.
- El diseno debe priorizar contraste, targets grandes y accion rapida con una mano.

## Reglas UI

- El atleta debe poder avanzar rapido con una mano.
- La tabla del coach no se replica visualmente en app.
- Usar chips/botones compactos por serie.
- Mostrar `%`, `series x reps`, descanso y notas.
- Usar una vista por ejercicio/row inspirada en la referencia visual recibida.
- Si no hay logs, todo inicia pendiente.
- Si un row tiene todas las series completas, mostrar estado completo.
- Si hay una serie fallada, mostrar estado parcial/fallado sin ocultar las series completadas.

## Fases Ionic

### Fase 1: DTO Y Servicio

- [x] Agregar interfaces `TrainingLiftingBlockDTO`, `TrainingLiftingRowDTO`, `TrainingLiftingSetStatusDTO`.
- [x] Mapear `sections[].lifting_blocks` en el servicio de entrenamientos.
- [x] Mantener compatibilidad cuando no exista `lifting_blocks`.

### Fase 2: Render En Detalle

- [x] Renderizar bloques debajo de la seccion correspondiente.
- [x] Mostrar ejercicio, notas y rows.
- [x] Renderizar series como controles marcables.
- [x] Crear vista/card mobile base inspirada en `nueva.png`.
- [ ] Mostrar hero/imagen cuando exista asset del ejercicio.
- [ ] Mostrar fallback visual cuando no haya imagen.
- [ ] Mostrar peso calculado solo si existe RM/base de carga; si no, mostrar porcentaje.
- [ ] Cuidar estados vacios y responsive movil.

### Fase 3: Captura De Logs

- [x] Crear metodo de servicio para guardar logs.
- [x] Marcar serie completada.
- [x] Marcar serie fallada con motivo tecnico automatico inicial.
- [ ] Marcar serie fallada con formulario/modal compacto.
- [ ] Marcar row completo.
- [ ] Refrescar progreso despues de guardar.

### Fase 4: QA

- [ ] Entrenamiento sin lifting sigue igual.
- [ ] Entrenamiento lifting muestra ejercicios.
- [ ] Row de 4 series genera 4 controles.
- [ ] CTA `COMPLETAR TODO` marca todas las series del row/bloque esperado.
- [ ] Fallo guarda reps reales y motivo.
- [ ] Estado persiste al volver a abrir el detalle.
- [ ] UI no se encima en telefono.

## Estado

- Estado: `UI lifting inicial implementada en detalle de entrenamiento`.
- Rama Ionic: `feature/lifting-set-execution`.
- Backend esperado: Laravel rama `feature/lifting-set-execution`.
- Ultima actualizacion: `2026-07-28`.

## Checkpoint Fase 1-3 Inicial

- `TrainingApiService` mapea `lifting_blocks` para asignaciones y entrenamientos libres.
- Agregado `saveLiftingSet()` hacia `POST /api/v1/app/training-assignments/{assignment}/lifting-sets`.
- `TrainingDetailsPage` muestra bloques lifting dentro de cada seccion.
- Cada row se transforma en sets marcables con botones de completado y fallo.
- Al guardar un set se recarga el detalle para refrescar estados y progreso.
- `ng build` no muestra errores TypeScript/Angular por lifting; la build queda bloqueada por presupuesto preexistente de `src/app/tab1/tab1.page.scss` excedido por 687 bytes.

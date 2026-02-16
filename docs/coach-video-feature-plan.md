# Feature plan: videos por sección para coach

## Estado actual del sistema

Hoy ya existe soporte **básico** para videos por sección mediante un campo `video_url`:

- Base de datos: columna nullable `video_url` en `training_sections`.
- Backoffice coach: formularios de crear/editar entrenamiento aceptan `sections[*][video_url]`.
- Backend coach: validación para `sections.*.video_url` como URL.
- API app cliente: se expone `video_url` de cada sección.

Esto cubre pegar links (YouTube/Vimeo/CDN), pero **no cubre upload de archivos de video** por parte del coach.

---

## Qué necesitamos para soportar “coach sube videos al sistema”

## 1) Definir el alcance funcional

Decidir explícitamente:

- Si el coach podrá:
  - solo pegar URL externas,
  - solo subir archivo,
  - o ambas opciones.
- Límites por video (ej. 200 MB, duración máxima).
- Formatos permitidos (ej. `mp4`, `mov`, `webm`).
- Reglas de acceso: videos visibles solo a clientes asignados al entrenamiento.

> Recomendación: soportar **URL externa + archivo** para máxima flexibilidad.

## 2) Modelo de datos

Si se permitirá upload de archivo, separar URL externa de archivo interno:

- Mantener `video_url` para enlaces externos.
- Agregar `video_path` (nullable) para ruta en storage.
- (Opcional) agregar metadatos:
  - `video_provider` (`external`, `upload`),
  - `video_duration_seconds`,
  - `video_size_bytes`,
  - `video_mime_type`.

## 3) Backend (coach)

Actualizar `TrainingSessionController@store` y `@update` para:

- Validar `sections.*.video_file` como archivo de video.
- Subir a `storage` (o S3) en una ruta por coach/entrenamiento/sección.
- Guardar la ruta en `video_path`.
- Mantener compatibilidad con `video_url` (link externo).
- Si se reemplaza un video, eliminar archivo previo para evitar basura.

## 4) API para app cliente

En la respuesta de secciones, exponer un campo resuelto para consumo directo:

- `video_playback_url` (si hay `video_path`, convertir con `Storage::url`; si no, usar `video_url`).
- `video_source` (`upload` o `external`).

Así la app no necesita conocer internamente cómo se guardó.

## 5) UI Coach

En crear/editar secciones:

- Agregar input de archivo `video_file` por sección.
- Mantener campo de URL externa.
- Permitir elegir una opción y limpiar la otra para evitar conflictos.
- Mostrar preview/estado (archivo seleccionado o URL pegada).
- Mostrar errores de validación por sección.

## 6) Infraestructura y performance

Para videos grandes, priorizar object storage (S3 compatible) + CDN:

- Configurar disco de archivos por ambiente (`local` en dev, `s3` en prod).
- URLs firmadas o públicas según política de acceso.
- Definir expiración/caché.
- (Opcional recomendado) procesamiento async para thumbnails/transcodificación HLS.

## 7) Seguridad

- Validar MIME real y extensión.
- Límite de tamaño en backend y reverse proxy.
- Sanitizar nombres de archivo (o usar nombres hash).
- Controlar autorización: solo coach dueño puede gestionar sus videos.

## 8) QA / pruebas mínimas

- Crear entrenamiento con URL externa por sección.
- Crear entrenamiento subiendo archivo por sección.
- Editar y reemplazar video.
- Eliminar video y confirmar limpieza en storage.
- Verificar que la app cliente reciba `video_playback_url` válido.

---

## Roadmap sugerido (iterativo)

1. **MVP**: soportar upload (sin transcodificación), `video_path` + `video_playback_url`.
2. **V2**: thumbnails y validaciones mejoradas.
3. **V3**: transcodificación adaptive streaming (HLS) + analítica de reproducción.

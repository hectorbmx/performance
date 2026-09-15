# Plan de implementación: módulo Tips y aprobación de contenido

Creado: 2026-09-10. Revisión: 2026-09-11.
Estado: primera fase CP1 a CP6 completada en Laravel; migración aplicada en MySQL local `coach`. Siguiente bloque: integración Ionic con la API CP5, planificada en ION1 a ION6.

Este documento sustituye el plan inicial limitado a Tips del coach. Es la referencia única para el alcance, contratos y checkpoints. Actualizarlo al cerrar cada checkpoint; no confundir propuestas con funcionalidad implementada.

## 1. Meta de la primera fase

Construir en Laravel un módulo independiente de Tips, integrado con los paneles existentes, para guardar contenido del coach y del administrador, enviarlo a revisión, aprobarlo o rechazarlo, publicarlo y archivarlo. Dejar API autenticada de lectura lista para Ionic, sin implementar pantallas móviles.

Resultado esperado de extremo a extremo:

1. El coach guarda un borrador con título, categoría, texto e imagen opcional.
2. Envía el contenido a revisión y ve su estado en el panel.
3. El administrador revisa la publicación exacta y aprueba o rechaza con un motivo.
4. Solo las publicaciones aprobadas aparecen a los atletas elegibles del coach.
5. El administrador también crea borradores propios y los publica para todos los atletas elegibles, sin autoaprobación.
6. Archivar retira el contenido del feed, detalle e imagen protegida.

La primera fase incluye datos, reglas, UI web, moderación, imágenes y API de lectura. No incluye lecturas registradas, FCM, Ionic, fijados, comentarios, métricas, revisiones históricas, programación, videos, múltiples adjuntos, editor HTML ni categorías editables.

## 2. Decisiones de producto y supuestos explícitos

### Propuesta base para planificar

- El rol del dueño es `admin`; “master” es su denominación de producto. No crear otro rol.
- Confirmado por el usuario: todo contenido enviado por cualquier coach requiere aprobación del admin, incluso para sus propios atletas, para prevenir mal uso de la app. Ningún tipo (tip/note/news) evita la revisión.
- El coach publica exclusivamente para sus atletas; no puede solicitar ni modificar alcance global en esta fase.
- El admin crea contenido global y modera contenido de coaches. Moderar no le permite editar silenciosamente el texto ajeno: debe rechazarlo con observaciones.
- Ambos actores pueden guardar borradores. Guardar no significa publicar ni enviar a revisión.
- Confirmado por el usuario: atletas con membresía vencida pueden consultar anuncios globales del administrador, siempre que cuenta y cliente estén activos. El contenido del coach conserva el requisito de acceso por membresía.
- Los WODs y entrenamientos asignados permanecen bloqueados cuando el servicio vigente deniega acceso por membresía. Ver anuncios no habilita entrenamientos ni modifica la política existente de vigencia/gracia.
- Tip, nota y noticia son formatos editoriales, no permisos ni audiencias diferentes.

### Puertas de decisión

| Decisión | Estado | Antes de qué trabajo debe cerrarse |
| --- | --- | --- |
| Aprobar todos los Tips del coach | Confirmado: obligatorio para todos los coaches y tipos | CP2: transiciones y permisos |
| Anuncios globales visibles con membresía vencida | Confirmado: sí; WODs y asignados siguen bloqueados | CP5: acceso de atletas |

Las dos decisiones de producto están cerradas por confirmación expresa. CP0 conserva sus verificaciones técnicas pendientes; esta confirmación no implica que el backend ya implemente las reglas.

## 3. Qué ya existe y qué reutilizar

| Pieza existente | Reutilización concreta |
| --- | --- |
| `User`, Spatie y rol `admin`/`coach` | Autores, revisores y autorización por rol; sin tabla de autores nueva |
| `UserApp`, `Client`, relación con coach | Identidad del lector y resolución de audiencia; nunca inferir por email |
| `auth`, `admin`, `role:coach`, `coach.subscription` | Acceso a los dos paneles; admin no necesita coach ni suscripción de coach |
| Sanctum | Autenticación de consumo móvil |
| `ClientMembershipAccessService` | Cuenta/cliente activo, vigencia y gracia; no repetir cálculos de membresía |
| `x-app-layout`, sidebar, componentes Blade y Alpine | Estructura visual, botones, errores y acciones del panel |
| Grupos | Referencia de listado, filtros, formularios y paginación |
| `Storage`, disco privado `local` | Imágenes bajo carpeta `tips/`; no cambiar el disco global |
| Patrón de enums del proyecto | Estados, categorías, tipos y alcance |
| `AppNotificationService`, `UserDevice`, `PushNotification` | Reservados para fase posterior; no crear `device_tokens` ni otro canal Firebase |

No copiar `LibraryVideo::visibleForCoach`: permite registros globales con propietario nulo y no expresa estas reglas. No extender la tabla de videos para contener Tips.

## 4. Límites del componente

Independiente significa que Tips posee sus modelos, reglas, validaciones, vistas y contrato. Sigue siendo un módulo del mismo Laravel, no un microservicio, paquete externo o segunda autenticación.

Organización propuesta, ajustable a convenciones verificadas en CP0:

- `App/Models/Tip.php`: persistencia, relaciones y scopes sencillos.
- `App/Enums/TipStatus.php`, `TipType.php`, `TipScope.php`, `TipCategory.php`: valores únicos para formulario, validación y API.
- `App/Policies/TipPolicy.php`: rol, propiedad y acciones autorizadas.
- `App/Services/Tips/TipService.php`: guardado y cambios de estado con transacciones.
- `App/Services/Tips/TipVisibilityService.php`: consulta de contenido accesible al atleta reutilizando el resultado del servicio de membresía.
- `App/Services/Tips/TipImageService.php`: escritura, reemplazo, retiro y lectura del archivo privado.
- `App/Http/Requests/Tips/`: validaciones compartidas de contenido, filtros y rechazo.
- Controladores existentes `Admin/TipController` y `Coach/TipController`: delgados, delegan las reglas al mismo servicio.
- Controlador de lectura `Api/V1/App/Client/TipController` y Resources de tarjeta/detalle.
- `resources/views/components/tips/`: formulario, estado vacío, badge y previsualización compartidos cuando sea útil.
- Vistas de `admin/tips` y `coach/tips`: composición y acciones propias de cada panel.

No crear repositorios genéricos, un motor de workflows, un gestor universal de archivos ni nuevos módulos transversales para una sola necesidad. No mover código ajeno al módulo salvo integración necesaria y explícita.

## 5. Persistencia: una tabla en esta fase

Tabla `tips`. Una publicación tiene un texto y una imagen opcional: no necesita cabecera y detalles separados.

| Campo | Regla |
| --- | --- |
| id | Identificador primario |
| title | string(150), obligatorio, trim, 3–150 caracteres |
| body | text, texto plano con párrafos, trim, 1–10000 caracteres |
| type | tip / note / news; default tip |
| category | nutrition / training / recovery / wellbeing / general; obligatoria, default general |
| scope | global / tenant; asignado por servidor |
| author_id | FK a users; autor autenticado, obligatorio e inmutable |
| coach_id | FK nullable a users; obligatorio para tenant, null para global |
| status | draft / pending_approval / published / rejected / archived; default draft |
| reviewed_by | FK nullable a users; último revisor admin |
| reviewed_at | Fecha nullable de última decisión |
| rejection_reason | Texto nullable, obligatorio al rechazar, máximo 2000 caracteres |
| image_disk / image_path | Nullable; datos internos de almacenamiento |
| published_at | Primera publicación, nullable hasta publicar |
| archived_at | Fecha de archivo nullable |
| created_at / updated_at | Timestamps |

Relaciones: `author`, `coach`, `reviewer`. No `author_type` porque ambos autores son User. No `tenant_id` porque el proyecto utiliza coach_id. No añadir `is_active`, `SoftDeletes` ni `pinned_at` en esta fase.

Invariantes:

- Creación desde coach: scope tenant, coach_id = author_id = usuario autenticado.
- Creación desde admin: scope global, coach_id null, author_id = usuario autenticado.
- Nunca aceptar autor, scope, coach, revisión, estado o fechas mediante asignación masiva del formulario.
- Aprobar no cambia autor ni convierte el Tip del coach en global.
- Una publicación global no se identifica únicamente por coach_id null: también debe cumplir scope global.
- Tip = consejo educativo; note = comunicación breve; news = novedad. No cambian los permisos.
- Índices iniciales: `(scope, status, published_at, id)`, `(coach_id, status, published_at, id)`, `(author_id, status)` y `(status, created_at, id)` para la cola. Revisar tamaño y consultas reales antes de añadir más.
- FKs implementadas: autor/coach con restrictOnDelete y revisor con nullOnDelete. La fecha de revisión se conserva si se elimina el revisor, pero no su identidad; no equivale a auditoría histórica. La revisión del código encontró baja administrativa mediante cambio de estado y eliminación física en ProfileController::destroy. Antes de desplegar la migración se debe resolver el mensaje/flujo de eliminación de una cuenta con Tips, porque la FK impedirá eliminar al autor; no modificar ese flujo silenciosamente en CP1.

`tip_reads` se reserva para fase 2: `tip_id`, `user_app_id`, `read_at`, índice UNIQUE `(tip_id, user_app_id)`. No migrarla ni devolver un `is_read` inventado en fase 1.

## 6. Máquina de estados y moderación

| Acción | Actor | Estado origen | Resultado |
| --- | --- | --- | --- |
| Guardar nuevo | Autor coach/admin | Nuevo | draft |
| Editar borrador | Autor | draft | draft |
| Corregir rechazado | Autor coach | rejected | draft |
| Enviar a revisión | Autor coach | draft | pending_approval |
| Retirar de revisión | Autor coach | pending_approval | draft |
| Aprobar | Admin | pending_approval | published |
| Rechazar con motivo | Admin | pending_approval | rejected |
| Publicar propio | Autor admin | draft | published |
| Archivar | Autor o admin | draft / pending_approval / rejected / published | archived |
| Restaurar | Autor | archived | draft |

Reglas de transición:

- Pendientes y publicados no se editan. Para cambiar un pendiente hay que retirarlo primero; publicado exige archivar/restaurar y volver al flujo de revisión.
- `published_at` conserva la primera publicación; republicar no reposiciona el contenido. `updated_at` cambia con las operaciones.
- Aprobar/rechazar asigna reviewer y fecha; aprobar limpia rechazo. Corregir o restaurar limpia la decisión anterior porque comienza una nueva revisión. La UI indica que el motivo deja de conservarse al iniciar la corrección.
- No hay historial de revisiones en fase 1. Si se necesita conservar todas las decisiones, diseñar `tip_reviews` antes de implementar, no presentar estos campos como auditoría completa.
- Archivar conserva contenido e imagen; restaurar limpia archived_at. No DELETE definitivo.
- Aprobar/rechazar/publish son transiciones, no un update libre de status.
- Bloquear la fila y validar nuevamente estado/permisos dentro de la transacción. Dos decisiones concurrentes no pueden sobrescribirse.
- Acción repetida o estado incompatible: 409 para requests JSON, error de sesión comprensible para Blade. No repetir efectos; no asumir que un estado final demuestra que quien repite tenía autorización.
- Envío tras guardado con intención submit/publish debe ser atómico. Si falla validación, conservar el estado anterior y mostrar old input.

## 7. Seguridad y acceso

### Paneles

- Coach: middleware existente de autenticación, rol y suscripción; consultas limitadas a su autoría/propiedad.
- Admin: autenticación y middleware admin; lista global propia y cola de moderación de coaches. Puede consultar contenido de coach para moderación y archivo, no modificar texto ajeno.
- La Policy verifica identidad/acción. El servicio verifica transición/invariantes. No poner lógica equivalente en cada controlador.
- ID de otro coach no accesible: 404. Rol sin acceso al panel: 403. Estado incompatible sobre contenido autorizado: conflicto.
- Acciones mutables con POST/PUT y CSRF. Nada se publica, archiva ni aprueba mediante GET.

### Atletas

- Sanctum y tipo UserApp. IDs y scope se resuelven en servidor.
- Reutilizar `ClientMembershipAccessService::forUserApp` una vez por consulta: inactive_user deniega todo; active/grace permite tenant; expired permite anuncios globales. El caso no_membership no fue confirmado expresamente: mantener denegación por defecto hasta definirlo, sin equipararlo automáticamente a una membresía vencida.
- No poner el feed mixto dentro de `client.membership`: bloquearía los anuncios a atletas con membresía vencida. La adaptación queda acotada a TipVisibilityService; conservar ese middleware y sus reglas en WODs y entrenamientos asignados.
- Listado, detalle e imagen usan la misma consulta de visibilidad: published y global, o published y tenant del coach actual con acceso permitido.
- No revelar títulos/imágenes de borradores, rechazados, pendientes, archivados ni contenido de otro coach. Detalle inaccesible: 404.
- Cambio de coach afecta la siguiente consulta. No almacenar scope/coach confiando en el token enviado por Ionic.

## 8. Imágenes y contenido

- Una imagen opcional JPEG/PNG/WebP, máximo 5 MiB y 4096 x 4096 píxeles; validar contenido y MIME, excluir SVG/GIF.
- Texto plano, escapado por Blade; Ionic deberá renderizar texto, no innerHTML. Sin editor enriquecido ni sanitizador nuevo.
- Disco local privado, carpeta tips, nombres generados. Registrar disco/ruta internamente; no exponer rutas físicas.
- Archivo nuevo reemplaza; remove_image=true retira; omisión conserva. Archivo nuevo junto con remove_image=true es error 422.
- Guardar archivo nuevo antes de actualizar referencia; limpiar nuevo si falla BD; eliminar anterior después del commit. Fallos de limpieza se registran sin perder referencia vigente; definir recuperación manual en entrega, sin scheduler nuevo en esta fase.
- Ruta web de imagen por panel y ruta API protegida, con mismas reglas del recurso. Imagen inaccesible o ausente: 404.
- API retorna URL absoluta del endpoint protegido. Ionic deberá descargar con Bearer y crear URL de objeto; una etiqueta img no incorpora automáticamente el token.
- Cache-Control private,no-store para respuestas sensibles e imagen. Archivar bloquea futuras solicitudes, no retira copias ya descargadas.

## 9. UI esperada

### Coach

- Listado propio con filtros de estado/categoría y búsqueda; paginación y estado vacío real.
- Formulario compartido: título, tipo, categoría, cuerpo e imagen; guardar borrador y enviar a revisión.
- Badge de estado, previsualización y acciones disponibles según Policy.
- Pendiente: aviso de revisión y acción retirar; rechazado: motivo y acción corregir.
- Publicado: previsualizar o archivar; no mostrar edición directa.

### Admin

- Dos secciones: publicaciones globales y revisión de coaches; conservar el nombre Tips en sidebar.
- Globales: mismo formulario con guardar borrador y publicar.
- Cola: pendientes, autor/coach, fecha de envío si se incorpora al contrato antes de migrar (en fase 1 usar updated_at como referencia del último cambio, no etiquetarlo como historial), previsualización protegida, aprobar y rechazar.
- Motivo de rechazo obligatorio; confirmación de aprobación y archivo. No habilitar acciones que el servidor rechaza.
- Reutilizar componentes de formulario/preview/badge; las vistas de panel solo componen acciones y contexto.
- No duplicar alertas si x-app-layout ya las muestra. Mantener navegación y estilo existentes.

## 10. Rutas web

Conservar prefijos y nombres `coach.tips.*` / `admin.tips.*`. Escritura exclusivamente web en esta fase.

| Método | Sufijo bajo ambos paneles | Acción |
| --- | --- | --- |
| GET | /tips | index |
| GET | /tips/create | create |
| POST | /tips | store (borrador, o intención validada submit/publish según rol) |
| GET | /tips/{tip} | show |
| GET | /tips/{tip}/edit | edit |
| PUT | /tips/{tip} | update |
| POST | /tips/{tip}/archive | archive |
| POST | /tips/{tip}/restore | restore |
| GET | /tips/{tip}/image | image |

Específicas coach: POST `/tips/{tip}/submit`, POST `/tips/{tip}/withdraw`.
Específicas admin: GET `/tips/pending`, POST `/tips/{tip}/publish` (propio), POST `/tips/{tip}/approve`, POST `/tips/{tip}/reject`.

Registrar estáticas antes de `{tip}`, restringir IDs numéricos y resolver recursos según actor. No exponer `/api/tips` sin versión ni replicar escrituras API que aún no se necesitan.

## 11. Contrato de lectura Ionic, fase 1

Authorization Bearer y Accept application/json. Endpoints propuestos:

- GET `/api/v1/app/tips/categories`: cinco claves y etiquetas, mismo control de identidad activa.
- GET `/api/v1/app/tips`: contenido visible combinado.
- GET `/api/v1/app/tips/{tip}`: detalle visible.
- GET `/api/v1/app/tips/{tip}/image`: bytes de imagen tras autorización.

Filtros del listado: q (trim, máximo 150; vacío sin búsqueda), category válida, type válido, page entero >=1 default 1, per_page 1–50 default 20. Filtros combinados con AND; búsqueda literal parcial por title/body escapando comodines SQL. No filtro coach_id ni override de scope.

Orden estable `published_at DESC, id DESC`. Inicio podrá usar per_page=3. Cambios concurrentes pueden desplazar páginas: Ionic refrescará desde la primera página.

Envelope propuesto:

```json
{
  "ok": true,
  "data": [{
    "id": 123,
    "title": "Organiza tu semana",
    "type": "tip",
    "scope": "tenant",
    "category": {"key": "wellbeing", "label": "Hábitos y bienestar"},
    "excerpt": "Un consejo para comenzar…",
    "image_url": null,
    "published_at": "2026-09-11T12:00:00Z",
    "updated_at": "2026-09-11T12:00:00Z"
  }],
  "meta": {"current_page": 1, "per_page": 20, "last_page": 1, "total": 1}
}
```

- Detalle: mismo objeto más body y content_format=plain_text, sin meta.
- Excerpt calculado, espacios normalizados, máximo 180 caracteres; no columna.
- Fechas ISO 8601 UTC; image_url siempre string o null.
- Sin resultados: data=[], total=0, last_page=1. Página fuera de rango: 200 con array vacío y metadata real.
- Sin author_id, coach_id, rutas internas, datos de revisión ni motivo de rechazo. Scope sirve para identificar contenido general en Ionic.
- No is_read en fase 1. Se agregará cuando exista registro real de lecturas.

Errores: 401 del framework para falta de autenticación; 403 client_auth_required para tipo incorrecto; 403 con code membership_expired y access_state inactive_user cuando el servicio existente deniega por cuenta/cliente inactivo; 404 genérico para recurso no visible; 422 estándar Laravel con message/errors para validación. Conservar formatos existentes antes que personalizar el Handler global. CP0 debe verificar las respuestas efectivas y CP5 documentar ejemplos reales.

## 12. Checkpoints de primera fase

### CP0 — Cerrar contrato y preparar validación

- [x] Cascarón en menús admin/coach con componentes compartidos.
- [x] Revisión previa de roles, modelos, rutas y servicio de membresía.
- [x] Confirmar aprobación obligatoria de todos los coaches y anuncios globales accesibles con membresía vencida, sin desbloquear entrenamientos.
- [ ] Verificar rama, cambios locales e instrucciones vigentes, preservando trabajo ajeno.
- [ ] Confirmar versión/runtime, flujo de baja de User y restricciones FK; compatibilidad de enums e índices.
- [ ] Verificar respuesta real de autenticación/errores y conexión efectiva de test.
- [ ] Confirmar almacenamiento privado sin cambiar configuración global.

Salida: decisiones registradas, matriz de permisos sin ambigüedades y entorno seguro de pruebas. No ejecutar migraciones destructivas.

### CP1 — Modelo y persistencia

- [x] Migración aditiva tips, enums, relaciones y casts.
- [x] Validación compartida de contenido e invariantes autor/scope/coach.
- [x] Datos de prueba mediante factories/helpers aislados; no modificar seeders generales.
- [x] Probar restricciones y rollback de la migración únicamente en test aislado (SQLite en memoria).

Implementación CP1:

- `database/migrations/2026_09_11_000001_create_tips_table.php`.
- `app/Models/Tip.php`: relaciones author/coach/reviewer, casts de enums/fechas, campos editoriales fillable y defaults de borrador.
- `app/Enums/TipStatus.php`, `TipType.php`, `TipCategory.php`, `TipScope.php`: mismos métodos labels/values que el patrón existente.
- `app/Support/Tips/TipContentRules.php`: reglas de texto/tipo/categoría reutilizables por los Form Requests de CP2.
- `tests/Feature/TipPersistenceTest.php`: helpers usando User::factory y conexión exclusiva tips_test_memory.

Límites: el modelo valida contenido e invariantes al guardar mediante Eloquent y hace inmutables autor/coach/scope. No autoriza roles ni aplica transiciones todavía; escrituras directas mediante Query Builder omiten eventos del modelo y no deben usarse para operaciones del módulo. Policy y servicio son el siguiente checkpoint. No hay nuevas rutas de escritura.

Pruebas: `php artisan test --filter=TipPersistenceTest --do-not-cache-result`: 8 pruebas, 50 aserciones correctas. Cada test verifica driver sqlite, database :memory: y FKs habilitadas antes de crear tablas. Solo aplica la migración real de users y tips en esa conexión; sin RefreshDatabase, sin conexión MySQL ni cambios en seeders. Lint PHP de los 8 archivos nuevos correcto. Persiste aviso previo de esquema XML PHPUnit obsoleto.

No se ejecutó migración en la base principal ni se validó el DDL en MySQL. La compatibilidad y el flujo de baja de cuentas deben verificarse antes de despliegue. No hubo prueba visual porque CP1 no cambia pantallas.

Salida: se puede guardar y consultar un borrador íntegro, sin UI ni publicación automática. No crear tip_reads ni tablas de dispositivos.

### CP2 — Reglas, Policy e imágenes

- [x] Implementar TipPolicy y TipService con tabla de transiciones completa.
- [x] Implementar autorización por propiedad y acceso admin de moderación.
- [x] Bloqueo/transacción y relectura del estado en cada decisión; carreras reales MySQL pendientes de validación en CP4.
- [x] Implementar imagen privada y compensación de fallos de persistencia.
- [x] Verificar autoría inmutable, inyección de scope/status, rechazo obligatorio y estados incompatibles.

Cierre CP2: 2026-09-11 22:39:24 UTC. Inicio mínimo de CP3: 2026-09-11 22:49:24 UTC.

Archivos: `app/Policies/TipPolicy.php`, registro en `AuthServiceProvider`, `app/Services/Tips/TipService.php`, `TipImageService.php` y `tests/Feature/TipWorkflowTest.php`.

Contrato para los siguientes paneles:

- `TipService::save(User $actor, array $content, ?int $id = null, ?UploadedFile $image = null, bool $removeImage = false, ?string $intent = null)`: crea/edita borrador propio, o corrige rechazado. Intent null/submit/publish; el servidor determina scope/autor/coach y comprueba permisos antes de completar la intención. Validar remove_image boolean en el Request futuro.
- `TipService::transition(User $actor, int $id, string $action, ?string $reason = null)`: submit, withdraw, approve, reject, publish, archive, restore. Lee y bloquea registro dentro de transacción; no acepta instancias obsoletas del formulario. Estado incompatible devuelve ConflictHttpException (409); CP3/4 traducirán a mensaje de sesión para Blade.
- Policy valida actor/propiedad; servicio valida estados dentro de la transacción. La UI debe combinar permiso y estado al mostrar botones; no deducir que update autorizado permite editar un pendiente/publicado.
- `TipImageService::responseForPanel` relee y autoriza al autor/admin antes de servir imagen privada; API de atletas se implementa en CP5 con su propia visibilidad, no con el permiso del panel.
- save administra su transacción exterior y rechaza transacciones anidadas: los futuros controladores no deben envolverlo en otra transacción. Así los archivos anteriores se eliminan solo tras commit real y los nuevos se limpian al revertir.
- Limpieza fallida registra `Tips image cleanup requires retry` con disco/ruta. Recuperación manual: comprobar que la ruta ya no está referenciada por tips, pertenece a tips/ y al disco local antes de reintentar su eliminación. Sin scheduler ni envíos FCM.

Validación: `php artisan test --filter="TipWorkflowTest|TipPersistenceTest" --do-not-cache-result`: 20 pruebas / 111 aserciones correctas. La suite nueva migra users/permisos/tips únicamente en SQLite :memory: y usa Storage::fake(local). Verifica flujos, decisiones repetidas/obsoletas, aislamiento entre coaches, protección contra inyección de campos internos y rollback de imagen/BD al fallar publicación. Lint de servicios/Policy y diff --check correctos; aviso preexistente de XML PHPUnit obsoleto.

Límites: SQLite verifica estado obsoleto secuencialmente, no exclusión de dos conexiones MySQL simultáneas; lockForUpdate está implementado pero esa prueba real sigue en CP4. Sin rutas nuevas, UI, migraciones en MySQL principal ni notificaciones. CP3 puede continuar después de la pausa indicada.

Salida: operaciones reutilizables verificadas independientemente de los controladores; no notificaciones.

### CP3 — Panel coach

- [x] Listado, búsqueda, filtros, paginación y previsualización.
- [x] Formulario compartido, guardar borrador y enviar.
- [x] Retirar, corregir, archivar y restaurar según estado.
- [x] Motivo de rechazo visible y explicación de qué pasa al corregir.
- [x] Probar acceso cruzado con otro coach y recorrido en navegador.

Cierre CP3: 2026-09-12 15:30:28 UTC. Inicio mínimo de CP4: 2026-09-12 15:40:28 UTC.

Archivos: Coach/TipController, Requests/Tips/SaveTipRequest, rutas coach.tips, vistas coach/tips y componentes tips/form, preview, status. Se amplió TipWorkflowTest con recorrido HTTP, CSRF, aislamiento de autoría y contenido escapado. Controlador delega guardado/transiciones/imágenes en los servicios CP2; conflictos se muestran como error de sesión.

Validación: 14 pruebas / 89 aserciones en TipWorkflowTest correctas. Para pruebas HTTP se omite únicamente coach.subscription (no se cargan sus tablas en esa suite); auth/rol/Policy/CSRF permanecen activos. Blade compilado y limpiado; PHP lint y diff --check correctos. Vite build correcto tras escalación por permisos de assets, con avisos previos Browserslist y tipo ESM de postcss.

Navegador: Edge headless con Playwright, fixture temporal SQLite separada, actor coach de prueba y suscripción pagada de prueba. Se mantuvo middleware real de suscripción en este recorrido; la autenticación se inyectó solo en el router temporal, no se probó login real. Se recorrió crear/guardar, enviar, retirar, editar, archivar con confirmación y restaurar. Inspección visual de formulario en 1280px y previsualización en 390px sin overflow; párrafos preservados tras regenerar CSS. Logo de layout no servido por la fixture limitada; no es un cambio de Tips. Servidor/navegador cerrados y fixture temporal retirada.

Rutas de acción coach: POST tips/{tip}/{action}, nombre coach.tips.transition, restringido a submit/withdraw/archive/restore. No rutas separadas con nombres submit/withdraw. Resource sin destroy, IDs numéricos; imágenes protegidas con coach.tips.image. CP4 debe reutilizar los componentes y request sin conceder edición de contenido ajeno.

Base principal sin migrar y sin datos modificados. La UI real requiere desplegar la migración de tips en su momento; no se ejecutó por la restricción de la automatización.

Salida: coach administra su ciclo de trabajo sin publicar directamente; evidencia de UI y errores registrada.

### CP4 — Panel admin y moderación

- [x] Globales propios usando componentes existentes.
- [x] Cola de pendientes de coaches con previsualización/imagen protegida.
- [x] Aprobar/rechazar, guardar revisor/fecha, prohibir editar contenido ajeno.
- [x] Publicación propia y archivo administrativo.
- [x] Pruebas de dos revisiones concurrentes y retirada simultánea del coach.
- [x] Recorrido HTTP del panel admin sin coach ni verificación de correo obligatoria.

Cierre CP4: 2026-09-13 21:23:20 UTC. Inicio mínimo de CP5: 2026-09-13 21:33:20 UTC.

Archivos: Admin/TipController, vistas admin/tips/index/edit/show, rutas admin.tips en web.php, pruebas ampliadas en TipWorkflowTest y prueba manual reproducible tests/manual/TipConcurrencyCheck.php. Formulario, request, preview, badge y servicio reutilizados desde CP2/CP3; sin nueva lógica de publicación en las vistas. Índice muestra globales propios; pending muestra solo tenant pendientes. Admin puede aprobar/rechazar/archivar ajenos, nunca editar texto ajeno.

Validación: TipWorkflowTest pasó 16 pruebas / 121 aserciones, incluidas nuevas rutas admin, acceso sin correo verificado ni coach, rechazo obligatorio y 409 de segunda revisión. route:list expone 9 rutas admin; PHP lint, Blade cache/clear y diff --check correctos. Se preservaron cambios previos de ProfileController y fixtures ajenas.

Concurrencia real: script manual crea una base MySQL de nombre aleatorio tips_cp4_YYYYMMDDHHMMSS_xxxxxx, verifica SELECT DATABASE() y migra solo users/permisos/tips. Dos procesos verificaron aprobar vs rechazar y retirar vs aprobar: ambas segundas operaciones esperaron 1.51 s y devolvieron conflicto; estados finales published y draft respectivamente. Se eliminó únicamente esa base temporal al terminar. Script usa credenciales configuradas sin imprimirlas ni persistirlas. Comando reproducible: php tests/manual/TipConcurrencyCheck.php; no forma parte de PHPUnit y requiere permiso de crear/eliminar su propia base temporal.

QA visual: omitida por decisión del usuario para evitar el costo del control de navegador; no se presenta como validación realizada. El recorrido HTTP automatizado cubre cola, aprobar, rechazo con motivo, crear/publicar global, archivar/restaurar, admin con email_verified_at null y sin coach, con middleware admin real activo. Login real no fue probado. Base principal intacta, sin notificaciones ni API nueva.

Salida: ambos paneles completan el flujo y ninguna publicación pendiente se confunde con publicada.

### CP5 — API de lectura

- [x] TipVisibilityService usando identidad y ClientMembershipAccessService.
- [x] Categorías, listado, detalle e imagen con el mismo alcance.
- [x] Resources, filtros, metadata, errores, fechas y caché según contrato.
- [x] Validar atleta con membresía activa/gracia/vencida y cambio de coach.
- [x] Documentar ejemplos reales sin tokens ni información personal.

Cierre CP5: 2026-09-13 21:34:39 UTC. Inicio mínimo de CP6: 2026-09-13 21:44:39 UTC.

Archivos: `app/Services/Tips/TipVisibilityService.php`, `app/Http/Controllers/Api/V1/App/Client/TipController.php`, `app/Http/Requests/Tips/ListAppTipsRequest.php`, `app/Http/Resources/AppTipResource.php`, `app/Http/Resources/AppTipDetailResource.php`, rutas `app.tips.*` en `routes/api.php` y `tests/Feature/AppTipsApiTest.php`.

Contrato implementado: GET `/api/v1/app/tips/categories`, `/api/v1/app/tips`, `/api/v1/app/tips/{tip}` y `/api/v1/app/tips/{tip}/image`, todos bajo `auth:sanctum` y fuera de `client.membership`. La identidad debe ser `UserApp`; un `User` web obtiene 403. Cuenta o cliente inactivo devuelve 403 con `code=membership_expired` y `access_state=inactive_user`. Membresía activa o en gracia ve globales y tenant del coach actual; membresía vencida ve solo globales publicados; `no_membership` queda denegado por defecto. Detalle e imagen usan la misma consulta visible; recurso no visible responde 404.

Listado: filtros `q`, `category`, `type`, `page`, `per_page`; búsqueda literal con escape de `%`, `_` y `\`; orden `published_at desc, id desc`. Respuesta `ok`, `data` y `meta` con `current_page`, `per_page`, `last_page`, `total`. Resource oculta author_id, coach_id, reviewer, rutas internas y motivo de rechazo. Fechas ISO 8601 UTC, `image_url` absoluta al endpoint protegido y `content_format=plain_text` en detalle. Respuestas e imágenes usan `Cache-Control: no-store, private`.

Validación: `php artisan test --filter=AppTipsApiTest --do-not-cache-result`: 6 pruebas / 58 aserciones. Regresión enfocada `php artisan test --filter="AppTipsApiTest|TipWorkflowTest|CoachTipsPanelTest|AdminPanelAccessTest" --do-not-cache-result`: 33 pruebas / 268 aserciones. `route:list --path=api/v1/app/tips -v` muestra 4 rutas con `auth:sanctum` y sin `EnsureClientMembershipIsActive`; `route:list --path=api/v1/app/trainings -v` conserva `EnsureClientMembershipIsActive`. PHP lint de servicio, controlador, resource y test correcto. `view:cache`, `view:clear` y `git diff --check` correctos; persisten avisos conocidos de XML PHPUnit obsoleto y CRLF en rutas.

QA visual/Ionic: no se ejecutó por alcance de CP5 y por decisión del usuario de evitar control de navegador. Esta entrega deja endpoints integrables para Ionic, no pantallas móviles. Base principal intacta; pruebas en SQLite en memoria con migraciones mínimas y Storage fake.

Salida: contrato integrable desde Ionic sin nuevas decisiones de producto. Sin pantalla móvil, lecturas ni push.

### CP6 — Cierre de primera fase

- [x] Pruebas focalizadas completas, PHP lint, rutas y compilación Blade.
- [x] Revisar diff y regresiones de acceso admin/coach; no alterar biblioteca o membresías.
- [x] Registrar validación visual real y pendientes de navegador/dispositivo por separado.
- [x] Documentar despliegue aditivo, disco y recuperación. No rollback destructivo de datos reales.
- [x] Actualizar este documento con archivos, comandos, resultados, riesgos y siguiente checkpoint.

Cierre CP6: 2026-09-13 21:36:58 UTC.

Validación final Laravel: `php artisan test --filter="TipPersistenceTest|TipWorkflowTest|CoachTipsPanelTest|AppTipsApiTest|AdminPanelAccessTest" --do-not-cache-result` pasó 41 pruebas / 318 aserciones. Cubre persistencia, estados, imágenes privadas, panel coach, panel admin, acceso admin sin coach/correo verificado, API de atletas, vencidos solo globales y regresión de entrenamientos bloqueados por membresía. Las pruebas usan SQLite en memoria o BD temporal MySQL aislada para concurrencia CP4; no se migró ni modificó la base principal.

Rutas verificadas: `php artisan route:list --path=tips -v` muestra 21 rutas Tips en total: 9 admin web con `auth` + `EnsureAdminRole`, 8 coach web con `auth` + `role:coach` + `EnsureCoachSubscriptionIsActive`, y 4 API app con `auth:sanctum`. `php artisan route:list --path=api/v1/app/tips -v` confirma que Tips API no usa `EnsureClientMembershipIsActive`; `php artisan route:list --path=api/v1/app/trainings -v` confirma que entrenamientos conserva ese middleware.

Checks técnicos: lint PHP correcto para enums, modelo, policies, servicios, requests, resources, controladores y pruebas de Tips. `php artisan view:cache`, `php artisan view:clear` y `git diff --check` correctos. Build de assets correcto con `C:\Users\hecto\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe .\node_modules\vite\bin\vite.js build`: 54 módulos transformados. Persisten avisos conocidos: schema XML PHPUnit obsoleto, Browserslist/caniuse-lite desactualizado, package type ESM de `postcss.config.js`, y CRLF en `routes/api.php`/`routes/web.php`.

Diff y alcance: el worktree queda sucio y sin commit por decisión de trabajo incremental. Además de Tips, hay cambios relacionados con acceso admin/verificación y `ProfileController` para flujo de baja con FK de Tips. La carpeta `.codex-tmp/cp3-validation-0912/` queda como artefacto temporal preexistente y no se eliminó. No se cambiaron rutas ni lógica de biblioteca, pagos, WODs o entrenamientos fuera de la verificación de middleware.

Despliegue: aplicar migración aditiva `2026_09_11_000001_create_tips_table.php` en ambiente objetivo cuando corresponda, sin `migrate:fresh`, `db:wipe`, truncados ni rollback destructivo sobre datos reales. Verificar antes que el flujo administrativo de baja de usuarios sea compatible con las FK restrictivas de `tips.author_id` y `tips.coach_id`. El disco usado es `local` privado bajo `tips/`; no exponer rutas físicas ni cambiar el disco global. Si falla una limpieza de imagen, revisar que la ruta pertenezca a `tips/`, que no contenga `..`, que use disco `local` y que ya no esté referenciada por ningún registro antes de eliminarla manualmente.

QA pendiente: no se ejecutó navegador ni dispositivo por decisión del usuario de evitar el costo del control de navegador. CP3 tuvo navegador previo para coach, pero CP4/CP5/CP6 no reclaman QA visual. La siguiente fase debe probar Ionic con el cliente HTTP real, manejo de Bearer para imágenes protegidas y estados visuales, idealmente con pruebas de servicio/componentes antes de cualquier revisión manual.

Salida: primera fase Laravel completada con matriz mínima cubierta por pruebas focalizadas y rutas verificadas. Quedan fuera de esta fase: migración en BD principal, pantalla Ionic, lecturas, push, QA de navegador CP4/CP5/CP6 y validación en dispositivo.

## 13. Matriz mínima de aceptación

- Coach A no lista, edita ni descarga imágenes de B.
- Coach no puede crear globales, aprobarse ni inyectar status/author/coach en un formulario.
- Admin sin coach crea borrador global y publica; no necesita autoaprobarse.
- Pendiente no es editable: retirar primero. Rechazo requiere motivo y conserva autor/audiencia.
- Publicar, aprobar, rechazar y archivar respetan estados bajo concurrencia.
- Publicados son inmutables; archivar/restaurar obliga a comenzar de nuevo el proceso antes de ser visibles.
- Lista, detalle e imagen excluyen todo lo no publicado o ajeno.
- Atleta con membresía vencida puede consultar anuncios globales (lista, detalle e imagen), pero no contenido tenant, WODs ni entrenamientos asignados; cuenta inactiva no recibe contenido. Cubrir esta separación con pruebas de regresión de las rutas existentes.
- Texto se escapa; archivo inválido se rechaza; fallos de guardado no destruyen la imagen vigente.
- Publicaciones no envían notificaciones en fase 1 y no registran lecturas ficticias.

Pruebas de BD únicamente con conexión efectiva inequívocamente aislada. phpunit.xml señala coach_testing en la revisión previa, pero eso no sustituye verificar entorno/configuración cacheada. No migrate:fresh, db:wipe, truncados ni RefreshDatabase sobre la base principal. Imágenes de pruebas con Storage::fake o almacenamiento temporal aislado.

## 14. Fases siguientes, sin ejecución anticipada

### Integración Ionic — Tips para atletas

Este bloque pertenece al repo `C:\xampp\htdocs\coachSaaS\app`. No cambiar backend salvo que una prueba Ionic descubra una discrepancia real del contrato CP5. Mantener pruebas sin control de navegador por defecto; preferir TypeScript lint/build, pruebas unitarias de servicios/componentes y revisión manual opcional por el usuario.

#### ION1 — Contrato TypeScript y servicio API

- [x] Crear DTOs para card, detalle, categoría, filtros y metadata de paginación.
- [x] Crear `src/app/services/athlete-tips.service.ts` usando `ApiService`, sin duplicar base URL ni lectura de token.
- [x] Implementar `categories`, `index`, `show` y helper para descargar imagen protegida como Blob/Object URL con Bearer.
- [x] Manejar 401/403/404/422 con errores consumibles por páginas, sin cambiar el Handler global de Angular.
- [x] Pruebas unitarias del servicio con HttpClient testing o mocks equivalentes.

Cierre ION1: 2026-09-14 03:45:15 UTC.

Archivos Ionic: `src/app/services/athlete-tips.service.ts`, `src/app/services/athlete-tips.service.spec.ts` y `src/app/services/api.service.ts`. El servicio define tipos para `tip|note|news`, `global|tenant`, categorías, card, detalle, filtros y metadata; consume `app/tips/categories`, `app/tips`, `app/tips/{id}` y `app/tips/{id}/image` por medio de `ApiService`. Para imágenes protegidas se agregó `ApiService::getBlob`, reutilizando el mismo Bearer y `environment.apiUrl`; `AthleteTipsService` expone `imageBlob`, `objectUrlFor` y `revokeObjectUrl`.

Validación: build Ionic correcto con `C:\Users\hecto\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe .\node_modules\@angular\cli\bin\ng.js build`. Persisten warnings previos de imports no usados en LoginPage, optional chain en training-details, budgets SCSS y glob Stencil. `git diff --check` correcto con aviso CRLF esperado en `src/app/services/api.service.ts`.

Pruebas: se creó spec de servicio con mocks de `ApiService`, cubriendo categorías, limpieza de filtros, metadata, detalle e imagen Blob. La ejecución `ng test --include src/app/services/athlete-tips.service.spec.ts --watch=false --browsers=ChromeHeadless` no completó porque ChromeHeadless no pudo iniciar por fallo GPU/proceso; no se insistió para respetar la preferencia de evitar control de navegador. `ng lint` global no está verde por deuda preexistente `@angular-eslint/prefer-inject` en muchas clases; el servicio nuevo usa `inject()` para no agregar ese error. ESLint directo no pudo usarse porque el proyecto no expone `eslint.config.js` flat para ESLint 9.

Salida: Ionic puede consumir CP5 desde una capa typed y reutilizable, sin UI todavía.

#### ION2 — Rutas y navegación del atleta

- [x] Registrar ruta lazy `tips` bajo `src/app/tabs/tabs.routes.ts` o ruta protegida equivalente para atleta.
- [x] Registrar ruta de detalle `tips/:id` fuera o dentro de tabs según patrón existente de `training-details`.
- [x] Agregar tab/menu visible para Tips en `src/app/tabs/tabs.page.html` sin romper las tabs actuales.
- [x] Confirmar que `authGuard` protege listado y detalle.
- [x] Pruebas o revisión estática de rutas para evitar wildcard hacia login.

Cierre ION2: 2026-09-14 04:06:08 UTC.

Archivos Ionic: `src/app/tabs/tabs.routes.ts`, `src/app/tabs/tabs.page.html`, `src/app/tabs/tabs.page.ts`, `src/app/app.routes.ts`, `src/app/pages/tips/tips.page.ts`, `src/app/pages/tips/tips.page.html`, `src/app/pages/tips/tips.page.scss`, `src/app/pages/tip-detail/tip-detail.page.ts`, `src/app/pages/tip-detail/tip-detail.page.html` y `src/app/pages/tip-detail/tip-detail.page.scss`.

Implementación: listado registrado como tab lazy en `/tabs/tips`; detalle registrado como ruta protegida `/tips/:id`, siguiendo el patrón de `training-details` para pantallas que pueden abrirse desde varios puntos. Se agregó tab `TIPS` con `newspaper-outline`. Las páginas creadas son placeholders compilables para que ION3 implemente listado e ION4 detalle sin volver a resolver navegación.

Validación: build Ionic correcto con `C:\Users\hecto\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe .\node_modules\@angular\cli\bin\ng.js build`. `git diff --check` correcto con avisos CRLF esperados en archivos modificados. Revisión estática confirma `/tabs` protegido por `authGuard`, `/tabs/tips` bajo ese grupo y `/tips/:id` con `canMatch: [authGuard]`, antes del wildcard hacia login. Persisten warnings previos del build: imports no usados en LoginPage, optional chain en training-details, budgets SCSS y glob Stencil. No se ejecutó navegador.

Salida: navegación preparada para abrir listado y detalle de Tips autenticados.

#### ION3 — Listado de Tips

- [x] Crear página `src/app/pages/tips/` con carga inicial `per_page=20`.
- [x] Mostrar tarjetas escaneables con título, categoría, tipo, scope global/coach, excerpt, fecha e imagen opcional.
- [x] Agregar búsqueda y filtros por categoría/tipo usando el contrato CP5; refrescar desde página 1 al cambiar filtros.
- [x] Soportar loading, error, vacío real, pull-to-refresh y paginación/infinite scroll si encaja con el patrón actual.
- [x] No renderizar `body` como HTML; mantener texto plano.
- [x] Pruebas de componente o, mínimo, build TypeScript y verificación de estados mediante mocks.

Cierre ION3: 2026-09-14 04:12:00 UTC.

Archivos Ionic: `src/app/pages/tips/tips.page.ts`, `src/app/pages/tips/tips.page.html` y `src/app/pages/tips/tips.page.scss`.

Implementación: el tab `/tabs/tips` ahora carga datos reales con `AthleteTipsService.index({ per_page: 20 })` y `categories()`. Incluye búsqueda con debounce, filtros por categoría y tipo, refresh, estados de carga/error/vacío, paginación mediante botón `Cargar más`, tarjetas con metadata, scope, fecha, excerpt y navegación a `/tips/{id}`. Las imágenes del listado se descargan con `imageBlob(id)` y Object URL para respetar el Bearer del endpoint protegido; las URLs se revocan al destruir la página.

Validación: build Ionic correcto con `C:\Users\hecto\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe .\node_modules\@angular\cli\bin\ng.js build`. `git diff --check` correcto con avisos CRLF esperados. Persisten warnings previos del build: imports no usados en LoginPage, optional chain en training-details, budgets SCSS y glob Stencil. No se ejecutó navegador ni Karma por la preferencia vigente de evitar control de navegador; la validación principal fue TypeScript/build.

Salida: atleta puede navegar y filtrar el feed sin exponer datos internos.

#### ION4 — Detalle de Tip e imágenes privadas

- [x] Crear página `src/app/pages/tip-detail/` o nombre equivalente consistente.
- [x] Consumir `GET /api/v1/app/tips/{id}` y renderizar título, metadata, body texto plano, fecha e imagen.
- [x] Descargar imagen protegida con Bearer y usar Object URL; liberar URL en destroy para evitar fugas.
- [x] Manejar 404 como contenido no disponible y 403 como sesión/acceso no válido, sin revelar si existía otro Tip.
- [x] Back navigation coherente hacia listado o tab principal.
- [x] Pruebas de carga, error y limpieza de Object URL.

Cierre ION4: 2026-09-14 04:14:10 UTC.

Archivos Ionic: `src/app/pages/tip-detail/tip-detail.page.ts`, `src/app/pages/tip-detail/tip-detail.page.html` y `src/app/pages/tip-detail/tip-detail.page.scss`.

Implementación: la ruta `/tips/:id` carga el detalle real con `AthleteTipsService.show(id)`, renderiza categoría, tipo, scope, fecha, título y `body` como texto plano con saltos de línea preservados. Si el tip trae `image_url`, descarga la imagen con `imageBlob(id)`, crea Object URL y la revoca al recargar o destruir la página. 404 se presenta como contenido no disponible; 403 como falta de acceso de la sesión, sin revelar si el tip existía o pertenecía a otra audiencia. Incluye back navigation a `/tabs/tips`, retry y estado de carga/error.

Validación: build Ionic correcto con `C:\Users\hecto\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe .\node_modules\@angular\cli\bin\ng.js build`. `git diff --check` correcto con avisos CRLF esperados. Persisten warnings previos del build: imports no usados en LoginPage, optional chain en training-details, budgets SCSS y glob Stencil. No se ejecutó navegador ni Karma por la preferencia vigente de evitar control de navegador.

Salida: detalle funcional con imágenes privadas y sin depender de `<img src>` directo al endpoint protegido.

#### ION5 — Integración con Home/experiencia atleta

- [x] Decidir ubicación final: tab independiente, sección resumida en Home o ambas.
- [x] Si se muestra resumen en Home, consumir `GET /api/v1/app/tips?per_page=3` y enlazar al listado.
- [x] Para atletas vencidos, mostrar globales sin abrir entrenamientos bloqueados; no esconder Tips solo porque `/app/trainings` responda 403.
- [x] Revisar textos y estados para membresía vencida, sin prometer acceso a WODs ni entrenamientos.
- [x] Confirmar que notificaciones/push no se incorporan en esta fase.

Salida: Tips queda integrado en el flujo diario del atleta sin mezclar reglas de entrenamiento.

Cierre ION5 — 2026-09-13 22:22 -06 / 2026-09-14 04:22 UTC.

Archivos Ionic: `src/app/tab1/tab1.page.ts`, `src/app/tab1/tab1.page.html` y `src/app/tab1/tab1.page.scss`.

Implementación: Home mantiene el tab independiente `/tabs/tips` y agrega una sección resumida de Tips con los primeros 3 elementos publicados desde `AthleteTipsService.index({ per_page: 3 })`. Cada tarjeta enlaza al detalle `/tips/{id}` y el CTA `Ver todos` enlaza al listado completo. La carga de Tips se ejecuta en paralelo e independiente de entrenamientos; si `/app/trainings` falla por membresía vencida o cualquier otro 403, la sección de Tips no queda escondida por ese error. Los textos del resumen no prometen WODs ni entrenamientos. No se agregó lectura, push, notificaciones ni contadores.

Validación: build Ionic correcto con `C:\Users\hecto\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe .\node_modules\@angular\cli\bin\ng.js build`. `git diff --check` correcto con avisos CRLF esperados. Primer build falló solo por budget SCSS de `tab1`; se resolvió eliminando estilos muertos del bloque anterior de recovery que ya no estaba en uso. Persisten warnings previos del build: imports no usados en LoginPage, optional chain en training-details, budgets SCSS no bloqueantes y glob Stencil. No se ejecutó navegador, Karma ni dispositivo por la preferencia vigente de evitar control de navegador.

#### ION6 — Validación y handoff Ionic

- [x] Ejecutar build/lint/pruebas disponibles del repo `app`.
- [x] Validar que no se agregaron tokens hardcodeados, URLs absolutas nuevas innecesarias ni datos personales en fixtures.
- [x] Documentar comandos, resultados y limitaciones: sin navegador si se mantiene la preferencia actual; sin dispositivo salvo autorización explícita.
- [x] Registrar cualquier ajuste requerido en Laravel como nuevo checkpoint, no como parche silencioso.
- [x] Actualizar este roadmap con archivos Ionic, resultados y siguiente fase.

Salida: integración Ionic lista para revisión manual del usuario y para decidir si se continúa con lecturas (`tip_reads`) o notificaciones.

Cierre ION6 — 2026-09-13 23:01 -06 / 2026-09-14 05:01 UTC.

Archivos Ionic revisados: `src/app/services/athlete-tips.service.ts`, `src/app/services/athlete-tips.service.spec.ts`, `src/app/services/api.service.ts`, `src/app/tabs/tabs.routes.ts`, `src/app/tabs/tabs.page.html`, `src/app/tabs/tabs.page.ts`, `src/app/app.routes.ts`, `src/app/pages/tips/`, `src/app/pages/tip-detail/` y `src/app/tab1/`.

Validación CLI: `C:\Users\hecto\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe .\node_modules\@angular\cli\bin\ng.js build` correcto. `git diff --check` correcto con avisos CRLF esperados en archivos modificados. `ng lint` global ejecutado y no queda verde por deuda existente `@angular-eslint/prefer-inject` distribuida en el proyecto; se ajustó `tab1` para que `AthleteTipsService` use `inject()` y no sumar un error nuevo por constructor. Los archivos nuevos de Tips no aparecen en la salida de lint. No se ejecutó Karma, navegador ni dispositivo por la preferencia vigente de evitar control de navegador.

Revisión estática: no se agregaron tokens hardcodeados, secretos, credenciales, datos personales de fixture ni URLs absolutas nuevas para Tips. El feature usa `environment.apiUrl` y el Bearer existente mediante `ApiService`; la URL externa encontrada en Home corresponde al `fallbackCover` heredado, no a Tips. No se detectó necesidad de ajuste Laravel adicional para cerrar la integración Ionic.

Handoff: ION1-ION6 dejan Tips expuesto en Ionic con contrato, servicio, tab/listado, detalle, imágenes privadas mediante Blob y preview en Home. Pendiente fuera de esta fase: revisión manual visual en app/dispositivo si el usuario la autoriza, y decidir si se continúa con Fase 2 `tip_reads` o Fase 3 notificaciones.

### Fase 2 — Lecturas

TipRead y tip_reads con FK user_app_id a user_apps y UNIQUE(tip_id,user_app_id). POST `/api/v1/app/tips/{tip}/read` idempotente y autorizado; resolver concurrencia con unicidad real, no solo una comprobación previa. Añadir is_read mediante exists sin N+1. Leer un Tip y leer una PushNotification son cosas distintas.

### Fase 3 — Notificaciones

Reutilizar AppNotificationService, UserDevice/user_devices, PushNotification y `/api/v1/app/register-device`. No instalar otro paquete ni crear device_tokens por este feature.

Enviar solo tras commit de transición a published, en cola y con persistencia del control de envío para evitar duplicados ante reintentos. Definir si una republicación notifica antes de activarlo. Destinatarios elegibles según la misma visibilidad del feed, dispositivos habilitados y lotes acotados; no enviar a todos los tokens sin resolver acceso. Error FCM no revierte publicación. Payload con acción/tip_id y navegación Ionic se diseña en esta fase.

### Fase futura — Multiples imagenes por Tip

Idea anotada para desarrollar el 2026-09-16. Permitir que coach y panel master suban mas de una imagen por Tip, manteniendo una imagen principal para listados/Home y usando un slider en el detalle Ionic `/tips/{id}` cuando exista mas de una imagen.

Puntos a definir antes de implementar:

- Modelo: decidir si se reemplaza `tips.image_path` por una tabla `tip_images` ordenable, o si se conserva como imagen principal y se agrega galeria asociada.
- Backend: aceptar multiples archivos en formularios coach/admin, validar limite por cantidad/peso/dimensiones, preservar autorizacion existente y servir imagenes con la misma visibilidad del Tip.
- Compresion: comprimir/normalizar imagenes al guardar para ahorrar espacio, preferentemente generando WebP/JPEG optimizado y dimensiones maximas razonables para app movil.
- Storage: evaluar si continuar en disco privado local o mover imagenes a un bucket Cloudflare R2; si se usa R2, mantener URLs protegidas o temporales y no exponer rutas fisicas permanentes.
- API Ionic: extender el recurso para devolver una lista ordenada de imagenes protegidas; conservar compatibilidad con una sola imagen.
- Ionic: en `/tips/{id}` mostrar slider solo si hay mas de una imagen; si hay una, mantener hero simple. Listado y Home deben usar solo la imagen principal para no cargar de mas.
- Migracion: debe ser aditiva y con migracion/backfill de la imagen actual hacia la nueva estructura si se crea `tip_images`.

## 15. Registro y formato de entrega

| Fecha | Checkpoint | Resultado |
| --- | --- | --- |
| 2026-09-10 | Plan original | Primera propuesta de Tips del coach |
| 2026-09-11 | Cascarón y acceso admin | Menús/pantallas separados, componentes compartidos. Corrección de verificación admin. Previamente pasaron 5 pruebas de acceso / 11 aserciones, PHP, rutas y compilación Blade; sin prueba visual real |
| 2026-09-11 | Revisión del plan | Sustituido por módulo compartido con moderación, checkpoints y fases de lecturas/push diferidas. Sin nuevos cambios de código o BD en esta revisión |
| 2026-09-11 | Reglas confirmadas | Aprobación obligatoria para todo contenido del coach. Anuncios globales accesibles con membresía vencida; WODs y asignados siguen restringidos. Actualización documental, sin implementación adicional |
| 2026-09-11 | CP1 | Migración, modelo, 4 enums y reglas compartidas implementados. 8 pruebas / 50 aserciones en SQLite en memoria y lint correctos. Base principal sin migrar; siguiente paso CP2: Policy, servicio de estados e imágenes |

Al cerrar un checkpoint agregar: alcance completado, archivos afectados, pruebas/comandos con resultados, decisiones cerradas, pendientes/bloqueos y siguiente paso. No repetir auditorías ya realizadas salvo cambios relevantes en el entorno o código.

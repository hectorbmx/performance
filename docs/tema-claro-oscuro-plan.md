# Tema claro y oscuro para cliente y coach

Estado inicial: planeado.

## Objetivos

- Ofrecer **Claro**, **Oscuro** y **Sistema** desde el perfil del cliente y el menú del coach.
- Aplicar la apariencia a pantallas, modales, navegación y controles nativos, conservando el diseño claro actual como referencia visual.
- Recordar la elección al recargar o reiniciar la app. En **Sistema**, responder a cambios de apariencia del dispositivo mientras la app está abierta.
- Mantener legibles los formularios y reconocibles los estados de entrenamiento, membresía y validación en ambos temas.

## Alcance y punto de partida

La app Ionic/Angular comparte el arranque y la configuración de Capacitor entre los dos roles. El alcance incluye web, Android e iOS, además de las pantallas compartidas de acceso y cuenta. La preferencia se guarda por instalación o navegador. La sincronización entre dispositivos y el panel Blade de Laravel quedan para una iniciativa separada.

El barrido encontró colores explícitos en 36 de 39 archivos SCSS de la app, incluidos 13 del coach. La cifra incluye estilos compartidos y archivos fuera de las rutas principales. Ionic carga `dark.system.css`, mientras varios estilos de la app fijan fondos claros o fuerzan `color-scheme: light`. El selector por sí solo dejaría superficies de ambos temas mezcladas.

La compilación optimizada ya falla por el límite de 10 kB por hoja de componente en Inicio del cliente (15.63 kB), Calendario de actividad (11.42 kB) y Detalle de entrenamiento (16.64 kB). Hay que corregir esa regresión de presupuesto antes de publicar.

## Checkpoints

### CP01 · Inventario y línea base

**Objetivo** — Fijar las rutas, estados y tamaños que guiarán la migración.

- [ ] Capturar el tema claro actual en móvil y escritorio para cliente y coach. Incluir pantallas con datos, vacías, cargando y con errores.
- [ ] Inventariar fondos, texto, bordes, botones, gráficas y estados semánticos. Localizar valores en SCSS, estilos de plantilla, código y configuración nativa.
- [ ] Identificar qué estilos usan `_athlete-design.scss`, `_coach-refresh.scss` y `_secondary-page.scss` y qué reglas locales los sobrescriben.
- [ ] Registrar el resultado inicial de las compilaciones de desarrollo y producción, incluidos los presupuestos de CSS.

**Cierre** — Matriz de pantallas priorizada, capturas de referencia y resultados de compilación disponibles para comparar.

### CP02 · Preferencia y paleta compartida

**Objetivo** — Tener una sola fuente de verdad para la apariencia de la app.

- [ ] Cambiar la paleta automática de Ionic por la paleta controlada por clase en la raíz del documento.
- [ ] Implementar un servicio Angular para resolver y aplicar el tema y guardar la preferencia con Capacitor Preferences. Usar **Sistema** si falta el valor o es inválido.
- [ ] Aplicar el tema al inicio y sincronizar `color-scheme` con el tema aplicado. Escuchar cambios del dispositivo solo cuando se use **Sistema** y liberar el listener al salir de ese modo.
- [ ] Definir variables CSS para página, tarjetas, superficies elevadas, texto, bordes, campos, foco y estados. Vincular las variables de Ionic y los estilos comunes a esa paleta.
- [ ] Añadir un selector reutilizable a **Mi perfil** y al menú lateral del coach. Mantenerlo detrás de la habilitación del feature hasta cerrar las verificaciones visuales.
- [ ] Comprobar guardado, recarga, reinicio, cambio de tema del dispositivo y navegación entre roles.

**Cierre** — Los dos roles usan la misma preferencia y pueden cambiar de tema sin reiniciar, con una apariencia coherente desde el arranque.

### CP03 · Flujos del cliente

**Objetivo** — Adaptar las vistas del atleta en el orden en que se usan.

- [ ] Migrar navegación, Inicio, Mi perfil y los estilos compartidos del atleta.
- [ ] Migrar detalle de entrenamiento, registro de series, constructor y progreso. Revisar tablas, campos nativos, gráficas y modales.
- [ ] Migrar calendario de actividad, métricas, historial de salud y suscripciones, biblioteca, gimnasio, tips y temporizador.
- [ ] Revisar acceso, registro, recuperación, cuenta y privacidad en los dos temas.
- [ ] Reducir el CSS duplicado de Inicio, Calendario de actividad y Detalle de entrenamiento. Medir cada hoja contra el límite vigente.

**Cierre** — El cliente puede entrar, consultar un entrenamiento, registrar series y revisar su actividad en ambos temas. Las tres hojas con exceso quedan dentro del presupuesto de producción.

### CP04 · Flujos del coach

**Objetivo** — Adaptar la operación diaria del coach y sus controles compartidos.

- [ ] Migrar menú, barra de pestañas y estilos compartidos de listas y vistas secundarias.
- [ ] Migrar atletas y ficha del atleta, incluidos avatar, estado, resumen de entrenamientos, vigencia del plan y accesos a progreso y calendario.
- [ ] Migrar grupos, entrenamientos, biblioteca, planes y suscripciones.
- [ ] Migrar la captura y edición de entrenamientos. Revisar lifting, dictado, validaciones, asignación y los modales de grabación y búsqueda de video.
- [ ] Revisar las vistas compartidas con el cliente, en particular progreso y controles de fecha.

**Cierre** — El coach puede abrir la ficha de un atleta, asignar un plan y crear o editar un entrenamiento con video en ambos temas.

### CP05 · Verificación y publicación

**Objetivo** — Validar la experiencia completa y habilitar el selector cuando esté lista.

- [ ] Ejecutar la matriz de verificación en web de escritorio, web móvil, Android e iOS. Registrar versión y evidencia.
- [ ] Verificar contraste de texto, botones e indicadores. Confirmar que foco, errores y estados sigan siendo entendibles sin depender solo del color.
- [ ] Revisar barras de estado, áreas seguras, teclado, campos, alertas, modales, selectores, cámara y archivos en dispositivos reales.
- [ ] Comparar las capturas del modo claro contra CP01 y medir de nuevo los presupuestos de CSS.
- [ ] Ejecutar la compilación de producción y las pruebas del servicio de preferencia. Cerrar los hallazgos que bloqueen recorridos críticos.
- [ ] Habilitar el selector, verificar la actualización desde una instalación existente y registrar qué compilación se publicó.

**Cierre** — Ambos roles pasan sus recorridos críticos, la compilación optimizada termina correctamente y la preferencia persiste tras actualizar la app.

## Matriz de verificación

| Escenario | Resultado esperado | Evidencia |
| --- | --- | --- |
| Instalación nueva o preferencia inválida | Sigue la apariencia del dispositivo | Pendiente |
| Claro u Oscuro elegido manualmente | Se conserva al recargar, cerrar sesión y reabrir la app. Los cambios del sistema respetan la elección manual. | Pendiente |
| Sistema con la app abierta | La interfaz cambia cuando cambia la apariencia del dispositivo | Pendiente |
| Cliente abre un entrenamiento y registra series | Campos, gráficas, botones y mensajes se leen y funcionan en ambos temas | Pendiente |
| Coach edita un entrenamiento y adjunta un video | Formulario, cámara, biblioteca y estados de carga se leen y funcionan en ambos temas | Pendiente |
| Formularios y modales con teclado abierto | El foco y las acciones siguen visibles y utilizables | Pendiente |
| Compilación optimizada | Compila con los presupuestos vigentes | Pendiente |

## Bitácora de hallazgos

Registrar la ruta, el tema, el dispositivo y los pasos para reproducir. Añadir captura o referencia de código cuando ayude a resolver el hallazgo.

| ID | Fecha y checkpoint | Hallazgo y evidencia | Impacto | Acción y verificación | Estado |
| --- | --- | --- | --- | --- | --- |
| H-001 | Auditoría inicial · CP01 | Paleta automática de Ionic con estilos locales que fuerzan superficies claras | Apariencia inconsistente | Migrar a variables comunes y comparar ambos temas en las rutas principales | Abierto |
| H-002 | Auditoría inicial · CP01 | Tres hojas del cliente exceden 10 kB en producción | Bloquea la compilación optimizada | Reducir CSS duplicado y repetir la compilación | Abierto |
| H-___ | | | | | |

## Registro de decisiones

Anotar qué se decidió, qué alternativas se consideraron y cómo afecta al plan. Marcar cada fila como propuesta, aceptada o reemplazada.

| ID | Fecha y checkpoint | Decisión o pregunta | Alternativas y motivo | Estado |
| --- | --- | --- | --- | --- |
| D-001 | Planeación · CP02 | Guardar la preferencia por instalación o navegador | La sincronización por cuenta requeriría API y almacenamiento adicional. Una preferencia local permite que cada dispositivo tenga su propia apariencia. | Propuesta |
| D-002 | Planeación · CP02 | Incluir Claro, Oscuro y Sistema, con Sistema como valor inicial | Conserva el comportamiento automático de Ionic para quien nunca haya elegido un tema. | Propuesta |
| D-003 | Planeación · CP05 | Habilitar el selector después de verificar ambos roles | Evita publicar un selector que cambie la navegación mientras quedan formularios o superficies pendientes de migrar. | Propuesta |
| D-___ | | | | |

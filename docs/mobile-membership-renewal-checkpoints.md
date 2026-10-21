# Plan de Trabajo: Renovacion de Membresia Desde la App Movil

## Fecha y estado

- Fecha de inventario: 2026-10-20.
- Estado: Fase 0 completada; implementacion pendiente.
- Alcance: backend Laravel en `coach/` y app Ionic/Angular en `app/`.
- Regla de trabajo: avanzar por checkpoints pequenos, conservar cambios locales y validar cada bloque antes de continuar.

## Objetivo

Permitir que un atleta con membresia vencida pueda:

1. Ver claramente que su membresia vencio.
2. Elegir un plan Stripe disponible.
3. Pulsar `Renovar membresia`.
4. Abrir un checkout seguro de Stripe desde Android/iOS.
5. Recuperar el acceso solo despues de la confirmacion del webhook de Stripe.

El flujo de compra anticipada para una membresia activa debe mantenerse separado del flujo de renovacion.

## Resultado esperado

- Una membresia pendiente de pago no concede acceso.
- Un pago confirmado por Stripe activa la membresia y actualiza sus fechas.
- Un atleta vencido puede consultar membresias y renovar sin ser expulsado de la app.
- Un atleta activo puede seguir viendo y comprando una membresia futura.
- Un plan manual no aparece como comprable mediante Stripe.
- La app usa el navegador de Capacitor y puede refrescar su estado al volver.
- Los webhooks repetidos no duplican ni corrompen membresias.

## Checkpoint 0: Inventario y hallazgos

Estado: completado.

### Checkouts confirmados

App movil:

- Ruta: `C:\xampp\htdocs\coachSaaS\app`.
- Rama: `master`.
- Commit observado: `55d261f calculadora de porcentajes`.
- Tiene cambios locales no relacionados en calendario, entrenamiento y actividad.

Backend:

- Ruta: `C:\xampp\htdocs\coachSaaS\coach`.
- Rama: `main`.
- Commit observado: `deb9a8e ajustes visuales en las graficas`.
- Sin cambios locales detectados durante el inventario.

### Contrato actual

Rutas existentes bajo `auth:sanctum`:

```text
GET  /api/v1/app/memberships
POST /api/v1/app/memberships/future
```

`GET /api/v1/app/memberships` devuelve:

- `current_membership`.
- `future_membership`.
- `memberships`.
- `available_plans`.

La app consume este contrato en:

- `app/src/app/pages/subscription-history/subscription-history.page.ts`.
- `app/src/app/pages/subscription-history/subscription-history.page.html`.

El checkout de Stripe ya existe en:

- `coach/app/Services/Billing/StripeConnectService.php`.
- `coach/app/Http/Controllers/Api/V1/StripeWebhookController.php`.

La ruta real del webhook es:

```text
POST /api/stripe/webhook
```

### Hallazgos confirmados

1. `storeFuture()` crea una membresia antes de abrir Stripe.
2. La crea como `status = active` y `billing_status = unpaid`.
3. Para una membresia vencida calcula `starts_at` usando `ends_at + 1 dia`, aunque esa fecha ya este en el pasado.
4. `ClientMembershipAccessService` considera acceso vigente principalmente por `status` y fechas; no exige `billing_status = paid`.
5. Esto puede conceder acceso antes de confirmar el pago.
6. La app muestra `Comprar futura` incluso cuando la membresia actual ya vencio.
7. `available_plans.can_checkout` revisa Stripe habilitado en el coach, pero no confirma que el plan use Stripe.
8. La app abre el checkout con `window.open()` aunque el proyecto ya tiene `@capacitor/browser`.
9. Las URLs de exito y cancelacion actuales apuntan al panel web del coach.
10. No existen pruebas especificas para el contrato completo de `app/memberships` y renovacion movil.
11. El entorno movil observado apunta a `https://coach.training-flow.com/api/v1`; las URLs locales estan comentadas.
12. No se obtuvo un JSON autenticado real en esta fase porque no habia un token de atleta disponible.

## Checkpoint 1: Definir estados y proteger el acceso

Estado: implementado; prueba automatica bloqueada por el entorno local de PHPUnit.

### Trabajo

- Introducir formalmente `pending_payment` para membresias creadas antes de confirmar Stripe.
- Mantener `active`, `expired`, `cancelled` y `past_due` como estados diferenciables.
- Excluir `pending_payment` de la membresia actual y de la membresia futura activa.
- Asegurar que `ClientMembershipAccessService` nunca conceda acceso a `pending_payment`.
- Confirmar que `expired` siga siendo el estado de la membresia vencida anterior.

### Criterios de aceptacion

- Una membresia con `status = pending_payment` no puede usar rutas operativas.
- Una membresia `active` y pagada puede usar rutas operativas.
- Una membresia vencida mantiene `access_state = expired`.
- No se duplican consultas de vigencia entre controller, middleware y login.

### Validacion

- Pruebas aisladas para activa, vencida, pendiente y periodo de gracia.
- `php -l` en archivos PHP modificados.
- No ejecutar `migrate:fresh`, `migrate:refresh`, `db:wipe` ni migraciones destructivas sobre la base local.

### Implementacion realizada

- `ClientMembership::STATUS_PENDING_PAYMENT` centraliza el nuevo estado.
- `MembershipController::storeFuture()` crea la membresia movil como `pending_payment` antes del checkout.
- `ClientMembershipAccessService` distingue una membresia pendiente y devuelve `can_access_service = false`.
- El webhook existente puede cambiar `pending_payment` a `active` cuando Stripe confirma la suscripcion.
- Se agrego `tests/Feature/ClientMembershipAccessServiceTest.php` con casos de activa, vencida, gracia, pendiente y creacion movil.

### Validacion realizada

- `php -l` paso en los cuatro archivos PHP modificados y en la prueba nueva.
- `git diff --check` paso.
- `php artisan test tests/Feature/ClientMembershipAccessServiceTest.php` no pudo iniciar porque PHPUnit reporta que no puede abrir `vendor/autoload.php`, aunque PHP y Artisan si pueden leer/cargar el autoloader fuera del runner. La prueba queda pendiente de ejecutar cuando se corrija ese problema del entorno.

## Checkpoint 2: Separar renovacion de compra futura

Estado: implementado; prueba automatica pendiente por el bloqueo local de PHPUnit.

### Trabajo

- Mantener `POST /api/v1/app/memberships/future` para atletas con membresia activa.
- Crear un endpoint explicito para renovacion, por ejemplo:

```text
POST /api/v1/app/memberships/renewal-checkout
```

- Validar que el atleta pertenece al coach.
- Validar que el plan esta activo.
- Validar que el plan usa Stripe.
- Crear la nueva membresia como `pending_payment`.
- Reutilizar una renovacion pendiente si ya existe.
- Devolver `checkout_url`, `session_id` y la membresia pendiente.

### Implementacion realizada

- Se agrego `POST /api/v1/app/memberships/renewal-checkout`.
- El endpoint solo permite planes activos del coach que usan Stripe.
- Rechaza la renovacion si existe una membresia activa o futura.
- Una renovacion vencida inicia hoy, no en una fecha pasada derivada de la membresia anterior.
- Reutiliza la membresia `pending_payment` del mismo plan para evitar duplicados.
- Rechaza una segunda renovacion pendiente con otro plan.
- El listado de planes ahora expone `payment_provider` y `can_checkout` solo para planes Stripe con Stripe Connect habilitado.
- Se mantuvo `/future` separado para la compra anticipada de atletas activos.

### Validacion realizada

- `php -l` paso en controller, rutas y pruebas.
- `git diff --check` paso; Git solo reporto la normalizacion CRLF/LF existente en `routes/api.php`.
- Se agregaron pruebas para inicio desde hoy y reutilizacion de una renovacion pendiente.
- La ejecucion de PHPUnit queda pendiente porque el runner local no puede abrir `coach/vendor/autoload.php`, aunque PHP y Artisan si pueden leer el archivo fuera del runner.

### Criterios de aceptacion

- Un atleta vencido no usa semanticamente el endpoint de compra futura.
- Dos clics o dos peticiones consecutivas no crean dos renovaciones pendientes.
- Una membresia futura de un atleta activo conserva su fecha de inicio posterior.
- Una renovacion vencida inicia desde hoy o desde la fecha definida por la regla de negocio, no desde una fecha pasada.

## Checkpoint 3: Corregir disponibilidad de planes

Estado: implementado; compilacion movil correcta.

### Trabajo

- Incluir `payment_provider` en la consulta y payload de planes.
- Calcular `can_checkout` solo cuando:
  - el coach tiene `stripe_charges_enabled`;
  - el plan tiene `payment_provider = stripe`.
- Mantener visibles los planes manuales solo como informativos, o excluirlos de esta pantalla.
- Definir el mensaje cuando no haya planes Stripe disponibles.

### Implementacion realizada

- El endpoint de membresias ahora incluye `payment_provider` en cada plan.
- `can_checkout` solo es verdadero cuando el plan es Stripe y el coach tiene Stripe Connect habilitado.
- Los planes manuales permanecen visibles como informativos y la app muestra `Pago con tu coach` sin boton Stripe.
- Un plan Stripe sin Stripe habilitado muestra `Pago no disponible` y no permite iniciar checkout.

### Validacion realizada

- PHP lint y rutas del backend ya validados en el checkpoint anterior.
- Se actualizo el contrato TypeScript y la tarjeta de planes de la pantalla móvil.
- `npm run build` de la app termino correctamente; quedaron solo advertencias de presupuesto SCSS preexistentes y del bundle de Ionic.

### Criterios de aceptacion

- Un plan manual no muestra boton de checkout Stripe.
- Un plan Stripe con coach no habilitado no permite pagar.
- Un plan Stripe valido si permite generar checkout.

## Checkpoint 4: Hacer idempotente y seguro el checkout

Estado: implementado; validacion automatica bloqueada por el entorno local de PHPUnit.

### Trabajo

- Reutilizar la sesion Stripe abierta asociada a la membresia pendiente.
- Mantener la idempotencia por membresia y sesion.
- Evitar que un error al generar checkout deje una membresia activa.
- Definir que ocurre con una sesion cancelada o expirada.
- Confirmar que el webhook sea la fuente de verdad, no la URL de retorno.

### Implementacion realizada

- `future` reutiliza una membresia `pending_payment` del mismo plan y rechaza otra pendiente con un plan distinto.
- `StripeConnectService` reutiliza la sesion Stripe abierta asociada a la membresia.
- Una sesion Stripe completada no genera otra suscripcion; se espera la confirmacion del webhook.
- Una sesion expirada limpia el vinculo anterior y permite reintentar con una nueva sesion.
- Una membresia con suscripcion Stripe existente no puede abrir otro checkout.
- Los reintentos conservan la clave idempotente por membresia cuando Stripe no responde despues de crear la sesion.
- La membresia se mantiene en `pending_payment` mientras el checkout no este confirmado; nunca se activa por la URL de retorno.

### Validacion realizada

- `php -l` paso en controller, servicio Stripe y pruebas.
- `git diff --check` paso; Git solo reporto normalizacion CRLF/LF en `routes/api.php`.
- Se agrego prueba de reutilizacion de membresia pendiente en `future`.
- PHPUnit sigue sin iniciar por el error local al abrir `coach/vendor/autoload.php`.

### Criterios de aceptacion

- Repetir la peticion devuelve la misma sesion abierta cuando corresponde.
- Si Stripe falla antes de crear checkout, no queda una membresia activa.
- Una sesion cancelada puede reintentarse sin crear una membresia duplicada.

## Checkpoint 5: Ajustar el webhook de Stripe

Estado: implementado; pruebas automaticas pendientes por el bloqueo local de PHPUnit.

### Trabajo

- En `checkout.session.completed`, cambiar `pending_payment` a `active` y marcar `paid`.
- Actualizar `starts_at`, `ends_at` y `next_renewal_at` con los datos reales de Stripe.
- Manejar `customer.subscription.updated`.
- Manejar `customer.subscription.deleted`.
- Manejar estados de pago fallido o vencido.
- Verificar que eventos repetidos sean seguros.

### Implementacion realizada

- Solo `active` y `trialing` convierten una membresia pendiente en `active` y `paid`.
- `incomplete` conserva `pending_payment` cuando el checkout inicial aun no termina.
- `past_due`, `unpaid` y `paused` pasan a `past_due`, por lo que no conservan acceso operativo.
- `incomplete_expired` pasa a `expired` y `customer.subscription.deleted` a `cancelled`.
- Se actualizan fechas de periodo, suscripcion, cuenta conectada y estado Stripe cuando el evento trae esos datos.
- Los eventos repetidos actualizan el mismo registro y no crean membresias ni pagos adicionales.
- Un estado Stripe desconocido no activa la membresia por defecto.

### Validacion realizada

- `php -l` paso en el webhook y su prueba.
- `git diff --check` paso; Git solo reporto normalizacion CRLF/LF en `routes/api.php`.
- Se agregaron pruebas para activacion, idempotencia, `past_due`, `incomplete` y cancelacion.
- PHPUnit no pudo iniciar porque el runner local no puede abrir `coach/vendor/autoload.php`.

### Criterios de aceptacion

- Solo un evento confirmado activa la membresia.
- Un evento repetido no duplica pagos ni modifica incorrectamente fechas.
- Una suscripcion cancelada no permanece activa indefinidamente.

## Checkpoint 6: Ajustar el contrato para la app movil

Estado: implementado; build movil correcto.

### Trabajo backend

- Exponer `access_state` o un indicador equivalente en el endpoint de membresias.
- Exponer la ultima membresia vencida de forma explicita.
- Exponer una renovacion pendiente si existe.
- Exponer el checkout pendiente reutilizable cuando corresponda.

### Trabajo Ionic

En `subscription-history.page.ts`:

- Separar `renewMembership()` de `buyFuture()`.
- Agregar `continuePendingPayment()`.
- Detectar membresia vencida.
- Evitar doble clic.
- Mostrar errores de Stripe y del coach.

### Implementacion realizada

- El endpoint `app/memberships` expone `access_state`, `can_access_service`, `access_message`, `pending_membership` y `expired_membership`.
- La pantalla móvil separa `Renovar membresia`, `Comprar futura` y `Continuar pago`.
- Una membresía vencida ya no se presenta como compra futura.
- Los reintentos de pago reutilizan el endpoint adecuado según exista o no una membresía activa.
- Stripe se abre con `@capacitor/browser` y la pantalla recarga al cerrar el navegador.
- Se evita el doble clic mientras se crea o retoma un checkout.
- Se diferencian visualmente la membresía vencida y el pago pendiente.

### Validacion realizada

- `npm run build` terminó correctamente; permanecen advertencias de presupuesto SCSS preexistentes.
- PHP lint y rutas backend correctos.
- PHPUnit continúa bloqueado por el runner local al abrir `coach/vendor/autoload.php`.
- Abrir Stripe con `Browser.open()`.
- Recargar membresias cuando el navegador de Stripe se cierre.

En `subscription-history.page.html`:

- Mostrar `Renovar membresia` cuando el atleta este vencido.
- Mantener `Comprar futura` solo para membresia activa.
- Mostrar `Continuar pago` para `pending_payment`.
- Diferenciar visualmente `expired`, `pending_payment`, `paid` y `past_due`.

### Criterios de aceptacion

- La pantalla no usa el texto `Comprar futura` para un atleta vencido.
- El atleta puede renovar sin cerrar sesion.
- El regreso desde Stripe actualiza la pantalla.
- La app no asume que el pago fue exitoso solo porque el navegador regreso.

## Checkpoint 7: URLs de retorno movil

Estado: implementado; build movil y rutas backend validados.

### Trabajo

- Definir una URL de exito compatible con la app.
- Definir una URL de cancelacion compatible con la app.
- Evitar que el atleta termine en una URL del panel del coach.
- Decidir entre deep link, universal link o retorno controlado al navegador.
- Mantener la confirmacion definitiva en el webhook.

### Implementacion realizada

- Stripe ahora usa una URL HTTPS intermedia del backend para éxito y cancelacion.
- El backend redirige al esquema nativo configurado `com.app33performance://subscription-history`.
- Android e iOS ya tienen registrado el esquema y la app ya escucha `appUrlOpen`.
- El listener navega a historial de membresias y guarda el resultado solo como contexto visual.
- La pantalla vuelve a consultar `app/memberships`; el webhook sigue siendo la unica fuente de verdad.
- El retorno filtra el `session_id` y nunca actualiza la membresia directamente.

### Validacion realizada

- `php -l` correcto en controller, servicio Stripe y rutas web.
- La ruta `stripe.membership.return` aparece registrada correctamente.
- `npm run build` terminó correctamente; permanecen advertencias de presupuesto SCSS preexistentes.
- Falta prueba real en Android/iOS con Stripe Checkout sandbox.

### Criterios de aceptacion

- Pago exitoso puede regresar a la app o a una pantalla entendible.
- Cancelacion regresa sin marcar pagada la membresia.
- La app muestra el estado real despues de refrescar el endpoint.

## Checkpoint 8: Pruebas y QA integral

Estado: QA tecnico parcial completado; sandbox Stripe y dispositivo iOS/Android pendientes.

### Casos backend

- Atleta activo.
- Atleta en gracia.
- Atleta vencido.
- Atleta sin membresia.
- Renovacion pendiente.
- Pago confirmado.
- Pago cancelado.
- Pago fallido.
- Plan manual.
- Plan Stripe.
- Coach sin Stripe habilitado.
- Webhook repetido.

### Casos moviles

- Abrir pantalla con membresia activa.
- Abrir pantalla con membresia vencida.
- Iniciar renovacion.
- Cancelar checkout.
- Completar checkout.
- Cerrar Stripe y volver a la app.
- Reanudar la app desde segundo plano.
- Pulsar varias veces el boton.
- Cerrar sesion estando vencido.

### Evidencia minima

- Respuestas JSON de membresias antes y despues del flujo.
- Evidencia del webhook recibido.
- Pruebas enfocadas backend.
- `npm run build` de la app movil.
- QA real en al menos un dispositivo o emulador.

### Ejecucion realizada

- PHP lint correcto en los archivos backend modificados y pruebas nuevas.
- Rutas de membresias y retorno Stripe registradas correctamente.
- `git diff --check` correcto; las advertencias observadas son normalizaciones CRLF/LF.
- `npm run build` de la app móvil terminó correctamente.
- PHPUnit no pudo iniciar porque el runner local no puede abrir `coach/vendor/autoload.php`.
- No se ejecutó un checkout Stripe sandbox ni un webhook real.
- No se hizo QA en dispositivo iOS/Android; queda pendiente validar Browser, deep link, 3DS, cancelacion y retorno.

### Hallazgo corregido durante la prueba local

- El login rechazaba con `membership_expired` antes de emitir el token, por lo que el atleta vencido no podia entrar a la pantalla de renovacion.
- El login ahora permite sesiones con estado `expired`, `pending_payment` o `no_membership`, devuelve el estado de acceso y la app navega directamente a historial.
- Las cuentas inactivas siguen bloqueadas.

### Resultado y siguiente decision

- El flujo Stripe queda listo para una ronda controlada de sandbox, pero no se declara listo para publicación.
- Antes de publicar iOS se debe decidir entre Apple In-App Purchase o una excepción documentada de Apple.
- La planeacion de In-App Purchase se hará como feature separado y no modifica todavía este flujo Stripe.

## Orden de ejecucion

1. Checkpoint 1: estados y bloqueo de acceso.
2. Checkpoint 2: endpoint separado de renovacion.
3. Checkpoint 3: disponibilidad correcta de planes.
4. Checkpoint 4: idempotencia y checkout pendiente.
5. Checkpoint 5: webhook.
6. Checkpoint 6: app movil.
7. Checkpoint 7: retorno movil.
8. Checkpoint 8: pruebas y QA.

## Primer slice ejecutable

El primer bloque autorizado recomendado es el Checkpoint 1:

- no cambia la UI;
- no abre nuevos pagos;
- corrige primero la frontera de acceso;
- permite probar que `pending_payment` nunca desbloquea al atleta;
- deja preparado el sistema para crear el endpoint de renovacion sin riesgo.

## Decisiones pendientes

- Si la renovacion debe iniciar siempre hoy o conservar alguna fecha de continuidad.
- Si el periodo de gracia permite acceso completo o solo renovacion.
- Si se usara una tabla separada de checkout o `ClientMembership` con `pending_payment`.
- Que URL/deep link se usara al regresar de Stripe.
- Si los planes manuales se ocultan o se muestran como `Contacta a tu coach`.
- Si el checkout debe permitir una sola renovacion pendiente por atleta y plan.

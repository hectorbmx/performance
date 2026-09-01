# Registro De Pago De Membresia: Idempotencia Y Loading UI

## Objetivo

Cerrar el riesgo de doble cobro o doble registro en la pantalla:

```text
/coach/memberships/{membership}/register-payment
```

Al finalizar, los dos flujos disponibles en esa pantalla deben ser resistentes a doble click y reintentos:

- Pago manual: boton `Registrar pago`.
- Pago Stripe: boton `Cobrar con Stripe`.

La proteccion visual debe mejorar la experiencia, pero la garantia principal debe estar en backend y base de datos.

## Hallazgos Verificados

### 1. Pago manual sin idempotencia real

Archivo:

```text
coach/app/Http/Controllers/Coach/ClientPaymentController.php
```

Metodo:

```text
store(Request $request, ClientMembership $membership)
```

Estado actual:

- Valida que la membresia pertenezca al coach.
- Revisa `billing_status === 'paid'` antes de crear el pago.
- Crea `ClientPayment`.
- Despues actualiza `ClientMembership` a `paid`.

Riesgo:

- Si llegan dos POST concurrentes antes de que el primer request actualice `billing_status`, ambos pueden crear un registro en `client_payments`.
- No hay `DB::transaction()`.
- No hay `lockForUpdate()`.
- No hay llave idempotente.
- No hay restriccion unica en DB que impida multiples pagos activos/completados para la misma membresia.

### 2. Vista sin loading bloqueante ni disabled de submit

Archivo:

```text
coach/resources/views/coach/client-payments/create.blade.php
```

Estado actual:

- El formulario manual tiene `id="paymentForm"`.
- El boton `Registrar pago` es un submit normal.
- El formulario Stripe es independiente.
- No existe overlay/modal de carga.
- No se deshabilitan botones al enviar.
- El JavaScript actual solo recalcula el monto final.

Riesgo:

- El usuario puede hacer doble click.
- El usuario no recibe feedback inmediato de que el submit esta en proceso.
- La pantalla no cumple la regla del proyecto para operaciones de escritura: mostrar carga, bloquear interaccion accidental y evitar doble submit.

### 3. Stripe Checkout puede generar multiples sesiones

Archivos:

```text
coach/app/Http/Controllers/Coach/ClientMembershipStripeCheckoutController.php
coach/app/Services/Billing/StripeConnectService.php
```

Estado actual:

- `ClientMembershipStripeCheckoutController::store()` valida ownership y estado.
- `StripeConnectService::createMembershipCheckout()` llama `CheckoutSession::create()`.
- Se guarda `stripe_checkout_session_id` despues de crear la sesion.

Riesgo:

- Doble click puede disparar dos llamadas a Stripe.
- No se pasa `idempotency_key` a Stripe.
- No se reutiliza una sesion existente.
- Solo hay un indice normal sobre `stripe_checkout_session_id`, no una regla que proteja el intento.

## Decision Tecnica

La solucion debe tener dos niveles:

1. Backend: garantia real ante concurrencia, reintentos y requests duplicados.
2. UI: feedback visible y bloqueo de interaccion accidental mientras el request esta en curso.

La UI no debe ser la unica proteccion. El navegador puede repetir requests, el usuario puede refrescar, o el request puede llegar duplicado por condiciones de red.

## Roadmap Con Checkpoints

### Checkpoint 1: Pago manual atomico y protegido por lock

Estado:

```text
Completado - 2026-08-31
```

Evidencia:

- `ClientPaymentController@store` ahora usa `DB::transaction()`.
- La membresia se recarga dentro de la transaccion con `lockForUpdate()`.
- El estado `billing_status` se revalida despues de adquirir el lock.
- El pago y la actualizacion de membresia ocurren en la misma transaccion.
- Si otro request ya marco la membresia como pagada, se redirige con el mensaje `Esta membresia ya esta pagada.`

Validacion ejecutada:

```text
php -l app/Http/Controllers/Coach/ClientPaymentController.php
php artisan route:list --path=coach/memberships
```

Cambios:

- Importar `Illuminate\Support\Facades\DB` en `ClientPaymentController`.
- Envolver la operacion de `store()` en `DB::transaction()`.
- Dentro de la transaccion, recargar la membresia con:

```php
ClientMembership::query()
    ->whereKey($membership->id)
    ->where('coach_id', auth()->id())
    ->lockForUpdate()
    ->firstOrFail();
```

- Revalidar dentro del lock que `billing_status` no sea `paid`.
- Crear `ClientPayment` solo despues de adquirir el lock.
- Actualizar la membresia a `paid` dentro de la misma transaccion.
- Devolver error controlado si otro request ya marco la membresia como pagada.

Criterios de aceptacion:

- Dos POST casi simultaneos no crean dos pagos para la misma membresia.
- El segundo request ve la membresia pagada despues de esperar el lock.
- El pago y el cambio de estado de la membresia se confirman o fallan juntos.
- No queda pago creado si falla la actualizacion de la membresia.

Validacion minima:

```text
php -l app/Http/Controllers/Coach/ClientPaymentController.php
php artisan route:list --path=coach/memberships
```

Validacion funcional recomendada:

- Preparar una membresia `unpaid`.
- Enviar dos POST consecutivos o concurrentes al endpoint `coach.client-payments.store`.
- Confirmar que existe un solo registro en `client_payments` para esa membresia.
- Confirmar que `client_memberships.billing_status = paid`.

### Checkpoint 2: Definir regla de base de datos para duplicados

Estado:

```text
Completado - 2026-08-31
```

Decision tomada:

- Se eligio la opcion flexible para permitir abonos/parciales en una etapa futura.
- No se agrego unique directo por `client_membership_id`.
- Se agrego `idempotency_key` nullable en `client_payments`.
- Se agrego unique por `coach_id` + `idempotency_key`.
- Se agrego `idempotency_key` a `$fillable` en `ClientPayment`.

Evidencia:

- Nueva migracion: `database/migrations/2026_08_31_000002_add_idempotency_key_to_client_payments_table.php`.
- Modelo actualizado: `app/Models/ClientPayment.php`.

Validacion ejecutada:

```text
php -l database/migrations/2026_08_31_000002_add_idempotency_key_to_client_payments_table.php
php -l app/Models/ClientPayment.php
php artisan migrate --pretend
php artisan migrate
php artisan migrate:status --path=database/migrations/2026_08_31_000002_add_idempotency_key_to_client_payments_table.php
```

Decision pendiente:

- Si la regla de negocio es "una membresia se paga una sola vez", agregar restriccion unica para evitar mas de un pago no eliminado por `client_membership_id`.
- Si despues se soportaran abonos/parciales, no usar unique directo por membresia; crear un modelo de pagos parciales y marcar un pago final o liquidacion.

Opcion recomendada para el modelo actual:

- Agregar migracion con una columna `idempotency_key` nullable en `client_payments`.
- Agregar indice unico por `coach_id` + `idempotency_key`.
- Usar una llave por intento de submit manual.
- Mantener el lock del Checkpoint 1 aunque exista idempotency key.

Alternativa si se confirma "un solo pago final por membresia":

- Agregar unique por `client_membership_id`.
- Considerar soft deletes: si la tabla usa `softDeletes`, validar si el motor permite una restriccion que ignore registros eliminados o si se debe manejar en aplicacion.

Criterios de aceptacion:

- La base de datos ayuda a bloquear duplicados, no solo el codigo.
- La regla elegida no bloquea un futuro flujo de abonos si ese flujo esta planeado.
- La migracion tiene rollback limpio.

Validacion minima:

```text
php -l database/migrations/<timestamp>_add_idempotency_to_client_payments_table.php
php artisan migrate --pretend
```

### Checkpoint 3: Idempotency key en pago manual

Estado:

```text
Completado - 2026-08-31
```

Evidencia:

- `ClientPaymentController@create` genera un UUID para el intento manual.
- La llave se guarda en sesion por `coach_id` + `client_membership_id`.
- `create.blade.php` envia la llave como `input type="hidden"` dentro de `paymentForm`.
- `ClientPaymentController@store` exige `idempotency_key` en la validacion.
- `store()` compara la llave recibida contra la llave guardada en sesion con `hash_equals`.
- `ClientPayment::create()` guarda `idempotency_key`.
- La llave de sesion se elimina despues del procesamiento.
- Se mantiene `DB::transaction()` + `lockForUpdate()` como proteccion principal ante concurrencia.

Validacion ejecutada:

```text
php -l app/Http/Controllers/Coach/ClientPaymentController.php
php artisan view:cache
php artisan view:clear
```

Cambios:

- Generar una llave idempotente al renderizar la pantalla de pago manual.
- Guardar esa llave en sesion asociada a la membresia y al coach.
- Enviar la llave como `input type="hidden"` dentro de `paymentForm`.
- Validar en `store()` que la llave exista y corresponda a esa membresia y coach.
- Guardar la llave en `client_payments.idempotency_key`.
- Si llega el mismo key de nuevo:
  - No crear otro pago.
  - Redirigir con mensaje claro de que el pago ya fue procesado o que la membresia ya esta pagada.

Criterios de aceptacion:

- Reenviar el mismo formulario no duplica pagos.
- Un key de una membresia no sirve para otra.
- Un key de otro coach no sirve para esta membresia.
- La experiencia del usuario termina en un estado comprensible, no en error tecnico.

Validacion minima:

```text
php -l app/Http/Controllers/Coach/ClientPaymentController.php
php artisan view:cache
php artisan view:clear
```

Validacion funcional recomendada:

- Capturar el hidden input del formulario.
- Enviar dos POST con la misma llave.
- Confirmar que solo existe un pago.
- Confirmar que el segundo POST no genera excepcion SQL visible al usuario.

### Checkpoint 4: Loading UI para ambos formularios

Estado:

```text
Completado - 2026-08-31
```

Evidencia:

- El boton `Registrar pago` ahora tiene identificador, texto de carga y estado `disabled`.
- El boton `Cobrar con Stripe` ahora tiene identificador, texto de carga y estado `disabled`.
- Se agrego overlay bloqueante `paymentLoadingOverlay`.
- El handler se ejecuta en `submit`.
- Antes de mostrar el overlay se valida `form.checkValidity()`.
- Si el mismo formulario intenta enviarse dos veces, el segundo submit se cancela con `event.preventDefault()`.

Validacion ejecutada:

```text
php artisan view:cache
php artisan view:clear
```

Cambios:

- Agregar estado visual de carga bloqueante en `create.blade.php`.
- El overlay debe aparecer solo cuando el formulario es valido y se va a enviar.
- Para el formulario manual:
  - Deshabilitar `Registrar pago`.
  - Cambiar texto a `Registrando...`.
  - Bloquear segundo submit.
- Para el formulario Stripe:
  - Deshabilitar `Cobrar con Stripe`.
  - Cambiar texto a `Preparando cobro...`.
  - Bloquear segundo submit.
- Evitar activar overlay si la validacion nativa del navegador falla.

Nota importante:

- No usar solo `onclick` en el boton para mostrar loading, porque puede dejar el overlay prendido si el navegador bloquea el submit por campos invalidos.
- El handler debe correr en `submit` y comprobar `form.checkValidity()` antes de mostrar el overlay.

Criterios de aceptacion:

- Con campos invalidos, no aparece overlay permanente.
- Con campos validos, el boton queda deshabilitado al primer submit.
- Un segundo click no dispara otra interaccion visible.
- El usuario ve que el sistema esta procesando.
- La misma regla aplica a Stripe y pago manual.

Validacion minima:

```text
php artisan view:cache
php artisan view:clear
```

Validacion visual recomendada:

- Abrir `/coach/memberships/7/register-payment`.
- Probar submit con campos invalidos.
- Probar submit valido y observar boton/overlay.
- Probar doble click rapido.

### Checkpoint 5: Stripe Checkout idempotente o reutilizable

Estado:

```text
Completado - 2026-08-31
```

Evidencia:

- `StripeConnectService::createMembershipCheckout()` ahora corre dentro de `DB::transaction()`.
- La membresia se recarga con `lockForUpdate()` antes de revisar/crear sesion Stripe.
- Si la membresia ya esta pagada dentro del lock, se detiene con error controlado.
- Si existe `stripe_checkout_session_id`, se intenta recuperar la sesion en Stripe.
- Si la sesion existente esta `open` y tiene `url`, se reutiliza y no se crea otra.
- Si no hay sesion abierta util, se crea una nueva `CheckoutSession`.
- La creacion de Stripe envia `idempotency_key`.
- La llave usada incluye el `client_membership_id` y la sesion anterior cuando exista, para permitir nuevo intento si la sesion anterior ya no esta abierta.

Validacion ejecutada:

```text
php -l app/Http/Controllers/Coach/ClientMembershipStripeCheckoutController.php
php -l app/Services/Billing/StripeConnectService.php
php artisan route:list --path=coach/memberships
```

Nota de QA:

- No se ejecuto una llamada real a Stripe en esta fase.
- La prueba funcional recomendada sigue pendiente para confirmar reutilizacion contra Stripe real o sandbox.

Cambios:

- Definir si se reutilizara una sesion Stripe existente mientras la membresia siga `unpaid`.
- Si se reutiliza:
  - Guardar tambien expiracion o recuperar sesion desde Stripe para validar si sigue activa.
  - Redirigir a la URL existente cuando aplique.
- Si se crea un nuevo intento:
  - Generar idempotency key local por intento.
  - Pasar esa key en las opciones de Stripe:

```php
CheckoutSession::create($params, [
    'stripe_account' => $profile->stripe_account_id,
    'idempotency_key' => $key,
]);
```

- Evitar que doble click cree dos sesiones Checkout para la misma membresia.

Criterios de aceptacion:

- Doble click en `Cobrar con Stripe` no genera multiples sesiones Stripe utiles para la misma membresia.
- La membresia conserva trazabilidad de la sesion usada.
- Si Stripe falla, el usuario vuelve con error visible y puede reintentar.
- Si el plan es manual, no se intenta crear Checkout.

Validacion minima:

```text
php -l app/Http/Controllers/Coach/ClientMembershipStripeCheckoutController.php
php -l app/Services/Billing/StripeConnectService.php
```

Validacion funcional recomendada:

- Simular doble POST al endpoint `coach.client-memberships.stripe-checkout`.
- Confirmar que no quedan multiples sesiones activas para una misma membresia.
- Confirmar que el webhook sigue actualizando `billing_status`, `paid_at`, `stripe_subscription_id` y `stripe_checkout_session_id`.

### Checkpoint 6: Pruebas automatizadas focalizadas

Estado:

```text
Completado - 2026-08-31
```

Evidencia:

- Se agrego `tests/Feature/ClientPaymentTest.php`.
- Cubre que un coach no pueda pagar una membresia de otro coach.
- Cubre que una membresia ya pagada no cree otro pago.
- Cubre que un submit valido cree el pago y marque la membresia como pagada.
- Cubre que reenviar la misma `idempotency_key` no cree un pago duplicado.
- Los tests usan datos locales y no dependen de Stripe real.

Validacion ejecutada:

```text
php -l tests/Feature/ClientPaymentTest.php
php artisan test --filter=ClientPayment --do-not-cache-result
```

Nota:

- En el primer intento, `php artisan test --filter=ClientPayment` fallo con 419 por CSRF antes de llegar al flujo de negocio.
- El test final desactiva solo `VerifyCsrfToken` para validar la logica de autorizacion, idempotencia y persistencia del controlador.
- Despues se detecto que `phpunit.xml` no tenia una base de testing aislada y `RefreshDatabase` corrio contra MySQL `coach`; se corrigio para usar `DB_DATABASE=coach_testing`.
- PHPUnit sigue mostrando warning de configuracion XML deprecada, pero la suite pasa.

Cambios:

- Agregar tests feature para pago manual.
- Cubrir:
  - coach no puede pagar membresia de otro coach.
  - membresia ya pagada no crea otro pago.
  - doble submit con misma idempotency key no crea duplicado.
  - submit valido crea pago y marca membresia como pagada.
- Si se implementa lock, agregar prueba practica de doble ejecucion hasta donde Laravel/PHPUnit lo permita.

Criterios de aceptacion:

- El flujo manual queda cubierto por tests de regresion.
- Los tests no dependen de Stripe real.
- Para Stripe, mockear servicio o validar controlador con fake del servicio.

Validacion minima:

```text
php artisan test --filter=ClientPayment
```

### Checkpoint 7: QA final de ruta y contrato

Estado:

```text
Pendiente
```

Validacion:

```text
php -l app/Http/Controllers/Coach/ClientPaymentController.php
php -l app/Http/Controllers/Coach/ClientMembershipStripeCheckoutController.php
php -l app/Services/Billing/StripeConnectService.php
php artisan route:list --path=coach/memberships
php artisan view:cache
php artisan view:clear
php artisan test --filter=ClientPayment
```

Checklist manual:

- Entrar a `/coach/memberships/7/register-payment`.
- Confirmar que la pantalla carga solo si la membresia pertenece al coach autenticado.
- Confirmar que una membresia pagada redirige y no muestra formulario.
- Confirmar que `Registrar pago` muestra carga, deshabilita el boton y no permite doble click.
- Confirmar que el pago manual crea un solo `client_payments`.
- Confirmar que `Cobrar con Stripe` muestra carga y no permite doble click.
- Confirmar que errores de Stripe se muestran en la pantalla.

## Orden De Ejecucion Recomendado

1. Implementar Checkpoint 1.
2. Implementar Checkpoint 4 para cubrir feedback visual inmediato.
3. Resolver decision del Checkpoint 2.
4. Implementar Checkpoint 3 si se decide usar idempotency key local.
5. Implementar Checkpoint 5 para Stripe.
6. Agregar pruebas del Checkpoint 6.
7. Ejecutar QA final del Checkpoint 7.

## Definition Of Done

La tarea se considera cerrada cuando:

- El pago manual es atomico.
- El doble submit manual no crea pagos duplicados.
- La pantalla muestra loading bloqueante en ambos formularios.
- Stripe no genera sesiones duplicadas por doble click.
- Existen validaciones automatizadas o, si no se agregan, queda documentado el motivo.
- Los comandos de validacion pasan.
- Este archivo se actualiza marcando cada checkpoint completado con fecha, evidencia y comandos ejecutados.

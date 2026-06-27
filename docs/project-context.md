# Contexto General Del Proyecto

## Producto

Training Flow es una plataforma SaaS para coaches. El sistema tiene tres capas principales:

- Admin master: administra coaches/tenants, planes, suscripciones y pagos de uso de la plataforma.
- Coach tenant: administra su operacion diaria, atletas/clientes, grupos, planes propios, membresias de atletas, entrenamientos y biblioteca.
- App movil atleta: permite al atleta consultar entrenamientos, membresia, perfil, metricas, notificaciones y progreso.

## Modelo De Negocio

El admin master da de alta coaches. Los coaches pagan una suscripcion para usar el sistema.

La suscripcion del coach puede manejarse de dos formas:

- Manual: el admin registra la suscripcion y posteriormente asienta pagos manuales.
- Stripe: el sistema genera checkout/suscripcion con Stripe cuando el plan lo permita.

El coach, a su vez, puede cobrar a sus atletas/clientes usando planes propios. Ese flujo es distinto al cobro del SaaS al coach.

## Terminologia Actual

- Coach: usuario Laravel con role `coach`.
- Coach profile: datos operativos del coach en `coach_profiles`.
- Atleta/cliente/customer: en backend se representa principalmente como `clients` y relaciones del coach.
- Suscripcion del coach: `coach_subscriptions`.
- Pago del coach al SaaS: `payments`.
- Membresia del atleta al coach: `client_memberships`.
- Pago del atleta al coach: `client_payments`.

## Regla De Acceso Del Coach

El estado visible del perfil del coach no es suficiente para determinar acceso real.

Actualmente existen dos conceptos:

- Estado operativo del coach: `coach_profiles.status` (`active`, `inactive`, `trial`, `suspended`, `cancelled`).
- Estado comercial/de acceso: derivado de la ultima `coach_subscriptions` y sus campos `billing_status`, `grace_until`, `starts_at`, `ends_at` y `status`.

Regla vigente observada en login y middleware:

- Si no hay suscripcion, bloquear acceso.
- Si `billing_status = paid`, permitir acceso.
- Si `billing_status = unpaid` y `grace_until` existe y no ha vencido, permitir acceso.
- En cualquier otro caso, bloquear acceso.

Esto implica que un coach puede verse como `active` en el index administrativo, pero estar bloqueado comercialmente por una suscripcion `unpaid` con gracia vencida.

## Issue Actual: Coaches Activos Pero Bloqueados

Caso detectado:

- En `/admin/coaches`, el coach David aparece con perfil `active`.
- En login, el sistema lo bloquea porque su suscripcion esta `unpaid` y la gracia vencio.
- En `/admin/coaches/{coach}/edit`, no hay contexto suficiente de suscripcion, pagos o acciones de cobro.
- En `/admin/subscriptions`, si se ve el motivo real: `UNPAID` y `GRACIA VENCIDA`.

Problema de producto:

- El admin necesita ver el estado de acceso real desde el index de coaches.
- El edit del coach debe mostrar suscripcion actual, historial de pagos y acciones para resolver deuda.

## Direccion De Correccion

- El index de coaches debe separar visualmente:
  - Estado operativo del perfil.
  - Estado de acceso/comercial calculado desde la suscripcion.
- El edit de coach debe evolucionar a una vista de gestion del tenant:
  - Datos del coach.
  - Suscripcion actual.
  - Historial de pagos.
  - Acciones: registrar pago manual, crear nueva suscripcion, generar link de pago Stripe cuando aplique.
- La fuente de verdad para bloqueo debe quedar centralizada para evitar reglas duplicadas entre login, middleware y UI.

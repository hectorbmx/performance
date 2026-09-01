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


# agregar configuracion bancaria para el coach (tareas para la proxima sesion cuando se renueve el plan 30 jun)
- Metodos de pago, 
- Revisa stripe en tenant para los cobros
-

## Regla De Feedback En Operaciones De Escritura

Toda accion que ejecute una operacion contra base de datos debe dar feedback visible al usuario durante y despues de la operacion.

Aplica como minimo a acciones `store`, `update`, `patch`, `delete`, copiado/clonado, asignaciones y cualquier submit que cree, modifique o elimine registros.

Regla UI:

- Al iniciar la operacion, mostrar un modal/overlay de carga que bloquee interaccion accidental.
- El overlay debe aplicar blur o atenuacion sobre la pantalla y mostrar el icono/logo de la marca cuando la vista tenga acceso al asset.
- Al terminar correctamente, mostrar toast o mensaje visible de exito.
- Al fallar, ocultar el loading y mostrar error visible con el mensaje disponible.
- Evitar submits dobles deshabilitando botones mientras la operacion esta en curso.
- En pantallas de listado o calendario, refrescar el estado visible despues de una operacion exitosa cuando aplique.

Esta regla aplica tanto en Laravel Blade como en Ionic/Angular. Si una pantalla todavia no tiene este patron, debe agregarse al tocar cualquier flujo de escritura de esa pantalla.

## Regla De Arquitectura Y Reuso

Todo cambio nuevo debe seguir principios SOLID y favorecer procesos eficientes, modulos existentes y contratos reutilizables antes de agregar logica duplicada en controllers, componentes o vistas.

Reglas obligatorias:

- Revisar si ya existe un servicio, helper, metodo compartido, modelo, DTO o componente que resuelva parte del flujo.
- Reusar o extender el modulo existente cuando mantenga responsabilidades claras y reduzca duplicacion.
- Evitar que controllers Laravel, componentes Ionic o vistas Blade concentren reglas de negocio que pertenecen a servicios o modulos compartidos.
- Si no existe un modulo adecuado y la implementacion empieza a requerir una nueva responsabilidad transversal, hacer una pausa antes de codificar y avisar al usuario para decidir si conviene separarla como modulo independiente.
- No crear abstracciones nuevas solo por forma; crearlas cuando reduzcan complejidad real, eviten contratos paralelos o permitan validar una regla en un solo punto.

## Regla Critica De Proteccion De Datos

Bajo ningun motivo se debe borrar, refrescar, truncar, resetear, recrear o migrar destructivamente la base de datos local/principal del proyecto sin autorizacion explicita del usuario y sin backup confirmado.

Reglas obligatorias:

- Antes de correr tests con `RefreshDatabase`, `DatabaseMigrations`, `migrate:fresh`, `migrate:refresh`, `db:wipe`, seeders destructivos o comandos equivalentes, verificar la conexion y el nombre exacto de la base de datos.
- PHPUnit debe usar una base aislada de pruebas, por ejemplo `coach_testing`, nunca la base local/principal `coach`.
- Si la base aislada no existe, crearla o detenerse antes de ejecutar la suite.
- No asumir que `APP_ENV=testing` protege la base si `phpunit.xml` no define una conexion/base de testing separada.
- Si un comando puede destruir datos, pedir confirmacion explicita antes de ejecutarlo.


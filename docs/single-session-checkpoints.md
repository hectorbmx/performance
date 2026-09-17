# Roadmap: Sesion Unica Por Usuario API

## Objetivo

Permitir solo una sesion activa por usuario en la app movil.

Alcance aprobado:

- Atleta autenticado como `UserApp`.
- Coach autenticado desde la app movil como `User`.
- Reutilizar el mayor codigo posible.
- Mantener principios SOLID: controllers delgados, reglas en servicios reutilizables y contratos estables.

Fuera de alcance inicial:

- Limitar sesiones web del panel Laravel.
- Cambiar flujos de login Blade.
- Cambiar reglas comerciales de membresia o suscripcion.

## Estado Actual

La app movil usa tokens Sanctum como sesion API.

Atleta:

- `Api/V1/App/AuthController::login()` valida credenciales y crea un token nuevo con `createToken('app')`.
- `Api/V1/App/AuthController::logout()` revoca solo el token actual con `currentAccessToken()->delete()`.

Coach en app movil:

- `Api/V1/Coach/AuthController::login()` valida credenciales y crea un token nuevo con `createToken('coach')`.
- `Api/V1/Coach/AuthController::logout()` revoca solo el token actual.

Frontend Ionic:

- `ApiService` guarda el token en `Preferences` o `sessionStorage`.
- `AuthGuard` solo valida que exista un token local.
- No hay manejo global de `401 Unauthorized` para limpiar sesion local cuando el token fue revocado en backend.

Dispositivos push:

- `registerDevice()` reutiliza `user_devices`.
- Actualmente mantiene hasta 2 devices activos por usuario.
- Esta politica no controla sesiones; solo afecta entrega de notificaciones.

## Decision Tecnica

Crear una capa reutilizable para administrar tokens Sanctum de sesion API.

No insertar `tokens()->delete()` directamente en cada controller como regla dispersa. La politica debe vivir en un servicio dedicado, para que atleta y coach compartan el mismo comportamiento.

Servicio propuesto:

`App\Services\Auth\SingleSessionTokenService`

Responsabilidades:

- Revocar tokens anteriores del usuario autenticable.
- Crear el nuevo token de sesion.
- Revocar el token actual en logout, si se decide centralizarlo.
- Mantener la politica de sesion unica en un solo punto.

Contrato sugerido:

```php
public function issueSingleToken(Authenticatable $user, string $tokenName): string
public function revokeCurrentToken(Authenticatable $user): void
public function revokeAllTokens(Authenticatable $user): void
```

Nota de implementacion:

- Verificar el tipo exacto aceptado por `HasApiTokens` y usar un type-hint compatible.
- Preservar el payload actual de login para no romper la app.
- La revocacion debe ejecutarse antes de emitir el nuevo token.

## Politica Esperada

Cuando un usuario inicia sesion en un segundo dispositivo:

- El backend revoca todos sus tokens Sanctum anteriores.
- El backend emite un token nuevo.
- El dispositivo anterior conserva temporalmente un token local, pero ese token ya no es valido.
- En la siguiente llamada API desde el dispositivo anterior, el backend responde `401`.
- La app detecta el `401`, limpia la sesion local y redirige a login.

Mensaje UX sugerido:

`Tu sesion se cerro porque se inicio sesion en otro dispositivo.`

## Checkpoint 1: Auditoria Y Contrato

Estado: pendiente.

Tareas:

- Confirmar rutas de login/logout API de atleta.
- Confirmar rutas de login/logout API de coach movil.
- Confirmar que ambos modelos usan `Laravel\Sanctum\HasApiTokens`.
- Confirmar que el panel web no entra en alcance.
- Confirmar mensajes esperados para sesion revocada.
- Registrar en este documento cualquier ruta o controlador adicional que emita tokens.

Criterio de salida:

- Lista completa de puntos donde se crean o revocan tokens.
- Decision confirmada de que el alcance inicial aplica solo a API movil.

## Checkpoint 2: Servicio Backend Reutilizable

Estado: pendiente.

Tareas:

- Crear `app/Services/Auth/SingleSessionTokenService.php`.
- Implementar emision de token unico.
- Implementar revocacion del token actual.
- Cubrir el caso en que no exista token actual sin generar error.
- Evitar dependencias de controller, request o rutas dentro del servicio.

Criterio de salida:

- El servicio puede ser usado por cualquier modelo con tokens Sanctum.
- No hay logica de sesion unica duplicada entre controllers.

## Checkpoint 3: Integracion En Login/Logout API

Estado: pendiente.

Tareas:

- Inyectar `SingleSessionTokenService` en `Api/V1/App/AuthController::login()`.
- Reemplazar `createToken('app')` por el servicio.
- Inyectar `SingleSessionTokenService` en `Api/V1/Coach/AuthController::login()`.
- Reemplazar `createToken('coach')` por el servicio.
- Opcionalmente mover logout de ambos controllers al servicio.
- Mantener respuestas JSON actuales.

Criterio de salida:

- Login de atleta revoca tokens previos y devuelve el mismo contrato.
- Login de coach movil revoca tokens previos y devuelve el mismo contrato.
- Logout normal sigue revocando la sesion actual.

## Checkpoint 4: Manejo Frontend De Token Revocado

Estado: pendiente.

Tareas:

- Agregar manejo centralizado de `401` en `ApiService.normalizeError()`.
- Emitir un evento global, por ejemplo `app:session-revoked`.
- Agregar en `AuthService` un metodo de limpieza local sin llamar al backend, por ejemplo `clearLocalSession()`.
- Reutilizar esa limpieza desde `logout()` para evitar duplicacion.
- Escuchar el evento en `AppComponent`.
- Redirigir a `/login` con `replaceUrl: true`.
- Mostrar toast claro al usuario.

Criterio de salida:

- Un token revocado no deja la app en estado autenticado local.
- La limpieza local se reutiliza y no depende de que `/logout` responda correctamente.
- No se rompe el flujo existente de membresia vencida.

## Checkpoint 5: Politica De Dispositivos Push

Estado: pendiente.

Tareas:

- Decidir si `user_devices` debe alinearse a 1 device activo por usuario.
- Si se alinea, cambiar `registerDevice()` para conservar solo el device mas reciente.
- Confirmar impacto en FCM: un usuario con sesion nueva debe recibir notificaciones solo en el device activo.
- Mantener upsert por `token_hash`.

Criterio de salida:

- La politica de devices queda documentada y consistente con sesion unica.
- No se rompe el registro de token FCM.

## Checkpoint 6: Validacion

Estado: pendiente.

Validaciones backend:

- Login A atleta crea token A.
- Login B atleta crea token B y revoca token A.
- Token A recibe `401` al llamar una ruta protegida.
- Token B sigue funcionando.
- Logout con token B funciona.
- Repetir el mismo flujo con coach movil.

Validaciones frontend:

- App con token revocado recibe `401`.
- La app limpia token y datos locales.
- La app redirige a `/login`.
- La app muestra mensaje de sesion cerrada por otro dispositivo.
- Login normal sigue funcionando.

Validaciones de no regresion:

- Membresia vencida sigue usando `403 membership_expired`.
- Login con credenciales invalidas sigue usando `422`.
- Logout normal no muestra mensaje de sesion revocada.

## Riesgos Y Cuidados

- Revocar todos los tokens del modelo `User` tambien afecta otros tokens API del coach si existieran con otro uso futuro.
- Si en el futuro se agregan integraciones externas con Sanctum, convendra usar nombres o abilities para separar tokens de sesion movil de tokens de integracion.
- El panel web usa sesion Laravel, no tokens Sanctum; limitarlo requiere otro diseno.
- El dispositivo anterior no se cerrara en tiempo real; se cerrara en su siguiente request o al refrescar estado.

## Regla De Implementacion

- Antes de tocar codigo, revisar el diff local y preservar cambios no relacionados.
- Mantener controllers como orquestadores.
- Reusar `AuthService.logout()` y extraer limpieza local comun en frontend.
- No cambiar payloads de login salvo que el checkpoint lo pida explicitamente.
- No tocar migraciones destructivas ni base de datos para validar este flujo.

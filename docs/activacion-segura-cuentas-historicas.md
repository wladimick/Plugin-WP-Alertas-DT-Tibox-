# Activación segura de cuentas históricas

**Versión:** 0.3.1  
**Estado:** implementado, pendiente de prueba en staging.

## Problema resuelto

Antes de esta versión, un suscriptor histórico podía intentar registrarse usando únicamente un correo ya existente. Eso no demuestra propiedad de la casilla y podía permitir una vinculación indebida.

## Flujo implementado

1. El suscriptor visita `/activar-cuenta-alertas-dt/`.
2. Ingresa su correo.
3. La respuesta pública es siempre genérica.
4. Si el correo corresponde a un suscriptor sin usuario, se genera un token aleatorio de 32 bytes.
5. Solo se almacena `SHA-256(token)`.
6. El token se envía al correo registrado.
7. Vence en 60 minutos y permite un uso.
8. El usuario define una contraseña de al menos 10 caracteres.
9. Se crea el usuario WordPress con rol `alertas_dt_customer`.
10. Se vincula el `wp_user_id` a la fila histórica.
11. Se inicia la prueba de 15 días.
12. Se invalidan todos los enlaces pendientes del suscriptor.

## Controles de seguridad

- Nonce en solicitud y finalización.
- Rate limit de cinco minutos por correo/IP.
- Tokens criptográficos, no predecibles.
- Hash en base; nunca token en texto plano.
- Caducidad y uso único.
- Email parcialmente enmascarado en pantalla.
- Registro normal interceptado cuando el correo ya pertenece a un histórico.
- No se cambian roles ni contraseñas de usuarios WordPress preexistentes.
- Si falla la vinculación, el usuario recién creado se revierte.
- Eventos de auditoría sin token ni contraseña.

## Tabla

```text
wp_alertas_dt_activation_tokens
```

Campos:

- `subscriber_id`
- `token_hash`
- `expires_at`
- `used_at`
- `requested_ip_hash`
- `created_at`

## Pruebas obligatorias en staging

- Respuesta genérica para correo existente e inexistente.
- Recepción del correo real.
- Token no visible en la base.
- Reemplazo de un enlace anterior.
- Expiración después de 60 minutos.
- Imposibilidad de reutilizar el enlace.
- Registro normal bloqueado para históricos no vinculados.
- Conservación del mismo `subscriber_id`.
- Inicio de prueba de 15 días.
- Login posterior y recuperación de contraseña.
- Auditoría de solicitud, fallo de correo y activación.

## Pendientes

- Definir texto comercial definitivo del correo.
- Definir si la prueba de históricos comienza al activar o en una fecha común.
- Crear campaña de invitación masiva con control de lotes.
- Añadir verificación de correo para registros nuevos.

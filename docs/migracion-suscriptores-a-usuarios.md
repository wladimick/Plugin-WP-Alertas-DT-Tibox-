# Migración de suscriptores a usuarios WordPress

## Objetivo

Vincular los suscriptores históricos con usuarios WordPress sin perder consentimiento, teléfono, fechas ni sincronización con la aplicación Python.

## Estrategia de transición

1. La tabla existente se conserva.
2. Se agrega `wp_user_id` sin eliminar datos.
3. Los suscriptores sin usuario siguen siendo elegibles temporalmente.
4. El suscriptor solicita un enlace en `/activar-cuenta-alertas-dt/`.
5. WordPress envía el enlace únicamente al correo histórico registrado.
6. Al completar el enlace se reutiliza la misma fila y se vincula el nuevo usuario.
7. Desde ese momento se aplica la prueba/suscripción del portal.

## Flujo seguro implementado en 0.3.1

1. El suscriptor ingresa su correo en `[alertas_dt_activate_account]`.
2. La respuesta pública es genérica para no revelar si el correo existe.
3. WordPress genera 32 bytes aleatorios y envía el token únicamente por correo.
4. En la base se almacena solo `SHA-256(token)`.
5. El enlace vence en 60 minutos.
6. Una nueva solicitud invalida enlaces anteriores del mismo suscriptor.
7. El enlace permite definir una contraseña de al menos 10 caracteres.
8. Se crea un usuario WordPress con rol `alertas_dt_customer`.
9. Se conserva el mismo `subscriber_id`, nombre, teléfono y consentimientos.
10. Se inicia la prueba de 15 días.
11. El token y todos sus enlaces hermanos quedan invalidados.
12. Se registra el evento `historical_account_activated`.

## Protección contra apropiación de cuentas

El formulario de registro normal intercepta correos que ya pertenecen a un suscriptor histórico sin usuario y los dirige al flujo de activación segura. Por tanto, conocer una dirección de correo no basta para vincularla: es necesario abrir el enlace enviado a esa casilla.

Si ya existe un usuario WordPress con el correo, el flujo no modifica su contraseña ni sus roles; dirige a inicio de sesión o recuperación.

## Tabla de tokens

`wp_alertas_dt_activation_tokens` contiene:

- `subscriber_id`;
- hash del token;
- vencimiento;
- fecha de uso;
- hash de IP solicitante;
- fecha de creación.

No guarda tokens en texto plano. Los registros usados o vencidos se eliminan después del período de retención.

## Controles

- Nonces en solicitud y finalización.
- Rate limit de cinco minutos por combinación correo/IP.
- Token criptográfico de un solo uso.
- Caducidad de 60 minutos.
- Contraseña nunca enviada por correo.
- Email en pantalla parcialmente enmascarado.
- Eventos sin token, contraseña ni API keys.

## Reglas pendientes

- Verificación de correo para registros completamente nuevos.
- Cambio de correo con doble confirmación.
- Política para clientes que ya tengan otro rol WordPress.
- Comunicación masiva invitando a activar cuentas.
- Definir si la prueba de históricos comienza siempre al activar o si habrá una fecha comercial común.

## Checklist de staging

- [ ] Respaldar la base WordPress.
- [ ] Actualizar el plugin a 0.3.1.
- [ ] Confirmar creación de `wp_alertas_dt_activation_tokens`.
- [ ] Confirmar que los suscriptores históricos siguen visibles.
- [ ] Solicitar enlace para un correo inexistente y verificar respuesta genérica.
- [ ] Solicitar enlace para un histórico y confirmar recepción.
- [ ] Confirmar que la base no contiene el token en texto plano.
- [ ] Confirmar que un enlace anterior queda inválido al solicitar otro.
- [ ] Confirmar caducidad y uso único.
- [ ] Crear contraseña y vincular el histórico.
- [ ] Confirmar que no se duplicó la fila.
- [ ] Confirmar prueba de 15 días.
- [ ] Confirmar que el registro normal no permite apropiarse de un histórico.
- [ ] Confirmar REST heredado.
- [ ] Confirmar `eligible_only=true`.
- [ ] Revisar acceso a wp-admin del rol cliente.
- [ ] Revisar recuperación de contraseña.
- [ ] Probar cancelación y opt-out.

# Migración de suscriptores a usuarios WordPress

## Objetivo

Vincular los suscriptores históricos con usuarios WordPress sin perder consentimiento, teléfono, fechas ni sincronización con la aplicación Python.

## Estrategia de transición

1. La tabla existente se conserva.
2. Se agrega `wp_user_id` sin eliminar datos.
3. Los suscriptores sin usuario siguen siendo elegibles temporalmente.
4. Cuando una persona crea una cuenta con un correo existente, se reutiliza el registro y se vincula al nuevo usuario.
5. Desde ese momento se aplica la prueba/suscripción del portal.

## Primera entrega implementada

- Un correo sin usuario WordPress puede registrarse en el portal.
- Se mantiene el mismo `subscriber_id`.
- Se copian nombre, teléfono y consentimiento.
- Se crea una prueba de 15 días.
- No se genera una segunda fila para el mismo correo.

## Riesgo pendiente

En esta primera entrega no existe verificación de propiedad del correo antes de vincular un suscriptor histórico. Por esa razón, la migración masiva no debe habilitarse en producción hasta incorporar un flujo de activación seguro.

## Flujo definitivo recomendado

1. El suscriptor solicita `Activar mi cuenta`.
2. WordPress genera un token aleatorio de un solo uso.
3. Solo se almacena el hash del token y su vencimiento.
4. Se envía un enlace al correo ya registrado.
5. El enlace permite definir contraseña.
6. Se crea/vincula el usuario.
7. El token queda invalidado.
8. Se registra el evento de activación.

## Reglas

- Nunca enviar contraseñas por correo.
- Nunca crear contraseñas predecibles.
- El enlace debe caducar.
- Un token solo puede utilizarse una vez.
- El cambio de correo requiere una verificación independiente.
- Un correo ya vinculado debe dirigir a login/recuperación.

## Checklist de staging

- [ ] Respaldar la base WordPress.
- [ ] Actualizar el plugin a 0.3.0.
- [ ] Confirmar creación de tablas y columnas.
- [ ] Confirmar que los suscriptores históricos siguen visibles.
- [ ] Crear una cuenta con correo nuevo.
- [ ] Vincular un correo histórico de prueba.
- [ ] Confirmar que no se duplicó la fila.
- [ ] Confirmar prueba de 15 días.
- [ ] Confirmar REST heredado.
- [ ] Confirmar `eligible_only=true`.
- [ ] Revisar acceso a wp-admin del rol cliente.
- [ ] Revisar recuperación de contraseña.
- [ ] Probar cancelación y opt-out.

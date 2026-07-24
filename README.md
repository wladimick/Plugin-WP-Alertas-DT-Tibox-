# Alertas DT + SII Portal

Plugin WordPress de External Group para capturar suscriptores, administrar cuentas de clientes y sincronizar usuarios elegibles con la aplicación Python Alertas DT.

## Versión de esta rama

`0.3.3` — portal de clientes con activación segura, páginas automáticas y correcciones detectadas en staging.

## Funcionalidades

- Formulario heredado `[alertas_dt_form]`.
- Registro `[alertas_dt_register]`.
- Activación segura de cuenta existente `[alertas_dt_activate_account]`.
- Inicio de sesión `[alertas_dt_login]`.
- Mi cuenta `[alertas_dt_account]`.
- Páginas preparadas para contratación, resultado de pago y recuperación.
- Rol `alertas_dt_customer` sin acceso a `wp-admin`.
- Prueba gratuita de 15 días.
- Preferencias de email y WhatsApp.
- Término programado al finalizar el período.
- Modelo de pagos y auditoría.
- Pago anual simulado solo fuera de producción.
- API REST compatible con la aplicación Python.

## Ajustes de QA 0.3.3

- Una cuenta administradora ya no puede recibir una prueba o suscripción por visitar Mi cuenta.
- Las acciones de perfil, notificaciones, baja y pago simulado exigen el rol de cliente.
- Los usuarios WordPress vinculados con un rol distinto de cliente quedan excluidos de la elegibilidad REST.
- Un cliente autenticado que visita `/alertas-dt/`, crear cuenta o ingresar es enviado directamente a Mi cuenta.
- Durante la prueba aparece el botón `Contratar plan anual`.
- Los botones principales refuerzan el verde de External Group frente a estilos globales del tema.

## Páginas automáticas

Al activar o actualizar el plugin se crean únicamente las páginas que falten. Las páginas existentes se reutilizan y conservan su ID. Si una página anterior del plugin ya existe, se mueve al nuevo árbol sin duplicarla.

```text
/alertas-dt/
/alertas-dt/crear-cuenta/
/alertas-dt/activar-cuenta/
/alertas-dt/ingresar/
/alertas-dt/mi-cuenta/
/alertas-dt/contratar/
/alertas-dt/resultado-pago/
/alertas-dt/recuperar-contrasena/
```

Las URLs antiguas del prototipo redirigen hacia las nuevas:

```text
/registro-alertas-dt/
/activar-cuenta-alertas-dt/
/ingresar-alertas-dt/
/mi-cuenta-alertas-dt/
```

El plugin no reemplaza contenido personalizado de una página existente. Solo establece el shortcode cuando la página está vacía o contiene exclusivamente un shortcode propio de Alertas DT.

## Activación de suscriptores históricos

Los suscriptores existentes no pueden vincularse escribiendo solamente su correo en el registro normal. Deben demostrar acceso a esa casilla:

1. Solicitan un enlace en `/alertas-dt/activar-cuenta/`.
2. WordPress envía un token de un solo uso al correo registrado.
3. Solo se almacena el hash SHA-256 del token.
4. El enlace vence en 60 minutos.
5. El cliente define su contraseña.
6. El usuario se vincula a la fila histórica sin duplicarla.
7. Comienza la prueba de 15 días.

La respuesta pública es siempre genérica para no revelar qué correos están registrados. El diseño técnico completo está en `docs/activacion-segura-cuentas-historicas.md`.

## Instalación en staging

1. Respaldar la base de datos.
2. Subir la carpeta `alertas-dt-bridge` a `wp-content/plugins/`.
3. Activar o actualizar el plugin.
4. Visitar **Alertas DT + SII** en el administrador.
5. Confirmar la estructura automática bajo `/alertas-dt/`.
6. Confirmar que los suscriptores existentes siguen visibles.
7. Confirmar la tabla `wp_alertas_dt_activation_tokens`.
8. Probar con un usuario `Cliente Alertas DT`, no con la cuenta administradora.

La actualización ejecuta `dbDelta()` y no elimina las filas históricas.

## API

```text
GET /wp-json/alertas-dt/v1/health
GET /wp-json/alertas-dt/v1/subscribers
GET /wp-json/alertas-dt/v1/subscribers?eligible_only=true
POST /wp-json/alertas-dt/v1/subscribers/synced
```

Los endpoints privados requieren el token del plugin mediante Bearer o `X-Alertas-DT-Token`.

## Pago simulado

El simulador se habilita por defecto únicamente cuando `wp_get_environment_type()` no es `production`.

Para staging se puede definir en `wp-config.php`:

```php
define( 'WP_ENVIRONMENT_TYPE', 'staging' );
```

El monto de `$100 CLP` es referencial y no representa el precio comercial final.

## No incluido todavía

- Webpay Plus real.
- Oneclick.
- Lioren.
- Verificación de correo para registros completamente nuevos.
- Campaña masiva para invitar a activar cuentas históricas.
- Cambios en la aplicación Python para consumir `eligible_only=true`.

Consulta `/docs` para arquitectura, migración y decisiones pendientes.

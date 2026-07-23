# Alertas DT + SII Portal

Plugin WordPress de External Group para capturar suscriptores, administrar cuentas de clientes y sincronizar usuarios elegibles con la aplicación Python Alertas DT.

## Versión de esta rama

`0.3.0` — base del portal de clientes.

## Funcionalidades

- Formulario heredado `[alertas_dt_form]`.
- Registro `[alertas_dt_register]`.
- Inicio de sesión `[alertas_dt_login]`.
- Mi cuenta `[alertas_dt_account]`.
- Rol `alertas_dt_customer` sin acceso a `wp-admin`.
- Prueba gratuita de 15 días.
- Preferencias de email y WhatsApp.
- Término programado al finalizar el período.
- Modelo de pagos y auditoría.
- Pago anual simulado solo fuera de producción.
- API REST compatible con la aplicación Python.

## Instalación en staging

1. Respaldar la base de datos.
2. Subir la carpeta `alertas-dt-bridge` a `wp-content/plugins/`.
3. Activar o actualizar el plugin.
4. Visitar **Alertas DT + SII** en el administrador.
5. Confirmar las páginas creadas:
   - `/registro-alertas-dt/`
   - `/ingresar-alertas-dt/`
   - `/mi-cuenta-alertas-dt/`
6. Confirmar que los suscriptores existentes siguen visibles.

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
- Verificación de correo.
- Activación segura masiva de suscriptores históricos.
- Cambios en la aplicación Python para consumir `eligible_only=true`.

Consulta `/docs` para arquitectura, migración y decisiones pendientes.

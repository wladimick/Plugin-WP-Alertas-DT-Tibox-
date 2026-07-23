# Arquitectura del portal de clientes

**Estado:** borrador implementable  
**Versión del plugin:** 0.3.0  
**Fuente oficial:** `wladimick/Plugin-WP-Alertas-DT-Tibox-`  
**Última actualización:** 23-07-2026

## Decisiones confirmadas

- WordPress administra usuarios, inicio de sesión, perfiles, prueba, suscripción, pagos y consentimientos.
- Alertas DT Python administra monitoreo DT/SII, documentos, resúmenes IA, revisión y envíos.
- Se utilizan usuarios nativos de WordPress; no se almacenan contraseñas en tablas propias.
- Rol de cliente: `alertas_dt_customer`.
- El cliente no accede a `wp-admin` ni ve la barra administrativa.
- Prueba gratuita: 15 días.
- Pago del MVP: anual manual.
- Cobertura tras pago aprobado: 12 meses.
- Webpay Plus y Lioren se integrarán en ramas posteriores.

## Páginas creadas

| Página | Slug | Shortcode |
|---|---|---|
| Registro Alertas DT | `/registro-alertas-dt/` | `[alertas_dt_register]` |
| Ingresar a Alertas DT | `/ingresar-alertas-dt/` | `[alertas_dt_login]` |
| Mi cuenta Alertas DT | `/mi-cuenta-alertas-dt/` | `[alertas_dt_account]` |

El shortcode heredado `[alertas_dt_form]` se mantiene para no interrumpir la captura actual de suscriptores.

## Responsabilidades

### WordPress

- Registro y autenticación.
- Recuperación de contraseña mediante WordPress.
- Perfil y teléfono.
- Estado de prueba y suscripción.
- Preferencias de email y WhatsApp.
- Término programado al final del período.
- Historial de pagos.
- Auditoría comercial.
- Futuro checkout Webpay Plus.
- Futuras boletas/facturas mediante Lioren.

### Alertas DT Python

- Monitoreo de fuentes DT y SII.
- Detección y almacenamiento de documentos.
- Generación de resúmenes Azure/Codex.
- Revisión manual de alertas.
- Envío por SendGrid.
- Consumo de suscriptores elegibles desde REST.

## API de sincronización

Se conserva:

```text
GET /wp-json/alertas-dt/v1/subscribers
POST /wp-json/alertas-dt/v1/subscribers/synced
GET /wp-json/alertas-dt/v1/health
```

Se agrega:

```text
GET /wp-json/alertas-dt/v1/subscribers?eligible_only=true
```

Cada suscriptor incluye estado de notificaciones, estado de suscripción, fechas de prueba/cobertura y `eligible_for_alerts`.

## Compatibilidad de transición

Los suscriptores históricos que todavía no tienen usuario WordPress siguen siendo elegibles mientras estén activos y con consentimiento. Esto evita cortar los envíos durante la migración.

Cuando un suscriptor crea su cuenta, se vincula mediante `wp_user_id` y comienza a aplicarse el ciclo de prueba/suscripción.

## Seguridad

- Nonces en todas las acciones del portal.
- Sanitización y escaping de entradas/salidas.
- Acciones destructivas solo mediante POST.
- Tokens API no se muestran completos salvo al regenerarlos.
- No se almacenan tarjetas ni credenciales de pasarela.
- Eventos auditados sin API keys, tokens ni contraseñas.
- Los clientes solo pueden operar sobre su propio usuario autenticado.

## Pendientes antes de producción

- Verificación de correo para cuentas nuevas.
- Activación segura de cuentas históricas mediante enlace enviado por correo.
- Rate limiting de registro/login/recuperación.
- Políticas legales y privacidad.
- Pruebas de integración en staging WordPress.
- Webpay Plus real.
- Lioren real.
- Adaptar la aplicación Python para solicitar `eligible_only=true`.

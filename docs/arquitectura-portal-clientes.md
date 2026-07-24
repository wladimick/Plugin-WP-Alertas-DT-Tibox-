# Arquitectura del portal de clientes

**Estado:** borrador implementable  
**Versión del plugin:** 0.3.2  
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
- Los suscriptores históricos deben verificar su correo mediante un enlace de un solo uso antes de crear su cuenta.
- Todas las páginas privadas y transaccionales se agrupan bajo `/alertas-dt/`.

## Páginas creadas automáticamente

| Página | Slug | Shortcode/función |
|---|---|---|
| Acceso al portal | `/alertas-dt/` | `[alertas_dt_login]` |
| Crear cuenta | `/alertas-dt/crear-cuenta/` | `[alertas_dt_register]` |
| Activar cuenta histórica | `/alertas-dt/activar-cuenta/` | `[alertas_dt_activate_account]` |
| Ingresar | `/alertas-dt/ingresar/` | `[alertas_dt_login]` |
| Mi cuenta | `/alertas-dt/mi-cuenta/` | `[alertas_dt_account]` |
| Contratar plan | `/alertas-dt/contratar/` | `[alertas_dt_checkout]` |
| Resultado del pago | `/alertas-dt/resultado-pago/` | `[alertas_dt_payment_result]` |
| Recuperar contraseña | `/alertas-dt/recuperar-contrasena/` | `[alertas_dt_recovery]` |

El plugin crea únicamente las páginas que falten. Si ya existe una página administrada por el plugin, conserva su ID y la mueve al árbol definitivo. No sobreescribe contenido personalizado: solo repone el shortcode cuando la página está vacía o contiene exclusivamente un shortcode propio.

Las URLs antiguas del prototipo redirigen mediante HTTP 301 a las nuevas rutas.

El shortcode heredado `[alertas_dt_form]` se mantiene para no interrumpir la captura actual de suscriptores en la landing pública.

## Responsabilidades

### WordPress

- Registro y autenticación.
- Recuperación de contraseña mediante WordPress.
- Activación segura de suscriptores históricos.
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

## Activación de históricos

El flujo público no revela si un correo está registrado. Cuando corresponde:

1. genera un token aleatorio de 32 bytes;
2. almacena únicamente su hash SHA-256;
3. lo envía al correo histórico;
4. vence después de 60 minutos;
5. permite un solo uso;
6. vincula el usuario a la fila existente;
7. inicia la prueba de 15 días;
8. registra el evento de auditoría.

El registro normal intercepta correos históricos todavía no vinculados y exige este flujo, evitando apropiaciones basadas solo en conocer el email.

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

Cuando un suscriptor activa su cuenta, se vincula mediante `wp_user_id` y comienza a aplicarse el ciclo de prueba/suscripción.

## Seguridad

- Nonces en todas las acciones del portal.
- Sanitización y escaping de entradas/salidas.
- Acciones destructivas solo mediante POST.
- Tokens de activación de un solo uso, con hash y expiración.
- Respuesta genérica para evitar enumeración de correos.
- Rate limit por combinación correo/IP.
- Tokens API no se muestran completos salvo al regenerarlos.
- No se almacenan tarjetas ni credenciales de pasarela.
- Eventos auditados sin API keys, tokens ni contraseñas.
- Los clientes solo pueden operar sobre su propio usuario autenticado.

## Pendientes antes de producción

- Verificación de correo para cuentas completamente nuevas.
- Rate limiting adicional de login y recuperación.
- Políticas legales y privacidad.
- Pruebas de integración en staging WordPress.
- Webpay Plus real.
- Lioren real.
- Campaña de invitación para suscriptores históricos.
- Adaptar la aplicación Python para solicitar `eligible_only=true`.

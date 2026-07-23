# Modelo de suscripciones y pagos

**Estado:** borrador  
**Modelo comercial:** prueba de 15 días + pago anual manual  
**Precio:** pendiente; `$100 CLP` se usa solo como valor referencial de prueba.

## Estados de suscripción

| Estado | Acceso | Alertas | Descripción |
|---|---|---|---|
| `trialing` | Sí, hasta `trial_end_at` | Sí, si están habilitadas | Prueba gratuita de 15 días. |
| `awaiting_payment` | No | No | La prueba terminó y falta el pago anual. |
| `active` | Sí, hasta `subscription_end_at` | Sí | Plan anual pagado. |
| `past_due` | Pendiente de política | No por defecto | Pago pendiente o fallido en una futura renovación. |
| `cancel_at_period_end` | Sí, hasta el final del período | Sí, salvo opt-out | Término solicitado, sin cortar el período pagado. |
| `expired` | No | No | Cobertura finalizada. |
| `paused` | No | No | Pausa administrativa o comercial. |
| `blocked` | No | No | Bloqueo administrativo. |

Durante una prueba, la solicitud de término mantiene `trialing` y activa `cancel_at_period_end=1`; el acceso continúa hasta `trial_end_at`.

## Estados de notificación

El consentimiento de notificaciones es independiente de la suscripción:

- `active`: recibe alertas.
- `paused`: pausa temporal.
- `opted_out`: solicitó no recibir más notificaciones.

Una persona puede conservar acceso al portal y al período pagado aunque haya desactivado los correos.

## Estados de pago

- `created`
- `pending`
- `authorized`
- `failed`
- `canceled`
- `refunded`

## Tablas

### `wp_alertas_dt_subscribers`

Mantiene el registro histórico y agrega:

- `wp_user_id`
- `notification_status`
- `notification_updated_at`

### `wp_alertas_dt_subscriptions`

- usuario y suscriptor vinculados;
- estado;
- inicio y término de prueba;
- inicio y término de cobertura anual;
- término programado.

### `wp_alertas_dt_payments`

- proveedor;
- identificador externo;
- monto CLP;
- estado;
- cobertura asociada;
- fecha de autorización.

No contiene tarjetas ni payloads sensibles.

### `wp_alertas_dt_events`

Bitácora de:

- registro;
- inicio de prueba;
- cambios de perfil;
- preferencias;
- pago;
- activación;
- solicitud/reversión de término.

## Elegibilidad para alertas

Un cliente vinculado es elegible cuando:

1. el suscriptor está activo;
2. existe consentimiento;
3. `notification_status=active`;
4. la prueba no venció, o el plan anual sigue vigente.

Los registros históricos sin `wp_user_id` continúan elegibles temporalmente para evitar una interrupción durante la migración.

## Pago simulado

En ambientes distintos de producción se puede habilitar un proveedor simulado que:

1. registra un pago `authorized`;
2. usa $100 CLP referenciales;
3. activa 12 meses;
4. no llama a ninguna pasarela;
5. no emite documentos tributarios.

En producción el simulador queda desactivado por defecto.

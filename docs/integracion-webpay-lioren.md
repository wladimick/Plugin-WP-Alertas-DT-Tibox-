# Integración futura: Webpay Plus y Lioren

**Estado:** diseño pendiente de credenciales y definición tributaria.

## Decisión preliminar

- MVP comercial: pago anual manual.
- Pasarela preferida: Webpay Plus.
- No se almacenan tarjetas.
- No existe renovación automática en el MVP.
- Oneclick queda para una fase futura.
- Lioren se evaluará para emitir boleta, factura y nota de crédito.

## Flujo Webpay Plus esperado

1. El cliente inicia sesión en WordPress.
2. Desde Mi cuenta selecciona `Activar plan anual`.
3. WordPress crea una orden interna `created`.
4. El servidor crea la transacción en Webpay Plus.
5. El navegador se redirige al entorno seguro de Transbank.
6. El cliente vuelve a una URL de retorno de WordPress.
7. El servidor confirma la transacción con Transbank.
8. Solo una confirmación válida marca el pago `authorized`.
9. Se activa la cobertura por 12 meses.
10. Se solicita el documento tributario a Lioren.
11. El resultado queda en Mi cuenta y en la bitácora.

La visita a una URL de éxito nunca es suficiente para aprobar un pago.

## Idempotencia

- `provider + external_id` debe ser único.
- Un callback duplicado no puede duplicar pagos ni extender dos veces la cobertura.
- La activación anual debe quedar vinculada al `payment_id`.
- Los reintentos deben reutilizar la orden o crear una nueva con trazabilidad.

## Variables futuras de Webpay

No deben guardarse en la base ni en Git:

```text
ADT_PAYMENT_PROVIDER=webpay_plus
ADT_WEBPAY_COMMERCE_CODE=
ADT_WEBPAY_API_KEY=
ADT_WEBPAY_ENVIRONMENT=integration|production
ADT_WEBPAY_RETURN_URL=
```

Los nombres definitivos deben validarse al implementar la SDK oficial.

## Lioren

Después del pago autorizado se deberá enviar:

- razón social emisora;
- RUT y giro;
- tipo de DTE;
- monto neto/IVA/total;
- datos del receptor cuando correspondan;
- referencia interna del pago.

La integración debe registrar:

- identificador Lioren;
- estado;
- folio;
- tipo de documento;
- fecha;
- URL o referencia segura de PDF/XML;
- último error sanitizado.

## Pendientes tributarios

- Nueva razón social definitiva.
- RUT, giro e inicio de actividades.
- Certificado digital.
- Boleta afecta, exenta o factura.
- Tratamiento de IVA.
- Política de devoluciones.
- Cuándo corresponde nota de crédito.
- Ambiente de certificación y producción.
- Plan y costos de Lioren.

## Membrezia

La cliente ya utiliza Membrezia para otra actividad. Antes de desarrollar Webpay directamente se debe confirmar:

- si permite una segunda razón social;
- si ofrece API y webhooks suficientes;
- si el plan actual permite integración externa;
- si puede automatizar Lioren;
- si puede ser fuente de verdad sin duplicar estados con WordPress.

No se elige definitivamente entre Webpay directo y Membrezia hasta cerrar esa evaluación.

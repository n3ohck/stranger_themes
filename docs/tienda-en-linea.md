# Tienda en línea (`/comprar`)

Pantalla pública de compra de boletos, pensada para enlazarse desde
strangerthemes.com. Vive dentro de Laravel, no en el sitio de WordPress.

**URL para el sitio:** `https://TU-DOMINIO/comprar`

El botón "COMPRAR BOLETOS" del sitio apunta ahí. No hace falta ninguna integración
más: la tienda se sirve sola, con su propio diseño alineado al del sitio.

---

## Cómo funciona la compra

1. **Recorrido o paquete.** Se listan los productos marcados como visibles en la
   tienda, separados en recorridos individuales y paquetes.
2. **Participantes, fecha y horario.** Solo se ofrecen los días que la sucursal abre
   y los horarios realmente libres.
3. **Datos del cliente y código de descuento.** Nombre y correo; teléfono y código
   son opcionales.
4. **Pago.** Se redirige a **Stripe Checkout**, la página alojada por Stripe.
5. **Confirmación.** Al volver se muestra el folio y los horarios, y se envía el
   comprobante por correo.

### Horarios: cómo se calculan

- La rejilla es de **30 minutos**, desde la hora de apertura hasta la de cierre del
  día correspondiente, tomadas de los horarios de la sucursal.
- **Cada horario es exclusivo del grupo que lo reserva.** Si alguien aparta las 18:00
  del Manicomio, esas 18:00 desaparecen para todos, aunque vayan 2 personas de las 8
  que caben. La capacidad del producto solo sirve para rechazar grupos más grandes de
  lo que cabe.
- Un **paquete ocupa un tramo por cada recorrido**, encadenados. El paquete de 3
  recorridos que empieza a las 18:00 aparta 18:00, 18:30 y 19:00, y solo se ofrece
  una hora de inicio si los tres tramos están libres y caben antes del cierre.
- Reservar un paquete **también bloquea esos recorridos por separado**: nadie puede
  comprar el Escape Room de las 19:00 si un paquete ya lo tomó.
- No se ofrecen horarios con menos de **una hora** de anticipación.

### El horario se aparta mientras se paga

Al pulsar Pagar se crea una **compra pendiente** que bloquea esos horarios durante
**20 minutos**. Sin eso, dos clientes pueden elegir el mismo horario, pagar los dos y
dejar una sobreventa que solo se descubre en mostrador.

Si el cliente abandona el pago, el apartado caduca solo y el horario vuelve a
ofrecerse. `tienda:limpiar-pendientes` (agendado cada 10 minutos) solo marca el
estado; no es lo que libera el horario.

---

## Códigos de descuento

El cliente puede escribir un código en el paso de datos. Se valida contra el servidor
al aplicarlo y **se vuelve a validar al cobrar**: lo que se muestra y lo que se cobra
salen del mismo cálculo, así que no pueden diferir.

Se aplica la misma regla de redondeo del negocio que usa el punto de venta, **solo al
descuento unitario**:

- decimal ≤ .49 → hacia abajo
- decimal = .50 → se queda igual
- decimal ≥ .51 → hacia arriba

Ejemplo real: Escape Room a $175 con 28.8% da $50.40 de descuento unitario, que
redondea a **$50**. El precio por persona queda en $125.

### Cuándo se rechaza un código

| Caso | Mensaje al cliente |
|---|---|
| No existe o está inactivo | Ese código no existe o ya no está vigente. |
| Es de otra sucursal | Ese código no existe o ya no está vigente. |
| Su `producto_tipo` no coincide | El código X no aplica para *producto*. |
| Deja la compra en cero | Ese código solo se puede usar en taquilla. Preséntalo al llegar. |

**Los códigos del 100% no se aceptan en línea.** Códigos como `Cumpleañero`, `2 X 1` o
`Salieron` existen para taquilla, donde alguien verifica en persona que corresponden;
en línea no hay quién lo verifique y cualquiera que conozca el código reservaría
gratis. Además Stripe no admite cobros de cero.

> **Ojo con el catálogo actual:** todos los descuentos activos tienen
> `producto_tipo = tour`, así que **ninguno aplica a paquetes**. Si quieren promociones
> sobre paquetes, hay que crear códigos con `producto_tipo = tour_paquete` (o dejarlo
> vacío para que aplique a cualquiera) desde Descuentos en el panel.

Al cambiar de recorrido o de número de participantes, el código se revalida solo: si
deja de aplicar, se quita y se avisa, en vez de arrastrar un descuento que ya no
corresponde.

### Qué se guarda

La venta queda con `codigo_descuento`, `descuento` y `porcentaje_descuento`, y la línea
de `venta_productos` con el precio de lista, la cantidad, el total neto y el
porcentaje. Con eso el reporte de descuentos del panel reconstruye correctamente a
cuántas personas se aplicó.

---

## Stripe

Se usa **Stripe Checkout**, la página alojada por Stripe: el cliente escribe su
tarjeta en el dominio de Stripe. **Este servidor nunca ve, transmite ni guarda datos
de tarjeta.**

### Configuración

En el `.env` del servidor:

```
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_MONEDA=mxn
```

Mientras `STRIPE_SECRET` esté vacío, la tienda funciona pero al pagar avisa que el
pago en línea todavía no está disponible. Nada se aparta ni se cobra.

### Webhook

En el panel de Stripe, agregar un endpoint apuntando a:

```
https://TU-DOMINIO/tienda-api/webhook/stripe
```

Eventos: `checkout.session.completed` y `checkout.session.async_payment_succeeded`.

El webhook **no es opcional**. El regreso del cliente al sitio puede no ocurrir (cierra
la pestaña, se le va el internet) y sin webhook ese pago quedaría cobrado sin venta ni
reservación. Con webhook, la venta se crea igual.

La firma del webhook se valida con `STRIPE_WEBHOOK_SECRET`; sin ese secreto el
endpoint responde 503 y no procesa nada.

### Doble confirmación sin doble venta

El regreso del cliente y el webhook pueden llegar los dos, y los webhooks se
reintentan. La confirmación es idempotente en tres capas:

1. Si la compra ya tiene venta, se devuelve esa misma.
2. Si ya existe una venta con esa referencia de pago, se engancha a ella.
3. Si dos avisos entran a la vez, el índice único `ventas.referencia_pago` corta al
   segundo y el código lo trata como reintento.

---

## Qué se guarda al confirmarse el pago

Una venta normal del sistema, indistinguible de las demás salvo por su origen:

- `ventas` con `origen = 'web'`, folio de la sucursal (`PV-123`) y
  `referencia_pago` = el PaymentIntent de Stripe.
- Una línea en `venta_productos` y un pago en `venta_pagos` con `tipo = 'online'`.
- Una **reservación por cada recorrido**: un paquete de tres genera tres.

Aparecen en el panel, en los reportes y en el calendario del punto de venta, donde el
cajero las ve marcadas como provenientes del sitio web.

---

## Poner un producto a la venta en línea

Desde **Productos** en el panel, cada producto tiene:

| Campo | Para qué |
|---|---|
| Vender en la tienda en línea | Lo muestra en `/comprar` |
| Capacidad por horario | Máximo de participantes por sesión |
| Duración (minutos) | Cuánto dura; en un paquete, la suma de sus recorridos |

Capacidad y duración son **obligatorias** si se marca para vender en línea: sin ellas
no se pueden calcular horarios.

Los valores iniciales se tomaron de strangerthemes.com: Winchester y Manicomio hasta
8 participantes, Escape Room hasta 6, 25 minutos cada uno. Los productos
"DIFERENCIA" quedaron fuera de la tienda por ser ajustes internos de precio.

---

## Pendientes conocidos

- **Los horarios de la sucursal deben estar al día.** La tienda los usa tal cual: si
  en el panel dicen 17:30 y el sitio anuncia 17:00, gana el panel.
- **Una sola moneda.** `STRIPE_MONEDA` aplica a todas las sucursales.

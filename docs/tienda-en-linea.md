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
- **Un horario se comparte entre grupos y se cuenta por lugares.** Si una pareja
  aparta las 18:00 del Manicomio (cupo 8), quedan 6 lugares y esas 18:00 se siguen
  ofreciendo a grupos de hasta 6. `disponibilidad` recibe `personas` y solo devuelve
  los horarios donde cabe el grupo completo, con `lugares` restantes en cada uno.
- El punto de venta no respeta el cupo: en mostrador se puede pasar cuando el grupo
  sí cabe. Por eso un horario puede aparecer con menos lugares de los esperados, o
  sin lugares, aunque nadie lo haya comprado en línea.
- Un **paquete ocupa un tramo por cada recorrido**, encadenados. El paquete de 3
  recorridos que empieza a las 18:00 aparta 18:00, 18:30 y 19:00, y solo se ofrece
  una hora de inicio si el grupo cabe en los tres tramos y terminan antes del cierre.
- Reservar un paquete **también ocupa lugares en esos recorridos por separado**: si un
  paquete de 4 tomó el Escape Room de las 19:00 (cupo 6), ahí quedan 2 lugares.
- No se ofrecen horarios con menos de **una hora** de anticipación.

### El horario se aparta mientras se paga

Al pulsar Pagar se crea una **compra pendiente** que bloquea esos horarios durante
**30 minutos** (el mínimo que Stripe acepta para una sesión de pago, que vence junto
con el apartado). Sin eso, dos clientes pueden elegir el mismo horario, pagar los dos y
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

### Staging: que el cliente pruebe sin cobrar

En el servidor de staging se usan **las llaves de prueba**, nunca las `live`:

```
APP_ENV=staging
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...   # el del webhook de staging, no el de stripe listen
STRIPE_MONEDA=mxn
```

1. En Stripe, en **modo prueba**, crea un destino de webhook a
   `https://TU-STAGING/tienda-api/webhook/stripe` con los eventos
   `checkout.session.completed` y `checkout.session.async_payment_succeeded`, y copia
   su *Signing secret* a `STRIPE_WEBHOOK_SECRET`.
2. `php artisan config:cache` después de editar el `.env`.
3. Con una llave `sk_test_` la tienda muestra el aviso **"Modo de prueba"** con la
   tarjeta 4242 4242 4242 4242, así que el cliente sabe qué usar y que no se cobra.
4. `php artisan tienda:seguridad --url=https://TU-STAGING` para verificar el servidor.

Los pagos de prueba aparecen en el panel de Stripe en modo prueba, y las ventas y
reservaciones se crean igual que las reales en la base de staging.

## Seguridad

`php artisan tienda:seguridad` prueba por HTTP: cabeceras, inyección SQL, XSS,
honeypot, firma del webhook, regreso manipulado y límites de peticiones. No crea
compras ni toca Stripe. Las de límite gastan el cupo de tu IP un minuto
(`--sin-limites` las omite).

- **Honeypot**: campo oculto `sitio_web` y `tiempo_llenado` (mínimo 3 s, medido en el
  navegador). La respuesta al bot es genérica.
- **Tope de apartados**: 4 por IP cada 30 minutos, para que nadie bloquee horarios
  sin pagar.
- **Pago verificado**: el regreso y el webhook solo confirman si la sesión de Stripe
  es de esa compra, por ese monto y en esa moneda.
- **Idempotencia**: clave por intento de pago (doble clic, cortes de red) y candado
  sobre la compra al confirmar (regreso y webhook simultáneos).

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

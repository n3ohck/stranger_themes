# Integración del sitio web con el API

Contrato de los endpoints públicos (`/api/public/*`) que consume el sitio de comercio
electrónico. Sin autenticación: no llevan token ni cookie.

Base: `https://TU-DOMINIO/api/public`

---

## Lo que cambia y por qué

El negocio pasa a operar **dos sucursales**. Cada una tiene su propio catálogo,
sus propios códigos de descuento, sus propios horarios y su propia agenda.

Como estos endpoints no llevan sesión, el servidor no tiene forma de adivinar de qué
sucursal habla el sitio: **`sucursal_id` es obligatorio en todos ellos**.

El sitio ya lo envía en `public/reservas` y `public/sucursal` (si no, la disponibilidad
nunca habría funcionado). Lo que falta es enviarlo también en:

| Endpoint | Antes | Ahora |
|---|---|---|
| `GET public/productos` | `sucursal_id` opcional; sin él devolvía todo | `sucursal_id` **obligatorio** |
| `POST public/ventas/make` | `sucursal_id` se aceptaba sin validar | **obligatorio y validado** |
| `POST public/ventas/cancel` | solo pedía `venta_id` | pide además `referencia` |

---

## Orden de despliegue (importante)

**Actualiza y publica el sitio primero, el API después.**

Los dos campos nuevos ya se pueden enviar contra el API que está en producción hoy:

- `sucursal_id` en `public/productos` existe hace tiempo, solo que era opcional.
- `referencia` en `public/ventas/cancel` es un campo extra que el API actual ignora.

Es decir, el sitio actualizado funciona igual antes y después del cambio de API, y no
hay ventana de caída.

Si se despliega el API primero, el catálogo del sitio deja de responder y las
cancelaciones fallan hasta que salga la versión nueva del sitio.

---

## Endpoints

### `GET /sucursales`

Lista las sucursales para que el cliente elija. **Nuevo.**

Sin parámetros.

```json
{
  "sucursales": [
    {
      "id": 1,
      "nombre": "Stranger Themes Plaza Vallarta",
      "direccion": "Av Vallarta 5900, Chihuahua",
      "telefono": "4422281679",
      "email": "contacto@ejemplo.com",
      "horarios": [{ "dia": "jueves", "hora_entrada": "17:30", "hora_salida": "22:00" }],
      "ubicacion": "https://maps.google.com/...",
      "logotipo": "storage/uploads/logotipos/..."
    }
  ],
  "qty": 1
}
```

Guarda el `id` elegido y mándalo en todo lo demás.

---

### `GET /sucursal?sucursal_id=1`

Datos de una sucursal concreta. Ya en uso.

---

### `GET /productos?sucursal_id=1&tipo=tour`

Catálogo de la sucursal.

| Parámetro | Obligatorio | Notas |
|---|---|---|
| `sucursal_id` | **sí** | Sin él responde `500` con `"Debes indicar la sucursal para consultar el catálogo"` |
| `tipo` | no | `tour`, `tour_paquete`, `articulo`, `diferencias`. Sin él devuelve el catálogo completo de la sucursal |

Un `tipo` que no esté en esa lista responde `"Tipo de producto no válido"`.

```json
{
  "productos": [
    { "id": 3, "codigo": "ESCAPE ROOM", "descripcion": "Escape Room",
      "precio": 175, "existencia": 0, "sucursal_id": 1, "tipo": "tour",
      "tours": [] }
  ],
  "qty": 1
}
```

**Paquetes**: un producto `tour_paquete` trae en `tours` los recorridos que incluye,
como `[{"producto_id":"2"},{"producto_id":"4"}]`. El sitio debe pedir **una fecha y
hora por cada uno** y mandar una reservación por cada tour del paquete.

**Precio por persona**: `precio` es unitario. El total de una línea es
`precio × cantidad`, donde para un tour la cantidad es el número de personas.

---

### `GET /descuentos?sucursal_id=1&codigo=PROMO10`

Valida un código. Ambos parámetros son obligatorios.

```json
{
  "descuento": { "id": 8, "codigo": "PROMO10", "porcentaje": 10,
                 "sucursal_id": 1, "producto_tipo": "tour", "estatus": "activo" },
  "valid": true
}
```

`valid: false` significa que no existe, no está activo o no es de esa sucursal.
`producto_tipo` limita a qué tipo de producto aplica; vacío significa cualquiera.

**Redondeo del descuento** (regla del negocio, aplica solo al descuento unitario en
dinero, nunca al precio ni a la cantidad):

- decimal ≤ .49 → hacia abajo
- decimal = .50 → se queda igual
- decimal ≥ .51 → hacia arriba

---

### `GET /reservas?sucursal_id=1&producto_id=3&date=2026-09-12`

Ocupación de un producto en un día, para calcular disponibilidad. Los tres parámetros
son obligatorios. Ya en uso.

```json
{
  "reservas": { "18:00": { "time": "18:00", "qty": 4 } },
  "qty": 4
}
```

`qty` es la suma de personas ya agendadas en ese horario.

---

### `POST /ventas/make`

Registra la venta pagada. Este es el importante.

```json
{
  "ventas": [
    {
      "sucursal_id": 1,
      "total": 525,
      "nombre": "Ana Pérez",
      "email": "ana@ejemplo.com",
      "telefono": "6141234567",
      "descuento_id": 8,
      "codigo_descuento": "PROMO10",
      "descuento": 52.5,
      "porcentaje_descuento": 10,
      "productos": [
        { "producto_id": 3, "precio": 175, "cantidad": 3, "total": 472.5,
          "descuento": 52.5, "descuento_id": 8, "porcentaje_descuento": 10 }
      ],
      "pagos": [
        { "tipo": "online", "monto": 472.5, "referencia": "pi_3ABC..." }
      ],
      "reservaciones": [
        { "producto_id": 3, "name": "Ana Pérez", "number": 3,
          "datetime": "2026-09-12 18:00:00" }
      ]
    }
  ]
}
```

Reglas:

- **`sucursal_id` obligatorio y validado.** Si falta o no existe, responde `400` con
  `"La venta no indica una sucursal válida"`.
- **`pagos` debe traer al menos uno con `tipo: "online"` y `referencia` no vacía.**
  Sin pago online o sin referencia, responde `400` con el mensaje correspondiente.
- **`referencia` es la llave anti-duplicado.** Usa el identificador del cobro de la
  pasarela (el PaymentIntent de Stripe, `pi_...`). Un mismo cobro nunca debe llegar
  con referencias distintas.
- **`datetime`** en formato `YYYY-MM-DD HH:mm:ss` (también acepta ISO con `T`).
- **`name`** y **`number`** son el nombre del cliente y el número de personas de esa
  reservación. Un paquete manda una reservación por cada tour incluido.
- Si mandas `email`, el servidor envía el comprobante digital al cliente.

Respuesta:

```json
{
  "ventas": [
    { "venta_id": 5007, "estatus": "activo", "folio": "PV-4", "total": 472.5,
      "referencias": ["pi_3ABC..."], "reservaciones": [ ... ] }
  ],
  "qty": 1
}
```

#### Reintentos y duplicados

El servidor rechaza a nivel de base de datos cualquier venta con una `referencia` ya
registrada. Si reenvías la misma referencia, la respuesta es **200 con `qty: 0`**:

```json
{ "ventas": [], "qty": 0 }
```

Eso **no es un error**: significa que esa compra ya estaba registrada. El sitio debe
tratarlo como éxito y mostrar la confirmación, no reintentar ni cobrar de nuevo.

> Este es el punto que causó el problema histórico: entre agosto de 2025 y abril de
> 2026 se registraron 40 ventas duplicadas por reenvíos del mismo cobro. Ahora la base
> lo impide, pero el sitio debe además **deshabilitar el botón de pagar tras el primer
> clic** y no reintentar automáticamente ante un timeout sin antes consultar.

---

### `POST /ventas/cancel`

Cancela una compra en línea a petición del cliente: marca la venta como cancelada,
cancela sus reservaciones, devuelve existencias y levanta una disputa que se notifica
por correo.

```json
{
  "venta_id": 5007,
  "referencia": "pi_3ABC...",
  "motivo": "El cliente no puede asistir"
}
```

| Campo | Obligatorio | Notas |
|---|---|---|
| `venta_id` | **sí** | |
| `referencia` | **sí** | La misma que se envió al registrar la venta. Es la prueba de que la compra es de quien la cancela |
| `motivo` | no | Texto libre, máx. 500 caracteres. Queda guardado en la venta |

Respuesta correcta:

```json
{
  "status": true,
  "message": "Compra cancelada correctamente.",
  "data": { "venta_id": 5007, "folio": "PV-4", "disputa_id": 12 }
}
```

Reglas:

- **La referencia debe coincidir con la de la compra.** Sin ella, o con una que no
  corresponda, responde `404` con un mensaje genérico. Los ids de venta son
  consecutivos y adivinables; la referencia del cobro no.
- **Solo aplica a ventas en línea.** Una venta de mostrador responde `404`: esas se
  cancelan desde el punto de venta o desde el panel.
- **Es idempotente.** Reenviar la solicitud de una compra ya cancelada responde `200`
  con `"Esta compra ya estaba cancelada."`, sin duplicar la disputa ni el correo.
- **Límite de 10 intentos por minuto** por IP, para que la referencia no se pueda
  tantear por fuerza bruta. Al excederlo responde `429`.

> Aun así, conviene llamarlo desde el backend del sitio y no directamente desde el
> navegador del cliente.

---

## Manejo de errores

Todos los errores devuelven `{"error": "mensaje"}`. Los códigos no son finos:

| Endpoint | Código en error |
|---|---|
| `GET /productos`, `/descuentos`, `/reservas`, `/sucursal` | `500` |
| `POST /ventas/make` | `400` |
| `POST /ventas/cancel` | `404` si no coincide, `422` si faltan campos, `429` si excede el límite |

Un `500` aquí suele ser un parámetro faltante, no una caída del servidor. El mensaje
viene en español y sirve para el log del sitio; no lo muestres tal cual al cliente.

El API ya no devuelve el stack trace en la respuesta: el detalle queda en el log del
servidor.

Antes de comprar conviene revalidar contra el API, porque el catálogo pudo cambiar
mientras el cliente navegaba:

1. Que el producto siga existiendo en esa sucursal.
2. Que el código de descuento siga activo.
3. Que el horario siga teniendo cupo (`GET /reservas`).

---

## Checklist

- [ ] Elegir sucursal (o listarla con `GET /sucursales`) y guardarla en el estado del sitio.
- [ ] Enviar `sucursal_id` en `productos`, `descuentos`, `reservas`, `sucursal` y `ventas/make`.
- [ ] Mandar una reservación por cada tour de un paquete.
- [ ] Usar el id del cobro de la pasarela como `referencia`.
- [ ] Tratar `qty: 0` como compra ya registrada, no como error.
- [ ] Deshabilitar el botón de pago tras el primer clic.
- [ ] Mandar `referencia` también al cancelar una compra.
- [ ] Publicar el sitio **antes** que el API.

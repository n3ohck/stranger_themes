# CLAUDE.md

Contexto del proyecto para agentes trabajando en este repo.

## Qué es

**StrangerThemes** — backoffice + punto de venta para un negocio de salas de escape /
experiencias por reserva, multi-sucursal. Tres consumidores:

1. **Panel admin** (Backpack CRUD) — operación diaria y catálogos. Rutas en `routes/backpack/custom.php`.
2. **App POS** (cliente externo, JWT) — venta en mostrador, apertura/corte de caja, egresos. Rutas en `routes/api.php`.
3. **Sitio web público** (venta online) — endpoints `public/*` sin auth en `routes/api.php`.

Las pantallas Vue/Inertia (`resources/js/Pages`) son solo Dashboard y Reportes; se
alimentan de `routes/webapi.php`. Todo lo demás es Blade + Backpack.

## Stack

- PHP 8.2 / Laravel 8 / MySQL
- Backpack CRUD 4.1 + permissionmanager (roles con spatie/permission)
- Auth: **doble guard** — sesión `web` para el admin, **JWT** (`tymon/jwt-auth`) para la API
- Vue 3 + Inertia 0.6 + element-plus + chart.js, compilado con Laravel Mix (`npm run watch`)
- PDF/imagen: `barryvdh/laravel-snappy` + wkhtmltopdf
- Excel: `maatwebsite/excel`
- `QUEUE_CONNECTION=sync` — **los jobs corren dentro del request**, no hay worker

## Dominio

| Modelo | Rol |
|---|---|
| `Sucursal` | Unidad de tenancy. Casi todo tiene `sucursal_id`. |
| `Producto` | Salas/experiencias y artículos. `tipo` ∈ `articulo`, `tour`, `tour_paquete`. Solo `articulo` descuenta existencia. |
| `Reserva` | Horario agendado (`fecha`, `cantidad_personas`, `estado` ∈ `confirmada`/`cancelada`) |
| `Venta` → `VentaProducto`, `VentaPago` | Cabecera + líneas + pagos. `estatus` ∈ `activo`/`cancelado`. |
| `VentaPago.tipo` | `efectivo`, `tarjeta`, `transferencia`, `online` |
| `Descuento` | Código + porcentaje, por sucursal y tipo de producto |
| `Apertura` / `Corte` | Apertura y cierre de caja por turno. Un corte cierra su apertura. |
| `Egreso`, `EmpleadoPago`, `Empleado` | Salidas de efectivo y nómina |
| `PagoCarta`, `PagoConcepto` | Cartas de pago (con PDF) |
| `CompraPendiente` | Compra del sitio web que aún no se paga. Aparta horarios 20 min. |
| `Disputa`, `LogNotificacion` | Cancelación de venta online (requiere la referencia del cobro) y trazabilidad de correos |

## Reglas de arquitectura importantes

### 1. Multi-tenancy por scope global
`App\Scopes\SucursalFilterScope` está aplicado en `Venta`, `Reserva`, `Producto`,
`Descuento`, `Corte`, `Apertura`, `PagoCarta`, `PagoConcepto`. La sucursal por la que
filtra la resuelve `App\Support\SucursalActiva`:

- Cajeros y consulta: siempre la sucursal de su ficha.
- Administración y gerencia: la que hayan elegido en el selector del panel; si no han
  elegido, la suya.
- En el POS no hay sesión (el grupo `api` no incluye `StartSession`), así que la
  elección nunca aplica y todos quedan fijos en su propia sucursal. Es deliberado.

Si `backpack_user()` es null (rutas públicas), **el scope no filtra nada**. Por eso los
endpoints `public/*` exigen `sucursal_id` explícito.

`EmpleadoPago` usa su propio closure de scope, no la clase, porque hereda la sucursal
del empleado.

**Los nombres de rol son sensibles a mayúsculas.** Usa siempre `App\Support\Roles`:
el rol se llama `Administrador`, y el scope preguntaba por `'administrador'`, así que
la exención de administrador nunca se aplicó. Con una sola sucursal era invisible.

### 2. El guard `web` se puebla en peticiones JWT
`JwtMiddleware` llama `JWTAuth::parseToken()->authenticate()`, que internamente hace
`Auth::onceUsingId()` sobre el guard **por defecto (`web`)**. Consecuencia: dentro de una
petición JWT, `backpack_user()` y `Auth::user()` **sí devuelven el usuario**. De ahí que
los controladores de la API usen `backpack_user()->sucursal_id` sin problema.

Esto es implícito y frágil: si se cambia el guard por defecto o se sustituye el
middleware por `auth:api`, se rompe medio `routes/api.php` con "Call to a member
function on null".

### 3. Zonas horarias: todo en hora de Chihuahua

`config('app.timezone') = 'America/Chihuahua'`. **No es UTC y es deliberado.**

Las 4,582 ventas históricas ya estaban guardadas en hora local, pero el código nuevo
usaba `Carbon::now()` que con `app.timezone = UTC` guardaba UTC: los registros nuevos
salían 6 horas adelantados. Unificar la zona de la aplicación alinea escrituras,
comparaciones y lecturas con lo que ya existe.

Consecuencias:

- `Carbon::now()`, `now()` y los timestamps de Eloquent dan hora local. **No conviertas
  nada a UTC**; los reportes lo hacían y corrían la ventana 6 horas, dejando fuera las
  ventas de madrugada.
- `display_timezone` se conserva porque varios puntos la leen, pero hoy coincide con la
  de la aplicación, así que esas conversiones son inocuas.
- `App\Traits\DateTrait::makeDate()` normaliza strings ISO (`T` → espacio).
- **Si se abre una sucursal en otra zona horaria esto deja de servir** y hay que migrar
  a UTC con conversión en presentación, corriendo también los datos históricos.

**La mayoría de los bugs históricos del repo vienen de aquí.** Ante cualquier cambio de
fechas, revisar en qué zona está el dato antes de comparar.

### 4. La lógica de venta vive en `App\Actions\VentaAction`
No en el controlador. Métodos:
- `do()` — venta de mostrador. Dedupe por `created_at` + `total`.
- `saleOnline()` — venta web. Dedupe por `ventas.referencia_pago`, con índice único detrás.
- `cancelVentas()` — cancela venta, cancela sus reservas, **devuelve existencias**.
- `makeVentaProductos()` / `makeVentaProductosOnline()` — líneas + descuento agregado en la cabecera.
- `sellarFolio()` — asigna el folio de la sucursal después de insertar.

Las existencias se mueven vía `App\Actions\ExistenciaAction` (solo productos `tipo = articulo`).

### 5. Ventas online: tratamiento especial en reportes
Se crean con `user_id = 1` y su `created_at` es la fecha de compra, no la de la
experiencia. Por eso los reportes las consultan aparte, con `withoutGlobalScopes()` y
filtrando por `reservaciones.fecha` en lugar de `ventas.created_at`, y luego hacen merge
+ `unique('id')`. Ver `VentaCrudController@fetch` y `@resumen`.

### 6. Regla de redondeo de descuentos (negocio)
Aplica **solo al descuento unitario**, nunca a precios ni cantidades:
- decimal ≤ .49 → `floor`
- decimal == .50 → se queda igual
- decimal ≥ .51 → `ceil`

Está duplicada como closure `$roundDiscount` en `VentaCrudController@resumen` y
`@resumenProductosDescuentos`. Si se toca una, tocar la otra.

### 7. Folio de venta: prefijo y consecutivo por sucursal

Cada sucursal tiene `prefijo_folio` (p. ej. `PV`) y su propio contador
`folio_consecutivo`. `Sucursal::tomarFolio()` lo reparte con `lockForUpdate`, y el
índice único `ventas(sucursal_id, folio_consecutivo)` es la red de seguridad.

La columna `ventas.folio_consecutivo` es nullable: las ventas históricas quedan en
NULL y MySQL no las considera en el índice único, así que la garantía aplica desde la
primera venta nueva sin reescribir el pasado.

**No vuelvas a calcular el folio con `MAX(id)+1`.** El modelo `Venta` usa SoftDeletes,
así que `max('id')` ignora las borradas: al borrar un bloque de ventas recientes el
contador retrocedía y los folios nuevos chocaban con los viejos. Así se generaron 93
folios repetidos entre 741 ventas históricas, que se conservan tal cual.

### 8. Anti-duplicado de venta online

`ventas.referencia_pago` guarda el PaymentIntent de Stripe y tiene índice único. Un
`exists()` en PHP no basta: dos peticiones simultáneas lo pasan las dos. `saleOnline()`
consulta primero y, si aun así choca, atrapa la `QueryException` del índice y trata el
caso como reintento en vez de devolver error al sitio.

Es nullable por la misma razón que el folio: las 36 referencias duplicadas del
histórico se conservan y no estorban al índice.

**No agregues un índice único sobre `venta_pagos(tipo, referencia)`.** Existió una
migración así y se eliminó: las 36 referencias duplicadas del histórico se conservan
por decisión del negocio, así que ese índice nunca puede crearse. Peor aún, la
migración abortaba `php artisan migrate` y dejaba sin correr todo lo que venía
después, bloqueando cada despliegue. La protección vive en `ventas.referencia_pago`;
para diagnosticar el histórico está `php artisan pos:duplicados-online`.

**Una migración nunca debe abortar el despliegue por el estado de los datos.** Si algo
necesita revisión humana, va en un comando de diagnóstico, no en una migración.

### 9. Endpoints públicos: la sucursal y la referencia son obligatorias

`public/*` no lleva sesión, así que el scope de sucursal no filtra nada. Todos exigen
`sucursal_id` explícito, incluido `public/productos` (antes era opcional y con dos
sucursales devolvía los catálogos mezclados).

`public/ventas/cancel` exige además la `referencia` del cobro: los ids de venta son
consecutivos y antes bastaba con uno válido para cancelar la venta de cualquiera. La
referencia funciona como prueba de propiedad, la ruta va con `throttle:10,1` y la
respuesta de rechazo es genérica para no revelar qué ids existen.

El contrato completo para el sitio web está en `docs/integracion-sitio-web.md`.

**Ningún endpoint público debe devolver `$e->getTrace()`.** `publicMake` lo hacía y
respondía 16 KB con las rutas absolutas del servidor en cada error. El detalle va al
log; la respuesta solo lleva el mensaje.

## Tienda en línea (`/comprar`)

SPA de Vue pública, enlazada desde strangerthemes.com. Diseño alineado al del sitio
(fondo `#1C1C21`, rojo `#B11724`, Montserrat + Jim Nightshade, botones cuadrados).

- **Frontend**: `resources/js/tienda/`, entry propio en `webpack.mix.js`.
- **Backend**: `routes/tienda.php` bajo `/tienda-api`, sin autenticación.
- **Pago**: Stripe Checkout alojado. El servidor nunca ve datos de tarjeta.

### Reglas propias de la tienda

1. **Un horario se comparte entre grupos y se cuenta por lugares.** La ocupación es
   la suma de personas (reservas confirmadas + compras apartadas) contra la
   `capacidad` de cada recorrido. En línea solo se ofrece un horario donde el grupo
   completo cabe en todos sus tramos, y se muestra cuántos lugares quedan
   (`Disponibilidad::paraProducto(..., $personas)` y `tramosConCupo()`).
   **En mostrador el cupo no bloquea**: se meten grupos distintos al mismo horario y
   se puede pasar de la capacidad (llegan 10 niños a un recorrido de 8 que sí
   caben); el POS solo avisa. Para eso `paraProducto()` recibe `$personas = null`.
   Antes la regla era un grupo por horario; el negocio la cambió.
2. **La cadencia de salidas la define cada sucursal, día por día**
   (`horarios[].intervalo_minutos`). Una sucursal saca recorridos cada 30 minutos y
   otra cada hora. `Disponibilidad` es la única fuente: de ahí leen la tienda, el
   punto de venta y la lista de reservas, así que no pueden desincronizarse.
3. **Un paquete ocupa un tramo por recorrido**, encadenados según ese intervalo, y
   solo se ofrece si todos caben antes del cierre. El último tramo se mide con la
   **duración real** del recorrido, no con el intervalo: con salidas cada hora y
   recorridos de 25 minutos, la salida de las 21:30 termina 21:55 y sí es vendible.
   Apartar un paquete también bloquea esos recorridos por separado.
4. **El horario se aparta 20 minutos mientras se paga** (`compras_pendientes`). Sin
   eso dos clientes pagan el mismo horario. Lo caducado deja de bloquear solo; el
   comando `tienda:limpiar-pendientes` únicamente marca el estado.
5. **La confirmación del pago es idempotente**: llegan el regreso del cliente y el
   webhook, y los webhooks se reintentan. Ver `ConfirmarCompraAction`.
6. **El precio se calcula en el servidor** desde el catálogo, igual que en el POS.
   Los códigos de descuento los resuelve `App\Support\Tienda\DescuentoAplicado`, que
   usan tanto la validación en pantalla como el cobro, para que no puedan diferir.
   **Los códigos que dejan el total en cero se rechazan en línea**: son de taquilla,
   donde alguien verifica en persona, y Stripe no admite cobros de cero.
7. `productos.visible_en_tienda`, `capacidad` y `duracion_minutos` controlan qué se
   vende en línea. Las dos últimas son obligatorias para publicar un producto.

Cuidado con `Eloquent\Collection::map()`: solo degrada a colección base si detecta
algún elemento que no sea modelo, así que sobre un resultado **vacío** devuelve una
colección de Eloquent, y su `unique()` llama `getKey()` sobre strings. En
`Disponibilidad` se fuerza `toBase()` por eso.

El contrato y la configuración de Stripe están en `docs/tienda-en-linea.md`.

## Punto de venta (`/pos`)

SPA de Vue 3 + Tailwind, independiente de Backpack y de Inertia. Los cajeros
entran por `/pos`, **no** por `/admin`.

- **Frontend**: `resources/js/pos/` — vue-router (base `/pos`), Pinia, Tailwind.
  Entry point propio en `webpack.mix.js` (`public/js/pos.js`), separado del
  bundle del dashboard.
- **Backend**: `routes/pos.php` bajo `/pos-api`, controladores en
  `app/Http/Controllers/Pos/`. Middleware `jwt.verify` + `pos.user`
  (`EnsurePosUser`: exige rol `APP USER` o `Administrador` y sucursal asignada).
- **Blade**: `resources/views/pos.blade.php`, servido por un comodín
  `/pos/{cualquiera?}` para que vue-router maneje sus rutas al recargar.

### Reglas propias del POS

1. **El servidor calcula los importes.** `RegistrarVentaAction` recibe producto,
   cantidad y código de descuento; los precios salen del catálogo. El POS nunca
   envía precios ni totales. Esto es distinto de `VentaAction::do()`, donde el
   cliente mandaba los importes ya calculados.
2. **Las ventas se amarran a la apertura** (`ventas.apertura_id`), no a un rango
   de fechas. Un turno que cruza la medianoche sigue siendo un turno.
   Las ventas en línea no tienen apertura: el corte suma las compradas desde que
   se abrió la caja (`Venta::enLineaEntre`), y el historial muestra esas más las
   que tienen visita hoy (`Venta::enLineaConVisitaEntre`), sin botón de cancelar.
3. **El corte lo calcula el servidor** (`App\Support\Caja\ResumenCorte`). El
   POS solo declara el efectivo contado; la diferencia contra lo esperado queda
   registrada. La misma clase alimenta el precorte, el ticket impreso, los
   accesores de `Corte` y el listado y la vista "Ver corte" del panel, para que
   todos muestren lo mismo. Filtra por sucursal explícita: en el panel el usuario
   puede estar viendo otra. Ganancia = efectivo + tarjeta + transferencia + en
   línea − egresos − pagos a empleados, sin el fondo. Las fechas del corte llevan
   el día de la semana (`App\Support\Fechas` y su espejo `diaCorto()` en JS).
4. **La regla de redondeo de descuentos** vive en `App\Support\ReglaDescuento`
   (autoridad) y su espejo en JavaScript en `resources/js/shared/descuento.js`, que
   comparten el POS y la tienda y solo sirve para mostrar el total antes de cobrar.
   Si cambia una, cambiar la otra.
5. **Paquetes**: un `tour_paquete` genera una reservación por cada tour de su
   columna JSON `tours`. Un `tour` genera una. Un `articulo`, ninguna.
6. **Tickets**: HTML con `@media print` a 58mm (área imprimible 48mm), en
   `resources/css/pos.css`. El elemento a imprimir debe tener `id="ticket"`.
7. **La sucursal del cajero es fija.** Sale de su ficha de usuario, incluso si es
   administrador: el grupo `api` no arranca sesión, así que el selector del panel
   no llega al POS. `CatalogoController` y `ReservaController` además filtran por
   sucursal de forma explícita para no depender del scope ambiental.
8. **Egresos y pagos a empleados** se registran con la fecha del momento, no una
   elegida por el cajero: lo que se captura es dinero saliendo del cajón ahora.
   Van con `apertura_id`, y el precorte los cuenta por turno cuando lo traen y
   por ventana de fechas cuando no (los capturados desde el panel).
   **Los egresos del POS son siempre en efectivo**: lo pagado con tarjeta o
   transferencia de la empresa no toca la caja del turno y se captura en el panel.
   **Un pago a empleado se registra sin comprobante**: al guardarlo se imprime un
   ticket con el folio para que el empleado lo firme, y la foto de ese ticket se
   sube después a `empleados/pagos/{pago}/comprobante`. Mientras no la tenga, la
   lista lo marca como "Falta firma" y `CajaController@cerrar` rechaza el corte
   (el precorte lo informa en `pendientes.pagos_sin_firma`).
9. **Una línea de venta lleva un nombre y un número de participantes**
   (`cantidad`), y una lista de `recorridos`: uno para un tour, uno por cada tour
   de un paquete. Cada recorrido tiene su propia fecha y hora (un paquete puede
   repartirse en días distintos); al elegir una hora se sugiere encadenado el
   siguiente, y una hora que choca con otro recorrido del grupo queda bloqueada.
   Todos llevan las mismas personas: antes había un contador de cantidad y otro de
   personas por reservación que se desincronizaban.
   La línea que recibe `LineaCarrito` es una copia de `carrito.calculadas`: nada se
   escribe directo en ella (sin `v-model`), todo pasa por acciones del store.
   No hay nombre para toda la venta: `ventas.nombre` lo arma `RegistrarVentaAction`
   con los nombres distintos de las líneas, separados por coma.
10. **Los horarios salen de `/pos-api/disponibilidad`**, que usa el mismo
   `Disponibilidad` que la tienda, pero con anticipación 0: en mostrador se vende la
   salida que viene enseguida, mientras que el sitio web exige una hora de margen.
11. **Una venta de total cero se registra sin pagos.** Pasa con los códigos de
   cortesía que cubren el importe completo; `pagos` es `present|array`, no
   `required|min:1`, y `validarPagos` sale temprano cuando el total es cero.
12. **En la lista de reservas se puede mover, bajar personas y cambiar recorrido,
   y la venta NO se toca** (sin reembolso). Mover no valida cupo ni ocupación: se
   puede juntar un grupo con otro o pasarse de la capacidad. La agenda muestra por
   horario las personas de cada recorrido contra su cupo.
   **Subir personas se cobra** (`AmpliarReservaAction`): es una venta nueva del
   turno ligada a la original por `ventas.venta_original_id`, al precio por persona
   que se cobró en la venta original (con su descuento). En un paquete se suben
   todos sus recorridos y se cobra el paquete. `ReservaController@actualizar`
   rechaza subir personas sin pasar por ahí. Cancelar una ampliación devuelve el
   dinero en el corte pero no baja las personas: eso se ajusta a mano.

### Subida de comprobantes: el campo se tiene que llamar `imagen`

`Egreso` y `EmpleadoPago` definen `setImagenAttribute()`, que delega en
`uploadFileToDisk()` de Backpack. Ese método **ignora el valor que recibe** y lee
`request()->file('imagen')` por su cuenta. Consecuencias:

- El campo del formulario debe llamarse exactamente `imagen`. Con cualquier otro
  nombre el registro se guarda con la columna vacía, sin error.
- No hay que llamar a `->store()` antes: eso deja el archivo huérfano en disco y
  la columna igual queda vacía.

Las URLs de comprobante se arman como `asset('storage/pagos/' . $ruta)`. No uses
`Storage::disk('pagos')->url()`: el disco declara `url => APP_URL.'/storage'`,
que omite el segmento `pagos` de su propia raíz y devuelve una ruta rota.

Requiere el symlink `public/storage` (`php artisan storage:link`).

### Cuidado al tocar el SPA

`App.vue` monta el `RouterView` cuando `sesion.inicializado` es true, **no**
cuando `cargando` es false. Condicionarlo a `cargando` desmonta la vista activa
en cada refresco de sesión y el cajero pierde lo que tenga en pantalla — por
ejemplo el ticket de corte, que se muestra justo después de refrescar.

## Convenciones del código

- Dominio y mensajes de error **en español**; el andamiaje de Laravel/Backpack en inglés.
- Los `CrudController` de `app/Http/Controllers/Admin` sirven doble propósito: CRUD de
  Backpack (`setup*Operation`) **y** endpoints JSON de API (`fetch`, `make`, `cancel`, ...).
  Al editar uno, considerar ambos consumidores.
- Patrón de API: `try / catch (\Exception) / response()->json(['error' => ...], 4xx|5xx)`.
  Ya no se devuelve `$e->getTrace()` en ninguna respuesta: el detalle va al log del
  servidor. No reintroducir ese patrón.
- Filtros de listado vía `scopeSearch($query, $search)` recibiendo un `(object)` con
  llaves nullables, y `scopeFilters($query, array)` para los reportes.
- Los modelos usan `RevisionableTrait` (venturecraft/revisionable) para auditoría.

## Comandos

```bash
php artisan serve                # o Valet: https://stranger_themes.test
npm run watch                    # compila el dashboard y el POS
php artisan migrate
php artisan remember:command     # recordatorios de reserva (agendado c/15 min)
php artisan pos:duplicados-online   # diagnóstico de ventas online duplicadas
php artisan tienda:limpiar-pendientes  # marca compras web abandonadas (agendado c/10 min)
```

**No ejecutes `vendor/bin/phpunit` sin revisar antes `phpunit.xml`**: la línea
que fija `DB_CONNECTION` a sqlite está comentada, así que los tests corren
contra la base de desarrollo real. Un test con `RefreshDatabase` la borraría.

## Cosas a tener en cuenta

- **Nunca uses `Auth::loginUsingId()` para que `backpack_user()` deje de ser null.**
  `ProductoCrudController`, `DescuentoCrudController` y `ReservaCrudController` lo hacían
  en `setup()`, que corre en el constructor de todo `CrudController`: cualquier petición
  anónima a `public/*` quedaba autenticada como el usuario 1. Si un `setup()` necesita
  permisos, envuélvelos en `if (backpack_user())`.

- **Laravel 8.83 sobre PHP 8.2.** Laravel 8 solo está *soportado* oficialmente hasta
  PHP 8.1, aunque su constraint (`^7.3|^8.0`) admite 8.2 y en la práctica funciona sin
  deprecaciones en este proyecto. Al actualizar dependencias, `composer update` a secas
  sobre un paquete concreto; **nunca `--with-all-dependencies`**, que arrastra Symfony 5
  a 6/7 y rompe Laravel 8.
- **`inertiajs/inertia-laravel` está fijado en `^0.6.11`**, la última rama que soporta
  PHP 8.2 y Laravel 8 a la vez. Subir a 1.x obliga a migrar también los paquetes npm
  (`@inertiajs/inertia-vue3` 0.x → `@inertiajs/vue3` 1.x) y a reescribir `app.js`.

- **`vendor/` está en `.gitignore` pero `vendor/backpack/crud/src/app/Models/Traits/CrudTrait.php`
  está trackeado individualmente** con un parche local. Cualquier `composer install` lo
  revierte silenciosamente.
- No hay cobertura de tests. `tests/` solo tiene los `ExampleTest` de Laravel.
- `POST /api/register` está abierto y roto (usa un campo `name` inexistente en `User` y
  `JWTAuth` sin importar). No es un endpoint en uso.
- Los binarios `wkhtmltopdf.exe` / `wkhtmltoimage.exe` (Windows, ~60 MB) están commiteados
  en la raíz.
- Historial de commits: mensajes repetidos tipo `hotfix: ...` / `debugging`, muchos
  duplicados. No sirve como referencia de qué cambió.

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { api, aIso, aFecha, fechaLarga, dinero, sinAcentos } from '../api';
import Paso from '../components/Paso.vue';

const cargando = ref(true);
const errorCarga = ref('');
const sucursales = ref([]);
const productos = ref([]);

const sucursalId = ref(null);
const productoId = ref(null);
const fecha = ref('');
const personas = ref(2);

const horarios = ref([]);
const horaElegida = ref('');
const buscandoHorarios = ref(false);
const avisoHorarios = ref('');

const codigo = ref('');
const descuento = ref(null);      // importes que devolvió el servidor
const validandoCodigo = ref(false);
const errorCodigo = ref('');

const cliente = reactive({ nombre: '', email: '', telefono: '' });

// Anti-bots: un campo que una persona no ve (si llega lleno, fue un bot) y el
// tiempo que tardó en llenar la pantalla, medido aquí para no depender del reloj
// del servidor. Ver CheckoutController::rechazarBots().
const trampa = ref('');
const cargadaEn = Date.now();

const modoPrueba = ref(false);
const parametros = new URLSearchParams(window.location.search);
const avisoRegreso = parametros.has('pago_invalido')
    ? 'No pudimos confirmar ese pago. Si se hizo un cargo, escríbenos con tu correo y lo revisamos.'
    : parametros.has('cancelado')
        ? 'Cancelaste el pago. Tu horario se liberó; puedes elegirlo de nuevo.'
        : '';
const enviando = ref(false);
const errorPago = ref('');

const sucursal = computed(() => sucursales.value.find((s) => s.id === sucursalId.value) || null);
const producto = computed(() => productos.value.find((p) => p.id === productoId.value) || null);

const productosDeSucursal = computed(() =>
    productos.value.filter((p) => p.sucursal_id === sucursalId.value)
);

const recorridos = computed(() => productosDeSucursal.value.filter((p) => p.tipo === 'tour'));
const paquetes = computed(() => productosDeSucursal.value.filter((p) => p.tipo === 'tour_paquete'));

const subtotal = computed(() => (producto.value ? producto.value.precio * personas.value : 0));
const ahorro = computed(() => (descuento.value ? descuento.value.descuento : 0));
const total = computed(() => (descuento.value ? descuento.value.total : subtotal.value));

const capacidad = computed(() => producto.value?.capacidad || 20);

/** Próximos 45 días en los que la sucursal abre. */
const diasDisponibles = computed(() => {
    if (!sucursal.value) return [];

    const abiertos = (sucursal.value.dias_abiertos || []).map(sinAcentos);
    const nombres = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const dias = [];

    for (let i = 0; i < 45 && dias.length < 24; i++) {
        const d = new Date(hoy);
        d.setDate(d.getDate() + i);
        if (abiertos.includes(nombres[d.getDay()])) dias.push(aIso(d));
    }

    return dias;
});

const puedePagar = computed(() =>
    producto.value && fecha.value && horaElegida.value &&
    cliente.nombre.trim().length >= 3 && /\S+@\S+\.\S+/.test(cliente.email) &&
    personas.value >= 1 && personas.value <= capacidad.value
);

onMounted(async () => {
    try {
        const datos = await api.catalogo();
        sucursales.value = datos.sucursales;
        productos.value = datos.productos;
        modoPrueba.value = !!datos.modo_prueba;
        if (sucursales.value.length === 1) sucursalId.value = sucursales.value[0].id;
    } catch (e) {
        errorCarga.value = e.message;
    } finally {
        cargando.value = false;
    }
});

// Cambiar de sucursal o de recorrido invalida lo elegido después, para que nadie
// termine pagando un horario que ya no corresponde a lo que ve en pantalla.
watch(sucursalId, () => { productoId.value = null; fecha.value = ''; horaElegida.value = ''; horarios.value = []; quitarCodigo(); });
// El código depende del producto y del número de personas, así que al cambiar
// cualquiera de los dos se revalida contra el servidor en vez de arrastrar
// importes que ya no corresponden.
watch(productoId, () => { horaElegida.value = ''; revalidarCodigo(); if (fecha.value) cargarHorarios(); });
watch(personas, () => revalidarCodigo());
watch(fecha, () => { horaElegida.value = ''; cargarHorarios(); });
watch(personas, () => {
    if (producto.value?.capacidad && personas.value > producto.value.capacidad) {
        personas.value = producto.value.capacidad;
    }
});
// Los horarios dependen del tamaño del grupo: uno con 3 lugares sirve para una
// pareja pero no para cinco amigos.
watch(personas, () => { if (fecha.value) cargarHorarios(); });

let consulta = 0;

async function cargarHorarios() {
    const esta = ++consulta;
    horarios.value = [];
    avisoHorarios.value = '';

    if (!sucursalId.value || !productoId.value || !fecha.value) return;

    buscandoHorarios.value = true;

    try {
        const datos = await api.disponibilidad({
            sucursal_id: sucursalId.value,
            producto_id: productoId.value,
            fecha: fecha.value,
            personas: personas.value,
        });

        // Con los botones de personas llegan varias respuestas; solo vale la última.
        if (esta !== consulta) return;

        horarios.value = datos.horarios;

        // El horario elegido pudo quedarse sin lugar para el grupo nuevo.
        if (horaElegida.value && !datos.horarios.some((h) => h.inicio === horaElegida.value)) {
            horaElegida.value = '';
        }

        if (!datos.horarios.length) {
            avisoHorarios.value = datos.abierto
                ? `No quedan lugares para ${personas.value} ${personas.value === 1 ? 'persona' : 'personas'} ese día. Prueba con otra fecha.`
                : 'Ese día no abrimos. Elige otra fecha.';
        }
    } catch (e) {
        avisoHorarios.value = e.primero;
    } finally {
        buscandoHorarios.value = false;
    }
}

async function aplicarCodigo() {
    const texto = codigo.value.trim();
    errorCodigo.value = '';

    if (!texto) { quitarCodigo(); return; }
    if (!productoId.value) { errorCodigo.value = 'Elige primero un recorrido.'; return; }

    validandoCodigo.value = true;

    try {
        const datos = await api.descuento({
            producto_id: productoId.value,
            personas: personas.value,
            codigo: texto,
        });

        if (!datos.valido) {
            descuento.value = null;
            errorCodigo.value = datos.mensaje;
            return;
        }

        descuento.value = datos;
    } catch (e) {
        descuento.value = null;
        errorCodigo.value = e.primero;
    } finally {
        validandoCodigo.value = false;
    }
}

function quitarCodigo() {
    codigo.value = '';
    descuento.value = null;
    errorCodigo.value = '';
}

/** Si ya había un código aplicado, se vuelve a validar con los datos nuevos. */
function revalidarCodigo() {
    if (descuento.value || errorCodigo.value) {
        if (codigo.value.trim()) aplicarCodigo(); else quitarCodigo();
    }
}

/**
 * Clave de idempotencia del intento de pago. Se conserva mientras la compra sea
 * la misma, para que un reintento tras perder la conexión recupere la sesión de
 * Stripe que el servidor ya creó en vez de apartar otra. Cambia si cambian los
 * datos o si el servidor contestó (aceptó o rechazó ese intento).
 */
let intento = null;

function nuevaClave() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();

    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
}

function claveDe(compra) {
    const firma = JSON.stringify(compra);

    if (!intento || intento.firma !== firma) {
        intento = { firma, clave: nuevaClave() };
    }

    return intento.clave;
}

async function pagar() {
    errorPago.value = '';
    enviando.value = true;

    const compra = {
        sucursal_id: sucursalId.value,
        producto_id: productoId.value,
        personas: personas.value,
        fecha: fecha.value,
        hora: horaElegida.value,
        nombre: cliente.nombre.trim(),
        email: cliente.email.trim(),
        telefono: cliente.telefono.trim() || null,
        codigo_descuento: descuento.value ? descuento.value.codigo : null,
    };

    try {
        const datos = await api.checkout({
            ...compra,
            clave_idempotencia: claveDe(compra),
            sitio_web: trampa.value,
            tiempo_llenado: Date.now() - cargadaEn,
        });

        window.location.href = datos.url;
    } catch (e) {
        // Con respuesta del servidor el intento quedó resuelto: el siguiente es
        // otro. Sin respuesta (se cayó la red) se reintenta con la misma clave.
        if (e.estado) intento = null;

        errorPago.value = e.primero || 'Se perdió la conexión. Revisa tu internet y vuelve a pulsar Pagar.';
        // El horario pudo haberse ocupado mientras llenaba sus datos.
        cargarHorarios();
        enviando.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <div class="mb-10 text-center">
            <h1 class="font-titular text-5xl leading-tight text-terror-rojo sm:text-6xl">Compra tus boletos</h1>
            <p class="mt-2 text-sm">Elige tu recorrido, tu horario y listo. Te enviamos los boletos por correo.</p>
        </div>

        <div v-if="modoPrueba" class="mb-6 border border-amber-500/60 bg-amber-500/10 p-4 text-sm text-amber-200">
            <strong class="text-amber-100">Modo de prueba:</strong> no se cobra dinero real.
            Paga con la tarjeta <span class="font-semibold tabular-nums">4242 4242 4242 4242</span>,
            cualquier fecha futura y cualquier CVC.
        </div>

        <p v-if="avisoRegreso" class="mb-6 border border-terror-borde p-4 text-sm">{{ avisoRegreso }}</p>

        <p v-if="cargando" class="py-16 text-center">Cargando recorridos…</p>
        <p v-else-if="errorCarga" class="panel p-6 text-center text-terror-rojoClaro">{{ errorCarga }}</p>

        <div v-else class="space-y-4">
            <!-- Sucursal: solo estorba si hay una sola -->
            <Paso v-if="sucursales.length > 1" :numero="1" titulo="Sucursal">
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="s in sucursales" :key="s.id" type="button"
                        class="border p-4 text-left transition"
                        :class="sucursalId === s.id ? 'border-terror-rojo bg-terror-rojo/10' : 'border-terror-borde hover:border-terror-rojo'"
                        @click="sucursalId = s.id"
                    >
                        <span class="block font-semibold text-white">{{ s.nombre }}</span>
                        <span class="mt-1 block text-xs">{{ s.direccion }}</span>
                    </button>
                </div>
            </Paso>

            <Paso :numero="sucursales.length > 1 ? 2 : 1" titulo="Recorrido" :activo="!!sucursalId">
                <div v-if="recorridos.length" class="mb-6">
                    <p class="etiqueta-terror">Recorridos individuales</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <button
                            v-for="p in recorridos" :key="p.id" type="button"
                            class="border p-4 text-left transition"
                            :class="productoId === p.id ? 'border-terror-rojo bg-terror-rojo/10' : 'border-terror-borde hover:border-terror-rojo'"
                            @click="productoId = p.id"
                        >
                            <span class="block font-semibold text-white">{{ p.nombre }}</span>
                            <span class="mt-1 block text-xs">Hasta {{ p.capacidad }} personas · {{ p.duracion_minutos }} min</span>
                            <span class="mt-2 block font-semibold text-terror-rojoClaro">{{ dinero(p.precio) }} <span class="text-xs font-normal text-terror-texto">por persona</span></span>
                        </button>
                    </div>
                </div>

                <div v-if="paquetes.length">
                    <p class="etiqueta-terror">Paquetes</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="p in paquetes" :key="p.id" type="button"
                            class="border p-4 text-left transition"
                            :class="productoId === p.id ? 'border-terror-rojo bg-terror-rojo/10' : 'border-terror-borde hover:border-terror-rojo'"
                            @click="productoId = p.id"
                        >
                            <span class="block font-semibold text-white">{{ p.nombre }}</span>
                            <span class="mt-1 block text-xs">{{ p.recorridos.map(r => r.nombre).join(' · ') }}</span>
                            <span class="mt-1 block text-xs">Hasta {{ p.capacidad }} personas · {{ p.duracion_minutos }} min en total</span>
                            <span class="mt-2 block font-semibold text-terror-rojoClaro">{{ dinero(p.precio) }} <span class="text-xs font-normal text-terror-texto">por persona</span></span>
                        </button>
                    </div>
                </div>
            </Paso>

            <Paso :numero="sucursales.length > 1 ? 3 : 2" titulo="Fecha y horario" :activo="!!productoId">
                <template v-if="producto">
                    <div class="mb-5">
                        <label class="etiqueta-terror">Participantes</label>
                        <div class="flex items-center gap-3">
                            <button type="button" class="border border-terror-borde px-4 py-2 text-lg text-white"
                                    :disabled="personas <= 1" @click="personas--">−</button>
                            <span class="tabular w-10 text-center text-xl font-semibold text-white">{{ personas }}</span>
                            <button type="button" class="border border-terror-borde px-4 py-2 text-lg text-white"
                                    :disabled="personas >= capacidad" @click="personas++">+</button>
                            <span class="text-xs">máximo {{ capacidad }} en {{ producto.nombre }}</span>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="etiqueta-terror">Fecha</label>
                        <div class="flex gap-2 overflow-x-auto pb-2">
                            <button
                                v-for="d in diasDisponibles" :key="d" type="button"
                                class="slot shrink-0 whitespace-nowrap"
                                :class="fecha === d ? 'slot-activo' : ''"
                                @click="fecha = d"
                            >{{ fechaLarga(d) }}</button>
                        </div>
                        <p v-if="!diasDisponibles.length" class="text-xs text-terror-rojoClaro">
                            Esta sucursal no tiene días de operación configurados.
                        </p>
                    </div>

                    <div v-if="fecha">
                        <label class="etiqueta-terror">Horario de inicio</label>
                        <p v-if="buscandoHorarios" class="text-sm">Buscando horarios…</p>
                        <p v-else-if="avisoHorarios" class="text-sm text-terror-rojoClaro">{{ avisoHorarios }}</p>
                        <div v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                            <button
                                v-for="h in horarios" :key="h.inicio" type="button"
                                class="slot flex flex-col items-center" :class="horaElegida === h.inicio ? 'slot-activo' : ''"
                                @click="horaElegida = h.inicio"
                            >
                                <span>{{ h.etiqueta }}</span>
                                <span v-if="h.lugares !== null" class="text-[10px] font-normal opacity-75">
                                    {{ h.lugares }} {{ h.lugares === 1 ? 'lugar' : 'lugares' }}
                                </span>
                            </button>
                        </div>
                        <p v-if="horaElegida && producto.recorridos.length > 1" class="mt-3 text-xs">
                            Los {{ producto.recorridos.length }} recorridos van uno tras otro a partir de esa hora.
                        </p>
                    </div>
                </template>
                <p v-else class="text-sm">Elige primero un recorrido.</p>
            </Paso>

            <Paso :numero="sucursales.length > 1 ? 4 : 3" titulo="Tus datos" :activo="!!horaElegida">
                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Campo trampa: fuera de pantalla, sin tabulación ni autocompletado. -->
                    <div aria-hidden="true" style="position:absolute; left:-10000px; width:1px; height:1px; overflow:hidden;">
                        <label>Sitio web <input v-model="trampa" type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="etiqueta-terror">Nombre completo</label>
                        <input v-model="cliente.nombre" type="text" class="campo-terror" placeholder="A nombre de quién va la reservación">
                    </div>
                    <div>
                        <label class="etiqueta-terror">Correo</label>
                        <input v-model="cliente.email" type="email" class="campo-terror" placeholder="Ahí llegan tus boletos">
                    </div>
                    <div>
                        <label class="etiqueta-terror">Teléfono <span class="normal-case">(opcional)</span></label>
                        <input v-model="cliente.telefono" type="tel" class="campo-terror">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="etiqueta-terror">Código de descuento <span class="normal-case">(opcional)</span></label>

                        <div v-if="descuento" class="flex items-center justify-between gap-3 border border-terror-rojo/60 bg-terror-rojo/10 px-4 py-3">
                            <span class="text-sm">
                                <span class="font-semibold text-white">{{ descuento.codigo }}</span>
                                · {{ descuento.porcentaje }}% de descuento
                            </span>
                            <button type="button" class="text-xs font-semibold uppercase tracking-[0.12em] text-terror-texto hover:text-white"
                                    @click="quitarCodigo">Quitar</button>
                        </div>

                        <div v-else class="flex gap-2">
                            <input
                                v-model="codigo" type="text" class="campo-terror uppercase"
                                placeholder="Si tienes uno, escríbelo aquí"
                                @keyup.enter="aplicarCodigo"
                            >
                            <button type="button" class="btn-linea shrink-0 px-5"
                                    :disabled="validandoCodigo || !codigo.trim()" @click="aplicarCodigo">
                                {{ validandoCodigo ? '…' : 'Aplicar' }}
                            </button>
                        </div>

                        <p v-if="errorCodigo" class="mt-2 text-sm text-terror-rojoClaro">{{ errorCodigo }}</p>
                    </div>
                </div>
            </Paso>

            <!-- Resumen y pago -->
            <div class="panel p-5 sm:p-6">
                <h2 class="mb-4 text-lg font-semibold">Resumen</h2>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt>Recorrido</dt>
                        <dd class="text-right text-white">{{ producto?.nombre || '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>Cuándo</dt>
                        <dd class="text-right text-white">
                            <span v-if="fecha && horaElegida">
                                {{ fechaLarga(fecha) }}, {{ horarios.find(h => h.inicio === horaElegida)?.etiqueta }}
                            </span>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>Participantes</dt>
                        <dd class="text-right text-white">{{ personas }}</dd>
                    </div>
                    <div v-if="ahorro > 0" class="flex justify-between gap-3">
                        <dt>Subtotal</dt>
                        <dd class="tabular text-right text-white">{{ dinero(subtotal) }}</dd>
                    </div>
                    <div v-if="ahorro > 0" class="flex justify-between gap-3">
                        <dt>Descuento <span class="text-white">{{ descuento.codigo }}</span></dt>
                        <dd class="tabular text-right text-terror-rojoClaro">−{{ dinero(ahorro) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-terror-borde pt-3 text-xl font-semibold">
                        <dt class="text-white">Total</dt>
                        <dd class="tabular text-terror-rojoClaro">{{ dinero(total) }}</dd>
                    </div>
                </dl>

                <p v-if="errorPago" class="mt-4 border border-terror-rojo/50 bg-terror-rojo/10 px-4 py-3 text-sm text-terror-rojoClaro">
                    {{ errorPago }}
                </p>

                <button type="button" class="btn-rojo mt-5 w-full" :disabled="!puedePagar || enviando" @click="pagar">
                    {{ enviando ? 'Llevándote al pago…' : `Pagar ${dinero(total)}` }}
                </button>

                <p class="mt-3 text-center text-xs">
                    Te llevamos a Stripe para completar el pago de forma segura.
                    Tu horario queda apartado 30 minutos.
                </p>
            </div>
        </div>
    </div>
</template>

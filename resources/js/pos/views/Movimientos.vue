<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api/cliente';
import { dinero, horaCorta } from '../utils/formato';
import CampoComprobante from '../components/CampoComprobante.vue';
import Icono from '../components/Icono.vue';
import TicketPagoEmpleado from '../components/TicketPagoEmpleado.vue';

const pestana = ref('egresos');

const egresos = ref([]);
const pagos = ref([]);
const totales = ref(null);
const empleados = ref([]);
const cargando = ref(true);
const error = ref('');

// Sin forma de pago: todo egreso del punto de venta sale del cajón en efectivo.
const formEgreso = reactive({
    monto: '',
    descripcion: '',
    comprobante: null,
});

// Sin comprobante aquí: el comprobante es la foto del ticket firmado, y ese
// ticket se imprime después de registrar el pago.
const formPago = reactive({
    empleado_id: '',
    monto: '',
});

// Recibo de pago abierto. Recién registrado se imprime solo para que el
// empleado lo firme; luego se sube la foto del ticket firmado.
const ticketPago = ref(null);
const autoImprimir = ref(false);

function abrirTicketPago(pago, imprimirAlAbrir = false) {
    autoImprimir.value = imprimirAlAbrir;
    ticketPago.value = pago;
}

async function alGuardarFirma() {
    ticketPago.value = null;
    exito.value = 'Ticket firmado guardado.';
    await cargar();
}

const guardando = ref(false);
const errorForm = ref('');
const exito = ref('');

async function cargar() {
    cargando.value = true;
    error.value = '';

    try {
        const [movimientos, catalogoEmpleados] = await Promise.all([
            api.get('movimientos'),
            api.get('empleados'),
        ]);

        egresos.value = movimientos.egresos;
        pagos.value = movimientos.pagos;
        totales.value = movimientos.totales;
        empleados.value = catalogoEmpleados.empleados;
    } catch (e) {
        error.value = e.message || 'No se pudieron cargar los movimientos.';
    } finally {
        cargando.value = false;
    }
}

onMounted(cargar);

function limpiarAviso() {
    errorForm.value = '';
    exito.value = '';
}

async function guardarEgreso() {
    limpiarAviso();
    guardando.value = true;

    try {
        await api.subir('egresos', {
            monto: formEgreso.monto,
            descripcion: formEgreso.descripcion,
            // El backend espera el archivo bajo 'imagen': el mutator del modelo
            // lo lee del request por ese nombre exacto.
            imagen: formEgreso.comprobante,
        });

        Object.assign(formEgreso, { monto: '', descripcion: '', comprobante: null });

        exito.value = 'Egreso registrado.';
        await cargar();
    } catch (e) {
        errorForm.value = e.listaErrores?.[0] || e.message || 'No se pudo registrar el egreso.';
    } finally {
        guardando.value = false;
    }
}

// Pagos que ya se le hicieron en este turno al empleado elegido. Pagarle dos
// veces puede ser legítimo (un adelanto y luego el resto), pero también es el
// error típico de registrar el mismo pago dos veces, así que se pide confirmar.
const pagosPrevios = computed(() =>
    pagos.value.filter((pago) => String(pago.empleado_id) === String(formPago.empleado_id))
);
const confirmandoRepetido = ref(false);

function intentarPago() {
    limpiarAviso();

    if (pagosPrevios.value.length) {
        confirmandoRepetido.value = true;
        return;
    }

    guardarPago();
}

async function confirmarRepetido() {
    confirmandoRepetido.value = false;
    await guardarPago();
}

async function guardarPago() {
    limpiarAviso();
    guardando.value = true;

    try {
        const respuesta = await api.post('empleados/pagos', {
            empleado_id: formPago.empleado_id,
            monto: formPago.monto,
        });

        Object.assign(formPago, { empleado_id: '', monto: '' });

        exito.value = 'Pago registrado.';
        await cargar();
        abrirTicketPago(respuesta.pago, true);
    } catch (e) {
        errorForm.value = e.listaErrores?.[0] || e.message || 'No se pudo registrar el pago.';
    } finally {
        guardando.value = false;
    }
}

</script>

<template>
    <div class="h-full min-h-0 overflow-y-auto">
        <div class="mx-auto max-w-5xl p-3 sm:p-6">
            <div class="mb-4">
                <h1 class="text-2xl font-bold text-slate-100">Movimientos de caja</h1>
                <p class="text-sm text-slate-400">
                    Salidas de dinero de este turno. Se registran con la fecha y hora del momento
                    y se descuentan del efectivo esperado en el corte.
                </p>
            </div>

            <div v-if="totales" class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                <div class="tarjeta p-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Egresos</p>
                    <p class="tabular text-lg font-bold text-amber-400">{{ dinero(totales.egresos) }}</p>
                </div>
                <div class="tarjeta p-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Pagos empleados</p>
                    <p class="tabular text-lg font-bold text-amber-400">{{ dinero(totales.pagos_empleados) }}</p>
                </div>
                <div class="tarjeta p-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Sale de caja</p>
                    <p class="tabular text-lg font-bold text-sangre-400">{{ dinero(totales.salida_efectivo) }}</p>
                </div>
            </div>

            <div class="mb-4 flex gap-1 rounded-lg bg-noche-700 p-1">
                <button
                    v-for="opcion in [
                        { clave: 'egresos', etiqueta: `Egresos (${egresos.length})` },
                        { clave: 'pagos', etiqueta: `Pagos a empleados (${pagos.length})` },
                    ]"
                    :key="opcion.clave"
                    type="button"
                    class="flex-1 rounded px-4 py-2.5 text-sm font-semibold transition"
                    :class="pestana === opcion.clave ? 'bg-slate-100 text-noche-900' : 'text-slate-400 hover:text-slate-100'"
                    @click="pestana = opcion.clave; limpiarAviso()"
                >
                    {{ opcion.etiqueta }}
                </button>
            </div>

            <p v-if="cargando" class="py-12 text-center text-slate-500">Cargando…</p>
            <p v-else-if="error" class="rounded-lg bg-sangre-600/15 px-4 py-3 text-sangre-400">{{ error }}</p>

            <div v-else class="grid gap-4 lg:grid-cols-5">
                <!-- Formulario -->
                <section class="tarjeta h-fit p-4 lg:col-span-2">
                    <h2 class="mb-4 text-base font-bold text-slate-100">
                        {{ pestana === 'egresos' ? 'Registrar egreso' : 'Registrar pago' }}
                    </h2>

                    <form v-if="pestana === 'egresos'" class="space-y-4" novalidate @submit.prevent="guardarEgreso">
                        <div>
                            <label class="etiqueta">Monto</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-slate-500">$</span>
                                <input v-model="formEgreso.monto" type="number" inputmode="decimal" min="0.01" step="0.01"
                                       class="campo tabular pl-8 text-lg font-semibold" placeholder="0.00">
                            </div>
                        </div>

                        <div>
                            <label class="etiqueta">¿En qué se gastó?</label>
                            <input v-model="formEgreso.descripcion" type="text" class="campo"
                                   placeholder="Ej. compra de insumos de limpieza" maxlength="255">
                        </div>


                        <CampoComprobante v-model="formEgreso.comprobante" />

                        <p v-if="errorForm" class="rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">{{ errorForm }}</p>
                        <p v-if="exito" class="flex items-center gap-2 rounded-lg bg-emerald-600/15 px-3 py-2 text-sm text-emerald-400">
                            <Icono nombre="check" clase="h-4 w-4" />{{ exito }}
                        </p>

                        <button type="submit" class="btn-primario w-full"
                                :disabled="guardando || !formEgreso.monto || !formEgreso.descripcion">
                            {{ guardando ? 'Guardando…' : 'Registrar egreso' }}
                        </button>
                    </form>

                    <form v-else class="space-y-4" novalidate @submit.prevent="intentarPago">
                        <div>
                            <label class="etiqueta">Empleado</label>
                            <select v-model="formPago.empleado_id" class="campo">
                                <option value="">Selecciona…</option>
                                <option v-for="empleado in empleados" :key="empleado.id" :value="empleado.id">
                                    {{ empleado.nombre }}<template v-if="empleado.puesto"> · {{ empleado.puesto }}</template>
                                </option>
                            </select>
                            <p v-if="!empleados.length" class="mt-1.5 text-xs text-amber-400">
                                No hay empleados activos en tu sucursal.
                            </p>
                        </div>

                        <div>
                            <label class="etiqueta">Monto</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-slate-500">$</span>
                                <input v-model="formPago.monto" type="number" inputmode="decimal" min="0.01" step="0.01"
                                       class="campo tabular pl-8 text-lg font-semibold" placeholder="0.00">
                            </div>
                        </div>

                        <p class="text-xs text-slate-500">
                            Al registrarlo se imprime un ticket para que el empleado lo firme. Después
                            sube la foto del ticket firmado: administración la revisa para autorizar la nómina,
                            y el corte no se puede hacer mientras falte alguna.
                        </p>

                        <p v-if="errorForm" class="rounded-lg bg-sangre-600/15 px-3 py-2 text-sm text-sangre-400">{{ errorForm }}</p>
                        <p v-if="exito" class="flex items-center gap-2 rounded-lg bg-emerald-600/15 px-3 py-2 text-sm text-emerald-400">
                            <Icono nombre="check" clase="h-4 w-4" />{{ exito }}
                        </p>

                        <button type="submit" class="btn-primario w-full"
                                :disabled="guardando || !formPago.empleado_id || !formPago.monto">
                            {{ guardando ? 'Guardando…' : 'Registrar pago' }}
                        </button>
                    </form>
                </section>

                <!-- Listado -->
                <section class="lg:col-span-3">
                    <template v-if="pestana === 'egresos'">
                        <p v-if="!egresos.length" class="tarjeta py-12 text-center text-slate-600">
                            No has registrado egresos en este turno.
                        </p>

                        <ul v-else class="space-y-2">
                            <li v-for="egreso in egresos" :key="egreso.id" class="tarjeta flex items-center gap-3 p-3">
                                <a v-if="egreso.comprobante" :href="egreso.comprobante" target="_blank" rel="noopener"
                                   class="shrink-0" title="Ver comprobante">
                                    <img :src="egreso.comprobante" alt="" class="h-12 w-12 rounded object-cover">
                                </a>
                                <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded bg-noche-700 text-slate-600">
                                    <Icono nombre="documento" clase="h-5 w-5" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-100">{{ egreso.descripcion }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ horaCorta(egreso.fecha) }}
                                    </p>
                                </div>

                                <span class="tabular shrink-0 text-lg font-bold text-amber-400">
                                    −{{ dinero(egreso.monto) }}
                                </span>
                            </li>
                        </ul>
                    </template>

                    <template v-else>
                        <p v-if="!pagos.length" class="tarjeta py-12 text-center text-slate-600">
                            No has registrado pagos en este turno.
                        </p>

                        <ul v-else class="space-y-2">
                            <li v-for="pago in pagos" :key="pago.id" class="tarjeta flex items-center gap-3 p-3">
                                <a v-if="pago.comprobante" :href="pago.comprobante" target="_blank" rel="noopener"
                                   class="shrink-0" title="Ver comprobante">
                                    <img :src="pago.comprobante" alt="" class="h-12 w-12 rounded object-cover">
                                </a>
                                <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded bg-noche-700 text-slate-600">
                                    <Icono nombre="usuario" clase="h-5 w-5" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-100">{{ pago.empleado }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ horaCorta(pago.fecha) }}
                                        <span v-if="!pago.comprobante" class="chip ml-1 bg-amber-500/15 text-amber-400">Falta firma</span>
                                    </p>
                                </div>

                                <span class="tabular shrink-0 text-lg font-bold text-amber-400">
                                    −{{ dinero(pago.monto) }}
                                </span>

                                <button v-if="pago.comprobante" type="button" class="btn-fantasma min-h-0 shrink-0 px-2.5 py-2"
                                        title="Ver o reimprimir ticket" @click="abrirTicketPago(pago)">
                                    <Icono nombre="imprimir" clase="h-5 w-5" />
                                </button>
                                <button v-else type="button" class="btn-primario min-h-0 shrink-0 px-3 py-2 text-sm"
                                        @click="abrirTicketPago(pago)">
                                    Subir firma
                                </button>
                            </li>
                        </ul>
                    </template>
                </section>
            </div>
        </div>

        <!-- Confirmación de pago repetido -->
        <div v-if="confirmandoRepetido" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            <div class="tarjeta w-full max-w-md p-5">
                <h2 class="flex items-center gap-2 text-lg font-bold text-amber-400">
                    <Icono nombre="alerta" clase="h-6 w-6" />
                    ¿Otro pago a la misma persona?
                </h2>
                <p class="mt-2 text-sm text-slate-300">
                    En este turno ya le pagaste a
                    <span class="font-semibold text-slate-100">{{ pagosPrevios[0]?.empleado }}</span>:
                </p>

                <ul class="mt-3 space-y-1 rounded-lg bg-noche-700 px-3 py-2 text-sm">
                    <li v-for="pago in pagosPrevios" :key="pago.id" class="flex justify-between">
                        <span class="text-slate-400">{{ horaCorta(pago.fecha) }} · folio #{{ pago.id }}</span>
                        <span class="tabular font-semibold text-slate-200">{{ dinero(pago.monto) }}</span>
                    </li>
                </ul>

                <p class="mt-3 text-sm text-slate-300">
                    ¿Seguro que quieres registrar otro pago de
                    <span class="tabular font-semibold text-amber-400">{{ dinero(formPago.monto) }}</span>?
                </p>

                <div class="mt-5 flex gap-2">
                    <button type="button" class="btn-neutro flex-1" @click="confirmandoRepetido = false">Cancelar</button>
                    <button type="button" class="btn-primario flex-1" @click="confirmarRepetido">Sí, registrar</button>
                </div>
            </div>
        </div>

        <TicketPagoEmpleado
            v-if="ticketPago"
            :pago="ticketPago"
            :auto-imprimir="autoImprimir"
            @cerrar="ticketPago = null"
            @guardado="alGuardarFirma"
        />
    </div>
</template>

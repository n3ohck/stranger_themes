<script setup>
import { computed, onMounted, ref } from 'vue';
import { useCatalogo } from '../stores/catalogo';
import { useCarrito } from '../stores/carrito';
import { dinero } from '../utils/formato';
import TarjetaProducto from '../components/TarjetaProducto.vue';
import LineaCarrito from '../components/LineaCarrito.vue';
import ModalCobro from '../components/ModalCobro.vue';
import TicketVenta from '../components/TicketVenta.vue';
import Icono from '../components/Icono.vue';

const catalogo = useCatalogo();
const carrito = useCarrito();

const grupoActivo = ref('todos');
const busqueda = ref('');
const cobrando = ref(false);
const errorCobro = ref('');
const ventaRecien = ref(null);
const carritoAbierto = ref(false);

onMounted(() => catalogo.cargar());

const productosVisibles = computed(() => {
    const grupo = catalogo.grupos.find((g) => g.clave === grupoActivo.value);
    let lista = catalogo.delGrupo(grupo?.tipos ?? null);

    const termino = busqueda.value.trim().toLowerCase();

    if (termino) {
        lista = lista.filter((p) =>
            p.nombre.toLowerCase().includes(termino) || String(p.codigo).toLowerCase().includes(termino)
        );
    }

    return lista;
});

const faltanDatos = computed(() => carrito.reservacionesIncompletas.length > 0);

function agregar(producto) {
    carrito.agregar(producto);
    carritoAbierto.value = true;
}

function abrirCobro() {
    if (!carrito.puedeCobrar) return;
    errorCobro.value = '';
    cobrando.value = true;
}

async function cobrar({ pagos, cliente }) {
    errorCobro.value = '';
    carrito.cliente = cliente;

    try {
        const venta = await carrito.cobrar(pagos);
        cobrando.value = false;
        carritoAbierto.value = false;
        ventaRecien.value = venta;
    } catch (e) {
        errorCobro.value = e.listaErrores?.[0] || e.message || 'No se pudo registrar la venta.';
    }
}
</script>

<template>
    <div class="flex h-full min-h-0">
        <!-- Catálogo -->
        <section class="flex min-w-0 flex-1 flex-col">
            <div class="shrink-0 space-y-3 border-b border-noche-700 p-3 sm:p-4">
                <div class="flex gap-2">
                    <input
                        v-model="busqueda"
                        type="search"
                        class="campo py-2.5"
                        placeholder="Buscar producto o código…"
                    >
                    <button
                        type="button"
                        class="btn-neutro relative shrink-0 px-4 lg:hidden"
                        @click="carritoAbierto = true"
                    >
                        <Icono nombre="carrito" />
                        <span
                            v-if="carrito.lineas.length"
                            class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-acento-500 text-xs font-bold text-white"
                        >{{ carrito.lineas.length }}</span>
                    </button>
                </div>

                <div class="flex gap-1.5 overflow-x-auto pb-1">
                    <button
                        v-for="grupo in catalogo.grupos"
                        :key="grupo.clave"
                        type="button"
                        class="shrink-0 rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="grupoActivo === grupo.clave
                            ? 'bg-slate-100 text-noche-900'
                            : 'bg-noche-700 text-slate-400 hover:text-slate-100'"
                        @click="grupoActivo = grupo.clave"
                    >
                        {{ grupo.etiqueta }}
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4">
                <p v-if="catalogo.cargando" class="py-12 text-center text-slate-500">Cargando catálogo…</p>

                <p v-else-if="!productosVisibles.length" class="py-12 text-center text-slate-500">
                    No hay productos que coincidan.
                </p>

                <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    <TarjetaProducto
                        v-for="producto in productosVisibles"
                        :key="producto.id"
                        :producto="producto"
                        @agregar="agregar"
                    />
                </div>
            </div>
        </section>

        <!-- Carrito -->
        <aside
            class="fixed inset-0 z-40 flex w-full flex-col border-l border-noche-700 bg-noche-800 lg:static lg:z-auto lg:w-[26rem] xl:w-[30rem]"
            :class="carritoAbierto ? 'flex' : 'hidden lg:flex'"
        >
            <div class="flex shrink-0 items-center justify-between border-b border-noche-700 px-4 py-3">
                <h2 class="text-base font-bold text-slate-100">
                    Venta actual
                    <span v-if="carrito.lineas.length" class="text-slate-500">· {{ carrito.lineas.length }}</span>
                </h2>
                <div class="flex items-center gap-1">
                    <button
                        v-if="carrito.lineas.length"
                        type="button"
                        class="btn-fantasma min-h-0 px-3 py-1.5 text-sm"
                        @click="carrito.limpiar()"
                    >
                        Vaciar
                    </button>
                    <button type="button" class="btn-fantasma min-h-0 px-3 py-1.5 lg:hidden" @click="carritoAbierto = false">
                        Cerrar
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                <div v-if="carrito.vacio" class="flex h-full flex-col items-center justify-center gap-3 text-center text-slate-600">
                    <Icono nombre="carrito" clase="h-12 w-12" />
                    <p class="text-sm">Agrega productos para iniciar la venta</p>
                </div>

                <LineaCarrito
                    v-for="linea in carrito.calculadas"
                    :key="linea.uid"
                    :linea="linea"
                />
            </div>

            <div v-if="!carrito.vacio" class="shrink-0 space-y-3 border-t border-noche-700 bg-noche-900/60 p-4">
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between text-slate-400">
                        <span>Subtotal</span>
                        <span class="tabular">{{ dinero(carrito.subtotal) }}</span>
                    </div>
                    <div v-if="carrito.totalDescuento > 0" class="flex justify-between text-emerald-400">
                        <span>Descuento</span>
                        <span class="tabular">−{{ dinero(carrito.totalDescuento) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-noche-700 pt-2 text-xl font-bold text-slate-100">
                        <span>Total</span>
                        <span class="tabular">{{ dinero(carrito.total) }}</span>
                    </div>
                </div>

                <p v-if="faltanDatos" class="flex items-start gap-2 rounded-lg bg-amber-600/15 px-3 py-2 text-xs text-amber-400">
                    <Icono nombre="alerta" clase="h-4 w-4 shrink-0" />
                    <span>
                        Falta fecha u hora en
                        {{ carrito.reservacionesIncompletas.length }}
                        {{ carrito.reservacionesIncompletas.length === 1 ? 'reservación' : 'reservaciones' }}.
                    </span>
                </p>

                <button
                    type="button"
                    class="btn-primario w-full text-lg"
                    :disabled="!carrito.puedeCobrar"
                    @click="abrirCobro"
                >
                    Cobrar {{ dinero(carrito.total) }}
                </button>
            </div>
        </aside>

        <ModalCobro
            v-if="cobrando"
            :total="carrito.total"
            :guardando="carrito.guardando"
            :error="errorCobro"
            @cerrar="cobrando = false"
            @cobrar="cobrar"
        />

        <TicketVenta
            v-if="ventaRecien"
            :venta="ventaRecien"
            @cerrar="ventaRecien = null"
        />
    </div>
</template>

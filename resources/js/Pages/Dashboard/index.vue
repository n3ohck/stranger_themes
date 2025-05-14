<template>
    <div>
        <div class="row" v-if="esadmin">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body pt-0">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="block">
                                    <el-divider>Rango de fechas</el-divider>
                                    <el-date-picker
                                        @change="handleGet"
                                        style="width: 100%;"
                                        v-model="query.dates"
                                        type="daterange"
                                        range-separator="a"
                                        start-placeholder="Fecha inicio"
                                        end-placeholder="Fecha final">
                                    </el-date-picker>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="block">
                                    <el-divider>Sucursal</el-divider>
                                    <el-select v-model="query.sucursal" placeholder="Selecciona una sucursal" @change="handleGet"
                                               style="width: 100%;">
                                        <el-option
                                            v-for="item in sucursales"
                                            :key="item.id"
                                            :label="item.nombre"
                                            :value="item.id">
                                        </el-option>
                                    </el-select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="block">
                                    <el-divider>Estatus</el-divider>
                                    <el-select v-model="query.estatus" placeholder="Selecciona un estatus" @change="handleGet"
                                               style="width: 100%;">
                                        <el-option
                                            v-for="item in estatus"
                                            :key="item.value"
                                            :label="item.label"
                                            :value="item.value">
                                        </el-option>
                                    </el-select>
                                </div>
                            </div>
                            <div class="col-md-3 pull-right">
                                <el-divider>Buscar</el-divider>
                                <div class="block">
                                    <el-input v-model="query.search" placeholder="Buscar por folio"></el-input>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card" style="background: transparent !important; border: none !important; box-shadow: none !important; ">
                    <div class="col-md-12 p-0">
                        <div class="alert alert-warning font-weight-bold text-dark" role="alert" v-if="disputas > 0">Tienes ({{ disputas }}) posibles disputas en el mes en curso.</div>
                    </div>
                    <div class="card-header with-border">
                        <div class="row">
                            <div class="col-sm-2">
                                <div class="callout callout-info">
                                    <small class="text-muted">Ventas</small>
                                    <br>
                                    <strong class="h4">{{ cantidadVentas }}</strong>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="callout callout-info">
                                    <small class="text-muted">Reservas</small>
                                    <br>
                                    <strong class="h4">{{ cantidadReservas }}</strong>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="callout callout-danger">
                                    <small class="text-muted">Ventas totales</small>
                                    <br>
                                    <strong class="h4">${{ ventasTotales }}</strong>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="callout callout-warning">
                                    <small class="text-muted">Egresos</small>
                                    <br>
                                    <strong class="h4">${{ egresos }}</strong>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="callout callout-warning">
                                    <small class="text-muted">Salarios</small>
                                    <br>
                                    <strong class="h4">${{ salarios }}</strong>
                                </div>
                            </div>

                            <div class="col-sm-2">
                                <div class="callout callout-success">
                                    <small class="text-muted">Ganancia operativa</small>
                                    <br>
                                    <strong class="h4">${{ utilidad }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 p-0" style="margin: -17px 0 0 0;">
                                <el-tabs v-model="activeName" v-loading="loading">
                                    <el-tab-pane label="Resumen Ventas" name="first">
                                        <el-table
                                            :data="tableData.filter(data => !query.search || data.folio.toLowerCase().includes(query.search.toLowerCase()))"
                                            border
                                            v-loading="loading"
                                            height="380"
                                            :show-summary="true"
                                            style="width: 100%">
                                            <el-table-column
                                                sortable
                                                label="Folio"
                                                fixed="left"
                                                width="233"
                                                prop="folio">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                label="Fecha"
                                                width="143"
                                                prop="created_at">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                width="130"
                                                label="C. Descuento"
                                                align="left"
                                                prop="codigo_descuento">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                width="130"
                                                label="T. Tarjeta"
                                                align="right"
                                                :formatter="moneyFormat"
                                                prop="tarjeta">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                width="130"
                                                label="T. Efectivo"
                                                align="right"
                                                :formatter="moneyFormat"
                                                prop="efectivo">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                width="130"
                                                label="T. Descuento"
                                                align="right"
                                                :formatter="moneyFormat"
                                                prop="descuento">
                                            </el-table-column>
                                            <el-table-column
                                                width="130"
                                                sortable
                                                label="T. Venta"
                                                align="right"
                                                :formatter="moneyFormat"
                                                prop="total">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                width="130"
                                                label="Cambio"
                                                align="right"
                                                :formatter="moneyFormat"
                                                prop="cambio">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                label="Estatus"
                                                width="130"
                                                align="center"
                                                prop="estatus">
                                                <template #default="scope">
                                                    <el-tag v-if="scope.row.estatus === 'activo'" type="success">Activo</el-tag>
                                                    <el-tag v-else type="danger">Inactivo</el-tag>
                                                </template>
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                width="233"
                                                label="Sucursal"
                                                prop="sucursal">
                                            </el-table-column>
                                        </el-table>
                                        <div class="col-md-3 p-0 mt-4">
                                            <button class="btn btn-block btn-outline-primary" @click="handleExportToExcel" type="button">Exportar Excel</button>
                                        </div>
                                    </el-tab-pane>
                                    <el-tab-pane label="Resumen Productos" name="second">
                                        <el-table
                                            :data="tableDataProductos"
                                            border
                                            v-loading="loading"
                                            height="380"
                                            :show-summary="true"
                                            style="width: 100%">
                                            <el-table-column
                                                sortable
                                                label="Producto"
                                                fixed="left"
                                                prop="producto">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                label="C. Vendida"
                                                align="right"
                                                prop="cantidad">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                label="Total"
                                                align="right"
                                                :formatter="moneyFormat"
                                                prop="total">
                                            </el-table-column>
                                        </el-table>
                                        <div class="col-md-3 p-0 mt-4">
                                            <button class="btn btn-block btn-outline-primary" @click="handleExportToExcelProductos" type="button">Exportar Excel</button>
                                        </div>
                                    </el-tab-pane>
                                    <el-tab-pane label="Descuentos En Productos" name="third">
                                        <el-table
                                            :data="tableDataDescuentos"
                                            border
                                            v-loading="loading"
                                            height="380"
                                            :show-summary="true"
                                            style="width: 100%">
                                            <el-table-column
                                                sortable
                                                label="Fecha"
                                                fixed="left"
                                                prop="fecha">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                label="Producto"
                                                fixed="left"
                                                prop="producto">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                label="Precio Original"
                                                align="right"
                                                :formatter="moneyFormat"
                                                prop="precio">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                label="Precio Con Descuento"
                                                align="right"
                                                :formatter="moneyFormat"
                                                prop="total">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                label="Cantidad Descuento"
                                                align="right"
                                                :formatter="moneyFormat"
                                                prop="descuento">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                label="% Descuento"
                                                align="right"
                                                prop="porcentaje_descuento">
                                            </el-table-column>
                                            <el-table-column
                                                sortable
                                                label="Codigo Descuento"
                                                align="right"
                                                prop="codigo_descuento">
                                            </el-table-column>
                                        </el-table>
                                        <div class="col-md-3 p-0 mt-4">
                                            <button class="btn btn-block btn-outline-primary" @click="handleExportToExcelProductosDescuento" type="button">Exportar Excel</button>
                                        </div>
                                    </el-tab-pane>
                                </el-tabs>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script type="text/javascript">
import * as XLSX from 'xlsx-js-style';
export default {
    name: "Dashboard",
    components: {},
    props: {
        disputas:{
            type: Number,
            required: true,
            default: 0
        },
        sucursales: {
            type: Array,
            required: true
        },
        estatus: {
            type: Array,
            required: true,
            default: () => [
                {label: 'Activo', value: 'activo'},
                {label: 'Inactivo', value: 'cancelado'}
            ]
        },
        esadmin:{
            type: Boolean,
            default: false
        }
    },
    data: () => ({
        loading: false,
        tableData:[],
        tableDataProductos:[],
        tableDataDescuentos:[],
        query: {
            search: '',
            dates: null,
            estatus: null,
            sucursal: null
        },
        cantidadVentas:0,
        cantidadReservas:0,
        ventasTotales:0,
        egresos:0,
        salarios:0,
        utilidad:0,
        activeName: 'first'
    }),
    computed: {
    },
    methods: {
        handleGet(){
            this.loading = true;
            axios.get('/webapi/ventas/resumen',{
                params: this.query
            }).then(response => {
                this.tableData = response.data.ventas;
                this.cantidadVentas = response.data.cantidad_ventas;
                this.cantidadReservas = response.data.cantidad_reservaciones;
                this.ventasTotales = response.data.total_ventas;
                this.egresos = response.data.total_egresos;
                this.salarios = response.data.total_salarios;
                this.utilidad = response.data.utilidad_operativa;
            }).catch(error => {
                console.log(error);
            }).finally(() => {
                this.loading = false;
            })
            this.handleGetProductos();
            this.handleGetProductosDescuentos();
        },
        handleGetProductos(){
            this.loading = true;
            axios.get('/webapi/ventas/resumen/productos',{
                params: this.query
            }).then(response => {
                this.tableDataProductos = Object.values(response.data.productos);
            }).catch(error => {
                console.log(error);
            }).finally(() => {
                this.loading = false;
            })
        },
        handleGetProductosDescuentos(){
            this.loading = true;
            axios.get('/webapi/ventas/resumen/productos/descuentos',{
                params: this.query
            }).then(response => {
                this.tableDataDescuentos = Object.values(response.data.productos);
            }).catch(error => {
                console.log(error);
            }).finally(() => {
                this.loading = false;
            })
        },
        moneyFormat(value, row, column) {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN',
            }).format(parseFloat(column));
        },
        handleExportToExcel() {
            this.exportToExcel();
        },
        handleExportToExcelProductos() {
            this.exportToExcelProductos();
        },
        handleExportToExcelProductosDescuento() {
            this.exportToExcelProductosDescuento();
        },
        exportToExcel() {
            const data = this.tableData.map(item => ({
                folio: item.folio,
                created_at: item.created_at,
                codigo_descuento: item.codigo_descuento,
                tarjeta: item.tarjeta,
                efectivo: item.efectivo,
                descuento: item.descuento,
                total: item.total,
                cambio: item.cambio,
                estatus: item.estatus,
                sucursal: item.sucursal
            }));

            const worksheet = XLSX.utils.json_to_sheet(data);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Resumen Ventas');

            // Generar el archivo Excel
            XLSX.writeFile(workbook, 'resumen_ventas.xlsx');
        },
        exportToExcelProductos() {
            const data = this.tableDataProductos.map(item => ({
                producto: item.producto,
                cantidad: item.cantidad,
                total: item.total
            }));

            const worksheet = XLSX.utils.json_to_sheet(data);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Resumen Ventas Productos');

            // Generar el archivo Excel
            XLSX.writeFile(workbook, 'resumen_ventas_productos.xlsx');
        },
        exportToExcelProductosDescuento() {
            const data = this.tableDataProductos.map(item => ({
                fecha: item.fecha,
                producto: item.producto,
                precio: item.precio,
                total: item.total,
                descuento: item.descuento,
                porcentaje: item.porcentaje_descuento,
                codigo_descuento: item.codigo_descuento
            }));

            const worksheet = XLSX.utils.json_to_sheet(data);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Resumen Ventas Productos');

            // Generar el archivo Excel
            XLSX.writeFile(workbook, 'resumen_ventas_productos.xlsx');
        }
    },
    created() {
        this.handleGet();
        this.handleGetProductos();
        this.handleGetProductosDescuentos();
    }
};
</script>
<style>
.w-100 {
    width: 100%;
}

.paddingTop {
    padding-top: 10px;
}

.card-title {
    padding: 11px 0 0 0;
}
</style>

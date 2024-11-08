<template>
    <div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body pt-0">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="block">
                                    <el-divider>Rango de fechas</el-divider>
                                    <el-date-picker
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
                                    <el-select v-model="query.sucursal" placeholder="Selecciona una sucursal"
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
                                    <el-select v-model="query.estatus" placeholder="Selecciona un estatus"
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
                <div class="card">
                    <div class="card-header with-border">
                        <div class="row">
                            <div class="col-sm-2">
                                <div class="callout callout-info">
                                    <small class="text-muted">Ventas</small>
                                    <br>
                                    <strong class="h4">10</strong>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="callout callout-info">
                                    <small class="text-muted">Reservas</small>
                                    <br>
                                    <strong class="h4">6</strong>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="callout callout-danger">
                                    <small class="text-muted">Ventas totales</small>
                                    <br>
                                    <strong class="h4">$8,700.10</strong>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="callout callout-warning">
                                    <small class="text-muted">Egresos</small>
                                    <br>
                                    <strong class="h4">$800.00</strong>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="callout callout-warning">
                                    <small class="text-muted">Salarios</small>
                                    <br>
                                    <strong class="h4">$7,000.00</strong>
                                </div>
                            </div>

                            <div class="col-sm-2">
                                <div class="callout callout-success">
                                    <small class="text-muted">Ganancia operativa</small>
                                    <br>
                                    <strong class="h4">$900.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="row">
                            <div class="col-md-12">
                                <el-table
                                    :data="tableData.filter(data => !query.search || data.folio.toLowerCase().includes(query.search.toLowerCase()))"
                                    border
                                    v-loading="loading"
                                    height="380"
                                    :show-summary="true"
                                    style="width: 100%">
                                    <el-table-column
                                        label="Folio"
                                        width="233"
                                        fixed="left"
                                        prop="folio">
                                    </el-table-column>
                                    <el-table-column
                                        label="Fecha"
                                        width="143"
                                        prop="created_at">
                                    </el-table-column>
                                    <el-table-column
                                        label="T. Tarjeta"
                                        align="right"
                                        :formatter="moneyFormat"
                                        prop="tarjeta">
                                    </el-table-column>
                                    <el-table-column
                                        label="T. Efectivo"
                                        align="right"
                                        :formatter="moneyFormat"
                                        prop="efectivo">
                                    </el-table-column>
                                    <el-table-column
                                        width="105"
                                        label="T. Descuento"
                                        align="right"
                                        :formatter="moneyFormat"
                                        prop="descuento">
                                    </el-table-column>
                                    <el-table-column
                                        label="T. Venta"
                                        align="right"
                                        :formatter="moneyFormat"
                                        prop="total">
                                    </el-table-column>
                                    <el-table-column
                                        label="Cambio"
                                        align="right"
                                        :formatter="moneyFormat"
                                        prop="cambio">
                                    </el-table-column>
                                    <el-table-column
                                        label="Estatus"
                                        align="center"
                                        prop="estatus">
                                        <template #default="scope">
                                            <el-tag v-if="scope.row.estatus === 'Activo'" type="success">Activo</el-tag>
                                            <el-tag v-else type="danger">Inactivo</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column
                                        width="233"
                                        label="Sucursal"
                                        prop="sucursal">
                                    </el-table-column>
                                </el-table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 p-0">
                    <button class="btn btn-block btn-outline-primary" @click="handleExportToExcel" type="button">Exportar Excel</button>
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
        sucursales: {
            type: Array,
            required: true
        },
        estatus: {
            type: Array,
            required: true,
            default: () => [
                {label: 'Activo', value: 'activo'},
                {label: 'Inactivo', value: 'inactivo'}
            ]
        }
    },
    data: () => ({
        loading: false,
        tableData: [
            {
                folio: 'VEN-001',
                created_at: '2024-11-01 14:01:34',
                tarjeta: 300,
                efectivo: 145.50,
                descuento: 0,
                total: 445.50,
                cambio: 0,
                estatus: 'Activo',
                sucursal: 'STRANGER THEMES'
            },
            {
                folio: 'VEN-002',
                created_at: '2024-11-02 14:05:23',
                tarjeta: 0,
                efectivo: 560.00,
                descuento: 0,
                total: 560.00,
                cambio: 0,
                estatus: 'Activo',
                sucursal: 'STRANGER THEMES'
            },
            {
                folio: 'VEN-003',
                created_at: '2024-11-03 14:15:13',
                tarjeta: 1009.9,
                efectivo: 0,
                descuento: 0,
                total: 1009.0,
                cambio: 0,
                estatus: 'Activo',
                sucursal: 'STRANGER THEMES'
            },
            {
                folio: 'VEN-004',
                created_at: '2024-11-03 14:35:03',
                tarjeta: 1060.9,
                efectivo: 0,
                descuento: 0,
                total: 1060.9,
                cambio: 0,
                estatus: 'Activo',
                sucursal:'STRANGER THEMES'
            },
            {
                folio: 'VEN-005',
                created_at: '2024-11-03 14:36:12',
                tarjeta: 0,
                efectivo: 870.9,
                descuento: 0,
                total: 870.9,
                cambio: 0,
                estatus: 'Activo',
                sucursal:'STRANGER THEMES'
            },
            {
                folio: 'VEN-006',
                created_at: '2024-11-03 14:37:33',
                tarjeta: 0,
                efectivo: 1220.9,
                descuento: 0,
                total: 1220.9,
                cambio: 0,
                estatus: 'Activo',
                sucursal:'STRANGER THEMES'
            },
            {
                folio: 'VEN-007',
                created_at: '2024-11-03 14:38:12',
                tarjeta: 0,
                efectivo: 572,
                descuento: 0,
                total: 572,
                cambio: 0,
                estatus: 'Activo',
                sucursal:'STRANGER THEMES'
            },
            {
                folio: 'VEN-008',
                created_at: '2024-11-03 14:39:11',
                tarjeta: 1400.9,
                efectivo: 0,
                descuento: 0,
                total: 1400.9,
                cambio: 0,
                estatus: 'Activo',
                sucursal:'STRANGER THEMES'
            },
            {
                folio: 'VEN-009',
                created_at: '2024-11-03 14:41:09',
                tarjeta: 450,
                efectivo: 650,
                descuento: 0,
                total: 1100,
                cambio: 0,
                estatus: 'Activo',
                sucursal:'STRANGER THEMES'
            },
            {
                folio: 'VEN-010',
                created_at: '2024-11-03 14:56:19',
                tarjeta: 0,
                efectivo: 460,
                descuento: 0,
                total: 460,
                cambio: 0,
                estatus: 'Activo',
                sucursal:'STRANGER THEMES'
            }

        ],
        query: {
            search: '',
            dates: null,
            estatus: null,
            sucursal: null
        },
    }),
    computed: {

    },
    methods: {
        handleGet(){
            this.loading = true;
            axios.get('/webapi/ventas/resumen',{
                params: this.query
            }).then(response => {
                this.tableData = response.data;
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
        exportToExcel() {
            const data = this.tableData.map(item => ({
                folio: item.folio,
                created_at: item.created_at,
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
        }
    },
    created() {
        this.handleGet();
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

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
                                    <branch-selector v-model="query.branch"></branch-selector>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="block">
                                    <el-divider>Origen</el-divider>
                                    <el-select v-model="query.origins" placeholder="Selecciona un origen"
                                               multiple
                                               style="width: 100%;">
                                        <el-option
                                            v-for="item in origins"
                                            :key="item.value"
                                            :label="item.label"
                                            :value="item.value">
                                        </el-option>
                                    </el-select>
                                </div>
                            </div>
                            <div class="col-md-3 pull-right">
                               <el-button type="info" class="mt-5 w-100" plain @click="handleGet">Buscar</el-button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
            <div class="col-md-12">
                <el-row v-loading="loading" style="width: 117.9%;">
                    <el-col :span="5" v-for="payment in data" style="margin: 0 10px 10px 0;">
                        <el-card :body-style="{ padding: '0px' }">
                            <el-image
                                class="image"
                                :src="payment.image"
                                :preview-src-list="[payment.image]">
                            </el-image>
                            <div style="padding: 14px;">
                                <span>{{ this.$options.filters.moneyFormat(payment.amount) }}</span>
                                <div class="bottom clearfix">
                                    <time class="time">{{ payment.date }}</time>
                                    <el-button type="text" class="button">{{ payment.type }}</el-button>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </div>
            <div class="col-md-12 pull-right">
                <div class="card p-0">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h5>Total Egresos</h5>
                                <h5>{{ this.$options.filters.moneyFormat(totals.egreso) }}</h5>
                            </div>
                            <div class="col-md-4">
                                <h5>Total Empleados</h5>
                                <h5>{{ this.$options.filters.moneyFormat(totals.pagoEmpleado) }}</h5>
                            </div>
                            <div class="col-md-4">
                                <h5>Total General</h5>
                                <h5>{{ this.$options.filters.moneyFormat((totals.egreso + totals.pagoEmpleado)) }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/javascript">
import BranchSelector from "../Components/branchSelector.vue";

export default {
    name: "PaymentReport",
    components: {BranchSelector},
    props: {
        branch: {
            type: Number,
            required: false
        },
        origin:{
            type: String,
            required: false
        },
        startDate:{
            type: String,
            required: false
        },
        endDate:{
            type: String,
            required: false
        },
        origins: {
            type: Array,
            required: true,
            default: () => [
                {label: 'Egresos', value: 'egreso'},
                {label: 'Empleados', value: 'pagoEmpleado'}
            ]
        }
    },
    data: () => ({
        loading: false,
        data:[],
        totals:{
            egreso: 0,
            pagoEmpleado: 0
        },
        query: {
            dates: [],
            origins: [],
            branch: null
        }
    }),
    computed: {
    },
    filters: {
        moneyFormat(value) {
            if (typeof value !== "number") {
                value = parseFloat(value);
            }

            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN',
            }).format(value);
        }
    },
    methods: {
        handleGet() {
            if( !this.query.dates ){
                this.$message.error('Debes seleccionar un rango de fechas');
                return;
            }

            if( !this.query.branch ){
                this.$message.error('Debes seleccionar una sucursal');
                return;
            }

            if( !this.query.origins ){
                this.$message.error('Debes seleccionar un origen');
                return;
            }

            this.loading = true;
            axios.get('/webapi/reports/payments', {params: this.query})
                .then(response => {
                    this.data = response.data.data;
                    this.totals = response.data.totals;
                    this.loading = false;
                })
                .catch(error => {
                    console.error("Error fetching payments:", error);
                    this.loading = false;
                });
        }
    },
    created() {
        if(this.branch) {
            this.query.branch = this.branch;
        }
        if(this.origin) {
            if (this.origin === 'empleado'){
                this.query.origins.push('pagoEmpleado');
            }else{
                this.query.origins.push('egreso');
            }
        }
        if (this.startDate){
            this.query.dates = [this.startDate, this.endDate];
            this.handleGet()
        }
    },
    watch: {
        'query.branch': function (newVal) {
            console.log('Sucursal seleccionada:', newVal);
        }
    }
};
</script>
<style>
.time {
    font-size: 13px;
    color: #999;
}

.bottom {
    margin-top: 13px;
    line-height: 12px;
}

.button {
    padding: 0;
    float: right;
}

.image {
    width: 100%;
    display: block;
}

.clearfix:before,
.clearfix:after {
    display: table;
    content: "";
}

.clearfix:after {
    clear: both
}
</style>

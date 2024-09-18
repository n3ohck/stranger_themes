<template>
    <div class="card" v-loading="loading">
        <div class="card-header">
            <h5 class="card-title">{{ title }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <Bar
                        v-if="!!chartData"
                        id="chartDisponibilidad"
                        :options="chartOptions"
                        :data="chartData"
                    />
                </div>
            </div>

        </div>
    </div>
</template>

<script>
import {Bar} from 'vue-chartjs'

export default {
    name: 'Chart',
    components: {
        Bar
    },
    data: () => ({
        loading: false,

        chartData: null,

        chartOptions: {}
    }),
    props: {
        title: {
            type: String,
            required: true
        },
        apiEndpoint: {
            type: String,
            required: true
        },
        form: {
            type: Object,
            required: true
        }
    },
    watch: {
        form: {
            deep: true,

            handler() {
                this.getData();
            }
        }
    },
    methods: {
        getData() {
            this.clearChart();
            this.loading = true;

            axios
                .post(this.apiEndpoint, this.form)
                .then(res => {
                    this.chartData = res.data;
                })
                .finally(() => this.loading = false)
        },

        clearChart() {
            this.chartData = null;
        },

        handleClickChart(point, event) {
            console.log(event[0])
        }
    },
    created() {
        this.getData();
    },
    mounted() {
        this.chartOptions = {
            responsive: true,
            onClick: this.handleClickChart
        }
    }
}
</script>

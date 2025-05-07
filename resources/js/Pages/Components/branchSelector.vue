<template>
    <el-select
        :value="value"
        @change="$emit('input', $event)"
        placeholder="Selecciona una sucursal"
        style="width: 100%;"
        v-loading="loading"
    >
        <el-option
            v-for="branch in branches"
            :key="branch.id"
            :label="branch.razon_social"
            :value="branch.id"
        />
    </el-select>
</template>

<script>
export default {
    name: "BranchSelector",
    props: {
        value: {
            type: [Number, String],
            default: null
        },
        endPoint: {
            type: String,
            default: '/webapi/branches'
        }
    },
    data() {
        return {
            loading: false,
            branches: []
        };
    },
    methods: {
        handleGetBranches() {
            this.loading = true;
            axios.get(this.endPoint)
                .then(response => {
                    this.branches = response.data.data;
                    this.loading = false;
                    if (!this.value && this.branches.length > 0) {
                        this.$emit('input', this.branches[0].id);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error("Error fetching branches:", error);
                });
        }
    },
    created() {
        this.handleGetBranches();
    }
}
</script>

<style scoped>

</style>

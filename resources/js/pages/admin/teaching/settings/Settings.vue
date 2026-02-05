<template>
    <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap ga-2 w-100 my-2 ml-1">
        <ItsMenuButton
            subtitle="Arbeiten & Noten"
            :icon="show_works ? 'mdi-eye' : 'mdi-eye-off'"
            :color="show_works ? 'success' : 'secondary'"
            @click="show_works = !show_works" />
        <ItsMenuButton
            subtitle="Benotung"
            :icon="show_grading ? 'mdi-eye' : 'mdi-eye-off'"
            :color="show_grading ? 'success' : 'secondary'"
            @click="show_grading = !show_grading" />
    </v-card>

    <v-col cols="12" md="6" xl="4" v-if="show_works">
        <WorksAndGrades />
    </v-col>
    <v-col cols="12" md="6" xl="4" v-if="show_grading">
        <Grading />
    </v-col>
</template>

<script>
import WorksAndGrades from './components/WorksAndGrades.vue'
import Grading from './components/Grading.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'

export default {
    components: { WorksAndGrades, Grading, ItsMenuButton },

    data() {
        return {
            show_works: true,
            show_grading: true,
        }
    },
}
</script>

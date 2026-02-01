<template>
    <v-col cols="12" md="6" xl="4">
        <WorksAndGrades />
    </v-col>
    <v-col cols="12" md="6" xl="4">
        <Grading />
    </v-col>
</template>

<script>
import WorksAndGrades from './components/WorksAndGrades.vue'
import Grading from './components/Grading.vue'

export default {
    components: { WorksAndGrades, Grading },
}
</script>

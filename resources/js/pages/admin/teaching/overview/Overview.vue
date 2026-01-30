<template>
    <!-- MENÜ FÜR OVERVIEW -->
    <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 my-2 ml-1" :disabled="action != ''">
        <its-menu-button
            subtitle="Meine Fächer"
            :icon="show_my_courses ? 'mdi-eye' : 'mdi-eye-off'"
            :color="show_my_courses ? 'success' : 'secondary'"
            @click="show_my_courses = !show_my_courses" />

        <its-menu-button
            subtitle="Schüler:innen"
            :icon="show_students ? 'mdi-eye' : 'mdi-eye-off'"
            :color="show_students ? 'success' : 'secondary'"
            @click="show_students = !show_students"
            v-if="selected_course" />
    </v-card>

    <!-- OVERVIEW-->
    <v-col cols="12" md="6" xl="4" :style="show_my_courses || (action == '' && show_students && selected_course) ? 'display: block;' : 'display: none;'">
        <v-row :style="show_my_courses ? 'display: block;' : 'display: none;'">
            <v-col>
                <MyCourses />
            </v-col>
        </v-row>
        <v-row :style="action == '' && show_students && selected_course ? 'display: block;' : 'display: none;'">
            <v-col>
                <CourseStudents />
            </v-col>
        </v-row>
    </v-col>

    <!-- KURS-INFOS -->
    <v-col cols="12" md="6" xl="4" :style="selected_course ? 'display: block;' : 'display: none;'">
        <ItsGridBox color="primary" :title="selected_course?.title + ' (' + selected_course?.classes?.join(', ') + ')'" icon="mdi-home" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100"></v-card>
            </div>
        </ItsGridBox>
    </v-col>

    <!-- STATISTIK -->
    <v-col cols="12" md="6" xl="4" :style="!selected_course ? 'display: block;' : 'display: none;'">
        <ItsGridBox color="primary" title="Statistiken" icon="mdi-chart-bell-curve-cumulative" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100"></v-card>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import MyCourses from './components/MyCourses.vue'
import CourseStudents from './components/CourseStudents.vue'

export default {
    components: { ItsGridBox, MyCourses, ItsMenuButton, CourseStudents },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.selected_course = null
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            courseStore: null,
            selected_course_old: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', , 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['selected_course', 'show_my_courses', 'show_students']),
    },

    watch: {},

    methods: {
        toggleShowMyCourses() {
            if (this.show_my_courses) {
                this.selected_course_old = { ...this.selected_course }
            }
            this.show_my_courses = !this.show_my_courses
            if (this.show_my_courses) {
                this.selected_course = { ...this.selected_course_old }
            }
        },
    },
}
</script>

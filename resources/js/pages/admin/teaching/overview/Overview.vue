<template>
    <!-- MENÜ FÜR OVERVIEW -->
    <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap ga-2 w-100 my-2 ml-1" :disabled="action != '' || action_2 != ''">
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

        <its-menu-button
            subtitle="Infos"
            :icon="show_infos ? 'mdi-eye' : 'mdi-eye-off'"
            :color="show_infos ? 'success' : 'secondary'"
            @click="show_infos = !show_infos"
            v-if="selected_course" />

        <its-menu-button
            subtitle="Termine"
            :icon="show_dates ? 'mdi-eye' : 'mdi-eye-off'"
            :color="show_dates ? 'success' : 'secondary'"
            @click="show_dates = !show_dates"
            v-if="selected_course" />
    </v-card>

    <!-- OVERVIEW-->
    <v-col cols="12" md="6" xl="4" v-if="show_my_courses || show_students">
        <!-- MY_COURSES-->
        <v-row v-if="show_my_courses">
            <v-col>
                <MyCourses />
            </v-col>
        </v-row>

        <!-- STUDENTS -->
        <v-row v-if="action != 'teaching_course_new_or_edit' && show_students">
            <v-col>
                <CourseStudents />
            </v-col>
        </v-row>
    </v-col>

    <!-- KURS-INFOS  -->
    <v-col cols="12" md="6" xl="4" v-if="show_infos || show_dates">
        <v-row v-if="show_infos">
            <v-col>
                <CourseInfos />
            </v-col>
        </v-row>

        <v-row v-if="show_dates">
            <v-col>
                <CourseDates />
            </v-col>
        </v-row>
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
import CourseInfos from './components/CourseInfos.vue'
import CourseDates from './components/CourseDates.vue'

export default {
    components: { ItsGridBox, MyCourses, ItsMenuButton, CourseStudents, CourseInfos, CourseDates },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.selected_course = null
        this.selected_course_id = null
        await this.courseStore.index()
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
        ...mapWritableState(useCourseStore, ['selected_course', 'selected_course_id', 'show_my_courses', 'show_students', 'show_infos', 'show_dates']),
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

<template>
    <div class="content-card">
        <div class="content-head">
            <v-icon size="26">mdi-book-open-variant</v-icon>
            <h2>Meine Fächer</h2>
            <v-chip v-if="!loading" size="small" class="ml-auto">{{ courses.length }}</v-chip>
        </div>
        <p class="content-copy">Hier findest du alle deine Fächer.</p>

        <!-- Loading State -->
        <div v-if="loading" class="courses-loading">
            <v-progress-circular indeterminate color="#fd802e" />
            <p>Lade Fächer...</p>
        </div>

        <!-- Courses Grid -->
        <div v-else-if="courses.length > 0" class="courses-grid">
            <div v-for="course in courses" :key="course.id" class="course-card" @click="handleCourseClick(course)">
                <div class="course-header">
                    <v-icon size="24" color="#fd802e">mdi-school</v-icon>
                </div>
                <h3 class="course-title">{{ course.title }}</h3>
                <p class="course-teacher">{{ course.teacher }}</p>
                <div class="course-footer">
                    <span v-if="course.classes && course.classes.length > 0" class="course-info">
                        <v-icon size="16">mdi-account-group</v-icon>
                        {{ course.classes.join(', ') }}
                    </span>
                    <span class="course-info">
                        <v-icon size="16">mdi-account-multiple</v-icon>
                        {{ course.students_count }} Schüler
                    </span>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="courses-empty">
            <v-icon size="64" color="#fd802e">mdi-book-off-outline</v-icon>
            <h3>Keine Fächer gefunden</h3>
            <p>Du bist derzeit in keinen Fächern eingeschrieben.</p>
        </div>
    </div>
</template>

<script>
import { useCourseStore } from '@/stores/student/CourseStore'

export default {
    async beforeMount() {
        await this.loadCourses()
    },

    data() {
        return {
            courseStore: useCourseStore(),
            courses: [],
            loading: false,
        }
    },

    methods: {
        async loadCourses() {
            this.loading = true
            try {
                const success = await this.courseStore.getCourses()
                if (success) {
                    this.courses = this.courseStore.courses || []
                }
            } catch (error) {
                console.error('Error loading courses:', error)
            } finally {
                this.loading = false
            }
        },

        handleCourseClick(course) {
            this.$router.push(`/student/course/${course.id}`)
        },
    },
}
</script>

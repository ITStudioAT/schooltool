<template>
    <v-navigation-drawer v-model="drawerModel" temporary location="right" width="320">
        <div class="drawer-header">
            <div class="drawer-user-info">
                <v-avatar color="#fd802e" size="56">
                    <span class="text-h6">{{ userInitials }}</span>
                </v-avatar>
                <div class="drawer-user-details">
                    <h3>{{ user?.first_name }} {{ user?.last_name }}</h3>
                    <p v-if="user?.schoolclass" style="font-weight: 600; margin-bottom: 2px;">{{ user.schoolclass }}</p>
                    <p>{{ user?.email }}</p>
                </div>
            </div>
        </div>

        <v-divider />

        <v-list>
            <v-list-item v-if="currentRoute !== 'overview'" prepend-icon="mdi-calendar-clock-outline" @click="handleOverview">
                <v-list-item-title>Übersicht</v-list-item-title>
                <v-list-item-subtitle>Zurück zum Stundenplanbereich</v-list-item-subtitle>
            </v-list-item>

            <v-list-item v-if="currentRoute !== 'password'" prepend-icon="mdi-lock-reset" @click="handlePasswordChange">
                <v-list-item-title>Passwort ändern</v-list-item-title>
                <v-list-item-subtitle>Ändern Sie Ihr Passwort</v-list-item-subtitle>
            </v-list-item>

            <v-list-item v-if="currentRoute !== 'profile'" prepend-icon="mdi-account-circle" @click="handleProfileView">
                <v-list-item-title>Ihr Profil</v-list-item-title>
                <v-list-item-subtitle>Zeigen Sie Ihre persönlichen Informationen</v-list-item-subtitle>
            </v-list-item>

            <v-divider class="my-2" />

            <v-list-item prepend-icon="mdi-logout" @click="handleLogout">
                <v-list-item-title>Abmelden</v-list-item-title>
                <v-list-item-subtitle>Von SEPP abmelden</v-list-item-subtitle>
            </v-list-item>
        </v-list>
    </v-navigation-drawer>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentTimetablesUserStore } from '@/stores/studentsTimetables/StudentTimetablesUserStore'

export default {
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        currentRoute: {
            type: String,
            default: '',
        },
    },

    emits: ['update:modelValue'],

    data() {
        return {
            studentTimetablesStore: null,
        }
    },

    computed: {
        ...mapWritableState(useStudentTimetablesUserStore, ['user']),

        drawerModel: {
            get() {
                return this.modelValue
            },
            set(value) {
                this.$emit('update:modelValue', value)
            },
        },

        userInitials() {
            if (!this.user) return '?'
            const first = this.user.first_name?.[0] || ''
            const last = this.user.last_name?.[0] || ''
            return (first + last).toUpperCase()
        },
    },

    beforeMount() {
        this.studentTimetablesStore = useStudentTimetablesUserStore()
    },

    methods: {
        handleOverview() {
            this.drawerModel = false
            this.$router.push('/students-timetables/overview')
        },

        handlePasswordChange() {
            this.drawerModel = false
            this.$router.push('/students-timetables/password')
        },

        handleProfileView() {
            this.drawerModel = false
            this.$router.push('/students-timetables/profile')
        },

        async handleLogout() {
            this.drawerModel = false
            await this.studentTimetablesStore.logout()
            this.$router.push('/homepage/students-timetables')
        },
    },
}
</script>

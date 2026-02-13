<template>
    <v-navigation-drawer v-model="drawerModel" temporary location="right" width="320">
        <div class="drawer-header">
            <div class="drawer-user-info">
                <v-avatar color="#fd802e" size="56">
                    <span class="text-h6">{{ userInitials }}</span>
                </v-avatar>
                <div class="drawer-user-details">
                    <h3>{{ user?.first_name }} {{ user?.last_name }}</h3>
                    <p>{{ user?.email }}</p>
                </div>
            </div>
        </div>

        <v-divider />

        <v-list>
            <v-list-item v-if="currentRoute !== 'password'" prepend-icon="mdi-lock-reset" @click="handlePasswordChange">
                <v-list-item-title>Passwort ändern</v-list-item-title>
                <v-list-item-subtitle>Ändere dein Passwort für mehr Sicherheit</v-list-item-subtitle>
            </v-list-item>

            <v-list-item v-if="currentRoute !== 'profile'" prepend-icon="mdi-account-circle" @click="handleProfileView">
                <v-list-item-title>Mein Profil</v-list-item-title>
                <v-list-item-subtitle>Zeige deine persönlichen Informationen</v-list-item-subtitle>
            </v-list-item>

            <v-list-item prepend-icon="mdi-cog" @click="handleSettings">
                <v-list-item-title>Einstellungen</v-list-item-title>
                <v-list-item-subtitle>Verwalte deine Benachrichtigungen und Präferenzen</v-list-item-subtitle>
            </v-list-item>

            <v-divider class="my-2" />

            <v-list-item prepend-icon="mdi-logout" @click="handleLogout">
                <v-list-item-title>Abmelden</v-list-item-title>
                <v-list-item-subtitle>Vom Unterrichtsbereich abmelden</v-list-item-subtitle>
            </v-list-item>
        </v-list>

        <!-- Coming Soon Dialog -->
        <v-dialog v-model="showComingSoonDialog" max-width="400">
            <v-card>
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon color="primary" icon="mdi-information" />
                    <span>Demnächst verfügbar</span>
                </v-card-title>
                <v-card-text class="pt-4">Diese Funktion ist noch in Entwicklung und wird bald verfügbar sein.</v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" @click="showComingSoonDialog = false">OK</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-navigation-drawer>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'

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
            studentStore: null,
            showComingSoonDialog: false,
        }
    },

    computed: {
        ...mapWritableState(useStudentStore, ['user']),

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
        this.studentStore = useStudentStore()
    },

    methods: {
        async handleLogout() {
            this.drawerModel = false
            await this.studentStore.logout()
            this.$router.push('/student')
        },

        handlePasswordChange() {
            this.drawerModel = false
            this.$router.push('/student/password')
        },

        handleProfileView() {
            this.drawerModel = false
            this.$router.push('/student/profile')
        },

        handleSettings() {
            this.drawerModel = false
            this.showComingSoonDialog = true
        },
    },
}
</script>

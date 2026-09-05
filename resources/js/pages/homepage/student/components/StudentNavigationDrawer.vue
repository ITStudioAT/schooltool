<template>
    <v-navigation-drawer v-model="drawerModel" class="student-menu-drawer" temporary location="right" width="320">
        <div class="drawer-header">
            <div class="drawer-topline">
                <span>Dein Lernraum</span>
                <v-btn variant="text" icon="mdi-close" aria-label="Menü schließen" @click="drawerModel = false" />
            </div>
            <div class="drawer-user-info">
                <v-avatar color="#4056d6" size="48">
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
            <v-list-item v-if="currentRoute !== 'overview'" data-testid="student-drawer-courses" prepend-icon="mdi-book-open-page-variant-outline" @click="handleCoursesView">
                <v-list-item-title>Meine Fächer</v-list-item-title>
                <v-list-item-subtitle>Zurück zu deiner Übersicht</v-list-item-subtitle>
            </v-list-item>

            <v-list-item data-testid="student-drawer-change-child" v-if="viewer_type === 'parent'" prepend-icon="mdi-account-switch" @click="handleParentStudentChange">
                <v-list-item-title>Kind wechseln</v-list-item-title>
                <v-list-item-subtitle>Unterrichtsbereich eines anderen Kindes öffnen</v-list-item-subtitle>
            </v-list-item>

            <v-list-item data-testid="student-drawer-password" v-if="viewer_type !== 'parent' && currentRoute !== 'password'" prepend-icon="mdi-lock-reset" @click="handlePasswordChange">
                <v-list-item-title>Passwort ändern</v-list-item-title>
                <v-list-item-subtitle>Ändere dein Passwort für mehr Sicherheit</v-list-item-subtitle>
            </v-list-item>

            <v-list-item data-testid="student-drawer-profile" v-if="currentRoute !== 'profile'" prepend-icon="mdi-account-circle" @click="handleProfileView">
                <v-list-item-title>Mein Profil</v-list-item-title>
                <v-list-item-subtitle>Zeige deine persönlichen Informationen</v-list-item-subtitle>
            </v-list-item>

            <v-list-item data-testid="student-drawer-settings" prepend-icon="mdi-cog" @click="handleSettings">
                <v-list-item-title>Einstellungen</v-list-item-title>
                <v-list-item-subtitle>Verwalte deine Benachrichtigungen und Präferenzen</v-list-item-subtitle>
            </v-list-item>

            <v-divider class="my-2" />

            <v-list-item data-testid="student-drawer-logout" prepend-icon="mdi-logout" @click="handleLogout">
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
        ...mapWritableState(useStudentStore, ['user', 'viewer_type']),

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

        handleParentStudentChange() {
            this.drawerModel = false
            this.$router.push({ path: '/student', query: { select_child: '1' } })
        },

        handleProfileView() {
            this.drawerModel = false
            this.$router.push('/student/profile')
        },

        handleCoursesView() {
            this.drawerModel = false
            this.$router.push('/student/overview')
        },

        handleSettings() {
            this.drawerModel = false
            this.showComingSoonDialog = true
        },
    },
}
</script>

<style scoped>
.student-menu-drawer {
    max-width: 100vw;
    color: #18243b;
    background: #fff;
    border-radius: 20px 0 0 20px;
}

.student-menu-drawer .drawer-header {
    padding: 16px 20px 24px;
    background: #f5f7fd;
    color: #18243b;
}

.drawer-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
    color: #4056d6;
    font-size: 0.82rem;
    font-weight: 700;
}

.student-menu-drawer .drawer-user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-menu-drawer .drawer-user-details {
    min-width: 0;
}

.student-menu-drawer .drawer-user-details h3 {
    color: #18243b;
    font-size: 1rem;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.student-menu-drawer .drawer-user-details p {
    color: #647086;
    font-size: 0.8rem;
    overflow-wrap: anywhere;
}

.student-menu-drawer :deep(.v-list) {
    padding: 12px;
}

.student-menu-drawer :deep(.v-list-item) {
    min-height: 68px;
    margin-bottom: 4px;
    border-radius: 12px;
}

.student-menu-drawer :deep(.v-list-item-title) {
    font-size: 0.92rem;
    font-weight: 600;
}

.student-menu-drawer :deep(.v-list-item-subtitle) {
    margin-top: 3px;
    font-size: 0.76rem;
    line-height: 1.45;
}

.student-menu-drawer :deep(.v-list-item__prepend .v-icon) {
    margin-inline-end: 16px;
    color: #4056d6;
    opacity: 1;
}

.student-menu-drawer :deep(.v-list-item:focus-visible) {
    outline: 3px solid #aab6f0;
    outline-offset: -3px;
}
</style>

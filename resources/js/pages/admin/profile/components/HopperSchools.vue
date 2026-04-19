<template>
    <div class="hopper-grid">
        <v-card rounded="xl" class="hopper-card" flat>
            <v-card-text class="pa-5">
                <div class="hopper-card__header mb-4">
                    <div class="hopper-card__header-icon-wrap">
                        <v-icon size="20" icon="mdi-account-switch-outline" />
                    </div>
                    <div>
                        <div class="hopper-card__header-title">Gespeicherte Hopper-Schulen</div>
                        <div class="hopper-card__header-sub">Konten für den schnellen Wechsel zwischen Schulen.</div>
                    </div>
                </div>

                <div v-if="hopper_accounts.length > 0" class="hopper-list">
                    <div v-for="account in hopper_accounts" :key="`hopper-account-${account.id}`" class="hopper-item">
                        <div class="hopper-item__copy">
                            <div class="hopper-item__title">{{ account.full_name || account.email }}</div>
                            <div class="hopper-item__meta">{{ account.email }}</div>
                            <div class="hopper-item__school">{{ account.school_label }}</div>
                        </div>

                        <div class="hopper-item__actions">
                            <v-btn color="primary" variant="flat" rounded="lg" size="small" @click="switchAccount(account)">
                                Wechseln
                            </v-btn>
                            <v-btn color="warning" variant="tonal" rounded="lg" size="small" @click="removeAccount(account)">
                                Entfernen
                            </v-btn>
                        </div>
                    </div>
                </div>

                <div v-else class="hopper-empty">
                    Noch keine Hopper-Konten gespeichert.
                </div>
            </v-card-text>

            <v-card-actions class="pa-5 pt-0">
                <v-btn color="primary" variant="flat" rounded="lg" @click="openAddForm">
                    <v-icon size="16" class="mr-1">mdi-plus</v-icon>
                    Konto hinzufügen
                </v-btn>
            </v-card-actions>
        </v-card>

        <v-card v-if="show_add_form" rounded="xl" class="hopper-card" flat>
            <v-card-text class="pa-5">
                <div class="hopper-card__header mb-4">
                    <div class="hopper-card__header-icon-wrap hopper-card__header-icon-wrap--accent">
                        <v-icon size="20" icon="mdi-account-search-outline" />
                    </div>
                    <div>
                        <div class="hopper-card__header-title">Hopper-Konto hinzufügen</div>
                        <div class="hopper-card__header-sub">Konto suchen und mit dessen Passwort bestätigen.</div>
                    </div>
                </div>

                <v-form ref="form" v-model="is_valid" @submit.prevent="saveAccount">
                    <v-text-field
                        v-model="email"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        label="E-Mail"
                        prepend-inner-icon="mdi-email-outline"
                        class="mb-3"
                        @keydown.enter.prevent="loadCandidateSchools" />

                    <div class="hopper-inline-actions mb-3">
                        <div class="hopper-inline-actions__copy">Schulen zu dieser E-Mail laden</div>
                        <v-btn color="primary" variant="tonal" rounded="lg" size="small" prepend-icon="mdi-refresh" @click="loadCandidateSchools">
                            Liste laden
                        </v-btn>
                    </div>

                    <v-text-field
                        v-model="last_name"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        label="Nachname"
                        prepend-inner-icon="mdi-account-search-outline"
                        class="mb-3"
                        @keydown.enter.prevent="loadCandidateUsers" />

                    <div v-if="hopper_user_match_chips.length > 0" class="mb-3">
                        <div class="hopper-section-label mb-2">Schnelltreffer nach Nachname</div>
                        <div class="hopper-chip-list">
                            <v-chip
                                v-for="chip in hopper_user_match_chips"
                                :key="`hopper-user-${chip.email}-${chip.school_id}`"
                                size="small"
                                color="primary"
                                variant="outlined"
                                @click="selectCandidate(chip)">
                                {{ chip.last_name }} {{ chip.first_name }} • {{ chip.email }} • {{ chip.school_label }}
                            </v-chip>
                        </div>
                    </div>

                    <v-autocomplete
                        v-model="selected_school_id"
                        :items="hopper_switchable_schools"
                        item-title="long_name"
                        item-value="id"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        label="Auswahl Schule"
                        class="mb-3"
                        hide-details="auto" />

                    <v-text-field
                        v-model="password"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        label="Passwort des ausgewählten Kontos"
                        :append-inner-icon="is_password_visible ? 'mdi-eye' : 'mdi-eye-off'"
                        :type="is_password_visible ? 'text' : 'password'"
                        @click:append-inner="is_password_visible = !is_password_visible" />
                </v-form>
            </v-card-text>

            <v-card-actions class="pa-5 pt-0 ga-2">
                <v-btn color="success" variant="flat" rounded="lg" @click="saveAccount" class="flex-1-1">
                    Speichern
                </v-btn>
                <v-btn color="warning" variant="text" rounded="lg" @click="cancelAddForm" class="flex-1-1">
                    Abbruch
                </v-btn>
            </v-card-actions>
        </v-card>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { resolveAdminRouteAccess } from '../../../../../routes/admin.js'

export default {
    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()

        await this.schoolStore.loadHopperAccounts()
    },

    data() {
        return {
            adminStore: null,
            schoolStore: null,
            show_add_form: false,
            is_valid: false,
            email: '',
            last_name: '',
            password: '',
            selected_school_id: null,
            is_password_visible: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        ...mapWritableState(useSchoolStore, ['hopper_accounts', 'hopper_switchable_schools', 'hopper_user_matches']),
        currentUserEmail() {
            return String(this.config?.user?.email || '').trim()
        },
        currentUserLastName() {
            return String(this.config?.user?.last_name || '').trim()
        },
        hopper_user_match_chips() {
            return (Array.isArray(this.hopper_user_matches) ? this.hopper_user_matches : [])
                .flatMap((match) => {
                    const schools = Array.isArray(match?.schools) ? match.schools : []

                    return schools.map((school) => ({
                        email: String(match?.email || '').trim(),
                        first_name: String(match?.first_name || '').trim(),
                        last_name: String(match?.last_name || '').trim(),
                        school_id: Number(school?.id || 0),
                        school_label: String(school?.label || '').trim(),
                    }))
                })
                .filter((chip) => chip.email && chip.school_id > 0)
        },
    },

    methods: {
        async openAddForm() {
            this.show_add_form = true
            this.email = this.currentUserEmail
            this.last_name = this.currentUserLastName
            this.password = ''
            this.selected_school_id = null
            await this.loadCandidateSchools()
            await this.loadCandidateUsers()
        },
        cancelAddForm() {
            this.show_add_form = false
            this.email = ''
            this.last_name = ''
            this.password = ''
            this.selected_school_id = null
            this.hopper_switchable_schools = []
            this.hopper_user_matches = []
        },
        async loadCandidateSchools() {
            const ok = await this.schoolStore.loadHopperSwitchableSchools(this.email)
            if (!ok) {
                return false
            }

            const selectedStillExists = (this.hopper_switchable_schools || []).some((school) => school?.id === this.selected_school_id)
            if (!selectedStillExists) {
                this.selected_school_id = null
            }

            return true
        },
        async loadCandidateUsers() {
            const search = String(this.last_name || '').trim()
            if (search === '') {
                this.hopper_user_matches = []
                return true
            }

            const result = await this.schoolStore.searchHopperUsers(search)
            return !!result
        },
        async selectCandidate(chip) {
            this.email = String(chip?.email || '').trim()
            await this.loadCandidateSchools()
            this.selected_school_id = Number(chip?.school_id || 0) || null
        },
        async saveAccount() {
            if (!this.selected_school_id || !String(this.email).trim() || !String(this.password).trim()) {
                return
            }

            const saved = await this.schoolStore.storeHopperAccount({
                school_id: this.selected_school_id,
                email: this.email,
                password: this.password,
            })

            if (!saved) {
                return
            }

            this.cancelAddForm()
        },
        async removeAccount(account) {
            await this.schoolStore.deleteHopperAccount(account.id)
        },
        async switchAccount(account) {
            const currentRouteTarget = this.$route?.fullPath || '/admin'

            if (!(await this.schoolStore.switchHopperAccount(account.id))) {
                return
            }

            await this.adminStore.loadConfig()
            await this.$nextTick()
            this.redirectToPostHopTarget(currentRouteTarget)
        },
        resolvePostHopTarget(target) {
            if (typeof target !== 'string' || target.trim() === '') {
                return '/admin'
            }

            const resolvedRoute = this.$router?.resolve(target)
            const normalizedPath = resolvedRoute?.path || target
            const routeAccess = resolveAdminRouteAccess(normalizedPath)

            if (!routeAccess) {
                return '/admin'
            }

            if (routeAccess.public || !routeAccess.capability) {
                return target
            }

            return this.adminStore?.config?.capabilities?.[routeAccess.capability] === true ? target : '/admin'
        },
        redirectToPostHopTarget(target) {
            window.location.assign(this.resolvePostHopTarget(target))
        },
    },
}
</script>

<style scoped>
.hopper-grid {
    display: grid;
    gap: 16px;
    width: 100%;
}

.hopper-card {
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.68)) !important;
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(10px);
    color: #112536 !important;
}

.hopper-card__header {
    display: flex;
    align-items: center;
    gap: 14px;
}

.hopper-card__header-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(180deg, #4f88b8, #2f628c);
    color: #fff;
    box-shadow: 0 8px 18px rgba(36, 76, 109, 0.22);
    flex-shrink: 0;
}

.hopper-card__header-icon-wrap--accent {
    background: rgba(63, 99, 255, 0.1);
    color: #3049b5;
    box-shadow: none;
}

.hopper-card__header-title {
    font-size: 1rem;
    font-weight: 700;
    color: #10263a;
    line-height: 1.2;
}

.hopper-card__header-sub {
    font-size: 0.78rem;
    color: rgba(16, 38, 58, 0.86);
    margin-top: 2px;
}

.hopper-list {
    display: grid;
    gap: 12px;
}

.hopper-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.78);
    transition: background-color 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
}

.hopper-item:hover {
    background: rgba(46, 104, 171, 0.045);
    border-color: rgba(46, 104, 171, 0.1);
    transform: translateY(-1px);
}

.hopper-item__copy {
    min-width: 0;
}

.hopper-item__title {
    font-weight: 700;
    color: #10263a;
}

.hopper-item__meta,
.hopper-item__school {
    font-size: 0.82rem;
    color: rgba(16, 38, 58, 0.86);
}

.hopper-item__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.hopper-empty {
    border: 1px dashed rgba(16, 38, 58, 0.14);
    border-radius: 14px;
    padding: 18px 16px;
    color: rgba(16, 38, 58, 0.92);
    background: rgba(255, 255, 255, 0.72);
}

.hopper-inline-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.hopper-inline-actions__copy,
.hopper-section-label {
    font-size: 0.78rem;
    color: rgba(16, 38, 58, 0.86);
}

.hopper-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.hopper-card :deep(.v-card-actions) {
    border-top: 1px solid rgba(16, 38, 58, 0.08);
}

.hopper-card :deep(.v-field) {
    border-radius: 12px !important;
    background: rgba(255, 255, 255, 0.8);
}

.hopper-card :deep(.v-field__input),
.hopper-card :deep(.v-label) {
    color: #112536 !important;
}

@media (max-width: 700px) {
    .hopper-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .hopper-item__actions,
    .hopper-inline-actions {
        width: 100%;
        justify-content: flex-start;
    }
}
</style>

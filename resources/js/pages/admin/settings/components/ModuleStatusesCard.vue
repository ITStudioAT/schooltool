<template>
    <v-sheet rounded="xl" class="module-status-card pa-5">
        <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-3">
            <div>
                <div class="text-overline module-status-card__eyebrow">Grundeinstellungen</div>
                <div class="text-h6 module-status-card__title">Sichtbarkeit der Module</div>
                <div class="text-body-2 module-status-card__subtitle">
                    Steuert die Anzeige und Freigabe app-weit.
                </div>
            </div>

            <v-chip size="small" color="primary" variant="flat" prepend-icon="mdi-earth" class="module-status-card__scope-chip">
                App-weit gültig!
            </v-chip>
        </div>

        <div class="module-status-card__grid">
            <div v-for="item in moduleRows" :key="item.key" class="module-status-card__row">
                <div class="module-status-card__copy">
                    <div class="module-status-card__module">{{ item.label }}</div>
                    <div class="module-status-card__meta">{{ item.meta }}</div>
                </div>

                <div class="module-status-card__switches">
                    <div class="module-status-card__toggle module-status-card__toggle--admin">
                        <v-switch
                            :model-value="form[item.adminVisibleField]"
                            color="primary"
                            inset
                            hide-details
                            density="compact"
                            :label="'Angezeigt Admin'"
                            @update:modelValue="setAdminVisible(item, $event)" />
                    </div>

                    <div class="module-status-card__toggle module-status-card__toggle--user">
                        <v-switch
                            :model-value="form[item.userVisibleField]"
                            color="success"
                            inset
                            hide-details
                            density="compact"
                            :label="'Angezeigt Benutzer'"
                            :disabled="!Boolean(form[item.adminVisibleField])"
                            @update:modelValue="setUserVisible(item, $event)" />
                    </div>

                    <div class="module-status-card__toggle module-status-card__toggle--test">
                        <v-switch
                            :model-value="form[item.userTestModeField]"
                            color="info"
                            inset
                            hide-details
                            density="compact"
                            :label="'Benutzer Testmodus'"
                            :disabled="Boolean(form[item.userVisibleField])"
                            @update:modelValue="setUserTestMode(item, $event)" />
                    </div>

                    <div class="module-status-card__toggle module-status-card__toggle--soon">
                        <v-switch
                            :model-value="form[item.userComingSoonField]"
                            color="warning"
                            inset
                            hide-details
                            density="compact"
                            :label="'Benutzer Kommt bald'"
                            :disabled="Boolean(form[item.userVisibleField]) || Boolean(form[item.userTestModeField])"
                            @update:modelValue="setUserComingSoon(item, $event)" />
                    </div>
                </div>
            </div>
        </div>

    </v-sheet>
</template>

<script>
import { useSchoolToolStore } from '@/stores/admin/SchoolToolStore'

const MODULE_ROWS = [
    { key: 'register', label: 'Anmeldetool', meta: 'Events und Anmeldungen' },
    { key: 'tutoring', label: 'Nachhilfe', meta: 'Schüler helfen Schülern' },
    { key: 'teaching', label: 'Unterricht', meta: 'Lehrer- und Schülerbereich' },
    { key: 'materials', label: 'Materialien', meta: 'Materialverwaltung und Freigaben' },
    { key: 'restaurant', label: 'Restaurant', meta: 'Menüpläne und Bestellungen' },
]

const moduleRows = () => MODULE_ROWS.map((item) => ({
    ...item,
    adminVisibleField: `${item.key}_visible_admin`,
    userVisibleField: `${item.key}_visible_user`,
    userTestModeField: `${item.key}_user_test_mode`,
    userComingSoonField: `${item.key}_user_comming_soon`,
}))

const defaultForm = () => moduleRows().reduce((form, item) => ({
    ...form,
    [item.adminVisibleField]: false,
    [item.userVisibleField]: false,
    [item.userTestModeField]: false,
    [item.userComingSoonField]: false,
}), {})

export default {
    name: 'ModuleStatusesCard',

    async beforeMount() {
        this.schoolToolStore = useSchoolToolStore()

        if (! this.schoolToolStore.data) {
            await this.schoolToolStore.loadConfig()
        }

        this.syncFormFromStore(this.schoolToolStore.data)
    },

    data() {
        return {
            schoolToolStore: null,
            form: defaultForm(),
            moduleRows: moduleRows(),
        }
    },

    watch: {
        'schoolToolStore.data': {
            handler(value) {
                this.syncFormFromStore(value)
            },
            deep: true,
        },
    },

    methods: {
        syncFormFromStore(data) {
            if (! data) {
                this.form = defaultForm()
                return
            }

            this.form = this.moduleRows.reduce((form, item) => {
                const adminVisible = Boolean(data[item.adminVisibleField])

                return {
                    ...form,
                    [item.adminVisibleField]: adminVisible,
                    [item.userVisibleField]: adminVisible ? Boolean(data[item.userVisibleField]) : false,
                    [item.userTestModeField]: Boolean(data[item.userTestModeField]),
                    [item.userComingSoonField]: Boolean(data[item.userComingSoonField]),
                }
            }, {})
        },

        setAdminVisible(item, value) {
            const isEnabled = Boolean(value)

            this.form[item.adminVisibleField] = isEnabled

            if (! isEnabled) {
                this.form[item.userVisibleField] = false
            }

            void this.save()
        },

        setUserVisible(item, value) {
            if (! this.form[item.adminVisibleField]) {
                this.form[item.userVisibleField] = false
                void this.save()
                return
            }

            const isEnabled = Boolean(value)

            this.form[item.userVisibleField] = isEnabled

            if (isEnabled) {
                this.form[item.userTestModeField] = false
                this.form[item.userComingSoonField] = false
            }

            void this.save()
        },

        setUserTestMode(item, value) {
            const isEnabled = Boolean(value)

            this.form[item.userTestModeField] = isEnabled

            if (isEnabled) {
                this.form[item.userVisibleField] = false
                this.form[item.userComingSoonField] = false
            }

            void this.save()
        },

        setUserComingSoon(item, value) {
            const isEnabled = Boolean(value)

            this.form[item.userComingSoonField] = isEnabled

            if (isEnabled) {
                this.form[item.userVisibleField] = false
                this.form[item.userTestModeField] = false
            }

            void this.save()
        },

        async save() {
            const id = this.schoolToolStore?.data?.id
            if (! id) {
                return
            }

            await this.schoolToolStore.saveModuleStatuses({
                id,
                ...this.form,
            })
        },
    },
}
</script>

<style scoped>
.module-status-card {
    background:
        linear-gradient(180deg, rgba(15, 23, 42, 0.94) 0%, rgba(15, 23, 42, 0.88) 100%) !important;
    border: 1px solid rgba(99, 102, 241, 0.16);
    box-shadow: 0 18px 40px rgba(2, 6, 23, 0.24);
}

.module-status-card__scope-chip {
    font-weight: 700;
    color: #eef2ff !important;
}

.module-status-card__eyebrow {
    color: rgba(165, 180, 252, 0.82);
    letter-spacing: 0.08em;
}

.module-status-card__title {
    color: #f8fafc;
    font-weight: 700;
}

.module-status-card__subtitle {
    color: rgba(226, 232, 240, 0.72);
}

.module-status-card__grid {
    display: grid;
    gap: 10px;
}

.module-status-card__row {
    display: grid;
    grid-template-columns: minmax(0, 210px) minmax(0, 1fr);
    gap: 14px;
    align-items: center;
    padding: 12px 14px;
    border-radius: 16px;
    background: rgba(15, 23, 42, 0.78);
    border: 1px solid rgba(148, 163, 184, 0.14);
}

.module-status-card__module {
    color: #f8fafc;
    font-weight: 700;
    font-size: 0.97rem;
}

.module-status-card__meta {
    color: rgba(191, 219, 254, 0.72);
    font-size: 0.8rem;
    margin-top: 3px;
}

.module-status-card__switches {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 10px;
}

.module-status-card__toggle {
    min-height: 46px;
    border-radius: 12px;
    padding: 0 10px;
    border: 1px solid transparent;
}

.module-status-card__toggle--admin {
    background: rgba(99, 102, 241, 0.14);
    border-color: rgba(129, 140, 248, 0.22);
}

.module-status-card__toggle--user {
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(52, 211, 153, 0.2);
}

.module-status-card__toggle--test {
    background: rgba(14, 165, 233, 0.12);
    border-color: rgba(56, 189, 248, 0.2);
}

.module-status-card__toggle--soon {
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(251, 191, 36, 0.2);
}

.module-status-card__toggle :deep(.v-selection-control) {
    min-height: 44px;
}

.module-status-card__toggle :deep(.v-selection-control__wrapper) {
    margin-inline-end: 8px;
}

.module-status-card__toggle :deep(.v-label) {
    color: #f8fafc;
    font-size: 0.84rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.module-status-card__toggle :deep(.v-selection-control--disabled) {
    opacity: 0.52;
}

.module-status-card__toggle :deep(.v-selection-control--disabled .v-label) {
    color: rgba(226, 232, 240, 0.58);
}

@media (max-width: 900px) {
    .module-status-card__row {
        grid-template-columns: 1fr;
        align-items: start;
    }
}

@media (max-width: 760px) {
    .module-status-card__switches {
        grid-template-columns: 1fr;
    }
}
</style>

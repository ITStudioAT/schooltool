<template>
    <v-sheet rounded="xl" class="module-status-card pa-5">
        <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-3">
            <div>
                <div class="text-overline module-status-card__eyebrow">Grundeinstellungen</div>
                <div class="text-h6 module-status-card__title">Sichtbarkeit der Module</div>
                <div class="text-body-2 module-status-card__subtitle">
                    Je Taste klicken, um den Zustand zu wechseln. Änderungen werden sofort gespeichert.
                </div>
            </div>

            <v-chip size="small" color="primary" variant="flat" prepend-icon="mdi-earth" class="module-status-card__scope-chip">
                App-weit gültig!
            </v-chip>
        </div>

        <div class="module-status-card__grid" :aria-busy="isSaving">
            <div v-for="item in moduleRows" :key="item.key" class="module-status-card__row">
                <div class="module-status-card__copy">
                    <div class="module-status-card__module">{{ item.label }}</div>
                    <div class="module-status-card__meta">{{ item.meta }}</div>
                </div>

                <div class="module-status-card__controls">
                    <button
                        type="button"
                        class="module-status-card__button"
                        :class="form[item.adminVisibleField] ? 'module-status-card__button--admin' : 'module-status-card__button--off'"
                        :disabled="isSaving || !schoolToolStore?.data?.id"
                        :aria-pressed="Boolean(form[item.adminVisibleField])"
                        :aria-label="`${item.label}: Admin ${form[item.adminVisibleField] ? 'Angezeigt' : 'Aus'}. Nächster Klick: ${form[item.adminVisibleField] ? 'Aus' : 'Angezeigt'}`"
                        @click="toggleAdmin(item)">
                        <span class="module-status-card__audience">Admin</span>
                        <strong class="module-status-card__state">{{ form[item.adminVisibleField] ? 'Angezeigt' : 'Aus' }}</strong>
                    </button>

                    <button
                        type="button"
                        class="module-status-card__button"
                        :class="`module-status-card__button--${item.hasUserView ? userState(item) : 'unavailable'}`"
                        :disabled="!item.hasUserView || isSaving || !schoolToolStore?.data?.id"
                        :aria-label="item.hasUserView
                            ? `${item.label}: Benutzer ${userStateLabel(userState(item))}. Nächster Klick: ${userStateLabel(nextUserState(item))}`
                            : `${item.label}: Benutzer nicht verfügbar. Keine eigene Benutzeransicht vorhanden.`"
                        @click="cycleUserState(item)">
                        <span class="module-status-card__audience">Benutzer</span>
                        <strong class="module-status-card__state">{{ item.hasUserView ? userStateLabel(userState(item)) : 'Nicht verfügbar' }}</strong>
                        <span v-if="!item.hasUserView" class="module-status-card__next">Keine eigene Benutzeransicht</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="module-status-card__feedback" role="status" aria-live="polite">
            {{ isSaving ? 'Änderung wird gespeichert …' : saveError }}
        </div>
    </v-sheet>
</template>

<script>
import { useSchoolToolStore } from '@/stores/admin/SchoolToolStore'

const normalizeModuleRows = (rows) =>
    Array.isArray(rows)
        ? rows
              .filter((item) => item && typeof item === 'object')
              .map((item) => ({
                  key: item.key,
                  label: item.label,
                  meta: item.meta || 'Lizenz aus Tabelle',
                  adminVisibleField: item.adminVisibleField,
                  userVisibleField: item.userVisibleField,
                  userTestModeField: item.userTestModeField,
                  userComingSoonField: item.userComingSoonField,
                  hasUserView: item.hasUserView !== false,
              }))
              .filter(
                  (item) =>
                      item.key &&
                      item.label &&
                      item.adminVisibleField &&
                      item.userVisibleField &&
                      item.userTestModeField &&
                      item.userComingSoonField
              )
        : []

const defaultForm = (data = null, rows = []) => {
    const fieldNames = new Set()

    rows.forEach((item) => {
        fieldNames.add(item.adminVisibleField)
        fieldNames.add(item.userVisibleField)
        fieldNames.add(item.userTestModeField)
        fieldNames.add(item.userComingSoonField)
    })

    Object.keys(data || {}).forEach((key) => {
        if (/(?:_visible_admin|_visible_user|_user_test_mode|_user_comming_soon)$/.test(key)) {
            fieldNames.add(key)
        }
    })

    const form = Array.from(fieldNames).reduce((values, field) => {
        values[field] = Boolean(data?.[field])
        return values
    }, {})

    return form
}

export default {
    name: 'ModuleStatusesCard',

    async beforeMount() {
        this.schoolToolStore = useSchoolToolStore()

        if (! this.schoolToolStore.data) {
            await this.schoolToolStore.loadConfig()
        }

        this.syncModuleRowsFromStore(this.schoolToolStore.data)
        this.syncFormFromStore(this.schoolToolStore.data)
    },

    data() {
        return {
            schoolToolStore: null,
            form: defaultForm(),
            moduleRows: [],
            isSaving: false,
            saveError: '',
        }
    },

    watch: {
        'schoolToolStore.data': {
            handler(value) {
                if (this.isSaving) {
                    return
                }

                this.syncModuleRowsFromStore(value)
                this.syncFormFromStore(value)
            },
            deep: true,
        },
    },

    methods: {
        syncModuleRowsFromStore(data) {
            this.moduleRows = normalizeModuleRows(data?.module_rows)
        },

        syncFormFromStore(data) {
            if (!data) {
                this.form = defaultForm()
                return
            }

            const form = defaultForm(data, this.moduleRows)

            this.moduleRows.forEach((item) => {
                const adminVisible = Boolean(form[item.adminVisibleField])
                form[item.adminVisibleField] = adminVisible
                form[item.userVisibleField] = adminVisible ? Boolean(data[item.userVisibleField]) : false
                form[item.userTestModeField] = Boolean(data[item.userTestModeField])
                form[item.userComingSoonField] = Boolean(data[item.userComingSoonField])
            })

            this.form = form
        },

        userState(item) {
            if (this.form[item.adminVisibleField] && this.form[item.userVisibleField]) {
                return 'visible'
            }

            if (this.form[item.userTestModeField]) {
                return 'test'
            }

            return this.form[item.userComingSoonField] ? 'soon' : 'off'
        },

        userStateLabel(state) {
            return { visible: 'Anzeige', test: 'Testmodus', soon: 'Kommt bald', off: 'Aus' }[state]
        },

        nextUserState(item) {
            const states = this.form[item.adminVisibleField]
                ? ['visible', 'test', 'soon', 'off']
                : ['test', 'soon', 'off']

            return states[(states.indexOf(this.userState(item)) + 1) % states.length]
        },

        toggleAdmin(item) {
            if (this.isSaving || !this.schoolToolStore?.data?.id) {
                return
            }

            this.form[item.adminVisibleField] = !this.form[item.adminVisibleField]

            if (!this.form[item.adminVisibleField]) {
                this.form[item.userVisibleField] = false
            }

            void this.save()
        },

        cycleUserState(item) {
            if (!item.hasUserView || this.isSaving || !this.schoolToolStore?.data?.id) {
                return
            }

            const state = this.nextUserState(item)
            this.form[item.userVisibleField] = state === 'visible'
            this.form[item.userTestModeField] = state === 'test'
            this.form[item.userComingSoonField] = state === 'soon'
            void this.save()
        },

        async save() {
            const id = this.schoolToolStore?.data?.id
            if (!id || this.isSaving) {
                return
            }

            this.isSaving = true
            this.saveError = ''

            try {
                const saved = await this.schoolToolStore.saveModuleStatuses({ id, ...this.form })
                if (!saved) {
                    this.saveError = 'Änderung konnte nicht gespeichert werden. Bitte erneut versuchen.'
                }
            } catch {
                this.saveError = 'Änderung konnte nicht gespeichert werden. Bitte erneut versuchen.'
            } finally {
                this.syncModuleRowsFromStore(this.schoolToolStore.data)
                this.syncFormFromStore(this.schoolToolStore.data)
                this.isSaving = false
            }
        },
    },
}
</script>

<style scoped>
.module-status-card {
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(246, 250, 255, 0.92) 100%) !important;
    border: 1px solid rgba(16, 38, 58, 0.1);
    box-shadow: 0 18px 40px rgba(16, 38, 58, 0.12);
}

.module-status-card__scope-chip {
    font-weight: 700;
    color: #eef2ff !important;
}

.module-status-card__eyebrow {
    color: rgba(16, 38, 58, 0.68);
    letter-spacing: 0.08em;
}

.module-status-card__title {
    color: #10263a;
    font-weight: 700;
}

.module-status-card__subtitle {
    color: rgba(16, 38, 58, 0.68);
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
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(16, 38, 58, 0.1);
}

.module-status-card__module {
    color: #10263a;
    font-weight: 700;
    font-size: 0.97rem;
}

.module-status-card__meta {
    color: rgba(16, 38, 58, 0.68);
    font-size: 0.8rem;
    margin-top: 3px;
}

.module-status-card__controls {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
    max-width: 560px;
    gap: 10px;
    padding: 9px 9px 13px;
    border: 1px solid #4b5563;
    border-radius: 14px;
    background: linear-gradient(160deg, #303946, #17202b);
    box-shadow: inset 0 2px 5px #0b111b, 0 1px 0 #ffffff;
}

.module-status-card__button {
    --key-light: #e2e8f0;
    --key-edge: #748091;
    --key-glow: transparent;
    --key-wash: rgba(148, 163, 184, 0.06);
    position: relative;
    isolation: isolate;
    display: grid;
    gap: 2px;
    min-width: 0;
    min-height: 88px;
    padding: 10px 16px;
    border: 3px solid #0b111b;
    border-radius: 12px;
    color: #f1f5f9;
    background:
        radial-gradient(ellipse at 50% 0%, var(--key-wash), transparent 85%),
        linear-gradient(160deg, #303b49, #19222e);
    text-align: left;
    cursor: pointer;
    box-shadow:
        inset 0 2px 2px rgba(255, 255, 255, 0.18),
        inset 0 -4px 5px rgba(0, 0, 0, 0.45),
        0 5px 0 #080d14,
        0 7px 7px rgba(0, 0, 0, 0.4),
        0 0 14px var(--key-glow);
    transition: transform 100ms ease, box-shadow 100ms ease;
}

.module-status-card__button::after {
    content: '';
    position: absolute;
    inset: 6px;
    z-index: -1;
    pointer-events: none;
    border: 1px solid var(--key-edge);
    border-radius: 5px;
    box-shadow: 0 0 5px var(--key-glow), inset 0 0 5px var(--key-glow);
}

.module-status-card__button::before {
    content: '';
    position: absolute;
    top: 14px;
    right: 14px;
    width: 8px;
    height: 8px;
    border-radius: 2px;
    background: radial-gradient(circle at 35% 30%, #ffffff, var(--key-light) 35%, var(--key-edge) 75%);
    box-shadow:
        inset 0 0 2px rgba(255, 255, 255, 0.9),
        0 0 4px 2px var(--key-edge),
        0 0 10px 4px var(--key-glow),
        0 0 18px 6px var(--key-glow);
}

.module-status-card__button--off::before,
.module-status-card__button--unavailable::before {
    background: #748091;
    box-shadow: inset 0 1px 2px #3b4655;
}

.module-status-card__button:hover:not(:disabled) {
    filter: brightness(1.1);
}

.module-status-card__button:focus-visible {
    outline: 3px solid #ffffff;
    outline-offset: 2px;
}

.module-status-card__button:active:not(:disabled) {
    transform: translateY(3px);
    box-shadow:
        inset 0 1px 5px rgba(0, 0, 0, 0.6),
        0 2px 0 #080d14,
        0 0 16px var(--key-glow);
}

.module-status-card__button:disabled {
    opacity: 1;
    cursor: wait;
}

.module-status-card__button--unavailable:disabled {
    --key-light: #d1d9e4;
    --key-edge: #56616d;
    --key-glow: transparent;
    opacity: 0.68;
    border-color: #171e28;
    background: linear-gradient(160deg, #252e39, #1b222c);
    box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.04), 0 3px 0 #111820;
    cursor: default;
}

.module-status-card__button--unavailable::after {
    opacity: 0.4;
    box-shadow: none;
}

.module-status-card__button--admin {
    --key-light: #c6d6ff;
    --key-edge: #a5bfff;
    --key-glow: rgba(119, 159, 255, 0.5);
    --key-wash: rgba(99, 130, 255, 0.22);
}

.module-status-card__button--visible {
    --key-light: #b5fbd2;
    --key-edge: #79e7ab;
    --key-glow: rgba(76, 222, 143, 0.45);
    --key-wash: rgba(52, 180, 115, 0.2);
}

.module-status-card__button--test {
    --key-light: #bdeaff;
    --key-edge: #85d6ff;
    --key-glow: rgba(87, 198, 255, 0.45);
    --key-wash: rgba(42, 155, 219, 0.2);
}

.module-status-card__button--soon {
    --key-light: #ffe6b0;
    --key-edge: #ffd17b;
    --key-glow: rgba(255, 191, 82, 0.45);
    --key-wash: rgba(195, 133, 28, 0.2);
}

.module-status-card__audience {
    padding-right: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.module-status-card__state {
    color: var(--key-light);
    font-size: 1.05rem;
    line-height: 1.4;
    text-shadow: 0 0 8px var(--key-glow);
}

.module-status-card__next {
    color: #d1d9e4;
    font-size: 0.78rem;
}

.module-status-card__feedback {
    min-height: 24px;
    margin-top: 12px;
    color: #10263a;
    font-size: 0.85rem;
}

@media (prefers-reduced-motion: reduce) {
    .module-status-card__button {
        transition: none;
    }
}

@media (max-width: 900px) {
    .module-status-card__row {
        grid-template-columns: 1fr;
        align-items: start;
    }
}

@media (max-width: 600px) {
    .module-status-card__controls {
        grid-template-columns: 1fr;
    }
}
</style>

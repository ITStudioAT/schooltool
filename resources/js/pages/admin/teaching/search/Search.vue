<template>
    <v-col cols="12" md="6" lg="7" xl="4">
        <section class="teaching-search-page">
            <section class="teaching-search-query-shell">
                <v-row class="w-100 ma-0" dense>
                    <v-col cols="12" class="teaching-search-panel-col">
                        <ItsGridBox
                            variant="overview"
                            color="primary"
                            title="Personen & Klassen"
                            subtitle="Sokrates 116 durchsuchen"
                            icon="mdi-magnify"
                            class="w-100">
                            <v-alert type="info" variant="tonal" class="mb-3">
                                Die Suche bezieht sich auf die zuletzt importierten Daten aus Sokrates Bund.
                            </v-alert>

                            <v-form ref="form" v-model="is_valid" @submit.prevent="search()">
                                <div class="teaching-search-form-row">
                                    <v-text-field
                                        ref="search_string"
                                        v-model="search_string"
                                        autofocus
                                        clearable
                                        density="compact"
                                        hide-details
                                        label="Suchbegriff"
                                        class="teaching-search-input"
                                        @click:clear="clearSearch" />
                                    <v-btn color="primary" variant="flat" type="submit" prepend-icon="mdi-magnify">
                                        Suchen
                                    </v-btn>
                                </div>
                            </v-form>
                        </ItsGridBox>
                    </v-col>
                </v-row>
            </section>

            <section class="teaching-search-results-shell">
                <v-row class="w-100 ma-0" dense>
                    <v-col cols="12" class="teaching-search-panel-col">
                        <ItsGridBox
                            variant="overview"
                            color="primary"
                            title="Trefferliste"
                            :subtitle="resultSummary"
                            icon="mdi-format-list-bulleted-square"
                            class="w-100">
                            <v-alert v-if="!import116.length" type="info" variant="tonal" class="mb-2">
                                Keine Treffer gefunden. Verwenden Sie einen anderen Suchbegriff.
                            </v-alert>

                            <v-list
                                v-else
                                density="comfortable"
                                variant="text"
                                select-strategy="leaf"
                                v-model:selected="selected_import116"
                                class="search-list">
                                <v-list-item
                                    v-for="(item, index) in import116"
                                    :key="item.id"
                                    :value="item.id"
                                    class="px-0">
                                    <div class="search-entry-card" :class="{ 'search-entry-card--even': index % 2 === 1 }">
                                        <div class="entry-main-line">
                                            <div class="entry-person">
                                                <div class="entry-name">
                                                    {{ item.last_name + ' ' + item.first_name }}
                                                </div>
                                                <v-chip size="x-small" color="primary" variant="tonal">
                                                    {{ item.class || 'ohne Klasse' }}
                                                </v-chip>
                                                <v-icon
                                                    size="18"
                                                    :color="item.sex === 'm' ? 'blue' : item.sex === 'w' ? 'red' : 'amber'"
                                                    :icon="item.sex === 'm' ? 'mdi-gender-male' : item.sex === 'w' ? 'mdi-gender-female' : 'mdi-gender-male-female'" />
                                            </div>
                                            <div v-if="item.email" class="entry-email">
                                                <v-icon icon="mdi-email-outline" size="x-small" />
                                                <span>{{ item.email }}</span>
                                                <v-btn
                                                    icon="mdi-content-copy"
                                                    size="x-small"
                                                    variant="text"
                                                    color="secondary"
                                                    density="comfortable"
                                                    title="E-Mail kopieren"
                                                    @click.stop="copyEmailToClipboard(item.email)" />
                                            </div>
                                        </div>

                                        <div class="entry-meta-row">
                                            <v-chip v-if="item.birth_date" size="x-small" color="secondary" variant="outlined" prepend-icon="mdi-cake">
                                                {{ item.birth_date }}<span v-if="item.age"> ({{ item.age }})</span>
                                            </v-chip>
                                            <v-chip v-if="item.phone_1" size="x-small" color="secondary" variant="outlined" prepend-icon="mdi-phone">
                                                {{ item.phone_1 }}
                                            </v-chip>
                                            <v-chip v-if="item.phone_2" size="x-small" color="secondary" variant="outlined" prepend-icon="mdi-phone">
                                                {{ item.phone_2 }}
                                            </v-chip>
                                        </div>

                                        <div v-if="item.mother_name || item.mother_email || item.mother_phone_1 || item.mother_phone_2" class="entry-parent entry-parent--mother">
                                            <div class="entry-parent-line">
                                                <div class="entry-parent-name">{{ item.mother_name || 'Erziehungsberechtigte 1' }}</div>
                                                <div v-if="item.mother_email" class="entry-parent-mail">
                                                    <span>{{ item.mother_email }}</span>
                                                    <v-btn
                                                        icon="mdi-content-copy"
                                                        size="x-small"
                                                        variant="text"
                                                        color="secondary"
                                                        density="comfortable"
                                                        title="E-Mail kopieren"
                                                        @click.stop="copyEmailToClipboard(item.mother_email)" />
                                                </div>
                                            </div>
                                            <div class="entry-parent-phones">
                                                <span v-if="item.mother_phone_1">{{ item.mother_phone_1 }}</span>
                                                <span v-if="item.mother_phone_2">{{ item.mother_phone_2 }}</span>
                                            </div>
                                        </div>

                                        <div v-if="item.father_name || item.father_email || item.father_phone_1 || item.father_phone_2" class="entry-parent entry-parent--father">
                                            <div class="entry-parent-line">
                                                <div class="entry-parent-name">{{ item.father_name || 'Erziehungsberechtigte 2' }}</div>
                                                <div v-if="item.father_email" class="entry-parent-mail">
                                                    <span>{{ item.father_email }}</span>
                                                    <v-btn
                                                        icon="mdi-content-copy"
                                                        size="x-small"
                                                        variant="text"
                                                        color="secondary"
                                                        density="comfortable"
                                                        title="E-Mail kopieren"
                                                        @click.stop="copyEmailToClipboard(item.father_email)" />
                                                </div>
                                            </div>
                                            <div class="entry-parent-phones">
                                                <span v-if="item.father_phone_1">{{ item.father_phone_1 }}</span>
                                                <span v-if="item.father_phone_2">{{ item.father_phone_2 }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </v-list-item>
                            </v-list>

                            <div class="mt-3">
                                <Pagination20 :meta="meta" :store="teachingStore" index_method="search116" selected_field="selected_import116" />
                            </div>
                        </ItsGridBox>
                    </v-col>
                </v-row>
            </section>
        </section>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import Pagination20 from '@/pages/components/Pagination20.vue'

export default {
    components: { ItsGridBox, Pagination20 },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        this.teachingStore.search116()
    },

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTeachingStore, ['import116', 'search_string', 'selected_import116', 'meta']),
        resultSummary() {
            const count = Array.isArray(this.import116) ? this.import116.length : 0
            if (count === 0) {
                return 'Keine Treffer'
            }
            if (count === 1) {
                return '1 Treffer'
            }
            return `${count} Treffer`
        },
        searchMetaLabel() {
            const query = String(this.search_string || '').trim()
            if (!query) {
                return 'Ohne Filter'
            }
            return `Filter: "${query}"`
        },
    },

    methods: {
        async copyTextToClipboard(value) {
            const text = String(value || '').trim()
            if (!text) {
                return false
            }

            if (typeof navigator !== 'undefined' && navigator?.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(text)
                    return true
                } catch {
                    // Fallback below.
                }
            }

            if (typeof document === 'undefined') {
                return false
            }

            try {
                const textarea = document.createElement('textarea')
                textarea.value = text
                textarea.setAttribute('readonly', '')
                textarea.style.position = 'fixed'
                textarea.style.left = '-9999px'
                document.body.appendChild(textarea)
                textarea.select()
                textarea.setSelectionRange(0, text.length)
                const copied = document.execCommand('copy')
                document.body.removeChild(textarea)

                return copied
            } catch {
                return false
            }
        },
        async copyEmailToClipboard(email) {
            const normalizedEmail = String(email || '').trim()
            if (!normalizedEmail) {
                return
            }

            const notification = useNotificationStore()
            const copied = await this.copyTextToClipboard(normalizedEmail)

            notification.notify({
                message: copied ? 'E-Mail wurde in die Zwischenablage kopiert.' : 'E-Mail konnte nicht kopiert werden.',
                type: copied ? 'success' : 'warning',
                timeout: 2200,
            })
        },
        search() {
            this.selected_import116 = []
            this.teachingStore.search116()
        },
        clearSearch() {
            this.search_string = ''
            this.selected_import116 = []
            this.teachingStore.search116(1)
        },
    },
}
</script>
<style scoped>
.teaching-search-page {
    width: 100%;
    display: grid;
    gap: 12px;
}

.teaching-search-query-shell,
.teaching-search-results-shell {
    border-radius: 16px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.66);
    padding: 8px;
}

.teaching-search-form-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.teaching-search-input {
    min-width: 240px;
    flex: 1 1 320px;
}

.teaching-search-input :deep(input) {
    font-size: 1.45rem;
}

.search-list {
    background: transparent;
}

.search-list :deep(.v-list-item) {
    box-shadow: none;
}

.search-entry-card {
    display: grid;
    gap: 6px;
    width: 100%;
    border-radius: 10px;
    border: 1px solid rgba(16, 38, 58, 0.11);
    background: rgba(255, 255, 255, 0.86);
    padding: 8px;
    font-size: 1rem;
}

.search-entry-card--even {
    background: rgba(234, 246, 255, 0.82);
}

.entry-main-line {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.entry-person {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.entry-name {
    font-size: 1.22rem;
    font-weight: 650;
    line-height: 1.25;
}

.entry-email {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    font-size: 1rem;
    color: rgba(16, 38, 58, 0.85);
    word-break: break-word;
}

.entry-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.entry-parent {
    border-radius: 8px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    background: rgba(255, 255, 255, 0.82);
    padding: 6px;
}

.entry-parent--mother {
    border-left: 4px solid rgba(219, 39, 119, 0.5);
}

.entry-parent--father {
    border-left: 4px solid rgba(79, 70, 229, 0.5);
}

.entry-parent-line {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 8px;
}

.entry-parent-name {
    font-size: 1.02rem;
    font-weight: 600;
}

.entry-parent-mail {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.95rem;
    color: rgba(16, 38, 58, 0.82);
    word-break: break-word;
}

.entry-parent-phones {
    margin-top: 4px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 0.92rem;
}

.entry-meta-row :deep(.v-chip__content),
.entry-person :deep(.v-chip__content) {
    font-size: 0.9rem;
}

@media (max-width: 960px) {
    .teaching-search-input {
        min-width: 100%;
        flex: 1 1 100%;
    }
}
</style>

<template>
    <component :is="wrapperComponent" v-bind="wrapperProps" v-on="wrapperListeners" class="log-panel-shell">
        <v-card>
            <v-card-title class="d-flex justify-space-between align-center py-3 px-4">
                <span>Log-Dateien</span>
                <v-btn v-if="!embedded" icon variant="text" size="small" @click="$emit('update:modelValue', false)">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-0" style="height: 75vh; overflow: hidden;">
                <v-row class="ma-0" style="height: 100%;">
                    <!-- Linke Seite: Liste der Log-Dateien -->
                    <v-col cols="4" class="pa-0" style="border-right: 1px solid rgba(128,128,128,0.2); overflow-y: auto; height: 100%;">
                        <v-list density="compact">
                            <v-list-item
                                v-for="logFile in logs"
                                :key="logFile.name"
                                :class="{ 'bg-primary': selected_log === logFile.name }"
                                class="py-2">
                                <template #default>
                                    <div class="text-body-2 font-weight-medium">{{ logFile.name }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ logFile.size }} &bull; {{ logFile.modified }}</div>
                                </template>
                                <template #append>
                                    <div class="d-flex align-center ga-1">
                                        <v-btn
                                            icon
                                            size="small"
                                            variant="text"
                                            :color="selected_log === logFile.name ? 'white' : 'primary'"
                                            @click="viewLog(logFile.name)"
                                            title="Anzeigen">
                                            <v-icon size="18">mdi-eye</v-icon>
                                        </v-btn>
                                        <template v-if="['super_admin'].some((role) => config.roles.includes(role))">
                                            <!-- Löschen: Stufe 0 -->
                                            <v-btn
                                                v-if="getDeleteLevel(logFile.name) === 0"
                                                icon
                                                size="small"
                                                variant="text"
                                                color="warning"
                                                @click="setDeleteLevel(logFile.name, 1)"
                                                title="Löschen">
                                                <v-icon size="18">mdi-delete</v-icon>
                                            </v-btn>
                                            <!-- Löschen: Stufe 1 – Bestätigung -->
                                            <template v-else>
                                                <v-btn
                                                    icon
                                                    size="small"
                                                    variant="text"
                                                    color="success"
                                                    @click="setDeleteLevel(logFile.name, 0)"
                                                    title="Abbrechen">
                                                    <v-icon size="18">mdi-delete-off</v-icon>
                                                </v-btn>
                                                <v-btn
                                                    icon
                                                    size="small"
                                                    variant="text"
                                                    color="error"
                                                    @click="deleteLog(logFile.name)"
                                                    title="Endgültig löschen">
                                                    <v-icon size="18">mdi-delete</v-icon>
                                                </v-btn>
                                            </template>
                                        </template>
                                    </div>
                                </template>
                            </v-list-item>
                            <v-list-item v-if="is_loaded && !logs.length">
                                <v-list-item-title class="text-medium-emphasis">Keine Log-Dateien vorhanden</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-col>

                    <!-- Rechte Seite: Log-Inhalt -->
                    <v-col cols="8" class="pa-0 d-flex flex-column" style="height: 100%;">
                        <div v-if="log" class="d-flex justify-end align-center px-3 py-1" style="border-bottom: 1px solid rgba(128,128,128,0.2); flex-shrink: 0;">
                            <v-btn
                                icon
                                size="small"
                                variant="text"
                                :color="copied ? 'success' : 'default'"
                                :title="copied ? 'Kopiert!' : 'In Zwischenablage kopieren'"
                                @click="copyToClipboard">
                                <v-icon size="18">{{ copied ? 'mdi-check' : 'mdi-content-copy' }}</v-icon>
                            </v-btn>
                        </div>
                        <div style="overflow-y: auto; flex: 1;">
                            <div
                                v-if="log"
                                class="pa-4"
                                style="white-space: pre-wrap; font-family: monospace; font-size: 11px; line-height: 1.6; word-break: break-all;">
                                {{ log }}
                            </div>
                            <div v-else class="d-flex justify-center align-center text-medium-emphasis" style="height: 100%;">
                                <span>Wähle eine Log-Datei aus</span>
                            </div>
                        </div>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>
    </component>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useLogStore } from '@/stores/admin/LogStore'

export default {
    props: {
        embedded: {
            type: Boolean,
            default: false,
        },
        modelValue: {
            type: Boolean,
            default: false,
        },
    },

    emits: ['update:modelValue'],

    async beforeMount() {
        this.logStore = useLogStore()
        await this.logStore.listLogs()
        this.is_loaded = true
    },

    data() {
        return {
            logStore: null,
            selected_log: null,
            delete_levels: {},
            is_loaded: false,
            copied: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        ...mapWritableState(useLogStore, ['logs', 'log']),
        wrapperComponent() {
            return this.embedded ? 'div' : 'v-dialog'
        },
        wrapperListeners() {
            if (this.embedded) {
                return {}
            }

            return {
                'update:modelValue': (value) => this.$emit('update:modelValue', value),
            }
        },
        wrapperProps() {
            if (this.embedded) {
                return {}
            }

            return {
                modelValue: this.modelValue,
                persistent: true,
                maxWidth: 1300,
            }
        },
    },

    methods: {
        async viewLog(filename) {
            this.delete_levels = {}
            this.selected_log = filename
            this.log = null
            this.copied = false
            await this.logStore.getLog(filename)
        },
        getDeleteLevel(filename) {
            return this.delete_levels[filename] ?? 0
        },
        setDeleteLevel(filename, level) {
            const reset = {}
            Object.keys(this.delete_levels).forEach((key) => (reset[key] = 0))
            this.delete_levels = { ...reset, [filename]: level }
        },
        async copyToClipboard() {
            await navigator.clipboard.writeText(this.log)
            this.copied = true
            setTimeout(() => (this.copied = false), 2000)
        },
        async deleteLog(filename) {
            await this.logStore.deleteLog(filename)
            this.setDeleteLevel(filename, 0)
            if (this.selected_log === filename) {
                this.selected_log = null
            }
        },
    },
}
</script>

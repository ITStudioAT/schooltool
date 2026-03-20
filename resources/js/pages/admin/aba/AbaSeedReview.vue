<template>
    <v-container fluid class="seed-review-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Intern · ABA"
            title="Vorschlag prüfen"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#0f766e"
            right-orb-color="#5eead4" />

        <!-- Navigation -->
        <v-sheet rounded="xl" class="sr-nav mb-2">
            <div class="sr-nav__buttons">
                <v-btn
                    rounded="xl"
                    color="secondary"
                    variant="tonal"
                    class="sr-nav__button"
                    @click="$router.push('/admin/aba')">
                    <v-icon size="18" icon="mdi-view-dashboard-outline" class="mr-2" />
                    <span class="sr-nav__button-copy">
                        <span class="sr-nav__button-title">Überblick</span>
                        <span class="sr-nav__button-meta">Meine ABAs</span>
                    </span>
                </v-btn>
                <v-btn
                    rounded="xl"
                    color="secondary"
                    variant="tonal"
                    class="sr-nav__button"
                    @click="$router.push('/admin/aba/ai-settings')">
                    <v-icon size="18" icon="mdi-brain" class="mr-2" />
                    <span class="sr-nav__button-copy">
                        <span class="sr-nav__button-title">KI-Einstellungen</span>
                        <span class="sr-nav__button-meta">Dashboard</span>
                    </span>
                </v-btn>
                <v-btn
                    rounded="xl"
                    color="secondary"
                    variant="tonal"
                    class="sr-nav__button"
                    @click="$router.push('/admin/aba/ai-settings/seed-report')">
                    <v-icon size="18" icon="mdi-file-document-edit-outline" class="mr-2" />
                    <span class="sr-nav__button-copy">
                        <span class="sr-nav__button-title">Hauptdatei prüfen</span>
                        <span class="sr-nav__button-meta">Hauptdatei</span>
                    </span>
                </v-btn>
                <v-btn rounded="xl" color="teal" variant="flat" class="sr-nav__button">
                    <v-icon size="18" icon="mdi-compare" class="mr-2" />
                    <span class="sr-nav__button-copy">
                        <span class="sr-nav__button-title">Vorschlag prüfen</span>
                        <span class="sr-nav__button-meta">Vergleich</span>
                    </span>
                </v-btn>
                <v-spacer />
                <v-btn
                    size="small"
                    variant="tonal"
                    color="secondary"
                    prepend-icon="mdi-arrow-left"
                    @click="$router.push('/admin/aba/ai-settings/seed-report')">
                    Zurück
                </v-btn>
            </div>
        </v-sheet>

        <!-- Hinweis zum Prüfmodus -->
        <v-alert
            type="info"
            variant="tonal"
            density="compact"
            rounded="lg"
            class="mb-2 text-caption">
            <strong>Prüfmodus:</strong>
            Diese Ansicht zeigt Unterschiede zwischen der aktuellen Hauptdatei und einem Vorschlag.
            Die Hauptdatei wird <strong>nicht automatisch</strong> geändert.
        </v-alert>

        <!-- Ladestate Proposals -->
        <v-row v-if="proposalsLoading" class="w-100 ma-0" dense>
            <v-col cols="12" class="d-flex justify-center pa-8">
                <v-progress-circular indeterminate color="teal" />
            </v-col>
        </v-row>

        <!-- Keine Proposals vorhanden -->
        <v-row v-else-if="!proposalsLoading && proposals.length === 0" class="w-100 ma-0" dense>
            <v-col cols="12">
                <v-alert type="warning" variant="tonal" rounded="lg" class="mb-3">
                    <strong>Noch kein Vorschlag vorhanden.</strong>
                    Der reguläre Weg ist: auf der Hauptdatei-Seite analysieren und dabei den Vorschlag erzeugen.
                </v-alert>
                <ItsGridBox>
                    <template #title>
                        <v-icon size="16" class="mr-1">mdi-map-marker-path</v-icon>
                        Nächster Schritt
                    </template>
                    <div class="pa-4">
                        <p class="text-body-2 mb-3">
                            Starte mit <strong>„Analysieren &amp; neuen Vorschlag erstellen“</strong>
                            auf der Hauptdatei-Seite. Danach kannst du hier den Vergleich prüfen.
                        </p>
                        <v-btn
                            color="teal"
                            variant="flat"
                            prepend-icon="mdi-file-document-edit-outline"
                            class="mr-2"
                            @click="$router.push('/admin/aba/ai-settings/seed-report')">
                            Zur Hauptdatei-Seite
                        </v-btn>
                        <v-btn
                            color="secondary"
                            variant="text"
                            prepend-icon="mdi-tools"
                            :loading="generateLoading"
                            @click="generateReplacement">
                            Spezialfall: Vorschlag manuell neu erzeugen
                        </v-btn>
                        <transition name="fade-down">
                            <v-alert
                                v-if="generateResult"
                                :type="generateResult.success ? 'success' : 'error'"
                                variant="tonal"
                                density="compact"
                                class="mt-3 text-caption">
                                {{ generateResult.success ? generateResult.message : generateResult.error }}
                            </v-alert>
                        </transition>
                    </div>
                </ItsGridBox>
            </v-col>
        </v-row>

        <template v-else>
            <!-- Vorschlagsauswahl + Metadaten -->
            <v-row class="w-100 ma-0 mb-2" dense>
                <v-col cols="12">
                    <ItsGridBox>
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-file-document-check-outline</v-icon>
                            Vorschlag wählen
                        </template>
                        <template #header-actions>
                            <v-btn
                                size="x-small"
                                variant="text"
                                prepend-icon="mdi-refresh"
                                :loading="proposalsLoading"
                                @click="loadProposals">
                                Aktualisieren
                            </v-btn>
                        </template>

                        <div class="pa-3">
                            <v-row dense align="center">
                                <!-- Dropdown -->
                                <v-col cols="12" md="7">
                                    <v-select
                                        v-model="selectedFilename"
                                        :items="proposalItems"
                                        item-title="label"
                                        item-value="filename"
                                        label="Vorschlagsdatei"
                                        density="compact"
                                        variant="outlined"
                                        hide-details
                                        rounded="lg"
                                        @update:model-value="loadDiff">
                                        <template #item="{ item, props: itemProps }">
                                            <v-list-item v-bind="itemProps">
                                                <template #prepend>
                                                    <v-chip
                                                        size="x-small"
                                                        :color="proposalTypeColor(item.raw.type)"
                                                        variant="tonal"
                                                        class="mr-2">
                                                        {{ item.raw.type_label }}
                                                    </v-chip>
                                                </template>
                                                <template #append>
                                                    <span class="text-caption text-disabled ml-2">
                                                        {{ formatDate(item.raw.modified_at) }}
                                                    </span>
                                                </template>
                                            </v-list-item>
                                        </template>
                                    </v-select>
                                </v-col>

                                <!-- Vorschlag-Metadaten + Empfehlung -->
                                <v-col cols="12" md="5">
                                    <div v-if="selectedProposal" class="proposal-meta-row">
                                        <!-- Hauptbadge: Gültiger Vorschlag / Ungültiger Vorschlag / Analysebericht -->
                                        <v-chip
                                            v-if="selectedProposal.is_seed_replacement && selectedProposal.is_valid_replacement !== false"
                                            size="small"
                                            color="green"
                                            variant="flat"
                                            class="mr-2 font-weight-bold">
                                            <v-icon start size="12">mdi-check-circle</v-icon>
                                            Gültiger Vorschlag
                                        </v-chip>
                                        <v-chip
                                            v-else-if="selectedProposal.is_seed_replacement && selectedProposal.is_valid_replacement === false"
                                            size="small"
                                            color="red"
                                            variant="flat"
                                            class="mr-2 font-weight-bold">
                                            <v-icon start size="12">mdi-alert-circle</v-icon>
                                            Ungültiger Vorschlag
                                        </v-chip>
                                        <v-chip
                                            v-else
                                            size="small"
                                            color="orange"
                                            variant="flat"
                                            class="mr-2 font-weight-bold">
                                            <v-icon start size="12">mdi-chart-bar</v-icon>
                                            Analysebericht
                                        </v-chip>
                                        <!-- Typ-Detail -->
                                        <v-chip
                                            size="x-small"
                                            :color="proposalTypeColor(selectedProposal.type)"
                                            variant="tonal"
                                            class="mr-2">
                                            {{ selectedProposal.type_label }}
                                        </v-chip>
                                        <span class="text-caption text-disabled">
                                            {{ formatDateTime(selectedProposal.modified_at) }}
                                            · {{ formatBytes(selectedProposal.size_bytes) }}
                                        </span>
                                    </div>
                                    <!-- Qualitätsprobleme -->
                                    <div
                                        v-if="selectedProposal?.quality_issues?.length"
                                        class="mt-1">
                                        <v-chip
                                            v-for="qi in selectedProposal.quality_issues"
                                            :key="qi"
                                            size="x-small"
                                            color="red"
                                            variant="tonal"
                                            class="mr-1 mt-1">
                                            <v-icon start size="10">mdi-alert</v-icon>
                                            {{ qi }}
                                        </v-chip>
                                    </div>
                                </v-col>
                            </v-row>

                            <!-- Vollansicht-Aktion: direkter, sichtbarer Zugang -->
                            <div v-if="selectedFilename" class="full-view-action mt-3">
                                <v-btn
                                    color="teal"
                                    variant="flat"
                                    prepend-icon="mdi-file-document-outline"
                                    :loading="fullViewLoading"
                                    @click="openFullView">
                                    Vollständigen Vorschlag anzeigen
                                </v-btn>
                                <v-btn
                                    v-if="canEditSelectedProposal"
                                    color="blue"
                                    variant="tonal"
                                    prepend-icon="mdi-pencil"
                                    class="ml-2"
                                    :loading="editorLoading"
                                    @click="openEditorDialog">
                                    Vorschlag bearbeiten
                                </v-btn>
                                <span class="text-caption text-disabled ml-3">
                                    Komplette vorgeschlagene Datei als Ganzes lesen
                                </span>
                            </div>
                            <div v-if="canEditSelectedProposal" class="text-caption text-disabled mt-2">
                                Sie bearbeiten einen Vorschlag. Die Hauptdatei wird dadurch noch nicht geändert.
                            </div>

                            <!-- Empfehlung: kein Vorschlag vorhanden -->
                            <v-alert
                                v-if="!hasReplacementDraft"
                                type="info"
                                variant="tonal"
                                density="compact"
                                rounded="lg"
                                class="mt-3 text-caption">
                                <strong>Empfehlung:</strong>
                                Erzeuge den Vorschlag regulär über
                                <strong>„Analysieren &amp; neuen Vorschlag erstellen“</strong>
                                auf der Hauptdatei-Seite und kehre dann zum Vergleich zurück.
                                <v-btn
                                    size="x-small"
                                    variant="text"
                                    color="teal"
                                    class="ml-1"
                                    prepend-icon="mdi-open-in-new"
                                    @click="$router.push('/admin/aba/ai-settings/seed-report')">
                                    Hauptdatei prüfen
                                </v-btn>
                            </v-alert>

                            <!-- Warnung: Ungültiger Vorschlag -->
                            <v-alert
                                v-else-if="selectedProposal?.is_seed_replacement && selectedProposal?.is_valid_replacement === false"
                                type="error"
                                variant="tonal"
                                density="compact"
                                rounded="lg"
                                class="mt-3 text-caption">
                                <strong>Ungültiger Vorschlag – nicht übernahmefähig:</strong>
                                <ul class="mt-1 pl-4">
                                    <li v-for="qi in (selectedProposal.quality_issues ?? [])" :key="qi">{{ qi }}</li>
                                </ul>
                                Bitte auf der Hauptdatei-Seite erneut analysieren und einen neuen Vorschlag erzeugen.
                            </v-alert>

                            <!-- Warnung: Analysebericht ausgewählt -->
                            <v-alert
                                v-else-if="selectedProposal && !selectedProposal.is_seed_replacement"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                rounded="lg"
                                class="mt-3 text-caption">
                                <strong>Analysebericht gewählt:</strong>
                                Diese Datei ist kein Vorschlag für die Hauptdatei, sondern ein Bericht über offene Punkte.
                                Der Vergleich ist daher nur eingeschränkt aussagekräftig.
                                Wähle stattdessen einen <strong>Vorschlag</strong> aus der Liste.
                            </v-alert>

                            <!-- Generate-Ergebnis -->
                            <transition name="fade-down">
                                <v-alert
                                    v-if="generateResult"
                                    :type="generateResult.success ? 'success' : 'error'"
                                    variant="tonal"
                                    density="compact"
                                    rounded="lg"
                                    class="mt-3 text-caption">
                                    {{ generateResult.success ? generateResult.message : generateResult.error }}
                                </v-alert>
                            </transition>
                        </div>
                    </ItsGridBox>
                </v-col>
            </v-row>

            <!-- Diff-Ansicht -->
            <v-row class="w-100 ma-0" dense>
                <v-col cols="12">
                    <ItsGridBox>
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-compare</v-icon>
                            Vergleich
                            <span v-if="diffData" class="text-caption text-disabled ml-2">
                                Hauptdatei vs. {{ selectedFilename }}
                            </span>
                        </template>
                        <template #header-actions>
                            <!-- Übernahme-Button: nur für echte Vorschläge -->
                            <v-btn
                                v-if="canApply"
                                size="x-small"
                                color="green"
                                variant="flat"
                                prepend-icon="mdi-check-bold"
                                class="mr-3"
                                @click="showApplyDialog = true">
                                Vorschlag übernehmen
                            </v-btn>
                            <!-- Summary-Chips -->
                            <template v-if="diffData?.summary">
                                <v-chip size="x-small" color="green" variant="tonal" class="mr-1">
                                    <v-icon start size="10">mdi-plus</v-icon>
                                    {{ diffData.summary.added }} hinzugefügt
                                </v-chip>
                                <v-chip size="x-small" color="red" variant="tonal" class="mr-1">
                                    <v-icon start size="10">mdi-minus</v-icon>
                                    {{ diffData.summary.removed }} entfernt
                                </v-chip>
                                <v-chip size="x-small" color="grey" variant="tonal" class="mr-2">
                                    {{ diffData.summary.unchanged }} unverändert
                                </v-chip>
                            </template>
                            <!-- Filter -->
                            <v-btn-toggle
                                v-model="diffFilter"
                                density="compact"
                                rounded="lg"
                                color="teal"
                                class="mr-1">
                                <v-btn value="all" size="x-small">Alle</v-btn>
                                <v-btn value="changes" size="x-small">Nur Änderungen</v-btn>
                            </v-btn-toggle>
                            <!-- Copy-Button -->
                            <v-tooltip :text="diffCopied ? 'Kopiert!' : 'Vorschlag kopieren'" location="bottom">
                                <template #activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        :disabled="!selectedFilename"
                                        size="x-small"
                                        variant="tonal"
                                        :icon="diffCopied ? 'mdi-check' : 'mdi-content-copy'"
                                        :color="diffCopied ? 'green' : undefined"
                                        class="ml-1"
                                        @click="copyDiff" />
                                </template>
                            </v-tooltip>
                        </template>

                        <!-- Ladestate Diff -->
                        <div v-if="diffLoading" class="d-flex justify-center pa-8">
                            <v-progress-circular indeterminate color="teal" />
                        </div>

                        <!-- Fehler -->
                        <div v-else-if="diffError" class="pa-4">
                            <v-alert type="error" variant="tonal" rounded="lg">{{ diffError }}</v-alert>
                        </div>

                        <!-- Kein Vorschlag gewählt -->
                        <div v-else-if="!selectedFilename" class="pa-6 text-center text-disabled text-caption">
                            <v-icon size="48" class="mb-2 d-block">mdi-compare</v-icon>
                            Bitte oben einen Vorschlag auswählen.
                        </div>

                        <!-- Kein Diff -->
                        <div v-else-if="!diffData" class="pa-6 text-center text-disabled text-caption">
                            <v-icon size="48" class="mb-2 d-block">mdi-file-search-outline</v-icon>
                            Noch kein Vergleich geladen.
                        </div>

                        <!-- Keine Änderungen -->
                        <div
                            v-else-if="diffData.summary.total_changes === 0"
                            class="pa-4 text-center text-caption">
                            <v-alert type="success" variant="tonal" rounded="lg">
                                <v-icon start>mdi-check-circle-outline</v-icon>
                                Keine Unterschiede gefunden – Vorschlag ist identisch mit der aktuellen Hauptdatei.
                            </v-alert>
                        </div>

                        <!-- Diff-Anzeige -->
                        <div v-else class="diff-container">
                            <!-- Datei-Header -->
                            <div class="diff-header-row">
                                <div class="diff-header-left">
                                    <v-icon size="14" class="mr-1">mdi-file-document-outline</v-icon>
                                    <strong>Aktuell:</strong>
                                    <span class="ml-1 diff-filename">{{ diffData.seed_meta.filename }}</span>
                                    <span class="ml-2 text-caption text-disabled">
                                        {{ diffData.seed_meta.line_count }} Zeilen
                                        · {{ formatDate(diffData.seed_meta.modified_at) }}
                                    </span>
                                </div>
                                <div class="diff-header-right">
                                    <v-icon size="14" class="mr-1">mdi-file-document-edit-outline</v-icon>
                                    <strong>Vorschlag:</strong>
                                    <span class="ml-1 diff-filename">{{ diffData.proposal_meta.filename }}</span>
                                    <span class="ml-2 text-caption text-disabled">
                                        {{ diffData.proposal_meta.line_count }} Zeilen
                                        · {{ formatDate(diffData.proposal_meta.modified_at) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Hunks -->
                            <div class="diff-body">
                                <template v-for="(hunk, hunkIndex) in visibleHunks" :key="hunkIndex">
                                    <!-- Collapsed-Trenner -->
                                    <div v-if="hunk.type === 'collapsed'" class="diff-collapsed">
                                        <v-icon size="12" class="mr-1">mdi-dots-horizontal</v-icon>
                                        {{ hunk.count }} unveränderte
                                        {{ hunk.count === 1 ? 'Zeile' : 'Zeilen' }} ausgeblendet
                                    </div>

                                    <!-- Hunk mit Zeilen -->
                                    <template v-else>
                                        <div
                                            v-for="(line, lineIndex) in hunk.lines"
                                            :key="lineIndex"
                                            :class="['diff-line', `diff-line--${line.type}`]">
                                            <!-- Alte Zeilennummer -->
                                            <span class="diff-ln diff-ln--old">
                                                {{ line.old_num ?? '' }}
                                            </span>
                                            <!-- Neue Zeilennummer -->
                                            <span class="diff-ln diff-ln--new">
                                                {{ line.new_num ?? '' }}
                                            </span>
                                            <!-- Marker -->
                                            <span class="diff-marker">
                                                <template v-if="line.type === 'added'">+</template>
                                                <template v-else-if="line.type === 'removed'">-</template>
                                                <template v-else>&nbsp;</template>
                                            </span>
                                            <!-- Inhalt -->
                                            <span class="diff-content">{{ line.content }}</span>
                                        </div>
                                    </template>
                                </template>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
            </v-row>
        <!-- Übernahme-Fehler -->
        <transition name="fade-down">
            <v-row v-if="applyResult && !applyResult.success" class="w-100 ma-0 mt-2" dense>
                <v-col cols="12">
                    <v-alert type="error" variant="tonal" rounded="lg" class="mb-2">
                        <strong>Übernahme fehlgeschlagen:</strong> {{ applyResult.error }}
                    </v-alert>
                </v-col>
            </v-row>
        </transition>

        <!-- Übernahme-Erfolg: Aufbau-Empfehlung -->
        <transition name="fade-down">
            <v-row v-if="applyResult?.success" class="w-100 ma-0 mt-2" dense>
                <v-col cols="12">
                    <v-alert
                        type="success"
                        variant="tonal"
                        rounded="lg"
                        class="mb-2">
                        <div class="font-weight-bold mb-1">
                            <v-icon start>mdi-check-circle</v-icon>
                            Hauptdatei erfolgreich aktualisiert
                        </div>
                        <div class="text-caption mb-2">
                            Vorschlag übernommen: <code>{{ applyResult.applied_filename }}</code><br />
                            Backup gesichert: <code>archive/{{ applyResult.archive_filename }}</code>
                        </div>
                        <v-divider class="my-2 opacity-25" />
                        <div class="d-flex align-center gap-2 mt-1">
                            <v-icon size="16" color="amber">mdi-alert-circle-outline</v-icon>
                            <span class="text-caption">
                                <strong>Empfohlener nächster Schritt:</strong>
                                Wissensbasis neu aufbauen, damit die Änderungen aktiv werden.
                            </span>
                            <v-btn
                                size="x-small"
                                color="amber"
                                variant="flat"
                                prepend-icon="mdi-database-sync-outline"
                                class="ml-2"
                                @click="$router.push('/admin/aba/ai-settings')">
                                Wissensbasis aufbauen
                            </v-btn>
                        </div>
                    </v-alert>
                </v-col>
            </v-row>
        </transition>

        </template>

        <!-- Vollständige Ansicht Dialog -->
        <v-dialog v-model="showFullView" max-width="900" scrollable>
            <v-card rounded="xl">
                <v-card-title class="pa-4 pb-2 d-flex align-center">
                    <v-icon class="mr-2" color="teal">mdi-file-document-outline</v-icon>
                    <span class="text-body-1 font-weight-bold">Vollständige Ansicht</span>
                    <v-chip size="x-small" color="teal" variant="tonal" class="ml-2">Vorschlag</v-chip>
                    <v-spacer />
                    <span class="text-caption text-disabled mr-3">{{ fullViewFilename }}</span>
                    <v-tooltip :text="fullViewCopied ? 'Kopiert!' : 'Inhalt kopieren'" location="bottom">
                        <template #activator="{ props: tooltipProps }">
                            <v-btn
                                v-bind="tooltipProps"
                                :icon="fullViewCopied ? 'mdi-check' : 'mdi-content-copy'"
                                :color="fullViewCopied ? 'green' : undefined"
                                size="x-small"
                                variant="text"
                                class="mr-1"
                                :disabled="!fullViewContent"
                                @click="copyFullView">
                            </v-btn>
                        </template>
                    </v-tooltip>
                    <v-btn icon size="x-small" variant="text" @click="showFullView = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-0" style="max-height: 75vh; overflow-y: auto;">
                    <div v-if="fullViewLoading" class="d-flex justify-center pa-8">
                        <v-progress-circular indeterminate color="teal" />
                    </div>
                    <div v-else-if="fullViewError" class="pa-4">
                        <v-alert type="error" variant="tonal" rounded="lg">{{ fullViewError }}</v-alert>
                    </div>
                    <pre v-else class="full-view-content">{{ fullViewContent }}</pre>
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-3">
                    <v-chip size="x-small" color="amber" variant="tonal">
                        <v-icon start size="10">mdi-alert-circle-outline</v-icon>
                        Nur Ansicht – Übernahme über „Vorschlag übernehmen"
                    </v-chip>
                    <v-spacer />
                    <v-btn variant="text" size="small" @click="showFullView = false">Schließen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Vorschlag bearbeiten Dialog -->
        <v-dialog v-model="showEditorDialog" max-width="1200" persistent>
            <v-card rounded="xl">
                <v-card-title class="pa-4 pb-2 d-flex align-center">
                    <v-icon class="mr-2" color="blue">mdi-pencil</v-icon>
                    <span class="text-body-1 font-weight-bold">Vorschlag bearbeiten</span>
                    <v-chip size="x-small" color="blue" variant="tonal" class="ml-2">
                        Hauptdatei bleibt unverändert
                    </v-chip>
                    <v-spacer />
                    <span class="text-caption text-disabled mr-3">{{ editorFilename || selectedFilename }}</span>
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                    <v-alert
                        type="info"
                        variant="tonal"
                        density="compact"
                        rounded="lg"
                        class="text-caption mb-3">
                        <strong>Hinweis:</strong>
                        Sie bearbeiten einen Vorschlag. Erst nach „Vorschlag übernehmen" wird die Hauptdatei geändert.
                    </v-alert>

                    <v-alert
                        v-if="editorResult"
                        type="success"
                        variant="tonal"
                        density="compact"
                        rounded="lg"
                        class="text-caption mb-3">
                        {{ editorResult }}
                    </v-alert>

                    <v-alert
                        v-if="editorError"
                        type="error"
                        variant="tonal"
                        density="compact"
                        rounded="lg"
                        class="text-caption mb-3">
                        {{ editorError }}
                    </v-alert>

                    <div v-if="editorLoading" class="d-flex justify-center py-8">
                        <v-progress-circular indeterminate color="blue" />
                    </div>

                    <div v-else>
                        <div class="d-flex align-center mb-2">
                            <v-chip size="x-small" color="grey" variant="outlined" class="mr-2">
                                {{ editorLineCount }} Zeilen
                            </v-chip>
                            <v-chip
                                size="x-small"
                                :color="editorDirty ? 'orange' : 'green'"
                                variant="tonal">
                                {{ editorDirty ? 'Ungespeicherte Änderungen' : 'Gespeichert' }}
                            </v-chip>
                        </div>

                        <v-textarea
                            v-model="editorContent"
                            variant="outlined"
                            density="compact"
                            rows="28"
                            no-resize
                            spellcheck="false"
                            class="proposal-editor"
                            hide-details />
                    </div>
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-4">
                    <v-btn
                        variant="tonal"
                        color="orange"
                        :disabled="editorLoading || editorSaving || !editorDirty"
                        @click="discardEditorChanges">
                        Änderungen verwerfen
                    </v-btn>
                    <v-spacer />
                    <v-btn
                        variant="text"
                        :disabled="editorSaving"
                        @click="closeEditorDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="blue"
                        variant="flat"
                        prepend-icon="mdi-content-save"
                        :loading="editorSaving"
                        :disabled="editorLoading || !editorDirty"
                        @click="saveEditorContent">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Übernahme-Bestätigungsdialog -->
        <v-dialog v-model="showApplyDialog" max-width="520" persistent>
            <v-card rounded="xl">
                <v-card-title class="pa-4 pb-2">
                    <v-icon color="amber" class="mr-2">mdi-alert-circle-outline</v-icon>
                    Diesen Vorschlag übernehmen?
                </v-card-title>
                <v-card-text class="pa-4 pt-0">
                    <p class="text-body-2 mb-3">
                        Die folgende neue Fassung wird als Hauptdatei übernommen:
                    </p>
                    <v-sheet rounded="lg" color="surface-variant" class="pa-3 mb-3">
                        <div class="text-caption text-disabled mb-1">Wird übernommen</div>
                        <code class="text-body-2">{{ selectedFilename }}</code>
                    </v-sheet>
                    <v-alert
                        type="info"
                        variant="tonal"
                        density="compact"
                        rounded="lg"
                        class="text-caption mb-3">
                        <strong>Backup:</strong>
                        Die aktuelle Hauptdatei wird automatisch im Archiv gesichert, bevor sie überschrieben wird.
                    </v-alert>
                    <v-alert
                        type="warning"
                        variant="tonal"
                        density="compact"
                        rounded="lg"
                        class="text-caption">
                        <strong>Nach der Übernahme:</strong>
                        Ein Aufbau der Wissensbasis wird empfohlen, damit die Änderungen wirksam werden.
                    </v-alert>
                </v-card-text>
                <v-card-actions class="pa-4 pt-0">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        :disabled="applyLoading"
                        @click="showApplyDialog = false">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="green"
                        variant="flat"
                        prepend-icon="mdi-check-bold"
                        :loading="applyLoading"
                        @click="confirmApply">
                        Jetzt übernehmen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { AdminSectionHero, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        await this.loadProposals()
    },

    data() {
        return {
            adminStore: null,
            proposals: [],
            proposalsLoading: false,
            proposalsError: null,
            selectedFilename: null,
            diffData: null,
            diffLoading: false,
            diffError: null,
            diffFilter: 'all',
            generateLoading: false,
            generateResult: null,
            applyLoading: false,
            applyResult: null,
            showApplyDialog: false,
            showFullView: false,
            fullViewLoading: false,
            fullViewContent: null,
            fullViewFilename: null,
            fullViewError: null,
            fullViewCopied: false,
            diffCopied: false,
            showEditorDialog: false,
            editorLoading: false,
            editorSaving: false,
            editorFilename: null,
            editorContent: '',
            editorOriginalContent: '',
            editorError: null,
            editorResult: null,
        }
    },

    watch: {
        diffFilter() {
            this.loadDiff()
        },
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),

        selectedSchoolLabel() {
            return this.config?.selected_school?.long_name || this.config?.selected_school?.name || 'Keine Schule'
        },

        selectedRoleLabel() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            return roles.slice(0, 2).join(' / ') || 'Keine Rolle'
        },

        headerChips() {
            return [
                { key: 'school', text: this.selectedSchoolLabel, icon: 'mdi-domain' },
                { key: 'role', text: this.selectedRoleLabel, icon: 'mdi-shield-account' },
            ]
        },

        activeSection() {
            return { label: 'Vorschlag prüfen', icon: 'mdi-compare', note: 'Vergleich Hauptdatei vs. vorgeschlagene neue Fassung.' }
        },

        /** Gibt es mindestens einen echten Seed-Ersatzdraft? */
        hasReplacementDraft() {
            return this.proposals.some((p) => p.is_seed_replacement)
        },

        /** Kann der aktuell gewählte Draft übernommen werden? */
        canApply() {
            return !!(
                this.selectedProposal?.type === 'replacement_draft' &&
                this.selectedProposal?.is_valid_replacement !== false &&
                this.diffData
            )
        },

        canEditSelectedProposal() {
            return !!this.selectedProposal?.is_editable
        },

        editorDirty() {
            return this.editorContent !== this.editorOriginalContent
        },

        editorLineCount() {
            if (!this.editorContent) {
                return 0
            }
            return this.editorContent.split('\n').length
        },

        proposalItems() {
            return this.proposals.map((p) => ({
                ...p,
                label: p.is_seed_replacement
                    ? `★ ${p.type_label} · ${p.filename}`
                    : `${p.type_label} · ${p.filename}`,
            }))
        },

        selectedProposal() {
            return this.proposals.find((p) => p.filename === this.selectedFilename) ?? null
        },

        visibleHunks() {
            if (!this.diffData?.hunks) {
                return []
            }
            if (this.diffFilter === 'changes') {
                // Nur Hunks mit Änderungen, keine Collapsed-Trenner
                return this.diffData.hunks.filter((h) => h.type === 'hunk')
            }
            return this.diffData.hunks
        },
    },

    methods: {
        async loadProposals() {
            this.proposalsLoading = true
            this.proposalsError = null

            try {
                const response = await axios.get('/api/admin/aba/seed-review/proposals')
                this.proposals = response.data.proposals ?? []

                // Besten Vorschlag vorauswählen: Ersatzdraft bevorzugt, sonst erster in Liste
                if (this.proposals.length > 0 && !this.selectedFilename) {
                    const preferred = this.proposals.find((p) => p.type === 'replacement_draft')
                        ?? this.proposals.find((p) => p.is_seed_replacement)
                        ?? this.proposals[0]
                    this.selectedFilename = preferred.filename
                    await this.loadDiff()
                }
            } catch (e) {
                this.proposalsError = 'Vorschläge konnten nicht geladen werden.'
            } finally {
                this.proposalsLoading = false
            }
        },

        async generateReplacement() {
            this.generateLoading = true
            this.generateResult = null

            try {
                const response = await axios.post('/api/admin/aba/seed-review/generate-replacement')
                this.generateResult = response.data

                if (response.data.success) {
                    // Liste neu laden und neuen Draft direkt auswählen
                    const prevSelected = this.selectedFilename
                    this.selectedFilename = null
                    await this.loadProposals()

                    // Falls loadProposals nicht den neuen Draft gewählt hat, manuell setzen
                    if (response.data.filename && this.proposals.find((p) => p.filename === response.data.filename)) {
                        this.selectedFilename = response.data.filename
                        await this.loadDiff()
                    } else if (prevSelected && !this.selectedFilename) {
                        this.selectedFilename = prevSelected
                    }
                }
            } catch (e) {
                this.generateResult = {
                    success: false,
                    error: e.response?.data?.error || 'Vorschlag konnte nicht erstellt werden.',
                }
            } finally {
                this.generateLoading = false
            }
        },

        async confirmApply() {
            this.applyLoading = true

            try {
                const response = await axios.post('/api/admin/aba/seed-review/apply', {
                    filename: this.selectedFilename,
                })
                this.applyResult = response.data
                this.showApplyDialog = false

                if (response.data.success) {
                    // Proposals neu laden – Draft ist jetzt die produktive Datei
                    await this.loadProposals()
                }
            } catch (e) {
                this.applyResult = {
                    success: false,
                    error: e.response?.data?.error || 'Übernahme fehlgeschlagen.',
                }
                this.showApplyDialog = false
            } finally {
                this.applyLoading = false
            }
        },

        async openEditorDialog() {
            if (!this.selectedFilename || !this.canEditSelectedProposal) {
                return
            }

            this.showEditorDialog = true
            this.editorError = null
            this.editorResult = null
            this.editorFilename = this.selectedFilename
            this.editorLoading = true

            try {
                const response = await axios.get(
                    `/api/admin/aba/seed-review/content/${encodeURIComponent(this.selectedFilename)}`,
                )
                const content = response.data?.content ?? ''
                this.editorContent = content
                this.editorOriginalContent = content
            } catch (e) {
                this.editorError = e.response?.data?.error || 'Vorschlag konnte nicht geladen werden.'
            } finally {
                this.editorLoading = false
            }
        },

        closeEditorDialog() {
            if (this.editorSaving) {
                return
            }

            if (this.editorDirty) {
                const shouldDiscard = window.confirm('Ungespeicherte Änderungen verwerfen?')
                if (!shouldDiscard) {
                    return
                }
            }

            this.showEditorDialog = false
            this.editorError = null
            this.editorResult = null
        },

        discardEditorChanges() {
            if (!this.editorDirty) {
                return
            }

            const shouldDiscard = window.confirm('Alle ungespeicherten Änderungen verwerfen?')
            if (!shouldDiscard) {
                return
            }

            this.editorContent = this.editorOriginalContent
            this.editorError = null
            this.editorResult = null
        },

        async saveEditorContent() {
            if (!this.selectedFilename || !this.canEditSelectedProposal) {
                return
            }

            this.editorSaving = true
            this.editorError = null
            this.editorResult = null

            try {
                const response = await axios.put(
                    `/api/admin/aba/seed-review/content/${encodeURIComponent(this.selectedFilename)}`,
                    { content: this.editorContent },
                )

                const savedContent = this.editorContent
                this.editorOriginalContent = savedContent
                this.editorResult = response.data?.message || 'Vorschlag erfolgreich gespeichert.'

                if (this.fullViewFilename === this.selectedFilename) {
                    this.fullViewContent = savedContent
                }

                await this.loadProposals()
                await this.loadDiff()
            } catch (e) {
                this.editorError = e.response?.data?.error || 'Speichern fehlgeschlagen.'
            } finally {
                this.editorSaving = false
            }
        },

        async copyFullView() {
            if (!this.fullViewContent) {
                return
            }
            try {
                await navigator.clipboard.writeText(this.fullViewContent)
                this.fullViewCopied = true
                setTimeout(() => {
                    this.fullViewCopied = false
                }, 2000)
            } catch {
                // Fallback für ältere Browser
                const el = document.createElement('textarea')
                el.value = this.fullViewContent
                document.body.appendChild(el)
                el.select()
                document.execCommand('copy')
                document.body.removeChild(el)
                this.fullViewCopied = true
                setTimeout(() => {
                    this.fullViewCopied = false
                }, 2000)
            }
        },

        async copyDiff() {
            if (!this.selectedFilename) {
                return
            }
            try {
                const response = await axios.get(
                    `/api/admin/aba/seed-review/content/${encodeURIComponent(this.selectedFilename)}`,
                )
                const text = response.data?.content ?? ''
                await navigator.clipboard.writeText(text)
                this.diffCopied = true
                setTimeout(() => {
                    this.diffCopied = false
                }, 2000)
            } catch {
                // ignore
            }
        },

        async openFullView() {
            this.fullViewError = null
            this.fullViewContent = null
            this.fullViewFilename = this.selectedFilename
            this.showFullView = true
            this.fullViewLoading = true

            try {
                const response = await axios.get(
                    `/api/admin/aba/seed-review/content/${encodeURIComponent(this.selectedFilename)}`,
                )
                this.fullViewContent = response.data.content
            } catch (e) {
                this.fullViewError = e.response?.data?.error || 'Inhalt konnte nicht geladen werden.'
            } finally {
                this.fullViewLoading = false
            }
        },

        async loadDiff() {
            if (!this.selectedFilename) {
                return
            }

            this.diffLoading = true
            this.diffError = null
            this.diffData = null

            try {
                const expand = this.diffFilter === 'all' ? '?expand=1' : ''
                const response = await axios.get(
                    `/api/admin/aba/seed-review/diff/${encodeURIComponent(this.selectedFilename)}${expand}`,
                )
                this.diffData = response.data
            } catch (e) {
                const msg = e.response?.data?.error
                this.diffError = msg || 'Vergleich konnte nicht geladen werden.'
            } finally {
                this.diffLoading = false
            }
        },

        proposalTypeColor(type) {
            const colors = {
                replacement_draft: 'green',
                editorial_cleanup: 'amber',
                proposal: 'blue',
                ai_draft: 'grey',
                pattern_draft: 'grey',
                other: 'grey',
            }
            return colors[type] ?? 'grey'
        },

        formatDate(iso) {
            if (!iso) {
                return '–'
            }
            return new Date(iso).toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },

        formatDateTime(iso) {
            if (!iso) {
                return '–'
            }
            return new Date(iso).toLocaleString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            })
        },

        formatBytes(bytes) {
            if (!bytes) {
                return '–'
            }
            if (bytes < 1024) {
                return `${bytes} B`
            }
            if (bytes < 1024 * 1024) {
                return `${(bytes / 1024).toFixed(1)} KB`
            }
            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        },
    },
}
</script>

<style scoped>
/* Navigation (gleicher Stil wie AbaSeedReport) */
.sr-nav {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 6px 10px;
}
.sr-nav__buttons {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.sr-nav__button {
    height: 44px !important;
    padding: 0 12px !important;
}
.sr-nav__button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.1;
}
.sr-nav__button-title {
    font-size: 0.8rem;
    font-weight: 600;
}
.sr-nav__button-meta {
    font-size: 0.65rem;
    opacity: 0.65;
    font-weight: 400;
}

/* Proposal-Meta */
.proposal-meta-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}

/* Diff-Container – eigener dunkler Hintergrund, unabhängig vom App-Theme */
.diff-container {
    border-radius: 0 0 12px 12px;
    overflow: hidden;
    font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', ui-monospace, monospace;
    font-size: 0.78rem;
    line-height: 1.5;
    background: #0d1117;
}

/* Datei-Header (zweispaltig: alt / neu) */
.diff-header-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    background: rgba(255, 255, 255, 0.04);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.diff-header-left,
.diff-header-right {
    padding: 8px 12px;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}
.diff-header-left {
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}
.diff-filename {
    font-family: inherit;
    font-size: 0.75rem;
    opacity: 0.85;
}

/* Diff-Body: scrollbar */
.diff-body {
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: auto;
}

/* Collapsed-Trenner */
.diff-collapsed {
    display: flex;
    align-items: center;
    padding: 4px 12px;
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.02);
    border-top: 1px dashed rgba(255, 255, 255, 0.08);
    border-bottom: 1px dashed rgba(255, 255, 255, 0.08);
    user-select: none;
}

/* Diff-Zeile */
.diff-line {
    display: flex;
    align-items: baseline;
    min-height: 22px;
    border-left: 3px solid transparent;
    white-space: pre;
}
.diff-line:hover {
    background: rgba(255, 255, 255, 0.03);
}

/* Typen – kräftige Hintergründe, weißer Text für maximalen Kontrast */
.diff-line--added {
    background-color: #1b4332;
    border-left-color: #2ea043;
}
.diff-line--removed {
    background-color: #4a1515;
    border-left-color: #cf222e;
}
.diff-line--unchanged {
    background: transparent;
    border-left-color: transparent;
}

/* Zeilennummern */
.diff-ln {
    display: inline-block;
    width: 44px;
    text-align: right;
    padding-right: 8px;
    font-size: 0.7rem;
    user-select: none;
    flex-shrink: 0;
}
.diff-ln--old {
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}
.diff-line--unchanged .diff-ln {
    color: rgba(255, 255, 255, 0.3);
}
.diff-line--added .diff-ln {
    background-color: #165a38;
    color: #aaffcc;
}
.diff-line--removed .diff-ln {
    background-color: #5a1a1a;
    color: #ffaaaa;
}

/* Marker (+/-) */
.diff-marker {
    display: inline-block;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
    font-weight: 700;
}
.diff-line--added .diff-marker {
    color: #6ef5a0;
}
.diff-line--removed .diff-marker {
    color: #ff8080;
}
.diff-line--unchanged .diff-marker {
    color: rgba(255, 255, 255, 0.2);
}

/* Inhalt */
.diff-content {
    flex: 1;
    overflow-x: hidden;
    padding: 0 8px;
}
.diff-line--added .diff-content {
    color: #ffffff;
}
.diff-line--removed .diff-content {
    color: #ffffff;
}
.diff-line--unchanged .diff-content {
    color: rgba(255, 255, 255, 0.72);
}

/* Vollansicht-Aktionszeile */
.full-view-action {
    display: flex;
    align-items: center;
    padding: 6px 2px;
    border-top: 1px solid rgba(255, 255, 255, 0.07);
}

/* Vollständige Ansicht */
.full-view-content {
    font-family: 'JetBrains Mono', 'Fira Code', ui-monospace, monospace;
    font-size: 0.78rem;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
    padding: 20px 24px;
    margin: 0;
    color: #1a1a2e;
    background: #f8f9fb;
}

:deep(.proposal-editor textarea) {
    font-family: 'JetBrains Mono', 'Fira Code', ui-monospace, monospace;
    font-size: 0.78rem;
    line-height: 1.55;
}
</style>

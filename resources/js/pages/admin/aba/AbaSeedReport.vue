<template>
    <v-container fluid class="seed-report-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Intern · ABA"
            title="Hauptdatei prüfen"
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
                <v-btn rounded="xl" color="teal" variant="flat" class="sr-nav__button">
                    <v-icon size="18" icon="mdi-file-document-edit-outline" class="mr-2" />
                    <span class="sr-nav__button-copy">
                        <span class="sr-nav__button-title">Hauptdatei prüfen</span>
                        <span class="sr-nav__button-meta">Hauptdatei</span>
                    </span>
                </v-btn>
                <v-btn
                    rounded="xl"
                    color="secondary"
                    variant="tonal"
                    class="sr-nav__button"
                    @click="$router.push('/admin/aba/ai-settings/seed-report/review')">
                    <v-icon size="18" icon="mdi-compare" class="mr-2" />
                    <span class="sr-nav__button-copy">
                        <span class="sr-nav__button-title">Vorschlag prüfen</span>
                        <span class="sr-nav__button-meta">Vergleich</span>
                    </span>
                </v-btn>
                <v-spacer />
                <v-btn
                    size="small"
                    variant="outlined"
                    color="white"
                    prepend-icon="mdi-refresh"
                    :loading="isLoading"
                    @click="loadData">
                    Aktualisieren
                </v-btn>
                <v-btn
                    size="small"
                    variant="tonal"
                    color="secondary"
                    prepend-icon="mdi-arrow-left"
                    @click="$router.push('/admin/aba/ai-settings')">
                    Zurück
                </v-btn>
            </div>
        </v-sheet>

        <!-- Zugriff verweigert -->
        <v-row v-if="accessDenied" class="w-100 ma-0" dense>
            <v-col cols="12">
                <v-alert type="error" variant="tonal" rounded="lg">
                    Zugriff nicht erlaubt. Diese Seite ist nur für Administratoren zugänglich.
                </v-alert>
            </v-col>
        </v-row>

        <!-- Ladestate -->
        <v-row v-else-if="isLoading && !pageData" class="w-100 ma-0" dense>
            <v-col cols="12" class="d-flex justify-center pa-8">
                <v-progress-circular indeterminate color="teal" />
            </v-col>
        </v-row>

        <!-- Ladefehler -->
        <v-row v-else-if="loadError" class="w-100 ma-0" dense>
            <v-col cols="12">
                <v-alert type="error" variant="tonal" rounded="lg">{{ loadError }}</v-alert>
            </v-col>
        </v-row>

        <!-- Datei nicht gefunden -->
        <v-row v-else-if="pageData && !contentData.found" class="w-100 ma-0" dense>
            <v-col cols="12">
                <v-alert type="warning" variant="tonal" rounded="lg">
                    <strong>Hauptdatei nicht gefunden:</strong>
                    <code class="ml-2">ai/knowledge/aba/sources/aba-knowledge-seed-report.md</code>
                </v-alert>
            </v-col>
        </v-row>

        <!-- Hauptinhalt -->
        <template v-else-if="pageData">
            <v-row class="w-100 ma-0" dense>
                <!-- Linke Spalte: Meta + Regeln & Sicherheit -->
                <v-col cols="12" lg="4">
                    <!-- Datei-Metadaten -->
                    <ItsGridBox class="mb-2">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-file-document-outline</v-icon>
                            Metadaten
                        </template>
                        <div class="pa-3">
                            <v-alert
                                v-if="metaData.error"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                class="mb-3 text-caption">
                                {{ metaData.error }}
                            </v-alert>
                            <div class="meta-grid">
                                <div class="meta-row">
                                    <span class="meta-label">Datei</span>
                                    <span class="meta-value meta-mono">aba-knowledge-seed-report.md</span>
                                </div>
                                <div v-if="seedMeta.title" class="meta-row">
                                    <span class="meta-label">Titel</span>
                                    <span class="meta-value">{{ seedMeta.title }}</span>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-label">Version</span>
                                    <span class="meta-value">
                                        <v-chip size="x-small" color="blue" variant="tonal">
                                            {{ seedMeta.version || '–' }}
                                        </v-chip>
                                    </span>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-label">Status</span>
                                    <span class="meta-value">
                                        <v-chip
                                            size="x-small"
                                            :color="seedMeta.status === 'active' ? 'green' : 'grey'"
                                            variant="tonal">
                                            {{ seedMeta.status || 'unbekannt' }}
                                        </v-chip>
                                    </span>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-label">Letztes Review</span>
                                    <span class="meta-value">{{ formatDateTime(seedMeta.last_reviewed_at) }}</span>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-label">Nächstes Review</span>
                                    <span class="meta-value">
                                        <v-chip
                                            v-if="seedMeta.next_review_due_at"
                                            size="x-small"
                                            :color="isReviewOverdue ? 'error' : 'primary'"
                                            variant="tonal">
                                            {{ seedMeta.next_review_due_at }}
                                        </v-chip>
                                        <span v-else>–</span>
                                    </span>
                                </div>
                                <div v-if="metaData.modified_at" class="meta-row">
                                    <span class="meta-label">Datei geändert</span>
                                    <span class="meta-value">{{ formatDateTime(metaData.modified_at) }}</span>
                                </div>
                                <div v-if="metaData.size_bytes" class="meta-row">
                                    <span class="meta-label">Dateigröße</span>
                                    <span class="meta-value">{{ formatBytes(metaData.size_bytes) }}</span>
                                </div>
                                <div v-if="contentData.line_count" class="meta-row">
                                    <span class="meta-label">Zeilen</span>
                                    <span class="meta-value">{{ contentData.line_count }} gesamt · {{ contentData.body_line_count }} Body</span>
                                </div>
                                <div v-if="contentData.char_count" class="meta-row">
                                    <span class="meta-label">Zeichen</span>
                                    <span class="meta-value">{{ contentData.char_count.toLocaleString('de-AT') }}</span>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>

                    <!-- Regeln & Sicherheit / Review-Status -->
                    <ItsGridBox>
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-shield-check-outline</v-icon>
                            Regeln &amp; Sicherheit
                        </template>
                        <div class="pa-3">
                            <div class="status-chips mb-3">
                                <v-chip size="small" color="grey" variant="outlined">
                                    <v-icon start size="14">mdi-database-outline</v-icon>
                                    {{ reviewState.total_claims }} Aussagen
                                </v-chip>
                                <v-chip size="small" color="green" variant="tonal">
                                    <v-icon start size="14">mdi-check-circle-outline</v-icon>
                                    {{ statusCount('verified') }} ✓
                                </v-chip>
                                <v-chip
                                    size="small"
                                    :color="statusCount('needs_review') > 0 ? 'orange' : 'grey'"
                                    variant="tonal">
                                    <v-icon start size="14">mdi-alert-circle-outline</v-icon>
                                    {{ statusCount('needs_review') }} ⚠
                                </v-chip>
                                <v-chip
                                    size="small"
                                    :color="statusCount('stale') > 0 ? 'error' : 'grey'"
                                    variant="tonal">
                                    <v-icon start size="14">mdi-clock-alert-outline</v-icon>
                                    {{ statusCount('stale') }} veraltet
                                </v-chip>
                            </div>

                            <div class="meta-grid">
                                <div v-if="reviewState.last_rebuilt_at" class="meta-row">
                                    <span class="meta-label">Letzter Aufbau</span>
                                    <span class="meta-value">{{ formatDateTime(reviewState.last_rebuilt_at) }}</span>
                                </div>
                                <div v-if="freshnessData.found" class="meta-row">
                                    <span class="meta-label">Online-Prüfung</span>
                                    <span class="meta-value">{{ freshnessData.last_run_at ? formatDateTime(freshnessData.last_run_at) : '–' }}</span>
                                </div>
                            </div>

                            <div v-if="openClaimsData.total > 0" class="mt-2">
                                <div class="text-caption text-medium-emphasis mb-1">
                                    {{ openClaimsData.total }} offene Aussagen – Überprüfung empfohlen
                                </div>
                                <v-chip
                                    v-for="claim in openClaimsData.claims.slice(0, 6)"
                                    :key="claim.claim_key"
                                    size="x-small"
                                    :color="claim.change_risk === 'high' ? 'error' : 'orange'"
                                    variant="tonal"
                                    class="mr-1 mb-1">
                                    {{ claim.claim_key }}
                                </v-chip>
                                <span v-if="openClaimsData.total > 6" class="text-caption text-disabled">
                                    + {{ openClaimsData.total - 6 }} weitere
                                </span>
                            </div>

                            <div v-else class="text-caption text-medium-emphasis mt-1">
                                <v-icon size="14" color="green" class="mr-1">mdi-check-circle-outline</v-icon>
                                Alle Aussagen verifiziert.
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>

                <!-- Rechte Spalte: Dateiinhalt -->
                <v-col cols="12" lg="8">
                    <ItsGridBox>
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-text-box-outline</v-icon>
                            Dateiinhalt
                            <v-chip size="x-small" color="grey" variant="outlined" class="ml-2">
                                {{ contentData.line_count }} Zeilen
                            </v-chip>
                        </template>

                        <template #header-actions>
                            <v-btn-toggle
                                v-model="viewMode"
                                density="compact"
                                rounded="lg"
                                color="teal"
                                variant="outlined">
                                <v-btn value="body" size="small">
                                    <v-icon size="14" class="mr-1">mdi-text</v-icon>
                                    Body
                                </v-btn>
                                <v-btn value="full" size="small">
                                    <v-icon size="14" class="mr-1">mdi-code-braces</v-icon>
                                    Komplett
                                </v-btn>
                            </v-btn-toggle>
                            <v-btn
                                size="small"
                                variant="text"
                                color="grey"
                                prepend-icon="mdi-content-copy"
                                @click="copyContent">
                                Kopieren
                            </v-btn>
                        </template>

                        <div class="pa-3">
                            <v-alert
                                v-if="contentData.error"
                                type="error"
                                variant="tonal"
                                density="compact"
                                class="mb-3 text-caption">
                                {{ contentData.error }}
                            </v-alert>

                            <!-- Frontmatter-Hinweis im Body-Modus -->
                            <div v-if="viewMode === 'body'" class="frontmatter-hint text-caption text-disabled mb-2">
                                <v-icon size="12" class="mr-1">mdi-information-outline</v-icon>
                                YAML-Frontmatter ausgeblendet · Wechsle zu „Komplett" für den vollständigen Rohtext.
                            </div>

                            <div class="content-wrapper">
                                <div class="line-numbers" aria-hidden="true">
                                    <span
                                        v-for="n in displayLineCount"
                                        :key="n"
                                        class="line-number">{{ n }}</span>
                                </div>
                                <pre class="content-text"><code>{{ displayContent }}</code></pre>
                            </div>

                            <div class="text-caption text-disabled mt-2 d-flex justify-space-between">
                                <span>
                                    <v-icon size="12" class="mr-1">mdi-file-document-outline</v-icon>
                                    ai/knowledge/aba/sources/aba-knowledge-seed-report.md
                                </span>
                                <span>
                                    {{ displayLineCount }} Zeilen ·
                                    {{ (displayContent || '').length.toLocaleString('de-AT') }} Zeichen
                                </span>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
            </v-row>

            <!-- Workflow-Aktionen -->
            <v-row class="w-100 ma-0 mt-2" dense>
                <v-col cols="12">
                    <ItsGridBox>
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-clipboard-list-outline</v-icon>
                            Empfohlener Ablauf
                        </template>
                        <template #header-actions>
                            <v-btn
                                v-if="hardeningStatus"
                                size="x-small"
                                variant="text"
                                color="grey"
                                :loading="hardeningStatusLoading"
                                prepend-icon="mdi-refresh"
                                @click="loadHardeningStatus">
                                Status laden
                            </v-btn>
                        </template>

                        <div class="pa-3">
                            <!-- Workflow-Schritte Übersicht -->
                            <div class="workflow-steps mb-4">
                                <div class="workflow-step">
                                    <div class="workflow-step-number">1</div>
                                    <div class="workflow-step-label">Änderungen online prüfen</div>
                                </div>
                                <div class="workflow-step-arrow">→</div>
                                <div class="workflow-step">
                                    <div class="workflow-step-number">2</div>
                                    <div class="workflow-step-label">Offene Punkte erkennen</div>
                                </div>
                                <div class="workflow-step-arrow">→</div>
                                <div class="workflow-step">
                                    <div class="workflow-step-number">3</div>
                                    <div class="workflow-step-label">Analysieren &amp; neuen Vorschlag erstellen</div>
                                </div>
                                <div class="workflow-step-arrow">→</div>
                                <div class="workflow-step">
                                    <div class="workflow-step-number">4</div>
                                    <div class="workflow-step-label">Vorschlag prüfen</div>
                                </div>
                                <div class="workflow-step-arrow">→</div>
                                <div class="workflow-step">
                                    <div class="workflow-step-number">5</div>
                                    <div class="workflow-step-label">Vorschlag übernehmen</div>
                                </div>
                                <div class="workflow-step-arrow">→</div>
                                <div class="workflow-step">
                                    <div class="workflow-step-number">6</div>
                                    <div class="workflow-step-label">Wissensbasis aufbauen</div>
                                </div>
                            </div>

                            <v-row dense>
                                <!-- Schritt 1: Online-Prüfung -->
                                <v-col cols="12" md="4">
                                    <div class="action-card">
                                        <div class="action-card-header">
                                            <div class="action-card-step">Schritt 1</div>
                                            <div class="action-card-title">
                                                <v-icon size="16" class="mr-1">mdi-web-check</v-icon>
                                                Änderungen online prüfen
                                            </div>
                                            <div class="action-card-desc text-caption text-disabled">
                                                Prüft, ob Quellen auf ahs-aba.at und BMBWF noch aktuell und erreichbar sind.
                                            </div>
                                        </div>

                                        <v-btn
                                            block
                                            variant="tonal"
                                            color="teal"
                                            :loading="actionLoading.onlineFreshness"
                                            prepend-icon="mdi-web-check"
                                            class="mb-2"
                                            @click="runCheckFreshnessOnline">
                                            Online prüfen
                                        </v-btn>

                                        <transition name="fade-down">
                                            <div v-if="actionResults.onlineFreshness" class="action-result mb-2">
                                                <v-alert
                                                    :type="actionResults.onlineFreshness.success ? 'success' : 'error'"
                                                    variant="tonal"
                                                    density="compact"
                                                    class="text-caption">
                                                    <template v-if="actionResults.onlineFreshness.success">
                                                        {{ actionResults.onlineFreshness.result.sources_checked }} geprüft ·
                                                        {{ actionResults.onlineFreshness.result.sources_reachable }} erreichbar ·
                                                        {{ actionResults.onlineFreshness.result.sources_with_changes }} Änderung(en)
                                                    </template>
                                                    <template v-else>{{ actionResults.onlineFreshness.error }}</template>
                                                </v-alert>
                                            </div>
                                        </transition>

                                        <div class="action-card-status text-caption text-disabled">
                                            <template v-if="freshnessData.found">
                                                <v-icon size="12" class="mr-1">mdi-clock-outline</v-icon>
                                                Letzte Prüfung: {{ formatDateTime(freshnessData.last_run_at) }}
                                            </template>
                                            <template v-else>
                                                Noch keine Online-Prüfung durchgeführt.
                                            </template>
                                        </div>
                                    </div>
                                </v-col>

                                <!-- Schritt 2-3: Offene Punkte erkennen & Vorschlag erstellen -->
                                <v-col cols="12" md="4">
                                    <div class="action-card action-card--primary">
                                        <div class="action-card-header">
                                            <div class="action-card-step">Schritte 2–3</div>
                                            <div class="action-card-title">
                                                <v-icon size="16" class="mr-1">mdi-text-search</v-icon>
                                                Offene Punkte erkennen &amp; Vorschlag erstellen
                                            </div>
                                            <div class="action-card-desc text-caption text-disabled">
                                                Erkennt offene Punkte, verifiziert Quellen und erstellt direkt eine vollständige neue Fassung.
                                                <span v-if="hardeningStatus?.ai_provider?.configured" class="text-success">
                                                    KI aktiv.
                                                </span>
                                                <span v-else>
                                                    KI nicht konfiguriert – nur Muster-Analyse.
                                                </span>
                                            </div>
                                        </div>

                                        <v-btn
                                            block
                                            variant="flat"
                                            color="purple"
                                            :loading="hardeningLoading.analyze"
                                            :disabled="hardeningLoading.analyze"
                                            prepend-icon="mdi-file-document-edit-outline"
                                            class="mb-2"
                                            @click="runAnalyzeAndPropose">
                                            Offene Punkte erkennen &amp; Vorschlag erstellen
                                        </v-btn>

                                        <!-- Fortschrittsanzeige während der Analyse -->
                                        <transition name="fade-down">
                                            <div v-if="hardeningLoading.analyze" class="analyze-progress mb-3">
                                                <v-progress-linear
                                                    indeterminate
                                                    color="purple"
                                                    rounded
                                                    height="3"
                                                    class="mb-3" />

                                                <div class="analyze-steps">
                                                    <div
                                                        v-for="(step, i) in analyzeProgress.steps"
                                                        :key="i"
                                                        :class="[
                                                            'analyze-step',
                                                            i < analyzeProgress.currentStep && 'analyze-step--done',
                                                            i === analyzeProgress.currentStep && 'analyze-step--active',
                                                            i > analyzeProgress.currentStep && 'analyze-step--pending',
                                                        ]">
                                                        <span class="analyze-step-icon">
                                                            <v-icon
                                                                v-if="i < analyzeProgress.currentStep"
                                                                size="14"
                                                                color="green">
                                                                mdi-check-circle
                                                            </v-icon>
                                                            <v-progress-circular
                                                                v-else-if="i === analyzeProgress.currentStep"
                                                                indeterminate
                                                                size="14"
                                                                width="2"
                                                                color="purple" />
                                                            <v-icon
                                                                v-else
                                                                size="14"
                                                                color="grey-darken-1">
                                                                mdi-circle-outline
                                                            </v-icon>
                                                        </span>
                                                        <span class="analyze-step-label">{{ step.label }}</span>
                                                    </div>
                                                </div>

                                                <div class="analyze-hint text-caption text-disabled mt-2">
                                                    <v-icon size="12" class="mr-1">mdi-information-outline</v-icon>
                                                    Die Analyse läuft in mehreren Schritten. Bitte die Seite nicht schließen.
                                                </div>
                                            </div>
                                        </transition>

                                        <transition name="fade-down">
                                            <div v-if="hardeningResults.analyze" class="action-result mb-2">
                                                <!-- Fehler -->
                                                <v-alert
                                                    v-if="!hardeningResults.analyze.success"
                                                    type="error"
                                                    variant="tonal"
                                                    density="compact"
                                                    class="text-caption">
                                                    {{ hardeningResults.analyze.error }}
                                                </v-alert>

                                                <!-- Erfolg -->
                                                <template v-else>
                                                    <!-- Gültiger Hauptvorschlag erzeugt -->
                                                    <template v-if="hardeningResults.analyze.replacement_draft?.valid">
                                                        <v-alert
                                                            type="success"
                                                            variant="tonal"
                                                            density="compact"
                                                            rounded="lg"
                                                            class="text-caption mb-2">
                                                            <strong>Analyse abgeschlossen · Neuer Vorschlag liegt vor</strong><br />
                                                            <span class="text-caption opacity-80">
                                                                {{ hardeningResults.analyze.replacement_draft.lines }} Zeilen ·
                                                                {{ hardeningResults.analyze.replacement_draft.editorial_removed }} redakt. Elemente entfernt
                                                            </span>
                                                        </v-alert>
                                                        <!-- Source-Resolution Chips -->
                                                        <div
                                                            v-if="hardeningResults.analyze.source_resolution && hardeningResults.analyze.source_resolution.total > 0"
                                                            class="d-flex flex-wrap gap-1 mb-2">
                                                            <v-chip size="x-small" color="green" variant="tonal">
                                                                <v-icon start size="10">mdi-check-decagram-outline</v-icon>
                                                                Quellen vollständig geklärt: {{ sourceResolutionSummarySafe().fully_resolved }}
                                                            </v-chip>
                                                            <v-chip size="x-small" color="orange" variant="tonal">
                                                                <v-icon start size="10">mdi-alert-outline</v-icon>
                                                                Quellen noch offen: {{ sourceResolutionSummarySafe().open_for_main_file }}
                                                            </v-chip>
                                                            <v-chip
                                                                v-if="sourceResolutionSummarySafe().partially_resolved > 0"
                                                                size="x-small"
                                                                color="grey"
                                                                variant="outlined">
                                                                <v-icon start size="10">mdi-progress-clock</v-icon>
                                                                Teilweise geklärt (Zwischenstand): {{ sourceResolutionSummarySafe().partially_resolved }}
                                                            </v-chip>
                                                        </div>
                                                        <v-alert
                                                            v-if="sourceResolutionItemsSafe().length > 0"
                                                            type="info"
                                                            variant="tonal"
                                                            density="compact"
                                                            rounded="lg"
                                                            class="text-caption mb-2">
                                                            <strong>Quellenstatus nach dem Lauf:</strong>
                                                            {{ sourceResolutionSummarySafe().fully_resolved }} vollständig geklärt ·
                                                            {{ sourceResolutionSummarySafe().open_for_main_file }} noch offen für die Hauptdatei.
                                                            <div
                                                                v-if="sourceResolutionOpenItemsSafe().length > 0"
                                                                class="mt-2">
                                                                <div class="font-weight-medium mb-1">Offene Quellenpunkte:</div>
                                                                <div
                                                                    v-for="item in sourceResolutionOpenItemsSafe().slice(0, 3)"
                                                                    :key="`${item.source_id || 'ohne-quelle'}-${item.issue_id || 'ohne-id'}`"
                                                                    class="mb-1">
                                                                    · {{ item.source_title || item.source_id || 'Unbekannte Quelle' }}
                                                                    <span v-if="item.reason"> – {{ item.reason }}</span>
                                                                    <span v-else-if="item.note"> – {{ item.note }}</span>
                                                                    <div
                                                                        v-if="Array.isArray(item.missing_requirements) && item.missing_requirements.length > 0"
                                                                        class="text-disabled">
                                                                        Fehlt: {{ item.missing_requirements.join('; ') }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </v-alert>
                                                        <v-btn
                                                            block
                                                            variant="flat"
                                                            color="teal"
                                                            prepend-icon="mdi-compare"
                                                            @click="$router.push('/admin/aba/ai-settings/seed-report/review')">
                                                            Vorschlag jetzt prüfen →
                                                        </v-btn>
                                                    </template>

                                                    <!-- Analyse ok, Vorschlag hat Qualitätsprobleme -->
                                                    <template v-else-if="hardeningResults.analyze.replacement_draft?.generated && !hardeningResults.analyze.replacement_draft?.valid">
                                                        <v-alert
                                                            type="warning"
                                                            variant="tonal"
                                                            density="compact"
                                                            rounded="lg"
                                                            class="text-caption mb-2">
                                                            <strong>Analyse abgeschlossen</strong> – Vorschlag hat Qualitätsprobleme:<br />
                                                            <span
                                                                v-for="qi in hardeningResults.analyze.replacement_draft.quality_issues"
                                                                :key="qi"
                                                                class="d-block opacity-80">· {{ qi }}</span>
                                                        </v-alert>
                                                        <v-btn
                                                            block
                                                            variant="tonal"
                                                            color="orange"
                                                            size="small"
                                                            prepend-icon="mdi-refresh"
                                                            @click="runAnalyzeAndPropose">
                                                            Erneut versuchen
                                                        </v-btn>
                                                    </template>

                                                    <!-- Analyse ok, kein Vorschlag erzeugbar -->
                                                    <template v-else>
                                                        <v-alert
                                                            type="info"
                                                            variant="tonal"
                                                            density="compact"
                                                            rounded="lg"
                                                            class="text-caption mb-2">
                                                            <strong>Analyse abgeschlossen</strong> – kein Vorschlag erzeugbar.<br />
                                                            <span v-if="hardeningResults.analyze.replacement_draft?.error" class="opacity-80">
                                                                {{ hardeningResults.analyze.replacement_draft.error }}
                                                            </span>
                                                        </v-alert>
                                                    </template>
                                                </template>
                                            </div>
                                        </transition>

                                        <div class="action-card-status text-caption">
                                            <template v-if="proposalAvailable">
                                                <v-chip size="x-small" color="green" variant="tonal" class="mr-1">
                                                    <v-icon start size="10">mdi-check-circle-outline</v-icon>
                                                    Vorschlag vorhanden
                                                </v-chip>
                                                <v-btn
                                                    size="x-small"
                                                    variant="tonal"
                                                    color="teal"
                                                    prepend-icon="mdi-compare"
                                                    class="ml-1"
                                                    @click="$router.push('/admin/aba/ai-settings/seed-report/review')">
                                                    Prüfen
                                                </v-btn>
                                            </template>
                                            <template v-else-if="hardeningStatus?.last_run">
                                                <span class="text-disabled">
                                                    Letzter Lauf: {{ formatDateTime(hardeningStatus.last_run.generated_at) }}
                                                </span>
                                            </template>
                                            <template v-else>
                                                <span class="text-disabled">Noch keine Analyse.</span>
                                            </template>
                                        </div>
                                    </div>
                                </v-col>

                                <!-- Schritt 4-6: Vorschlag übernehmen und Wissensbasis aufbauen -->
                                <v-col cols="12" md="4">
                                    <div class="action-card">
                                        <div class="action-card-header">
                                            <div class="action-card-step">Schritte 4–6</div>
                                            <div class="action-card-title">
                                                <v-icon size="16" class="mr-1">mdi-database-refresh-outline</v-icon>
                                                Übernehmen &amp; aufbauen
                                            </div>
                                            <div class="action-card-desc text-caption text-disabled">
                                                Vorschlag prüfen, neue Fassung übernehmen und danach die Wissensbasis aus der Hauptdatei aufbauen.
                                            </div>
                                        </div>

                                        <v-alert
                                            v-if="rebuildRecommended"
                                            type="info"
                                            variant="tonal"
                                            density="compact"
                                            class="text-caption mb-2">
                                            Neuer Aufbau empfohlen – Analyse liegt vor.
                                        </v-alert>

                                        <v-btn
                                            block
                                            variant="tonal"
                                            :color="rebuildRecommended ? 'green' : 'grey'"
                                            :loading="actionLoading.rebuild"
                                            prepend-icon="mdi-database-refresh-outline"
                                            class="mb-2"
                                            @click="runRebuild">
                                            Wissensbasis aufbauen
                                        </v-btn>

                                        <transition name="fade-down">
                                            <div v-if="actionResults.rebuild" class="action-result mb-2">
                                                <v-alert
                                                    :type="actionResults.rebuild.success ? 'success' : 'error'"
                                                    variant="tonal"
                                                density="compact"
                                                class="text-caption">
                                                    <template v-if="actionResults.rebuild.success">
                                                        {{ actionResults.rebuild.result.claims_count }} Aussagen ·
                                                        {{ actionResults.rebuild.result.chunks_count }} Chunks ·
                                                        {{ actionResults.rebuild.result.files_written_count }} Dateien
                                                    </template>
                                                    <template v-else>{{ actionResults.rebuild.error }}</template>
                                                </v-alert>
                                            </div>
                                        </transition>

                                        <div class="action-card-status text-caption text-disabled">
                                            <template v-if="reviewState.last_rebuilt_at">
                                                <v-icon size="12" class="mr-1">mdi-clock-outline</v-icon>
                                                Letzter Aufbau: {{ formatDateTime(reviewState.last_rebuilt_at) }}
                                            </template>
                                            <template v-else>
                                                Noch kein Aufbau durchgeführt.
                                            </template>
                                        </div>
                                    </div>
                                </v-col>
                            </v-row>

                            <!-- Hinweis zu Regeln & Sicherheit -->
                            <v-alert
                                type="info"
                                variant="tonal"
                                density="compact"
                                class="text-caption mt-3"
                                rounded="lg">
                                <strong>Hinweis zu Regeln &amp; Sicherheit:</strong>
                                Schritte 1–3 erzeugen nur Vorschläge – die Hauptdatei wird <strong>nicht automatisch</strong> geändert.
                                Schritt 4 (Vorschlag prüfen) ermöglicht die manuelle Prüfung und bewusste Übernahme.
                                Erst danach wird die Wissensbasis neu aufgebaut.
                            </v-alert>

                            <!-- Technische Details (expandierbar) -->
                            <v-expansion-panels class="mt-3" variant="accordion" rounded="lg">
                                <v-expansion-panel>
                                    <v-expansion-panel-title class="text-caption">
                                        <v-icon size="14" class="mr-2">mdi-cog-outline</v-icon>
                                        Technische Details &amp; erweiterte Aktionen
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <v-row dense class="mt-1">
                                            <!-- Offene Punkte -->
                                            <v-col cols="12" md="5">
                                                <div class="text-caption text-medium-emphasis mb-2">
                                                    <v-icon size="12" class="mr-1">mdi-bug-outline</v-icon>
                                                    Offene Punkte
                                                    <span v-if="hardeningStatus?.open_issues?.generated_at" class="text-disabled ml-1">
                                                        · Erkennung: {{ formatDateTime(hardeningStatus.open_issues.generated_at) }}
                                                    </span>
                                                </div>

                                                <div v-if="hardeningStatus?.open_issues?.total_issues > 0" class="d-flex flex-wrap ga-1 mb-3">
                                                    <v-chip
                                                        v-for="(count, type) in hardeningStatus.open_issues.by_type"
                                                        v-show="count > 0"
                                                        :key="type"
                                                        size="x-small"
                                                        :color="issueTypeColor(type)"
                                                        variant="tonal">
                                                        {{ issueTypeLabel(type) }}: {{ count }}
                                                    </v-chip>
                                                </div>
                                                <div v-else-if="hardeningStatus?.open_issues?.found === false" class="text-caption text-disabled mb-3">
                                                    Noch keine Erkennung durchgeführt.
                                                </div>
                                                <div v-else class="text-caption text-disabled mb-3">
                                                    Keine offenen Punkte.
                                                </div>

                                                <!-- KI-Provider-Status -->
                                                <div v-if="hardeningStatus?.ai_provider" class="provider-badge mb-3">
                                                    <v-chip
                                                        size="x-small"
                                                        :color="hardeningStatus.ai_provider.configured ? 'green' : 'error'"
                                                        :prepend-icon="hardeningStatus.ai_provider.configured ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline'"
                                                        variant="tonal">
                                                        {{ hardeningStatus.ai_provider.configured ? 'OpenAI konfiguriert' : 'OpenAI nicht konfiguriert' }}
                                                    </v-chip>
                                                    <div class="text-caption text-disabled mt-1">
                                                        Research: {{ hardeningStatus.ai_provider.models?.research }} ·
                                                        Verification/Hardening: {{ hardeningStatus.ai_provider.models?.verification }}
                                                    </div>
                                                </div>

                                                <!-- Erweiterte Aktionen -->
                                                <div class="text-caption text-medium-emphasis mb-2">
                                                    <v-icon size="12" class="mr-1">mdi-wrench-outline</v-icon>
                                                    Einzelaktionen
                                                </div>
                                                <div class="d-flex flex-column ga-2">
                                                    <div>
                                                        <v-btn
                                                            block
                                                            variant="outlined"
                                                            color="blue"
                                                            size="small"
                                                            :loading="hardeningLoading.scan"
                                                            prepend-icon="mdi-magnify-scan"
                                                            @click="runScan">
                                                            Offene Punkte erkennen
                                                        </v-btn>
                                                        <transition name="fade-down">
                                                            <div v-if="hardeningResults.scan" class="action-result mt-1">
                                                                <v-alert
                                                                    :type="hardeningResults.scan.success ? 'success' : 'error'"
                                                                    variant="tonal"
                                                                    density="compact"
                                                                    class="text-caption">
                                                                    <template v-if="hardeningResults.scan.success">
                                                                        {{ hardeningResults.scan.total_issues }} offene Punkte gefunden
                                                                    </template>
                                                                    <template v-else>{{ hardeningResults.scan.error }}</template>
                                                                </v-alert>
                                                            </div>
                                                        </transition>
                                                    </div>

                                                    <div>
                                                        <v-btn
                                                            block
                                                            variant="outlined"
                                                            color="amber"
                                                            size="small"
                                                            :loading="hardeningLoading.cleanup"
                                                            prepend-icon="mdi-broom"
                                                            @click="runCleanup">
                                                            Redaktionelle Bereinigung
                                                        </v-btn>
                                                        <transition name="fade-down">
                                                            <div v-if="hardeningResults.cleanup" class="action-result mt-1">
                                                                <v-alert
                                                                    :type="hardeningResults.cleanup.success ? 'success' : 'error'"
                                                                    variant="tonal"
                                                                    density="compact"
                                                                    class="text-caption">
                                                                    <template v-if="hardeningResults.cleanup.success">
                                                                        {{ hardeningResults.cleanup.summary?.total_extracted ?? 0 }} Elemente extrahiert ·
                                                                        <code class="hardening-code">{{ hardeningResults.cleanup.cleanup_file }}</code>
                                                                    </template>
                                                                    <template v-else>{{ hardeningResults.cleanup.error }}</template>
                                                                </v-alert>
                                                            </div>
                                                        </transition>
                                                    </div>

                                                    <div>
                                                        <v-btn
                                                            block
                                                            variant="text"
                                                            color="grey"
                                                            size="small"
                                                            :loading="actionLoading.proposal"
                                                            prepend-icon="mdi-tools"
                                                            @click="runProposeUpdate">
                                                            Spezialfall: Vorschlag aus Online-Prüfung erstellen
                                                        </v-btn>
                                                        <transition name="fade-down">
                                                            <div v-if="actionResults.proposal" class="action-result mt-1">
                                                                <v-alert
                                                                    :type="actionResults.proposal.success ? 'success' : 'error'"
                                                                    variant="tonal"
                                                                    density="compact"
                                                                    class="text-caption">
                                                                    <template v-if="actionResults.proposal.success">
                                                                        Erzeugt: <code>{{ actionResults.proposal.proposal_filename }}</code>
                                                                    </template>
                                                                    <template v-else>{{ actionResults.proposal.error }}</template>
                                                                </v-alert>
                                                            </div>
                                                        </transition>
                                                    </div>
                                                </div>
                                            </v-col>

                                            <!-- Artefakte -->
                                            <v-col cols="12" md="7">
                                                <div class="text-caption text-medium-emphasis mb-2">
                                                    <v-icon size="12" class="mr-1">mdi-folder-open-outline</v-icon>
                                                    Erzeugte Artefakte
                                                    <span class="text-disabled ml-1">proposals/</span>
                                                </div>

                                                <div
                                                    v-if="hardeningStatus && hardeningStatus.artifacts.length === 0"
                                                    class="text-caption text-disabled">
                                                    Noch keine Artefakte vorhanden.
                                                </div>

                                                <div
                                                    v-else-if="hardeningStatus"
                                                    class="d-flex flex-column ga-1">
                                                    <div
                                                        v-for="artifact in hardeningStatus.artifacts"
                                                        :key="artifact.filename"
                                                        class="artifact-row">
                                                        <div class="artifact-type">
                                                            <v-chip
                                                                size="x-small"
                                                                :color="artifactTypeColor(artifact.type)"
                                                                variant="tonal">
                                                                {{ artifactTypeLabel(artifact.type) }}
                                                            </v-chip>
                                                        </div>
                                                        <div class="artifact-name text-caption">
                                                            <code class="hardening-code">{{ artifact.filename }}</code>
                                                        </div>
                                                        <div class="artifact-meta text-caption text-disabled">
                                                            {{ formatDateTime(artifact.modified_at) }}
                                                            <span class="ml-1">· {{ formatBytes(artifact.size_bytes) }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div v-else-if="!hardeningStatusLoading" class="text-caption text-disabled">
                                                    Status noch nicht geladen.
                                                </div>
                                            </v-col>
                                        </v-row>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </div>
                    </ItsGridBox>
                </v-col>
            </v-row>

            <!-- Hinweis -->
            <v-row class="w-100 ma-0 mt-1" dense>
                <v-col cols="12">
                    <v-alert
                        type="info"
                        variant="tonal"
                        density="compact"
                        rounded="lg"
                        class="text-caption">
                        <strong>Wichtiger Hinweis:</strong>
                        Diese Datei ist die kuratierte Hauptdatei der AHS-ABA-Wissenspipeline.
                        Änderungen sind redaktionell relevant. Online-Prüfungen ändern die Hauptdatei nicht automatisch.
                        Prüfung, Übernahme und Aufbau der Wissensbasis hängen direkt an dieser Datei.
                    </v-alert>
                </v-col>
            </v-row>
        </template>

        <!-- Snackbar für Kopieren -->
        <v-snackbar v-model="copySnackbar" timeout="2000" color="success" location="bottom">
            Rohtext kopiert.
        </v-snackbar>
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
        await Promise.all([this.loadData(), this.loadHardeningStatus()])
    },

    data() {
        return {
            adminStore: null,
            isLoading: false,
            loadError: null,
            accessDenied: false,
            pageData: null,
            viewMode: 'body',
            copySnackbar: false,
            actionLoading: { onlineFreshness: false, proposal: false, rebuild: false },
            actionResults: { onlineFreshness: null, proposal: null, rebuild: null },
            hardeningStatus: null,
            hardeningStatusLoading: false,
            hardeningLoading: { analyze: false, scan: false, run: false, runAi: false, cleanup: false },
            hardeningResults: { analyze: null, scan: null, run: null, runAi: null, cleanup: null },
            analyzeProgress: {
                currentStep: 0,
                timers: [],
                // Schritte entsprechen den echten Pipeline-Phasen (Reihenfolge: Scan → Research → Verification → Draft).
                // Timing-Werte sind konservative Schätzungen basierend auf typischem Pipelineverhalten –
                // kein Countdown, nur Schritt-Fortschritt. Die Schritte bleiben ehrlich indeterminiert.
                steps: [
                    { label: 'Analyse startet …', delay: 0 },
                    { label: 'Offene Punkte werden erkannt …', delay: 1500 },
                    { label: 'KI-Recherche läuft …', delay: 5000 },
                    { label: 'KI-Verifikation läuft …', delay: 18000 },
                    { label: 'Quellen werden aufgelöst …', delay: 38000 },
                    { label: 'Neuer Vorschlag wird erstellt …', delay: 42000 },
                ],
            },
        }
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
            return { label: 'Hauptdatei prüfen', icon: 'mdi-file-document-edit-outline', note: 'Hauptdatei der AHS-ABA-Wissenspipeline.' }
        },

        contentData() {
            return this.pageData?.content ?? { found: false, full_content: null, body_content: null, frontmatter_raw: null, line_count: 0, body_line_count: 0, char_count: 0, error: null }
        },

        metaData() {
            return this.pageData?.meta ?? { found: false, path: '–', size_bytes: null, modified_at: null, meta: {}, error: null }
        },

        seedMeta() {
            return this.metaData?.meta ?? {}
        },

        reviewState() {
            return this.pageData?.review_state ?? { total_claims: 0, by_status: {}, last_rebuilt_at: null, error: null }
        },

        freshnessData() {
            return this.pageData?.freshness_results ?? { found: false, last_run_at: null, summary: {} }
        },

        openClaimsData() {
            return this.pageData?.open_claims ?? { found: false, total: 0, claims: [] }
        },

        isReviewOverdue() {
            const due = this.seedMeta?.next_review_due_at
            return due ? new Date(due) < new Date() : false
        },

        displayContent() {
            if (this.viewMode === 'body') {
                return this.contentData.body_content || ''
            }
            return this.contentData.full_content || ''
        },

        displayLineCount() {
            if (!this.displayContent) {
                return 0
            }
            return this.displayContent.split('\n').length
        },

        proposalAvailable() {
            // Gültiger Replacement-Draft aus dem letzten Analyse-Lauf hat Priorität
            if (this.hardeningResults.analyze?.replacement_draft?.valid) {
                return true
            }
            return !!this.hardeningStatus?.last_run?.proposal_file
        },

        rebuildRecommended() {
            const lastAnalysis = this.hardeningStatus?.last_run?.generated_at
            const lastRebuild = this.reviewState?.last_rebuilt_at
            if (!lastAnalysis) return false
            if (!lastRebuild) return true
            return new Date(lastAnalysis) > new Date(lastRebuild)
        },

        latestSourceResolution() {
            return this.hardeningResults?.analyze?.source_resolution ?? null
        },

        sourceResolutionItems() {
            const items = this.latestSourceResolution?.items
            return Array.isArray(items) ? items : []
        },

        sourceResolutionOpenItems() {
            return this.sourceResolutionItems.filter((item) =>
                item.resolution_status === 'unresolved' || item.resolution_status === 'partially_resolved',
            )
        },
    },

    methods: {
        async loadData() {
            this.isLoading = true
            this.loadError = null
            this.accessDenied = false
            try {
                const response = await axios.get('/api/admin/aba/seed-report')
                this.pageData = response.data
            } catch (error) {
                if (error.response?.status === 403) {
                    this.accessDenied = true
                } else {
                    this.loadError = error.response?.data?.message || 'Daten konnten nicht geladen werden.'
                }
            } finally {
                this.isLoading = false
            }
        },

        async runCheckFreshnessOnline() {
            this.actionLoading.onlineFreshness = true
            this.actionResults.onlineFreshness = null
            try {
                const response = await axios.post('/api/admin/aba/ai-settings/check-freshness-online')
                this.actionResults.onlineFreshness = response.data
                await this.loadData()
            } catch (error) {
                this.actionResults.onlineFreshness = { success: false, error: error.response?.data?.error || 'Online-Prüfung fehlgeschlagen.' }
            } finally {
                this.actionLoading.onlineFreshness = false
            }
        },

        async runProposeUpdate() {
            this.actionLoading.proposal = true
            this.actionResults.proposal = null
            try {
                const response = await axios.post('/api/admin/aba/ai-settings/propose-update')
                this.actionResults.proposal = response.data
            } catch (error) {
                this.actionResults.proposal = { success: false, error: error.response?.data?.error || 'Vorschlagserstellung fehlgeschlagen.' }
            } finally {
                this.actionLoading.proposal = false
            }
        },

        async runRebuild() {
            this.actionLoading.rebuild = true
            this.actionResults.rebuild = null
            try {
                const response = await axios.post('/api/admin/aba/ai-settings/rebuild')
                this.actionResults.rebuild = response.data
                await this.loadData()
            } catch (error) {
                this.actionResults.rebuild = { success: false, error: error.response?.data?.error || 'Aufbau fehlgeschlagen.' }
            } finally {
                this.actionLoading.rebuild = false
            }
        },

        async copyContent() {
            try {
                await navigator.clipboard.writeText(this.displayContent)
                this.copySnackbar = true
            } catch {
                // Fallback für Browser ohne Clipboard-API
            }
        },

        async loadHardeningStatus() {
            this.hardeningStatusLoading = true
            try {
                const response = await axios.get('/api/admin/aba/seed-hardening/status')
                this.hardeningStatus = response.data
            } catch {
                this.hardeningStatus = { open_issues: { found: false, total_issues: 0, by_type: {}, generated_at: null }, last_run: null, artifacts: [], proposals_dir: '', editorial_notes: { found: false, total_extracted: 0 } }
            } finally {
                this.hardeningStatusLoading = false
            }
        },

        startAnalyzeProgress() {
            this.analyzeProgress.currentStep = 0
            this.analyzeProgress.timers.forEach(clearTimeout)
            this.analyzeProgress.timers = []

            this.analyzeProgress.steps.forEach((step, i) => {
                if (step.delay === 0) {
                    return // Schritt 0 ist sofort aktiv
                }
                const t = setTimeout(() => {
                    if (this.hardeningLoading.analyze) {
                        this.analyzeProgress.currentStep = i
                    }
                }, step.delay)
                this.analyzeProgress.timers.push(t)
            })
        },

        stopAnalyzeProgress() {
            this.analyzeProgress.timers.forEach(clearTimeout)
            this.analyzeProgress.timers = []
        },

        async runAnalyzeAndPropose() {
            this.hardeningLoading.analyze = true
            this.hardeningResults.analyze = null
            this.startAnalyzeProgress()
            try {
                const response = await axios.post('/api/admin/aba/seed-hardening/analyze-and-propose')
                this.hardeningResults.analyze = response.data
                await this.loadHardeningStatus()
            } catch (error) {
                this.hardeningResults.analyze = { success: false, error: error.response?.data?.error || 'Analyse fehlgeschlagen.' }
            } finally {
                this.stopAnalyzeProgress()
                this.hardeningLoading.analyze = false
            }
        },

        async runScan() {
            this.hardeningLoading.scan = true
            this.hardeningResults.scan = null
            try {
                const response = await axios.post('/api/admin/aba/seed-hardening/scan')
                this.hardeningResults.scan = response.data
                await this.loadHardeningStatus()
            } catch (error) {
                this.hardeningResults.scan = { success: false, error: error.response?.data?.error || 'Erkennung offener Punkte fehlgeschlagen.' }
            } finally {
                this.hardeningLoading.scan = false
            }
        },

        async runCleanup() {
            this.hardeningLoading.cleanup = true
            this.hardeningResults.cleanup = null
            try {
                const response = await axios.post('/api/admin/aba/seed-hardening/cleanup')
                this.hardeningResults.cleanup = response.data
                await this.loadHardeningStatus()
            } catch (error) {
                this.hardeningResults.cleanup = { success: false, error: error.response?.data?.error || 'Bereinigung fehlgeschlagen.' }
            } finally {
                this.hardeningLoading.cleanup = false
            }
        },

        issueTypeColor(type) {
            const map = {
                editorial_meta: 'amber',
                source_placeholder: 'orange',
                verification_needed: 'red',
                scope_remnant: 'deep-purple',
                weak_statement: 'grey',
            }
            return map[type] ?? 'grey'
        },

        issueTypeLabel(type) {
            const map = {
                editorial_meta: 'Redaktionell',
                source_placeholder: 'Quelle fehlt',
                verification_needed: 'Verifikation',
                scope_remnant: 'Scope-Altlast',
                weak_statement: 'Schwach belegt',
            }
            return map[type] ?? type
        },

        sourceResolutionItemsSafe() {
            const items = this.hardeningResults?.analyze?.source_resolution?.items
            return Array.isArray(items) ? items : []
        },

        sourceResolutionOpenItemsSafe() {
            return this.sourceResolutionItemsSafe().filter((item) =>
                item.resolution_status === 'unresolved' || item.resolution_status === 'partially_resolved',
            )
        },

        sourceResolutionSummarySafe() {
            const sourceResolution = this.hardeningResults?.analyze?.source_resolution ?? {}
            return {
                resolved: Number(sourceResolution.resolved ?? 0),
                partially_resolved: Number(sourceResolution.partially_resolved ?? 0),
                unresolved: Number(sourceResolution.unresolved ?? 0),
                fully_resolved: Number(sourceResolution.fully_resolved ?? sourceResolution.resolved ?? 0),
                open_for_main_file: Number(
                    sourceResolution.open_for_main_file
                    ?? (Number(sourceResolution.partially_resolved ?? 0) + Number(sourceResolution.unresolved ?? 0)),
                ),
            }
        },

        artifactTypeColor(type) {
            const map = {
                ai_research: 'blue',
                ai_verification: 'teal',
                ai_draft: 'purple',
                pattern_draft: 'indigo',
                proposal: 'orange',
                editorial_cleanup: 'amber',
            }
            return map[type] ?? 'grey'
        },

        artifactTypeLabel(type) {
            const map = {
                ai_research: 'KI-Research',
                ai_verification: 'KI-Verifikation',
                ai_draft: 'KI-Vorschlag',
                pattern_draft: 'Muster-Vorschlag',
                proposal: 'Änderungsvorschlag',
                editorial_cleanup: 'Red.-Bereinigung',
            }
            return map[type] ?? type
        },

        statusCount(status) {
            return this.reviewState?.by_status?.[status] ?? 0
        },

        formatBytes(bytes) {
            if (bytes < 1024) return `${bytes} B`
            if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        },

        formatDateTime(value) {
            if (!value) return '–'
            const d = new Date(value)
            if (isNaN(d.getTime())) return value
            const hasTime = /T|\d{2}:\d{2}:\d{2}/.test(value)
            const date = d.toLocaleDateString('de-AT', { year: 'numeric', month: '2-digit', day: '2-digit' })
            if (!hasTime) return date
            const time = d.toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit' })
            return `${date} ${time}`
        },
    },
}
</script>

<style scoped>
.seed-report-page {
    background: #0f172a;
    min-height: 100vh;
}

.sr-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    overflow: hidden;
}

.sr-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.sr-nav__button {
    min-height: 54px;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.sr-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.sr-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.sr-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

.meta-grid {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.meta-row {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.meta-label {
    font-size: 0.72rem;
    color: rgba(71, 85, 105, 0.8);
    width: 110px;
    flex-shrink: 0;
}

.meta-value {
    font-size: 0.8rem;
    color: rgba(15, 23, 42, 0.88);
    word-break: break-word;
}

.meta-mono {
    font-family: monospace;
    font-size: 0.72rem;
}

.status-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.frontmatter-hint {
    padding: 4px 8px;
    background: rgba(15, 23, 42, 0.3);
    border-radius: 4px;
}

.content-wrapper {
    display: flex;
    gap: 0;
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(148, 163, 184, 0.12);
    border-radius: 8px;
    overflow: auto;
    max-height: 72vh;
    min-height: 320px;
}

.line-numbers {
    display: flex;
    flex-direction: column;
    padding: 12px 8px;
    background: rgba(15, 23, 42, 0.6);
    border-right: 1px solid rgba(148, 163, 184, 0.08);
    user-select: none;
    flex-shrink: 0;
    min-width: 40px;
    text-align: right;
}

.line-number {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 0.68rem;
    line-height: 1.55;
    color: rgba(148, 163, 184, 0.3);
}

.content-text {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 0.72rem;
    line-height: 1.55;
    color: rgba(203, 213, 225, 0.9);
    white-space: pre;
    word-break: normal;
    padding: 12px 16px;
    margin: 0;
    flex: 1;
    overflow: visible;
}

.action-result {
    animation: fadeDown 0.18s ease;
}

.fade-down-enter-active {
    animation: fadeDown 0.18s ease;
}

@keyframes fadeDown {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Workflow steps */
.workflow-steps {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    background: rgba(15, 23, 42, 0.2);
    border: 1px solid rgba(148, 163, 184, 0.1);
    border-radius: 8px;
    padding: 10px 14px;
}

.workflow-step {
    display: flex;
    align-items: center;
    gap: 6px;
}

.workflow-step-number {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.3);
    border: 1px solid rgba(99, 102, 241, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(165, 180, 252, 0.9);
    flex-shrink: 0;
}

.workflow-step-label {
    font-size: 0.75rem;
    color: rgba(148, 163, 184, 0.8);
}

.workflow-step-arrow {
    font-size: 0.8rem;
    color: rgba(148, 163, 184, 0.35);
}

/* Action cards */
.action-card {
    background: rgba(15, 23, 42, 0.25);
    border: 1px solid rgba(148, 163, 184, 0.1);
    border-radius: 10px;
    padding: 14px;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.action-card--primary {
    border-color: rgba(167, 139, 250, 0.25);
    background: rgba(88, 28, 135, 0.1);
}

.action-card-header {
    margin-bottom: 12px;
    flex: 1;
}

.action-card-step {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(148, 163, 184, 0.5);
    margin-bottom: 4px;
}

.action-card-title {
    font-size: 0.88rem;
    font-weight: 600;
    color: rgba(203, 213, 225, 0.9);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
}

.action-card-desc {
    line-height: 1.4;
}

.action-card-status {
    margin-top: 8px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}

.hardening-code {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 0.68rem;
    background: rgba(15, 23, 42, 0.3);
    padding: 1px 4px;
    border-radius: 3px;
    word-break: break-all;
}

.artifact-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid rgba(148, 163, 184, 0.07);
}

.artifact-row:last-child {
    border-bottom: none;
}

.artifact-type {
    flex-shrink: 0;
    width: 100px;
}

.artifact-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.artifact-meta {
    flex-shrink: 0;
    white-space: nowrap;
}

.provider-badge {
    background: rgba(15, 23, 42, 0.2);
    border: 1px solid rgba(148, 163, 184, 0.1);
    border-radius: 6px;
    padding: 8px 10px;
}

/* Analyse-Fortschrittsanzeige */
.analyze-progress {
    background: rgba(88, 28, 135, 0.08);
    border: 1px solid rgba(167, 139, 250, 0.15);
    border-radius: 10px;
    padding: 12px 14px;
}

.analyze-steps {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.analyze-step {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.78rem;
    transition: opacity 0.3s;
}

.analyze-step--done {
    opacity: 0.5;
}

.analyze-step--active {
    opacity: 1;
    font-weight: 600;
    color: rgba(216, 180, 254, 0.95);
}

.analyze-step--pending {
    opacity: 0.3;
}

.analyze-step-icon {
    width: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.analyze-step-label {
    line-height: 1.3;
}

.analyze-hint {
    display: flex;
    align-items: center;
    font-size: 0.72rem;
    padding-top: 4px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}
</style>

<template>
    <div class="mc-workspace" :class="{ 'mc-catalog-detail': section === 'catalogs' && catalogEditing, 'mc-test-create': section === 'tests' && preparingTest }">
        <section class="mc-heading">
            <div>
                <h1>Multiple Choice</h1>
                <p>Fragen vorbereiten, gemeinsam starten und Ergebnisse im Blick behalten.</p>
            </div>
            <v-btn variant="text" prepend-icon="mdi-refresh" :loading="loading" @click="loadIndex">Aktualisieren</v-btn>
        </section>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-3" role="alert">{{ error }}</v-alert>
        <v-alert v-if="notice" type="success" variant="tonal" class="mb-3" role="status">{{ notice }}</v-alert>
        <v-alert v-if="templateSyncWarnings.length" type="warning" variant="tonal" class="mb-3" role="status">
            <ul><li v-for="(warning, index) in templateSyncWarnings" :key="index">{{ warning }}</li></ul>
        </v-alert>
        <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3"
            aria-label="Multiple Choice wird geladen" />
        <v-alert v-if="!available" type="info" variant="tonal"
            class="mb-3">{{ unavailableMessage || 'Multiple Choice muss für diese lokale Anwendung noch aktiviert werden.' }}</v-alert>
        <template v-if="available">
            <div v-if="section !== 'catalogs'" class="mc-toolbar mb-4" aria-label="Zurücknavigation">
                <v-btn v-if="selectedTestId || preparingTest" color="primary" variant="text" prepend-icon="mdi-arrow-left"
                    :disabled="!!busy" @click="showCatalogTests">Zur Testübersicht</v-btn>
                <v-btn v-else color="primary" variant="text" prepend-icon="mdi-arrow-left"
                    :disabled="!!busy" @click="openCatalog(catalog.id)">Zum Fragenkatalog</v-btn>
                <span class="text-body-2 text-medium-emphasis">{{ catalog.title }}<template v-if="selectedTest"> · {{ selectedTest.title }}</template></span>
            </div>

            <div v-if="section !== 'catalogs' || catalogEditing" class="mc-guide" :class="preparingTest ? 'mb-3' : 'mb-4'" aria-label="Nächster Schritt">
                <ol class="mc-steps" aria-label="Ablauf">
                    <li :class="{ 'is-current': section === 'catalogs' }">1 Fragen</li>
                    <li :class="{ 'is-current': section === 'tests' && (!selectedTest || preparingTest) }">2 Test</li>
                    <li :class="{ 'is-current': section === 'tests' && selectedTest && !preparingTest && !testEnded }">3 {{ selectedTest?.start_mode === 'individual' ? 'Selbstständig starten' : 'Gemeinsam starten' }}</li>
                    <li :class="{ 'is-current': selectedTest && testEnded }">4 Ergebnisse</li>
                </ol>
                <p>{{ nextStepHint }}</p>
            </div>

            <section v-if="section === 'catalogs'" aria-label="Fragenkataloge">
                <template v-if="!catalogEditing">
                    <div class="mc-toolbar mc-catalog-heading">
                        <div>
                            <h2>Meine Fragenkataloge</h2>
                            <p class="text-body-2 text-medium-emphasis mt-1">{{ catalogs.length }} {{ catalogs.length === 1 ? 'Katalog' : 'Kataloge' }}</p>
                        </div>
                        <v-spacer />
                        <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" :disabled="!!busy"
                            @click="newCatalog">Neuer Katalog</v-btn>
                    </div>
                    <v-sheet v-if="!catalogs.length" border rounded="lg" class="mc-catalog-empty">
                        <v-icon icon="mdi-bookshelf" size="36" color="primary" aria-hidden="true" />
                        <h3>Noch keine Fragenkataloge</h3>
                        <p>Beginne mit „Neuer Katalog“.</p>
                    </v-sheet>
                    <ul v-else class="mc-catalog-list" aria-label="Vorhandene Fragenkataloge">
                        <li v-for="item in catalogs" :key="item.id">
                            <v-card border rounded="lg" class="mc-catalog-card">
                                <v-card-text>
                                    <v-chip size="small" :color="readyQuestions(item.questions ?? []).length ? 'success' : undefined" variant="tonal">
                                        {{ readyQuestions(item.questions ?? []).length ? 'Für Tests bereit' : 'Entwurf' }}
                                    </v-chip>
                                    <h3 class="mt-4">{{ item.title }}</h3>
                                    <p class="text-body-2 text-medium-emphasis mt-2">{{ item.questions?.length ?? 0 }} {{ item.questions?.length === 1 ? 'Frage' : 'Fragen' }} · {{ readyQuestions(item.questions ?? []).length }} aktiv und geprüft</p>
                                </v-card-text>
                                <v-card-actions class="pa-4 pt-0">
                                    <v-btn color="primary" variant="text" append-icon="mdi-arrow-right"
                                        :aria-label="`Katalog öffnen: ${item.title}`" @click="openCatalog(item.id)">Katalog öffnen</v-btn>
                                </v-card-actions>
                            </v-card>
                        </li>
                    </ul>
                </template>
                <template v-else>
                    <div class="mc-toolbar">
                        <v-btn color="primary" variant="text" prepend-icon="mdi-arrow-left" :disabled="!!busy"
                            @click="backToCatalogs">Zur Katalogübersicht</v-btn>
                        <v-spacer />
                        <v-btn color="primary" variant="flat" prepend-icon="mdi-clipboard-play-outline" :disabled="!!busy"
                            @click="showCatalogTests">Tests zu diesem Katalog ({{ catalogTests.length }})</v-btn>
                    </div>
                    <v-card border rounded="lg" class="mt-3">
                    <v-card-text>
                        <form v-if="catalogRenaming" @submit.prevent="saveCatalogTitle">
                            <v-text-field v-model="catalogTitleDraft" label="Bezeichnung des Katalogs" variant="outlined"
                                autofocus maxlength="255" :disabled="!!busy" :error-messages="catalogTitleError"
                                :hide-details="!catalogTitleError" />
                            <div class="mc-toolbar mt-2">
                                <v-btn type="submit" color="primary" variant="flat" :disabled="!!busy"
                                    :loading="busy === 'catalog-title'">Bezeichnung speichern</v-btn>
                                <v-btn :disabled="!!busy" @click="cancelCatalogRename">Abbrechen</v-btn>
                            </div>
                        </form>
                        <div v-else class="mc-toolbar">
                            <h2 class="text-break">{{ catalog.title }}</h2>
                            <v-btn v-if="editingQuestions" icon="mdi-pencil-outline" size="small" variant="text" :disabled="!!busy"
                                aria-label="Katalog umbenennen" title="Katalog umbenennen" @click="startCatalogRename" />
                            <v-spacer />
                            <v-btn v-if="!editingQuestions" color="primary" variant="outlined" prepend-icon="mdi-pencil-outline"
                                :disabled="!!busy" @click="editingQuestions = true">Bearbeiten</v-btn>
                            <v-btn v-if="catalog.template_connection && !showImport" color="primary" variant="outlined"
                                prepend-icon="mdi-sync" :loading="busy === 'sync-preview'"
                                :disabled="!!busy || catalog.template_connection.status === 'ambiguous'"
                                @click="previewTemplateSync">Vorlage synchronisieren</v-btn>
                            <v-btn v-if="catalog.id" variant="text" color="error" :disabled="!!busy" @click="openDeletion('catalog', catalog)">Katalog löschen</v-btn>
                        </div>
                        <div class="mc-toolbar mt-4">
                            <p class="text-body-2">{{ catalog.questions.length }} Fragen · {{ catalogReadyCount }} aktiv
                                und geprüft</p>
                            <v-spacer />
                            <v-btn v-if="editingQuestions && !showImport && !catalog.template_connection" :variant="!catalog.questions.length ? 'flat' : 'text'"
                                :color="!catalog.questions.length ? 'primary' : undefined" prepend-icon="mdi-file-import-outline"
                                @click="navigateTo('import')">Vorlage importieren</v-btn>
                            <v-btn v-if="editingQuestions && !showImport" variant="outlined" prepend-icon="mdi-plus"
                                @click="catalog.questions.push(newQuestion())">Frage hinzufügen</v-btn>
                        </div>
                        <div v-if="catalog.template_connection" class="mc-template-connection mt-4" aria-label="Verbundene Fragenvorlage">
                            <div class="mc-toolbar">
                                <div>
                                    <strong>{{ catalog.template_connection.status === 'connected' ? 'Vorlage verbunden' : 'Vorlage noch nicht bestätigt' }}</strong>
                                    <p class="text-caption text-medium-emphasis">{{ catalog.template_connection.status === 'connected'
                                        ? 'Schooltool und Dropbox verwenden diese Vorlage beim manuellen Abgleich.'
                                        : catalog.template_connection.status === 'ambiguous'
                                            ? 'Mehrere frühere Vorlagen kommen infrage. Bitte die Verbindung lösen und die gewünschte Vorlage erneut importieren.'
                                            : 'Die frühere Importquelle wird beim ersten Abgleich online geprüft und bestätigt.' }}</p>
                                </div>
                                <v-spacer />
                                <v-btn v-if="catalog.template_connection.status === 'connected'" size="small" variant="outlined"
                                    prepend-icon="mdi-text-box-check-outline" :loading="busy === 'summary-preview'"
                                    :disabled="!!busy || editingQuestions" @click="previewQuestionSummary">Question Summary</v-btn>
                                <v-btn size="small" variant="text" :disabled="!!busy" @click="disconnectRecoveryToken = null; disconnectTemplateDialog = true">Verbindung auflösen</v-btn>
                            </div>
                            <p class="text-body-2 mt-2">Pfad der Fragenvorlage:</p>
                            <p class="mc-source-path text-caption">{{ catalog.template_connection.source_path || 'Pfad nicht eindeutig' }}</p>
                            <p v-if="catalog.template_connection.message" class="text-caption mt-2">{{ catalog.template_connection.message }}</p>
                        </div>
                        <p v-if="editingQuestions || showImport" class="text-caption text-medium-emphasis mt-2">Mit dem Übernehmen oder Speichern bestätigst du deine
                            importierten oder bearbeiteten vollständigen Fragen für neue Tests. Unvollständige Fragen bleiben Entwürfe.</p>
                        <div v-if="showImport" class="mc-import mt-4">
                            <div class="mc-toolbar mb-2">
                                <h2>Fragen aus einer Vorlage</h2>
                                <v-spacer />
                                <v-btn variant="text" prepend-icon="mdi-close" :disabled="!!busy"
                                    @click="cancelImport">Import abbrechen</v-btn>
                            </div>
                            <p class="text-body-2 mb-3">Lies deine fertigen Fragen mit Antwortoptionen und markierten Lösungen ein.
                                Prüfe die Vorschau, bevor du sie in den Katalog übernimmst.
                            </p>
                            <FilePond name="mc-template" :allow-multiple="false" :max-files="1"
                                :instant-upload="false" :allow-process="false" :allow-revert="false"
                                :allow-replace="true" :allow-paste="false" :server="null" :credits="false" :disabled="!!busy"
                                :accepted-file-types="['text/markdown', 'text/plain', 'application/json']" :file-validate-type-detect-type="templateFileType"
                                label-idle="<strong>Vorlage hierher ziehen oder <span class='filepond--label-action'>Datei auswählen</span></strong>"
                                label-file-loading="Datei wird gelesen" label-file-load-error="Datei konnte nicht gelesen werden"
                                label-tap-to-retry="Erneut versuchen" label-tap-to-cancel="Abbrechen"
                                label-button-remove-item="Datei entfernen" @updatefiles="readSourceFiles" />
                            <p class="text-caption text-medium-emphasis mt-2">Markdown (.md), Text (.txt), JSON (.json) oder handshake.json des Lernmoduls. Mit handshake.json werden vorhandene Vorlagen aus „Multiple Choice Fragen“ in Dropbox gelesen.</p>
                            <div v-if="importReading" role="status" aria-live="polite" class="mt-3" data-testid="template-loading">
                                <v-progress-linear indeterminate color="primary" class="mb-2" aria-label="Fragenvorlage wird geladen" />
                                <p class="text-body-2">{{ moduleHandshake ? 'Lernmodul wird in Dropbox gesucht und Fragenvorlage wird geladen …' : 'Datei wird gelesen …' }}</p>
                            </div>
                            <div v-if="moduleImport" class="mt-3 text-caption">
                                <div class="mc-source-path">{{ moduleImport.module.folder_path }}</div>
                                <div>Modul-ID: {{ moduleImport.module.module_id }}</div>
                                <p>Online-Quelle geprüft. Noch nicht im Katalog gespeichert.</p>
                                <v-select v-if="moduleImport.sources.length > 1" :model-value="moduleSourceId" :items="moduleImport.sources"
                                    item-title="name" item-value="id" label="Fragenvorlage auswählen" :disabled="importReading || !!busy"
                                    @update:model-value="loadModuleSource($event)" />
                                <p v-if="moduleImport.module_source" class="mc-source-path">{{ moduleImport.module_source.source_path }}</p>
                            </div>
                            <v-alert v-if="importError" type="error" variant="tonal" class="mt-3" role="alert">{{ importError }}</v-alert>
                            <p v-else-if="importDetection.format" class="text-caption mt-2" role="status">
                                Erkanntes Format: {{ importDetection.format === 'json' ? 'JSON' : 'Markdown / Text' }}
                            </p>
                            <v-btn v-if="!moduleHandshake" class="mt-3" variant="outlined" :loading="busy === 'import'"
                                :disabled="!importText.trim() || !!importError || importReading || !!busy"
                                @click="previewSource">Import prüfen</v-btn>
                            <div v-if="importPreview" class="mt-4">
                                <v-alert v-for="(item, position) in importPreview.errors ?? []"
                                    :key="`error-${position}`" type="error" variant="tonal"
                                    class="mb-2">{{ messageText(item) }}</v-alert>
                                <v-alert v-for="(item, position) in importPreview.warnings ?? []"
                                    :key="`warning-${position}`" type="warning" variant="tonal"
                                    class="mb-2">{{ messageText(item) }}</v-alert>
                                <p class="text-body-2 mb-2">Vorschau: {{ importPreview.questions?.length ?? 0 }} Fragen.
                                    Die Übernahme ergänzt den geöffneten Katalog.</p>
                                <QuestionEditor v-model:questions="importPreview.questions" id-prefix="import" />
                                <v-btn class="mt-3" color="primary" variant="flat" :disabled="!importPreview.questions?.length || !catalog.title.trim() || !!busy"
                                    :loading="busy === 'import-save'" @click="adoptImport">Fragen importieren</v-btn>
                            </div>
                        </div>
                        <QuestionEditor v-model:questions="catalog.questions" :readonly="!editingQuestions" />
                        <div v-if="!catalog.template_connection" v-for="source in catalog.module_sources ?? []" :key="`${source.source_file_id}:${source.source_revision}`"
                            class="text-caption text-medium-emphasis mt-2 mc-source-path">Früher importiert aus: {{ source.source_path }} · Modul-ID: {{ source.module_id }}</div>
                    </v-card-text>
                    <v-card-actions class="mc-catalog-actions pa-4 pt-0 justify-end flex-wrap ga-2">
                        <v-btn v-if="editingQuestions" variant="text" :disabled="!!busy" @click="cancelQuestionEditing">Bearbeitung abbrechen</v-btn>
                        <v-btn v-if="editingQuestions && catalogQuestionsChanged && !showImport" color="primary" variant="flat" prepend-icon="mdi-content-save-outline"
                            :loading="busy === 'catalog'" :disabled="!catalog.title.trim() || !!busy"
                            @click="saveCatalog">Fragen speichern</v-btn>
                    </v-card-actions>
                    </v-card>
                </template>
            </section>

            <section v-if="section === 'tests' && !preparingTest && !selectedTestId" aria-label="Tests des Fragenkatalogs">
                <div class="mc-toolbar mb-3">
                    <div>
                        <h2>Tests zu „{{ catalog.title }}“</h2>
                        <p class="text-body-2 text-medium-emphasis mt-1">{{ catalogTests.length }} {{ catalogTests.length === 1 ? 'Test' : 'Tests' }} · {{ showUnassignedTests ? 'Altbestand ohne Schuljahrzuordnung' : schoolyearLabel }}</p>
                    </div>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" prepend-icon="mdi-plus"
                        :disabled="!canPrepareCatalog || !!busy" @click="prepareCatalogTest">Neuen Test anlegen</v-btn>
                </div>
                <div class="mc-toolbar mb-3" v-if="unassignedTests.some(test => test.catalog_id === catalog.id)">
                    <label><input type="checkbox" v-model="showUnassignedTests"> Altbestand ohne eindeutiges Schuljahr anzeigen</label>
                </div>
                <p class="text-caption text-medium-emphasis mb-3">Fragenkataloge bleiben über Schuljahre hinweg erhalten. Die Testliste folgt dem oben in Schooltool gewählten Schuljahr.</p>
                <p v-if="!canPrepareCatalog" class="text-body-2 text-medium-emphasis mb-3">Prüfe und speichere zuerst die Fragen im Katalog. Danach kannst du einen Test anlegen.</p>
                <v-sheet v-if="!catalogTests.length" border rounded="lg" class="pa-6 text-medium-emphasis">
                    <p>Zu diesem Fragenkatalog gibt es noch keine Tests.</p>
                </v-sheet>
                <ul v-else class="mc-catalog-list mc-test-list" aria-label="Tests dieses Katalogs">
                    <li v-for="item in catalogTests" :key="item.id">
                        <v-card border rounded="lg" class="mc-catalog-card">
                            <v-card-text>
                                <div class="mc-test-heading-title">
                                    <h3>{{ item.title }}</h3>
                                    <v-btn icon="mdi-pencil-outline" size="small" variant="text" :disabled="!!busy"
                                        :aria-label="`Test umbenennen: ${item.title}`" title="Test umbenennen" @click="openTestRename(item)" />
                                </div>
                                <p class="text-body-2 text-medium-emphasis mt-2">{{ item.question_count }} Fragen · {{ item.total_points }} Punkte · {{ Math.round(item.duration_seconds / 60) }} Minuten</p>
                                <p class="text-caption text-medium-emphasis mt-1">{{ item.status === 'draft' ? 'Erstellt · noch nicht freigegeben' : item.start_mode === 'individual' ? `Selbstständiger Start · Schlussfrist: ${dateLabel(item.closes_at)}` : item.starts_at ? `Start: ${dateLabel(item.starts_at)}` : 'Warteraum geöffnet' }}</p>
                            </v-card-text>
                            <v-card-actions class="mc-test-card-actions pa-4 pt-0">
                                <v-btn color="primary" variant="text" append-icon="mdi-arrow-right" :disabled="!!busy"
                                    :aria-label="`Test öffnen: ${item.title}`" @click="selectTest(item.id)">Test öffnen</v-btn>
                                <v-btn variant="text" color="error" :disabled="!!busy" :aria-label="`Test löschen: ${item.title}`" @click="openDeletion('test', item)">Löschen</v-btn>
                            </v-card-actions>
                        </v-card>
                    </li>
                </ul>
            </section>

            <section v-if="section === 'tests' && preparingTest" aria-label="Tests vorbereiten">
                <v-card border rounded="lg">
                    <v-card-title class="px-4 pt-3 pb-1 text-subtitle-1 font-weight-bold">Neuen Test vorbereiten</v-card-title>
                    <v-card-text class="pt-2">
                        <p class="text-body-2 mb-3">Fragenkatalog: <strong>{{ catalog.title }}</strong></p>
                        <div class="mb-4">
                            <template v-if="!testFolderProof">
                            <v-btn variant="outlined" prepend-icon="mdi-file-link-outline" :loading="testFolderReading"
                                :disabled="!!busy || testFolderReading" @click="chooseTestFolder">Test mit Dropbox-Ordner verknüpfen</v-btn>
                            <p class="text-caption text-medium-emphasis mt-2">Wähle die handshake.json oder eine Markdown-Datei aus dem Testordner. Schooltool übernimmt die online geprüften Teilnehmer und speichert die Verbindung beim Erstellen.</p>
                            </template>
                            <v-alert v-if="testFolderError" type="error" variant="tonal" class="mt-2">{{ testFolderError }}</v-alert>
                            <v-alert v-if="testFolderWarning" type="warning" variant="tonal" class="mt-2">{{ testFolderWarning }}</v-alert>
                            <div v-if="testFolderProof && testFolderInfo" class="mt-2">
                                <span class="mc-source-path d-block">{{ testFolderInfo.path }}</span>
                                <v-btn variant="text" :disabled="!!busy" @click="disconnectDraftFolder">Verknüpfung aufheben</v-btn>
                            </div>
                        </div>
                        <div class="mc-form-grid">
                            <v-text-field v-model="testDraft.title" label="Testbezeichnung" variant="outlined" density="compact" class="mc-test-title"
                                hide-details />
                            <v-text-field :model-value="10" readonly label="Fragen pro Schüler"
                                variant="outlined" density="compact" hide-details />
                            <v-text-field v-model.number="testDraft.total_points" type="number" min="0.01" step="0.01"
                                label="Erreichbare Gesamtpunkte" variant="outlined" density="compact" hide-details />
                            <v-text-field v-model.number="testDraft.duration_minutes" type="number" min="1"
                                label="Bearbeitungsdauer in Minuten" variant="outlined" density="compact" hide-details />
                            <fieldset class="mc-start-modes" aria-label="Start des Tests" :disabled="!!busy">
                            <legend>Start des Tests</legend>
                            <div class="mc-start-mode-options">
                                <label v-for="option in startModes" :key="option.value" class="mc-start-mode" :class="{ 'is-selected': testDraft.start_mode === option.value }">
                                    <input v-model="testDraft.start_mode" type="radio" name="mc-start-mode-new" :value="option.value" :aria-label="option.title">
                                    <span><strong>{{ option.title }}</strong><span class="mc-start-mode-description">{{ option.description }}</span></span>
                                </label>
                            </div>
                        </fieldset>
                            <button v-if="testDraft.start_mode === 'individual'" type="button" class="mc-deadline-trigger" aria-label="Schlussfrist für alle" :disabled="!!busy" @click="openDeadlinePicker('new')">
                                <span class="mc-deadline-label">Schlussfrist für alle</span>
                                <strong>{{ testDraft.closes_at ? dateLabel(testDraft.closes_at) : 'Datum und Uhrzeit auswählen' }}</strong>
                                <span class="mc-deadline-action">{{ testDraft.closes_at ? 'Ändern' : 'Auswählen' }} <v-icon icon="mdi-calendar-month-outline" size="20" aria-hidden="true" /></span>
                            </button>
                            <v-text-field v-if="testDraft.start_mode === 'common'" v-model.number="testDraft.lead_seconds" type="number" min="0"
                                label="Vorlaufzeit in Sekunden" variant="outlined" density="compact" hide-details />
                        </div>
                        <p v-if="testDraft.start_mode === 'individual'" class="text-body-2 mt-3">Jede Person startet einmal selbst. Ab dann läuft die Zeit ohne Pause, spätestens bis zur Schlussfrist. Einzelne Fristen kannst du später in der Schülerliste anpassen.</p>
                        <label class="mc-check mt-3"><input v-model="testDraft.exam_mode" type="checkbox" aria-label="Prüfungsmodus aktivieren" />Prüfungsmodus: Seitenwechsel, Dauer und Vollbild protokollieren</label>
                        <details class="mc-more mt-3">
                            <summary>Weitere Einstellungen</summary>
                            <div class="mc-checks mt-3">
                            <label class="mc-check">
                                <input v-model="testDraft.balance_types" type="checkbox" />Einfach- und Mehrfachauswahl
                                gleichmäßig mischen</label>
                            <label class="mc-check">
                                <input v-model="testDraft.shuffle_questions" type="checkbox" />Fragenreihenfolge
                                mischen</label>
                            <label class="mc-check">
                                <input v-model="testDraft.shuffle_options" type="checkbox" />Antwortreihenfolge
                                mischen</label>
                            </div>
                        </details>
                        <v-alert type="info" variant="tonal" density="compact" class="mt-3 text-body-2">Jede Frage zählt gleich viel.
                            Punkte gibt es nur für die exakt richtige Auswahl; sonst null Punkte. Gerundet wird erst die
                            Gesamtpunktzahl.<span v-if="testDraft.balance_types"> Der Anteil beider Antworttypen folgt
                                dem Katalog und bleibt pro Person möglichst gleich.</span>
                        </v-alert>
                        <v-alert v-if="selectedCatalogReadyCount < 10" type="warning" variant="tonal" class="mt-3">Für die Erstellung sind mindestens zehn aktive, geprüfte Fragen erforderlich. Im Katalog sind {{ selectedCatalogReadyCount }} vorhanden.</v-alert>
                        <template v-if="!testFolderProof">
                        <h3 class="mt-4 mb-1">Schülerliste prüfen</h3>
                        <p class="text-body-2 mb-2">CSV mit Vorname, Nachname, E-Mail, Kurs und Klasse. Die gesamte
                            Liste wird mit bestehenden Schülerkonten abgeglichen. Unklare Zuordnungen müssen zuerst
                            korrigiert werden.</p>
                        <FilePond name="mc-roster" :allow-multiple="false" :max-files="1"
                            :instant-upload="false" :allow-process="false" :allow-revert="false"
                            :allow-replace="true" :allow-paste="false" :server="null" :credits="false" :disabled="!!busy"
                            label-idle="<strong>Schülerliste hierher ziehen oder <span class='filepond--label-action'>Datei auswählen</span></strong>"
                            label-file-loading="Datei wird gelesen" label-file-load-error="Datei konnte nicht gelesen werden"
                            label-tap-to-retry="Erneut versuchen" label-tap-to-cancel="Abbrechen"
                            label-button-remove-item="Datei entfernen" @updatefiles="readRosterFiles" />
                        <v-alert v-if="rosterFileError" type="error" variant="tonal" class="mt-3" role="alert">{{ rosterFileError }}</v-alert>
                        <p v-if="rosterFileName" class="text-caption text-medium-emphasis mt-2">{{ testFolderProof ? 'Online geprüft: ' : 'Ausgewählt: ' }}{{ rosterFileName }}</p>
                        <details class="mc-more mt-2">
                            <summary>CSV als Text einfügen oder bearbeiten</summary>
                            <label class="mc-native-field mt-3">
                                <span>Schülerliste als CSV</span>
                                <textarea v-model="rosterCsv" rows="5" @input="editRosterCsv" />
                            </label>
                        </details>
                        <v-btn class="mt-2" size="small" variant="outlined" prepend-icon="mdi-account-check-outline"
                            :loading="busy === 'roster' || rosterReading" :disabled="!rosterCsv.trim() || rosterReading || !!rosterFileError || !!busy"
                            @click="previewRoster">Zuordnung prüfen</v-btn>
                        <div v-if="roster" class="mt-4">
                            <v-alert v-for="(item, position) in roster.errors ?? []" :key="`roster-error-${position}`"
                                type="error" variant="tonal" class="mb-2">{{ messageText(item) }}</v-alert>
                            <v-alert v-for="(item, position) in roster.warnings ?? []"
                                :key="`roster-warning-${position}`" type="warning" variant="tonal"
                                class="mb-2">{{ messageText(item) }}</v-alert>
                            <p class="font-weight-bold mb-2">{{ roster.rows?.length ?? 0 }} Einträge ·
                                {{ rosterMatchedCount }} eindeutig zugeordnet</p>
                            <div class="mc-table-wrap">
                                <table class="mc-table">
                                    <thead>
                                        <tr>
                                            <th>Zeile</th>
                                            <th>Schüler</th>
                                            <th>E-Mail</th>
                                            <th>Klasse / Kurs</th>
                                            <th>Zuordnung</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in roster.rows" :key="row.row">
                                            <td>{{ row.row }}</td>
                                            <td>{{ row.last_name }} {{ row.first_name }}</td>
                                            <td>{{ row.email }}</td>
                                            <td>{{ row.class }} / {{ row.course }}</td>
                                            <td :class="row.status === 'matched' ? 'text-success' : 'text-error'">
                                                {{ row.status === 'matched' ? 'Eindeutig zugeordnet' : (row.errors ?? []).map(messageText).join(' ') || 'Zuordnung unklar' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        </template>
                        <p class="text-caption text-medium-emphasis mt-3">Beim Erstellen werden jeder Person zehn zufällig ausgewählte Fragen dauerhaft zugeordnet. Die Freigabe erfolgt anschließend separat. Es wird keine E-Mail versandt.</p>
                    </v-card-text>
                    <v-card-actions class="pa-4 pt-0">
                        <v-btn variant="text" :disabled="!!busy" @click="cancelNewTest">Abbrechen</v-btn>
                        <v-spacer />
                        <v-btn color="primary" variant="flat" :disabled="!canPublish || !!busy"
                            :loading="busy === 'publish'" @click="publishDialog = true">Test erstellen</v-btn>
                    </v-card-actions>
                </v-card>
            </section>

            <section v-if="selectedTestId && !preparingTest && section !== 'catalogs'" class="mt-4" aria-label="Geöffneter Test">
                <v-alert v-if="monitorError && !selectedTest" type="warning" variant="tonal"
                    class="mb-3">{{ monitorError }} <v-btn variant="text" @click="refreshMonitor">Erneut laden</v-btn>
                </v-alert>
                <v-progress-linear v-if="!selectedTest && !monitorError" indeterminate color="primary" aria-label="Test wird geladen" />
                <v-card v-if="selectedTest" border rounded="lg">
                    <v-card-text>
                        <div class="mc-toolbar">
                            <div>
                                <div class="mc-test-heading-title">
                                    <h2>{{ selectedTest.title }}</h2>
                                    <v-btn icon="mdi-pencil-outline" size="small" variant="text" :disabled="!!busy"
                                        aria-label="Test umbenennen" title="Test umbenennen" @click="openTestRename(selectedTest)" />
                                </div>

                                <p class="text-body-2 text-medium-emphasis">{{ selectedTest.question_count }} Fragen ·
                                    {{ selectedTest.total_points }} Punkte ·
                                    {{ Math.round(selectedTest.duration_seconds / 60) }} Minuten</p>
                            </div>
                            <v-spacer />
                            <v-chip :color="testPhase.color" variant="tonal">{{ testPhase.label }}</v-chip>
                        </div>
                        <nav class="mc-test-subnav mt-4" aria-label="Testbereiche">
                            <v-btn v-for="pane in testPanes" :key="pane.key" :variant="testPane === pane.key ? 'flat' : 'outlined'"
                                color="primary" :aria-current="testPane === pane.key ? 'page' : undefined" :disabled="!!busy"
                                @click="selectTestPane(pane.key)">{{ pane.label }}</v-btn>
                        </nav>
                        <div class="mc-monitor mt-4" role="status">
                            <div>
                                <strong>{{ attempts.filter((attempt) => attempt.status === 'ready').length }}</strong>
                                <span>bereit</span>
                            </div>
                            <div>
                                <strong>{{ attempts.filter((attempt) => attempt.status === 'in_progress').length }}</strong>
                                <span>in Bearbeitung</span>
                            </div>
                            <div>
                                <strong>{{ attempts.filter((attempt) => ['submitted', 'timeout'].includes(attempt.status)).length }}</strong>
                                <span>abgeschlossen</span>
                            </div>
                            <div>
                                <strong>{{ attempts.length }}</strong>
                                <span>zugewiesen</span>
                            </div>
                        </div>
                        <v-alert v-if="selectedTest.status === 'draft'" type="warning" variant="tonal" class="mt-4" role="status">
                            <strong>Noch nicht freigegeben</strong>
                            <p>Die Fragen sind pro Person gespeichert. Schüler können diesen Test erst nach der separaten Freigabe öffnen.</p>
                        </v-alert>
                        <p v-if="selectedTest.start_mode === 'individual'" class="text-body-2 mt-3">{{ selectedTest.status === 'draft' ? 'Nach Freigabe: selbstständiger Start' : 'Selbstständiger Start' }} · Schlussfrist für alle: {{ dateLabel(selectedTest.closes_at) }} · {{ selectedTest.duration_seconds / 60 }} Minuten ab persönlichem Start</p>
                        <p v-else-if="selectedTest.starts_at" class="text-body-2 mt-3">Start:
                            {{ dateLabel(selectedTest.starts_at) }} · Ende: {{ dateLabel(selectedTest.ends_at) }}</p>
                        <div v-if="section === 'tests'" v-show="['control', 'access'].includes(testPane)" class="mc-control-grid mc-control-grid--single mt-5">
                            <section v-show="testPane === 'control'" class="mc-control-section" aria-label="Prüfung steuern">
                                <h3>Prüfung steuern</h3>
                                <div class="mc-toolbar mt-3">
                                    <v-btn v-if="selectedTest.status === 'draft'" color="primary" variant="flat" :loading="busy === 'publish'" :disabled="!!busy" @click="releaseTestDialog = true">Test freigeben</v-btn>
                                    <v-btn v-if="selectedTest.status !== 'draft' && selectedTest.start_mode !== 'individual' && !selectedTest.starts_at" color="primary" variant="flat" prepend-icon="mdi-play"
                                        :loading="busy === 'countdown'" :disabled="!!busy" @click="startDialog = true">Countdown starten ({{ selectedTest.lead_seconds }} s)</v-btn>
                                    <v-btn v-if="selectedTest.paused_at" color="primary" variant="flat" prepend-icon="mdi-play" :disabled="!!busy" @click="setTestPause(false)">Test fortsetzen</v-btn>
                                    <v-btn v-else-if="canPauseTest" variant="outlined" prepend-icon="mdi-pause" :disabled="!!busy" @click="setTestPause(true)">Alle pausieren</v-btn>
                                    <v-btn v-if="testRunning" variant="outlined" :disabled="!!busy" @click="openRemainingTime">Restzeit ändern</v-btn>
                                    <v-btn v-if="canCloseTest" color="error" variant="text" :disabled="!!busy" @click="closeTestDialog = true">Test vorzeitig schließen</v-btn>
                                    <v-btn v-if="canReopenAttempts" variant="outlined" prepend-icon="mdi-lock-open-outline"
                                        :disabled="!!busy || !reopenSelectionEnabled || !attempts.length" @click="openReopenSelection">Wieder öffnen</v-btn>
                                </div>
                                <p v-if="allAttemptsCompleted" class="text-body-2 mt-3">Alle Bearbeitungen sind abgeschlossen. Zum Wiederöffnen wählst du hier alle geeigneten oder einzelne Schüler und ihre Zusatzzeit aus.</p>
                                <p v-else-if="testEnded && hasPersonalRunningAttempts" class="text-body-2 mt-3">Persönliche Zusatzzeit läuft. Abgeschlossene Bearbeitungen kannst du hier wieder öffnen.</p>
                                <p v-if="hasCompletedAttempts && selectedTest.start_mode !== 'individual' && !testEnded" class="text-body-2 mt-2">Wiederöffnen ist nach Ende der gemeinsamen Bearbeitungszeit möglich. Du kannst den laufenden Test dafür vorzeitig schließen.</p>
                                <div class="mc-toolbar mt-3">
                                    <v-btn variant="text" :disabled="!!busy || allAttemptsCompleted" :prepend-icon="selectedTest.exam_mode ? 'mdi-eye-off-outline' : 'mdi-eye-outline'" @click="toggleExamMode">{{ selectedTest.exam_mode ? 'Prüfungsmodus deaktivieren' : 'Prüfungsmodus aktivieren' }}</v-btn>
                                </div>
                                <p class="text-caption text-medium-emphasis mt-2">Status wird automatisch aktualisiert.</p>
                            </section>
                            <section v-show="testPane === 'access'" class="mc-control-section" aria-label="Zugang und Einladungen">
                                <h3>Zugang und Einladungen</h3>
                                <p class="text-body-2 text-medium-emphasis mt-2">{{ selectedTest.access_links_disabled_at ? 'Persönliche Zugangslinks sind deaktiviert. Die Schüleranmeldung bleibt möglich.' : 'Persönliche Links öffnen oder kopieren: direkt in der Schülerliste.' }}</p>
                                <div class="mc-toolbar mt-3">
                                    <v-btn variant="outlined" prepend-icon="mdi-email-outline" :disabled="!!busy || !!selectedTest.access_links_disabled_at" @click="openInvitationDialog">Einladungen vorbereiten</v-btn>
                                    <v-btn variant="outlined" prepend-icon="mdi-folder-sync-outline" :disabled="!!busy" @click="folderDialog = true">Im Testordner speichern</v-btn>
                                    <v-btn variant="text" :loading="busy === 'exercise-folder-export'" :disabled="!!busy || !!selectedTest.access_links_disabled_at" @click="saveExerciseExportToDirectory">Einmalig lokal speichern</v-btn>
                                    <v-btn variant="outlined" prepend-icon="mdi-download-outline" :loading="busy === 'exercise-export'" :disabled="!!busy || !!selectedTest.access_links_disabled_at" @click="downloadExerciseExport">Für Leistungsfeststellung exportieren</v-btn>
                                    <v-btn variant="text" :prepend-icon="selectedTest.access_links_disabled_at ? 'mdi-link' : 'mdi-link-off'" :disabled="!!busy" @click="toggleAccessLinks">{{ selectedTest.access_links_disabled_at ? 'Zugangslinks aktivieren' : 'Zugangslinks deaktivieren' }}</v-btn>
                                </div>
                                <p class="text-caption text-medium-emphasis mt-2">Die persönlichen Testlinks können schon vor dem Teststart im lokalen Testordner gespeichert und für die Aufgaben-Mails verwendet werden. Der Export startet den Test nicht.</p>
                                <p v-if="selectedTest.access_links_disabled_at" class="text-caption mt-2">Die Sperre gilt auch für bereits geöffnete persönliche Links.</p>
                            </section>
                        </div>
                        <v-alert v-if="selectedTest.paused_at" type="warning" variant="tonal" class="mt-4">Pausiert: Alle Eingaben und die Restzeit sind eingefroren. Beim Fortsetzen verschieben sich auch die Schlussfristen um die Pausendauer.</v-alert>
                        <p v-if="selectedTest.exam_mode" class="text-caption text-medium-emphasis mt-3">Das Prüfungsprotokoll zeigt Browsermeldungen, keine automatische Bewertung. Bei fehlender Verbindung wird der Status als unklar angezeigt.</p>
                        <v-alert v-if="monitorError" type="warning" variant="tonal" class="mt-4">{{ monitorError }}
                            Angezeigte Daten können veraltet sein.</v-alert>
                        <section v-show="testPane === 'results'" class="mc-results-section mt-5" aria-label="Ergebnisse und Lösungen">
                            <h3 class="mb-3">Ergebnisse und Lösungen</h3>
                            <v-alert :type="selectedTest.results_released_at ? 'success' : 'info'" variant="tonal"
                                class="mb-3">{{ selectedTest.results_released_at ? 'Ergebnisse sind für abgeschlossene Abgaben freigegeben.' : 'Ergebnisse sind für Schüler noch verborgen.' }}
                                {{ selectedTest.solutions_released_at ? 'Auch Lösungen sind freigegeben.' : 'Lösungen sind noch verborgen.' }}</v-alert>
                            <div class="mc-toolbar mb-4">
                                <v-btn v-if="!selectedTest.results_released_at" variant="outlined"
                                    :disabled="!hasCompletedAttempts || !!busy" @click="releaseMode = 'results'">Ergebnisse
                                    freigeben</v-btn>
                                <v-btn v-if="!selectedTest.solutions_released_at" variant="outlined"
                                    :disabled="!hasCompletedAttempts || !selectedTest.results_released_at || !!busy" @click="releaseMode = 'solutions'">Lösungen freigeben</v-btn>
                                <v-btn v-if="selectedTest.solutions_released_at" variant="text" prepend-icon="mdi-eye-off-outline"
                                    :disabled="!!busy" @click="revokeMode = 'solutions'">Lösungen verbergen</v-btn>
                                <v-btn v-if="selectedTest.results_released_at" variant="text" prepend-icon="mdi-eye-off-outline"
                                    :disabled="!!busy" @click="revokeMode = 'results'">Ergebnisse verbergen</v-btn>
                                <span v-if="!hasCompletedAttempts" class="text-caption text-medium-emphasis">Freigabe möglich, sobald eine Abgabe abgeschlossen ist.</span>
                            </div>
                            <p class="text-body-2 text-medium-emphasis">Jeder sieht freigegebene Ergebnisse und Lösungen erst nach der eigenen Abgabe. Noch offene Tests bleiben bearbeitbar; abwesende Personen blockieren die Freigabe nicht.</p>
                        </section>
                        <section v-show="testPane === 'students'" aria-label="Schülerübersicht">
                        <h3 class="mt-5">Schülerübersicht</h3>
                        <div class="mc-table-wrap mt-4">
                            <table class="mc-table">
                                <thead>
                                    <tr>
                                        <th>Schüler</th>
                                        <th>Zugang</th>
                                        <th>Status</th>
                                        <th v-if="showActivity">Prüfungsprotokoll</th>
                                        <th>Punkte</th>
                                        <th>Prozent</th>
                                        <th :title="selectedTest.start_mode === 'individual' ? 'Zeit seit dem persönlichen Teststart' : 'Zeit seit dem gemeinsamen Teststart'">Abgabe nach</th>
                                        <th>Auswertung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="attempt in attempts" :key="attempt.id">
                                        <tr>
                                            <td>
                                                <strong>{{ attempt.student_name }}</strong>
                                                <div class="text-caption text-medium-emphasis">{{ attempt.email }}</div>
                                            </td>
                                            <td class="mc-access-cell">
                                                <v-btn variant="text" size="small" prepend-icon="mdi-eye-outline" :aria-label="`Gespeicherte Fragen von ${attempt.student_name} ansehen`" @click="questionPreviewId = attempt.id">Fragenvorschau</v-btn>
                                                <v-btn v-if="attempt.access_url" :href="attempt.access_url" target="_blank" rel="noopener noreferrer"
                                                    icon="mdi-open-in-new" size="small" variant="tonal" color="primary"
                                                    :aria-label="`Persönlichen Test von ${attempt.student_name} öffnen`" title="Persönlichen Testzugang öffnen" />
                                                <v-btn v-if="attempt.access_url" icon="mdi-content-copy" size="small" variant="text" color="primary" :aria-label="`Zugangslink von ${attempt.student_name} kopieren`" title="Zugangslink kopieren" @click="copyAccessLink(attempt)" />
                                                <span v-else class="text-caption">{{ selectedTest.status === 'draft' ? 'Schülerzugang noch gesperrt' : '—' }}</span>
                                            </td>
                                            <td>{{ statusLabel(attempt.status) }}
                                                <div v-if="selectedTest.start_mode === 'individual'" class="text-caption text-medium-emphasis">
                                                    Schlussfrist: {{ dateLabel(attempt.closing_deadline) }}<span v-if="attempt.closes_at"> · Ausnahme</span>
                                                    <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" :disabled="!!busy" :aria-label="`Schlussfrist für ${attempt.student_name} ändern`" title="Persönliche Schlussfrist ändern" @click="openClosingDeadline(attempt)" />
                                                </div>
                                                <div v-if="attempt.reopened_until && !attempt.completed_at" class="text-caption text-medium-emphasis">{{ personalTimeLabel(attempt) }}</div>
                                            </td>
                                            <td v-if="showActivity" class="mc-activity-cell">
                                                <span class="mc-activity-state" :class="`mc-activity-${attempt.activity?.state || 'not_reported'}`">{{ activityStateLabel(attempt.activity) }}</span>
                                                <div class="text-caption mt-1">{{ attempt.activity?.away_count || 0 }} Wechsel · {{ activityDuration(attempt.activity?.away_seconds) }} außerhalb</div>
                                                <div class="text-caption text-medium-emphasis">Vollbild verlassen: {{ attempt.activity?.fullscreen_exit_count || 0 }}×</div>
                                                <v-btn variant="text" size="small" :aria-label="`Prüfungsprotokoll von ${attempt.student_name} ansehen`" @click="activityExpandedAttempt = activityExpandedAttempt === attempt.id ? null : attempt.id">{{ activityExpandedAttempt === attempt.id ? 'Protokoll schließen' : 'Protokoll ansehen' }}</v-btn>
                                            </td>
                                            <td>{{ scoreLabel(attempt) }}</td>
                                            <td>{{ isScored(attempt) ? `${attempt.percentage ?? 0} %` : '—' }}</td>
                                            <td>{{ completionTimeLabel(attempt) }}</td>
                                            <td>
                                                <v-btn v-if="isScored(attempt)" variant="text" size="small"
                                                    @click="toggleAttempt(attempt.id)">{{ expandedAttempt === attempt.id ? 'Schließen' : 'Fragen ansehen' }}</v-btn>
                                            </td>
                                        </tr>
                                        <tr v-if="showActivity && activityExpandedAttempt === attempt.id"><td :colspan="tableColumnCount" class="mc-evaluation">
                                            <strong>Prüfungsprotokoll · {{ attempt.student_name }}</strong>
                                            <p class="text-body-2 my-2">{{ attempt.activity?.away_count || 0 }} Wechsel, insgesamt {{ activityDuration(attempt.activity?.away_seconds) }}. Vollbild verlassen: {{ attempt.activity?.fullscreen_exit_count || 0 }}×.</p>
                                            <p v-if="!attempt.activity?.periods?.length" class="text-body-2">Bisher keine Abwesenheit protokolliert.</p>
                                            <ul v-else class="ml-5"><li v-for="(period, index) in attempt.activity.periods" :key="index">{{ dateLabel(period.left_at) }} → {{ (period.returned_at || period.ended_at) ? dateLabel(period.returned_at || period.ended_at) : 'noch außerhalb' }} · {{ activityDuration(period.seconds) }} · {{ period.reason === 'hidden' ? 'Testseite ausgeblendet' : 'Fenster ohne Fokus' }}</li></ul>
                                            <p v-if="attempt.activity?.fullscreen_exits?.length" class="text-body-2 mt-3">Vollbild verlassen: {{ attempt.activity.fullscreen_exits.map(dateLabel).join(' · ') }}</p>
                                            <p v-if="attempt.activity?.truncated" class="text-caption">Es werden die letzten 2.000 Zeiträume angezeigt; die Gesamtsumme bleibt vollständig.</p>
                                        </td></tr>
                                        <tr v-if="expandedAttempt === attempt.id">
                                            <td :colspan="tableColumnCount" class="mc-evaluation">
                                                <McQuestionReview v-for="(question, position) in attempt.question_results ?? attempt.evaluation ?? []"
                                                    :key="question.question_id ?? question.id" :question="question" :position="position" />
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        </section>
                        <section v-show="testPane === 'settings'" class="mt-5" aria-label="Testeinstellungen">
                            <h3>Testeinstellungen</h3>
                            <p class="text-body-2 my-3">{{ selectedTest.question_count }} Fragen · {{ selectedTest.total_points }} Punkte · {{ Math.round(selectedTest.duration_seconds / 60) }} Minuten</p>
                            <p v-if="!canEditTestSettings" class="text-body-2 mb-3">Die geschützten Einstellungen können nach Beginn einer Bearbeitung nicht mehr geändert werden.</p>
                            <v-btn variant="outlined" prepend-icon="mdi-cog-outline" :disabled="!!busy" @click="openTestSettings">Einstellungen bearbeiten</v-btn>
                            <details class="mc-test-maintenance mt-5"><summary>Test verwalten</summary><p class="text-body-2 my-3">Das Löschen entfernt auch Abgaben, Ergebnisse und persönliche Links dieses Tests.</p><v-btn variant="outlined" color="error" :disabled="!!busy" @click="openDeletion('test', selectedTest)">Test löschen</v-btn></details>
                        </section>
                    </v-card-text>
                </v-card>
            </section>
        </template>

        <v-dialog v-model="templateSyncDialog" persistent max-width="760" scrollable>
            <v-card v-if="templateSyncPreview" aria-label="Vorschau der Vorlagensynchronisierung">
                <v-card-title>Vorlage synchronisieren</v-card-title>
                <v-card-text>
                    <p class="text-body-2 mb-2">Vorlage: <span class="mc-source-path">{{ templateSyncPreview.source_path }}</span></p>
                    <p class="text-body-2 mb-4">Der Abgleich startet erst mit deiner Bestätigung. Änderungen in beiden Richtungen sind hier aufgeführt.</p>
                    <v-alert v-if="templateSyncPreview.resuming" type="warning" variant="tonal" class="mb-3">
                        Ein unterbrochener Abgleich wird fortgesetzt. Prüfe diese Vorschau erneut, bevor du ihn abschließt.
                    </v-alert>
                    <v-alert v-if="templateSyncPreview.initial_connection" type="info" variant="tonal" class="mb-3">
                        Beim ersten Abgleich wird die bisherige Importquelle fest verbunden. Die Markdown-Datei wird dafür mit technischen Fragenkennungen neu gespeichert.
                    </v-alert>
                    <div v-for="direction in templateSyncDirections" :key="direction.title" class="mc-sync-direction mb-4">
                        <h3>{{ direction.title }}</h3>
                        <p v-if="!syncChangeCount(direction.changes)" class="text-body-2 text-medium-emphasis mt-1">Keine Änderungen.</p>
                        <ul v-else class="mc-sync-changes mt-2">
                            <li v-for="change in direction.changes.added ?? []" :key="`add-${change.id}`">
                                <strong>Neu: {{ change.prompt }}</strong>
                                <div v-if="change.after" class="mc-sync-answer-detail">
                                    <p>{{ syncQuestionType(change.after) }}</p>
                                    <ul><li v-for="option in change.after.options ?? []" :key="option.id">
                                        <strong>{{ change.after.correct_option_ids?.includes(option.id) ? 'Richtig' : 'Falsch' }}:</strong> {{ option.text }}
                                    </li></ul>
                                </div>
                            </li>
                            <li v-for="change in direction.changes.modified ?? []" :key="`modify-${change.id}`">
                                <strong>Geändert: {{ change.prompt }}</strong>
                                <div v-if="change.before && change.after" class="mc-sync-compare">
                                    <div v-for="version in [{ label: 'Bisher', question: change.before }, { label: 'Nach dem Abgleich', question: change.after }]" :key="version.label" class="mc-sync-answer-detail">
                                        <h4>{{ version.label }}</h4>
                                        <p>{{ version.question.prompt }} · {{ syncQuestionType(version.question) }}</p>
                                        <ul><li v-for="option in version.question.options ?? []" :key="option.id">
                                            <strong>{{ version.question.correct_option_ids?.includes(option.id) ? 'Richtig' : 'Falsch' }}:</strong> {{ option.text }}
                                        </li></ul>
                                    </div>
                                </div>
                            </li>
                            <li v-for="change in direction.changes.removed ?? []" :key="`remove-${change.id}`">
                                <strong>Entfernt: {{ change.prompt }}</strong>
                                <div v-if="change.before" class="mc-sync-answer-detail">
                                    <p>{{ syncQuestionType(change.before) }}</p>
                                    <ul><li v-for="option in change.before.options ?? []" :key="option.id">
                                        <strong>{{ change.before.correct_option_ids?.includes(option.id) ? 'Richtig' : 'Falsch' }}:</strong> {{ option.text }}
                                    </li></ul>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <v-alert v-for="(warning, index) in templateSyncPreview.warnings ?? []" :key="`sync-warning-${index}`"
                        type="warning" variant="tonal" class="mb-3">{{ warning }}</v-alert>
                    <v-alert v-if="templateSyncPreview.conflicts?.length" type="warning" variant="tonal" role="alert">
                        <strong>Konflikte verhindern den Abgleich:</strong>
                        <ul class="mt-2"><li v-for="(conflict, index) in templateSyncPreview.conflicts" :key="index">{{ conflict }}</li></ul>
                    </v-alert>
                    <p v-else-if="!templateSyncPreview.can_apply" class="text-body-2 text-medium-emphasis">Derzeit ist keine Übernahme nötig.</p>
                    <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mt-3">{{ error }}</v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-btn :disabled="!!busy" @click="closeTemplateSync">Schließen</v-btn>
                    <v-spacer />
                    <v-btn v-if="templateSyncPreview.can_disconnect && templateSyncPreview.recovery_token" color="error" variant="outlined"
                        :disabled="!!busy" @click="openRecoveryDisconnect">Abgleich beenden und Verbindung lösen</v-btn>
                    <v-btn v-if="templateSyncPreview.can_apply && templateSyncPreview.token" color="primary" variant="flat"
                        :loading="busy === 'sync-apply'" :disabled="!!busy || !!error" @click="applyTemplateSync">Abgleich bestätigen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="questionSummaryDialog" persistent max-width="760" scrollable>
            <v-card v-if="questionSummaryPreview" aria-label="Vorschau der Question Summary">
                <v-card-title>Question Summary</v-card-title>
                <v-card-text>
                    <p class="text-body-2 mb-3">Dropbox-Datei: <span class="mc-source-path">{{ questionSummaryPreview.path }}</span></p>
                    <v-alert type="info" variant="tonal" class="mb-3">
                        {{ questionSummaryPreview.action === 'create' ? 'Die Zusammenfassung wird neu angelegt.'
                            : questionSummaryPreview.action === 'update' ? 'Die vorhandene Zusammenfassung wird aktualisiert.'
                                : 'Die Zusammenfassung ist bereits aktuell.' }}
                    </v-alert>
                    <p class="text-body-2 mb-3">{{ questionSummaryPreview.question_count }} Fragen · {{ questionSummaryPreview.incomplete_count }} unvollständig</p>
                    <v-alert v-if="questionSummaryPreview.incomplete_count" type="warning" variant="tonal" class="mb-3">
                        Unvollständige Fragen sind in der Datei ausdrücklich gekennzeichnet.
                    </v-alert>
                    <v-alert v-if="questionSummaryPreview.warning" type="warning" variant="tonal" class="mb-3">{{ questionSummaryPreview.warning }}</v-alert>
                    <div v-if="questionSummaryStructured" class="mc-summary-preview" role="list" aria-label="Fragen und richtige Antworten">
                        <div v-for="row in questionSummaryPreview.rows" :key="row.number" class="mc-summary-question" role="listitem">
                            <div class="text-caption text-medium-emphasis">{{ row.number }} · {{ row.type }}<span v-if="!row.active"> · Inaktiv</span></div>
                            <div class="font-weight-bold mc-summary-text">{{ row.prompt }}</div>
                            <p v-if="row.incomplete" class="text-warning mt-1">Unvollständige Frage</p>
                            <div class="text-caption mt-2">Richtige Antworten</div>
                            <ul v-if="row.answers?.length" class="mt-1">
                                <li v-for="(answer, position) in row.answers" :key="position" class="mc-summary-text">{{ answer }}</li>
                            </ul>
                            <p v-else class="text-body-2 text-medium-emphasis mt-1">Keine richtige Antwort hinterlegt.</p>
                        </div>
                        <p v-if="!questionSummaryPreview.rows.length" class="text-body-2">Keine Fragen im Katalog.</p>
                    </div>
                    <v-alert v-else type="error" variant="tonal">Die lesbare Vorschau fehlt. Bitte die Question Summary erneut öffnen.</v-alert>
                    <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mt-3">{{ error }}</v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-btn :disabled="!!busy" @click="closeQuestionSummary">Schließen</v-btn>
                    <v-spacer />
                    <v-btn v-if="questionSummaryPreview.can_apply && questionSummaryPreview.token" color="primary" variant="flat"
                        :loading="busy === 'summary-apply'" :disabled="!!busy || !!error || !questionSummaryStructured" @click="applyQuestionSummary">In Dropbox speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="disconnectTemplateDialog" persistent max-width="520">
            <v-card>
                <v-card-title>Vorlagenverbindung auflösen?</v-card-title>
                <v-card-text>
                    <p v-if="disconnectRecoveryToken">Der unterbrochene Abgleich wird beendet und nur die lokale Verbindung gelöst. Katalogfragen und Dropbox-Datei bleiben erhalten, können aber unterschiedliche Stände haben. Prüfe beide, bevor du erneut importierst.</p>
                    <p v-else>Der Fragenkatalog und die Dropbox-Vorlage bleiben erhalten. Danach kannst du eine Vorlage erneut importieren.</p>
                    <p class="mc-source-path text-body-2 mt-3">{{ catalog.template_connection?.source_path }}</p>
                    <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mt-3">{{ error }}</v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-btn :disabled="!!busy" @click="cancelTemplateDisconnect">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="error" variant="outlined" :loading="busy === 'sync-disconnect'" :disabled="!!busy"
                        @click="disconnectTemplate">{{ disconnectRecoveryToken ? 'Abgleich beenden und Verbindung lösen' : 'Verbindung auflösen' }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="testFolderDialog" persistent max-width="720" scrollable>
            <v-card aria-label="Dropbox-Testordner zuordnen">
                <v-card-title>Testordner zuordnen</v-card-title>
                <v-card-text>
                    <p class="text-body-2 mb-3">Wähle die handshake.json oder eine Markdown-Datei direkt aus dem Testordner.</p>
                    <FilePond v-if="testFolderDialog" :key="testFolderPondKey" name="mc-test-handshake" :allow-multiple="false" :max-files="1"
                        :instant-upload="false" :allow-process="false" :allow-revert="false" :allow-replace="true"
                        :allow-paste="false" :server="null" :credits="false" :disabled="testFolderReading"
                        :before-add-file="acceptTestFolderFile" :accepted-file-types="['application/json', 'text/markdown', '.md']"
                        :file-validate-type-detect-type="detectTestFolderFileType"
                        label-file-type-not-allowed="Zulässig sind handshake.json und Markdown-Dateien."
                        label-idle="Datei hierher ziehen oder <span class='filepond--label-action'>Datei auswählen</span>"
                        label-button-remove-item="Datei entfernen" @updatefiles="selectTestFolderFiles" />
                    <p class="text-caption text-medium-emphasis">Schooltool prüft den Testordner und die Schülerdaten im verbundenen Dropbox-Konto. Bei Markdown wird zum Finden der Datei ein digitaler Fingerabdruck verglichen.</p>
                    <v-progress-linear v-if="testFolderReading" indeterminate color="primary" class="mt-3" aria-label="Dropbox-Testordner wird geprüft" />
                    <v-alert v-if="testFolderError" type="error" variant="tonal" role="alert" class="mt-3">{{ testFolderError }}</v-alert>
                    <div v-if="testFolderPreview" class="mt-4">
                        <v-alert type="info" variant="tonal">Testordner online bestätigt. Prüfe die Angaben, bevor du sie übernimmst.</v-alert>
                        <div class="text-subtitle-2 mt-3">Gefundener Dropbox-Testordner</div>
                        <div class="mc-source-path" data-testid="test-folder-path">{{ testFolderPreview.path }}</div>
                        <div class="text-body-2 mt-2">Testname: <strong>{{ testFolderPreview.directory }}</strong></div>
                        <div class="text-body-2">{{ testFolderPreview.source?.work_title }} · {{ testFolderPreview.source?.course_title }}</div>
                        <div class="text-body-2">{{ testFolderPreview.rows?.length ?? 0 }} Schüler in der Vorschau</div>
                        <v-alert v-for="(item, position) in testFolderPreview.errors ?? []" :key="position" type="error" variant="tonal" class="mt-2">{{ messageText(item) }}</v-alert>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-btn :disabled="testFolderReading" @click="closeTestFolderDialog">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" :disabled="!testFolderPreview?.folder_proof || testFolderReading"
                        @click="useTestFolder">Für diesen Test vormerken</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="folderDialog" max-width="860" scrollable>
            <MultipleChoiceTestFolder v-if="folderDialog && selectedTest" :key="selectedTest.id" :test="selectedTest"
                :user="folderUser" @close="folderDialog = false" @changed="refreshMonitor" />
        </v-dialog>
        <v-dialog v-model="invitationDialog" persistent max-width="1000" scrollable>
            <v-card>
                <v-card-title>Einladungen vorbereiten</v-card-title>
                <v-card-text>
                    <p class="text-body-2 mb-4">Wähle zuerst die Ansprache. Danach prüfst du Empfänger und Nachricht; versendet wird erst nach deiner Bestätigung.</p>
                    <v-select v-model="invitationAddressForm" label="Ansprache der Einladungen" :items="[{ title: 'Sie-Form', value: 'sie' }, { title: 'Du-Form', value: 'du' }]" density="compact" variant="outlined" :disabled="!!busy" hide-details @update:model-value="resetInvitationPreview" />
                    <v-btn class="mt-4" color="primary" variant="outlined" :disabled="!!busy" :loading="busy === 'invitations'" @click="previewInvitations">Empfänger und E-Mail prüfen</v-btn>
                    <v-alert v-if="error" type="error" class="mt-3">{{ error }}</v-alert>
                        <div v-if="invitationPreview" class="mt-4">
                            <h3>Empfängervorschau</h3>
                            <p class="text-body-2 my-2">Jede Person erhält ihren persönlichen Testzugang. Bereits
                                versandte Einladungen werden übersprungen.</p>
                            <div class="mc-table-wrap">
                                <table class="mc-table">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" aria-label="Alle noch nicht eingeladenen Schüler auswählen" :checked="allInvitationsSelected" :disabled="!!busy || !availableInvitationRecipients.length" @change="selectAllInvitations($event.target.checked)"></th>
                                            <th>Name</th>
                                            <th>E-Mail</th>
                                            <th>Einladung</th>
                                            <th>E-Mail-Vorschau</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="recipient in invitationPreview.recipients"
                                            :key="recipient.attempt_id">
                                            <td><input type="checkbox" :aria-label="`Einladung auswählen: ${recipient.student_name}`" :checked="selectedInvitationIds.includes(recipient.attempt_id)" :disabled="!!busy || !!recipient.invited_at" @change="selectInvitation(recipient.attempt_id, $event.target.checked)"></td>
                                            <td>{{ recipient.student_name }}</td>
                                            <td>{{ recipient.email }}</td>
                                            <td>{{ recipient.invited_at ? `Versandt: ${dateLabel(recipient.invited_at)}` : 'Noch nicht versandt' }}</td>
                                            <td><v-btn size="small" variant="text" @click="invitationRecipientId = recipient.attempt_id">E-Mail ansehen</v-btn></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="invitationRecipient" class="mt-4" aria-label="Einladungsinhalt">
                                <h3>E-Mail an {{ invitationRecipient.student_name }}</h3>
                                <p class="text-body-2 my-2">Betreff und Text können für diese Person angepasst werden. Der persönliche Zugangsbutton wird automatisch ergänzt.</p>
                                <v-text-field v-model="invitationRecipient.subject" label="Betreff der Einladung" variant="outlined"
                                    maxlength="255" :disabled="!!busy" hide-details class="my-3" />
                                <v-textarea v-model="invitationRecipient.body" label="Nachrichtentext der Einladung" variant="outlined"
                                    rows="9" auto-grow maxlength="20000" :disabled="!!busy" hide-details class="mb-3" />
                                <iframe :srcdoc="invitationRecipientHtml" sandbox="" referrerpolicy="no-referrer" title="Vorschau der persönlichen Einladung" style="width: 100%; height: 340px; border: 1px solid #d7e1df; background: white;"></iframe>
                            </div>
                            <v-btn class="mt-3" color="primary" variant="flat"
                                :disabled="!unsentInvitationCount || !!busy"
                                @click="mailDialog = true">{{ unsentInvitationCount }} Einladungen per E-Mail
                                senden</v-btn>
                        </div>
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn :disabled="!!busy" @click="invitationDialog = false">Schließen</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="testSettingsDialog" persistent max-width="760" scrollable>
            <v-card>
                <v-card-title>Testeinstellungen</v-card-title>
                <v-card-text>
                    <v-alert v-if="!canEditTestSettings" type="info" variant="tonal" class="mb-4">Der Test wurde bereits gestartet oder pausiert. Fragen und Bewertungsgrundlagen bleiben geschützt. Restzeit, persönliche Schlussfristen und Wiederöffnen steuerst du in der Übersicht.</v-alert>
                    <p v-else class="text-body-2 mb-4">Änderungen gelten für alle noch nicht gestarteten Personen. Bei geändertem Fragenumfang oder geänderter Mischung werden Fragen aus dem ursprünglichen Katalogstand neu zugeteilt. Zugangslinks bleiben erhalten; Einladungen bitte neu vorbereiten.</p>
                    <fieldset class="mc-settings-fields" :disabled="!canEditTestSettings || !!busy">
                        <v-text-field v-model="settingsDraft.title" label="Testbezeichnung" variant="outlined" :disabled="!canEditTestSettings || !!busy" maxlength="255" />
                        <div class="mc-form-grid">
                            <v-text-field v-model.number="settingsDraft.question_count" type="number" label="Anzahl der Fragen" :min="1" :max="settingsPoolCount" variant="outlined" :disabled="!canEditTestSettings || !!busy || selectedTest?.snapshot?.assignments_locked" />
                            <v-text-field v-model.number="settingsDraft.total_points" type="number" label="Gesamtpunkte" min="0.01" step="0.01" variant="outlined" :disabled="!canEditTestSettings || !!busy" />
                            <v-text-field v-model.number="settingsDraft.duration_minutes" type="number" label="Bearbeitungszeit in Minuten" min="1" max="1440" step="0.1" variant="outlined" :disabled="!canEditTestSettings || !!busy" />
                        </div>
                        <fieldset class="mc-start-modes" aria-label="Startart" :disabled="!canEditTestSettings || !!busy">
                            <legend>Startart</legend>
                            <div class="mc-start-mode-options">
                                <label v-for="option in startModes" :key="option.value" class="mc-start-mode" :class="{ 'is-selected': settingsDraft.start_mode === option.value }">
                                    <input v-model="settingsDraft.start_mode" type="radio" name="mc-start-mode-settings" :value="option.value" :aria-label="option.title">
                                    <span><strong>{{ option.title }}</strong><span class="mc-start-mode-description">{{ option.description }}</span></span>
                                </label>
                            </div>
                        </fieldset>
                        <button v-if="settingsDraft.start_mode === 'individual'" type="button" class="mc-deadline-trigger mb-4" aria-label="Letzter Abgabezeitpunkt" :disabled="!canEditTestSettings || !!busy" @click="openDeadlinePicker('settings')">
                            <span class="mc-deadline-label">Letzter Abgabezeitpunkt</span>
                            <strong>{{ settingsDraft.closes_at ? dateLabel(settingsDraft.closes_at) : 'Datum und Uhrzeit auswählen' }}</strong>
                            <span class="mc-deadline-action">{{ settingsDraft.closes_at ? 'Ändern' : 'Auswählen' }} <v-icon icon="mdi-calendar-month-outline" size="20" aria-hidden="true" /></span>
                        </button>
                        <v-text-field v-else v-model.number="settingsDraft.lead_seconds" type="number" label="Countdown in Sekunden" min="0" max="86400" variant="outlined" :disabled="!canEditTestSettings || !!busy" />
                        <label class="mc-setting-check"><input type="checkbox" v-model="settingsDraft.exam_mode"> Prüfungsmodus mit Vollbild und Protokoll</label>
                        <label class="mc-setting-check"><input type="checkbox" v-model="settingsDraft.balance_types" :disabled="selectedTest?.snapshot?.assignments_locked"> Fragentypen ausgewogen verteilen</label>
                        <label class="mc-setting-check"><input type="checkbox" v-model="settingsDraft.shuffle_questions" :disabled="selectedTest?.snapshot?.assignments_locked"> Fragen mischen</label>
                        <label class="mc-setting-check"><input type="checkbox" v-model="settingsDraft.shuffle_options" :disabled="selectedTest?.snapshot?.assignments_locked"> Antwortmöglichkeiten mischen</label>
                    </fieldset>
                    <v-alert v-if="settingsError" type="error" class="mt-3">{{ settingsError }}</v-alert>
                </v-card-text>
                <v-card-actions><v-btn :disabled="!!busy" @click="testSettingsDialog = false">{{ canEditTestSettings ? 'Abbrechen' : 'Schließen' }}</v-btn><v-spacer /><v-btn v-if="canEditTestSettings" color="primary" variant="flat" :disabled="!!busy" :loading="busy === 'settings'" @click="saveTestSettings">Einstellungen speichern</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog :model-value="!!deadlinePickerTarget" persistent max-width="440" scrollable>
            <v-card>
                <form @submit.prevent="confirmDeadlinePicker">
                    <v-card-title>Schlussfrist auswählen</v-card-title>
                    <v-card-text class="mc-deadline-dialog">
                        <v-date-picker v-model="deadlinePickerDate" :min="deadlinePickerMinimum" color="primary" hide-header :first-day-of-week="1" aria-label="Datum der Schlussfrist" />
                        <v-text-field v-model="deadlinePickerTime" type="time" step="60" label="Uhrzeit der Schlussfrist" variant="outlined" hide-details class="mt-3" />
                        <p class="text-body-2 mt-3" aria-live="polite">{{ deadlinePickerValue ? dateLabel(deadlinePickerValue) : 'Bitte Datum und Uhrzeit wählen.' }}</p>
                        <p class="text-caption mt-2">Alle Uhrzeiten gelten für Wien.</p>
                        <p v-if="deadlinePickerValue && !viennaDateTimeIso(deadlinePickerValue)" class="text-body-2 text-error mt-2">Diese Uhrzeit gibt es in Wien wegen der Zeitumstellung nicht. Bitte wähle eine gültige Uhrzeit.</p>
                        <p v-else-if="deadlinePickerValue && !deadlinePickerValid" class="text-body-2 text-error mt-2">Die Schlussfrist muss in der Zukunft liegen.</p>
                    </v-card-text>
                    <v-card-actions><v-btn type="button" @click="deadlinePickerTarget = null">Abbrechen</v-btn><v-spacer /><v-btn type="submit" color="primary" variant="flat" :disabled="!deadlinePickerValid">OK</v-btn></v-card-actions>
                </form>
            </v-card>
        </v-dialog>
        <v-dialog :model-value="!!deletionTarget" persistent max-width="520">
            <v-card>
                <v-card-title>{{ deletionTarget?.kind === 'catalog' ? 'Fragenkatalog' : 'Test' }} dauerhaft löschen?</v-card-title>
                <v-card-text>
                    <p><strong>{{ deletionTarget?.title }}</strong></p>
                    <p class="mt-3" v-if="deletionTarget?.kind === 'catalog'">Der Fragenkatalog und seine {{ deletionTarget.testIds.length }} zugehörigen Tests aus allen Schuljahren werden einschließlich aller Abgaben und Ergebnisse dauerhaft gelöscht.</p>
                    <p class="mt-3" v-else>Dieser Test wird einschließlich aller persönlichen Zugangslinks, Abgaben und Ergebnisse dauerhaft gelöscht. Der Fragenkatalog bleibt erhalten.</p>
                    <v-alert v-if="error" type="error" role="alert">{{ error }}</v-alert>
                </v-card-text>
                <v-card-actions><v-btn :disabled="!!busy" @click="deletionTarget = null">Abbrechen</v-btn><v-spacer /><v-btn color="error" :loading="busy === 'delete'" :disabled="!!busy" @click="confirmDeletion">Dauerhaft löschen</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog :model-value="!!testRenameTarget" persistent max-width="500" aria-labelledby="mc-rename-test-title">
            <v-card>
                <form @submit.prevent="saveTestTitle">
                    <v-card-title id="mc-rename-test-title">Test umbenennen</v-card-title>
                    <v-card-text>
                        <v-text-field v-model="testTitleDraft" label="Neue Testbezeichnung" :error-messages="testTitleError" :disabled="!!busy" autofocus maxlength="255" />
                        <v-alert v-if="error" type="error" role="alert">{{ error }}</v-alert>
                    </v-card-text>
                    <v-card-actions><v-btn :disabled="!!busy" @click="testRenameTarget = null">Abbrechen</v-btn><v-spacer /><v-btn type="submit" color="primary" :loading="busy === 'rename-test'" :disabled="!!busy">Speichern</v-btn></v-card-actions>
                </form>
            </v-card>
        </v-dialog>
        <v-dialog v-model="remainingTimeDialog" persistent max-width="500">
            <v-card>
                <form @submit.prevent="saveRemainingTime">
                    <v-card-title>Restzeit für alle ändern</v-card-title>
                    <v-card-text>
                        <p class="mb-3">Die neue Restzeit gilt ab dem Speichern für alle. Bereits abgegebene Versuche bleiben abgeschlossen.</p>
                        <v-text-field v-model="remainingMinutes" type="number" min="0" max="1440" step="1" label="Verbleibende Minuten" :disabled="!!busy" />
                        <v-text-field v-model="remainingSeconds" type="number" min="0" max="59" step="1" label="Zusätzliche Sekunden" :disabled="!!busy" />
                        <p v-if="!validRemainingTime" role="alert">Bitte eine Restzeit zwischen einer Sekunde und 24 Stunden eingeben.</p>
                        <v-alert v-if="error" type="error" role="alert">{{ error }}</v-alert>
                    </v-card-text>
                    <v-card-actions><v-btn :disabled="!!busy" @click="remainingTimeDialog = false">Abbrechen</v-btn><v-spacer /><v-btn type="submit" color="primary" :disabled="!!busy || !validRemainingTime" :loading="busy === 'remaining-time'">Restzeit übernehmen</v-btn></v-card-actions>
                </form>
            </v-card>
        </v-dialog>
        <v-dialog :model-value="!!deadlineTarget" max-width="540" @update:model-value="deadlineTarget = null">
            <v-card v-if="deadlineTarget">
                <form @submit.prevent="saveClosingDeadline(false)">
                    <v-card-title>Persönliche Schlussfrist</v-card-title>
                    <v-card-text>
                        <p class="mb-3">{{ deadlineTarget.student_name }}</p>
                        <p class="text-body-2 mb-3">Für alle: {{ dateLabel(selectedTest.closes_at) }}. Eine Ausnahme gilt nur für diese Person. Eine laufende Bearbeitungsdauer wird dadurch nicht neu gestartet; abgeschlossene Abgaben bleiben geschlossen.</p>
                        <v-text-field v-model="deadlineDraft" type="datetime-local" label="Persönliche Schlussfrist" :disabled="!!busy" />
                    </v-card-text>
                    <v-card-actions><v-btn :disabled="!!busy" @click="deadlineTarget = null">Abbrechen</v-btn><v-btn :disabled="!!busy || !deadlineTarget.closes_at" @click="saveClosingDeadline(true)">Frist für alle übernehmen</v-btn><v-spacer /><v-btn type="submit" color="primary" :disabled="!!busy || !validDeadline">Speichern</v-btn></v-card-actions>
                </form>
            </v-card>
        </v-dialog>
        <v-dialog :model-value="!!copyFallback" max-width="620" @update:model-value="copyFallback = null">
            <v-card v-if="copyFallback"><v-card-title>Zugangslink kopieren</v-card-title><v-card-text><p class="mb-3">Der Browser erlaubt kein automatisches Kopieren. Markiere den Link und kopiere ihn.</p><v-text-field :model-value="copyFallback.access_url" readonly label="Persönlicher Zugangslink" @focus="$event.target.select()" /></v-card-text><v-card-actions><v-btn @click="copyFallback = null">Schließen</v-btn></v-card-actions></v-card>
        </v-dialog>
        <v-dialog v-model="reopenDialog" persistent max-width="560">
            <v-card><form @submit.prevent="savePersonalReopen">
                <v-card-title>Ausgewählte Tests wieder öffnen?</v-card-title>
                <v-card-text>
                    <p class="mb-2">{{ selectedReopenAttempts.length }} von {{ eligibleReopenAttempts.length }} geeigneten Personen ausgewählt.</p>
                    <label class="d-flex align-center ga-2 mb-2"><input type="checkbox" aria-label="Alle Schüler zum Wiederöffnen auswählen"
                        :checked="allReopenSelected" :indeterminate="selectedReopenAttempts.length > 0 && !allReopenSelected"
                        :disabled="!!busy || !eligibleReopenAttempts.length" @change="selectAllReopen($event.target.checked)">Alle geeigneten Schüler auswählen</label>
                    <div class="mb-3" style="max-height: 220px; overflow-y: auto;" role="group" aria-label="Schüler zum Wiederöffnen">
                        <div v-for="person in attempts" :key="person.id" class="mb-2">
                            <label class="d-flex align-center ga-2"><input type="checkbox" :aria-label="`Test zum Wiederöffnen auswählen: ${person.student_name}`"
                                :checked="selectedReopenAttempts.some(attempt => attempt.id === person.id)" :disabled="!!busy || !canSelectReopen(person)"
                                @change="selectReopen(person.id, $event.target.checked)">{{ person.student_name }}</label>
                            <p class="text-caption text-medium-emphasis">{{ reopenUnavailableReason(person) || statusLabel(person.status) }}</p>
                        </div>
                    </div>
                    <p class="mb-3">Die Zusatzzeit gilt ab dem Bestätigen ausschließlich für die markierten Personen. Die bisherigen Fragen und gespeicherten Antworten bleiben erhalten. Nicht ausgewählte Abgaben werden nicht verändert.</p>
                    <p class="mb-3">Laufende Bearbeitungen werden nicht wieder geöffnet. Nach erneuter Abgabe oder Zeitablauf wird die Bewertung neu berechnet; der bisherige Stand bleibt im Verlauf gespeichert.</p>
                    <v-alert v-if="selectedTest?.solutions_released_at" type="warning" variant="tonal" class="mb-3">Die Musterlösungen waren bereits freigegeben. Die ausgewählten Personen könnten sie schon gesehen haben.</v-alert>
                    <p v-if="selectedTest.start_mode === 'individual'" class="text-body-2 mb-3">Die persönliche Schlussfrist bleibt verbindlich. Falls nötig, verlängere sie vor dem Wiederöffnen mit dem Stift in der Schülerliste.</p>
                    <v-text-field v-model="reopenMinutes" type="number" min="0" max="1440" step="1" label="Persönliche Zusatzzeit in Minuten" :disabled="!!busy" />
                    <v-text-field v-model="reopenSeconds" type="number" min="0" max="59" step="1" label="Persönliche zusätzliche Sekunden" :disabled="!!busy" />
                    <p v-if="!validReopenTime" role="alert">Bitte eine Zusatzzeit zwischen einer Sekunde und 24 Stunden eingeben.</p>
                    <v-alert v-if="error" type="error" role="alert">{{ error }}</v-alert>
                </v-card-text>
                <v-card-actions><v-btn :disabled="!!busy" @click="reopenDialog = false">Abbrechen</v-btn><v-spacer /><v-btn type="submit" color="primary" :disabled="!!busy || !validReopenTime || !selectedReopenAttempts.length" :loading="busy === 'reopen-attempt'">Zusatzzeit freigeben</v-btn></v-card-actions>
            </form></v-card>
        </v-dialog>
        <v-dialog v-model="closeTestDialog" persistent max-width="500">
            <v-card>
                <v-card-title>Laufenden Test jetzt schließen?</v-card-title>
                <v-card-text>Alle offenen Versuche werden mit den bisher gespeicherten Antworten abgeschlossen und bewertet. Danach sind keine weiteren Antworten möglich.<v-alert v-if="error" type="error" role="alert">{{ error }}</v-alert></v-card-text>
                <v-card-actions><v-btn :disabled="!!busy" @click="closeTestDialog = false">Abbrechen</v-btn><v-spacer /><v-btn color="error" :disabled="!!busy" :loading="busy === 'close-test'" @click="finishTestEarly">Jetzt schließen</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="newCatalogDialog" persistent max-width="500" aria-labelledby="mc-new-catalog-title">
            <v-card>
                <form @submit.prevent="confirmNewCatalog">
                    <v-card-title id="mc-new-catalog-title">Neuer Katalog</v-card-title>
                    <v-card-text>
                        <p class="text-body-2 text-medium-emphasis mb-4">Gib deinem Katalog eine Bezeichnung. Die Fragen ergänzt du im nächsten Schritt.</p>
                        <v-text-field v-model="newCatalogTitle" label="Bezeichnung" autofocus maxlength="255"
                            variant="outlined" :hide-details="!newCatalogTitleError" :error-messages="newCatalogTitleError"
                            :disabled="!!busy" @update:model-value="error = ''" />
                        <v-alert v-if="error" type="error" variant="tonal" class="mt-3" role="alert">{{ error }}</v-alert>
                    </v-card-text>
                    <v-card-actions class="pa-4 pt-0">
                        <v-btn :disabled="!!busy" @click="cancelNewCatalog">Abbrechen</v-btn>
                        <v-spacer />
                        <v-btn type="submit" color="primary" variant="flat" :loading="busy === 'new-catalog'"
                            :disabled="!!busy">Katalog anlegen</v-btn>
                    </v-card-actions>
                </form>
            </v-card>
        </v-dialog>
        <v-dialog v-model="discardCatalogDialog" persistent max-width="480" aria-labelledby="mc-discard-catalog-title">
            <v-card>
                <v-card-title id="mc-discard-catalog-title">Ungespeicherte Änderungen</v-card-title>
                <v-card-text>Speichere deine Änderungen im Katalog oder verwirf sie, um zur Übersicht zurückzukehren.</v-card-text>
                <v-card-actions class="pa-4 pt-0">
                    <v-btn @click="cancelDiscardNavigation">Weiterbearbeiten</v-btn>
                    <v-spacer />
                    <v-btn color="primary" @click="leaveCatalog">Änderungen verwerfen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog :model-value="!!questionPreview" max-width="850" scrollable @update:model-value="questionPreviewId = null">
            <v-card v-if="questionPreview">
                <v-card-title>Gespeicherte Fragen · {{ questionPreview.student_name }}</v-card-title>
                <v-card-text>
                    <p class="mb-4">Schreibgeschützte Lehrervorschau · {{ questionPreview.questions?.length ?? 0 }} fest zugeordnete Fragen. Der Versuch wird weder gestartet noch verändert.</p>
                    <article v-for="(question, position) in questionPreview.questions ?? []" :key="question.id" class="mb-4 pa-4 border rounded-lg" aria-label="Gespeicherte Frage">
                        <h3 class="text-subtitle-1 font-weight-bold">{{ position + 1 }}. {{ question.prompt }}</h3>
                        <p class="text-caption mt-1">{{ question.type === 'multiple' ? 'Mehrfachauswahl' : 'Einfachauswahl' }}</p>
                        <ul class="mt-2 ml-5"><li v-for="option in question.options ?? []" :key="option.id">{{ option.text }}</li></ul>
                    </article>
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn @click="questionPreviewId = null">Schließen</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="publishDialog" max-width="560">
            <v-card>
                <v-card-title>Test erstellen?</v-card-title>
                <v-card-text>
                    <h3 class="mb-3">{{ testDraft.title }}</h3>
                    <dl class="mc-publish-summary">
                        <dt>Katalog</dt><dd>{{ selectedCatalogTitle }}</dd>
                        <dt>Teilnehmer</dt><dd>{{ rosterMatchedCount }} eindeutig zugeordnet</dd>
                        <dt>Pro Person</dt><dd>{{ testDraft.question_count }} Fragen · {{ testDraft.total_points }} Punkte</dd>
                        <dt>Bearbeitungszeit</dt><dd>{{ testDraft.duration_minutes }} Minuten</dd>
                        <dt>Start</dt><dd>{{ testDraft.start_mode === 'individual' ? 'Selbstständig, einmal pro Person' : `Countdown: ${testDraft.lead_seconds} Sekunden` }}</dd>
                        <template v-if="testDraft.start_mode === 'individual'"><dt>Schlussfrist für alle</dt><dd>{{ dateLabel(testDraft.closes_at) }}</dd></template>
                    </dl>
                    <p class="mt-4">Die zehn Fragen pro Person werden jetzt zufällig ausgewählt und gespeichert. Der Test bleibt bis zur separaten Freigabe für Schüler gesperrt. E-Mails werden nicht versandt.</p>
                </v-card-text>
                <v-card-actions>
                    <v-btn @click="publishDialog = false">Zurück</v-btn>
                    <v-spacer />
                    <v-btn color="primary" :loading="busy === 'publish'" :disabled="!!busy || !canPublish"
                        @click="publishTest">Test erstellen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="releaseTestDialog" max-width="520">
            <v-card>
                <v-card-title>Test freigeben?</v-card-title>
                <v-card-text>Die gespeicherten Fragen bleiben erhalten. {{ selectedTest?.start_mode === 'individual' ? 'Danach können die Teilnehmer selbst starten.' : 'Danach öffnet sich der Warteraum. Den Countdown startest du separat.' }}</v-card-text>
                <v-card-actions>
                    <v-btn @click="releaseTestDialog = false">Zurück</v-btn>
                    <v-spacer />
                    <v-btn color="primary" :disabled="!!busy" :loading="busy === 'publish'" @click="releaseCreatedTest">Test freigeben</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="startDialog" max-width="520">
            <v-card>
                <v-card-title>Gemeinsamen Countdown starten?</v-card-title>
                <v-card-text>Alle zugewiesenen Schüler erhalten nach {{ selectedTest?.lead_seconds }} Sekunden Zugriff
                    auf ihre Fragen. Danach laufen die gemeinsamen
                    {{ Math.round((selectedTest?.duration_seconds ?? 0) / 60) }} Minuten. Später einsteigende Schüler
                    haben nur die verbleibende Zeit.</v-card-text>
                <v-card-actions>
                    <v-btn @click="startDialog = false">Zurück</v-btn>
                    <v-spacer />
                    <v-btn color="primary" :loading="busy === 'countdown'" :disabled="!!busy"
                        @click="startCountdown">Countdown starten</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="mailDialog" max-width="520">
            <v-card>
                <v-card-title>Einladungen jetzt senden?</v-card-title>
                <v-card-text>Es werden {{ unsentInvitationCount }} echte E-Mails ausschließlich an die markierten Personen in der
                    Empfängervorschau gesendet. Jede Einladung enthält ausschließlich den persönlichen Zugang zu diesem
                    Test.</v-card-text>
                <v-card-actions>
                    <v-btn @click="mailDialog = false">Zurück</v-btn>
                    <v-spacer />
                    <v-btn color="primary" :loading="busy === 'mail'" :disabled="!!busy" @click="sendMail">E-Mails
                        senden</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog :model-value="!!revokeMode" max-width="520" persistent @update:model-value="revokeMode = null">
            <v-card>
                <v-card-title>{{ revokeMode === 'solutions' ? 'Lösungen wieder verbergen?' : 'Ergebnisse wieder verbergen?' }}</v-card-title>
                <v-card-text>{{ revokeMode === 'solutions'
                    ? 'Die richtigen Antworten und die Frageauswertung werden für Schüler wieder verborgen. Ihre Punkte bleiben sichtbar.'
                    : 'Die Punkte und die Lösungen werden für Schüler wieder verborgen.' }}
                    Bewertungen und gespeicherte Antworten bleiben erhalten. Die Freigabe ist später erneut möglich.</v-card-text>
                <v-card-actions>
                    <v-btn :disabled="!!busy" @click="revokeMode = null">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="primary" :loading="busy === 'revoke'" :disabled="!!busy" @click="revoke">Freigabe zurücknehmen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog :model-value="!!releaseMode" max-width="520" @update:model-value="releaseMode = null">
            <v-card>
                <v-card-title>{{ releaseMode === 'solutions' ? 'Lösungen freigeben?' : 'Ergebnisse freigeben?' }}</v-card-title>
                <v-card-text>{{ releaseMode === 'solutions'
                    ? 'Schüler können danach die richtigen Antworten und ihre Frageauswertung ansehen.'
                    : 'Schüler können danach ihre Punkte und Prozente sehen. Die Musterlösungen bleiben verborgen.' }}
                    Die Anzeige erfolgt jeweils erst nach der eigenen Abgabe, auch bei später abschließenden Personen.
                    Es erfolgt keine Übernahme in andere Leistungsbeurteilungen.</v-card-text>
                <v-card-actions>
                    <v-btn @click="releaseMode = null">Zurück</v-btn>
                    <v-spacer />
                    <v-btn color="primary" :loading="busy === 'release'" :disabled="!!busy"
                        @click="release">Freigeben</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup>
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch
} from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import { readModuleHandshake } from '@/helpers/moduleHandshake'
import 'filepond/dist/filepond.min.css'
import {
    index,
    createCatalog,
    updateCatalog,
    previewImport,
    rosterPreview,
    testFolderResolve,
    createTest,
    publishTest as publishCreatedTest,
    showTest,
    exportExercise,
    renameTest,
    deleteTest,
    deleteCatalog,
    closeTest,
    pauseTest,
    resumeTest,
    setExamMode,
    updateTestSettings,
    setAccessLinks,
    changeRemainingTime,
    reopenAttempts,
    changeClosingDeadline,
    countdown,
    invitations,
    sendInvitations,
    releaseResults,
    revokeRelease
} from '@/actions/App/Http/Controllers/Admin/MultipleChoiceController'
import QuestionEditor from './QuestionEditor.vue'
import MultipleChoiceTestFolder from './MultipleChoiceTestFolder.vue'
import McQuestionReview from '@/components/McQuestionReview.vue'
import {
    messageText,
    detectImportFormat,
    newQuestion,
    questionProblems,
    readyQuestions,
    requestError,
    statusLabel,
    viennaDateTime,
    viennaDateTimeIso
} from './multipleChoiceTeacher'

const FilePond = vueFilePond(FilePondPluginFileValidateType)
const route = useRoute()
const router = useRouter()
const folderDialog = ref(route.query.folder === '1')
const folderUser = computed(() => ({ id: selectedTest.value?.owner_id, school_id: selectedTest.value?.school_id }))
const workspacePath = route.path

const section = ref('catalogs')
const testPanes = [
    { key: 'control', label: 'Prüfung steuern' }, { key: 'access', label: 'Zugang und Einladungen' },
    { key: 'results', label: 'Ergebnisse und Lösungen' }, { key: 'students', label: 'Schülerübersicht' },
    { key: 'settings', label: 'Einstellungen' },
]
const testPane = computed(() => testPanes.some(pane => pane.key === route.query.pane) ? route.query.pane
    : route.query.attempt ? 'students' : route.query.view === 'results' ? 'results' : 'control')
const catalogs = ref([])
const tests = ref([])
const unassignedTests = ref([])
const selectedSchoolyear = ref(null)
const showUnassignedTests = ref(false)
const schoolyearLabel = computed(() => selectedSchoolyear.value?.name || (selectedSchoolyear.value?.from ? `${new Date(selectedSchoolyear.value.from).getFullYear()}/${new Date(selectedSchoolyear.value.until).getFullYear()}` : 'ausgewähltes Schuljahr'))
const available = ref(true)
const unavailableMessage = ref('')
const loading = ref(false)
const busy = ref('')
const error = ref('')
const notice = ref('')
const catalog = ref({
    id: null,
    title: '',
    questions: []
})
const catalogEditing = ref(false)
const editingQuestions = ref(false)
const catalogRenaming = ref(false)
const catalogTitleDraft = ref('')
const catalogTitleAttempted = ref(false)
const catalogTitleError = computed(() => {
    if (catalogTitleAttempted.value && !catalogTitleDraft.value.trim()) {
        return 'Bitte gib eine Bezeichnung ein.'
    }
    return catalogTitleDraft.value.trim().length > 255 ? 'Die Bezeichnung darf höchstens 255 Zeichen lang sein.' : ''
})
const newCatalogDialog = ref(false)
const newCatalogTitle = ref('')
const newCatalogAttempted = ref(false)
const discardCatalogDialog = ref(false)
const newCatalogTitleError = computed(() => {
    if (newCatalogAttempted.value && !newCatalogTitle.value.trim()) {
        return 'Bitte gib eine Bezeichnung ein.'
    }
    return newCatalogTitle.value.trim().length > 255 ? 'Die Bezeichnung darf höchstens 255 Zeichen lang sein.' : ''
})
const showImport = ref(false)
const templateSyncDialog = ref(false)
const templateSyncPreview = ref(null)
const templateSyncWarnings = ref([])
const questionSummaryDialog = ref(false)
const questionSummaryPreview = ref(null)
const questionSummaryStructured = computed(() => Array.isArray(questionSummaryPreview.value?.rows))
const disconnectTemplateDialog = ref(false)
const disconnectRecoveryToken = ref(null)
const templateSyncDirections = computed(() => templateSyncPreview.value ? [
    { title: 'In Schooltool übernehmen', changes: templateSyncPreview.value.catalog_changes ?? {} },
    { title: 'In Dropbox übernehmen', changes: templateSyncPreview.value.source_changes ?? {} },
] : [])
const importText = ref('')
const moduleImport = ref(null)
const moduleHandshake = ref(null)
const moduleSourceId = ref(null)
const moduleSourceProof = ref('')
const importReading = ref(false)
const importFileError = ref('')
let importSourceFile = null
let importReadSequence = 0
const importDetection = computed(() => {
    try {
        return { format: detectImportFormat(importText.value), error: '' }
    } catch (failure) {
        return { format: null, error: failure.message }
    }
})
const importError = computed(() => importFileError.value || importDetection.value.error)
const importPreview = ref(null)
const rosterCsv = ref('')
const rosterFileName = ref('')
const rosterReading = ref(false)
const rosterFileError = ref('')
let rosterSourceFile = null
let rosterReadSequence = 0
const roster = ref(null)
const testFolderDialog = ref(false)
const testFolderPondKey = ref(0)
const testFolderPreview = ref(null)
const testFolderReading = ref(false)
const testFolderError = ref('')
const testFolderWarning = ref('')
const testFolderInfo = ref(null)
const testFolderProof = ref('')
let testFolderReadSequence = 0
let testFolderAbortController = null
const startModes = [
    { value: 'common', title: 'Gemeinsam starten', description: 'Die Lehrkraft startet den Countdown.' },
    { value: 'individual', title: 'Selbstständig starten', description: 'Jede Person startet ihre eigene Bearbeitungszeit.' },
]
const defaultTestDraft = {
    title: '',
    catalog_id: null,
    question_count: 10,
    total_points: 10,
    duration_minutes: 20,
    lead_seconds: 30,
    exam_mode: false,
    start_mode: 'individual',
    closes_at: '',
    balance_types: true,
    shuffle_questions: true,
    shuffle_options: true
}
const testDraft = reactive({ ...defaultTestDraft })
const selectedTestId = ref(null)
const preparingTest = ref(false)
const selectedTest = ref(null)
const attempts = ref([])
const expandedAttempt = ref(null)
const activityExpandedAttempt = ref(null)
const tableColumnCount = computed(() => 7 + (showActivity.value ? 1 : 0))
const invitationAddressForm = ref('sie')
const invitationPreview = ref(null)
const selectedInvitationIds = ref([])
const invitationRecipientId = ref(null)
const invitationRecipient = computed(() => invitationPreview.value?.recipients?.find((recipient) => recipient.attempt_id === invitationRecipientId.value) ?? invitationPreview.value?.recipients?.[0])
const invitationRecipientHtml = computed(() => {
    const recipient = invitationRecipient.value
    if (!recipient || typeof recipient.body !== 'string') return recipient?.html ?? ''
    const escape = (value) => value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;')
    const body = recipient.body.replace(/\r\n|\r/g, '\n').trim().split(/\n\s*\n/)
        .map(paragraph => `<p>${escape(paragraph).replace(/\n/g, '<br>')}</p>`).join('')
    return recipient.html.replace(/<div data-mc-message="true">[\s\S]*?<\/div>/, () => `<div data-mc-message="true">${body}</div>`)
})
const availableInvitationRecipients = computed(() => (invitationPreview.value?.recipients ?? []).filter((recipient) => !recipient.invited_at))
const allInvitationsSelected = computed(() => availableInvitationRecipients.value.length > 0 && availableInvitationRecipients.value.every((recipient) => selectedInvitationIds.value.includes(recipient.attempt_id)))
const deletionTarget = ref(null)
const testRenameTarget = ref(null)
const testTitleDraft = ref('')
const testTitleError = ref('')
const testSettingsDialog = ref(false)
const canEditTestSettings = ref(false)
const settingsPoolCount = ref(0)
const settingsDraft = reactive({ ...defaultTestDraft })
const settingsError = ref('')
const invitationDialog = ref(false)
const showActivity = computed(() => Boolean(selectedTest.value?.exam_mode || attempts.value.some(person => person.activity)))
const closeTestDialog = ref(false)
const remainingTimeDialog = ref(false)
const canReopenAttempts = ref(false)
const reopenDialog = ref(false)
const selectedReopenIds = ref([])
const reopenSelectionEnabled = computed(() => !selectedTest.value?.paused_at && (testEnded.value || selectedTest.value?.start_mode === 'individual') && canReopenAttempts.value)
const selectedReopenAttempts = computed(() => eligibleReopenAttempts.value.filter(attempt => selectedReopenIds.value.includes(attempt.id)))
const eligibleReopenAttempts = computed(() => attempts.value.filter(canSelectReopen))
const allReopenSelected = computed(() => eligibleReopenAttempts.value.length > 0 && eligibleReopenAttempts.value.every(person => selectedReopenIds.value.includes(person.id)))
const reopenMinutes = ref(5)
const reopenSeconds = ref(0)
const reopenTimeValue = computed(() => Number(reopenMinutes.value) * 60 + Number(reopenSeconds.value))
const validReopenTime = computed(() => Number.isInteger(Number(reopenMinutes.value)) && Number(reopenMinutes.value) >= 0 && Number.isInteger(Number(reopenSeconds.value)) && Number(reopenSeconds.value) >= 0 && Number(reopenSeconds.value) < 60 && reopenTimeValue.value > 0 && reopenTimeValue.value <= 86400)
const hasPersonalRunningAttempts = computed(() => attempts.value.some(personalAttemptRunning))
const hasCompletedAttempts = computed(() => attempts.value.some(attempt => Boolean(attempt.completed_at)))
const allAttemptsCompleted = computed(() => attempts.value.length > 0 && attempts.value.every(attemptCompleted))
const deadlineTarget = ref(null)
const deadlineDraft = ref('')
const copyFallback = ref(null)
const deadlinePickerTarget = ref(null)
const deadlinePickerDate = ref(null)
const deadlinePickerTime = ref('18:00')
const deadlinePickerMinimum = computed(() => localDateTime(serverNow.value).slice(0, 10))
const deadlinePickerValue = computed(() => {
    const date = deadlinePickerDate.value
    if (!(date instanceof Date) || !Number.isFinite(date.getTime()) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(deadlinePickerTime.value)) return ''
    const day = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
    return `${day}T${deadlinePickerTime.value}`
})
const deadlinePickerValid = computed(() => Date.parse(viennaDateTimeIso(deadlinePickerValue.value)) > serverNow.value)
const validDeadline = computed(() => Date.parse(viennaDateTimeIso(deadlineDraft.value)) > serverNow.value)
const remainingMinutes = ref(0)
const remainingSeconds = ref(0)
const remainingTimeValue = computed(() => Number(remainingMinutes.value) * 60 + Number(remainingSeconds.value))
const validRemainingTime = computed(() => Number.isInteger(Number(remainingMinutes.value)) && Number(remainingMinutes.value) >= 0 && Number.isInteger(Number(remainingSeconds.value)) && Number(remainingSeconds.value) >= 0 && Number(remainingSeconds.value) < 60 && remainingTimeValue.value > 0 && remainingTimeValue.value <= 86400)
const canPauseTest = computed(() => Boolean(selectedTest.value && !selectedTest.value.paused_at && attempts.value.some(attempt => !attemptCompleted(attempt)) && (!testEnded.value || hasPersonalRunningAttempts.value) && (selectedTest.value.start_mode === 'individual' || selectedTest.value.starts_at)))
const testRunning = computed(() => Boolean(!selectedTest.value?.paused_at && selectedTest.value?.start_mode !== 'individual' && selectedTest.value?.starts_at && Date.parse(selectedTest.value.starts_at) <= serverNow.value && !testEnded.value))
const canCloseTest = computed(() => !selectedTest.value?.paused_at && (testRunning.value || hasPersonalRunningAttempts.value))
const monitorError = ref('')
const publishDialog = ref(false)
const startDialog = ref(false)
const mailDialog = ref(false)
const releaseMode = ref(null)
const releaseTestDialog = ref(false)
const questionPreviewId = ref(null)
const questionPreview = computed(() => attempts.value.find(attempt => attempt.id === questionPreviewId.value) ?? null)
const revokeMode = ref(null)
const serverOffset = ref(0)
const now = ref(Date.now())
let pollInterval = null
let tickInterval = null
let monitorLoading = null
let disposed = false
let indexLoaded = false
let pendingNavigation = null
let discardNavigationAllowed = false

const catalogReadyCount = computed(() => readyQuestions(catalog.value.questions).length)
const catalogTests = computed(() => (showUnassignedTests.value ? unassignedTests.value : tests.value).filter((item) => String(item.catalog_id) === String(catalog.value.id)))
const catalogQuestionsChanged = computed(() => {
    const saved = catalogs.value.find((item) => item.id === catalog.value.id)
    return Boolean(saved && JSON.stringify(saved.questions) !== JSON.stringify(catalog.value.questions))
})
const catalogTitleChanged = computed(() => catalogRenaming.value && catalogTitleDraft.value.trim() !== catalog.value.title)
const canPrepareCatalog = computed(() => {
    const saved = catalogs.value.find((item) => item.id === catalog.value.id)
    return Boolean(saved && catalogReadyCount.value > 0 && !catalogTitleChanged.value && saved.title === catalog.value.title
        && JSON.stringify(saved.questions) === JSON.stringify(catalog.value.questions))
})
const selectedCatalogTitle = computed(() => catalogs.value.find((item) => item.id === testDraft.catalog_id)?.title ?? '')
const nextStepHint = computed(() => {
    if (section.value === 'catalogs') {
        if (canPrepareCatalog.value) {
            return 'Dein Katalog ist bereit. Öffne „Tests zu diesem Katalog“, um einen neuen Test anzulegen oder einen vorhandenen Test zu öffnen.'
        }
        return catalog.value.questions.length
            ? 'Prüfe Fragen und Lösungen und speichere deinen Katalog. Danach bereitest du den Test vor.'
            : 'Starte mit einer Vorlage oder füge deine erste Frage hinzu. Danach prüfst und speicherst du den Katalog.'
    }
    if (section.value === 'results') {
        return 'Sieh dir die Ergebnisse dieses Tests an und gib Punkte oder Lösungen bewusst für die Schüler frei.'
    }
    if (preparingTest.value) {
        return 'Lege die Testeinstellungen fest. Beim Erstellen werden zehn Fragen pro Person gespeichert. Die Freigabe erfolgt separat.'
    }
    if (!selectedTestId.value) {
        return 'Öffne einen Test dieses Katalogs oder bereite einen neuen Test vor. Die Ergebnisse findest du im jeweiligen Test.'
    }
    if (!selectedTest.value) {
        return 'Der gewählte Test wird geladen.'
    }
    if (selectedTest.value.start_mode === 'individual') {
        return 'Die Personen starten selbst. Die Zeit läuft ab dem persönlichen Start. Pause und Wiederöffnung steuerst du unter „Prüfung steuern“, persönliche Schlussfristen mit dem Stift in der Schülerliste.'
    }
    if (hasPersonalRunningAttempts.value) {
        return 'Die gemeinsame Bearbeitungszeit ist beendet. Einzelne Personen arbeiten mit ihrer persönlichen Zusatzzeit weiter. Pause, Beenden und Wiederöffnung findest du unter „Prüfung steuern“.'
    }
    if (testEnded.value) {
        return 'Die Bearbeitungszeit ist beendet. Punkte und die Auswertung jeder Frage findest du direkt in der Schülerliste.'
    }
    return selectedTest.value.starts_at
        ? 'Der gemeinsame Start steht fest. Behalte hier die Teilnahme im Blick; alle haben dieselbe Endzeit.'
        : 'Bereite die Einladungen vor oder lass die Schüler ihren Bereich öffnen. Sobald die Klasse bereit ist, startest du gemeinsam.'
})
const selectedCatalogReadyCount = computed(() => readyQuestions(catalogs.value.find((item) => item.id === testDraft
    .catalog_id)?.questions ?? []).length)
const rosterMatchedCount = computed(() => (roster.value?.rows ?? []).filter((row) => row.status === 'matched').length)
const canPublish = computed(() => Boolean(!testFolderReading.value && (!testFolderInfo.value || testFolderProof.value) && testDraft.title.trim() && testDraft.catalog_id && Number.isInteger(Number(
        testDraft.question_count)) && testDraft.question_count === 10 && testDraft.question_count <=
    selectedCatalogReadyCount.value && Number(testDraft.total_points) > 0 && Number(testDraft
    .duration_minutes) >= 1 && Number.isInteger(Number(testDraft.lead_seconds)) && Number(testDraft
        .lead_seconds) >= 0 && (testDraft.start_mode !== 'individual' || Date.parse(viennaDateTimeIso(testDraft.closes_at)) > serverNow.value) && roster.value?.valid && rosterMatchedCount.value > 0))
const unsentInvitationCount = computed(() => availableInvitationRecipients.value.filter((recipient) => selectedInvitationIds.value.includes(recipient.attempt_id)).length)
const serverNow = computed(() => selectedTest.value?.paused_at ? Date.parse(selectedTest.value.paused_at) : now.value + serverOffset.value)
const testEnded = computed(() => selectedTest.value?.start_mode === 'individual'
    ? attempts.value.length > 0 && attempts.value.every(person => person.completed_at || (person.effective_ends_at && Date.parse(person.effective_ends_at) <= serverNow.value))
    : Boolean(selectedTest.value?.ends_at && Date.parse(selectedTest.value.ends_at) <= serverNow.value))
const testPhase = computed(() => {
    const test = selectedTest.value
    if (test?.status === 'draft') return { label: 'Erstellt · noch nicht freigegeben', color: 'info' }
    if (test?.paused_at) return { label: 'Test pausiert', color: 'warning' }
    if (test?.start_mode === 'individual') {
        return { label: testEnded.value ? 'Bearbeitungszeit beendet' : 'Selbstständiger Start möglich', color: testEnded.value ? 'secondary' : 'success' }
    }
    if (!test?.starts_at) {
        return {
            label: 'Warteraum geöffnet',
            color: 'info'
        }
    }
    if (hasPersonalRunningAttempts.value) {
        return { label: 'Persönliche Zusatzzeit läuft', color: 'success' }
    }
    if (testEnded.value) {
        return {
            label: 'Bearbeitungszeit beendet',
            color: 'secondary'
        }
    }
    const secondsUntilStart = Math.ceil((Date.parse(test.starts_at) - serverNow.value) / 1000)
    if (secondsUntilStart > 0) {
        return {
            label: `Start in ${secondsUntilStart} Sekunden`,
            color: 'warning'
        }
    }
    const seconds = Math.max(0, Math.ceil((Date.parse(test.ends_at) - serverNow.value) / 1000))
    return {
        label: `Noch ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`,
        color: 'success'
    }
})

onMounted(() => {
    loadIndex()
    pollInterval = window.setInterval(() => {
        if (section.value !== 'catalogs' && selectedTestId.value && !busy.value) {
            refreshMonitor()
        }
    }, 3000)
    tickInterval = window.setInterval(() => {
        now.value = Date.now()
    }, 500)
})
watch(() => route.fullPath, () => {
    testFolderReadSequence++
    testFolderAbortController?.abort()
    testFolderReading.value = false
    testFolderDialog.value = false
    testFolderPreview.value = null
    if (indexLoaded && route.path === workspacePath) {
        restoreLocation()
    }
})
onBeforeRouteUpdate(guardCatalogNavigation)
onBeforeRouteLeave(guardCatalogNavigation)
onBeforeUnmount(() => {
    importReadSequence++
    testFolderReadSequence++
    testFolderAbortController?.abort()
    disposed = true
    window.clearInterval(pollInterval)
    window.clearInterval(tickInterval)
})

async function task(name, callback) {
    if (busy.value) {
        return
    }
    busy.value = name
    error.value = ''
    notice.value = ''
    try {
        await callback()
    } catch (failure) {
        error.value = requestError(failure)
    } finally {
        busy.value = ''
    }
}

async function loadIndex() {
    if (loading.value) {
        return
    }
    loading.value = true
    error.value = ''
    try {
        const {
            data
        } = await axios.get(index.url())
        available.value = data.available !== false
        unavailableMessage.value = data.message ?? ''
        catalogs.value = data.catalogs ?? []
        tests.value = data.tests ?? []
        unassignedTests.value = data.unassigned_tests ?? []
        selectedSchoolyear.value = data.schoolyear ?? null
        if (!unassignedTests.value.some(test => test.catalog_id === catalog.value.id)) showUnassignedTests.value = false
        indexLoaded = true
        if (available.value) {
            await restoreLocation()
        }
    } catch (failure) {
        error.value = requestError(failure)
    } finally {
        loading.value = false
    }
}

function newCatalog() {
    newCatalogTitle.value = ''
    newCatalogAttempted.value = false
    newCatalogDialog.value = true
    error.value = ''
    notice.value = ''
}

function cancelNewCatalog() {
    if (!busy.value) {
        newCatalogDialog.value = false
        error.value = ''
    }
}

function rememberCatalog(saved) {
    catalogs.value = [...catalogs.value.filter((item) => item.id !== saved.id), saved]
        .sort((first, second) => first.title.localeCompare(second.title, 'de'))
}

async function confirmNewCatalog() {
    newCatalogAttempted.value = true
    if (newCatalogTitleError.value) {
        return
    }
    await task('new-catalog', async () => {
        const { data } = await axios.post(createCatalog.url(), { title: newCatalogTitle.value.trim(), questions: [] })
        rememberCatalog(data.catalog)
        openCatalog(data.catalog.id)
        newCatalogDialog.value = false
    })
}

function queryId(value) {
    return typeof value === 'string' && /^[1-9]\d*$/.test(value) && Number.isSafeInteger(Number(value))
        ? Number(value) : null
}

function navigationLocation(view, catalogId = catalog.value.id, testId = selectedTestId.value, attemptId = null) {
    const query = { ...route.query }
    for (const key of ['catalog', 'view', 'test', 'attempt', 'pane']) {
        delete query[key]
    }
    if (view !== 'catalogs') {
        query.catalog = String(catalogId)
        if (view !== 'catalog') {
            query.view = view
        }
        if (['test', 'results'].includes(view)) {
            query.test = String(testId)
        }
        if (['test', 'results'].includes(view) && attemptId) {
            query.attempt = String(attemptId)
            query.pane = 'students'
        }
    }
    return { path: workspacePath, query }
}

function navigateTo(view, catalogId = catalog.value.id, testId = selectedTestId.value, attemptId = null) {
    error.value = ''
    notice.value = ''
    templateSyncWarnings.value = []
    return router.push(navigationLocation(view, catalogId, testId, attemptId))
}

function selectTestPane(pane) {
    const location = navigationLocation(pane === 'results' ? 'results' : 'test')
    location.query.pane = pane
    return router.push(location)
}

async function unavailableLocation(view, catalogId, message) {
    await router.replace(navigationLocation(view, catalogId))
    error.value = message
}

async function restoreLocation() {
    const location = route.fullPath
    const catalogId = queryId(route.query.catalog)
    if (!catalogId) {
        clearSelectedTest()
        catalogEditing.value = false
        editingQuestions.value = false
        preparingTest.value = false
        section.value = 'catalogs'
        cancelCatalogRename()
        if (['catalog', 'view', 'test', 'attempt'].some((key) => route.query[key] !== undefined)) {
            await unavailableLocation('catalogs', null, 'Der Fragenkatalog wurde nicht gefunden oder ist nicht zugänglich.')
        }
        return
    }
    const saved = catalogs.value.find((item) => item.id === catalogId)
    if (!saved) {
        await unavailableLocation('catalogs', null, 'Der Fragenkatalog wurde nicht gefunden oder ist nicht zugänglich.')
        return
    }
    if (!catalogEditing.value || catalog.value.id !== saved.id) {
        loadCatalog(saved.id)
    }
    const view = route.query.view === 'tests' && queryId(route.query.test) ? 'test' : route.query.view ?? 'catalog'
    if (!['catalog', 'import', 'tests', 'new-test', 'test', 'results'].includes(view)) {
        await unavailableLocation('catalog', catalogId, 'Diese Ansicht ist nicht verfügbar.')
        return
    }
    showImport.value = view === 'import'
    preparingTest.value = view === 'new-test'
    section.value = ['catalog', 'import'].includes(view) ? 'catalogs' : 'tests'
    if (view === 'new-test') {
        if (!canPrepareCatalog.value) {
            await unavailableLocation('tests', catalogId, 'Prüfe und speichere zuerst die Fragen im Katalog, bevor du einen neuen Test anlegst.')
            return
        }
        initializeTestDraft()
    }
    if (['test', 'results'].includes(view)) {
        const testId = queryId(route.query.test)
        showUnassignedTests.value = unassignedTests.value.some(test => test.id === testId)
        if (!catalogTests.value.some((item) => item.id === testId)) {
            clearSelectedTest()
            await unavailableLocation('tests', catalogId, 'Der Test wurde nicht gefunden oder gehört nicht zu diesem Katalog.')
            return
        }
        if (selectedTestId.value !== testId) {
            await loadSelectedTest(testId)
        }
        if (route.fullPath === location) {
            const attemptId = queryId(route.query.attempt)
            expandedAttempt.value = ['test', 'results'].includes(view) && attempts.value.some((item) => item.id === attemptId && isScored(item)) ? attemptId : null
        }
    } else {
        clearSelectedTest()
    }
}

function guardCatalogNavigation(to) {
    if (discardNavigationAllowed) {
        discardNavigationAllowed = false
        return true
    }
    const staysInCatalog = to.path === workspacePath && queryId(to.query.catalog) === catalog.value.id
    if (catalogEditing.value && !staysInCatalog && (catalogTitleChanged.value || catalogQuestionsChanged.value)) {
        pendingNavigation = to.fullPath
        discardCatalogDialog.value = true
        return false
    }
    return true
}

function cancelDiscardNavigation() {
    discardCatalogDialog.value = false
    pendingNavigation = null
}

function openCatalog(id) {
    return navigateTo('catalog', id)
}

function loadCatalog(id) {
    const selected = catalogs.value.find((item) => item.id === id)
    if (selected) {
        section.value = 'catalogs'
        catalog.value = JSON.parse(JSON.stringify(selected))
        editingQuestions.value = false
        cancelCatalogRename()
        catalogEditing.value = true
        showImport.value = false
        importPreview.value = null
        templateSyncDialog.value = false
        templateSyncPreview.value = null
        templateSyncWarnings.value = []
        questionSummaryDialog.value = false
        questionSummaryPreview.value = null
        disconnectTemplateDialog.value = false
        disconnectRecoveryToken.value = null
        resetImportSource()
        error.value = ''
        notice.value = ''
    }
}

function backToCatalogs() {
    return navigateTo('catalogs')
}

function leaveCatalog() {
    discardCatalogDialog.value = false
    discardNavigationAllowed = true
    const target = pendingNavigation ?? navigationLocation('catalogs')
    pendingNavigation = null
    return router.push(target)
}

function clearSelectedTest() {
    questionPreviewId.value = null
    selectedTestId.value = null
    selectedTest.value = null
    canEditTestSettings.value = false
    testSettingsDialog.value = false
    invitationDialog.value = false
    canReopenAttempts.value = false
    reopenDialog.value = false
    selectedReopenIds.value = []
    attempts.value = []
    invitationPreview.value = null
    expandedAttempt.value = null
    monitorError.value = ''
    startDialog.value = false
    mailDialog.value = false
    releaseMode.value = null
    revokeMode.value = null
}

function showCatalogTests() {
    return navigateTo('tests')
}

function prepareCatalogTest() {
    if (!canPrepareCatalog.value) {
        return
    }
    showUnassignedTests.value = false
    return navigateTo('new-test')
}

function initializeTestDraft() {
    const previousCatalogId = testDraft.catalog_id
    testDraft.catalog_id = catalog.value.id
    testDraft.question_count = 10
    if (previousCatalogId !== catalog.value.id || !testDraft.title.trim()) {
        testDraft.title = `${catalog.value.title} – Test`
    }
}

function cancelNewTest() {
    if (busy.value) {
        return
    }
    Object.assign(testDraft, defaultTestDraft)
    testFolderReadSequence++
    testFolderAbortController?.abort()
    testFolderDialog.value = false
    testFolderPreview.value = null
    testFolderReading.value = false
    testFolderError.value = ''
    testFolderWarning.value = ''
    testFolderInfo.value = null
    testFolderProof.value = ''
    editRosterCsv()
    rosterCsv.value = ''
    publishDialog.value = false
    error.value = ''
    notice.value = ''
    return router.replace(navigationLocation('tests'))
}

function toggleAttempt(id) {
    const location = navigationLocation('test', catalog.value.id, selectedTestId.value, expandedAttempt.value === id ? null : id)
    location.query.pane = 'students'
    return router.push(location)
}

function startCatalogRename() {
    catalogTitleDraft.value = catalog.value.title
    catalogTitleAttempted.value = false
    catalogRenaming.value = true
    error.value = ''
    notice.value = ''
}

function cancelQuestionEditing() {
    const saved = catalogs.value.find((item) => item.id === catalog.value.id)
    if (saved) {
        catalog.value = JSON.parse(JSON.stringify(saved))
    }
    editingQuestions.value = false
    cancelCatalogRename()
    resetImportSource()
    importPreview.value = null
    return navigateTo('catalog')
}

function cancelCatalogRename() {
    catalogRenaming.value = false
    catalogTitleDraft.value = ''
    catalogTitleAttempted.value = false
}

async function saveCatalogTitle() {
    catalogTitleAttempted.value = true
    if (catalogTitleError.value) {
        return
    }
    if (!catalogTitleChanged.value) {
        cancelCatalogRename()
        return
    }
    const saved = catalogs.value.find((item) => item.id === catalog.value.id)
    await task('catalog-title', async () => {
        const { data } = await axios.patch(updateCatalog.url(saved.id), {
            title: catalogTitleDraft.value.trim(),
            questions: saved.questions,
            ...(saved.template_version != null ? { expected_version: saved.template_version } : {}),
        })
        rememberCatalog(data.catalog)
        catalog.value.title = data.catalog.title
        cancelCatalogRename()
        notice.value = 'Bezeichnung gespeichert.'
    })
}

async function persistCatalog(questions = catalog.value.questions, sourceProofs = []) {
    const savedCatalog = catalogs.value.find((item) => item.id === catalog.value.id)
    const savedQuestions = savedCatalog?.questions ?? []
    const payload = {
        title: catalog.value.title,
        ...(savedCatalog?.template_version != null ? { expected_version: savedCatalog.template_version } : {}),
        questions: questions.map((question) => {
            const saved = savedQuestions.find((item) => item.id === question.id)
            const changed = !saved || JSON.stringify(saved) !== JSON.stringify(question)
            return {
                ...question,
                reviewed: changed ? Boolean(question.active && !questionProblems(question).length) : question.reviewed,
            }
        })
    }
    if (sourceProofs.length) payload.module_source_proofs = [...sourceProofs]
    const {
        data
    } = catalog.value.id ? await axios.patch(updateCatalog.url(catalog.value.id), payload) :
        await axios.post(createCatalog.url(), payload)
    if (!data.catalog || !Array.isArray(data.catalog.questions) || data.catalog.questions.length !== payload.questions.length
        || (catalog.value.id && String(data.catalog.id) !== String(catalog.value.id))) {
        throw new Error('Das Speichern des vollständigen Katalogs wurde nicht bestätigt. Vorschau und bisheriger Inhalt bleiben erhalten.')
    }
    if (data.catalog) {
        catalog.value = JSON.parse(JSON.stringify(data.catalog))
        rememberCatalog(data.catalog)
        editingQuestions.value = false
    }
    notice.value = 'Fragen gespeichert. Vollständige bearbeitete Fragen sind für neue Tests bestätigt.'
}

async function saveCatalog() {
    await task('catalog', () => persistCatalog())
}

function catalogTemplateUrl(action) {
    return `${index.url().replace(/\/$/, '')}/catalogs/${encodeURIComponent(String(catalog.value.id))}/${action}`
}

function syncChangeCount(changes) {
    return ['added', 'modified', 'removed'].reduce((total, key) => total + (changes?.[key]?.length ?? 0), 0)
}

function syncQuestionType(question) {
    return question?.type === 'multiple' ? 'Mehrfachauswahl' : 'Einfachauswahl'
}

async function previewTemplateSync() {
    if (!catalog.value.id || !catalog.value.template_connection || catalog.value.template_connection.status === 'ambiguous') return
    const catalogId = catalog.value.id
    templateSyncWarnings.value = []
    const expectedVersion = catalogs.value.find((item) => item.id === catalogId)?.template_version
    if (!expectedVersion) {
        error.value = 'Der Katalogstand ist nicht verfügbar. Bitte die Seite aktualisieren und erneut vergleichen.'
        return
    }
    templateSyncPreview.value = null
    templateSyncDialog.value = false
    await task('sync-preview', async () => {
        const { data } = await axios.post(catalogTemplateUrl('sync-preview'), {
            expected_version: expectedVersion,
            title: catalog.value.title,
            questions: catalog.value.questions,
        })
        if (String(catalog.value.id) !== String(catalogId)) return
        templateSyncPreview.value = data
        templateSyncDialog.value = true
    })
}

function closeTemplateSync() {
    if (busy.value) return
    templateSyncDialog.value = false
    templateSyncPreview.value = null
}

async function applyTemplateSync() {
    const token = templateSyncPreview.value?.token
    if (!templateSyncPreview.value?.can_apply || !token || !catalog.value.id) return
    await task('sync-apply', async () => {
        const { data } = await axios.post(catalogTemplateUrl('sync-apply'), { token })
        if (!data.catalog || !Array.isArray(data.catalog.questions)) {
            throw new Error('Der Abgleich wurde nicht bestätigt. Bitte den Katalog neu laden und den Stand prüfen.')
        }
        catalog.value = JSON.parse(JSON.stringify(data.catalog))
        rememberCatalog(data.catalog)
        editingQuestions.value = false
        templateSyncDialog.value = false
        templateSyncPreview.value = null
        templateSyncWarnings.value = data.warnings ?? []
        notice.value = data.message || 'Vorlage und Fragenkatalog wurden synchronisiert.'
    })
}

async function previewQuestionSummary() {
    if (!catalog.value.id || catalog.value.template_connection?.status !== 'connected' || editingQuestions.value) return
    const catalogId = catalog.value.id
    questionSummaryPreview.value = null
    questionSummaryDialog.value = false
    await task('summary-preview', async () => {
        const { data } = await axios.post(catalogTemplateUrl('question-summary-preview'))
        if (String(catalog.value.id) !== String(catalogId)) return
        questionSummaryPreview.value = data
        questionSummaryDialog.value = true
    })
}

function closeQuestionSummary() {
    if (busy.value) return
    questionSummaryDialog.value = false
    questionSummaryPreview.value = null
}

async function applyQuestionSummary() {
    const token = questionSummaryPreview.value?.token
    if (!questionSummaryPreview.value?.can_apply || !token || !catalog.value.id) return
    await task('summary-apply', async () => {
        const { data } = await axios.post(catalogTemplateUrl('question-summary-apply'), { token })
        if (!data.path || !data.revision) {
            throw new Error('Das Speichern wurde nicht eindeutig bestätigt. Bitte die Dropbox-Datei prüfen und die Vorschau neu öffnen.')
        }
        questionSummaryDialog.value = false
        questionSummaryPreview.value = null
        notice.value = data.message || 'Die Question Summary wurde in Dropbox gespeichert.'
    })
}

function openRecoveryDisconnect() {
    disconnectRecoveryToken.value = templateSyncPreview.value?.recovery_token ?? null
    if (!disconnectRecoveryToken.value) return
    templateSyncDialog.value = false
    disconnectTemplateDialog.value = true
}

function cancelTemplateDisconnect() {
    disconnectTemplateDialog.value = false
    disconnectRecoveryToken.value = null
}

async function disconnectTemplate() {
    if (!catalog.value.id || !catalog.value.template_connection) return
    await task('sync-disconnect', async () => {
        const recoveryToken = disconnectRecoveryToken.value
        const { data } = recoveryToken
            ? await axios.delete(catalogTemplateUrl('template-connection'), { data: { recovery_token: recoveryToken } })
            : await axios.delete(catalogTemplateUrl('template-connection'))
        if (!data.catalog || !Array.isArray(data.catalog.questions)) {
            throw new Error('Das Auflösen der Verbindung wurde nicht bestätigt. Bitte den Katalog neu laden und den Stand prüfen.')
        }
        catalog.value = JSON.parse(JSON.stringify(data.catalog))
        rememberCatalog(data.catalog)
        editingQuestions.value = false
        disconnectTemplateDialog.value = false
        disconnectRecoveryToken.value = null
        notice.value = 'Die Vorlagenverbindung wurde aufgelöst. Fragen und Quelldatei sind erhalten.'
    })
}

function resetImportSource() {
    importReadSequence++
    importSourceFile = null
    importText.value = ''
    importFileError.value = ''
    importReading.value = false
    moduleImport.value = null
    moduleHandshake.value = null
    moduleSourceId.value = null
    moduleSourceProof.value = ''
}

function templateFileType(file, type) {
    const extension = file.name.split('.').pop()?.toLowerCase()
    return Promise.resolve({ md: 'text/markdown', txt: 'text/plain', json: 'application/json' }[extension] || type)
}

async function readModuleFiles(items) {
    const file = items[0]?.file || null
    if (file === importSourceFile) return
    resetImportSource()
    importPreview.value = null
    if (items.length !== 1) return
    importSourceFile = file
    const sequence = importReadSequence
    importReading.value = true
    try {
        const selected = await readModuleHandshake(items[0].file)
        if (sequence !== importReadSequence) return
        moduleHandshake.value = selected
        await loadModuleSource()
    } catch (failure) {
        if (sequence === importReadSequence) importFileError.value = failure.message || 'Die Handshake-Datei konnte nicht gelesen werden.'
    } finally {
        if (sequence === importReadSequence) importReading.value = false
    }
}

async function loadModuleSource(sourceId = null) {
    if (!moduleHandshake.value) return
    const sequence = ++importReadSequence
    const catalogId = catalog.value.id
    importReading.value = true
    importFileError.value = ''
    importPreview.value = null
    moduleSourceProof.value = ''
    try {
        const { data } = await axios.post(previewImport.url(), { filename: moduleHandshake.value.filename,
            handshake: moduleHandshake.value.contents, catalog_id: catalogId || null, source_id: sourceId, generate: false })
        if (sequence !== importReadSequence || catalog.value.id !== catalogId || !showImport.value) return
        moduleImport.value = data
        moduleSourceId.value = data.module_source?.source_file_id || null
        importText.value = data.text || ''
        moduleSourceProof.value = data.source_proof || ''
        if (data.preview) importPreview.value = { ...data.preview, questions: (data.preview.questions || []).map(question => ({ ...question, reviewed: false })) }
    } catch (failure) {
        if (sequence === importReadSequence) importFileError.value = requestError(failure)
    } finally {
        if (sequence === importReadSequence) importReading.value = false
    }
}

function cancelImport() {
    if (busy.value) {
        return
    }
    resetImportSource()
    importPreview.value = null
    showImport.value = false
    error.value = ''
    navigateTo('catalog')
}

async function readSourceFiles(items) {
    const file = items[0]?.file ?? null
    if (/^handshake\.json$/i.test(file?.name || '')) return readModuleFiles(items)
    if (file === importSourceFile) {
        return
    }
    resetImportSource()
    importPreview.value = null
    importSourceFile = file
    if (!file) {
        return
    }
    const sequence = importReadSequence
    importReading.value = true
    try {
        let buffer
        try {
            buffer = await file.arrayBuffer()
        } catch {
            throw new Error('Die Datei konnte nicht gelesen werden. Bitte wähle sie erneut aus.')
        }
        let text
        try {
            text = new TextDecoder('utf-8', { fatal: true }).decode(buffer)
        } catch {
            throw new Error('Bitte speichere die Vorlage als UTF-8 und wähle sie erneut aus.')
        }
        detectImportFormat(text, file)
        if (sequence === importReadSequence) {
            importText.value = text
        }
    } catch (failure) {
        if (sequence === importReadSequence) {
            importFileError.value = failure.message || 'Die Datei konnte nicht gelesen werden. Bitte erneut auswählen.'
        }
    } finally {
        if (sequence === importReadSequence) {
            importReading.value = false
        }
    }
}

async function previewSource() {
    if (importReading.value || importError.value || !importDetection.value.format) {
        return
    }
    await task('import', async () => {
        const text = importText.value
        const format = importDetection.value.format
        const {
            data
        } = await axios.post(previewImport.url(), {
            text,
            format,
            generate: false
        })
        if (showImport.value && text === importText.value && format === importDetection.value.format) {
            importPreview.value = {
                ...data,
                questions: (data.questions ?? []).map((question) => ({
                    ...question,
                    reviewed: false
                }))
            }
        }
    })
}

async function adoptImport() {
    if (!importPreview.value?.questions?.length) {
        return
    }
    await task('import-save', async () => {
        const { data } = await axios.post(previewImport.url(), {
            text: JSON.stringify({ questions: importPreview.value.questions }),
            format: 'json',
            generate: false,
        })
        importPreview.value = data
        if (!data.valid) {
            return
        }
        const ids = new Set(catalog.value.questions.map((question) => question.id))
        const imported = JSON.parse(JSON.stringify(data.questions)).map((question) => {
            if (ids.has(question.id)) {
                question.id = crypto.randomUUID()
            }
            ids.add(question.id)
            return { ...question, active: true, reviewed: true }
        })
        const combinedQuestions = [...catalog.value.questions, ...imported]
        await persistCatalog(combinedQuestions, moduleSourceProof.value ? [moduleSourceProof.value] : [])
        resetImportSource()
        importPreview.value = null
        showImport.value = false
        await navigateTo('catalog')
        notice.value = 'Fragen importiert und gespeichert.'
    })
}


function chooseTestFolder() {
    if (!catalog.value.id || !preparingTest.value) return
    testFolderDialog.value = true
    testFolderPreview.value = null
    testFolderError.value = ''
    testFolderPondKey.value++
}

function acceptTestFolderFile(item) {
    const file = item.file
    const isHandshake = file?.name?.toLowerCase() === 'handshake.json'
    const isMarkdown = /\.md$/i.test(file?.name || '')
    if ((!isHandshake && !isMarkdown) || file.size > (isHandshake ? 4096 : 1024 * 1024)) {
        testFolderPreview.value = null
        testFolderError.value = 'Bitte eine handshake.json (höchstens 4 KB) oder eine Markdown-Datei (höchstens 1 MB) auswählen.'
        return false
    }
    return true
}

function detectTestFolderFileType(file, type) {
    if (file.name?.toLowerCase() === 'handshake.json') return Promise.resolve('application/json')
    return Promise.resolve(/\.md$/i.test(file.name || '') ? 'text/markdown' : type)
}

async function readTestFolderHandshake(file) {
    let contents
    try {
        contents = typeof file.arrayBuffer === 'function'
            ? new TextDecoder('utf-8', { fatal: true }).decode(await file.arrayBuffer()) : await file.text()
        if (new TextEncoder().encode(contents).length > 4096) throw new Error()
        const data = JSON.parse(contents.replace(/^\uFEFF/, ''))
        const keys = [...contents.matchAll(/"(?:[^"\\]|\\.)*"\s*:/g)]
            .map(match => JSON.parse(match[0].slice(0, match[0].lastIndexOf(':')).trim()))
        if (!data || Array.isArray(data) || Object.keys(data).sort().join(',') !== 'exercise_folder_id,kind,schema_version'
            || keys.length !== 3 || new Set(keys).size !== 3 || data.schema_version !== 1
            || data.kind !== 'schooltool.exercise_folder' || typeof data.exercise_folder_id !== 'string'
            || !/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(data.exercise_folder_id)) throw new Error()
    } catch {
        throw new Error('handshake.json muss als UTF-8 den gültigen Testordner-Vertrag enthalten.')
    }
    return contents
}

async function selectTestFolderFiles(items) {
    testFolderReadSequence++
    testFolderAbortController?.abort()
    testFolderPreview.value = null
    testFolderError.value = ''
    testFolderReading.value = false
    if (items.length !== 1 || !testFolderDialog.value || !preparingTest.value) return
    if (!acceptTestFolderFile(items[0])) return
    const sequence = testFolderReadSequence
    testFolderAbortController = new AbortController()
    testFolderReading.value = true
    try {
        const file = items[0].file
        let payload
        if (/\.md$/i.test(file.name)) {
            const contents = await file.arrayBuffer()
            const blockHash = contents.byteLength ? await crypto.subtle.digest('SHA-256', contents) : new ArrayBuffer(0)
            const fileHash = await crypto.subtle.digest('SHA-256', blockHash)
            const contentHash = Array.from(new Uint8Array(fileHash), byte => byte.toString(16).padStart(2, '0')).join('')
            payload = { filename: file.name, content_hash: contentHash }
        } else {
            payload = { filename: file.name, handshake: await readTestFolderHandshake(file) }
        }
        if (sequence !== testFolderReadSequence || disposed || !testFolderDialog.value) return
        const { data } = await axios.post(testFolderResolve.url(), payload, { signal: testFolderAbortController.signal, timeout: 120000 })
        if (sequence !== testFolderReadSequence || disposed || !testFolderDialog.value || !preparingTest.value) return
        if (!data.folder_proof || !data.path || !data.directory || !Array.isArray(data.rows)) {
            throw new Error('Der Dropbox-Testordner konnte nicht vollständig bestätigt werden.')
        }
        testFolderPreview.value = data
    } catch (failure) {
        if (sequence === testFolderReadSequence) testFolderError.value = failure.response ? requestError(failure) : failure.message || requestError(failure)
    } finally {
        if (sequence === testFolderReadSequence) testFolderReading.value = false
    }
}

function closeTestFolderDialog() {
    if (testFolderReading.value) return
    testFolderReadSequence++
    testFolderAbortController?.abort()
    testFolderDialog.value = false
    testFolderPreview.value = null
    testFolderError.value = ''
}

function useTestFolder() {
    const data = testFolderPreview.value
    if (!data?.valid || !data?.folder_proof || testFolderReading.value || !preparingTest.value) return
    editRosterCsv()
    testDraft.title = data.directory
    rosterCsv.value = data.csv
    rosterFileName.value = 'Schülerdaten aus Dropbox'
    roster.value = data
    testFolderInfo.value = {
        directory: data.directory, path: data.path, student_count: data.rows.length, ...data.source,
    }
    testFolderProof.value = data.folder_proof
    testFolderWarning.value = ''
    testFolderDialog.value = false
    testFolderPreview.value = null
}

function editRosterCsv() {
    testFolderReadSequence++
    testFolderAbortController?.abort()
    testFolderReading.value = false
    if (testFolderProof.value) {
        testFolderWarning.value = 'Die Schülerliste wurde geändert. Die vorgemerkte Dropbox-Verknüpfung wurde verworfen. Wähle die Datei erneut, wenn dieser Test verknüpft werden soll.'
    }
    testFolderProof.value = ''
    testFolderInfo.value = null
    rosterReadSequence++
    rosterSourceFile = null
    rosterReading.value = false
    rosterFileError.value = ''
    rosterFileName.value = ''
    roster.value = null
}

function disconnectDraftFolder() {
    editRosterCsv()
    rosterCsv.value = ''
    testFolderWarning.value = ''
}

async function readRosterFiles(items) {
    const file = items[0]?.file ?? null
    if (file === rosterSourceFile) {
        return
    }
    editRosterCsv()
    rosterCsv.value = ''
    rosterSourceFile = file
    if (!file) {
        return
    }
    if (!/\.csv$/i.test(file.name) && file.type !== 'text/csv') {
        rosterFileError.value = 'Bitte wähle eine CSV-Datei (.csv) aus.'
        return
    }
    const sequence = rosterReadSequence
    rosterReading.value = true
    try {
        const csv = await file.text()
        if (sequence === rosterReadSequence) {
            rosterCsv.value = csv
            rosterFileName.value = file.name
        }
    } catch {
        if (sequence === rosterReadSequence) {
            rosterFileError.value = 'Die CSV-Datei konnte nicht gelesen werden. Bitte erneut auswählen.'
        }
    } finally {
        if (sequence === rosterReadSequence) {
            rosterReading.value = false
        }
    }
}

async function previewRoster() {
    if (rosterReading.value || rosterFileError.value || !rosterCsv.value.trim()) {
        return
    }
    await task('roster', async () => {
        const csv = rosterCsv.value
        const {
            data
        } = await axios.post(rosterPreview.url(), {
            csv
        })
        if (csv === rosterCsv.value) {
            roster.value = data
        }
    })
}

async function publishTest() {
    if (!canPublish.value) {
        return
    }
    await task('publish', async () => {
        const {
            duration_minutes: durationMinutes,
            ...settings
        } = testDraft
        const {
            data
        } = await axios.post(createTest.url(), {
            ...settings,
            closes_at: settings.start_mode === 'individual' ? viennaDateTimeIso(settings.closes_at) : null,
            duration_seconds: Math.round(Number(durationMinutes) * 60),
            roster_csv: rosterCsv.value,
            ...(testFolderProof.value ? { test_folder_proof: testFolderProof.value } : {})
        })
        publishDialog.value = false
        await loadIndex()
        if (data.test) {
            await selectTest(data.test.id)
        }
        notice.value = 'Test erstellt. Die Fragen sind pro Person gespeichert. Der Test ist noch nicht freigegeben.'
    })
}

async function releaseCreatedTest() {
    await task('publish', async () => {
        await axios.post(publishCreatedTest.url(selectedTestId.value))
        releaseTestDialog.value = false
        await refreshMonitor()
        await loadIndex()
        notice.value = 'Test freigegeben. Die gespeicherten Fragen bleiben erhalten.'
    })
}

async function selectTest(id) {
    if (!catalogTests.value.some((item) => item.id === id)) {
        return
    }
    return navigateTo('test', catalog.value.id, id)
}

async function loadSelectedTest(id) {
    preparingTest.value = false
    selectedTestId.value = id
    selectedTest.value = null
    canEditTestSettings.value = false
    testSettingsDialog.value = false
    invitationDialog.value = false
    canReopenAttempts.value = false
    reopenDialog.value = false
    selectedReopenIds.value = []
    monitorError.value = ''
    attempts.value = []
    invitationPreview.value = null
    expandedAttempt.value = null
    startDialog.value = false
    mailDialog.value = false
    releaseMode.value = null
    revokeMode.value = null
    await refreshMonitor()
}

async function refreshMonitor() {
    if (!selectedTestId.value || monitorLoading === selectedTestId.value) {
        return
    }
    const id = selectedTestId.value
    monitorLoading = id
    try {
        const {
            data
        } = await axios.get(showTest.url(id))
        if (disposed || selectedTestId.value !== id) {
            return
        }
        selectedTest.value = data.test
        attempts.value = data.attempts ?? []
        selectedReopenIds.value = selectedReopenIds.value.filter(id => attempts.value.some(attempt => attempt.id === id))
        canReopenAttempts.value = Boolean(data.can_reopen_attempts)
        canEditTestSettings.value = Boolean(data.can_edit_settings)
        settingsPoolCount.value = data.question_pool_count ?? data.test.question_count
        const attemptId = queryId(route.query.attempt)
        expandedAttempt.value = ['test', 'results'].includes(route.query.view) && attempts.value.some((item) => item.id === attemptId && isScored(item)) ? attemptId : null
        if (data.server_now) {
            serverOffset.value = Date.parse(data.server_now) - Date.now()
        }
        monitorError.value = ''
    } catch (failure) {
        if (selectedTestId.value === id) {
            monitorError.value = requestError(failure)
        }
    } finally {
        if (monitorLoading === id) {
            monitorLoading = null
        }
    }
}

async function startCountdown() {
    await task('countdown', async () => {
        await axios.post(countdown.url(selectedTestId.value))
        startDialog.value = false
        await refreshMonitor()
        notice.value = 'Countdown gestartet. Die gemeinsame Endzeit steht fest.'
    })
}

function resetInvitationPreview() {
    invitationPreview.value = null
    selectedInvitationIds.value = []
    invitationRecipientId.value = null
    mailDialog.value = false
}

async function previewInvitations() {
    await task('invitations', async () => {
        const {
            data
        } = await axios.get(invitations.url(selectedTestId.value), { params: { address_form: invitationAddressForm.value } })
        invitationPreview.value = data
        selectedInvitationIds.value = []
        invitationRecipientId.value = null
    })
}

function editedInvitationMessages() {
    const messages = (invitationPreview.value?.recipients ?? [])
        .filter(recipient => selectedInvitationIds.value.includes(recipient.attempt_id) && typeof recipient.subject === 'string' && typeof recipient.body === 'string')
        .map(recipient => ({ attempt_id: recipient.attempt_id, subject: recipient.subject, body: recipient.body }))
    return messages.length ? { messages } : {}
}

async function sendMail() {
    await task('mail', async () => {
        const {
            data
        } = await axios.post(sendInvitations.url(selectedTestId.value), {
            attempt_ids: [...selectedInvitationIds.value],
            address_form: invitationPreview.value?.address_form ?? invitationAddressForm.value,
            ...editedInvitationMessages(),
        })
        mailDialog.value = false
        const preview = await axios.get(invitations.url(selectedTestId.value), { params: { address_form: invitationAddressForm.value } })
        invitationPreview.value = preview.data
        selectedInvitationIds.value = []
        notice.value =
            `${data.sent ?? 0} Einladungen versandt; ${data.skipped ?? 0} bereits versandte Einladungen übersprungen.`
    })
}

async function revoke() {
    await task('revoke', async () => {
        const scope = revokeMode.value
        await axios.delete(revokeRelease.url(selectedTestId.value), { data: { scope } })
        revokeMode.value = null
        await refreshMonitor()
        notice.value = scope === 'solutions' ? 'Lösungen sind wieder verborgen. Punkte bleiben sichtbar.' : 'Ergebnisse und Lösungen sind wieder verborgen.'
    })
}

async function release() {
    await task('release', async () => {
        const solutions = releaseMode.value === 'solutions'
        await axios.post(releaseResults.url(selectedTestId.value), {
            solutions
        })
        releaseMode.value = null
        await refreshMonitor()
        notice.value = solutions ? 'Lösungen freigegeben.' : 'Ergebnisse freigegeben.'
    })
}

function openDeletion(kind, item) {
    deletionTarget.value = { kind, id: item.id, title: item.title, testIds: item.test_ids ?? [...tests.value, ...unassignedTests.value].filter((test) => test.catalog_id === item.id).map((test) => test.id) }
    error.value = ''
}

async function confirmDeletion() {
    await task('delete', async () => {
        const target = deletionTarget.value
        if (target.kind === 'catalog') {
            await axios.delete(deleteCatalog.url(target.id), { data: { test_ids: target.testIds } })
            deletionTarget.value = null
            catalogEditing.value = false
            editingQuestions.value = false
            discardNavigationAllowed = true
            await loadIndex()
            await navigateTo('catalogs')
        } else {
            await axios.delete(deleteTest.url(target.id))
            deletionTarget.value = null
            clearSelectedTest()
            await loadIndex()
            await navigateTo('tests')
        }
        notice.value = 'Gelöscht.'
    })
}

function openTestRename(test) {
    testRenameTarget.value = { id: test.id }
    testTitleDraft.value = test.title
    testTitleError.value = ''
    error.value = ''
}

async function saveTestTitle() {
    const title = testTitleDraft.value.trim()
    if (!title || title.length > 255) {
        testTitleError.value = 'Bitte eine Bezeichnung mit höchstens 255 Zeichen eingeben.'
        return
    }
    await task('rename-test', async () => {
        const id = testRenameTarget.value.id
        const { data } = await axios.patch(renameTest.url(id), { title })
        tests.value = tests.value.map((test) => test.id === id ? { ...test, title: data.test.title } : test)
        if (selectedTest.value?.id === id) {
            selectedTest.value = { ...selectedTest.value, title: data.test.title }
            invitationPreview.value = null
        }
        testRenameTarget.value = null
        notice.value = 'Testbezeichnung gespeichert.'
    })
}

function selectInvitation(id, checked) {
    selectedInvitationIds.value = checked ? [...new Set([...selectedInvitationIds.value, id])] : selectedInvitationIds.value.filter((item) => item !== id)
}

function selectAllInvitations(checked) {
    selectedInvitationIds.value = checked ? availableInvitationRecipients.value.map((recipient) => recipient.attempt_id) : []
}

function openDeadlinePicker(target) {
    if (busy.value || (target === 'settings' && !canEditTestSettings.value)) return
    const saved = target === 'settings' ? settingsDraft.closes_at : testDraft.closes_at
    let local = saved ? localDateTime(saved) : ''
    let date = local ? new Date(`${local.slice(0, 10)}T12:00`) : null
    if (!date || !Number.isFinite(date.getTime())) {
        date = new Date(`${localDateTime(serverNow.value).slice(0, 10)}T12:00`)
        date.setDate(date.getDate() + 1)
        local = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T18:00`
    }
    deadlinePickerDate.value = new Date(date.getFullYear(), date.getMonth(), date.getDate())
    deadlinePickerTime.value = local.slice(11, 16)
    deadlinePickerTarget.value = target
}

function confirmDeadlinePicker() {
    if (!deadlinePickerValid.value || busy.value) return
    if (deadlinePickerTarget.value === 'new') testDraft.closes_at = deadlinePickerValue.value
    else if (deadlinePickerTarget.value === 'settings' && canEditTestSettings.value) settingsDraft.closes_at = deadlinePickerValue.value
    deadlinePickerTarget.value = null
}

function localDateTime(value) {
    return viennaDateTime(value)
}

function openClosingDeadline(attempt) {
    deadlineTarget.value = { ...attempt }
    deadlineDraft.value = localDateTime(attempt.closing_deadline || selectedTest.value.closes_at)
}

async function saveClosingDeadline(reset) {
    if (!deadlineTarget.value || (!reset && !validDeadline.value)) return
    await task('closing-deadline', async () => {
        await axios.patch(changeClosingDeadline.url({ test: selectedTestId.value, attempt: deadlineTarget.value.id }), { closes_at: reset ? null : viennaDateTimeIso(deadlineDraft.value) })
        deadlineTarget.value = null
        await refreshMonitor()
        notice.value = 'Die persönliche Schlussfrist wurde gespeichert.'
    })
}

function activityDuration(value) {
    const seconds = Math.max(0, Math.floor(Number(value || 0)))
    return `${Math.floor(seconds / 60)} Min. ${String(seconds % 60).padStart(2, '0')} Sek.`
}

function activityStateLabel(activity) {
    return { present: activity?.fullscreen ? 'Im Test · Vollbild' : 'Im Test', away: 'Testseite verlassen', unknown: 'Verbindung unklar', paused: 'Pausiert', expired: 'Zeit abgelaufen', finished: 'Abgeschlossen' }[activity?.state] || 'Noch nicht gemeldet'
}

async function setTestPause(paused) {
    await task(paused ? 'pause-test' : 'resume-test', async () => {
        await axios.post((paused ? pauseTest : resumeTest).url(selectedTestId.value))
        await refreshMonitor()
        notice.value = paused ? 'Der gesamte Test ist pausiert.' : 'Der Test läuft mit der verbliebenen Zeit weiter.'
    })
}

function openInvitationDialog() {
    error.value = ''
    invitationDialog.value = true
}

function openTestSettings() {
    const test = selectedTest.value
    Object.assign(settingsDraft, ...Object.keys(defaultTestDraft).map(key => ({ [key]: test[key] ?? defaultTestDraft[key] })), {
        duration_minutes: test.duration_seconds / 60,
        lead_seconds: test.lead_seconds ?? 60,
        start_mode: test.start_mode ?? 'common',
        closes_at: test.closes_at ? localDateTime(test.closes_at) : '',
    })
    settingsError.value = ''
    testSettingsDialog.value = true
}

async function saveTestSettings() {
    if (!canEditTestSettings.value) return
    settingsError.value = ''
    const closing = settingsDraft.start_mode === 'individual' ? new Date(viennaDateTimeIso(settingsDraft.closes_at)) : null
    if (closing && !Number.isFinite(closing.getTime())) {
        settingsError.value = 'Bitte wähle einen gültigen letzten Abgabezeitpunkt.'
        return
    }
    await task('settings', async () => {
        try {
            const { duration_minutes, catalog_id, ...draft } = settingsDraft
            const { data } = await axios.patch(updateTestSettings.url(selectedTestId.value), {
                ...draft, title: draft.title.trim(), duration_seconds: Math.round(Number(duration_minutes) * 60), closes_at: closing?.toISOString() ?? null,
            })
            tests.value = tests.value.map(test => test.id === data.test.id ? { ...test, ...data.test } : test)
            resetInvitationPreview()
            testSettingsDialog.value = false
            await refreshMonitor()
            notice.value = 'Testeinstellungen gespeichert.'
        } catch (failure) {
            settingsError.value = requestError(failure)
            throw failure
        }
    })
}

async function toggleAccessLinks() {
    await task('access-links', async () => {
        const enabled = Boolean(selectedTest.value.access_links_disabled_at)
        await axios.patch(setAccessLinks.url(selectedTestId.value), { enabled })
        resetInvitationPreview()
        copyFallback.value = null
        invitationDialog.value = false
        await refreshMonitor()
        notice.value = enabled ? 'Persönliche Zugangslinks sind wieder aktiv.' : 'Persönliche Zugangslinks sind deaktiviert. Die Schüleranmeldung bleibt möglich.'
    })
}

async function toggleExamMode() {
    await task('exam-mode', async () => {
        await axios.patch(setExamMode.url(selectedTestId.value), { enabled: !selectedTest.value.exam_mode })
        await refreshMonitor()
    })
}

async function downloadExerciseExport() {
    const testId = selectedTestId.value
    await task('exercise-export', async () => {
        const { data, headers } = await axios.get(exportExercise.url(testId))
        const blob = new Blob([JSON.stringify(data, null, 2) + '\n'], { type: 'application/json;charset=utf-8' })
        const objectUrl = URL.createObjectURL(blob)
        const anchor = document.createElement('a')
        anchor.href = objectUrl
        anchor.download = headers?.['content-disposition']?.match(/filename="([^"]+)"/)?.[1] || `MC-Test_${testId}.json`
        document.body.appendChild(anchor)
        try {
            anchor.click()
        } finally {
            anchor.remove()
            setTimeout(() => URL.revokeObjectURL(objectUrl), 1000)
        }
        notice.value = 'Export mit persönlichen Testlinks heruntergeladen. Die Datei bei der Leistungsfeststellung für den E-Mail-Versand bereitstellen.'
    })
}

async function saveExerciseExportToDirectory() {
    if (typeof window.showDirectoryPicker !== 'function') {
        error.value = 'Dein Browser unterstützt die Ordnerauswahl nicht. Verwende Chrome oder Edge, oder lade den Export herunter und speichere ihn im Testordner.'
        return
    }
    const testId = selectedTestId.value
    await task('exercise-folder-export', async () => {
        let directory
        try {
            directory = await window.showDirectoryPicker({ id: 'schooltool-mc-test-export', mode: 'readwrite' })
        } catch (failure) {
            if (failure.name === 'AbortError') return
            error.value = 'Der Testordner wurde nicht zum Schreiben freigegeben. Bitte wähle ihn erneut aus.'
            return
        }
        if (disposed || selectedTestId.value !== testId) return
        const { data, headers } = await axios.get(exportExercise.url(testId))
        if (disposed || selectedTestId.value !== testId) return
        if (data?.kind !== 'schooltool.multiple_choice_links' || data.schema_version !== 1
            || data.test?.id !== testId || !Array.isArray(data.participants)) {
            error.value = 'Die Testlinks konnten diesem Test nicht eindeutig zugeordnet werden. Bitte aktualisiere die Testansicht.'
            return
        }
        const suggestedName = headers?.['content-disposition']?.match(/filename="([^"]+)"/)?.[1]
        const baseName = /^MC-Test_[\p{L}\p{N}._-]+\.json$/u.test(suggestedName || '')
            ? suggestedName : `MC-Test_${testId}.json`
        let filename = baseName
        for (let suffix = 2; ; suffix += 1) {
            try {
                await directory.getFileHandle(filename)
                filename = baseName.replace(/\.json$/, `_${suffix}.json`)
            } catch (failure) {
                if (failure.name === 'NotFoundError') break
                error.value = 'Der Testordner ist nicht mehr zugänglich. Bitte wähle ihn erneut aus.'
                return
            }
        }
        if (disposed || selectedTestId.value !== testId) return
        let file
        let writable
        try {
            file = await directory.getFileHandle(filename, { create: true })
            writable = await file.createWritable()
            await writable.write(JSON.stringify(data, null, 2) + '\n')
            await writable.close()
        } catch (failure) {
            if (writable) await writable.abort().catch(() => {})
            if (file) await directory.removeEntry(filename).catch(() => {})
            error.value = 'Die Testlinks konnten nicht vollständig gespeichert werden. Bitte prüfe den Testordner und versuche es erneut.'
            return
        }
        notice.value = `Persönliche Testlinks für ${data.participants.length} Schüler im Testordner „${directory.name}“ gespeichert: ${filename}`
    })
}

async function copyAccessLink(attempt) {
    try {
        await navigator.clipboard.writeText(attempt.access_url)
        notice.value = `Zugangslink für ${attempt.student_name} kopiert.`
    } catch {
        copyFallback.value = { ...attempt }
    }
}

function personalAttemptRunning(attempt) {
    return Boolean(attempt && !attempt.completed_at && (attempt.reopened_until || (selectedTest.value?.start_mode === 'individual' && attempt.started_at)) && Date.parse(attempt.effective_ends_at || attempt.reopened_until) > serverNow.value)
}

function personalTimeLabel(attempt) {
    const seconds = Math.max(0, Math.ceil((Date.parse(attempt.effective_ends_at || attempt.reopened_until) - serverNow.value) / 1000))
    return seconds ? `Zusatzzeit: ${Math.floor(seconds / 60)} Min. ${String(seconds % 60).padStart(2, '0')} Sek.` : 'Zusatzzeit abgelaufen'
}

function attemptCompleted(attempt) {
    return Boolean(attempt.completed_at || ['submitted', 'timeout'].includes(attempt.status))
}

function reopenUnavailableReason(attempt) {
    if (selectedTest.value?.paused_at) return 'Bitte den Test zuerst fortsetzen.'
    if (selectedTest.value?.start_mode !== 'individual' && !testEnded.value) return 'Erst nach Ende der gemeinsamen Bearbeitungszeit möglich.'
    if (!attemptCompleted(attempt) || personalAttemptRunning(attempt)) return 'Die Bearbeitung ist noch nicht abgeschlossen.'
    if (selectedTest.value?.start_mode === 'individual') {
        if (!attempt.started_at) return 'Die Bearbeitung wurde noch nicht gestartet.'
        const closing = attempt.closing_deadline || attempt.closes_at || selectedTest.value.closes_at
        if (!Number.isFinite(Date.parse(closing)) || Date.parse(closing) <= serverNow.value) return 'Bitte zuerst die persönliche Schlussfrist in der Schülerübersicht verlängern.'
    }
    return ''
}

function canSelectReopen(attempt) {
    return reopenSelectionEnabled.value && reopenUnavailableReason(attempt) === ''
}

function selectReopen(id, checked) {
    if (checked && !eligibleReopenAttempts.value.some(attempt => attempt.id === id)) return
    selectedReopenIds.value = checked ? [...new Set([...selectedReopenIds.value, id])] : selectedReopenIds.value.filter(value => value !== id)
}

function selectAllReopen(checked) {
    selectedReopenIds.value = checked ? eligibleReopenAttempts.value.map(attempt => attempt.id) : []
}

function openReopenSelection() {
    if (!reopenSelectionEnabled.value || !attempts.value.length) return
    selectedReopenIds.value = []
    reopenMinutes.value = 5
    reopenSeconds.value = 0
    reopenDialog.value = true
    error.value = ''
}

async function savePersonalReopen() {
    if (!validReopenTime.value || !selectedReopenAttempts.value.length) return
    const targets = selectedReopenAttempts.value.map(person => person.id)
    await task('reopen-attempt', async () => {
        await axios.post(reopenAttempts.url(selectedTestId.value), { attempt_ids: targets, remaining_seconds: reopenTimeValue.value })
        reopenDialog.value = false
        selectedReopenIds.value = selectedReopenIds.value.filter(id => !targets.includes(id))
        await refreshMonitor()
        notice.value = `Zusatzzeit für ${targets.length} ausgewählte Person${targets.length === 1 ? '' : 'en'} freigegeben.`
    })
}

function openRemainingTime() {
    const seconds = Math.max(1, Math.ceil((Date.parse(selectedTest.value.ends_at) - serverNow.value) / 1000))
    remainingMinutes.value = Math.floor(seconds / 60)
    remainingSeconds.value = seconds % 60
    error.value = ''
    remainingTimeDialog.value = true
}

async function saveRemainingTime() {
    if (!validRemainingTime.value) {
        return
    }
    await task('remaining-time', async () => {
        await axios.patch(changeRemainingTime.url(selectedTestId.value), { remaining_seconds: remainingTimeValue.value })
        remainingTimeDialog.value = false
        await refreshMonitor()
        notice.value = 'Die verbleibende Zeit wurde für alle geändert.'
    })
}

async function finishTestEarly() {
    await task('close-test', async () => {
        await axios.post(closeTest.url(selectedTestId.value))
        closeTestDialog.value = false
        await refreshMonitor()
        notice.value = 'Der Test ist geschlossen. Offene Versuche wurden bewertet.'
    })
}

function completionTimeLabel(attempt) {
    if (!isScored(attempt) || !(selectedTest.value?.start_mode === 'individual' ? attempt.started_at : selectedTest.value?.starts_at) || !attempt.completed_at) {
        return '—'
    }
    const milliseconds = Date.parse(attempt.completed_at) - Date.parse(selectedTest.value.start_mode === 'individual' ? attempt.started_at : selectedTest.value.starts_at) - Number(attempt.pause_seconds || 0) * 1000
    if (!Number.isFinite(milliseconds) || milliseconds < 0) {
        return '—'
    }
    const seconds = Math.floor(milliseconds / 1000)
    return `${Math.floor(seconds / 60)} Min. ${String(seconds % 60).padStart(2, '0')} Sek.${attempt.status === 'timeout' ? ' (automatisch)' : ''}`
}

function dateLabel(value) {
    const instant = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value || '') ? viennaDateTimeIso(value) : value
    return instant ? new Date(instant).toLocaleString('de-AT', {
        timeZone: 'Europe/Vienna',
        dateStyle: 'short',
        timeStyle: 'medium'
    }) : '—'
}

function isScored(attempt) {
    return ['submitted', 'timeout'].includes(attempt.status) && Boolean(attempt.started_at)
}

function scoreLabel(attempt) {
    return isScored(attempt) ? `${attempt.score ?? 0} / ${selectedTest.value?.total_points ?? 0}` : '—'
}
</script>

<style scoped>
.mc-source-path {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.mc-template-connection {
    padding: 14px 16px;
    border: 1px solid #c9d9e8;
    border-radius: 8px;
    background: #f5f9fd;
}
.mc-template-connection .mc-toolbar { align-items: flex-start; }
.mc-template-connection strong { color: #204d73; }
.mc-sync-direction {
    padding: 12px 14px;
    border: 1px solid #dce4ec;
    border-radius: 6px;
}
.mc-sync-direction h3 { font-size: .94rem; }
.mc-sync-changes { padding-left: 20px; }
.mc-sync-changes li { margin-top: 5px; overflow-wrap: anywhere; }
.mc-sync-compare { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 8px; margin-top: 8px; }
.mc-sync-answer-detail { margin-top: 6px; padding: 8px 10px; border-radius: 5px; background: #f4f7fa; font-size: .82rem; }
.mc-sync-answer-detail h4 { font-size: .82rem; margin-bottom: 5px; }
.mc-sync-answer-detail ul { padding-left: 17px; }
.mc-sync-answer-detail li { margin-top: 3px; }
.mc-deadline-trigger { grid-column: 1 / -1; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 5px 18px; width: 100%; min-width: 0; text-align: left; padding: 14px 16px; border: 1px solid #aebfce; border-radius: 6px; background: #fff; color: #243c52; cursor: pointer; }
.mc-deadline-label { grid-column: 1 / -1; font-size: .85rem; color: #52697e; }
.mc-deadline-trigger strong { font-size: 1rem; line-height: 1.5; overflow-wrap: anywhere; }
.mc-deadline-action { display: flex; align-items: center; gap: 8px; color: #245b89; font-size: .9rem; }
.mc-deadline-trigger:focus-visible { outline: 2px solid #3879af; outline-offset: 2px; }
.mc-deadline-trigger:disabled { cursor: default; opacity: .65; }
.mc-deadline-dialog :deep(.v-picker) { width: 100%; max-width: 100%; }
@media (max-width: 420px) { .mc-deadline-trigger { grid-template-columns: 1fr; } }

.mc-start-modes { grid-column: 1 / -1; min-width: 0; border: 0; padding: 0; margin: 0 0 16px; }
.mc-start-modes legend { margin-bottom: 8px; padding: 0; font-size: .875rem; font-weight: 600; color: #425b70; }
.mc-start-mode-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.mc-start-mode { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: start; gap: 11px; padding: 14px; border: 1px solid #c6d4e2; border-radius: 6px; cursor: pointer; }
.mc-start-mode.is-selected { border-color: #3879af; background: #f0f6fc; }
.mc-start-mode:focus-within { outline: 2px solid #3879af; outline-offset: 2px; }
.mc-start-mode input { margin-top: 4px; width: 18px; height: 18px; accent-color: #245b89; }
.mc-start-mode strong { display: block; font-size: .95rem; line-height: 1.4; overflow-wrap: anywhere; }
.mc-start-mode-description { display: block; margin-top: 4px; font-size: .85rem; color: #52697e; line-height: 1.5; }
.mc-start-modes:disabled .mc-start-mode { cursor: default; opacity: .65; }
@media (max-width: 700px) { .mc-start-mode-options { grid-template-columns: 1fr; } }

.mc-workspace :deep(.v-btn) { text-transform: none; letter-spacing: .01em; }
.mc-control-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 28px; border-block: 1px solid #dce4ed; padding-block: 22px; }
.mc-control-section h3, .mc-results-section h3 { font-size: 1rem; color: #28465e; }
.mc-control-section :deep(.v-btn), .mc-results-section :deep(.v-btn) { text-transform: none; letter-spacing: .01em; }
.mc-control-section + .mc-control-section { border-left: 1px solid #dce4ed; padding-left: 28px; }
.mc-settings-fields { border: 0; min-width: 0; padding: 0; }
.mc-setting-check { display: flex; gap: 10px; align-items: center; padding: 9px 0; }
.mc-setting-check input { width: 17px; height: 17px; accent-color: #245b89; }
.mc-test-maintenance { border-top: 1px solid #dce4ed; padding-top: 18px; }
.mc-test-maintenance summary { cursor: pointer; color: #586b7d; font-size: .9rem; }
@media (max-width: 900px) { .mc-control-grid { grid-template-columns: 1fr; gap: 20px; } .mc-control-section + .mc-control-section { border-left: 0; border-top: 1px solid #dce4ed; padding-left: 0; padding-top: 20px; } }

.mc-activity-cell { min-width: 200px; }
.mc-activity-state { display: inline-block; padding: 4px 9px; border-radius: 7px; background: #eef2f3; font-size: 0.8rem; font-weight: 600; }
.mc-activity-present { color: #205a46; background: #e9f5ee; }
.mc-activity-away { color: #855315; background: #fff0d8; }
.mc-activity-unknown { color: #63566b; background: #f2edf6; }

.mc-workspace {
    padding: 0 2px 24px;
    color: #243447;
}

.mc-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 6px 20px;
}

h1 {
    font-size: 1.55rem;
    font-weight: 700;
}

h2 {
    font-size: 1.15rem;
    font-weight: 650;
}

h3 {
    font-size: 1rem;
}

.mc-heading p {
    font-size: .9rem;
    color: #68768a;
    margin-top: 4px;
}

.mc-guide {
    padding: 13px 16px;
    border-left: 3px solid #6e96b5;
    background: #f1f5f8;
    border-radius: 4px;
}

.mc-catalog-detail { width: 100%; max-width: 960px; }
.mc-test-create { width: 100%; max-width: 880px; }
.mc-test-create .mc-heading { padding-bottom: 12px; }
.mc-test-create .mc-guide { padding: 10px 14px; }
.mc-test-create .mc-form-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.mc-test-title { grid-column: 1 / -1; }
.mc-catalog-actions :deep(.v-btn) { max-width: 100%; height: auto; min-height: 36px; }
.mc-catalog-actions :deep(.v-btn__content) { white-space: normal; text-align: center; }
.mc-catalog-heading { margin: 8px 0 20px; }
.mc-catalog-empty { text-align: center; padding: 46px 20px; color: #68768a; }
.mc-catalog-empty h3 { color: #243447; margin: 14px 0 6px; }
.mc-catalog-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));
    gap: 18px;
    list-style: none;
    padding: 0;
}
.mc-catalog-list li, .mc-catalog-card { min-width: 0; height: 100%; }
.mc-test-list { grid-auto-rows: 1fr; }
.mc-catalog-card { display: flex; flex-direction: column; }
.mc-catalog-card :deep(.v-card-text) { flex: 1; }
.mc-catalog-card h3 { font-size: 1.08rem; overflow-wrap: anywhere; }

.mc-access-cell { min-width: 102px; white-space: nowrap; text-align: center; }
.mc-test-heading-title { display: flex; align-items: center; gap: 4px; min-width: 0; }
.mc-test-heading-title h2, .mc-test-heading-title h3 { min-width: 0; overflow-wrap: anywhere; }
.mc-test-heading-title :deep(.v-btn) { flex: 0 0 auto; }
.mc-test-card-actions { flex-wrap: wrap; gap: 4px; }
.mc-test-subnav { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-start; }
.mc-test-subnav :deep(.v-btn) { text-transform: none; letter-spacing: normal; }
.mc-control-grid--single { grid-template-columns: minmax(0, 1fr); }
.mc-control-grid--single .mc-control-section { border-left: 0; border-top: 0; padding-left: 0; padding-top: 0; }

.mc-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 22px;
    padding: 0;
    list-style: none;
    color: #6a7786;
    font-size: .79rem;
}

.mc-steps .is-current {
    color: #245b87;
    font-weight: 700;
}

.mc-guide p {
    margin-top: 6px;
    font-size: .87rem;
}

.mc-more > summary {
    width: fit-content;
    cursor: pointer;
    color: #42637e;
    font-size: .86rem;
    padding: 5px 0;
}

.mc-publish-summary {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 10px 20px;
    font-size: .9rem;
}

.mc-publish-summary dt {
    color: #697689;
}

.mc-publish-summary dd {
    font-weight: 600;
    overflow-wrap: anywhere;
}

.mc-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.mc-form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px 16px;
}

.mc-import {
    padding: 20px;
    border: 1px solid #cddae7;
    border-left: 4px solid #3879af;
    border-radius: 6px;
    background: #f5f8fc;
}

.mc-native-field {
    display: grid;
    gap: 7px;
    font-size: .88rem;
    font-weight: 600;
}

.mc-native-field textarea {
    border: 1px solid #aebac7;
    border-radius: 5px;
    background: white;
    padding: 10px 12px;
    width: 100%;
    resize: vertical;
    color: #243447;
    font-weight: 400;
}

.mc-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 24px;
}

.mc-check {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: .86rem;
}

.mc-check input {
    width: 19px;
    height: 19px;
    flex-shrink: 0;
    accent-color: #2563a6;
}

.mc-monitor {
    display: flex;
    flex-wrap: wrap;
    background: #f3f6f9;
    border-radius: 6px;
}

.mc-monitor>div {
    display: flex;
    align-items: baseline;
    gap: 9px;
    flex: 1 1 160px;
    padding: 15px 18px;
}

.mc-monitor strong {
    font-size: 1.5rem;
    color: #285d8a;
}

.mc-monitor span {
    font-size: .86rem;
    color: #5e6c7b;
}

.mc-table-wrap {
    overflow-x: auto;
    width: 100%;
}

.mc-table {
    border-collapse: collapse;
    width: 100%;
    text-align: left;
    font-size: .87rem;
}

.mc-table th {
    background: #f4f6f8;
    font-size: .78rem;
    color: #556578;
    font-weight: 700;
    white-space: nowrap;
}

.mc-table td,
.mc-table th {
    padding: 12px;
    border-bottom: 1px solid #e2e7ed;
    vertical-align: top;
}

.mc-table td {
    overflow-wrap: anywhere;
}

.mc-evaluation {
    background: #f7f9fb;
}

:focus-visible {
    outline: 3px solid #3b82b8;
    outline-offset: 3px;
}

.mc-summary-preview {
    max-height: 42vh;
    overflow: auto;
    overflow-wrap: anywhere;
    padding: 16px;
    border: 1px solid #dce3eb;
    border-radius: 8px;
    background: #f7f9fb;
    font: inherit;
    font-size: .88rem;
}

.mc-summary-question + .mc-summary-question {
    border-top: 1px solid #dce3eb;
    margin-top: 16px;
    padding-top: 16px;
}

.mc-summary-text {
    white-space: pre-line;
    overflow-wrap: anywhere;
}

@media (max-width: 1200px) {
    .mc-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .mc-test-create .mc-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 520px) {
    .mc-test-create .mc-form-grid { grid-template-columns: 1fr; }
}

@media (max-width: 700px) {
    .mc-form-grid {
        grid-template-columns: 1fr;
    }

    .mc-import {
        padding: 14px;
    }

    .mc-table td,
    .mc-table th {
        padding: 10px 7px;
    }

    .mc-toolbar :deep(.v-btn) {
        max-width: 100%;
        white-space: normal;
    }
}
</style>

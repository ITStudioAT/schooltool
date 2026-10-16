<template>
    <div class="mc-workspace">
        <section class="mc-heading">
            <div>
                <h1>Multiple Choice</h1>
                <p>Fragen vorbereiten, gemeinsam starten und Ergebnisse im Blick behalten.</p>
            </div>
            <v-btn variant="text" prepend-icon="mdi-refresh" :loading="loading" @click="loadIndex">Aktualisieren</v-btn>
        </section>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-3" role="alert">{{ error }}</v-alert>
        <v-alert v-if="notice" type="success" variant="tonal" class="mb-3" role="status">{{ notice }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3"
            aria-label="Multiple Choice wird geladen" />
        <v-alert v-if="!available" type="info" variant="tonal"
            class="mb-3">{{ unavailableMessage || 'Multiple Choice muss für diese lokale Anwendung noch aktiviert werden.' }}</v-alert>
        <template v-if="available">
            <nav class="mc-sections" aria-label="Multiple-Choice-Bereiche">
                <v-btn v-for="item in sections" :key="item.id" :variant="section === item.id ? 'flat' : 'text'"
                    :color="section === item.id ? 'primary' : undefined" :prepend-icon="item.icon"
                    :aria-pressed="section === item.id" @click="section = item.id">{{ item.label }}</v-btn>
            </nav>

            <div v-if="section !== 'catalogs' || catalogEditing" class="mc-guide mb-4" aria-label="Nächster Schritt">
                <ol class="mc-steps" aria-label="Ablauf">
                    <li :class="{ 'is-current': section === 'catalogs' }">1 Fragen</li>
                    <li :class="{ 'is-current': section === 'tests' && (!selectedTest || preparingTest) }">2 Test</li>
                    <li :class="{ 'is-current': section === 'tests' && selectedTest && !preparingTest }">3 Gemeinsam starten</li>
                    <li :class="{ 'is-current': section === 'results' }">4 Ergebnisse</li>
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
                    </div>
                    <v-card border rounded="lg" class="mt-3">
                    <v-card-text>
                        <v-text-field v-model="catalog.title" label="Bezeichnung des Katalogs" variant="outlined"
                            hide-details />
                        <div class="mc-toolbar mt-4">
                            <p class="text-body-2">{{ catalog.questions.length }} Fragen · {{ catalogReadyCount }} aktiv
                                und geprüft</p>
                            <v-spacer />
                            <v-btn :variant="!catalog.questions.length ? 'flat' : 'text'"
                                :color="!catalog.questions.length ? 'primary' : undefined" prepend-icon="mdi-file-import-outline"
                                @click="showImport = !showImport">Vorlage importieren</v-btn>
                            <v-btn variant="outlined" prepend-icon="mdi-plus"
                                @click="catalog.questions.push(newQuestion())">Frage hinzufügen</v-btn>
                        </div>
                        <p class="text-caption text-medium-emphasis mt-2">Entwürfe können gespeichert werden. Nur
                            aktive, vollständige und ausdrücklich geprüfte Fragen stehen für neue Tests bereit.</p>
                        <div v-if="showImport" class="mc-import mt-4">
                            <h2>Fragen aus einer Vorlage</h2>
                            <p class="text-body-2 mb-3">Vollständige Multiple-Choice-Fragen mit markierten Lösungen
                                direkt übernehmen oder aus Fragen mit Musterantworten einen prüfbaren Entwurf erzeugen.
                            </p>
                            <div class="mc-toolbar">
                                <label class="mc-file">Datei auswählen <input type="file"
                                        accept=".md,.txt,.json,text/plain,application/json" @change="readSourceFile" />
                                </label>
                                <v-select v-model="importFormat"
                                    :items="[{ title: 'Markdown / Text', value: 'markdown' }, { title: 'JSON', value: 'json' }]"
                                    label="Format" hide-details variant="outlined" density="compact" class="mc-format"
                                    @update:model-value="importPreview = null" />
                            </div>
                            <label class="mc-native-field mt-3">
                                <span>Vorlage</span>
                                <textarea v-model="importText" rows="8"
                                    placeholder="Fragen und Antworten hier einfügen …" @input="importPreview = null" />
                            </label>
                            <label class="mc-check mt-3">
                                <input v-model="generateWithAi" type="checkbox" @change="importPreview = null" />Aus
                                Musterantworten mit KI Antwortoptionen erstellen</label>
                            <p v-if="generateWithAi" class="text-caption mt-2">Die KI arbeitet mit dieser Vorlage. Alle
                                erzeugten Fragen bleiben ungeprüfte Entwürfe; Inhalt, Distraktoren und Lösungen bitte
                                einzeln prüfen.</p>
                            <v-btn class="mt-3" variant="outlined" :loading="busy === 'import'"
                                :disabled="!importText.trim() || !!busy"
                                @click="previewSource">{{ generateWithAi ? 'Entwurf erzeugen' : 'Import prüfen' }}</v-btn>
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
                                <v-btn class="mt-3" color="primary" :disabled="!importPreview.questions?.length"
                                    @click="adoptImport">Entwurf in Katalog übernehmen</v-btn>
                            </div>
                        </div>
                        <QuestionEditor v-model:questions="catalog.questions" />
                    </v-card-text>
                    <v-card-actions class="pa-4 pt-0">
                        <v-spacer />
                        <v-btn color="primary" :variant="canPrepareCatalog ? 'outlined' : 'flat'" prepend-icon="mdi-content-save-outline"
                            :loading="busy === 'catalog'" :disabled="!catalog.title.trim() || !!busy"
                            @click="saveCatalog">Katalog speichern</v-btn>
                        <v-btn v-if="canPrepareCatalog" color="primary" variant="flat"
                            prepend-icon="mdi-clipboard-play-outline" :disabled="!!busy" @click="prepareCatalogTest">
                            Test vorbereiten
                        </v-btn>
                    </v-card-actions>
                    </v-card>
                </template>
            </section>

            <section v-if="section === 'tests'" aria-label="Tests vorbereiten">
                <v-card border rounded="lg">
                    <v-card-title>Neuen Test vorbereiten</v-card-title>
                    <v-card-text>
                        <div class="mc-form-grid">
                            <v-text-field v-model="testDraft.title" label="Testbezeichnung" variant="outlined"
                                hide-details />
                            <v-select v-model="testDraft.catalog_id" :items="catalogChoices" item-title="label"
                                item-value="id" label="Gespeicherter Fragenkatalog" variant="outlined" hide-details />
                            <v-text-field v-model.number="testDraft.question_count" type="number" min="1"
                                :max="selectedCatalogReadyCount || undefined" label="Fragen pro Schüler"
                                variant="outlined" hide-details />
                            <v-text-field v-model.number="testDraft.total_points" type="number" min="0.01" step="0.01"
                                label="Erreichbare Gesamtpunkte" variant="outlined" hide-details />
                            <v-text-field v-model.number="testDraft.duration_minutes" type="number" min="1"
                                label="Bearbeitungsdauer in Minuten" variant="outlined" hide-details />
                            <v-text-field v-model.number="testDraft.lead_seconds" type="number" min="0"
                                label="Vorlaufzeit in Sekunden" variant="outlined" hide-details />
                        </div>
                        <details class="mc-more mt-4">
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
                        <v-alert type="info" variant="tonal" class="mt-4 text-body-2">Jede Frage zählt gleich viel.
                            Punkte gibt es nur für die exakt richtige Auswahl; sonst null Punkte. Gerundet wird erst die
                            Gesamtpunktzahl.<span v-if="testDraft.balance_types"> Der Anteil beider Antworttypen folgt
                                dem Katalog und bleibt pro Person möglichst gleich.</span>
                        </v-alert>
                        <h3 class="mt-6 mb-2">Schülerliste prüfen</h3>
                        <p class="text-body-2 mb-3">CSV mit Vorname, Nachname, E-Mail, Kurs und Klasse. Die gesamte
                            Liste wird mit bestehenden Schülerkonten abgeglichen. Unklare Zuordnungen müssen zuerst
                            korrigiert werden.</p>
                        <label class="mc-file">CSV auswählen <input type="file" accept=".csv,text/csv"
                                @change="readRosterFile" />
                        </label>
                        <p v-if="rosterFileName" class="text-caption text-medium-emphasis mt-2">Ausgewählt: {{ rosterFileName }}</p>
                        <details class="mc-more mt-3">
                            <summary>CSV als Text einfügen oder bearbeiten</summary>
                            <label class="mc-native-field mt-3">
                                <span>Schülerliste als CSV</span>
                                <textarea v-model="rosterCsv" rows="5" @input="roster = null; rosterFileName = ''" />
                            </label>
                        </details>
                        <v-btn class="mt-3" variant="outlined" prepend-icon="mdi-account-check-outline"
                            :loading="busy === 'roster'" :disabled="!rosterCsv.trim() || !!busy"
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
                        <p class="text-caption text-medium-emphasis mt-4">Mit der Freigabe werden Fragen, Lösungen und
                            persönliche Zuteilungen fest gespeichert. Der Test wartet danach auf deinen gemeinsamen
                            Start. Es wird noch keine E-Mail versandt.</p>
                    </v-card-text>
                    <v-card-actions class="pa-4 pt-0">
                        <v-spacer />
                        <v-btn color="primary" variant="flat" :disabled="!canPublish || !!busy"
                            :loading="busy === 'publish'" @click="publishDialog = true">Test freigeben</v-btn>
                    </v-card-actions>
                </v-card>
            </section>

            <section v-if="section !== 'catalogs'" class="mt-4" aria-label="Freigegebene Tests">
                <div class="mc-toolbar mb-3">
                    <h2>{{ section === 'results' ? 'Ergebnisse' : 'Freigegebene Tests' }}</h2>
                    <v-spacer />
                    <v-select :model-value="selectedTestId" :items="tests" item-title="title" item-value="id"
                        label="Test auswählen" :disabled="!!busy" variant="outlined" hide-details density="comfortable"
                        class="mc-picker" @update:model-value="selectTest" />
                </div>
                <v-alert v-if="monitorError && !selectedTest" type="warning" variant="tonal"
                    class="mb-3">{{ monitorError }} <v-btn variant="text" @click="refreshMonitor">Erneut laden</v-btn>
                </v-alert>
                <v-sheet v-if="!tests.length" border rounded="lg" class="pa-6 text-medium-emphasis">
                    <p>Noch kein Test freigegeben. Wähle einen geprüften Katalog und deine Schülerliste – danach kann es losgehen.</p>
                    <v-btn v-if="section === 'results'" class="mt-3" color="primary" variant="flat"
                        @click="section = catalogs.length ? 'tests' : 'catalogs'">
                        {{ catalogs.length ? 'Ersten Test vorbereiten' : 'Fragenkatalog anlegen' }}
                    </v-btn>
                </v-sheet>
                <v-card v-else-if="selectedTest" border rounded="lg">
                    <v-card-text>
                        <div class="mc-toolbar">
                            <div>
                                <h2>{{ selectedTest.title }}</h2>
                                <p class="text-body-2 text-medium-emphasis">{{ selectedTest.question_count }} Fragen ·
                                    {{ selectedTest.total_points }} Punkte ·
                                    {{ Math.round(selectedTest.duration_seconds / 60) }} Minuten</p>
                            </div>
                            <v-spacer />
                            <v-chip :color="testPhase.color" variant="tonal">{{ testPhase.label }}</v-chip>
                        </div>
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
                        <p v-if="selectedTest.starts_at" class="text-body-2 mt-3">Start:
                            {{ dateLabel(selectedTest.starts_at) }} · Ende: {{ dateLabel(selectedTest.ends_at) }}</p>
                        <div class="mc-toolbar mt-4">
                            <v-btn v-if="!selectedTest.starts_at" color="primary" variant="flat" prepend-icon="mdi-play"
                                :loading="busy === 'countdown'" :disabled="!!busy" @click="startDialog = true">Countdown
                                starten ({{ selectedTest.lead_seconds }} s)</v-btn>
                            <v-btn variant="outlined" prepend-icon="mdi-email-outline" :loading="busy === 'invitations'"
                                :disabled="!!busy" @click="previewInvitations">Einladungen vorbereiten</v-btn>
                            <span class="text-caption text-medium-emphasis">Status wird automatisch aktualisiert.</span>
                        </div>
                        <div v-if="invitationPreview" class="mc-import mt-4">
                            <h3>Empfängervorschau</h3>
                            <p class="text-body-2 my-2">Jede Person erhält ihren persönlichen Testzugang. Bereits
                                versandte Einladungen werden übersprungen.</p>
                            <div class="mc-table-wrap">
                                <table class="mc-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>E-Mail</th>
                                            <th>Einladung</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="recipient in invitationPreview.recipients"
                                            :key="recipient.attempt_id">
                                            <td>{{ recipient.student_name }}</td>
                                            <td>{{ recipient.email }}</td>
                                            <td>{{ recipient.invited_at ? `Versandt: ${dateLabel(recipient.invited_at)}` : 'Noch nicht versandt' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <v-btn class="mt-3" color="primary" variant="flat"
                                :disabled="!unsentInvitationCount || !!busy"
                                @click="mailDialog = true">{{ unsentInvitationCount }} Einladungen per E-Mail
                                senden</v-btn>
                        </div>
                        <v-alert v-if="monitorError" type="warning" variant="tonal" class="mt-4">{{ monitorError }}
                            Angezeigte Daten können veraltet sein.</v-alert>
                        <div v-if="section === 'results'" class="mt-5">
                            <v-alert :type="selectedTest.results_released_at ? 'success' : 'info'" variant="tonal"
                                class="mb-3">{{ selectedTest.results_released_at ? 'Ergebnisse sind für Schüler freigegeben.' : 'Ergebnisse sind für Schüler noch verborgen.' }}
                                {{ selectedTest.solutions_released_at ? 'Auch Lösungen sind freigegeben.' : 'Lösungen sind noch verborgen.' }}</v-alert>
                            <div class="mc-toolbar mb-4">
                                <v-btn v-if="!selectedTest.results_released_at" variant="outlined"
                                    :disabled="!testEnded || !!busy" @click="releaseMode = 'results'">Ergebnisse
                                    freigeben</v-btn>
                                <v-btn v-if="!selectedTest.solutions_released_at" variant="outlined"
                                    :disabled="!testEnded || !!busy" @click="releaseMode = 'solutions'">Ergebnisse und
                                    Lösungen freigeben</v-btn>
                                <span v-if="!testEnded" class="text-caption text-medium-emphasis">Freigabe nach Ende der
                                    Bearbeitungszeit möglich.</span>
                            </div>
                        </div>
                        <div class="mc-table-wrap mt-4">
                            <table class="mc-table">
                                <thead>
                                    <tr>
                                        <th>Schüler</th>
                                        <th>Status</th>
                                        <th>Punkte</th>
                                        <th>Prozent</th>
                                        <th v-if="section === 'results'">Auswertung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="attempt in attempts" :key="attempt.id">
                                        <tr>
                                            <td>
                                                <strong>{{ attempt.student_name }}</strong>
                                                <div class="text-caption text-medium-emphasis">{{ attempt.email }}</div>
                                            </td>
                                            <td>{{ statusLabel(attempt.status) }}</td>
                                            <td>{{ scoreLabel(attempt) }}</td>
                                            <td>{{ isScored(attempt) ? `${attempt.percentage ?? 0} %` : '—' }}</td>
                                            <td v-if="section === 'results'">
                                                <v-btn v-if="isScored(attempt)" variant="text" size="small"
                                                    @click="expandedAttempt = expandedAttempt === attempt.id ? null : attempt.id">{{ expandedAttempt === attempt.id ? 'Schließen' : 'Fragen ansehen' }}</v-btn>
                                            </td>
                                        </tr>
                                        <tr v-if="section === 'results' && expandedAttempt === attempt.id">
                                            <td colspan="5" class="mc-evaluation">
                                                <div v-for="(question, position) in attempt.question_results ?? attempt.evaluation ?? []"
                                                    :key="question.question_id ?? question.id" class="mb-4">
                                                    <p>
                                                        <strong>{{ position + 1 }}. {{ question.prompt }}</strong>
                                                    </p>
                                                    <p :class="question.correct ? 'text-success' : 'text-error'">
                                                        {{ question.correct ? 'Exakt richtig' : 'Nicht exakt richtig' }}
                                                        · {{ question.points ?? 0 }} Punkte</p>
                                                    <ul class="ml-5 mt-1">
                                                        <li v-for="option in question.options ?? []" :key="option.id">
                                                            {{ option.text }} <strong
                                                                v-if="question.selected_option_ids?.includes(option.id)">
                                                                · ausgewählt</strong>
                                                            <span
                                                                v-if="question.correct_option_ids?.includes(option.id)"
                                                                class="text-success"> · richtig</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </v-card-text>
                </v-card>
            </section>
        </template>

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
                    <v-btn @click="discardCatalogDialog = false">Weiterbearbeiten</v-btn>
                    <v-spacer />
                    <v-btn color="primary" @click="leaveCatalog">Änderungen verwerfen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="publishDialog" max-width="560">
            <v-card>
                <v-card-title>Test freigeben?</v-card-title>
                <v-card-text>
                    <h3 class="mb-3">{{ testDraft.title }}</h3>
                    <dl class="mc-publish-summary">
                        <dt>Katalog</dt><dd>{{ selectedCatalogTitle }}</dd>
                        <dt>Teilnehmer</dt><dd>{{ rosterMatchedCount }} eindeutig zugeordnet</dd>
                        <dt>Pro Person</dt><dd>{{ testDraft.question_count }} Fragen · {{ testDraft.total_points }} Punkte</dd>
                        <dt>Bearbeitungszeit</dt><dd>{{ testDraft.duration_minutes }} Minuten</dd>
                        <dt>Countdown</dt><dd>{{ testDraft.lead_seconds }} Sekunden</dd>
                    </dl>
                    <p class="mt-4">Die persönliche Zuteilung bleibt danach unverändert. Den Countdown startest du separat; E-Mails werden noch nicht versandt.</p>
                </v-card-text>
                <v-card-actions>
                    <v-btn @click="publishDialog = false">Zurück</v-btn>
                    <v-spacer />
                    <v-btn color="primary" :loading="busy === 'publish'" :disabled="!!busy || !canPublish"
                        @click="publishTest">Test freigeben</v-btn>
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
                <v-card-text>Es werden {{ unsentInvitationCount }} echte E-Mails an die Personen in der
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
        <v-dialog :model-value="!!releaseMode" max-width="520" @update:model-value="releaseMode = null">
            <v-card>
                <v-card-title>{{ releaseMode === 'solutions' ? 'Ergebnisse und Lösungen freigeben?' : 'Ergebnisse freigeben?' }}</v-card-title>
                <v-card-text>Schüler können danach ihre eigenen
                    Punkte{{ releaseMode === 'solutions' ? ' und die richtigen Antworten' : '' }} ansehen. Es erfolgt
                    keine Übernahme in andere Leistungsbeurteilungen.</v-card-text>
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
    ref
} from 'vue'
import axios from 'axios'
import {
    index,
    createCatalog,
    updateCatalog,
    previewImport,
    rosterPreview,
    createTest,
    showTest,
    countdown,
    invitations,
    sendInvitations,
    releaseResults
} from '@/actions/App/Http/Controllers/Admin/MultipleChoiceController'
import QuestionEditor from './QuestionEditor.vue'
import {
    messageText,
    newQuestion,
    readyQuestions,
    requestError,
    statusLabel
} from './multipleChoiceTeacher'

const sections = [{
    id: 'catalogs',
    label: 'Fragenkataloge',
    icon: 'mdi-bookshelf'
}, {
    id: 'tests',
    label: 'Tests',
    icon: 'mdi-clipboard-play-outline'
}, {
    id: 'results',
    label: 'Ergebnisse',
    icon: 'mdi-chart-box-outline'
}]
const section = ref('catalogs')
const catalogs = ref([])
const tests = ref([])
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
const importFormat = ref('markdown')
const importText = ref('')
const generateWithAi = ref(false)
const importPreview = ref(null)
const rosterCsv = ref('')
const rosterFileName = ref('')
const roster = ref(null)
const testDraft = reactive({
    title: '',
    catalog_id: null,
    question_count: 10,
    total_points: 10,
    duration_minutes: 20,
    lead_seconds: 60,
    balance_types: true,
    shuffle_questions: true,
    shuffle_options: true
})
const selectedTestId = ref(null)
const preparingTest = ref(false)
const selectedTest = ref(null)
const attempts = ref([])
const expandedAttempt = ref(null)
const invitationPreview = ref(null)
const monitorError = ref('')
const publishDialog = ref(false)
const startDialog = ref(false)
const mailDialog = ref(false)
const releaseMode = ref(null)
const serverOffset = ref(0)
const now = ref(Date.now())
let pollInterval = null
let tickInterval = null
let monitorLoading = false
let disposed = false

const catalogReadyCount = computed(() => readyQuestions(catalog.value.questions).length)
const canPrepareCatalog = computed(() => {
    const saved = catalogs.value.find((item) => item.id === catalog.value.id)
    return Boolean(saved && catalogReadyCount.value > 0 && saved.title === catalog.value.title
        && JSON.stringify(saved.questions) === JSON.stringify(catalog.value.questions))
})
const selectedCatalogTitle = computed(() => catalogs.value.find((item) => item.id === testDraft.catalog_id)?.title ?? '')
const nextStepHint = computed(() => {
    if (section.value === 'catalogs') {
        if (canPrepareCatalog.value) {
            return 'Dein Katalog ist bereit. Mit „Test vorbereiten“ wählst du Umfang, Zeit und Schülerliste.'
        }
        return catalog.value.questions.length
            ? 'Prüfe Fragen und Lösungen und speichere deinen Katalog. Danach bereitest du den Test vor.'
            : 'Starte mit einer Vorlage oder füge deine erste Frage hinzu. Danach prüfst und speicherst du den Katalog.'
    }
    if (section.value === 'results') {
        return 'Wähle einen Test, sieh dir die Ergebnisse an und gib Punkte oder Lösungen bewusst für die Schüler frei.'
    }
    if (!selectedTest.value || preparingTest.value) {
        return 'Wähle deinen Katalog, lege Umfang und Zeit fest und prüfe die Schülerliste. Mit der Freigabe öffnet sich der Warteraum.'
    }
    if (testEnded.value) {
        return 'Die Bearbeitungszeit ist beendet. Unter „Ergebnisse“ findest du Punkte und die Auswertung jeder Frage.'
    }
    return selectedTest.value.starts_at
        ? 'Der gemeinsame Start steht fest. Behalte hier die Teilnahme im Blick; alle haben dieselbe Endzeit.'
        : 'Bereite die Einladungen vor oder lass die Schüler ihren Bereich öffnen. Sobald die Klasse bereit ist, startest du gemeinsam.'
})
const catalogChoices = computed(() => catalogs.value.map((item) => ({
    ...item,
    label: `${item.title} (${readyQuestions(item.questions ?? []).length} bereit)`
})))
const selectedCatalogReadyCount = computed(() => readyQuestions(catalogs.value.find((item) => item.id === testDraft
    .catalog_id)?.questions ?? []).length)
const rosterMatchedCount = computed(() => (roster.value?.rows ?? []).filter((row) => row.status === 'matched').length)
const canPublish = computed(() => Boolean(testDraft.title.trim() && testDraft.catalog_id && Number.isInteger(Number(
        testDraft.question_count)) && testDraft.question_count > 0 && testDraft.question_count <=
    selectedCatalogReadyCount.value && Number(testDraft.total_points) > 0 && Number(testDraft
    .duration_minutes) >= 1 && Number.isInteger(Number(testDraft.lead_seconds)) && Number(testDraft
        .lead_seconds) >= 0 && roster.value?.valid && rosterMatchedCount.value > 0))
const unsentInvitationCount = computed(() => (invitationPreview.value?.recipients ?? []).filter((recipient) => !
    recipient.invited_at).length)
const serverNow = computed(() => now.value + serverOffset.value)
const testEnded = computed(() => Boolean(selectedTest.value?.ends_at && Date.parse(selectedTest.value.ends_at) <=
    serverNow.value))
const testPhase = computed(() => {
    const test = selectedTest.value
    if (!test?.starts_at) {
        return {
            label: 'Warteraum geöffnet',
            color: 'info'
        }
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
onBeforeUnmount(() => {
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
        if (!selectedTestId.value && tests.value.length) {
            await selectTest(tests.value[0].id)
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

function openCatalog(id) {
    const selected = catalogs.value.find((item) => item.id === id)
    if (selected) {
        catalog.value = JSON.parse(JSON.stringify(selected))
        catalogEditing.value = true
        showImport.value = false
        importPreview.value = null
        importText.value = ''
        generateWithAi.value = false
        error.value = ''
        notice.value = ''
    }
}

function backToCatalogs() {
    const saved = catalogs.value.find((item) => item.id === catalog.value.id)
    if (saved && (saved.title !== catalog.value.title || JSON.stringify(saved.questions) !== JSON.stringify(catalog.value.questions))) {
        discardCatalogDialog.value = true
        return
    }
    leaveCatalog()
}

function leaveCatalog() {
    discardCatalogDialog.value = false
    catalogEditing.value = false
    error.value = ''
    notice.value = ''
}

function prepareCatalogTest() {
    if (!canPrepareCatalog.value) {
        return
    }
    testDraft.catalog_id = catalog.value.id
    testDraft.question_count = Math.min(Number(testDraft.question_count) || 10, catalogReadyCount.value)
    if (!testDraft.title.trim()) {
        testDraft.title = `${catalog.value.title} – Test`
    }
    section.value = 'tests'
    preparingTest.value = true
    notice.value = ''
}

async function saveCatalog() {
    await task('catalog', async () => {
        const payload = {
            title: catalog.value.title,
            questions: catalog.value.questions
        }
        const {
            data
        } = catalog.value.id ? await axios.patch(updateCatalog.url(catalog.value.id), payload) :
            await axios.post(createCatalog.url(), payload)
        if (data.catalog) {
            catalog.value = JSON.parse(JSON.stringify(data.catalog))
            rememberCatalog(data.catalog)
        }
        notice.value = 'Katalog gespeichert. Entwürfe bleiben bis zu deiner Prüfung für Tests gesperrt.'
    })
}

async function readSourceFile(event) {
    const file = event.target.files?.[0]
    if (file) {
        try {
            importText.value = await file.text()
            importFormat.value = file.name.toLowerCase().endsWith('.json') ? 'json' : 'markdown'
            importPreview.value = null
        } catch {
            error.value = 'Die Datei konnte nicht gelesen werden. Bitte erneut auswählen.'
        }
    }
}

async function previewSource() {
    await task('import', async () => {
        const text = importText.value
        const format = importFormat.value
        const generate = generateWithAi.value
        const {
            data
        } = await axios.post(previewImport.url(), {
            text,
            format,
            generate
        })
        if (text === importText.value && format === importFormat.value && generate === generateWithAi
            .value) {
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

function adoptImport() {
    const ids = new Set(catalog.value.questions.map((question) => question.id))
    const imported = JSON.parse(JSON.stringify(importPreview.value.questions)).map((question) => {
        if (ids.has(question.id)) {
            question.id = crypto.randomUUID()
        }
        ids.add(question.id)
        return question
    })
    catalog.value.questions = [...catalog.value.questions, ...imported]
    importPreview.value = null
    showImport.value = false
    notice.value =
        'Fragen in den bearbeitbaren Katalog übernommen. Bitte Inhalt und Lösungen prüfen und anschließend speichern.'
}

async function readRosterFile(event) {
    const file = event.target.files?.[0]
    if (file) {
        try {
            rosterCsv.value = await file.text()
            rosterFileName.value = file.name
            roster.value = null
        } catch {
            error.value = 'Die CSV-Datei konnte nicht gelesen werden. Bitte erneut auswählen.'
        }
    }
}

async function previewRoster() {
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
            duration_seconds: Math.round(Number(durationMinutes) * 60),
            roster_csv: rosterCsv.value
        })
        publishDialog.value = false
        await loadIndex()
        if (data.test) {
            await selectTest(data.test.id)
        }
        notice.value =
            'Test freigegeben. Die Schüler können den Warteraum öffnen; du startest den Countdown.'
    })
}

async function selectTest(id) {
    preparingTest.value = false
    selectedTestId.value = id
    selectedTest.value = null
    attempts.value = []
    invitationPreview.value = null
    expandedAttempt.value = null
    startDialog.value = false
    mailDialog.value = false
    releaseMode.value = null
    await refreshMonitor()
}

async function refreshMonitor() {
    if (!selectedTestId.value || monitorLoading) {
        return
    }
    monitorLoading = true
    const id = selectedTestId.value
    try {
        const {
            data
        } = await axios.get(showTest.url(id))
        if (disposed || selectedTestId.value !== id) {
            return
        }
        selectedTest.value = data.test
        attempts.value = data.attempts ?? []
        if (data.server_now) {
            serverOffset.value = Date.parse(data.server_now) - Date.now()
        }
        monitorError.value = ''
    } catch (failure) {
        if (selectedTestId.value === id) {
            monitorError.value = requestError(failure)
        }
    } finally {
        monitorLoading = false
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

async function previewInvitations() {
    await task('invitations', async () => {
        const {
            data
        } = await axios.get(invitations.url(selectedTestId.value))
        invitationPreview.value = data
    })
}

async function sendMail() {
    await task('mail', async () => {
        const {
            data
        } = await axios.post(sendInvitations.url(selectedTestId.value))
        mailDialog.value = false
        const preview = await axios.get(invitations.url(selectedTestId.value))
        invitationPreview.value = preview.data
        notice.value =
            `${data.sent ?? 0} Einladungen versandt; ${data.skipped ?? 0} bereits versandte Einladungen übersprungen.`
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
        notice.value = solutions ? 'Ergebnisse und Lösungen freigegeben.' : 'Ergebnisse freigegeben.'
    })
}

function dateLabel(value) {
    return value ? new Date(value).toLocaleString('de-AT', {
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

.mc-sections {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    border-bottom: 1px solid #dce2ea;
    padding: 0 0 12px;
    margin-bottom: 20px;
}

.mc-sections :deep(.v-btn) {
    text-transform: none;
    letter-spacing: 0;
}

.mc-guide {
    padding: 13px 16px;
    border-left: 3px solid #6e96b5;
    background: #f1f5f8;
    border-radius: 4px;
}

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
.mc-catalog-card { display: flex; flex-direction: column; }
.mc-catalog-card :deep(.v-card-text) { flex: 1; }
.mc-catalog-card h3 { font-size: 1.08rem; overflow-wrap: anywhere; }

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

.mc-picker {
    flex: 1 1 260px;
    max-width: 520px;
    min-width: 180px;
}

.mc-format {
    flex: 0 1 240px;
    min-width: 180px;
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

.mc-file {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: .86rem;
    flex-wrap: wrap;
}

.mc-file input {
    max-width: 100%;
    font-size: .85rem;
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

@media (max-width: 1200px) {
    .mc-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .mc-form-grid {
        grid-template-columns: 1fr;
    }

    .mc-import {
        padding: 14px;
    }

    .mc-picker {
        max-width: none;
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

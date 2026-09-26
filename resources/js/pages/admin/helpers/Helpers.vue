<template>
    <v-container fluid class="helpers-page ma-0 w-100 pa-2">
        <AdminPageHeader class="mb-3" location="Helpers" :section="activePanelLabel" />

        <v-sheet class="helpers-nav mb-2">
            <div class="helpers-nav__sections" role="group" aria-label="Helpers-Bereiche">
                <v-btn
                    variant="flat"
                    class="helpers-nav__button"
                    :class="{ 'v-btn--active': activePanel === 'klassensprecherwahl' }"
                    :color="activePanel === 'klassensprecherwahl' ? 'primary' : undefined"
                    :aria-pressed="activePanel === 'klassensprecherwahl'"
                    prepend-icon="mdi-account-group-outline"
                    @click="selectPanel('klassensprecherwahl')">
                    <span class="helpers-nav__button-copy">
                        <span>Klassensprecherwahl</span>
                        <span class="helpers-nav__button-meta">{{ selectedSchoolyearLabel }}</span>
                    </span>
                </v-btn>
                <v-btn
                    variant="flat"
                    class="helpers-nav__button"
                    :class="{ 'v-btn--active': activePanel === 'matura' }"
                    :color="activePanel === 'matura' ? 'primary' : undefined"
                    :aria-pressed="activePanel === 'matura'"
                    prepend-icon="mdi-school-outline"
                    @click="selectPanel('matura')">
                    Matura
                </v-btn>
            </div>
        </v-sheet>

        <v-sheet class="helpers-subnav mb-3">
            <div class="helpers-subnav__sections" role="group" :aria-label="`${activePanelLabel}-Auswahl`">
                <v-btn
                    v-for="selection in 2"
                    :key="selection"
                    variant="flat"
                    class="helpers-subnav__button"
                    :class="{ 'v-btn--active': activeSelection === selection }"
                    :color="activeSelection === selection ? 'primary' : undefined"
                    :aria-pressed="activeSelection === selection"
                    @click="selectSelection(selection)">
                    {{ selection === 1 ? 'Überblick' : 'Auswahl 2' }}
                </v-btn>
            </div>
        </v-sheet>

        <v-sheet class="helpers-content">
            <template v-if="activePanel === 'klassensprecherwahl' && activeSelection === 1">
                <h2 class="text-h6 mb-3">Klassen · {{ selectedSchoolyearLabel }}</h2>
                <p v-if="!overviewSchoolyearId" class="mb-0">Bitte zuerst ein Schuljahr auswählen.</p>
                <p v-else-if="classesLoading" class="mb-0">Klassen werden geladen …</p>
                <p v-else-if="classesError" class="mb-0">Die Klassen konnten nicht geladen werden.</p>
                <template v-else>
                    <div class="helpers-class-toolbar mb-3">
                        <p class="helpers-class-count mb-0" aria-live="polite">{{ classes.length }} {{ classes.length === 1 ? 'Klasse' : 'Klassen' }}</p>
                        <div v-if="classes.length" class="helpers-class-actions">
                            <v-btn size="small" variant="tonal" @click="selectAllClasses">Alle auswählen</v-btn>
                            <v-btn size="small" variant="text" @click="deselectAllClasses">Alle abwählen</v-btn>
                            <v-btn size="small" color="primary" :disabled="selectedClasses.length === 0" @click="openAnnouncementDialog">Ausschreiben</v-btn>
                        </div>
                    </div>
                    <p v-if="classes.length === 0" class="mb-0">Der Schülerimport für dieses Schuljahr enthält noch keine Klassen.</p>
                    <table v-else class="helpers-classes-table">
                        <thead>
                            <tr>
                                <th scope="col" class="helpers-class-select-heading">Auswahl</th>
                                <th scope="col">Klasse</th>
                                <th scope="col"><abbr title="Schülerinnen und Schüler">S/S</abbr></th>
                                <th scope="col">Ausgeschrieben</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="schoolClass in classes" :key="schoolClass.name">
                                <td>
                                    <input
                                        type="checkbox"
                                        :aria-label="`Klasse ${schoolClass.name} auswählen`"
                                        :checked="selectedClasses.includes(schoolClass.name)"
                                        :disabled="schoolClass.announced"
                                        @change="toggleClass(schoolClass.name)">
                                </td>
                                <td>{{ formatClassName(schoolClass) }}</td>
                                <td>{{ schoolClass.student_count }}</td>
                                <td>
                                    <span
                                        class="helpers-election-status"
                                        :class="schoolClass.announced ? 'helpers-election-status--announced' : 'helpers-election-status--pending'"
                                        role="status"
                                        :aria-label="schoolClass.announced ? `${schoolClass.name}: ausgeschrieben` : `${schoolClass.name}: nicht ausgeschrieben`">
                                        {{ schoolClass.announced ? '✓ Ausgeschrieben' : '✕ Offen' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </template>
        </v-sheet>

        <v-dialog v-if="announcementDialog" v-model="announcementDialog" persistent max-width="800">
            <v-card>
                <v-card-title>Wahlausschreibung · Entwurf</v-card-title>
                <v-card-text>
                    <p class="mb-3">Der Klassenvorstand bzw. die Klassenvorständin legt den Wahltermin fest. Wahltag, Wahlzeit und Wahlort sind vor einer tatsächlichen Ausschreibung einzutragen.</p>
                    <div class="helpers-announcement-date mb-3">
                        <label for="helpers-announcement-date">Ausschreibungsdatum</label>
                        <input id="helpers-announcement-date" v-model="announcementDate" type="date">
                        <p v-if="announcementDate" class="mb-0" aria-live="polite">Frühester Wahltermin bei Ausschreibung an diesem Datum: {{ earliestElectionDateLabel }} (14 Kalendertage später).</p>
                        <p v-else class="mb-0" aria-live="polite">Bitte ein Ausschreibungsdatum wählen, um den frühesten Wahltermin zu berechnen.</p>
                    </div>
                    <pre class="helpers-announcement-text">{{ announcementText }}</pre>
                    <p class="mt-3 mb-0">
                        Rechtsgrundlagen:
                        <a href="https://www.ris.bka.gv.at/eli/bgbl/1986/472/P59a/NOR40019427" target="_blank" rel="noopener noreferrer">§ 59a SchUG</a>,
                        <a href="https://www.ris.bka.gv.at/eli/bgbl/1993/388/P7/NOR12124909" target="_blank" rel="noopener noreferrer">§ 7 Wahl der Schülervertreter</a>
                        und <a href="https://www.ris.bka.gv.at/GeltendeFassung.wxe?Abfrage=Bundesnormen&Gesetzesnummer=10009897" target="_blank" rel="noopener noreferrer">die Wahlverordnung</a>.
                    </p>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="announcementDialog = false">Schließen</v-btn>
                    <v-btn color="primary" disabled>Per E-Mail versenden</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script>
import { mapState } from 'pinia'
import { classes as helperClasses } from '@/actions/App/Http/Controllers/Admin/SchoolyearController'
import { useAdminStore } from '@/stores/admin/AdminStore'
import AdminPageHeader from '@/pages/admin/components/AdminPageHeader.vue'

export default {
    components: { AdminPageHeader },

    data() {
        return {
            classes: [],
            selectedClasses: [],
            announcementDialog: false,
            announcementClasses: [],
            announcementDate: '',
            classesLoading: false,
            classesError: false,
            classesRequestId: 0,
        }
    },

    computed: {
        ...mapState(useAdminStore, ['config', 'selected_schoolyear']),
        activePanel() {
            return this.$route.query.panel === 'matura' ? 'matura' : 'klassensprecherwahl'
        },
        activePanelLabel() {
            return this.activePanel === 'matura' ? 'Matura' : 'Klassensprecherwahl'
        },
        activeSelection() {
            return this.$route.query.selection === '2' ? 2 : 1
        },
        selectedSchoolyearLabel() {
            return (this.selected_schoolyear || this.config?.selected_schoolyear)?.name || 'Kein Schuljahr ausgewählt'
        },
        overviewSchoolyearId() {
            if (this.activePanel !== 'klassensprecherwahl' || this.activeSelection !== 1) return null

            return (this.selected_schoolyear || this.config?.selected_schoolyear)?.id ?? null
        },
        announcementDateLabel() {
            if (!this.announcementDate) return '[Ausschreibungsdatum eintragen]'

            const [year, month, day] = this.announcementDate.split('-')

            return `${day}.${month}.${year}`
        },
        earliestElectionDateLabel() {
            if (!this.announcementDate) return '[Ausschreibungsdatum eintragen]'

            const [year, month, day] = this.announcementDate.split('-').map(Number)
            const earliestDate = new Date(year, month - 1, day + 14)

            return earliestDate.toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        announcementText() {
            const classes = this.announcementClasses.join(', ')

            return [
                `Wahlausschreibung zur Wahl der Klassensprecherinnen und Klassensprecher sowie ihrer Stellvertretungen\nSchuljahr: ${this.selectedSchoolyearLabel}\nKlassen: ${classes}\nAusschreibungsdatum (geplant): ${this.announcementDateLabel}`,
                `Wahltermin: wird vom Klassenvorstand bzw. von der Klassenvorständin festgelegt. Wahltag: [eintragen], Wahlzeit: [eintragen], Wahlort: [eintragen].\nFrühester Wahltermin bei Ausschreibung am geplanten Datum: ${this.earliestElectionDateLabel} (14 Kalendertage später).`,
                'Gemäß § 59a Abs. 1 bis 4 Schulunterrichtsgesetz (SchUG) und § 1 der Verordnung „Wahl der Schülervertreter“ wählen die Schülerinnen und Schüler jeder genannten Klasse ihre Klassenvertretung und Stellvertretung in gleicher, unmittelbarer, geheimer und persönlicher Wahl. Wahlberechtigt und wählbar sind die Schülerinnen und Schüler der jeweiligen Klasse.',
                'Die Wahl erfolgt unter Leitung der Schulleitung oder einer beauftragten Lehrkraft (§ 59a Abs. 5 SchUG; § 9 der Wahlverordnung). Wahlberechtigte können bis spätestens drei Schultage vor der Wahl Kandidatinnen und Kandidaten vorschlagen. Die vorgeschlagene Person muss den Vorschlag annehmen (§ 8 der Wahlverordnung). Vor dem Wahltag erhalten die Wahlberechtigten Gelegenheit, die Kandidierenden kennenzulernen (§ 59a Abs. 5 SchUG).',
                'Die Stimmabgabe erfolgt persönlich am Wahlort mit einheitlichen Stimmzetteln und unter Wahrung des Wahlgeheimnisses. Jede wahlberechtigte Person gibt einen Stimmzettel ab und reiht zwei Personen für die Klassenvertretung und Stellvertretung (§ 59a Abs. 6 SchUG; §§ 10 und 11 der Wahlverordnung).',
                'Die Stimmen werden unmittelbar nach der Wahl durch die Wahlleitung gemeinsam mit zwei wahlberechtigten Wahlzeugen geprüft und ausgezählt. Für die Klassenvertretung ist eine Mehrheit der Erstplatzierungen erforderlich; andernfalls folgt eine Stichwahl. Die Stellvertretung richtet sich nach den Wahlpunkten des ersten Wahlgangs. Das Ergebnis wird im Wahlprotokoll festgehalten und in der Schule kundgemacht (§ 59a Abs. 7 bis 9 SchUG; § 12 der Wahlverordnung). Die Wahl kann innerhalb einer Woche ab Kundmachung angefochten werden (§ 13 der Wahlverordnung).',
                'Die tatsächliche Wahlausschreibung muss den vollständigen Wahltermin enthalten und von der Schulleitung spätestens zwei Wochen vor der Wahl in der Schule angeschlagen werden (§ 7 der Wahlverordnung).',
            ].join('\n\n')
        },
    },

    watch: {
        overviewSchoolyearId: {
            immediate: true,
            handler(schoolyearId) {
                this.loadClasses(schoolyearId)
            },
        },
    },

    methods: {
        formatClassName(schoolClass) {
            const { name, variants } = schoolClass
            if (variants.length === 1) return variants[0]

            const prefix = `${name}-`
            if (!variants.every((variant) => variant.startsWith(prefix))) return variants.join(' / ')

            return `${name}-${variants.map((variant) => variant.slice(prefix.length)).join('/')}`
        },
        selectAllClasses() {
            this.selectedClasses = this.classes.filter((schoolClass) => !schoolClass.announced).map((schoolClass) => schoolClass.name)
        },
        deselectAllClasses() {
            this.selectedClasses = []
        },
        toggleClass(name) {
            if (this.classes.find((schoolClass) => schoolClass.name === name)?.announced) return

            if (this.selectedClasses.includes(name)) {
                this.selectedClasses = this.selectedClasses.filter((selectedClass) => selectedClass !== name)
            } else {
                this.selectedClasses = [...this.selectedClasses, name]
            }
        },
        openAnnouncementDialog() {
            this.announcementClasses = this.classes
                .filter((schoolClass) => this.selectedClasses.includes(schoolClass.name) && !schoolClass.announced)
                .map((schoolClass) => this.formatClassName(schoolClass))
            if (this.announcementClasses.length === 0) return

            const today = new Date()
            this.announcementDate = [
                today.getFullYear(),
                String(today.getMonth() + 1).padStart(2, '0'),
                String(today.getDate()).padStart(2, '0'),
            ].join('-')
            this.announcementDialog = true
        },
        async loadClasses(schoolyearId) {
            const requestId = ++this.classesRequestId
            this.classes = []
            this.selectedClasses = []
            this.classesError = false
            this.classesLoading = Boolean(schoolyearId)

            if (!schoolyearId) return

            try {
                const response = await axios.get(helperClasses.url(), { params: { schoolyear_id: schoolyearId } })
                if (requestId === this.classesRequestId) {
                    this.classes = response.data.data
                }
            } catch {
                if (requestId === this.classesRequestId) {
                    this.classesError = true
                }
            } finally {
                if (requestId === this.classesRequestId) {
                    this.classesLoading = false
                }
            }
        },
        selectPanel(panel) {
            if (this.activePanel === panel) return

            const query = { ...this.$route.query }
            if (panel === 'matura') {
                query.panel = 'matura'
            } else {
                delete query.panel
            }
            delete query.selection

            this.$router.push({ path: this.$route.path, query })
        },
        selectSelection(selection) {
            if (this.activeSelection === selection) return

            const query = { ...this.$route.query }
            if (selection === 2) {
                query.selection = '2'
            } else {
                delete query.selection
            }

            this.$router.push({ path: this.$route.path, query })
        },
    },
}
</script>

<style scoped>
.helpers-page {
    background: var(--admin-page-background, #f7f8fa);
    min-height: 100vh;
}

.helpers-nav {
    border-radius: 16px;
    border: 1px solid var(--admin-page-border, #e7e9ef);
    background: var(--admin-page-surface, #ffffff);
    box-shadow: var(--admin-page-shadow);
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.helpers-nav__sections {
    display: flex;
    flex-wrap: wrap;
    row-gap: 6px;
    width: 100%;
    min-width: 0;
}

.helpers-nav__button {
    min-height: 56px !important;
    height: auto !important;
    border-radius: 0;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
}

.helpers-nav__button:first-child {
    border-start-start-radius: 4px;
    border-end-start-radius: 4px;
    border-inline-end: 1px solid rgba(0, 0, 0, 0.12);
}

.helpers-nav__button:last-child {
    border-start-end-radius: 4px;
    border-end-end-radius: 4px;
}

.helpers-nav__button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.helpers-nav__button-meta {
    color: var(--admin-page-muted, #65716c);
    background: #f2f4f7;
    border: 1px solid var(--admin-page-border, #e7e9ef);
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 0.76rem;
    font-weight: 700;
}

.helpers-subnav {
    border-radius: 16px;
    border: 1px solid var(--admin-page-border, #e7e9ef);
    background: var(--admin-page-surface, #ffffff);
    color: var(--admin-page-text, #25332c);
    box-shadow: var(--admin-page-shadow);
    padding: 8px;
}

.helpers-subnav__sections {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 0;
    width: 100%;
}

.helpers-subnav__button {
    min-height: 44px !important;
    height: auto !important;
    border-radius: 0;
    font-weight: 650;
    text-transform: none;
    letter-spacing: 0;
    border-inline-end: 1px solid rgba(0, 0, 0, 0.12);
}

.helpers-subnav__button:first-child { border-radius: 4px 0 0 4px; }
.helpers-subnav__button:last-child { border-inline-end: 0; border-radius: 0 4px 4px 0; }

.helpers-content {
    min-height: 45vh;
    border: 1px solid var(--admin-page-border, #e7e9ef);
    border-radius: 16px;
    background: var(--admin-page-surface, #ffffff);
    box-shadow: var(--admin-page-shadow);
    padding: 20px;
}

.helpers-classes-table {
    width: auto;
    border-collapse: collapse;
    text-align: left;
}

.helpers-class-count {
    color: var(--admin-page-muted, #65716c);
    font-weight: 700;
}

.helpers-class-toolbar,
.helpers-class-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 16px;
}

.helpers-class-toolbar {
    justify-content: space-between;
}

.helpers-classes-table .helpers-class-select-heading {
    width: 96px;
}

.helpers-classes-table th:nth-child(2),
.helpers-classes-table td:nth-child(2) {
    min-width: 80px;
    width: 100px;
    white-space: nowrap;
}

.helpers-classes-table abbr {
    text-decoration: none;
}

.helpers-election-status {
    display: inline-block;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
}

.helpers-election-status--announced {
    color: #16803c;
    background: #e5f4e9;
}

.helpers-election-status--pending {
    color: #c62828;
    background: #fce9e9;
}

.helpers-announcement-text {
    max-height: 55vh;
    overflow: auto;
    white-space: pre-wrap;
    font: inherit;
    line-height: 1.5;
}

.helpers-announcement-date {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
}

.helpers-announcement-date input {
    border: 1px solid var(--admin-page-border, #e7e9ef);
    border-radius: 6px;
    padding: 6px 10px;
    font: inherit;
}

.helpers-classes-table input[type='checkbox'] {
    width: 18px;
    height: 18px;
    vertical-align: middle;
}

.helpers-classes-table th,
.helpers-classes-table td {
    border-bottom: 1px solid var(--admin-page-border, #e7e9ef);
    padding: 10px 12px;
}

@media (max-width: 480px) {
    .helpers-nav__button,
    .helpers-subnav__button { width: 100%; min-width: 0; }
    .helpers-nav__button:first-child,
    .helpers-subnav__button { border-inline-end: 0; }
}
</style>

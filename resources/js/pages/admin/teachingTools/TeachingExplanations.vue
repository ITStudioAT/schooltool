<template>
    <section class="explanations">
        <template v-if="currentExplanation">
            <div class="explanation-detail">
            <RouterLink :to="`/admin/teaching-tools/explanations/${currentExplanation.category}`" class="explanations__back">← Zur Kategorie {{ explanationCategoryTitle }}</RouterLink>
            <header class="explanation-detail__heading">
                <h1>{{ currentExplanation.title }}</h1>
                <p class="explanation-detail__summary">{{ currentExplanation.description }}<template v-if="currentExplanation.category === 'number-systems'"> Mit eigener Zahl, Weiter, Zurück und Neustart.</template></p>
                <figure v-if="sharing?.published && sharing.invitation?.url && sharing.invitation?.qr_data_url" class="explanation-qr">
                    <img class="explanation-detail__qr" :src="sharing.invitation.qr_data_url" alt="QR-Code zur freigegebenen Erklärung" width="192" height="192" />
                    <figcaption>Mit dem Handy scannen</figcaption>
                </figure>
            </header>
            <p v-if="error" class="explanation-error" role="alert">{{ error }}</p>
            <div class="explanation-workbench">
                <aside class="explanation-preview" aria-labelledby="explanation-preview-title">
                    <div v-if="currentExplanation.category === 'number-systems'" class="explanation-preview__example" role="img" :aria-label="`Beispielumrechnung: ${currentExplanation.example} ${currentExplanation.sourceSystem} entspricht ${currentExplanation.result} ${currentExplanation.system}`">
                        <div class="explanation-preview__decimal" :class="{ 'explanation-preview__decimal--long': String(currentExplanation.example).length > 4 }"><span>{{ currentExplanation.sourceSystem }}</span><strong>{{ currentExplanation.sourceNotation.prefix }}{{ currentExplanation.example }}<sub v-if="currentExplanation.sourceNotation.subscript">{{ currentExplanation.sourceNotation.subscript }}</sub></strong></div>
                        <span class="explanation-preview__connector" aria-hidden="true"><v-icon icon="mdi-arrow-down" size="22" /></span>
                        <div class="explanation-preview__binary"><span>{{ currentExplanation.system }}</span><div><span v-if="currentExplanation.prefix" class="explanation-preview__prefix">{{ currentExplanation.prefix }}</span><b v-for="(digit, index) in currentExplanation.result" :key="index">{{ digit }}</b><sub v-if="currentExplanation.subscript">{{ currentExplanation.subscript }}</sub></div></div>
                    </div>
                    <div v-else class="explanation-preview__network" aria-hidden="true"><v-icon icon="mdi-lan-connect" size="96" /></div>
                    <div class="explanation-preview__copy"><h2 id="explanation-preview-title">{{ currentExplanation.category === 'networks' ? 'Schüleransicht ansehen' : 'Erst selbst ausprobieren' }}</h2>
                        <p>Teste die Erklärung so, wie deine Schüler:innen sie sehen.</p>
                        <button type="button" class="explanation-action explanation-preview__button" @click="previewOpened = true"><v-icon icon="mdi-eye-outline" size="20" aria-hidden="true" />Schüleransicht testen</button>
                    </div>
                </aside>
            <p v-if="loading" class="explanation-detail__loading" role="status">Freigabe wird geladen …</p>
            <section v-else class="explanation-detail__panel" aria-labelledby="explanation-sharing-title">
                <header class="explanation-sharing-heading"><div><h2 id="explanation-sharing-title">Teilen</h2><p>Lege fest, wer die Erklärung öffnen darf.</p></div>
                    <span class="explanation-detail__status" :class="{ 'explanation-detail__status--published': sharing?.published }"><span aria-hidden="true"></span>{{ sharing?.published ? 'Veröffentlicht' : 'Nicht veröffentlicht' }}</span>
                </header>
                <p v-if="sharing && !sharing.available">Die Freigabefunktion ist noch nicht eingerichtet. Die Schüleransicht kannst du bereits testen.</p>
                <p v-if="sharing?.published" class="explanation-active-access">Aktiv gespeichert: <strong>{{ accessLabel(sharing.access_mode) }}</strong></p>
                <fieldset class="explanation-access" :disabled="busy || !sharing?.available"><legend>Zugang auswählen und speichern</legend>
                    <div class="explanation-access__options">
                        <label class="explanation-access__option" :class="{ 'explanation-access__option--selected': accessMode === 'public' }">
                            <input v-model="accessMode" type="radio" value="public" name="explanation-access" aria-label="Ohne Anmeldung" />
                            <span class="explanation-access__top"><v-icon icon="mdi-web" size="24" aria-hidden="true" /><span class="explanation-access__check" aria-hidden="true"><v-icon icon="mdi-check" size="14" /></span></span>
                            <strong>Ohne Anmeldung</strong><span>Alle mit dem Link können die Erklärung ansehen.</span>
                        </label>
                        <label class="explanation-access__option" :class="{ 'explanation-access__option--selected': accessMode === 'authenticated' }">
                            <input v-model="accessMode" type="radio" value="authenticated" name="explanation-access" aria-label="Nur mit Schooltool-Anmeldung" />
                            <span class="explanation-access__top"><v-icon icon="mdi-shield-account-outline" size="24" aria-hidden="true" /><span class="explanation-access__check" aria-hidden="true"><v-icon icon="mdi-check" size="14" /></span></span>
                            <strong>Nur mit Schooltool-Anmeldung</strong><span>Der Link öffnet sich nach der Anmeldung.</span>
                        </label>
                    </div>
                </fieldset>
                <div class="explanation-publication">
                    <div v-if="hasUnsavedAccess" class="explanation-unsaved" role="status"><strong>Änderung noch nicht gespeichert.</strong> Aktuell gilt weiterhin „{{ accessLabel(sharing.access_mode) }}“. Beim Speichern werden der bisherige Link und QR-Code ungültig und durch einen neuen Link und QR-Code ersetzt.</div>
                    <p v-if="successMessage" class="explanation-success" role="status">{{ successMessage }}</p>
                    <p v-if="!sharing?.published"><v-icon icon="mdi-link-variant" size="20" aria-hidden="true" /><span>Nach dem Veröffentlichen stehen Link und QR-Code bereit.</span></p>
                    <div class="explanation-detail__actions">
                        <button type="button" class="explanation-action explanation-action--primary" :disabled="busy || !sharing?.available || (sharing?.published && !hasUnsavedAccess)" @click="publishExplanation">{{ busy ? 'Wird gespeichert …' : sharing?.published ? 'Zugangsänderung speichern' : 'Veröffentlichen' }}</button>
                        <button v-if="sharing?.published" type="button" class="explanation-action" :disabled="busy" @click="revokeExplanation">Freigabe widerrufen</button>
                    </div>
                </div>
            </section>
                <section v-if="sharing?.published && sharing.invitation" class="explanation-distribution" aria-label="Erklärung weitergeben">
                    <p>Aktueller Zugang: <strong>{{ sharing.access_mode === 'public' ? 'Ohne Anmeldung' : 'Nur mit Schooltool-Anmeldung' }}</strong></p>
                    <p v-if="sharing.invitation.local_only" class="explanation-local-note">Dieser lokale Link ist nur auf diesem Rechner erreichbar.</p>
                    <label class="explanation-detail__link">Link zur Schüleransicht<input :value="sharing.invitation.url" readonly @focus="$event.target.select()" /></label>
                    <div class="explanation-detail__actions">
                        <button type="button" class="explanation-action" @click="copyLink"><v-icon icon="mdi-content-copy" size="18" aria-hidden="true" />{{ copied ? 'Link kopiert' : 'Link kopieren' }}</button>
                    </div>
                </section>
            </div>
            </div>
            <v-dialog v-model="previewOpened" fullscreen aria-label="Schüleransicht testen">
                <v-card class="explanation-student-preview">
                    <header class="explanation-preview-toolbar"><span>Schüleransicht</span><button type="button" class="explanation-action" aria-label="Erklärung schließen" title="Erklärung schließen" @click="previewOpened = false"><v-icon icon="mdi-close" size="22" aria-hidden="true" />Vorschau beenden</button></header>
                    <div class="explanation-preview-content"><component :is="teachingExplanationComponent(explanation)" v-if="previewOpened" /></div>
                </v-card>
            </v-dialog>
        </template>
        <template v-else-if="currentCategory">
            <RouterLink to="/admin/teaching-tools/explanations" class="explanations__back">← Zu den Kategorien</RouterLink>
            <header><h1>{{ currentCategory.title }}</h1></header>
            <ul class="explanations__grid" aria-label="Verfügbare Erklärungen">
                <li v-for="item in categoryExplanations" :key="item.key" class="explanation-card">
                    <span class="explanation-card__availability" :class="`explanation-card__availability--${cardAvailability(cardShares[item.key]?.sharing)}`" role="img" :aria-label="`${item.title}: ${cardAvailabilityLabel(cardShares[item.key]?.sharing)}`" :title="cardAvailabilityLabel(cardShares[item.key]?.sharing)"></span>
                    <RouterLink :to="`/admin/teaching-tools/explanations/${item.key}`" class="explanation-tile" :aria-label="`${item.title} öffnen`">
                        <div v-if="item.category === 'number-systems'" class="explanation-tile__preview" aria-hidden="true"><span>{{ item.sourceNotation.prefix }}{{ item.example }}<sub v-if="item.sourceNotation.subscript">{{ item.sourceNotation.subscript }}</sub></span><span class="explanation-tile__arrow">→</span><span>{{ item.prefix }}{{ item.result }}<sub v-if="item.subscript">{{ item.subscript }}</sub></span></div>
                        <div v-else class="explanation-tile__preview" aria-hidden="true"><v-icon icon="mdi-lan-connect" size="64" /></div>
                        <div class="explanation-tile__copy"><v-icon :icon="currentCategory.icon" size="26" aria-hidden="true" /><h2>{{ item.title }}</h2>
                            <p>{{ item.description }}</p>
                            <span class="explanation-tile__open">Erklärung öffnen <span aria-hidden="true">↗</span></span>
                        </div>
                    </RouterLink>
                    <div class="explanation-card__sharing">
                        <button type="button" class="explanation-action" :aria-label="`${item.title}: ${cardShares[item.key]?.sharing && !cardShares[item.key].sharing.published ? 'Link einrichten' : 'Link kopieren'}`" :disabled="cardShares[item.key]?.busy" @click="copyCardLink(item.key)">
                            <v-icon icon="mdi-content-copy" size="18" aria-hidden="true" />
                            {{ cardShares[item.key]?.busy ? 'Wird geladen …' : cardShares[item.key]?.copied ? 'Link kopiert' : cardShares[item.key]?.sharing && !cardShares[item.key].sharing.published ? 'Link einrichten' : 'Link kopieren' }}
                        </button>
                        <p v-if="cardShares[item.key]?.sharing" class="explanation-card__access">{{ cardShares[item.key].sharing.published ? accessLabel(cardShares[item.key].sharing.access_mode) : 'Noch nicht veröffentlicht' }}</p>
                        <p v-if="cardShares[item.key]?.sharing?.invitation?.local_only" class="explanation-local-note">Dieser lokale Link ist nur auf diesem Rechner erreichbar.</p>
                        <span class="explanation-card__feedback" role="status">{{ cardShares[item.key]?.copied ? 'Link kopiert' : '' }}</span>
                        <p v-if="cardShares[item.key]?.error" class="explanation-error" role="alert">{{ cardShares[item.key].error }}</p>
                        <label v-if="cardShares[item.key]?.manualUrl" class="explanation-detail__link">Link zum Kopieren<input :value="cardShares[item.key].manualUrl" readonly @focus="$event.target.select()" /></label>
                    </div>
                </li>
            </ul>
        </template>
        <template v-else>
            <header><h1>Erklärungen</h1><p>Schritt für Schritt verstehen. Mit eigenen Zahlen ausprobieren.</p></header>
            <ul class="explanations__grid explanations__grid--categories" aria-label="Verfügbare Kategorien">
                <li v-for="item in teachingExplanationCategories" :key="item.key">
                    <RouterLink :to="`/admin/teaching-tools/explanations/${item.key}`" class="explanation-tile" :aria-label="`${item.title} öffnen`">
                        <div class="explanation-tile__copy"><v-icon :icon="item.icon" size="24" aria-hidden="true" /><h2>{{ item.title }}</h2>
                            <p>{{ teachingExplanations.filter(explanation => explanation.category === item.key).length }} {{ teachingExplanations.filter(explanation => explanation.category === item.key).length === 1 ? 'Erklärung' : 'Erklärungen' }}</p>
                            <span class="explanation-tile__open">Öffnen <span aria-hidden="true">→</span></span>
                        </div>
                    </RouterLink>
                </li>
            </ul>
        </template>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { show, publish, revoke } from '@/actions/App/Http/Controllers/Admin/TeachingExplanationController'
import { teachingExplanations, teachingExplanationCategories, teachingExplanationComponent } from '@/helpers/teachingExplanations'

const props = defineProps({ category: { type: String, default: null }, explanation: { type: String, default: null } })
const router = useRouter()
const cardShares = ref({})
const currentExplanation = computed(() => teachingExplanations.find(item => item.key === props.explanation))
const explanationCategoryTitle = computed(() => teachingExplanationCategories.find(item => item.key === currentExplanation.value?.category)?.title || '')
const currentCategory = computed(() => teachingExplanationCategories.find(item => item.key === props.category))
const categoryExplanations = computed(() => teachingExplanations.filter(item => item.category === props.category))
const previewOpened = ref(false)
const sharing = ref(null)
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const accessMode = ref('authenticated')
const copied = ref(false)
const successMessage = ref('')
let sharingLoadVersion = 0
const hasUnsavedAccess = computed(() => Boolean(sharing.value?.published && accessMode.value !== sharing.value.access_mode))
function accessLabel(mode) { return mode === 'public' ? 'Ohne Anmeldung' : 'Nur mit Schooltool-Anmeldung' }
function cardAvailability(sharing) {
    if (!sharing) return 'unknown'
    return sharing.available && sharing.published && sharing.invitation?.url ? 'available' : 'unavailable'
}
function cardAvailabilityLabel(sharing) {
    return { available: 'veröffentlicht', unavailable: 'nicht verfügbar', unknown: 'Status unbekannt' }[cardAvailability(sharing)]
}

async function loadCardShare(key) {
    cardShares.value[key] = { busy: true, sharing: null, copied: false, error: '', manualUrl: '' }
    const state = cardShares.value[key]
    try {
        const response = await window.axios.get(show.url(key))
        state.sharing = response.data
    } catch {
        state.error = 'Die Freigabe konnte nicht geladen werden. Mit „Link kopieren“ erneut versuchen.'
    } finally { state.busy = false }
}

async function copyCardLink(key) {
    const state = cardShares.value[key]
    if (!state || state.busy) return
    if (state.sharing && !state.sharing.published) {
        await router.push(`/admin/teaching-tools/explanations/${key}`)
        return
    }
    state.busy = true
    state.copied = false
    state.error = ''
    state.manualUrl = ''
    try {
        const response = await window.axios.get(show.url(key))
        state.sharing = response.data
        if (!state.sharing.published || !state.sharing.invitation?.url) {
            state.error = 'Die Erklärung ist nicht freigegeben. Bitte zuerst den Link einrichten.'
            return
        }
        try {
            await navigator.clipboard.writeText(state.sharing.invitation.url)
            state.copied = true
        } catch {
            state.manualUrl = state.sharing.invitation.url
            state.error = 'Bitte den Link im Textfeld markieren und kopieren.'
        }
    } catch {
        state.sharing = null
        state.error = 'Der aktuelle Link konnte nicht geladen werden. Bitte erneut versuchen.'
    } finally { state.busy = false }
}

watch(() => props.category, () => {
    cardShares.value = {}
    categoryExplanations.value.forEach(item => { void loadCardShare(item.key) })
}, { immediate: true })

async function loadSharing() {
    const version = ++sharingLoadVersion
    const key = props.explanation
    if (!currentExplanation.value) return
    loading.value = true
    error.value = ''
    try {
        const response = await window.axios.get(show.url(key))
        if (version !== sharingLoadVersion) return
        sharing.value = response.data
        accessMode.value = sharing.value.access_mode
    } catch (failure) {
        if (version !== sharingLoadVersion) return
        error.value = failure.response?.data?.message || 'Die Freigabe konnte nicht geladen werden.'
    } finally { if (version === sharingLoadVersion) loading.value = false }
}

async function changePublication(action, message) {
    const key = props.explanation
    busy.value = true
    error.value = ''
    successMessage.value = ''
    try {
        const response = await action()
        if (props.explanation !== key) return
        sharing.value = response.data
        accessMode.value = sharing.value.access_mode
        copied.value = false
        successMessage.value = message
    } catch (failure) {
        if (props.explanation !== key) return
        error.value = failure.response?.data?.message || 'Die Freigabe konnte nicht geändert werden.'
    } finally { busy.value = false }
}
function publishExplanation() { return changePublication(() => window.axios.post(publish.url(props.explanation), { access_mode: accessMode.value }), 'Zugang gespeichert. Der unten angezeigte Link und der QR-Code oben sind jetzt gültig.') }
function revokeExplanation() { return changePublication(() => window.axios.delete(revoke.url(props.explanation)), 'Freigabe widerrufen.') }
async function copyLink() {
    try { await navigator.clipboard.writeText(sharing.value.invitation.url); copied.value = true }
    catch { error.value = 'Bitte den Link im Textfeld markieren und kopieren.' }
}
watch(() => props.explanation, () => { previewOpened.value = false; sharing.value = null; successMessage.value = ''; void loadSharing() }, { immediate: true })
watch(accessMode, () => { successMessage.value = '' })
</script>

<style scoped>
.explanations { padding: 20px 8px 36px; color: #173458; }
.explanations h1 { font-size: 1.7rem; line-height: 1.25; }
.explanations header p { color: #50647c; margin-top: 8px; }
.explanations__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 310px), 360px)); gap: 24px; padding: 0; margin-top: 28px; list-style: none; }
.explanations__grid--categories { grid-template-columns: repeat(auto-fill, minmax(min(100%, 230px), 260px)); gap: 16px; margin-top: 20px; }
.explanations__grid--categories .explanation-tile__preview { min-height: 68px; font-size: 1.65rem; gap: 14px; }
.explanations__grid--categories .explanation-tile__copy { padding: 14px 16px; }
.explanations__grid--categories .explanation-tile h2 { font-size: 1rem; margin-left: 6px; }
.explanations__grid--categories .explanation-tile p { margin-top: 6px; font-size: .8rem; }
.explanations__grid--categories .explanation-tile__open { margin-top: 12px; font-size: .8rem; }
.explanation-active-access { margin-bottom: 18px; font-size: .88rem; color: #526780; }
.explanation-unsaved { padding: 14px 16px; margin-bottom: 18px; border: 1px solid #dfc579; border-radius: 10px; background: #fff8e4; color: #70551a; font-size: .85rem; line-height: 1.6; }
.explanation-success { color: #237353; }
.explanation-tile { display: block; overflow: hidden; color: inherit; background: #fff; border: 1px solid #c8d5e5; border-radius: 12px; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
.explanation-tile:hover { border-color: #1b62c5; box-shadow: 0 5px 16px #17345812; }
.explanation-tile:focus-visible, .explanations__back:focus-visible { outline: 3px solid #1b62c5; outline-offset: 4px; }
.explanation-tile__preview { display: flex; align-items: center; justify-content: center; gap: 20px; min-height: 132px; background: #eaf1fb; font-size: 2.4rem; font-weight: 750; font-variant-numeric: tabular-nums; }
.explanation-tile__preview sub { font-size: .8rem; margin-left: 2px; }
.explanation-tile__preview > span:last-child { color: #137865; }
.explanation-tile__arrow { color: #7c91aa; font-weight: 400; }
.explanation-tile__copy { padding: 22px; }
.explanation-tile h2 { display: inline; vertical-align: middle; margin-left: 10px; font-size: 1.2rem; }
.explanation-tile p { margin-top: 14px; color: #50647c; font-size: .95rem; line-height: 1.6; }
.explanation-tile__open { display: flex; justify-content: space-between; margin-top: 24px; font-weight: 650; color: #1b62c5; }
.explanation-card { position: relative; display: flex; flex-direction: column; overflow: hidden; border: 1px solid #c8d5e5; border-radius: 12px; background: #fff; }
.explanation-card__availability { position: absolute; top: 14px; right: 14px; z-index: 1; width: 14px; height: 14px; border: 2px solid #fff; border-radius: 50%; background: #778798; box-shadow: 0 0 0 1px #778798; pointer-events: none; }
.explanation-card__availability--available { background: #16864a; box-shadow: 0 0 0 1px #16864a, 0 0 9px #16864a66; }
.explanation-card__availability--unavailable { background: #c33434; box-shadow: 0 0 0 1px #c33434, 0 0 9px #c3343455; }
.explanation-card > .explanation-tile { flex: 1; border: 0; border-radius: 0; }
.explanation-card > .explanation-tile:focus-visible { outline-offset: -3px; }
.explanation-card__sharing { padding: 16px 22px; border-top: 1px solid #e0e7ef; }
.explanation-card__sharing .explanation-action { width: 100%; }
.explanation-card__access, .explanation-card__sharing .explanation-local-note { margin: 10px 0 0; font-size: .8rem; color: #526780; }
.explanation-card__feedback { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
.explanations__back { display: inline-flex; align-items: center; min-height: 44px; margin-bottom: 16px; color: #1b62c5; font-weight: 600; text-decoration: none; }
.explanation-detail { width: 100%; max-width: 1180px; margin: 0 auto; }
.explanation-detail .explanations__back { min-height: 32px; font-size: .85rem; font-weight: 600; color: #526780; margin-bottom: 14px; }
.explanation-detail__heading { display: grid; grid-template-columns: minmax(0, 1fr) auto; grid-template-rows: max-content 1fr; column-gap: 32px; align-items: start; margin-bottom: 28px; }
.explanation-detail__heading > h1, .explanation-detail__heading > p { grid-column: 1; }
.explanation-detail__heading h1 { font-size: clamp(1.9rem, 3vw, 2.5rem); font-weight: 750; letter-spacing: -.035em; line-height: 1.15; }
.explanations .explanation-detail__summary { max-width: 66ch; margin-top: 14px; color: #526780; font-size: 1rem; line-height: 1.65; }
.explanation-workbench { display: grid; grid-template-columns: minmax(260px, .85fr) minmax(0, 1.65fr); overflow: hidden; border: 1px solid #d6e0eb; border-radius: 22px; background: white; box-shadow: 0 8px 30px #17345806; }
.explanation-preview { display: flex; flex-direction: column; justify-content: space-between; padding: 30px; background: #edf3f9; border-right: 1px solid #d6e0eb; }
.explanation-preview__example { display: grid; justify-items: center; gap: 8px; padding: 10px 0 28px; }
.explanation-preview__network { display: grid; place-items: center; min-height: 180px; color: #237353; }
.explanation-preview__decimal, .explanation-preview__binary { display: grid; justify-items: center; gap: 8px; }
.explanation-preview__decimal > span, .explanation-preview__binary > span { color: #61758b; font-size: .8rem; font-weight: 600; }
.explanation-preview__decimal strong { position: relative; color: #173458; font-size: 4.5rem; font-weight: 650; line-height: 1.1; letter-spacing: -.07em; font-variant-numeric: tabular-nums; }
.explanation-preview__decimal--long strong { font-size: 2.6rem; letter-spacing: 0; }
.explanation-preview__binary:has(b:nth-of-type(8)) b { width: 22px; height: 42px; font-size: 1.2rem; }
.explanation-preview__binary:has(b:nth-of-type(8)) > div { gap: 4px; }
.explanation-preview__decimal sub { position: absolute; right: -20px; bottom: 7px; font-size: .8rem; letter-spacing: 0; }
.explanation-preview__connector { display: grid; place-items: center; width: 32px; height: 36px; color: #7f96ae; }
.explanation-preview__binary > div { display: flex; position: relative; gap: 7px; }
.explanation-preview__prefix { align-self: center; color: #14765e; font-size: 1.8rem; font-weight: 650; }
.explanation-preview__binary b { display: grid; place-items: center; width: 44px; height: 52px; border: 1px solid #b6d6cd; border-radius: 9px; background: #e0f0e9; color: #14765e; font-size: 1.8rem; font-weight: 650; }
.explanation-preview__binary sub { position: absolute; right: -12px; bottom: 4px; color: #14765e; font-size: .75rem; }
.explanation-preview__copy h2 { color: #173458; font-size: 1.1rem; font-weight: 700; line-height: 1.35; }
.explanation-preview__copy p { margin: 10px 0 22px; color: #526780; font-size: .9rem; line-height: 1.65; }
.explanation-action { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 46px; max-width: 100%; padding: 11px 18px; border: 1px solid #cfdae6; border-radius: 10px; background: #fff; color: #173458; font-size: .9rem; line-height: 1.4; font-weight: 650; cursor: pointer; transition: background .15s, border-color .15s; }
.explanation-action:hover:not(:disabled) { background: #f0f5fa; border-color: #9ab0c9; }
.explanation-action.explanation-preview__button { width: 100%; color: #fff; background: #173458; border-color: #173458; }
.explanation-action.explanation-preview__button:hover { background: #254a73; border-color: #254a73; }
.explanation-detail__panel { min-width: 0; padding: 32px; }
.explanation-sharing-heading { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 30px; }
.explanation-sharing-heading h2 { color: #173458; font-size: 1.4rem; line-height: 1.25; font-weight: 700; letter-spacing: -.02em; }
.explanations .explanation-sharing-heading p { max-width: 34ch; margin-top: 8px; color: #63758a; font-size: .9rem; line-height: 1.6; }
.explanation-detail__status { display: inline-flex; flex-shrink: 0; align-items: center; gap: 7px; padding: 7px 10px; border: 1px solid #e5dcc4; border-radius: 999px; background: #fbf7ec; color: #7d652c; font-size: .72rem; font-weight: 650; line-height: 1.2; }
.explanation-detail__status > span { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.explanation-detail__status--published { border-color: #bddcce; background: #edf7f1; color: #237353; }
.explanation-detail__loading { padding: 32px; color: #526780; }
.explanation-access { min-width: 0; border: 0; padding: 0; margin: 0; }
.explanation-access legend { padding: 0; margin-bottom: 12px; color: #526780; font-size: .8rem; font-weight: 650; }
.explanation-access__options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.explanation-access__option { position: relative; display: flex; flex-direction: column; min-width: 0; gap: 10px; padding: 18px; border: 1px solid #d6e0eb; border-radius: 12px; background: #fff; cursor: pointer; transition: background .15s, border-color .15s; }
.explanation-access__option:hover { border-color: #97b4d8; background: #f9fbfe; }
.explanation-access__option--selected { border-color: #2e6ac4; background: #f0f5fd; box-shadow: inset 0 0 0 1px #2e6ac4; }
.explanation-access__option--selected:hover { border-color: #2e6ac4; background: #eaf1fc; }
.explanation-access__option input { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
.explanation-access__option:has(input:focus-visible) { outline: 3px solid #1b62c5; outline-offset: 4px; }
.explanation-access:disabled .explanation-access__option { opacity: .5; cursor: default; }
.explanation-access__top { display: flex; align-items: center; justify-content: space-between; min-height: 28px; margin-bottom: 4px; color: #55728f; }
.explanation-access__check { display: grid; place-items: center; width: 20px; height: 20px; border: 1px solid #c1cddd; border-radius: 50%; background: #fff; color: transparent; }
.explanation-access__option--selected .explanation-access__check { border-color: #2e6ac4; background: #2e6ac4; color: #fff; }
.explanation-access__option--selected .explanation-access__top { color: #245caa; }
.explanation-access__option strong { color: #173458; font-size: .95rem; line-height: 1.4; }
.explanation-access__option > span:last-child { color: #62748a; font-size: .82rem; line-height: 1.6; }
.explanation-publication { margin-top: 26px; }
.explanation-publication > p { display: flex; align-items: flex-start; gap: 10px; max-width: 48ch; color: #6b7c90; font-size: .8rem; line-height: 1.6; }
.explanation-publication > p > .v-icon { margin-top: 2px; flex-shrink: 0; }
.explanation-distribution { grid-column: 1 / -1; min-width: 0; padding: 28px 32px 16px; border-top: 1px solid #d6e0eb; background: #fbfcfe; }
.explanation-distribution > p:first-of-type { color: #526780; font-size: .85rem; }
.explanation-qr { grid-column: 2; grid-row: 1 / 3; width: 192px; max-width: 100%; margin: 0; }
.explanation-qr .explanation-detail__qr { margin: 0; }
.explanation-qr figcaption { margin-top: 8px; color: #62748a; font-size: .75rem; line-height: 1.5; text-align: center; }
.explanation-action--primary { background: #1b62c5; color: white; border-color: #1b62c5; }
.explanation-action--primary:hover:not(:disabled) { background: #154fa0; color: #fff; border-color: #154fa0; }
.explanation-action:disabled { opacity: .5; cursor: default; }
.explanation-action:focus-visible { outline: 3px solid #1b62c5; outline-offset: 3px; }
.explanation-detail__actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0; }
.explanation-publication .explanation-detail__actions { margin-bottom: 26px; }
.explanation-detail__link { display: block; margin-top: 22px; color: #526780; font-size: .85rem; font-weight: 600; }
.explanation-detail__link input { display: block; width: 100%; margin: 8px 0 16px; padding: 12px; border: 1px solid #cfdae6; border-radius: 9px; background: #fafcfe; color: #173458; font-size: .9rem; font-weight: 400; }
.explanation-detail__qr { display: block; max-width: 100%; height: auto; margin: 20px 0; padding: 12px; border: 1px solid #e0e7ef; border-radius: 12px; background: #fff; }
.explanation-error { margin: 16px 0; padding: 14px 18px; border-radius: 10px; background: #fbeeee; color: #a62e30; }
.explanation-local-note { margin: 14px 0; color: #895d13; }
.explanation-student-preview { background: #f7f8fa; color: #173458; }
.explanation-preview-toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.explanation-preview-toolbar { position: sticky; top: 0; z-index: 5; padding: 16px 24px; background: #fff; border-bottom: 1px solid #c8d5e5; }
.explanation-preview-content { padding: clamp(16px, 3vw, 32px); }
@media (prefers-reduced-motion: reduce) { .explanation-tile { transition: none; } }
@media (max-width: 1150px) { .explanation-workbench { grid-template-columns: minmax(230px, .8fr) minmax(0, 1.6fr); } .explanation-preview { padding: 26px 22px; } .explanation-detail__panel { padding: 28px; } .explanation-sharing-heading { margin-bottom: 24px; } }
@media (max-width: 820px) { .explanation-workbench { grid-template-columns: 1fr; } .explanation-preview { display: grid; grid-template-columns: minmax(180px, .75fr) minmax(0, 1fr); gap: 24px; align-items: center; border-right: 0; border-bottom: 1px solid #d6e0eb; } .explanation-preview__example { padding: 0; } .explanation-preview__copy p { margin-bottom: 18px; } }
@media (max-width: 560px) { .explanations { padding: 12px 4px 28px; } .explanation-detail .explanations__back { margin-bottom: 14px; } .explanation-detail__heading { margin-bottom: 22px; } .explanation-detail__heading h1 { font-size: 1.9rem; } .explanation-workbench { border-radius: 18px; } .explanation-preview { grid-template-columns: 1fr; gap: 26px; padding: 28px 22px; } .explanation-preview__example { grid-template-columns: 1fr auto 1fr; gap: 10px; width: 100%; } .explanation-preview__decimal strong { font-size: 3.7rem; } .explanation-preview__connector { transform: rotate(-90deg); } .explanation-preview__binary b { width: 27px; height: 39px; font-size: 1.2rem; border-radius: 6px; } .explanation-preview__binary > div { gap: 4px; } .explanation-preview__decimal sub { right: -16px; font-size: .65rem; } .explanation-detail__panel { padding: 26px 22px; } .explanation-access__options { grid-template-columns: 1fr; } .explanation-access__option { padding: 18px; } .explanation-sharing-heading { gap: 14px; } .explanation-detail__summary { font-size: .95rem; } .explanation-publication .explanation-detail__actions > button { width: 100%; } .explanation-detail__actions > button { flex-grow: 1; } }
@media (prefers-reduced-motion: reduce) { .explanation-action, .explanation-access__option { transition: none; } }
@media (max-width: 560px) { .explanation-distribution { padding: 24px 22px 12px; } .explanation-detail__heading { grid-template-columns: 1fr; } .explanation-qr { grid-column: 1; grid-row: auto; margin: 20px auto 0; } }
</style>

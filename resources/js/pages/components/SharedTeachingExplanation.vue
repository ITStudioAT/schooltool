<template>
    <main class="shared-explanation">
        <header class="shared-explanation__toolbar"><a :href="returnUrl" class="shared-explanation__close" aria-label="Erklärung schließen" title="Erklärung schließen"><span aria-hidden="true">×</span> Schließen</a></header>
        <p v-if="loading" role="status">Erklärung wird geladen …</p>
        <section v-else-if="error" role="alert"><h1>Erklärung nicht verfügbar</h1><p>{{ error }}</p><button type="button" @click="load">Erneut prüfen</button></section>
        <component :is="explanationComponent" v-else-if="explanationComponent" />
    </main>
</template>

<script setup>
import { ref, shallowRef, watch } from 'vue'
import { useRoute } from 'vue-router'
import { show } from '@/actions/App/Http/Controllers/SharedTeachingExplanationController'
import { explanation as curriculumExplanation } from '@/actions/App/Http/Controllers/Admin/Teaching/CurriculumUnitFileController'
import { explanation as teachingExplanation } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseDateController'
import { explanation as courseExplanation } from '@/actions/App/Http/Controllers/Student/CourseController'
import { teachingExplanationComponent } from '@/helpers/teachingExplanations'

const props = defineProps({ materialContext: { type: String, default: null } })
const route = useRoute()
const loading = ref(false)
const explanationComponent = shallowRef(null)
const error = ref('')
const returnUrl = ref('/')
let loadVersion = 0
async function load() {
    const currentVersion = ++loadVersion
    explanationComponent.value = null
    returnUrl.value = '/'
    loading.value = true
    error.value = ''
    try {
        const materialActions = { curriculum: curriculumExplanation, teaching: teachingExplanation, course: courseExplanation }
        const action = props.materialContext ? materialActions[props.materialContext] : show
        const { data } = await window.axios.get(action.url(String(route.params.material || route.params.token)), { headers: { Accept: 'application/json' } })
        if (currentVersion !== loadVersion) return
        explanationComponent.value = teachingExplanationComponent(data.explanation)
        if (typeof data.return_url === 'string' && data.return_url.startsWith('/') && !data.return_url.startsWith('//')) returnUrl.value = data.return_url
        if (!explanationComponent.value) error.value = 'Diese Erklärung ist nicht mehr verfügbar.'
    } catch (failure) {
        if (currentVersion !== loadVersion) return
        error.value = failure.response?.status === 401 ? 'Bitte melde dich mit deinem Schooltool-Konto an und öffne den Link erneut.' : props.materialContext ? 'Dieses Unterrichtsmaterial ist für dich nicht verfügbar.' : 'Diese Freigabe ist nicht mehr verfügbar.'
    } finally { if (currentVersion === loadVersion) loading.value = false }
}
watch([() => route.params.token, () => route.params.material, () => props.materialContext], load, { immediate: true })
</script>

<style scoped>
.shared-explanation { max-width: 1280px; margin: 0 auto; padding: clamp(16px, 3vw, 36px); color: #173458; }
.shared-explanation__toolbar { display: flex; justify-content: flex-end; position: sticky; top: 0; z-index: 5; padding: 8px 0 16px; background: #f7f8fa; }
.shared-explanation__close { display: inline-flex; align-items: center; gap: 10px; min-height: 44px; padding: 8px 14px; border: 1px solid #c8d5e5; border-radius: 8px; background: white; color: #173458; font-weight: 650; text-decoration: none; }
.shared-explanation__close > span { font-size: 1.8rem; line-height: 1; }
.shared-explanation__close:focus-visible { outline: 3px solid #1b62c5; outline-offset: 3px; }
</style>

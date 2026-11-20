<template>
    <div v-if="available" class="mt-3" @click.stop>
        <v-btn size="small" variant="tonal" :loading="busy && !opened" :disabled="busy" @click="prepareTest">Test</v-btn>
        <v-dialog v-if="opened" v-model="opened" :persistent="busy" max-width="820">
            <v-card>
                <v-card-title>Postmark-Testmail</v-card-title>
                <v-card-text>
                    <template v-if="draft">
                        <dl class="text-body-2 mb-2">
                            <div><dt class="d-inline font-weight-medium">Absender</dt> <dd class="d-inline">{{ draft.sender }}</dd></div>
                            <div><dt class="d-inline font-weight-medium">Antwortadresse</dt> <dd class="d-inline">{{ draft.reply_to }}</dd></div>
                            <div><dt class="d-inline font-weight-medium">Empfänger</dt> <dd class="d-inline">{{ draft.recipient }}</dd></div>
                            <div><dt class="d-inline font-weight-medium">Betreff</dt> <dd class="d-inline">{{ draft.subject }}</dd></div>
                        </dl>
                        <iframe :srcdoc="draft.html" sandbox title="Vorschau der Postmark-Testmail" class="postmark-test-preview" />
                        <p v-if="draft.draft_id" class="text-caption mt-2">Postmark prüft die Absenderadresse beim Senden.</p>
                    </template>
                    <v-alert v-if="status" :type="status.status === 'accepted' ? 'success' : 'warning'" variant="tonal" class="mt-3" role="status">
                        {{ status.message }}
                    </v-alert>
                </v-card-text>
                <v-card-actions class="flex-wrap">
                    <v-spacer />
                    <v-btn variant="text" :disabled="busy" @click="opened = false">Schließen</v-btn>
                    <v-btn v-if="draft?.draft_id" color="primary" variant="tonal" :loading="busy" :disabled="busy" @click="sendTest">
                        Testmail über Postmark senden
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { prepareWorkPostmarkTest, sendWorkPostmarkTest } from '@/actions/App/Http/Controllers/Admin/ConnectionStatusController'
import { useAdminStore } from '@/stores/admin/AdminStore'

const props = defineProps({ workId: { type: Number, required: true } })
const admin = useAdminStore()
const available = computed(() => import.meta.env.DEV && admin.config?.user?.email === 'guenther.kron@cdgym.at')
const opened = ref(false)
const busy = ref(false)
const draft = ref(null)
const status = ref(null)

async function prepareTest() {
    if (!available.value || busy.value) return
    const workId = props.workId
    busy.value = true
    status.value = null
    draft.value = null
    try {
        const { data } = await axios.post(prepareWorkPostmarkTest.url(workId), null, { timeout: 30000 })
        if (props.workId !== workId) return
        if (data.html) draft.value = { ...data, workId }
        if (data.status !== 'ready') status.value = data
        opened.value = true
    } catch (failure) {
        if (props.workId !== workId) return
        status.value = { message: failure.response?.data?.message || 'Die Postmark-Testmail konnte nicht vorbereitet werden.' }
        opened.value = true
    } finally {
        busy.value = false
    }
}

async function sendTest() {
    if (!available.value || busy.value || !draft.value?.draft_id || draft.value.workId !== props.workId) return
    const { workId, draft_id } = draft.value
    busy.value = true
    try {
        const { data } = await axios.post(sendWorkPostmarkTest.url(workId), { draft_id }, { timeout: 30000 })
        if (props.workId === workId) status.value = data
    } catch (failure) {
        if (props.workId === workId) {
            status.value = { message: failure.response?.data?.message || 'Die Annahme durch Postmark ist unklar. Bitte nicht erneut senden.' }
        }
    } finally {
        draft.value = null
        busy.value = false
    }
}
</script>

<style scoped>
.postmark-test-preview { display: block; width: 100%; height: 520px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
@media (max-width: 560px) {
    .postmark-test-preview { height: 640px; }
}
</style>

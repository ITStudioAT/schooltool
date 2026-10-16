<template>
    <div class="question-editor">
        <p v-if="!questions.length" class="text-medium-emphasis py-4">
            Lege die erste Frage an oder übernimm Fragen aus einer Vorlage.
        </p>
        <details v-for="(question, index) in questions" :key="question.id" class="question-editor__question" :open="index === 0">
            <summary>
                <span class="question-editor__number">{{ index + 1 }}</span>
                <span class="question-editor__summary">{{ question.prompt || 'Neue Frage' }}</span>
                <span class="question-editor__state" :class="{ 'text-success': isReady(question) }">
                    {{ !question.active ? 'Archiviert' : isReady(question) ? 'Geprüft' : 'Entwurf' }}
                </span>
            </summary>
            <div class="question-editor__body">
                <label class="question-editor__field">
                    <span>Frage {{ index + 1 }}</span>
                    <textarea :value="question.prompt" rows="3" @input="change(index, { prompt: $event.target.value })" />
                </label>
                <label class="question-editor__field question-editor__type">
                    <span>Antworttyp</span>
                    <select :value="question.type" @change="changeType(index, $event.target.value)">
                        <option value="single">Eine richtige Antwort</option>
                        <option value="multiple">Mehrere richtige Antworten</option>
                    </select>
                </label>
                <fieldset class="question-editor__options">
                    <legend>Antworten · richtige Auswahl markieren</legend>
                    <div v-for="(option, optionIndex) in question.options" :key="option.id" class="question-editor__option">
                        <input
                            :type="question.type === 'single' ? 'radio' : 'checkbox'"
                            :name="`${idPrefix}-${question.id}-correct`"
                            :aria-label="`Antwort ${optionIndex + 1} ist richtig`"
                            :checked="question.correct_option_ids.includes(option.id)"
                            @change="selectCorrect(index, option.id, $event.target.checked)" />
                        <input
                            type="text"
                            :aria-label="`Antwort ${optionIndex + 1} für Frage ${index + 1}`"
                            :value="option.text"
                            placeholder="Antworttext"
                            @input="changeOption(index, option.id, $event.target.value)" />
                        <v-btn
                            icon="mdi-close"
                            variant="text"
                            size="small"
                            :aria-label="`Antwort ${optionIndex + 1} entfernen`"
                            @click="removeOption(index, option.id)" />
                    </div>
                </fieldset>
                <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="addOption(index)">Antwort hinzufügen</v-btn>
                <ul v-if="questionProblems(question).length" class="question-editor__problems text-error mt-3">
                    <li v-for="problem in questionProblems(question)" :key="problem">{{ problem }}</li>
                </ul>
                <p class="text-caption text-medium-emphasis mt-2">
                    Änderungen werden beim Übernehmen oder Speichern bestätigt. Unvollständige Fragen bleiben für Tests gesperrt.
                </p>
            </div>
        </details>
    </div>
</template>

<script setup>
import { newOption, questionProblems } from './multipleChoiceTeacher'

const props = defineProps({
    questions: { type: Array, required: true },
    idPrefix: { type: String, default: 'catalog' },
})
const emit = defineEmits(['update:questions'])

function change(index, updates) {
    const questions = props.questions.map((question, questionIndex) => questionIndex === index
        ? { ...question, ...updates, reviewed: false }
        : question)
    emit('update:questions', questions)
}

function changeType(index, type) {
    const question = props.questions[index]
    change(index, { type, correct_option_ids: type === 'single' ? question.correct_option_ids.slice(0, 1) : question.correct_option_ids })
}

function changeOption(index, id, text) {
    change(index, { options: props.questions[index].options.map((option) => option.id === id ? { ...option, text } : option) })
}

function addOption(index) {
    change(index, { options: [...props.questions[index].options, newOption()] })
}

function removeOption(index, id) {
    change(index, {
        options: props.questions[index].options.filter((option) => option.id !== id),
        correct_option_ids: props.questions[index].correct_option_ids.filter((optionId) => optionId !== id),
    })
}

function selectCorrect(index, id, selected) {
    const question = props.questions[index]
    const correctIds = question.type === 'single'
        ? [id]
        : selected ? [...new Set([...question.correct_option_ids, id])] : question.correct_option_ids.filter((optionId) => optionId !== id)
    change(index, { correct_option_ids: correctIds })
}

function isReady(question) {
    return question.reviewed && !questionProblems(question).length
}
</script>

<style scoped>
.question-editor__question { border: 1px solid #dfe4ea; border-radius: 8px; background: white; margin-top: 10px; }
summary { display: flex; gap: 12px; align-items: center; padding: 14px 16px; cursor: pointer; }
summary::before { content: '›'; color: #617184; font-size: 22px; }
details[open] > summary::before { transform: rotate(90deg); }
.question-editor__number { font-weight: 700; color: #385c80; }
.question-editor__summary { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.question-editor__state { font-size: .8rem; color: #697789; }
.question-editor__body { padding: 0 20px 20px; }
.question-editor__field { display: grid; gap: 7px; margin-bottom: 16px; font-size: .88rem; font-weight: 600; }
.question-editor__type { max-width: 360px; }
textarea, select, input[type='text'] { border: 1px solid #aebac7; border-radius: 5px; background: white; padding: 10px 12px; width: 100%; color: #243447; font-weight: 400; }
textarea { resize: vertical; }
.question-editor__options { border: 0; min-width: 0; }
legend { font-size: .86rem; color: #4d5d70; margin-bottom: 8px; }
.question-editor__option { display: flex; align-items: center; gap: 12px; margin-bottom: 9px; }
input[type='checkbox'], input[type='radio'] { width: 19px; height: 19px; accent-color: #2563a6; flex-shrink: 0; }
.question-editor__problems { padding-left: 18px; font-size: .84rem; }
:focus-visible { outline: 3px solid #3b82b8; outline-offset: 3px; }
@media (max-width: 700px) { .question-editor__body { padding: 0 12px 16px; } summary { padding: 12px; gap: 8px; } }
</style>

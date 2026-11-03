<template>
    <article class="mc-question-review" :class="{ 'mc-question-review-correct': question.correct }">
        <header class="mc-question-review-meta">
            <span class="mc-question-review-number">Frage {{ position + 1 }}</span>
            <span class="mc-question-review-result" :class="{ correct: question.correct }">
                <span aria-hidden="true">{{ question.correct ? '✓' : '×' }}</span>
                {{ question.correct ? 'Richtig' : 'Nicht richtig' }}
            </span>
            <span v-if="question.points != null" class="mc-question-review-points">{{ formatPoints(question.points) }} Punkte</span>
        </header>
        <h4>{{ question.prompt }}</h4>
        <p v-if="!question.selected_option_ids?.length" class="mc-question-review-empty">Keine Antwort ausgewählt</p>
        <ul class="mc-question-review-options">
            <li v-for="option in question.options ?? []" :key="option.id"
                :class="{ correct: isCorrect(option.id), incorrect: hasSolutions() && isSelected(option.id) && !isCorrect(option.id), selected: isSelected(option.id) }">
                <span class="mc-question-review-mark" aria-hidden="true">{{ isCorrect(option.id) ? '✓' : isSelected(option.id) ? (hasSolutions() ? '×' : '●') : '○' }}</span>
                <span class="mc-question-review-answer">{{ option.text || option.label }}</span>
                <span class="mc-question-review-tags">
                    <span v-if="isSelected(option.id)">Ausgewählt</span>
                    <span v-if="isCorrect(option.id)">Richtige Antwort</span>
                </span>
            </li>
        </ul>
    </article>
</template>

<script setup>
const props = defineProps({ question: { type: Object, required: true }, position: { type: Number, required: true } })
const hasSolutions = () => Array.isArray(props.question.correct_option_ids)
const formatPoints = (value) => new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(value)
const isSelected = (id) => (props.question.selected_option_ids ?? []).some(value => String(value) === String(id))
const isCorrect = (id) => (props.question.correct_option_ids ?? []).some(value => String(value) === String(id))
</script>

<style scoped>
.mc-question-review { border: 1px solid #d6e2dc; border-left: 4px solid #bf6860; border-radius: 10px; padding: 20px; background: #fff; color: #17352c; margin: 0 0 16px; text-align: left; }
.mc-question-review-correct { border-left-color: #35785d; }
.mc-question-review-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-bottom: 12px; font-size: .85rem; }
.mc-question-review-number { font-weight: 600; color: #52675d; }
.mc-question-review-result { display: inline-flex; align-items: center; gap: 6px; color: #8e3933; background: #fff0ed; border-radius: 6px; padding: 4px 9px; font-weight: 600; }
.mc-question-review-result.correct { color: #215b42; background: #eaf4ed; }
.mc-question-review-points { margin-left: auto; font-weight: 600; font-variant-numeric: tabular-nums; }
.mc-question-review h4 { font-size: 1.05rem; line-height: 1.55; font-weight: 600; max-width: 75ch; margin: 0 0 16px; overflow-wrap: anywhere; }
.mc-question-review-options { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
.mc-question-review-options li { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 8px 10px; padding: 12px 14px; border: 1px solid #e1e7e3; border-radius: 7px; background: #fafcfb; line-height: 1.5; }
.mc-question-review-options li.selected:not(.correct):not(.incorrect) { background: #edf2f8; border-color: #b8c9dd; }
.mc-question-review-options li.correct { background: #edf6f0; border-color: #b5d2bf; }
.mc-question-review-options li.incorrect { background: #fff3f1; border-color: #e2b9b3; }
.mc-question-review-mark { flex: 0 0 18px; font-weight: 700; color: #6d7d73; }
.correct > .mc-question-review-mark { color: #21603e; }
.incorrect > .mc-question-review-mark { color: #a23d36; }
.mc-question-review-answer { flex: 1 1 180px; overflow-wrap: anywhere; }
.mc-question-review-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-left: 28px; font-size: .75rem; color: #41554a; }
.mc-question-review-tags span { padding: 2px 7px; border: 1px solid currentColor; border-radius: 4px; }
.mc-question-review-empty { color: #6c756f; font-size: .9rem; margin: 0 0 12px; }
@media (min-width: 700px) { .mc-question-review-tags { margin-left: auto; } }
@media (max-width: 480px) { .mc-question-review { padding: 15px 12px; } .mc-question-review-points { margin-left: 0; } }
</style>

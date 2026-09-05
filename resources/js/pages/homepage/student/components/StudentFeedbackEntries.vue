<template>
    <section class="feedback-panel feedback-notes" :class="`feedback-notes--${kind}`">
        <header class="feedback-panel-heading">
            <span class="feedback-panel-icon"><v-icon size="23">{{ kind === 'behaviour' ? 'mdi-account-heart-outline' : 'mdi-message-text-outline' }}</v-icon></span>
            <div>
                <h3>{{ title }}</h3>
                <p>{{ kind === 'behaviour' ? 'Rückmeldungen zu deinem Schulalltag' : 'Weitere Informationen für dich' }}</p>
            </div>
            <span class="feedback-panel-count">{{ entries.length }}</span>
        </header>
        <div v-if="entries.length" class="feedback-notes-list">
            <article v-for="entry in entries" :key="entry.key" class="feedback-note">
                <div class="feedback-note-meta">
                    <span class="feedback-note-kind">{{ title }}</span>
                    <time v-if="entry.date" :datetime="entry.date">{{ entry.dateLabel }}</time>
                </div>
                <h4>{{ entry.typeLabel }}</h4>
                <strong v-if="entry.title">{{ entry.title }}</strong>
                <p v-if="entry.description">{{ entry.description }}</p>
                <p v-if="entry.comment">{{ entry.comment }}</p>
                <v-chip v-if="entry.grade" size="small" variant="tonal">{{ entry.grade }}</v-chip>
            </article>
        </div>
        <div v-else class="feedback-empty">
            <v-icon size="28">mdi-message-outline</v-icon>
            <strong>Noch keine Einträge</strong>
            <p>Für diesen Zeitraum ist hier nichts eingetragen.</p>
        </div>
    </section>
</template>

<script>
export default {
    props: {
        entries: { type: Array, default: () => [] },
        title: { type: String, required: true },
        kind: { type: String, default: 'behaviour' },
    },
}
</script>

<style scoped>
.feedback-notes {
    --feedback-accent: #c62828;
    --feedback-tint: #fff0f0;
}
.feedback-notes--additional {
    --feedback-accent: #286c83;
    --feedback-tint: #e9f5f8;
}
.feedback-panel-icon,
.feedback-panel-count {
    color: var(--feedback-accent);
    background: var(--feedback-tint);
}
.feedback-notes-list {
    display: grid;
    gap: 12px;
    padding: 0 20px 20px;
}
.feedback-note {
    min-width: 0;
    padding: 18px;
    border: 1px solid #e8e8ed;
    border-radius: 0 16px 16px 0;
    border-left: 3px solid var(--feedback-accent);
    background: #fff;
    overflow-wrap: anywhere;
}
.feedback-note-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
    font-size: 0.72rem;
    color: #677186;
}
.feedback-note-kind {
    padding: 4px 8px;
    border-radius: 6px;
    background: var(--feedback-tint);
    color: var(--feedback-accent);
    font-weight: 700;
}
.feedback-note h4 {
    font-size: 0.94rem;
    font-weight: 800;
    line-height: 1.45;
    color: #222b42;
}
.feedback-note p,
.feedback-note > strong {
    display: block;
    margin-top: 9px;
    font-size: 0.88rem;
    line-height: 1.65;
    color: #5a6479;
    white-space: pre-wrap;
}
.feedback-note .v-chip { margin-top: 10px; }
@media (max-width: 700px) {
    .feedback-notes-list { padding: 0 14px 14px; }
    .feedback-note { padding: 15px; }
}
</style>

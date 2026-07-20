<template>
    <div v-if="viewer_type === 'parent'" class="parent-access-panel" data-testid="parent-access-panel">
        <div class="parent-access-copy">
            <div class="parent-access-label">
                <v-icon size="20">mdi-account-supervisor-circle</v-icon>
                <span>Elternzugang</span>
                <span class="parent-access-readonly">Schreibgeschützt</span>
            </div>
            <p>
                Du siehst den Unterrichtsbereich von
                <strong>{{ user?.first_name }} {{ user?.last_name }}</strong>
                <span v-if="user?.schoolclass">({{ user.schoolclass }})</span>.
            </p>
        </div>

        <v-btn
            class="parent-access-switch"
            data-testid="parent-access-change-child"
            color="#163443"
            variant="flat"
            rounded="pill"
            prepend-icon="mdi-account-switch"
            @click="changeChild">
            Anderes Kind auswählen
        </v-btn>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'

export default {
    computed: {
        ...mapWritableState(useStudentStore, ['user', 'viewer_type']),
    },

    methods: {
        changeChild() {
            this.$router.push({ path: '/student', query: { select_child: '1' } })
        },
    },
}
</script>

<style scoped>
.parent-access-panel {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-top: 22px;
    padding: 16px 18px;
    color: #163443;
    background: rgba(255, 255, 255, 0.92);
    border: 2px solid rgba(22, 52, 67, 0.25);
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(22, 52, 67, 0.14);
}

.parent-access-copy {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.parent-access-copy p {
    margin: 0;
    font-size: 0.96rem;
    line-height: 1.45;
}

.parent-access-label {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 1.05rem;
    font-weight: 900;
}

.parent-access-readonly {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    color: #fff;
    background: #163443;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.parent-access-switch {
    flex-shrink: 0;
    color: #fff;
    font-weight: 800;
}

@media (max-width: 700px) {
    .parent-access-panel {
        align-items: stretch;
        flex-direction: column;
    }

    .parent-access-switch {
        width: 100%;
    }
}
</style>

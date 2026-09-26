<template>
    <div class="admin-page-heading">
        <header class="admin-page-header admin-page-header--branded" aria-label="Seitenkopf">
            <div class="admin-page-header__brand">
                <div class="admin-page-header__mark" aria-hidden="true">
                    <v-icon icon="mdi-school-outline" size="21" />
                </div>
                <div class="admin-page-header__copy">
                    <div class="admin-page-header__brand-name">SchoolTool</div>
                    <h1 class="admin-page-header__location">{{ locationLabel }}</h1>
                </div>
            </div>
            <div v-if="contextLabel || statusLabel" class="admin-page-header__status" role="status">
                <span class="admin-page-header__schoolyear">{{ contextLabel }}</span>
                <span v-if="statusLabel" class="admin-page-header__status-value" :class="{ 'admin-page-header__status-value--open': isOpen }">
                    <v-icon :icon="statusIcon || (isOpen ? 'mdi-check-circle-outline' : 'mdi-information-outline')" size="16" />
                    {{ statusLabel }}
                </span>
            </div>
            <div v-if="schoolName || userName || userEmail" class="admin-page-header__identity">
                <span v-if="schoolName" class="admin-page-header__school">{{ schoolName }}</span>
                <span v-if="userName" class="admin-page-header__user">{{ userName }}</span>
                <span v-if="userEmail" class="admin-page-header__user">{{ userEmail }}</span>
            </div>
        </header>
        <div v-if="$slots.actions" class="admin-page-header-actions">
            <slot name="actions" />
        </div>
    </div>
</template>

<script>
import { mapState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    props: {
        location: { type: String, required: true },
        section: { type: String, default: '' },
        contextLabel: { type: String, default: '' },
        statusLabel: { type: String, default: '' },
        isOpen: { type: Boolean, default: false },
        statusIcon: { type: String, default: '' },
    },
    computed: {
        ...mapState(useAdminStore, ['config']),
        locationLabel() {
            const location = this.location.trim()
            const section = this.section.trim()

            return section && section.toLocaleLowerCase('de') !== location.toLocaleLowerCase('de')
                ? `${location} · ${section}`
                : location
        },
        schoolName() {
            const school = this.config?.selected_school

            return [school?.long_name, school?.name]
                .map((name) => String(name || '').trim())
                .find(Boolean) || ''
        },
        userName() {
            const user = this.config?.user

            return [user?.last_name, user?.first_name]
                .map((name) => String(name || '').trim())
                .filter(Boolean)
                .join(' ')
        },
        userEmail() {
            return String(this.config?.user?.email || '').trim()
        },
    },
}
</script>

<style scoped>
.admin-page-header--branded {
    display: flex;
    align-items: center;
    gap: 20px 36px;
}

.admin-page-header__brand {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 0 1 280px;
    min-width: 0;
}

.admin-page-header__mark {
    display: grid;
    place-items: center;
    flex: 0 0 38px;
    height: 38px;
    border-radius: 10px;
    background: rgb(var(--v-theme-primary));
    color: #ffffff;
    box-shadow: 0 6px 15px rgba(var(--v-theme-primary), 0.2);
}

.admin-page-header__copy { min-width: 0; }
.admin-page-header__brand-name { font-size: 0.95rem; font-weight: 800; }
.admin-page-header__location {
    margin: 3px 0 0;
    color: #65716c;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    overflow-wrap: anywhere;
}

.admin-page-header__status {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-left: 24px;
    border-left: 1px solid #e7e9ef;
    font-size: 0.75rem;
    min-width: 0;
}

.admin-page-header__schoolyear { font-weight: 700; }
.admin-page-header__status-value {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #65716c;
}
.admin-page-header__status-value--open { color: #278250; }
.admin-page-header__identity {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    margin-left: auto;
    max-width: 420px;
    min-width: 0;
    text-align: right;
    overflow-wrap: anywhere;
}
.admin-page-header__school {
    padding: 5px 10px;
    border: 1px solid #e7e9ef;
    border-radius: 7px;
    background: #fafbfc;
    font-size: 0.7rem;
    font-weight: 600;
}
.admin-page-header__user { color: #65716c; font-size: 0.7rem; }
.admin-page-header-actions { margin-top: 12px; }

@media (max-width: 1099px) {
    .admin-page-header--branded { flex-wrap: wrap; gap: 14px 24px; }
    .admin-page-header__brand { flex: 1 1 180px; }
    .admin-page-header__identity { flex: 1 0 100%; max-width: none; }
}

@media (max-width: 599px) {
    .admin-page-header__status { flex: 1 0 100%; padding-left: 0; border-left: 0; }
    .admin-page-header__identity { align-items: flex-start; text-align: left; }
}
</style>

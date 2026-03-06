<template>
    <div class="groups-page">
        <v-container fluid class="ma-0 w-100 pa-2">
            <AdminSectionHero
                class="mb-3"
                eyebrow="Verwaltung"
                title="Gruppen"
                :active-section="headerActiveSection"
                :chips="headerChips"
                :show-current-user-chip="true"
                focus-label="Gruppentypen" />

            <div class="super-admin-overview-shell super-admin-overview-shell--active">
                <section class="sa-card sa-card-school mb-3">
                    <div class="sa-card-head">
                        <div>
                            <div class="sa-card-eyebrow">Übersicht</div>
                            <h2 class="sa-card-title">Gruppentypen & Rechte</h2>
                        </div>
                        <v-btn flat color="primary" prepend-icon="mdi-refresh" :loading="isBusy" @click="loadGroups">
                            Aktualisieren
                        </v-btn>
                    </div>

                    <div class="sa-kpi-grid">
                        <div class="sa-kpi-card" v-for="section in groupSections" :key="`kpi-${section.type}`">
                            <div class="sa-kpi-label">{{ section.label }}</div>
                            <div class="sa-kpi-value">{{ groupsByType(section.type).length }}</div>
                            <div class="sa-kpi-sub">
                                {{ canManageType(section.type) ? 'bearbeitbar' : 'nur sichtbar' }}
                            </div>
                        </div>
                    </div>
                </section>

                <div class="groups-cards-shell">
                    <v-row class="w-100 ma-0 groups-cards-row" dense>
                    <v-col cols="12" md="6" xl="4" v-for="section in groupSections" :key="section.type">
                    <section class="sa-card h-100">
                        <div class="sa-card-head">
                            <div>
                                <div class="sa-card-eyebrow">{{ section.eyebrow }}</div>
                                <h2 class="sa-card-title">{{ section.label }}</h2>
                                <div class="sa-item-sub mt-1">{{ section.permissionText }}</div>
                            </div>
                            <v-chip
                                :color="canManageType(section.type) ? 'success' : 'warning'"
                                variant="flat"
                                size="small">
                                {{ canManageType(section.type) ? 'bearbeitbar' : 'keine Bearbeitung' }}
                            </v-chip>
                        </div>

                        <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-2">
                            <div class="d-flex flex-wrap ga-2">
                                <v-btn
                                    v-if="canManageType(section.type)"
                                    flat
                                    color="primary"
                                    prepend-icon="mdi-plus"
                                    @click="openCreateDialog(section.type)">
                                    Gruppe anlegen
                                </v-btn>
                            </div>
                        </div>

                        <div class="sa-empty" v-if="groupsByType(section.type).length === 0">
                            Keine Gruppen vorhanden.
                        </div>

                        <div v-else-if="section.type === 'school'" class="groups-school-panels">
                            <v-expansion-panels
                                v-model="schoolSectionPanelsOpen"
                                multiple
                                variant="accordion">
                                <v-expansion-panel
                                    v-for="panel in schoolSectionPanels()"
                                    :key="panel.key"
                                    :value="panel.key">
                                    <v-expansion-panel-title>
                                        <div class="d-flex align-center justify-space-between w-100 ga-3">
                                            <div class="d-flex align-center ga-2">
                                                <v-icon size="18">{{ panel.icon }}</v-icon>
                                                <div class="groups-panel-title-wrap">
                                                    <span>{{ panel.title }}</span>
                                                    <span v-if="panel.metaLabel" class="groups-panel-title-meta">{{ panel.metaLabel }}</span>
                                                </div>
                                            </div>
                                            <v-chip color="secondary" variant="flat" size="x-small">
                                                {{ panel.groups.length }}
                                            </v-chip>
                                        </div>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <div class="sa-empty" v-if="panel.groups.length === 0">
                                            Keine Gruppen vorhanden.
                                        </div>
                                        <div class="groups-table-wrap" v-else>
                                            <v-table density="comfortable" class="groups-table">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="group in panel.groups"
                                                        :key="group.id"
                                                        class="groups-row"
                                                        :class="{ 'is-selected': isSelectedGroup(section.type, group.id) }"
                                                        @click="onGroupRowClick(section.type, group)">
                                                        <td>
                                                            <div class="groups-row-main">
                                                                <div class="groups-row-title">
                                                                    <div class="font-weight-bold text-body-1">{{ group.name }}</div>
                                                                </div>
                                                                <div class="groups-row-meta">
                                                                    <v-chip
                                                                        class="groups-row-counter-chip"
                                                                        :color="group.type === 'school' ? 'info' : (group.members_count > 0 ? 'warning' : 'secondary')"
                                                                        variant="flat"
                                                                        size="small">
                                                                        <template v-if="showSourceUsersCounter(group)">
                                                                            {{ Number(group.source_users_count || 0) }}/{{ Number(group.members_count || 0) }}
                                                                        </template>
                                                                        <template v-else>
                                                                            {{ Number(group.members_count || 0) }}
                                                                        </template>
                                                                    </v-chip>
                                                                    <div class="d-inline-flex align-center ga-1">
                                                                        <v-btn
                                                                            v-if="showInlineMemberManagementButton(group)"
                                                                            icon="mdi-account-edit-outline"
                                                                            size="small"
                                                                            variant="text"
                                                                            color="secondary"
                                                                            :title="`${group.name} Mitglieder hinzufügen oder entfernen`"
                                                                            @click.stop="openManageMembersDialog(section.type, group)" />
                                                                        <v-btn
                                                                            v-if="canManageType(section.type) && group.can_edit !== false"
                                                                            icon="mdi-pencil"
                                                                            size="small"
                                                                            variant="text"
                                                                            color="primary"
                                                                            :title="`${group.name} bearbeiten`"
                                                                            @click.stop="openEditDialog(group)" />
                                                                        <v-btn
                                                                            v-if="canManageType(section.type) && group.can_delete !== false"
                                                                            icon="mdi-delete"
                                                                            size="small"
                                                                            variant="text"
                                                                            color="warning"
                                                                            :disabled="isDeleteDisabled(group)"
                                                                            :title="deleteButtonTitle(group)"
                                                                            @click.stop="openDeleteDialog(group)" />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div
                                                                v-if="String(group.description || '').trim() !== ''"
                                                                class="text-caption text-medium-emphasis groups-desc-cell">
                                                                {{ group.description }}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </v-table>
                                        </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </div>

                        <div v-else-if="section.type === 'own'" class="groups-school-panels">
                            <v-expansion-panels
                                v-model="ownSectionPanelsOpen"
                                multiple
                                variant="accordion">
                                <v-expansion-panel
                                    v-for="panel in ownSectionPanels()"
                                    :key="panel.key"
                                    :value="panel.key">
                                    <v-expansion-panel-title>
                                        <div class="d-flex align-center justify-space-between w-100 ga-3">
                                            <div class="d-flex align-center ga-2">
                                                <v-icon size="18">{{ panel.icon }}</v-icon>
                                                <div class="groups-panel-title-wrap">
                                                    <span>{{ panel.title }}</span>
                                                    <span v-if="panel.metaLabel" class="groups-panel-title-meta">{{ panel.metaLabel }}</span>
                                                </div>
                                            </div>
                                            <v-chip color="secondary" variant="flat" size="x-small">
                                                {{ panel.groups.length }}
                                            </v-chip>
                                        </div>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <div class="sa-empty" v-if="panel.groups.length === 0">
                                            Keine Gruppen vorhanden.
                                        </div>
                                        <div class="groups-table-wrap" v-else>
                                            <v-table density="comfortable" class="groups-table">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="group in panel.groups"
                                                        :key="group.id"
                                                        class="groups-row"
                                                        :class="{ 'is-selected': isSelectedGroup(section.type, group.id) }"
                                                        @click="onGroupRowClick(section.type, group)">
                                                        <td>
                                                            <div class="groups-row-main">
                                                                <div class="groups-row-title">
                                                                    <div class="font-weight-bold text-body-1">{{ group.name }}</div>
                                                                </div>
                                                                <div class="groups-row-meta">
                                                                    <v-chip
                                                                        class="groups-row-counter-chip"
                                                                        :color="group.type === 'school' ? 'info' : (group.members_count > 0 ? 'warning' : 'secondary')"
                                                                        variant="flat"
                                                                        size="small">
                                                                        <template v-if="showSourceUsersCounter(group)">
                                                                            {{ Number(group.source_users_count || 0) }}/{{ Number(group.members_count || 0) }}
                                                                        </template>
                                                                        <template v-else>
                                                                            {{ Number(group.members_count || 0) }}
                                                                        </template>
                                                                    </v-chip>
                                                                    <div class="d-inline-flex align-center ga-1">
                                                                        <v-btn
                                                                            v-if="showInlineMemberManagementButton(group)"
                                                                            icon="mdi-account-edit-outline"
                                                                            size="small"
                                                                            variant="text"
                                                                            color="secondary"
                                                                            :title="`${group.name} Mitglieder hinzufügen oder entfernen`"
                                                                            @click.stop="openManageMembersDialog(section.type, group)" />
                                                                        <v-btn
                                                                            v-if="canManageType(section.type) && group.can_edit !== false"
                                                                            icon="mdi-pencil"
                                                                            size="small"
                                                                            variant="text"
                                                                            color="primary"
                                                                            :title="`${group.name} bearbeiten`"
                                                                            @click.stop="openEditDialog(group)" />
                                                                        <v-btn
                                                                            v-if="canManageType(section.type) && group.can_delete !== false"
                                                                            icon="mdi-delete"
                                                                            size="small"
                                                                            variant="text"
                                                                            color="warning"
                                                                            :disabled="isDeleteDisabled(group)"
                                                                            :title="deleteButtonTitle(group)"
                                                                            @click.stop="openDeleteDialog(group)" />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div
                                                                v-if="String(group.description || '').trim() !== ''"
                                                                class="text-caption text-medium-emphasis groups-desc-cell">
                                                                {{ group.description }}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </v-table>
                                        </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </div>

                        <div class="groups-table-wrap" v-else>
                            <v-table density="comfortable" class="groups-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="group in groupsByType(section.type)"
                                        :key="group.id"
                                        class="groups-row"
                                        :class="{ 'is-selected': isSelectedGroup(section.type, group.id) }"
                                        @click="onGroupRowClick(section.type, group)">
                                        <td>
                                            <div class="groups-row-main">
                                                <div class="groups-row-title">
                                                    <div class="font-weight-bold text-body-1">{{ group.name }}</div>
                                                </div>
                                                <div class="groups-row-meta">
                                                    <v-chip
                                                        class="groups-row-counter-chip"
                                                        :color="group.type === 'school' ? 'info' : (group.members_count > 0 ? 'warning' : 'secondary')"
                                                        variant="flat"
                                                        size="small">
                                                        <template v-if="showSourceUsersCounter(group)">
                                                            {{ Number(group.source_users_count || 0) }}/{{ Number(group.members_count || 0) }}
                                                        </template>
                                                        <template v-else>
                                                            {{ Number(group.members_count || 0) }}
                                                        </template>
                                                    </v-chip>
                                                    <div class="d-inline-flex align-center ga-1">
                                                        <v-btn
                                                            v-if="showInlineMemberManagementButton(group)"
                                                            icon="mdi-account-edit-outline"
                                                            size="small"
                                                            variant="text"
                                                            color="secondary"
                                                            :title="`${group.name} Mitglieder hinzufügen oder entfernen`"
                                                            @click.stop="openManageMembersDialog(section.type, group)" />
                                                        <v-btn
                                                            v-if="canManageType(section.type) && group.can_edit !== false"
                                                            icon="mdi-pencil"
                                                            size="small"
                                                            variant="text"
                                                            color="primary"
                                                            :title="`${group.name} bearbeiten`"
                                                            @click.stop="openEditDialog(group)" />
                                                        <v-btn
                                                            v-if="canManageType(section.type) && group.can_delete !== false"
                                                            icon="mdi-delete"
                                                            size="small"
                                                            variant="text"
                                                            color="warning"
                                                            :disabled="isDeleteDisabled(group)"
                                                            :title="deleteButtonTitle(group)"
                                                            @click.stop="openDeleteDialog(group)" />
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                v-if="String(group.description || '').trim() !== ''"
                                                class="text-caption text-medium-emphasis groups-desc-cell">
                                                {{ group.description }}
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </div>
                    </section>
                    </v-col>
                    </v-row>
                </div>
            </div>
        </v-container>

        <v-dialog v-model="editDialog.open" max-width="680" persistent>
            <v-card class="ai-glass-panel">
                <v-card-title class="d-flex justify-space-between align-center">
                    <div>
                        <div class="text-caption text-medium-emphasis">{{ currentTypeLabel(editDialog.form.type) }}</div>
                        <div>{{ editDialog.mode === 'create' ? 'Gruppe anlegen' : 'Gruppe bearbeiten' }}</div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" @click="closeEditDialog" />
                </v-card-title>

                <v-card-text>
                    <v-form ref="groupForm" v-model="editDialog.valid" @submit.prevent="saveGroup">
                        <v-text-field
                            v-model="editDialog.form.name"
                            label="Name"
                            autofocus
                            :rules="[requiredRule]"
                            maxlength="255"
                            counter />
                        <v-textarea
                            v-model="editDialog.form.description"
                            label="Beschreibung"
                            rows="4"
                            auto-grow
                            maxlength="5000"
                            counter />
                    </v-form>
                </v-card-text>

                <v-card-actions class="d-flex justify-space-between">
                    <v-btn color="warning" variant="text" @click="closeEditDialog">Abbrechen</v-btn>
                    <v-btn color="success" variant="flat" :loading="isBusy" @click="saveGroup">
                        {{ editDialog.mode === 'create' ? 'Erstellen' : 'Speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog.open" max-width="560">
            <v-card class="ai-glass-panel">
                <v-card-title>Gruppe löschen</v-card-title>
                <v-card-text v-if="deleteDialog.group">
                    <div class="text-body-1">
                        Soll die Gruppe <strong>{{ deleteDialog.group.name }}</strong> wirklich gelöscht werden?
                    </div>
                    <div class="text-caption text-medium-emphasis mt-2">
                        Gruppentyp: {{ currentTypeLabel(deleteDialog.group.type) }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        Mitglieder: {{ deleteDialog.group.members_count }}
                    </div>
                    <v-alert
                        v-if="showDeleteMembersWarning(deleteDialog.group)"
                        type="warning"
                        variant="tonal"
                        class="mt-3">
                        Löschen ist nur möglich, wenn die Gruppe keine Mitglieder enthält.
                    </v-alert>
                </v-card-text>
                <v-card-actions class="d-flex justify-space-between">
                    <v-btn color="secondary" variant="text" @click="closeDeleteDialog">Abbrechen</v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        :disabled="!deleteDialog.group || isDeleteDisabled(deleteDialog.group)"
                        :loading="isBusy"
                        @click="deleteGroup">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="assignUsersDialog.open" max-width="760" persistent>
            <v-card class="ai-glass-panel">
                <v-card-title class="d-flex justify-space-between align-center">
                    <div>
                        <div class="text-caption text-medium-emphasis">
                            {{ currentTypeLabel(assignUsersDialog.group?.type) }}
                        </div>
                        <div>{{ assignUsersDialog.readOnly ? 'Gruppenmitglieder' : 'Benutzer zuordnen' }}</div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" @click="closeAssignUsersDialog" />
                </v-card-title>
                <v-card-text>
                    <div v-if="assignUsersDialog.group" class="text-body-2 mb-2">
                        Gruppe: <strong>{{ assignUsersDialog.group.name }}</strong>
                    </div>
                    <v-alert v-if="showReadOnlyAutoManagedNotice()" type="info" variant="tonal" class="mb-3">
                        Gruppe automatisch erstellt.
                    </v-alert>
                    <div class="d-grid ga-3">
                        <section class="groups-assign-section">
                            <template v-if="assignUsersDialog.readOnly">
                                <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-2">
                                    <div>
                                        <div class="admin-card-eyebrow">Mitglieder</div>
                                        <div class="text-caption text-medium-emphasis">
                                            Registrierte Einträge sind mit einem Symbol markiert.
                                        </div>
                                    </div>
                                    <v-chip color="secondary" variant="flat" size="x-small">
                                        {{ readOnlyCombinedMembers().length }}
                                    </v-chip>
                                </div>
                                <div class="sa-empty" v-if="readOnlyCombinedMembersLoading()">
                                    {{ readOnlyCombinedMembersLoadingText() }}
                                </div>
                                <div class="sa-empty" v-else-if="readOnlyCombinedMembers().length === 0">
                                    {{ readOnlyCombinedMembersEmptyText() }}
                                </div>
                                <div class="groups-assign-list" v-else>
                                    <div class="groups-assign-list-item" v-for="member in paginatedReadOnlyCombinedMembers()" :key="`readonly-member-${readOnlyMemberKey(member) || member.id}`">
                                        <div class="d-flex align-start justify-space-between ga-2 w-100">
                                            <div class="min-w-0">
                                                <div class="d-flex align-center ga-2">
                                                    <div class="font-weight-bold text-body-2">{{ member.name }}</div>
                                                    <v-icon
                                                        v-if="member.is_registered"
                                                        size="16"
                                                        color="success"
                                                        title="Registriert">
                                                        mdi-check-circle
                                                    </v-icon>
                                                </div>
                                                <div class="text-caption text-medium-emphasis">{{ member.email || 'Keine E-Mail' }}</div>
                                                <div class="text-caption text-medium-emphasis" v-if="member.member_type_label">{{ member.member_type_label }}</div>
                                                <div class="text-caption text-medium-emphasis" v-if="member.schoolclass">Klasse: {{ member.schoolclass }}</div>
                                                <div class="text-caption text-medium-emphasis" v-if="member.phone">Telefon: {{ member.phone }}</div>
                                                <div class="text-caption text-medium-emphasis" v-if="member.children_label">Kinder: {{ member.children_label }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="!readOnlyCombinedMembersLoading() && shouldPaginateDialogList(readOnlyCombinedMembers().length)"
                                    class="groups-list-pagination">
                                    <div class="text-caption text-medium-emphasis">
                                        {{ dialogPaginationSummary(readOnlyCombinedMembers().length, assignUsersDialog.readOnlyPage) }}
                                    </div>
                                    <v-pagination
                                        v-model="assignUsersDialog.readOnlyPage"
                                        :length="dialogPaginationPageCount(readOnlyCombinedMembers().length)"
                                        :total-visible="5"
                                        active-color="primary"
                                        density="comfortable" />
                                </div>
                            </template>

                            <template v-else>
                                <div class="d-flex justify-space-between align-center flex-wrap ga-2">
                                    <div>
                                        <div class="admin-card-eyebrow">Zugeordnete Benutzer</div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ assignUsersDialog.members.length }} Benutzer in dieser Gruppe
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap ga-2">
                                        <v-btn
                                            flat
                                            :color="assignUsersDialog.membersExpanded ? 'secondary' : 'primary'"
                                            :prepend-icon="assignUsersDialog.membersExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                            @click="assignUsersDialog.membersExpanded = !assignUsersDialog.membersExpanded">
                                            {{ assignUsersDialog.membersExpanded ? 'Ausblenden' : 'Anzeigen' }}
                                        </v-btn>
                                    </div>
                                </div>

                                <div v-if="assignUsersDialog.membersExpanded" class="mt-3">
                                    <div class="d-flex flex-wrap ga-2 mb-2" v-if="assignUsersDialog.members.length >= 1">
                                        <v-btn
                                            flat
                                            color="primary"
                                            size="small"
                                            prepend-icon="mdi-check-all"
                                            @click="selectAllAssignedMembers">
                                            Alle auswählen
                                        </v-btn>
                                        <v-btn
                                            flat
                                            color="secondary"
                                            size="small"
                                            prepend-icon="mdi-close-box-multiple-outline"
                                            :disabled="assignUsersDialog.selectedMemberIds.length === 0"
                                            @click="clearAssignedMemberSelection">
                                            Auswahl aufheben ({{ assignUsersDialog.selectedMemberIds.length }})
                                        </v-btn>
                                        <v-btn
                                            flat
                                            color="warning"
                                            size="small"
                                            prepend-icon="mdi-account-multiple-remove"
                                            :disabled="assignUsersDialog.selectedMemberIds.length === 0"
                                            :loading="isBusy && assignUsersDialog.bulkRemoving"
                                            @click="removeSelectedAssignedMembers">
                                            Ausgewählte entfernen ({{ assignUsersDialog.selectedMemberIds.length }})
                                        </v-btn>
                                    </div>

                                    <div class="sa-empty" v-if="assignUsersDialog.membersLoading">
                                        Lade zugeordnete Benutzer ...
                                    </div>
                                    <div class="sa-empty" v-else-if="assignUsersDialog.members.length === 0">
                                        Keine Benutzer zugeordnet.
                                    </div>
                                    <div class="groups-assign-list" v-else>
                                        <div class="groups-assign-list-item" v-for="member in paginatedAssignedMembers()" :key="`group-member-${member.id}`">
                                            <div class="d-flex align-start ga-2 min-w-0">
                                                <v-checkbox-btn
                                                    :model-value="isAssignedMemberSelected(member.id)"
                                                    color="primary"
                                                    @update:model-value="toggleAssignedMemberSelection(member.id)" />
                                                <div class="min-w-0">
                                                    <div class="font-weight-bold text-body-2">{{ member.name }}</div>
                                                    <div class="text-caption text-medium-emphasis">{{ member.email }}</div>
                                                    <div class="text-caption text-medium-emphasis" v-if="member.schoolclass">Klasse: {{ member.schoolclass }}</div>
                                                </div>
                                            </div>
                                            <v-btn
                                                flat
                                                color="warning"
                                                size="small"
                                                prepend-icon="mdi-account-remove"
                                                :loading="isBusy && Number(assignUsersDialog.removingUserId) === Number(member.id)"
                                                @click="removeAssignedMember(member)">
                                                Entfernen
                                            </v-btn>
                                        </div>
                                    </div>
                                    <div
                                        v-if="!assignUsersDialog.membersLoading && shouldPaginateDialogList(assignUsersDialog.members.length)"
                                        class="groups-list-pagination">
                                        <div class="text-caption text-medium-emphasis">
                                            {{ dialogPaginationSummary(assignUsersDialog.members.length, assignUsersDialog.membersPage) }}
                                        </div>
                                        <v-pagination
                                            v-model="assignUsersDialog.membersPage"
                                            :length="dialogPaginationPageCount(assignUsersDialog.members.length)"
                                            :total-visible="5"
                                            active-color="primary"
                                            density="comfortable" />
                                    </div>
                                </div>
                            </template>
                        </section>

                        <section v-if="!assignUsersDialog.readOnly" class="groups-assign-section">
                            <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-2">
                                <div>
                                    <div class="admin-card-eyebrow">1. Benutzer:innen suchen</div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap ga-2 align-start mb-2">
                                <v-text-field
                                    v-model="assignUsersDialog.userSearchString"
                                    label="Benutzer suchen"
                                    clearable
                                    hide-details
                                    density="comfortable"
                                    class="flex-grow-1"
                                    @click:clear="searchAssignableUsers"
                                    @keyup.enter="searchAssignableUsers" />
                                <v-btn
                                    flat
                                    color="primary"
                                    prepend-icon="mdi-magnify"
                                    :loading="assignUsersDialog.userSearchLoading"
                                    @click="searchAssignableUsers">
                                    Suchen
                                </v-btn>
                            </div>

                            <div class="sa-empty" v-if="!assignUsersDialog.userSearchHasRun">
                                Bitte zuerst einen Benutzer suchen.
                            </div>
                            <div class="sa-empty" v-else-if="assignUsersDialog.userSearchResults.length === 0">
                                Keine Benutzer gefunden.
                            </div>
                            <div class="groups-assign-list" v-else>
                                <div class="groups-assign-list-item" v-for="user in assignUsersDialog.userSearchResults" :key="`assign-user-${user.id}`">
                                    <div class="min-w-0">
                                        <div class="font-weight-bold text-body-2">{{ user.name }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ user.email }}</div>
                                        <div class="text-caption text-medium-emphasis" v-if="user.schoolclass">Klasse: {{ user.schoolclass }}</div>
                                    </div>
                                    <div class="d-flex flex-wrap ga-1 justify-end">
                                        <v-chip
                                            v-if="user.already_member"
                                            color="secondary"
                                            variant="flat"
                                            size="x-small">
                                            zugeordnet
                                        </v-chip>
                                        <v-btn
                                            v-if="!user.already_member"
                                            flat
                                            color="success"
                                            size="small"
                                            prepend-icon="mdi-account-plus"
                                            :loading="isBusy"
                                            @click="assignUsersToCurrentGroup([user.id])">
                                            Zuordnen
                                        </v-btn>
                                        <v-btn
                                            v-else
                                            flat
                                            color="warning"
                                            size="small"
                                            prepend-icon="mdi-account-remove"
                                            :loading="isBusy && Number(assignUsersDialog.removingUserId) === Number(user.id)"
                                            @click="removeAssignedMember(user)">
                                            Entfernen
                                        </v-btn>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section v-if="!assignUsersDialog.readOnly" class="groups-assign-section">
                            <div class="admin-card-eyebrow mb-2">Weitere Möglichkeiten</div>
                            <v-expansion-panels
                                v-model="assignUsersDialog.extraPanel"
                                @update:modelValue="onExtraPanelChanged"
                                variant="accordion">
                                <v-expansion-panel value="group">
                                    <v-expansion-panel-title>
                                        <div class="d-flex align-center ga-2">
                                            <v-icon size="18">mdi-account-multiple-plus</v-icon>
                                            <span>2. Aus anderer Gruppe übernehmen</span>
                                        </div>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                            <div class="d-flex flex-wrap ga-2 align-start">
                                <v-select
                                    v-model="assignUsersDialog.sourceGroupId"
                                    :items="assignUsersDialog.sourceGroups"
                                    item-title="display_name"
                                    item-value="id"
                                    label="Quellgruppe"
                                    density="comfortable"
                                    hide-details
                                    class="flex-grow-1"
                                    :loading="assignUsersDialog.sourceGroupsLoading" />
                                <v-btn
                                    flat
                                    color="primary"
                                    prepend-icon="mdi-account-multiple-plus"
                                    :disabled="!assignUsersDialog.sourceGroupId"
                                    :loading="isBusy"
                                    @click="assignFromOtherGroup">
                                    Übernehmen
                                </v-btn>
                            </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>

                                <v-expansion-panel
                                    v-if="assignUsersDialog.group?.type === 'own'"
                                    value="my-courses">
                                    <v-expansion-panel-title>
                                        <div class="d-flex align-center ga-2">
                                            <v-icon size="18">mdi-book-education-outline</v-icon>
                                            <span>3. Aus meinen Fächern übernehmen</span>
                                        </div>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <div class="sa-empty" v-if="assignUsersDialog.myCoursesLoading">
                                            Lade meine Fächer ...
                                        </div>
                                        <div class="sa-empty" v-else-if="assignUsersDialog.myCourses.length === 0">
                                            Keine Fächer gefunden.
                                        </div>
                                        <div class="groups-assign-list" v-else>
                                            <div class="groups-assign-list-item d-block" v-for="course in assignUsersDialog.myCourses" :key="`my-course-${course.id}`">
                                                <div class="d-flex justify-space-between align-center flex-wrap ga-2">
                                                    <div class="d-inline-flex align-center flex-wrap ga-2 min-w-0">
                                                        <div class="font-weight-bold text-body-2">{{ course.title }}</div>
                                                        <v-chip color="secondary" variant="flat" size="x-small">
                                                            {{ course.students.length }}
                                                        </v-chip>
                                                        <v-chip
                                                            v-if="course.classes_label"
                                                            color="primary"
                                                            variant="flat"
                                                            size="x-small">
                                                            {{ course.classes_label }}
                                                        </v-chip>
                                                        <v-chip
                                                            v-if="myCourseAssignableIds(course).length > 0"
                                                            color="success"
                                                            variant="flat"
                                                            size="x-small">
                                                            {{ myCourseAssignableIds(course).length }} neu
                                                        </v-chip>
                                                    </div>
                                                    <div class="d-flex flex-wrap ga-1 justify-end">
                                                        <v-btn
                                                            flat
                                                            color="success"
                                                            size="small"
                                                            prepend-icon="mdi-account-multiple-plus"
                                                            :disabled="myCourseAssignableIds(course).length === 0"
                                                            :loading="isBusy"
                                                            @click="assignWholeMyCourse(course)">
                                                            Fach zuordnen
                                                        </v-btn>
                                                        <v-btn
                                                            flat
                                                            :color="course.expanded ? 'secondary' : 'primary'"
                                                            size="small"
                                                            :prepend-icon="course.expanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                                            @click="toggleMyCourseExpanded(course.id)">
                                                            {{ course.expanded ? 'Ausblenden' : 'Anzeigen' }}
                                                        </v-btn>
                                                    </div>
                                                </div>

                                                <div v-if="course.expanded" class="mt-3">
                                                    <div class="d-flex flex-wrap ga-2 mb-2">
                                                        <v-btn
                                                            flat
                                                            color="primary"
                                                            size="small"
                                                            prepend-icon="mdi-check-all"
                                                            @click="selectAllMyCourseStudents(course.id)">
                                                            Alle auswählen
                                                        </v-btn>
                                                        <v-btn
                                                            flat
                                                            color="secondary"
                                                            size="small"
                                                            prepend-icon="mdi-close-box-multiple-outline"
                                                            :disabled="course.selectedIds.length === 0"
                                                            @click="clearMyCourseSelection(course.id)">
                                                            Auswahl aufheben ({{ course.selectedIds.length }})
                                                        </v-btn>
                                                        <v-btn
                                                            flat
                                                            color="success"
                                                            size="small"
                                                            prepend-icon="mdi-account-multiple-plus"
                                                            :disabled="course.selectedIds.length === 0"
                                                            :loading="isBusy"
                                                            @click="assignSelectedMyCourseStudents(course.id)">
                                                            Ausgewählte zuordnen ({{ course.selectedIds.length }})
                                                        </v-btn>
                                                    </div>

                                                    <div class="groups-assign-list">
                                                        <div class="groups-assign-list-item" v-for="student in course.students" :key="`my-course-student-${course.id}-${student.import116_id || student.id}`">
                                                            <div class="d-flex align-start ga-2 min-w-0">
                                                                <v-checkbox-btn
                                                                    :model-value="isMyCourseStudentSelected(course.id, student.id)"
                                                                    :disabled="student.already_member || !student.has_user_account"
                                                                    color="primary"
                                                                    @update:model-value="toggleMyCourseStudentSelection(course.id, student.id)" />
                                                                <div class="min-w-0">
                                                                    <div class="font-weight-bold text-body-2">{{ student.name }}</div>
                                                                    <div class="text-caption text-medium-emphasis">{{ student.email }}</div>
                                                                    <div class="text-caption text-medium-emphasis" v-if="student.schoolclass">Klasse: {{ student.schoolclass }}</div>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex flex-wrap ga-1 justify-end">
                                                                <v-chip
                                                                    v-if="student.already_member"
                                                                    color="secondary"
                                                                    variant="flat"
                                                                    size="x-small">
                                                                    zugeordnet
                                                                </v-chip>
                                                                <v-chip
                                                                    v-else-if="!student.has_user_account"
                                                                    color="warning"
                                                                    variant="flat"
                                                                    size="x-small">
                                                                    kein Benutzerkonto
                                                                </v-chip>
                                                                <v-btn
                                                                    v-if="!student.already_member && student.has_user_account"
                                                                    flat
                                                                    color="success"
                                                                    size="small"
                                                                    prepend-icon="mdi-account-plus"
                                                                    :loading="isBusy"
                                                                    @click="assignUsersToCurrentGroup([student.user_id || student.id])">
                                                                    Zuordnen
                                                                </v-btn>
                                                                <v-btn
                                                                    v-else
                                                                    flat
                                                                    color="warning"
                                                                    size="small"
                                                                    prepend-icon="mdi-account-remove"
                                                                    :loading="isBusy && Number(assignUsersDialog.removingUserId) === Number(student.id)"
                                                                    @click="removeAssignedMember(student)">
                                                                    Entfernen
                                                                </v-btn>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>

                                <v-expansion-panel
                                    v-if="assignUsersDialog.group?.type === 'school'"
                                    value="teachers">
                                    <v-expansion-panel-title>
                                        <div class="d-flex align-center ga-2">
                                            <v-icon size="18">mdi-account-tie</v-icon>
                                            <span>3. Von Lehrer:innen übernehmen</span>
                                        </div>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                            <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-2">
                                <div></div>
                                <v-btn
                                    flat
                                    :color="assignUsersDialog.teacherShowAll ? 'secondary' : 'primary'"
                                    :prepend-icon="assignUsersDialog.teacherShowAll ? 'mdi-eye-off-outline' : 'mdi-account-group-outline'"
                                    @click="toggleTeacherShowAll">
                                    {{ assignUsersDialog.teacherShowAll ? 'Gesamtliste ausblenden' : 'Alle Lehrer anzeigen' }}
                                </v-btn>
                            </div>

                            <div class="d-flex flex-wrap ga-2 align-start mb-2">
                                <v-text-field
                                    v-model="assignUsersDialog.teacherSearchString"
                                    label="Lehrer suchen"
                                    clearable
                                    hide-details
                                    density="comfortable"
                                    class="flex-grow-1"
                                    @click:clear="onTeacherSearchCleared"
                                    @keyup.enter="searchAssignableTeachers" />
                                <v-btn
                                    flat
                                    color="primary"
                                    prepend-icon="mdi-magnify"
                                    :loading="assignUsersDialog.teacherSearchLoading"
                                    @click="searchAssignableTeachers">
                                    Suchen
                                </v-btn>
                            </div>

                            <div class="d-flex flex-wrap ga-2 mb-2" v-if="assignUsersDialog.teacherSearchHasRun && assignUsersDialog.teacherSearchResults.length > 0">
                                <v-btn
                                    flat
                                    color="primary"
                                    size="small"
                                    prepend-icon="mdi-check-all"
                                    @click="selectAllTeacherSearchResults">
                                    Alle auswählen
                                </v-btn>
                                <v-btn
                                    flat
                                    color="secondary"
                                    size="small"
                                    prepend-icon="mdi-close-box-multiple-outline"
                                    :disabled="assignUsersDialog.selectedTeacherIds.length === 0"
                                    @click="clearTeacherSearchSelection">
                                    Auswahl aufheben ({{ assignUsersDialog.selectedTeacherIds.length }})
                                </v-btn>
                                <v-btn
                                    flat
                                    color="success"
                                    size="small"
                                    prepend-icon="mdi-account-multiple-plus"
                                    :disabled="assignUsersDialog.selectedTeacherIds.length === 0"
                                    :loading="isBusy"
                                    @click="assignSelectedTeachers">
                                    Ausgewählte zuordnen ({{ assignUsersDialog.selectedTeacherIds.length }})
                                </v-btn>
                            </div>

                            <div class="sa-empty" v-if="!assignUsersDialog.teacherSearchHasRun">
                                Bitte Lehrer suchen oder "Alle Lehrer anzeigen" verwenden.
                            </div>
                            <div class="sa-empty" v-else-if="assignUsersDialog.teacherSearchResults.length === 0">
                                Keine Lehrer gefunden.
                            </div>
                            <div class="groups-assign-list" v-else>
                                <div class="groups-assign-list-item" v-for="teacher in assignUsersDialog.teacherSearchResults" :key="`assign-teacher-${teacher.id}`">
                                    <div class="d-flex align-start ga-2 min-w-0">
                                        <v-checkbox-btn
                                            :model-value="isTeacherSearchSelected(teacher.id)"
                                            :disabled="teacher.already_member"
                                            color="primary"
                                            @update:model-value="toggleTeacherSearchSelection(teacher.id)" />
                                        <div class="min-w-0">
                                            <div class="font-weight-bold text-body-2">{{ teacher.name }}</div>
                                            <div class="text-caption text-medium-emphasis">{{ teacher.email }}</div>
                                            <div class="text-caption text-medium-emphasis" v-if="teacher.schoolclass">Klasse: {{ teacher.schoolclass }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap ga-1 justify-end">
                                        <v-chip
                                            v-if="teacher.already_member"
                                            color="secondary"
                                            variant="flat"
                                            size="x-small">
                                            zugeordnet
                                        </v-chip>
                                        <v-btn
                                            v-if="!teacher.already_member"
                                            flat
                                            color="success"
                                            size="small"
                                            prepend-icon="mdi-account-plus"
                                            :loading="isBusy"
                                            @click="assignUsersToCurrentGroup([teacher.id])">
                                            Zuordnen
                                        </v-btn>
                                        <v-btn
                                            v-else
                                            flat
                                            color="warning"
                                            size="small"
                                            prepend-icon="mdi-account-remove"
                                            :loading="isBusy && Number(assignUsersDialog.removingUserId) === Number(teacher.id)"
                                            @click="removeAssignedMember(teacher)">
                                            Entfernen
                                        </v-btn>
                                    </div>
                                </div>
                            </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>

                                <v-expansion-panel
                                    v-if="assignUsersDialog.group?.type === 'school'"
                                    value="students">
                                    <v-expansion-panel-title>
                                        <div class="d-flex align-center ga-2">
                                            <v-icon size="18">mdi-account-school-outline</v-icon>
                                            <span>4. Von Schüler:innen übernehmen</span>
                                        </div>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                            <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-2">
                                <div class="text-caption text-medium-emphasis">Option 1: Suchen</div>
                            </div>

                            <div class="d-flex flex-wrap ga-2 align-start mb-2">
                                <v-text-field
                                    v-model="assignUsersDialog.studentSearchString"
                                    label="Schüler:in suchen"
                                    clearable
                                    hide-details
                                    density="comfortable"
                                    class="flex-grow-1"
                                    @click:clear="searchAssignableStudents"
                                    @keyup.enter="searchAssignableStudents" />
                                <v-btn
                                    flat
                                    color="primary"
                                    prepend-icon="mdi-magnify"
                                    :loading="assignUsersDialog.studentSearchLoading"
                                    @click="searchAssignableStudents">
                                    Suchen
                                </v-btn>
                            </div>

                            <div class="sa-empty" v-if="!assignUsersDialog.studentSearchHasRun">
                                Bitte zuerst Schüler:innen suchen.
                            </div>
                            <div class="sa-empty" v-else-if="assignUsersDialog.studentSearchResults.length === 0">
                                Keine Schüler:innen gefunden.
                            </div>
                            <div class="groups-assign-list mb-3" v-else>
                                <div class="groups-assign-list-item" v-for="student in assignUsersDialog.studentSearchResults" :key="`assign-student-${student.import116_id || student.id}`">
                                    <div class="min-w-0">
                                        <div class="font-weight-bold text-body-2">{{ student.name }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ student.email }}</div>
                                        <div class="text-caption text-medium-emphasis" v-if="student.schoolclass">Klasse: {{ student.schoolclass }}</div>
                                    </div>
                                    <div class="d-flex flex-wrap ga-1 justify-end">
                                        <v-chip
                                            v-if="student.already_member"
                                            color="secondary"
                                            variant="flat"
                                            size="x-small">
                                            zugeordnet
                                        </v-chip>
                                        <v-chip
                                            v-else-if="!student.has_user_account"
                                            color="warning"
                                            variant="flat"
                                            size="x-small">
                                            kein Benutzerkonto
                                        </v-chip>
                                        <v-btn
                                            v-if="!student.already_member && student.has_user_account"
                                            flat
                                            color="success"
                                            size="small"
                                            prepend-icon="mdi-account-plus"
                                            :loading="isBusy"
                                            @click="assignUsersToCurrentGroup([student.user_id || student.id])">
                                            Zuordnen
                                        </v-btn>
                                        <v-btn
                                            v-else
                                            flat
                                            color="warning"
                                            size="small"
                                            prepend-icon="mdi-account-remove"
                                            :loading="isBusy && Number(assignUsersDialog.removingUserId) === Number(student.id)"
                                            @click="removeAssignedMember(student)">
                                            Entfernen
                                        </v-btn>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-2">
                                <div class="text-caption text-medium-emphasis">Option 2: Klassen anzeigen</div>
                                <v-btn
                                    flat
                                    :color="assignUsersDialog.studentClassesVisible ? 'secondary' : 'primary'"
                                    :prepend-icon="assignUsersDialog.studentClassesVisible ? 'mdi-eye-off-outline' : 'mdi-google-classroom'"
                                    @click="toggleStudentClassesVisible">
                                    {{ assignUsersDialog.studentClassesVisible ? 'Klassen ausblenden' : 'Klassen anzeigen' }}
                                </v-btn>
                            </div>

                            <div v-if="assignUsersDialog.studentClassesVisible">
                                <div class="sa-empty" v-if="assignUsersDialog.studentClassesLoading">
                                    Lade Klassen ...
                                </div>
                                <div class="sa-empty" v-else-if="assignUsersDialog.studentClasses.length === 0">
                                    Keine Klassen mit Schüler:innen gefunden.
                                </div>
                                <div class="groups-assign-list" v-else>
                                    <div class="groups-assign-list-item d-block" v-for="classGroup in assignUsersDialog.studentClasses" :key="`student-class-${classGroup.key}`">
                                        <div class="d-flex justify-space-between align-center flex-wrap ga-2">
                                            <div class="d-inline-flex align-center flex-wrap ga-2 min-w-0">
                                                <div class="font-weight-bold text-body-2">{{ classGroup.label }}</div>
                                                <v-chip color="secondary" variant="flat" size="x-small">
                                                    {{ classGroup.students.length }}
                                                </v-chip>
                                                <v-chip
                                                    v-if="studentClassAssignableIds(classGroup).length > 0"
                                                    color="success"
                                                    variant="flat"
                                                    size="x-small">
                                                    {{ studentClassAssignableIds(classGroup).length }} neu
                                                </v-chip>
                                                <v-chip
                                                    v-if="studentClassAssignedCount(classGroup) > 0"
                                                    color="warning"
                                                    variant="flat"
                                                    size="x-small">
                                                    {{ studentClassAssignedCount(classGroup) }} zugeordnet
                                                </v-chip>
                                            </div>
                                            <div class="d-flex flex-wrap ga-1 justify-end">
                                                <v-btn
                                                    flat
                                                    color="success"
                                                    size="small"
                                                    prepend-icon="mdi-account-multiple-plus"
                                                    :disabled="studentClassAssignableIds(classGroup).length === 0"
                                                    :loading="isBusy"
                                                    @click="assignWholeStudentClass(classGroup)">
                                                    Klasse zuordnen
                                                </v-btn>
                                                <v-btn
                                                    flat
                                                    :color="classGroup.expanded ? 'secondary' : 'primary'"
                                                    size="small"
                                                    :prepend-icon="classGroup.expanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                                    @click="toggleStudentClassExpanded(classGroup.key)">
                                                    {{ classGroup.expanded ? 'Ausblenden' : 'Anzeigen' }}
                                                </v-btn>
                                            </div>
                                        </div>

                                        <div v-if="classGroup.expanded" class="mt-3">
                                            <div class="text-caption text-medium-emphasis mb-2">Option 3: Schüler:innen auswählen</div>
                                            <div class="d-flex flex-wrap ga-2 mb-2">
                                                <v-btn
                                                    flat
                                                    color="primary"
                                                    size="small"
                                                    prepend-icon="mdi-check-all"
                                                    @click="selectAllStudentsInClass(classGroup.key)">
                                                    Alle auswählen
                                                </v-btn>
                                                <v-btn
                                                    flat
                                                    color="secondary"
                                                    size="small"
                                                    prepend-icon="mdi-close-box-multiple-outline"
                                                    :disabled="classGroup.selectedIds.length === 0"
                                                    @click="clearStudentClassSelection(classGroup.key)">
                                                    Auswahl aufheben ({{ classGroup.selectedIds.length }})
                                                </v-btn>
                                                <v-btn
                                                    flat
                                                    color="success"
                                                    size="small"
                                                    prepend-icon="mdi-account-multiple-plus"
                                                    :disabled="classGroup.selectedIds.length === 0"
                                                    :loading="isBusy"
                                                    @click="assignSelectedStudentsInClass(classGroup.key)">
                                                    Ausgewählte zuordnen ({{ classGroup.selectedIds.length }})
                                                </v-btn>
                                            </div>

                                            <div class="groups-assign-list">
                                                <div class="groups-assign-list-item" v-for="student in classGroup.students" :key="`class-student-${classGroup.key}-${student.import116_id || student.id}`">
                                                    <div class="d-flex align-start ga-2 min-w-0">
                                                        <v-checkbox-btn
                                                            :model-value="isStudentInClassSelected(classGroup.key, student.id)"
                                                            :disabled="student.already_member || !student.has_user_account"
                                                            color="primary"
                                                            @update:model-value="toggleStudentInClassSelection(classGroup.key, student.id)" />
                                                        <div class="min-w-0">
                                                            <div class="font-weight-bold text-body-2">{{ student.name }}</div>
                                                            <div class="text-caption text-medium-emphasis">{{ student.email }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex flex-wrap ga-1 justify-end">
                                                        <v-chip
                                                            v-if="student.already_member"
                                                            color="secondary"
                                                            variant="flat"
                                                            size="x-small">
                                                            zugeordnet
                                                        </v-chip>
                                                        <v-chip
                                                            v-else-if="!student.has_user_account"
                                                            color="warning"
                                                            variant="flat"
                                                            size="x-small">
                                                            kein Benutzerkonto
                                                        </v-chip>
                                                        <v-btn
                                                            v-if="!student.already_member && student.has_user_account"
                                                            flat
                                                            color="success"
                                                            size="small"
                                                            prepend-icon="mdi-account-plus"
                                                            :loading="isBusy"
                                                            @click="assignUsersToCurrentGroup([student.user_id || student.id])">
                                                            Zuordnen
                                                        </v-btn>
                                                        <v-btn
                                                            v-else
                                                            flat
                                                            color="warning"
                                                            size="small"
                                                            prepend-icon="mdi-account-remove"
                                                            :loading="isBusy && Number(assignUsersDialog.removingUserId) === Number(student.id)"
                                                            @click="removeAssignedMember(student)">
                                                            Entfernen
                                                        </v-btn>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </section>
                    </div>
                </v-card-text>
                <v-card-actions class="d-flex justify-end">
                    <v-btn color="warning" variant="text" @click="closeAssignUsersDialog">Schließen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import axios from 'axios'
import { mapWritableState } from 'pinia'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export default {
    components: { AdminSectionHero },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.notificationStore = useNotificationStore()
        await this.loadGroups()
    },

    data() {
        return {
            adminStore: null,
            notificationStore: null,
            isBusy: false,
            groups: [],
            apiPermissions: {},
            groupSections: [
                {
                    type: 'school',
                    eyebrow: 'System',
                    label: 'Schulgruppen',
                    permissionText: 'Bearbeitung nur durch super_admin und admin.',
                },
                {
                    type: 'materials',
                    eyebrow: 'Materialientool',
                    label: 'Materialiengruppen',
                    permissionText: 'Bearbeitung durch super_admin, admin und materials_admin.',
                },
                {
                    type: 'own',
                    eyebrow: 'Persönlich',
                    label: 'Eigene Gruppen',
                    permissionText: 'Bearbeitung durch super_admin, admin, materials_admin und materials_moderator.',
                },
            ],
            selectedGroupIdsByType: {
                school: null,
                materials: null,
                own: null,
            },
            schoolSectionPanelsOpen: [],
            ownSectionPanelsOpen: [],
            editDialog: {
                open: false,
                mode: 'create',
                valid: false,
                form: {
                    id: null,
                    type: 'own',
                    name: '',
                    description: '',
                },
            },
            deleteDialog: {
                open: false,
                group: null,
            },
            assignUsersDialog: {
                open: false,
                group: null,
                membersExpanded: false,
                membersPage: 1,
                members: [],
                membersLoading: false,
                selectedMemberIds: [],
                removingUserId: null,
                bulkRemoving: false,
                extraPanel: null,
                userSearchString: '',
                userSearchResults: [],
                userSearchHasRun: false,
                userSearchLoading: false,
                teacherSearchString: '',
                teacherSearchResults: [],
                teacherSearchHasRun: false,
                teacherSearchLoading: false,
                selectedTeacherIds: [],
                teacherShowAll: false,
                myCoursesLoading: false,
                myCoursesLoaded: false,
                myCourses: [],
                studentSearchString: '',
                studentSearchResults: [],
                studentSearchHasRun: false,
                studentSearchLoading: false,
                studentClassesVisible: false,
                studentClassesLoading: false,
                studentClasses: [],
                sourceGroups: [],
                sourceGroupsLoading: false,
                sourceGroupId: null,
                sourceMembers: [],
                sourceMembersLoading: false,
                readOnly: false,
                readOnlyPage: 1,
                readOnlyPanel: null,
            },
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading']),
        selectedSchoolLabel() {
            return this.config?.selected_school?.long_name || this.config?.selected_school?.short_name || 'Keine Schule gewählt'
        },
        headerChips() {
            return [
                {
                    key: 'school',
                    text: this.selectedSchoolLabel,
                    icon: 'mdi-domain',
                },
                {
                    key: 'groups',
                    text: `${this.groups.length} Gruppen`,
                    icon: 'mdi-account-group-outline',
                },
            ]
        },
        headerActiveSection() {
            return {
                icon: 'mdi-account-group-outline',
                label: 'Schulgruppen, Materialien, Eigene',
                note: 'Löschen nur ohne Mitglieder möglich.',
            }
        },
        userRoles() {
            return Array.isArray(this.config?.roles) ? this.config.roles : []
        },
        requiredRule() {
            return (v) => (!!String(v || '').trim() ? true : 'Pflichtfeld')
        },
    },

    methods: {
        hasAnyRole(roles) {
            return roles.some((role) => this.userRoles.includes(role))
        },

        fallbackCanManageType(type) {
            if (this.hasAnyRole(['super_admin', 'admin'])) return true
            if (type === 'materials' && this.userRoles.includes('materials_admin')) return true
            if (type === 'own' && this.hasAnyRole(['materials_admin', 'materials_moderator'])) return true
            return false
        },

        canManageType(type) {
            if (Object.prototype.hasOwnProperty.call(this.apiPermissions || {}, type)) {
                return !!this.apiPermissions[type]
            }
            return this.fallbackCanManageType(type)
        },

        groupsByType(type) {
            return this.groups.filter((group) => group.type === type)
        },

        isTeacherSchoolGroup(group) {
            const normalizedName = String(group?.name || '').trim().toLowerCase()
            return normalizedName === 'lehrer' || normalizedName === 'teacher'
        },

        isParentSchoolGroup(group) {
            if (!group || group.type !== 'school') return false
            if (group.is_parent_group === true) return true
            const normalizedName = String(group?.name || '').trim().toLowerCase()
            return normalizedName.endsWith(' eltern')
        },

        isAllSchoolMembersGroup(group) {
            if (!group || group.type !== 'school') return false
            if (group.is_all_school_members_group === true) return true
            return String(group?.name || '').trim().toLowerCase() === 'alle schulmitglieder'
        },

        isAutomaticOwnCourseParentGroup(group) {
            return group?.type === 'own'
                && !!group?.teaching_course_id
                && (group?.teaching_course_group_type === 'parents' || group?.is_parent_group === true)
        },

        isParentGroup(group) {
            return this.isParentSchoolGroup(group) || this.isAutomaticOwnCourseParentGroup(group)
        },

        isAutomaticSchoolGroup(group) {
            return group?.type === 'school' && group?.is_system_default === true
        },

        showSourceUsersCounter(group) {
            return group?.source_users_count !== null && group?.source_users_count !== undefined
        },

        schoolSectionPanels() {
            const schoolGroups = this.groupsByType('school')

            return [
                {
                    key: 'classes',
                    title: 'Klassen',
                    metaLabel: '(automatisch erstellt)',
                    icon: 'mdi-google-classroom',
                    groups: schoolGroups.filter((group) => this.isAutomaticSchoolGroup(group) && !this.isTeacherSchoolGroup(group) && !this.isParentSchoolGroup(group) && !this.isAllSchoolMembersGroup(group)),
                },
                {
                    key: 'teachers',
                    title: 'Lehrer',
                    metaLabel: '(automatisch erstellt)',
                    icon: 'mdi-account-tie',
                    groups: schoolGroups.filter((group) => this.isAutomaticSchoolGroup(group) && this.isTeacherSchoolGroup(group)),
                },
                {
                    key: 'parents',
                    title: 'Eltern',
                    metaLabel: '(automatisch erstellt)',
                    icon: 'mdi-account-multiple-outline',
                    groups: schoolGroups.filter((group) => this.isAutomaticSchoolGroup(group) && this.isParentSchoolGroup(group)),
                },
                {
                    key: 'school-members',
                    title: 'Gesamtgruppen',
                    metaLabel: '(automatisch erstellt)',
                    icon: 'mdi-account-school-outline',
                    groups: schoolGroups.filter((group) => this.isAutomaticSchoolGroup(group) && this.isAllSchoolMembersGroup(group)),
                },
                {
                    key: 'other-school-groups',
                    title: 'Weitere Gruppen',
                    metaLabel: null,
                    icon: 'mdi-shape-outline',
                    groups: schoolGroups.filter((group) => !this.isAutomaticSchoolGroup(group)),
                },
            ]
        },

        isAutomaticOwnCourseGroup(group) {
            return group?.type === 'own' && !!group?.teaching_course_id
        },

        ownSectionPanels() {
            const ownGroups = this.groupsByType('own')

            return [
                {
                    key: 'course-groups',
                    title: 'Kursgruppen',
                    metaLabel: '(automatisch erstellt)',
                    icon: 'mdi-google-classroom',
                    groups: ownGroups.filter((group) => this.isAutomaticOwnCourseGroup(group) && !this.isAutomaticOwnCourseParentGroup(group)),
                },
                {
                    key: 'course-parent-groups',
                    title: 'Eltern Kursgruppen',
                    metaLabel: '(automatisch erstellt)',
                    icon: 'mdi-account-multiple-outline',
                    groups: ownGroups.filter((group) => this.isAutomaticOwnCourseParentGroup(group)),
                },
                {
                    key: 'own-groups',
                    title: 'Eigene Gruppen',
                    metaLabel: null,
                    icon: 'mdi-account-group-outline',
                    groups: ownGroups.filter((group) => !this.isAutomaticOwnCourseGroup(group)),
                },
            ]
        },

        selectedGroupByType(type) {
            const selectedId = this.selectedGroupIdsByType?.[type] ?? null
            if (!selectedId) return null
            return this.groups.find((group) => group.type === type && Number(group.id) === Number(selectedId)) || null
        },

        currentTypeLabel(type) {
            return this.groupSections.find((section) => section.type === type)?.label || type
        },

        notifyError(error, fallbackMessage = 'Fehler passiert.') {
            this.notificationStore?.notify({
                status: error?.response?.status || 500,
                message: error?.response?.data?.message || fallbackMessage,
                type: 'error',
                timeout: this.config?.timeout,
            })
        },

        notifySuccess(message) {
            this.notificationStore?.notify({
                message,
                type: 'success',
                timeout: 2500,
            })
        },

        async loadGroups() {
            this.isBusy = true
            this.is_loading++
            try {
                const response = await axios.get('/api/admin/groups')
                this.groups = Array.isArray(response.data?.data) ? response.data.data : []
                this.apiPermissions = response.data?.meta?.permissions || {}
                this.cleanupSelectedGroups()
                this.refreshAssignDialogGroupReference()
            } catch (error) {
                this.groups = []
                this.apiPermissions = {}
                this.selectedGroupIdsByType = { school: null, materials: null, own: null }
                this.refreshAssignDialogGroupReference()
                this.notifyError(error, 'Gruppen konnten nicht geladen werden.')
            } finally {
                this.is_loading--
                this.isBusy = false
            }
        },

        openCreateDialog(type) {
            if (!this.canManageType(type)) return
            this.editDialog.mode = 'create'
            this.editDialog.form = { id: null, type, name: '', description: '' }
            this.editDialog.valid = false
            this.editDialog.open = true
        },

        openEditDialog(group) {
            if (!this.canManageType(group.type)) return
            this.editDialog.mode = 'edit'
            this.editDialog.form = {
                id: group.id,
                type: group.type,
                name: group.name || '',
                description: group.description || '',
            }
            this.editDialog.valid = false
            this.editDialog.open = true
        },

        closeEditDialog() {
            this.editDialog.open = false
        },

        async saveGroup() {
            if (this.isBusy) return

            const formEl = this.$refs.groupForm
            if (formEl?.validate) {
                const result = await formEl.validate()
                const valid = typeof result === 'object' ? result.valid : this.editDialog.valid
                if (!valid) return
            }

            const payload = {
                type: this.editDialog.form.type,
                name: String(this.editDialog.form.name || '').trim(),
                description: String(this.editDialog.form.description || '').trim() || null,
            }

            this.isBusy = true
            this.is_loading++
            try {
                if (this.editDialog.mode === 'create') {
                    await axios.post('/api/admin/groups', payload)
                    this.notifySuccess('Gruppe erstellt.')
                } else {
                    await axios.put(`/api/admin/groups/${this.editDialog.form.id}`, {
                        name: payload.name,
                        description: payload.description,
                    })
                    this.notifySuccess('Gruppe gespeichert.')
                }

                this.closeEditDialog()
                await this.loadGroups()
            } catch (error) {
                this.notifyError(error, 'Gruppe konnte nicht gespeichert werden.')
            } finally {
                this.is_loading--
                this.isBusy = false
            }
        },

        openDeleteDialog(group) {
            this.deleteDialog.group = group
            this.deleteDialog.open = true
        },

        closeDeleteDialog() {
            this.deleteDialog.open = false
            this.deleteDialog.group = null
        },

        async deleteGroup() {
            const group = this.deleteDialog.group
            if (!group || this.isBusy || this.isDeleteDisabled(group)) return

            this.isBusy = true
            this.is_loading++
            try {
                await axios.delete(`/api/admin/groups/${group.id}`)
                this.notifySuccess('Gruppe gelöscht.')
                this.closeDeleteDialog()
                await this.loadGroups()
            } catch (error) {
                this.notifyError(error, 'Gruppe konnte nicht gelöscht werden.')
            } finally {
                this.is_loading--
                this.isBusy = false
            }
        },
        isDeleteDisabled(group) {
            return group?.can_delete === false
        },
        showDeleteMembersWarning(group) {
            return this.isDeleteDisabled(group) && Number(group?.members_count || 0) > 0
        },
        deleteButtonTitle(group) {
            if (this.showDeleteMembersWarning(group)) {
                return 'Nur ohne Mitglieder löschbar'
            }
            return `${group?.name || 'Gruppe'} löschen`
        },
        showInlineMemberManagementButton(group) {
            return !!group && group?.can_manage_members !== false
        },
        showReadOnlyAutoManagedNotice() {
            return this.assignUsersDialog.readOnly
                && this.assignUsersDialog.group?.can_manage_members === false
        },
        onGroupRowClick(type, group) {
            if (!group || group.type !== type) return
            this.selectedGroupIdsByType[type] = group.id
            this.openAssignUsersDialog(group, { readOnly: true })
        },
        openManageMembersDialog(type, group) {
            if (!group || group.type !== type || !this.showInlineMemberManagementButton(group)) return
            this.selectedGroupIdsByType[type] = group.id
            this.openAssignUsersDialog(group)
        },
        selectGroup(type, group) {
            if (!group || group.type !== type) return
            this.selectedGroupIdsByType[type] = group.id
        },
        showSourceMembersPanel() {
            const group = this.assignUsersDialog.group
            if (!group) return false
            if (this.isAutomaticOwnCourseGroup(group)) return true
            return group.type === 'school'
        },
        readOnlyCombinedMembers() {
            const registeredMembers = Array.isArray(this.assignUsersDialog?.members)
                ? this.assignUsersDialog.members
                : []
            const sourceMembers = Array.isArray(this.assignUsersDialog?.sourceMembers)
                ? this.assignUsersDialog.sourceMembers
                : []

            if (!this.showSourceMembersPanel()) {
                return this.sortReadOnlyMembers(registeredMembers.map((member) => ({
                    ...member,
                    is_registered: true,
                })))
            }

            const registeredByKey = new Map()
            for (const member of registeredMembers) {
                const key = this.readOnlyMemberKey(member)
                if (!key) continue
                registeredByKey.set(key, member)
            }

            const seenKeys = new Set()
            const combined = sourceMembers.map((member) => {
                const key = this.readOnlyMemberKey(member)
                if (key) {
                    seenKeys.add(key)
                }
                const registeredMember = key ? registeredByKey.get(key) : null

                return this.mergeReadOnlyMember(member, registeredMember, !!registeredMember || member?.already_member === true)
            })

            for (const member of registeredMembers) {
                const key = this.readOnlyMemberKey(member)
                if (key && seenKeys.has(key)) continue
                combined.push(this.mergeReadOnlyMember(null, member, true))
            }

            return this.sortReadOnlyMembers(combined)
        },
        paginatedReadOnlyCombinedMembers() {
            return this.dialogPaginationSlice(
                this.readOnlyCombinedMembers(),
                this.assignUsersDialog.readOnlyPage,
            )
        },
        paginatedAssignedMembers() {
            return this.dialogPaginationSlice(
                this.assignUsersDialog.members,
                this.assignUsersDialog.membersPage,
            )
        },
        readOnlyCombinedMembersLoading() {
            return this.assignUsersDialog.membersLoading
                || (this.showSourceMembersPanel() && this.assignUsersDialog.sourceMembersLoading)
        },
        readOnlyCombinedMembersLoadingText() {
            return 'Lade Mitglieder ...'
        },
        readOnlyCombinedMembersEmptyText() {
            if (this.isAllSchoolMembersGroup(this.assignUsersDialog.group)) {
                return 'Keine Schulmitglieder gefunden.'
            }
            return 'Keine Mitglieder gefunden.'
        },
        readOnlyMemberKey(member) {
            if (!member) return null
            if (Number(member?.user_id || 0) > 0) {
                return `user:${Number(member.user_id)}`
            }
            if (Number(member?.import116_id || 0) > 0) {
                return `import:${Number(member.import116_id)}`
            }
            if (member?.id !== undefined && member?.id !== null && String(member.id) !== '') {
                return `id:${String(member.id)}`
            }
            return null
        },
        mergeReadOnlyMember(sourceMember, registeredMember, isRegistered) {
            const member = {
                ...(registeredMember || {}),
                ...(sourceMember || {}),
            }

            return {
                ...member,
                is_registered: !!isRegistered,
            }
        },
        sortReadOnlyMembers(members) {
            return [...members].sort((left, right) => {
                const leftLastName = String(left?.last_name || '').trim().toLocaleLowerCase('de')
                const rightLastName = String(right?.last_name || '').trim().toLocaleLowerCase('de')
                if (leftLastName !== rightLastName) {
                    return leftLastName.localeCompare(rightLastName, 'de', { sensitivity: 'base', numeric: true })
                }

                const leftFirstName = String(left?.first_name || '').trim().toLocaleLowerCase('de')
                const rightFirstName = String(right?.first_name || '').trim().toLocaleLowerCase('de')
                if (leftFirstName !== rightFirstName) {
                    return leftFirstName.localeCompare(rightFirstName, 'de', { sensitivity: 'base', numeric: true })
                }

                const leftName = String(left?.name || '').trim().toLocaleLowerCase('de')
                const rightName = String(right?.name || '').trim().toLocaleLowerCase('de')
                return leftName.localeCompare(rightName, 'de', { sensitivity: 'base', numeric: true })
            })
        },
        dialogPaginationSize() {
            return 100
        },
        shouldPaginateDialogList(totalItems) {
            return Number(totalItems || 0) > this.dialogPaginationSize()
        },
        dialogPaginationPageCount(totalItems) {
            return Math.max(1, Math.ceil(Number(totalItems || 0) / this.dialogPaginationSize()))
        },
        normalizedDialogPage(page, totalItems) {
            return Math.min(
                Math.max(Number(page || 1), 1),
                this.dialogPaginationPageCount(totalItems),
            )
        },
        dialogPaginationSlice(items, page) {
            const rows = Array.isArray(items) ? items : []
            if (!this.shouldPaginateDialogList(rows.length)) {
                return rows
            }

            const currentPage = this.normalizedDialogPage(page, rows.length)
            const start = (currentPage - 1) * this.dialogPaginationSize()

            return rows.slice(start, start + this.dialogPaginationSize())
        },
        dialogPaginationSummary(totalItems, page) {
            const total = Number(totalItems || 0)
            if (total <= 0) {
                return ''
            }

            const currentPage = this.normalizedDialogPage(page, total)
            const start = ((currentPage - 1) * this.dialogPaginationSize()) + 1
            const end = Math.min(currentPage * this.dialogPaginationSize(), total)

            return `${start}-${end} von ${total}`
        },
        syncDialogPagination() {
            this.assignUsersDialog.membersPage = this.normalizedDialogPage(
                this.assignUsersDialog.membersPage,
                this.assignUsersDialog.members.length,
            )
            this.assignUsersDialog.readOnlyPage = this.normalizedDialogPage(
                this.assignUsersDialog.readOnlyPage,
                this.readOnlyCombinedMembers().length,
            )
        },
        isSelectedGroup(type, groupId) {
            return Number(this.selectedGroupIdsByType?.[type] || 0) === Number(groupId)
        },
        cleanupSelectedGroups() {
            const next = { ...this.selectedGroupIdsByType }
            for (const section of this.groupSections) {
                const type = section.type
                const selectedId = next[type]
                if (!selectedId) continue
                const exists = this.groups.some((group) => group.type === type && Number(group.id) === Number(selectedId))
                if (!exists) next[type] = null
            }
            this.selectedGroupIdsByType = next
        },
        openAssignUsersDialog(group, options = {}) {
            if (!group) return
            const readOnly = !!options.readOnly
            if (!readOnly && group.can_manage_members === false) {
                this.notifyError(
                    {
                        response: {
                            status: 409,
                            data: {
                                message: 'Standard-Schulgruppen werden automatisch verwaltet und können nicht manuell bearbeitet werden.',
                            },
                        },
                    },
                    'Benutzer können für diese Gruppe nicht manuell zugeordnet werden.',
                )
                return
            }
            this.assignUsersDialog.group = group
            this.assignUsersDialog.readOnly = readOnly
            this.assignUsersDialog.membersExpanded = false
            this.assignUsersDialog.membersPage = 1
            this.assignUsersDialog.members = []
            this.assignUsersDialog.membersLoading = false
            this.assignUsersDialog.selectedMemberIds = []
            this.assignUsersDialog.removingUserId = null
            this.assignUsersDialog.bulkRemoving = false
            this.assignUsersDialog.extraPanel = null
            this.assignUsersDialog.userSearchString = ''
            this.assignUsersDialog.userSearchResults = []
            this.assignUsersDialog.userSearchHasRun = false
            this.assignUsersDialog.teacherSearchString = ''
            this.assignUsersDialog.teacherSearchResults = []
            this.assignUsersDialog.teacherSearchHasRun = false
            this.assignUsersDialog.teacherSearchLoading = false
            this.assignUsersDialog.selectedTeacherIds = []
            this.assignUsersDialog.teacherShowAll = false
            this.assignUsersDialog.myCoursesLoading = false
            this.assignUsersDialog.myCoursesLoaded = false
            this.assignUsersDialog.myCourses = []
            this.assignUsersDialog.studentSearchString = ''
            this.assignUsersDialog.studentSearchResults = []
            this.assignUsersDialog.studentSearchHasRun = false
            this.assignUsersDialog.studentSearchLoading = false
            this.assignUsersDialog.studentClassesVisible = false
            this.assignUsersDialog.studentClassesLoading = false
            this.assignUsersDialog.studentClasses = []
            this.assignUsersDialog.sourceGroups = []
            this.assignUsersDialog.sourceGroupId = null
            this.assignUsersDialog.sourceMembers = []
            this.assignUsersDialog.sourceMembersLoading = false
            this.assignUsersDialog.readOnlyPage = 1
            this.assignUsersDialog.readOnlyPanel = null
            this.assignUsersDialog.open = true
            this.loadAssignedMembers()
            if (readOnly) {
                if (this.showSourceMembersPanel()) {
                    this.loadSourceMembers()
                }
            } else {
                this.loadAssignableGroups()
            }
        },
        closeAssignUsersDialog() {
            this.assignUsersDialog.open = false
            this.assignUsersDialog.group = null
            this.assignUsersDialog.membersExpanded = false
            this.assignUsersDialog.membersPage = 1
            this.assignUsersDialog.members = []
            this.assignUsersDialog.membersLoading = false
            this.assignUsersDialog.selectedMemberIds = []
            this.assignUsersDialog.removingUserId = null
            this.assignUsersDialog.bulkRemoving = false
            this.assignUsersDialog.extraPanel = null
            this.assignUsersDialog.userSearchString = ''
            this.assignUsersDialog.userSearchResults = []
            this.assignUsersDialog.userSearchHasRun = false
            this.assignUsersDialog.teacherSearchString = ''
            this.assignUsersDialog.teacherSearchResults = []
            this.assignUsersDialog.teacherSearchHasRun = false
            this.assignUsersDialog.teacherSearchLoading = false
            this.assignUsersDialog.selectedTeacherIds = []
            this.assignUsersDialog.teacherShowAll = false
            this.assignUsersDialog.myCoursesLoading = false
            this.assignUsersDialog.myCoursesLoaded = false
            this.assignUsersDialog.myCourses = []
            this.assignUsersDialog.studentSearchString = ''
            this.assignUsersDialog.studentSearchResults = []
            this.assignUsersDialog.studentSearchHasRun = false
            this.assignUsersDialog.studentSearchLoading = false
            this.assignUsersDialog.studentClassesVisible = false
            this.assignUsersDialog.studentClassesLoading = false
            this.assignUsersDialog.studentClasses = []
            this.assignUsersDialog.sourceGroups = []
            this.assignUsersDialog.sourceGroupId = null
            this.assignUsersDialog.sourceMembers = []
            this.assignUsersDialog.sourceMembersLoading = false
            this.assignUsersDialog.readOnly = false
            this.assignUsersDialog.readOnlyPage = 1
            this.assignUsersDialog.readOnlyPanel = null
        },
        refreshAssignDialogGroupReference() {
            if (!this.assignUsersDialog?.group?.id) return
            const nextGroup = this.groups.find((row) => Number(row.id) === Number(this.assignUsersDialog.group.id)) || null
            this.assignUsersDialog.group = nextGroup
            if (!nextGroup) {
                this.closeAssignUsersDialog()
            }
        },
        async loadAssignedMembers() {
            const group = this.assignUsersDialog.group
            if (!group?.id) return
            this.assignUsersDialog.membersLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/members`)
                this.assignUsersDialog.members = Array.isArray(response.data?.data) ? response.data.data : []
                this.syncAssignedMemberSelection()
            } catch (error) {
                this.assignUsersDialog.members = []
                this.assignUsersDialog.selectedMemberIds = []
                this.notifyError(error, 'Zugeordnete Benutzer konnten nicht geladen werden.')
            } finally {
                this.syncDialogPagination()
                this.assignUsersDialog.membersLoading = false
            }
        },
        async loadSourceMembers() {
            const group = this.assignUsersDialog.group
            if (!group?.id || !this.showSourceMembersPanel()) {
                this.assignUsersDialog.sourceMembers = []
                this.assignUsersDialog.sourceMembersLoading = false
                return
            }
            this.assignUsersDialog.sourceMembersLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/source-members`)
                this.assignUsersDialog.sourceMembers = Array.isArray(response.data?.data) ? response.data.data : []
            } catch (error) {
                this.assignUsersDialog.sourceMembers = []
                this.notifyError(error, 'Import116-Mitglieder konnten nicht geladen werden.')
            } finally {
                this.syncDialogPagination()
                this.assignUsersDialog.sourceMembersLoading = false
            }
        },
        isAssignedMemberSelected(userId) {
            return this.assignUsersDialog.selectedMemberIds.some((id) => Number(id) === Number(userId))
        },
        toggleAssignedMemberSelection(userId) {
            const id = Number(userId)
            const selected = [...this.assignUsersDialog.selectedMemberIds]
            const index = selected.findIndex((item) => Number(item) === id)
            if (index >= 0) {
                selected.splice(index, 1)
            } else {
                selected.push(id)
            }
            this.assignUsersDialog.selectedMemberIds = selected
        },
        selectAllAssignedMembers() {
            this.assignUsersDialog.selectedMemberIds = this.assignUsersDialog.members.map((member) => Number(member.id))
        },
        clearAssignedMemberSelection() {
            this.assignUsersDialog.selectedMemberIds = []
        },
        syncAssignedMemberSelection() {
            const memberIds = new Set(this.assignUsersDialog.members.map((member) => Number(member.id)))
            this.assignUsersDialog.selectedMemberIds = this.assignUsersDialog.selectedMemberIds.filter((id) => memberIds.has(Number(id)))
        },
        isTeacherSearchSelected(userId) {
            return this.assignUsersDialog.selectedTeacherIds.some((id) => Number(id) === Number(userId))
        },
        toggleTeacherSearchSelection(userId) {
            const id = Number(userId)
            const selected = [...this.assignUsersDialog.selectedTeacherIds]
            const index = selected.findIndex((item) => Number(item) === id)
            if (index >= 0) {
                selected.splice(index, 1)
            } else {
                selected.push(id)
            }
            this.assignUsersDialog.selectedTeacherIds = selected
        },
        selectAllTeacherSearchResults() {
            this.assignUsersDialog.selectedTeacherIds = this.assignUsersDialog.teacherSearchResults
                .filter((user) => !user.already_member)
                .map((user) => Number(user.id))
        },
        clearTeacherSearchSelection() {
            this.assignUsersDialog.selectedTeacherIds = []
        },
        syncTeacherSearchSelection() {
            const selectableIds = new Set(
                this.assignUsersDialog.teacherSearchResults
                    .filter((user) => !user.already_member)
                    .map((user) => Number(user.id))
            )
            this.assignUsersDialog.selectedTeacherIds = this.assignUsersDialog.selectedTeacherIds.filter((id) => selectableIds.has(Number(id)))
        },
        resetTeacherSearchResults() {
            this.assignUsersDialog.teacherSearchHasRun = false
            this.assignUsersDialog.teacherSearchResults = []
            this.assignUsersDialog.selectedTeacherIds = []
        },
        resetStudentSearchResults() {
            this.assignUsersDialog.studentSearchHasRun = false
            this.assignUsersDialog.studentSearchResults = []
        },
        studentClassAssignableIds(classGroup) {
            return (classGroup?.students || [])
                .filter((student) => !student.already_member && !!student.has_user_account)
                .map((student) => Number(student.user_id || student.id))
        },
        studentClassAssignedCount(classGroup) {
            return (classGroup?.students || []).filter((student) => !!student.already_member).length
        },
        findStudentClassGroup(classKey) {
            return this.assignUsersDialog.studentClasses.find((row) => row.key === classKey) || null
        },
        isStudentInClassSelected(classKey, userId) {
            if (!userId) return false
            const classGroup = this.findStudentClassGroup(classKey)
            if (!classGroup) return false
            return classGroup.selectedIds.some((id) => Number(id) === Number(userId))
        },
        toggleStudentInClassSelection(classKey, userId) {
            if (!userId) return
            const classGroup = this.findStudentClassGroup(classKey)
            if (!classGroup) return
            const id = Number(userId)
            const selected = [...classGroup.selectedIds]
            const index = selected.findIndex((item) => Number(item) === id)
            if (index >= 0) {
                selected.splice(index, 1)
            } else {
                selected.push(id)
            }
            classGroup.selectedIds = selected
        },
        selectAllStudentsInClass(classKey) {
            const classGroup = this.findStudentClassGroup(classKey)
            if (!classGroup) return
            classGroup.selectedIds = this.studentClassAssignableIds(classGroup)
        },
        clearStudentClassSelection(classKey) {
            const classGroup = this.findStudentClassGroup(classKey)
            if (!classGroup) return
            classGroup.selectedIds = []
        },
        toggleStudentClassExpanded(classKey) {
            const classGroup = this.findStudentClassGroup(classKey)
            if (!classGroup) return
            classGroup.expanded = !classGroup.expanded
        },
        buildStudentClasses(rows = []) {
            const expandedMap = new Map(
                (this.assignUsersDialog.studentClasses || []).map((row) => [row.key, !!row.expanded])
            )

            const groupsMap = new Map()
            for (const student of rows) {
                const rawClass = String(student?.schoolclass || '').trim()
                const key = rawClass !== '' ? rawClass : '__NO_CLASS__'
                const label = rawClass !== '' ? rawClass : 'Ohne Klasse'
                if (!groupsMap.has(key)) {
                    groupsMap.set(key, {
                        key,
                        label,
                        expanded: expandedMap.get(key) || false,
                        selectedIds: [],
                        students: [],
                    })
                }
                groupsMap.get(key).students.push(student)
            }

            return Array.from(groupsMap.values()).sort((a, b) => a.label.localeCompare(b.label, 'de', { numeric: true, sensitivity: 'base' }))
        },
        onExtraPanelChanged(panel) {
            if (panel === 'my-courses') {
                this.loadMyTeachingCourses()
            }
        },
        buildMyTeachingCourses(rows = []) {
            const previous = new Map(
                (this.assignUsersDialog.myCourses || []).map((course) => [Number(course.id), { expanded: !!course.expanded }])
            )

            return rows.map((course) => ({
                ...course,
                classes_label: Array.isArray(course.classes) && course.classes.length ? course.classes.join(', ') : '',
                expanded: previous.get(Number(course.id))?.expanded || false,
                selectedIds: [],
                students: Array.isArray(course.students) ? course.students : [],
            }))
        },
        async loadMyTeachingCourses(force = false) {
            const group = this.assignUsersDialog.group
            if (!group?.id || group.type !== 'own') return
            if (this.assignUsersDialog.myCoursesLoaded && !force) return

            this.assignUsersDialog.myCoursesLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/my-teaching-courses`)
                const rows = Array.isArray(response.data?.data) ? response.data.data : []
                this.assignUsersDialog.myCourses = this.buildMyTeachingCourses(rows)
                this.assignUsersDialog.myCoursesLoaded = true
            } catch (error) {
                this.assignUsersDialog.myCourses = []
                this.assignUsersDialog.myCoursesLoaded = false
                this.notifyError(error, 'Meine Fächer konnten nicht geladen werden.')
            } finally {
                this.assignUsersDialog.myCoursesLoading = false
            }
        },
        findMyCourse(courseId) {
            return this.assignUsersDialog.myCourses.find((row) => Number(row.id) === Number(courseId)) || null
        },
        myCourseAssignableIds(course) {
            return (course?.students || [])
                .filter((student) => !student.already_member && !!student.has_user_account)
                .map((student) => Number(student.user_id || student.id))
        },
        toggleMyCourseExpanded(courseId) {
            const course = this.findMyCourse(courseId)
            if (!course) return
            course.expanded = !course.expanded
        },
        isMyCourseStudentSelected(courseId, userId) {
            if (!userId) return false
            const course = this.findMyCourse(courseId)
            if (!course) return false
            return course.selectedIds.some((id) => Number(id) === Number(userId))
        },
        toggleMyCourseStudentSelection(courseId, userId) {
            if (!userId) return
            const course = this.findMyCourse(courseId)
            if (!course) return
            const id = Number(userId)
            const next = [...course.selectedIds]
            const index = next.findIndex((row) => Number(row) === id)
            if (index >= 0) next.splice(index, 1)
            else next.push(id)
            course.selectedIds = next
        },
        selectAllMyCourseStudents(courseId) {
            const course = this.findMyCourse(courseId)
            if (!course) return
            course.selectedIds = this.myCourseAssignableIds(course)
        },
        clearMyCourseSelection(courseId) {
            const course = this.findMyCourse(courseId)
            if (!course) return
            course.selectedIds = []
        },
        async assignWholeMyCourse(course) {
            const userIds = this.myCourseAssignableIds(course)
            if (userIds.length === 0) return
            await this.assignUsersToCurrentGroup(userIds)
        },
        async assignSelectedMyCourseStudents(courseId) {
            const course = this.findMyCourse(courseId)
            if (!course) return
            const userIds = (course.selectedIds || []).map((id) => Number(id)).filter((id) => id > 0)
            if (userIds.length === 0) return
            await this.assignUsersToCurrentGroup(userIds)
            this.clearMyCourseSelection(courseId)
        },
        onTeacherSearchCleared() {
            if (this.assignUsersDialog.teacherShowAll) {
                this.searchAssignableTeachers({ allowEmpty: true })
                return
            }
            this.resetTeacherSearchResults()
        },
        async toggleTeacherShowAll() {
            const nextValue = !this.assignUsersDialog.teacherShowAll
            this.assignUsersDialog.teacherShowAll = nextValue

            if (nextValue) {
                await this.searchAssignableTeachers({ allowEmpty: true })
                return
            }

            const hasSearch = String(this.assignUsersDialog.teacherSearchString || '').trim() !== ''
            if (hasSearch) {
                await this.searchAssignableTeachers()
            } else {
                this.resetTeacherSearchResults()
            }
        },
        async removeAssignedMember(member) {
            const group = this.assignUsersDialog.group
            if (!group?.id || !member?.id || this.isBusy) return

            this.isBusy = true
            this.is_loading++
            this.assignUsersDialog.removingUserId = member.id
            try {
                await axios.delete(`/api/admin/groups/${group.id}/members/${member.id}`)
                this.notifySuccess('Benutzer entfernt.')
                await this.loadGroups()
                await this.loadAssignedMembers()
                if (this.assignUsersDialog.userSearchHasRun) {
                    await this.searchAssignableUsers()
                }
                if (this.assignUsersDialog.teacherSearchHasRun) {
                    await this.searchAssignableTeachers()
                }
                if (this.assignUsersDialog.studentSearchHasRun) {
                    await this.searchAssignableStudents()
                }
                if (this.assignUsersDialog.studentClassesVisible) {
                    await this.loadStudentClasses()
                }
                if (this.assignUsersDialog.myCoursesLoaded) {
                    await this.loadMyTeachingCourses(true)
                }
                await this.loadAssignableGroups()
            } catch (error) {
                this.notifyError(error, 'Benutzer konnte nicht entfernt werden.')
            } finally {
                this.assignUsersDialog.removingUserId = null
                this.is_loading--
                this.isBusy = false
            }
        },
        async removeSelectedAssignedMembers() {
            const group = this.assignUsersDialog.group
            const userIds = (this.assignUsersDialog.selectedMemberIds || []).map((id) => Number(id)).filter((id) => id > 0)
            if (!group?.id || this.isBusy || userIds.length === 0) return

            this.isBusy = true
            this.is_loading++
            this.assignUsersDialog.bulkRemoving = true
            try {
                const response = await axios.post(`/api/admin/groups/${group.id}/remove-users`, { user_ids: userIds })
                const removedCount = Number(response.data?.meta?.removed_count ?? userIds.length)
                this.notifySuccess(`${removedCount} Benutzer entfernt.`)
                this.clearAssignedMemberSelection()
                await this.loadGroups()
                await this.loadAssignedMembers()
                if (this.assignUsersDialog.userSearchHasRun) {
                    await this.searchAssignableUsers()
                }
                if (this.assignUsersDialog.teacherSearchHasRun) {
                    await this.searchAssignableTeachers()
                }
                if (this.assignUsersDialog.studentSearchHasRun) {
                    await this.searchAssignableStudents()
                }
                if (this.assignUsersDialog.studentClassesVisible) {
                    await this.loadStudentClasses()
                }
                if (this.assignUsersDialog.myCoursesLoaded) {
                    await this.loadMyTeachingCourses(true)
                }
                await this.loadAssignableGroups()
            } catch (error) {
                this.notifyError(error, 'Benutzer konnten nicht entfernt werden.')
            } finally {
                this.assignUsersDialog.bulkRemoving = false
                this.is_loading--
                this.isBusy = false
            }
        },
        async searchAssignableUsers() {
            const group = this.assignUsersDialog.group
            if (!group?.id) return
            const searchString = String(this.assignUsersDialog.userSearchString || '').trim()
            if (searchString === '') {
                this.assignUsersDialog.userSearchHasRun = false
                this.assignUsersDialog.userSearchResults = []
                return
            }
            this.assignUsersDialog.userSearchLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/assignable-users`, {
                    params: { search_string: searchString },
                })
                this.assignUsersDialog.userSearchHasRun = true
                this.assignUsersDialog.userSearchResults = Array.isArray(response.data?.data) ? response.data.data : []
            } catch (error) {
                this.assignUsersDialog.userSearchHasRun = true
                this.assignUsersDialog.userSearchResults = []
                this.notifyError(error, 'Benutzer konnten nicht geladen werden.')
            } finally {
                this.assignUsersDialog.userSearchLoading = false
            }
        },
        async searchAssignableTeachers(options = {}) {
            const group = this.assignUsersDialog.group
            if (!group?.id || group.type !== 'school') return

            const searchString = String(this.assignUsersDialog.teacherSearchString || '').trim()
            const allowEmpty = !!options.allowEmpty || !!this.assignUsersDialog.teacherShowAll
            if (searchString === '' && !allowEmpty) {
                this.resetTeacherSearchResults()
                return
            }
            this.assignUsersDialog.teacherSearchLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/assignable-users`, {
                    params: {
                        search_string: searchString,
                        role_name: 'teacher',
                        limit: 500,
                    },
                })
                this.assignUsersDialog.teacherSearchHasRun = true
                this.assignUsersDialog.teacherSearchResults = Array.isArray(response.data?.data) ? response.data.data : []
                this.syncTeacherSearchSelection()
            } catch (error) {
                this.assignUsersDialog.teacherSearchHasRun = true
                this.assignUsersDialog.teacherSearchResults = []
                this.assignUsersDialog.selectedTeacherIds = []
                this.notifyError(error, 'Lehrer konnten nicht geladen werden.')
            } finally {
                this.assignUsersDialog.teacherSearchLoading = false
            }
        },
        async searchAssignableStudents() {
            const group = this.assignUsersDialog.group
            if (!group?.id || group.type !== 'school') return

            const searchString = String(this.assignUsersDialog.studentSearchString || '').trim()
            if (searchString === '') {
                this.resetStudentSearchResults()
                return
            }

            this.assignUsersDialog.studentSearchLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/assignable-users`, {
                    params: {
                        search_string: searchString,
                        role_name: 'student',
                        limit: 200,
                    },
                })
                this.assignUsersDialog.studentSearchHasRun = true
                this.assignUsersDialog.studentSearchResults = Array.isArray(response.data?.data) ? response.data.data : []
            } catch (error) {
                this.assignUsersDialog.studentSearchHasRun = true
                this.assignUsersDialog.studentSearchResults = []
                this.notifyError(error, 'Schüler:innen konnten nicht geladen werden.')
            } finally {
                this.assignUsersDialog.studentSearchLoading = false
            }
        },
        async toggleStudentClassesVisible() {
            const nextValue = !this.assignUsersDialog.studentClassesVisible
            this.assignUsersDialog.studentClassesVisible = nextValue

            if (nextValue) {
                await this.loadStudentClasses()
            }
        },
        async loadStudentClasses() {
            const group = this.assignUsersDialog.group
            if (!group?.id || group.type !== 'school') return

            this.assignUsersDialog.studentClassesLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/assignable-users`, {
                    params: {
                        role_name: 'student',
                        limit: 5000,
                    },
                })
                const rows = Array.isArray(response.data?.data) ? response.data.data : []
                this.assignUsersDialog.studentClasses = this.buildStudentClasses(rows)
            } catch (error) {
                this.assignUsersDialog.studentClasses = []
                this.notifyError(error, 'Klassen konnten nicht geladen werden.')
            } finally {
                this.assignUsersDialog.studentClassesLoading = false
            }
        },
        async assignWholeStudentClass(classGroup) {
            const userIds = this.studentClassAssignableIds(classGroup)
            if (userIds.length === 0) return
            await this.assignUsersToCurrentGroup(userIds)
        },
        async assignSelectedStudentsInClass(classKey) {
            const classGroup = this.findStudentClassGroup(classKey)
            if (!classGroup) return
            const userIds = (classGroup.selectedIds || []).map((id) => Number(id)).filter((id) => id > 0)
            if (userIds.length === 0) return
            await this.assignUsersToCurrentGroup(userIds)
            this.clearStudentClassSelection(classKey)
        },
        async loadAssignableGroups() {
            const group = this.assignUsersDialog.group
            if (!group?.id) return
            this.assignUsersDialog.sourceGroupsLoading = true
            try {
                const response = await axios.get(`/api/admin/groups/${group.id}/assignable-groups`)
                const rows = Array.isArray(response.data?.data) ? response.data.data : []
                this.assignUsersDialog.sourceGroups = rows.map((row) => ({
                    ...row,
                    display_name: `${this.currentTypeLabel(row.type)} | ${row.name} (${row.members_count})`,
                }))
                if (!this.assignUsersDialog.sourceGroups.some((row) => Number(row.id) === Number(this.assignUsersDialog.sourceGroupId))) {
                    this.assignUsersDialog.sourceGroupId = null
                }
            } catch (error) {
                this.assignUsersDialog.sourceGroups = []
                this.assignUsersDialog.sourceGroupId = null
                this.notifyError(error, 'Quellgruppen konnten nicht geladen werden.')
            } finally {
                this.assignUsersDialog.sourceGroupsLoading = false
            }
        },
        async assignUsersToCurrentGroup(userIds = []) {
            const group = this.assignUsersDialog.group
            if (!group?.id || this.isBusy || !Array.isArray(userIds) || userIds.length === 0) return

            this.isBusy = true
            this.is_loading++
            try {
                const response = await axios.post(`/api/admin/groups/${group.id}/assign-users`, {
                    user_ids: userIds,
                })
                const newCount = Number(response.data?.meta?.new_count ?? 0)
                this.notifySuccess(newCount > 0 ? `${newCount} Benutzer zugeordnet.` : 'Benutzer waren bereits zugeordnet.')
                await this.loadGroups()
                await this.loadAssignedMembers()
                await this.searchAssignableUsers()
                if (this.assignUsersDialog.teacherSearchHasRun) {
                    await this.searchAssignableTeachers()
                }
                if (this.assignUsersDialog.studentSearchHasRun) {
                    await this.searchAssignableStudents()
                }
                if (this.assignUsersDialog.studentClassesVisible) {
                    await this.loadStudentClasses()
                }
                if (this.assignUsersDialog.myCoursesLoaded) {
                    await this.loadMyTeachingCourses(true)
                }
                await this.loadAssignableGroups()
            } catch (error) {
                this.notifyError(error, 'Benutzer konnten nicht zugeordnet werden.')
            } finally {
                this.is_loading--
                this.isBusy = false
            }
        },
        async assignFromOtherGroup() {
            const group = this.assignUsersDialog.group
            const sourceGroupId = this.assignUsersDialog.sourceGroupId
            if (!group?.id || !sourceGroupId || this.isBusy) return

            this.isBusy = true
            this.is_loading++
            try {
                const response = await axios.post(`/api/admin/groups/${group.id}/assign-from-group`, {
                    source_group_id: sourceGroupId,
                })
                const newCount = Number(response.data?.meta?.new_count ?? 0)
                this.notifySuccess(newCount > 0 ? `${newCount} Benutzer aus Gruppe übernommen.` : (response.data?.message || 'Keine neuen Benutzer übernommen.'))
                await this.loadGroups()
                await this.loadAssignedMembers()
                await this.searchAssignableUsers()
                if (this.assignUsersDialog.teacherSearchHasRun) {
                    await this.searchAssignableTeachers()
                }
                if (this.assignUsersDialog.studentSearchHasRun) {
                    await this.searchAssignableStudents()
                }
                if (this.assignUsersDialog.studentClassesVisible) {
                    await this.loadStudentClasses()
                }
                if (this.assignUsersDialog.myCoursesLoaded) {
                    await this.loadMyTeachingCourses(true)
                }
                await this.loadAssignableGroups()
            } catch (error) {
                this.notifyError(error, 'Benutzer konnten nicht aus der Gruppe übernommen werden.')
            } finally {
                this.is_loading--
                this.isBusy = false
            }
        },
        async assignSelectedTeachers() {
            const userIds = (this.assignUsersDialog.selectedTeacherIds || [])
                .map((id) => Number(id))
                .filter((id) => id > 0)
            if (userIds.length === 0) return
            await this.assignUsersToCurrentGroup(userIds)
            this.clearTeacherSearchSelection()
        },
    },
}
</script>

<style scoped src="../../../../css/admin-superadmin-overview-shell.css"></style>
<style scoped src="../../../../css/admin-overview-card-foundation.css"></style>
<style scoped src="../../../../css/admin-superadmin-overview-cards.css"></style>

<style scoped>
.groups-page {
    background: #0f172a;
    min-height: 100vh;
}

.groups-cards-shell {
    width: calc(100% + ((100vw - 100%) / 2));
    max-width: none;
    margin-left: 0;
    margin-right: 0;
    padding: 0;
}

.groups-cards-row {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    justify-content: flex-start;
}

.groups-table-wrap {
    border-radius: 12px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.72);
    overflow: hidden;
}

.groups-school-panels :deep(.v-expansion-panel) {
    border-radius: 12px !important;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.62);
}

.groups-school-panels :deep(.v-expansion-panel-title) {
    min-height: 56px;
    padding: 12px 14px;
}

.groups-panel-title-wrap {
    display: inline-flex;
    align-items: baseline;
    gap: 6px;
    flex-wrap: wrap;
}

.groups-panel-title-meta {
    font-size: 0.78rem;
    color: rgba(16, 38, 58, 0.66);
}

.groups-list-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.groups-school-panels :deep(.v-expansion-panel-text__wrapper) {
    padding: 0 0 12px;
}

.groups-table :deep(table) {
    background: transparent !important;
}

.groups-table :deep(th) {
    white-space: nowrap;
    font-size: 0.75rem;
    color: rgba(16, 38, 58, 0.86);
}

.groups-table :deep(td) {
    vertical-align: middle;
}

.groups-table :deep(tbody td) {
    padding-top: 14px !important;
    padding-bottom: 14px !important;
}

.groups-table :deep(.groups-row) {
    cursor: pointer;
    transition: background-color 0.12s ease, box-shadow 0.12s ease;
}

.groups-table :deep(.groups-row:hover) {
    background: rgba(57, 73, 171, 0.06);
}

.groups-table :deep(.groups-row.is-selected) {
    background: rgba(57, 73, 171, 0.14);
    box-shadow: inset 4px 0 0 rgba(57, 73, 171, 0.9);
}

.groups-table :deep(.groups-row.is-selected td) {
    background: transparent !important;
}

.groups-table :deep(.groups-row.is-selected .font-weight-bold) {
    color: #2f41a8;
}

.groups-desc-cell {
    width: 100%;
    max-width: none;
    white-space: normal;
    line-height: 1.35;
    margin-top: 4px;
}

.groups-row-main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.groups-row-title {
    min-width: 0;
    flex: 1 1 auto;
}

.groups-row-meta {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    margin-left: auto;
    flex: 0 0 auto;
}

.groups-row-counter-chip {
    margin-left: auto;
}

.groups-assign-section {
    border-radius: 12px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.62);
    padding: 12px;
}

.groups-assign-list {
    display: grid;
    gap: 10px;
}

.groups-assign-list-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    border-radius: 10px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.72);
    padding: 12px 14px;
}

.min-w-0 {
    min-width: 0;
}

@media (max-width: 1200px) {
    .groups-cards-shell {
        width: 100%;
        max-width: 100%;
    }
}

@media (max-width: 720px) {
    .groups-row-main {
        flex-wrap: wrap;
    }

    .groups-row-meta {
        width: 100%;
    }

    .groups-assign-list-item {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

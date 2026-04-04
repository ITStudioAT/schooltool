<template>
    <v-col cols="12">
        <v-row dense>
            <v-col v-if="!embedded" cols="12">
                <v-sheet rounded="xl" class="settings-subnav pa-2 mb-3">
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn
                            rounded="xl"
                            :color="selectedPanel === 'general' ? 'primary' : undefined"
                            :variant="selectedPanel === 'general' ? 'flat' : 'outlined'"
                            class="settings-subnav__button"
                            :class="{ 'settings-subnav__button--active': selectedPanel === 'general' }"
                            :disabled="isPanelNavigationDisabled('general')"
                            @click="activatePanel('general')">
                            Allgemein
                        </v-btn>
                        <v-btn
                            rounded="xl"
                            :color="selectedPanel === 'categories' ? 'primary' : undefined"
                            :variant="selectedPanel === 'categories' ? 'flat' : 'outlined'"
                            class="settings-subnav__button"
                            :class="{ 'settings-subnav__button--active': selectedPanel === 'categories' }"
                            :disabled="isPanelNavigationDisabled('categories')"
                            @click="activatePanel('categories')">
                            Kategorien
                        </v-btn>
                        <v-btn
                            rounded="xl"
                            :color="selectedPanel === 'ingredient-icons' ? 'primary' : undefined"
                            :variant="selectedPanel === 'ingredient-icons' ? 'flat' : 'outlined'"
                            class="settings-subnav__button"
                            :class="{ 'settings-subnav__button--active': selectedPanel === 'ingredient-icons' }"
                            :disabled="isPanelNavigationDisabled('ingredient-icons')"
                            @click="activatePanel('ingredient-icons')">
                            Zutaten-Symbole
                        </v-btn>
                        <v-btn
                            rounded="xl"
                            :color="selectedPanel === 'free-days' ? 'primary' : undefined"
                            :variant="selectedPanel === 'free-days' ? 'flat' : 'outlined'"
                            class="settings-subnav__button"
                            :class="{ 'settings-subnav__button--active': selectedPanel === 'free-days' }"
                            :disabled="isPanelNavigationDisabled('free-days')"
                            @click="activatePanel('free-days')">
                            Freie Tage
                        </v-btn>
                        <v-btn
                            rounded="xl"
                            :color="selectedPanel === 'eating-times' ? 'primary' : undefined"
                            :variant="selectedPanel === 'eating-times' ? 'flat' : 'outlined'"
                            class="settings-subnav__button"
                            :class="{ 'settings-subnav__button--active': selectedPanel === 'eating-times' }"
                            :disabled="isPanelNavigationDisabled('eating-times')"
                            @click="activatePanel('eating-times')">
                            Speisezeiten
                        </v-btn>
                        <v-btn
                            rounded="xl"
                            :color="selectedPanel === 'users' ? 'primary' : undefined"
                            :variant="selectedPanel === 'users' ? 'flat' : 'outlined'"
                            class="settings-subnav__button"
                            :class="{ 'settings-subnav__button--active': selectedPanel === 'users' }"
                            :disabled="isPanelNavigationDisabled('users')"
                            @click="activatePanel('users')">
                            Benutzer
                        </v-btn>
                        <v-btn
                            rounded="xl"
                            :color="selectedPanel === 'sepa' ? 'primary' : undefined"
                            :variant="selectedPanel === 'sepa' ? 'flat' : 'outlined'"
                            class="settings-subnav__button"
                            :class="{ 'settings-subnav__button--active': selectedPanel === 'sepa' }"
                            :disabled="isPanelNavigationDisabled('sepa')"
                            @click="activatePanel('sepa')">
                            SEPA
                        </v-btn>
                        <v-btn
                            rounded="xl"
                            :color="selectedPanel === 'online' ? 'primary' : undefined"
                            :variant="selectedPanel === 'online' ? 'flat' : 'outlined'"
                            class="settings-subnav__button"
                            :class="{ 'settings-subnav__button--active': selectedPanel === 'online' }"
                            :disabled="isPanelNavigationDisabled('online')"
                            @click="activatePanel('online')">
                            Online
                        </v-btn>
                    </div>
                </v-sheet>
            </v-col>

            <v-col v-if="selectedPanel === 'general'" cols="12">
                <ItsGridBox variant="overview" color="primary" title="Allgemein" icon="mdi-tune-variant">
                    <template #header-actions>
                        <div class="d-flex flex-wrap justify-end ga-2">
                            <template v-if="isEditingGeneralSettings">
                                <v-btn
                                    icon="mdi-close"
                                    variant="text"
                                    aria-label="Bearbeitung abbrechen"
                                    title="Bearbeitung abbrechen"
                                    data-testid="general-settings-cancel-icon"
                                    @click="abortGeneralSettingsEdit" />
                                <v-btn
                                    icon="mdi-content-save"
                                    color="primary"
                                    variant="flat"
                                    aria-label="Allgemeine Einstellungen speichern"
                                    title="Allgemeine Einstellungen speichern"
                                    data-testid="general-settings-save-icon"
                                    @click="saveGeneralSettings" />
                            </template>
                            <v-btn
                                v-else
                                size="small"
                                color="primary"
                                variant="flat"
                                prepend-icon="mdi-pencil"
                                @click="beginGeneralSettingsEdit">
                                Bearbeiten
                            </v-btn>
                        </div>
                    </template>

                    <v-sheet rounded="xl" class="pa-5">
                        <div class="text-overline text-primary mb-2">Schulweite Einstellungen</div>
                        <div class="text-h6 font-weight-bold mb-2">Allgemeine Restaurant-Einstellungen</div>
                        <div class="text-body-1 text-medium-emphasis mb-5">
                            Diese Angaben gelten schulweit für das Restaurant und werden für Kommunikation und Benutzer-Informationen verwendet.
                        </div>


                        <div v-if="!isEditingGeneralSettings" class="general-settings-summary">
                            <div class="general-settings-row">
                                <div class="general-settings-row__label">Service-E-Mail-Adresse</div>
                                <div class="general-settings-row__value">{{ generalSettings.service_email || 'Nicht hinterlegt' }}</div>
                            </div>

                            <div class="general-settings-row">
                                <div class="general-settings-row__label">Neue Benutzer müssen bestätigt werden</div>
                                <div class="general-settings-row__value">
                                    <span
                                        class="general-settings-badge"
                                        :class="generalSettings.new_users_must_confirm_email ? 'is-yes' : 'is-no'">
                                        {{ generalSettings.new_users_must_confirm_email ? 'Ja' : 'Nein' }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="generalSettings.new_users_must_confirm_email" class="general-settings-row">
                                <div class="general-settings-row__label">E-Mail-Adresse für Bestätigung</div>
                                <div class="general-settings-row__value">{{ generalSettings.new_users_confirmer_email || 'Nicht hinterlegt' }}</div>
                            </div>

                            <div class="general-settings-row general-settings-row--stacked">
                                <div class="general-settings-row__label">Text für die erste Seite der Restaurant-Benutzerinformation</div>
                                <div
                                    v-if="generalSettings.user_information_intro_html"
                                    class="general-settings-richtext"
                                    v-html="generalSettings.user_information_intro_html" />
                                <div v-else class="general-settings-muted">Kein Text hinterlegt.</div>
                            </div>
                        </div>

                        <v-form v-else ref="generalForm" v-model="isGeneralFormValid" @submit.prevent="saveGeneralSettings">
                            <v-row dense>
                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model="generalSettingsForm.service_email"
                                        label="Service-E-Mail-Adresse"
                                        variant="outlined"
                                        density="comfortable"
                                        :rules="[mailOrNull(), maxLength(255)]" />
                                </v-col>

                                <v-col cols="12">
                                    <v-switch
                                        v-model="generalSettingsForm.new_users_must_confirm_email"
                                        label="Neue Benutzer müssen bestätigt werden"
                                        color="primary"
                                        inset />
                                </v-col>

                                <v-col v-if="generalSettingsForm.new_users_must_confirm_email" cols="12" md="6">
                                    <v-text-field
                                        v-model="generalSettingsForm.new_users_confirmer_email"
                                        label="E-Mail-Adresse für Bestätigung"
                                        variant="outlined"
                                        density="comfortable"
                                        :rules="[required(), mail(), maxLength(255)]" />
                                </v-col>

                                <v-col cols="12">
                                    <div class="text-subtitle-2 mb-2">Text für die erste Seite der Restaurant-Benutzerinformation</div>
                                    <ItsRichTextEditor v-model="generalSettingsForm.user_information_intro_html" />
                                </v-col>
                            </v-row>

                            <div class="d-flex flex-wrap justify-end ga-2 mt-5">
                                <v-btn variant="text" @click="abortGeneralSettingsEdit">
                                    Abbrechen
                                </v-btn>
                                <v-btn color="primary" variant="flat" @click="saveGeneralSettings">
                                    Allgemeine Einstellungen speichern
                                </v-btn>
                            </div>
                        </v-form>
                    </v-sheet>
                </ItsGridBox>
            </v-col>

            <v-col v-if="selectedPanel === 'categories'" cols="12">
                <ItsGridBox variant="overview" color="primary" title="Kategorien" icon="mdi-shape-outline">
                    <template #header-actions>
                        <v-btn size="small" color="primary" variant="flat" prepend-icon="mdi-plus" @click="openNewCategory">
                            Neue Kategorie
                        </v-btn>
                    </template>

                    <v-alert v-if="!categories.length" type="info" variant="tonal" class="mb-3">
                        Noch keine Kategorien vorhanden.
                    </v-alert>

                    <v-list v-else density="comfortable" class="bg-transparent pa-0">
                        <v-list-item
                            v-for="category in categories"
                            :key="category.id"
                            :title="category.title"
                            :subtitle="`${category.foods_count || 0} Speisen`">
                            <template #append>
                                <div class="d-flex ga-1">
                                    <v-btn icon="mdi-pencil" size="x-small" variant="text" @click="editCategory(category)" />
                                    <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="openCategoryDeleteDialog(category)" />
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                </ItsGridBox>
            </v-col>

            <v-col v-if="selectedPanel === 'ingredient-icons'" cols="12">
                <ItsGridBox variant="overview" color="primary" title="Zutaten-Symbole" icon="mdi-image-multiple-outline">
                    <template #header-actions>
                        <div class="d-flex flex-wrap justify-end ga-2">
                            <v-btn
                                size="small"
                                color="primary"
                                variant="outlined"
                                prepend-icon="mdi-folder-sync-outline"
                                :loading="isLoadingIngredientIconImportDialog"
                                :disabled="isLoadingIngredientIconImportDialog || isImportingIngredientIcons"
                                @click="openIngredientIconImportDialog">
                                Aus Verzeichnis übernehmen
                            </v-btn>
                            <v-btn size="small" color="primary" variant="flat" prepend-icon="mdi-plus" @click="openNewIngredientIcon">
                                Neues Symbol
                            </v-btn>
                        </div>
                    </template>

                    <v-alert v-if="!ingredientIcons.length" type="info" variant="tonal" class="mb-3">
                        Noch keine Zutaten-Symbole vorhanden.
                    </v-alert>

                    <v-row dense>
                        <v-col
                            v-for="icon in ingredientIcons"
                            :key="icon.id"
                            cols="12"
                            md="6">
                            <v-sheet rounded="lg" class="settings-icon-card pa-3">
                                <div class="d-flex align-center ga-3">
                                    <v-avatar size="56" rounded="lg" class="settings-icon-card__avatar">
                                        <v-img v-if="icon.image_url" :src="icon.image_url" cover />
                                        <span v-else class="font-weight-bold">{{ shortLabel(icon.title) }}</span>
                                    </v-avatar>

                                    <div class="flex-grow-1">
                                        <div class="font-weight-bold">{{ icon.title }}</div>
                                        <div class="text-body-2 text-medium-emphasis">{{ icon.foods_count || 0 }} Speisen</div>
                                    </div>

                                    <div class="d-flex ga-1">
                                        <v-btn icon="mdi-pencil" size="x-small" variant="text" @click="editIngredientIcon(icon)" />
                                        <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="openIngredientIconDeleteDialog(icon)" />
                                    </div>
                                </div>
                            </v-sheet>
                        </v-col>
                    </v-row>
                </ItsGridBox>
            </v-col>

            <FreeDays v-if="selectedPanel === 'free-days'" />
            <EatingTimes v-if="selectedPanel === 'eating-times'" />
            <Users v-if="selectedPanel === 'users'" />
            <Sepa v-if="selectedPanel === 'sepa'" />
            <OnlineSettings v-if="selectedPanel === 'online'" />
        </v-row>

        <v-dialog v-model="categoryDialog" max-width="520" persistent>
            <v-card rounded="xl">
                <v-card-title>{{ categoryEditingId ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}</v-card-title>

                <v-card-text>
                    <v-form ref="categoryForm" v-model="isCategoryFormValid" @submit.prevent="saveCategory">
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="categoryForm.title"
                                    label="Kategoriename"
                                    variant="outlined"
                                    density="comfortable"
                                    autofocus
                                    :rules="[required(), maxLength(255)]" />
                            </v-col>

                            <v-col cols="12">
                                <v-text-field
                                    v-model="categoryForm.sort_order"
                                    label="Reihenfolge"
                                    type="number"
                                    variant="outlined"
                                    density="comfortable" />
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeCategoryDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveCategory">
                        {{ categoryEditingId ? 'Kategorie aktualisieren' : 'Kategorie speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="categoryDeleteDialog" max-width="440" persistent>
            <v-card rounded="xl">
                <v-card-title>Kategorie löschen</v-card-title>

                <v-card-text>
                    <div class="text-body-1">
                        Soll <strong>{{ pendingDeleteCategory?.title || 'diese Kategorie' }}</strong> wirklich gelöscht werden?
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeCategoryDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" @click="confirmDestroyCategory">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="ingredientIconDialog" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title>{{ ingredientIconEditingId ? 'Symbol bearbeiten' : 'Neues Symbol' }}</v-card-title>

                <v-card-text>
                    <v-form ref="ingredientIconFormRef" v-model="isIngredientIconFormValid" @submit.prevent="saveIngredientIcon">
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="ingredientIconForm.title"
                                    label="Titel"
                                    variant="outlined"
                                    density="comfortable"
                                    autofocus
                                    :rules="[required(), maxLength(255)]" />
                            </v-col>

                            <v-col cols="12">
                                <div class="text-subtitle-2 mb-2">SVG hochladen</div>
                                <file-pond
                                    name="ingredientIconImage"
                                    :allow-multiple="false"
                                    :allow-file-type-validation="true"
                                    :accepted-file-types="['image/svg+xml']"
                                    :allow-image-preview="true"
                                    :allow-revert="false"
                                    :allow-remove="true"
                                    :files="ingredientIconFiles"
                                    label-idle="<strong>SVG hierher ziehen oder <i>klicken</i></strong>"
                                    label-file-processing-complete="SVG bereit"
                                    @updatefiles="updateIngredientIconFiles" />
                            </v-col>

                            <v-col cols="12" v-if="ingredientIconPreview || ingredientIconForm.currentImageUrl">
                                <div class="text-caption text-medium-emphasis mb-2">Vorschau</div>
                                <v-sheet rounded="lg" class="settings-icon-preview pa-4">
                                    <v-avatar size="84" rounded="lg">
                                        <v-img :src="ingredientIconPreview || ingredientIconForm.currentImageUrl" cover />
                                    </v-avatar>
                                </v-sheet>
                            </v-col>

                            <v-col cols="12" v-if="ingredientIconEditingId && ingredientIconForm.currentImageUrl">
                                <v-checkbox
                                    v-model="ingredientIconForm.removeImage"
                                    label="Bestehendes Bild entfernen"
                                    density="comfortable"
                                    hide-details />
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeIngredientIconDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveIngredientIcon">
                        {{ ingredientIconEditingId ? 'Symbol aktualisieren' : 'Symbol speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="ingredientIconImportDialog" max-width="880" persistent>
                <v-card rounded="xl">
                    <v-card-title>Zutaten-Symbole aus Verzeichnis übernehmen</v-card-title>

                    <v-card-text>
                    <div v-if="availablePrivateIngredientIcons.length" class="d-flex flex-wrap justify-space-between align-center ga-2 mb-4">
                        <div class="text-body-2">
                            {{ selectedIngredientIconImportPaths.length }} von {{ availablePrivateIngredientIcons.length }} Symbolen ausgewählt
                        </div>

                        <div class="d-flex flex-wrap ga-2">
                            <v-btn size="small" variant="text" @click="selectAllIngredientIconsForImport">
                                Alle auswählen
                            </v-btn>
                            <v-btn size="small" variant="text" @click="clearIngredientIconImportSelection">
                                Auswahl aufheben
                            </v-btn>
                        </div>
                    </div>

                    <v-alert v-if="!availablePrivateIngredientIcons.length" type="info" variant="tonal">
                        Im Verzeichnis wurden keine SVG-Symbole gefunden.
                    </v-alert>

                    <v-row v-else dense>
                        <v-col
                            v-for="icon in availablePrivateIngredientIcons"
                            :key="icon.path"
                            cols="12"
                            md="6">
                            <v-sheet rounded="lg" class="settings-icon-card pa-3">
                                <div class="d-flex align-center ga-3">
                                    <v-avatar size="56" rounded="lg" class="settings-icon-card__avatar">
                                        <v-img v-if="icon.image_url" :src="icon.image_url" cover />
                                        <span v-else class="font-weight-bold">{{ shortLabel(icon.title) }}</span>
                                    </v-avatar>

                                    <div class="flex-grow-1">
                                        <div class="font-weight-bold">{{ icon.title }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ icon.path }}</div>
                                        <div v-if="icon.already_imported" class="text-caption text-medium-emphasis mt-1">
                                            Bereits als Zutaten-Symbol vorhanden
                                        </div>
                                    </div>

                                    <v-checkbox
                                        v-model="selectedIngredientIconImportPaths"
                                        :value="icon.path"
                                        hide-details
                                        density="comfortable"
                                        color="primary" />
                                </div>
                            </v-sheet>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeIngredientIconImportDialog">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        :loading="isImportingIngredientIcons"
                        :disabled="!hasIngredientIconImportSelection"
                        @click="confirmIngredientIconImport">
                        Übernehmen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="ingredientIconDeleteDialog" max-width="440" persistent>
            <v-card rounded="xl">
                <v-card-title>Symbol löschen</v-card-title>

                <v-card-text>
                    <div class="text-body-1">
                        Soll <strong>{{ pendingDeleteIngredientIcon?.title || 'dieses Symbol' }}</strong> wirklich gelöscht werden?
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeIngredientIconDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" @click="confirmDestroyIngredientIcon">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import { useValidationRulesSetup } from '@/helpers/rules'
import EatingTimes from '@/pages/admin/restaurant/components/EatingTimes.vue'
import FreeDays from '@/pages/admin/restaurant/components/FreeDays.vue'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import OnlineSettings from '@/pages/admin/restaurant/components/OnlineSettings.vue'
import Sepa from '@/pages/admin/restaurant/components/Sepa.vue'
import Users from '@/pages/admin/restaurant/components/Users.vue'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import 'filepond/dist/filepond.min.css'
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import FilePondPluginImagePreview from 'filepond-plugin-image-preview'

const FilePond = vueFilePond(FilePondPluginFileValidateType, FilePondPluginImagePreview)

function emptyCategoryForm() {
    return {
        title: '',
        sort_order: 0,
    }
}

function emptyIngredientIconForm() {
    return {
        title: '',
        image: null,
        currentImageUrl: null,
        removeImage: false,
    }
}

function emptyGeneralSettingsForm() {
    return {
        service_email: '',
        new_users_must_confirm_email: false,
        new_users_confirmer_email: '',
        user_information_intro_html: '',
    }
}

const validPanels = ['general', 'categories', 'ingredient-icons', 'free-days', 'eating-times', 'users', 'sepa', 'online']

export default {
    setup() {
        return useValidationRulesSetup()
    },

    props: {
        embedded: {
            type: Boolean,
            default: false,
        },
        panel: {
            type: String,
            default: null,
        },
    },

    components: { EatingTimes, FilePond, FreeDays, ItsGridBox, ItsRichTextEditor, OnlineSettings, Sepa, Users },

    data() {
        return {
            selectedPanel: this.resolveInitialPanel(),
            isEditingGeneralSettings: false,
            isGeneralFormValid: false,
            categoryDialog: false,
            isCategoryFormValid: false,
            categoryDeleteDialog: false,
            ingredientIconDialog: false,
            ingredientIconImportDialog: false,
            ingredientIconDeleteDialog: false,
            isIngredientIconFormValid: false,
            isLoadingIngredientIconImportDialog: false,
            isImportingIngredientIcons: false,
            ingredientIconImportSourceDirectory: '',
            availablePrivateIngredientIcons: [],
            selectedIngredientIconImportPaths: [],
            categoryEditingId: null,
            pendingDeleteCategory: null,
            pendingDeleteIngredientIcon: null,
            generalSettingsForm: emptyGeneralSettingsForm(),
            categoryForm: emptyCategoryForm(),
            ingredientIconEditingId: null,
            ingredientIconForm: emptyIngredientIconForm(),
        }
    },

    computed: {
        ...mapState(useRestaurantStore, ['categories', 'ingredientIcons', 'generalSettings', 'canManageGeneralSettings']),
        ingredientIconPreview() {
            if (! this.ingredientIconForm.image) {
                return null
            }

            return URL.createObjectURL(this.ingredientIconForm.image)
        },
        ingredientIconFiles() {
            return this.ingredientIconForm.image ? [{ source: this.ingredientIconForm.image, options: { type: 'local' } }] : []
        },
        hasIngredientIconImportSelection() {
            return this.selectedIngredientIconImportPaths.length > 0
        },
    },

    async created() {
        const restaurantStore = useRestaurantStore()
        if (! restaurantStore.settings) {
            await restaurantStore.loadSettings()
        }
        this.resetGeneralSettingsForm()
    },

    watch: {
        '$route.query.panel'() {
            if (! this.embedded) {
                this.syncPanelFromRoute()
            }
        },
        panel() {
            if (this.embedded) {
                this.syncPanelFromEmbeddedPanel()
            }
        },
        generalSettings: {
            handler() {
                this.resetGeneralSettingsForm()
            },
            deep: true,
        },
    },

    methods: {
        resolveInitialPanel() {
            if (this.embedded) {
                return this.normalizePanel(this.panel)
            }

            return this.normalizePanel(this.$route?.query?.panel)
        },
        activatePanel(panel) {
            const normalizedPanel = this.normalizePanel(panel)

            if (this.isPanelNavigationDisabled(normalizedPanel)) {
                return
            }

            this.selectedPanel = normalizedPanel

            if (this.embedded) {
                return
            }

            this.$router.replace({
                query: {
                    ...(this.$route?.query || {}),
                    panel: normalizedPanel,
                },
            }).catch(() => {})
        },
        normalizePanel(panel) {
            return validPanels.includes(panel) ? panel : 'general'
        },
        isPanelNavigationDisabled(panel) {
            return this.isEditingGeneralSettings && panel !== this.selectedPanel
        },
        syncPanelFromEmbeddedPanel() {
            this.selectedPanel = this.normalizePanel(this.panel)
        },
        syncPanelFromRoute() {
            this.selectedPanel = this.normalizePanel(this.$route?.query?.panel)
        },
        resetGeneralSettingsForm() {
            this.generalSettingsForm = {
                service_email: this.generalSettings?.service_email || '',
                new_users_must_confirm_email: this.generalSettings?.new_users_must_confirm_email === true,
                new_users_confirmer_email: this.generalSettings?.new_users_confirmer_email || '',
                user_information_intro_html: this.generalSettings?.user_information_intro_html || '',
            }
            this.isGeneralFormValid = false
        },
        beginGeneralSettingsEdit() {
            this.resetGeneralSettingsForm()
            this.isEditingGeneralSettings = true
        },
        abortGeneralSettingsEdit() {
            this.resetGeneralSettingsForm()
            this.isEditingGeneralSettings = false
        },
        async saveGeneralSettings() {
            this.isGeneralFormValid = false
            await this.$refs.generalForm?.validate()

            if (! this.isGeneralFormValid) {
                return
            }

            const result = await useRestaurantStore().updateGeneralSettings({
                restaurant_service_email: this.generalSettingsForm.service_email,
                restaurant_new_users_must_confirm_email: this.generalSettingsForm.new_users_must_confirm_email,
                restaurant_new_users_confirmer_email: this.generalSettingsForm.new_users_must_confirm_email
                    ? this.generalSettingsForm.new_users_confirmer_email
                    : null,
                restaurant_user_information_intro_html: this.generalSettingsForm.user_information_intro_html,
            })

            if (result) {
                this.resetGeneralSettingsForm()
                this.isEditingGeneralSettings = false
            }
        },
        shortLabel(title) {
            return String(title || '').slice(0, 2).toUpperCase()
        },
        openNewCategory() {
            this.resetCategoryForm()
            this.categoryDialog = true
        },
        editCategory(category) {
            this.categoryEditingId = category.id
            this.categoryForm = {
                title: category.title,
                sort_order: category.sort_order || 0,
            }
            this.categoryDialog = true
        },
        resetCategoryForm() {
            this.categoryEditingId = null
            this.categoryForm = emptyCategoryForm()
            this.isCategoryFormValid = false
        },
        closeCategoryDialog() {
            this.categoryDialog = false
            this.resetCategoryForm()
        },
        openCategoryDeleteDialog(category) {
            this.pendingDeleteCategory = category
            this.categoryDeleteDialog = true
        },
        closeCategoryDeleteDialog() {
            this.categoryDeleteDialog = false
            this.pendingDeleteCategory = null
        },
        async saveCategory() {
            this.isCategoryFormValid = false
            await this.$refs.categoryForm?.validate()

            if (! this.isCategoryFormValid) {
                return
            }

            const restaurantStore = useRestaurantStore()
            const payload = {
                title: this.categoryForm.title,
                sort_order: this.categoryForm.sort_order,
            }

            const result = this.categoryEditingId
                ? await restaurantStore.updateCategory(this.categoryEditingId, payload)
                : await restaurantStore.storeCategory(payload)

            if (result) {
                this.categoryDialog = false
                this.resetCategoryForm()
                await restaurantStore.loadSettings()
            }
        },
        async confirmDestroyCategory() {
            if (! this.pendingDeleteCategory) {
                return
            }

            const deleted = await useRestaurantStore().destroyCategory(this.pendingDeleteCategory.id)
            if (deleted) {
                await useRestaurantStore().loadSettings()
            }

            this.closeCategoryDeleteDialog()
        },
        openNewIngredientIcon() {
            this.resetIngredientIconForm()
            this.ingredientIconDialog = true
        },
        editIngredientIcon(icon) {
            this.ingredientIconEditingId = icon.id
            this.ingredientIconForm = {
                title: icon.title,
                image: null,
                currentImageUrl: icon.image_url || null,
                removeImage: false,
            }
            this.ingredientIconDialog = true
        },
        resetIngredientIconForm() {
            this.ingredientIconEditingId = null
            this.ingredientIconForm = emptyIngredientIconForm()
            this.isIngredientIconFormValid = false
        },
        closeIngredientIconDialog() {
            this.ingredientIconDialog = false
            this.resetIngredientIconForm()
        },
        resetIngredientIconImportDialog() {
            this.ingredientIconImportSourceDirectory = ''
            this.availablePrivateIngredientIcons = []
            this.selectedIngredientIconImportPaths = []
        },
        closeIngredientIconImportDialog() {
            this.ingredientIconImportDialog = false
            this.resetIngredientIconImportDialog()
        },
        openIngredientIconDeleteDialog(icon) {
            this.pendingDeleteIngredientIcon = icon
            this.ingredientIconDeleteDialog = true
        },
        closeIngredientIconDeleteDialog() {
            this.ingredientIconDeleteDialog = false
            this.pendingDeleteIngredientIcon = null
        },
        updateIngredientIconFiles(fileItems) {
            const selectedFile = fileItems?.[0]?.file ?? null
            this.ingredientIconForm.image = selectedFile

            if (selectedFile) {
                this.ingredientIconForm.removeImage = false
            }
        },
        async saveIngredientIcon() {
            this.isIngredientIconFormValid = false
            await this.$refs.ingredientIconFormRef?.validate()

            if (! this.isIngredientIconFormValid) {
                return
            }

            const restaurantStore = useRestaurantStore()
            const payload = {
                title: this.ingredientIconForm.title,
                image: this.ingredientIconForm.image,
                remove_image: this.ingredientIconForm.removeImage ? '1' : '',
            }

            const result = this.ingredientIconEditingId
                ? await restaurantStore.updateIngredientIcon(this.ingredientIconEditingId, payload)
                : await restaurantStore.storeIngredientIcon(payload)

            if (result) {
                this.ingredientIconDialog = false
                this.resetIngredientIconForm()
                await restaurantStore.loadSettings()
            }
        },
        async confirmDestroyIngredientIcon() {
            if (! this.pendingDeleteIngredientIcon) {
                return
            }

            const deleted = await useRestaurantStore().destroyIngredientIcon(this.pendingDeleteIngredientIcon.id)
            if (deleted) {
                await useRestaurantStore().loadSettings()
            }

            this.closeIngredientIconDeleteDialog()
        },
        async openIngredientIconImportDialog() {
            if (this.isLoadingIngredientIconImportDialog || this.isImportingIngredientIcons) {
                return
            }

            this.isLoadingIngredientIconImportDialog = true

            try {
                const result = await useRestaurantStore().loadAvailableIngredientIconsFromPrivateDirectory()

                if (! result) {
                    return
                }

                this.ingredientIconImportSourceDirectory = result.source_directory
                this.availablePrivateIngredientIcons = result.icons
                this.selectedIngredientIconImportPaths = result.icons.map((icon) => icon.path)
                this.ingredientIconImportDialog = true
            } finally {
                this.isLoadingIngredientIconImportDialog = false
            }
        },
        selectAllIngredientIconsForImport() {
            this.selectedIngredientIconImportPaths = this.availablePrivateIngredientIcons.map((icon) => icon.path)
        },
        clearIngredientIconImportSelection() {
            this.selectedIngredientIconImportPaths = []
        },
        async confirmIngredientIconImport() {
            if (! this.hasIngredientIconImportSelection || this.isImportingIngredientIcons) {
                return
            }

            this.isImportingIngredientIcons = true

            try {
                const result = await useRestaurantStore().syncIngredientIconsFromPrivateDirectory(this.selectedIngredientIconImportPaths)

                if (result) {
                    this.closeIngredientIconImportDialog()
                }
            } finally {
                this.isImportingIngredientIcons = false
            }
        },
    },
}
</script>

<style scoped>
.settings-subnav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(255, 255, 255, 0.92);
}

.settings-subnav__button {
    border-color: rgba(100, 116, 139, 0.28);
    background: rgba(248, 250, 252, 0.94);
    color: rgb(15, 23, 42);
}

.settings-subnav__button--active {
    color: inherit;
}

.settings-icon-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: rgba(255, 255, 255, 0.92);
}

.general-settings-summary {
    display: grid;
    gap: 16px;
}

.general-settings-row {
    display: grid;
    grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
    gap: 16px;
    align-items: start;
    padding: 16px 18px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 18px;
    background: rgba(248, 250, 252, 0.92);
}

.general-settings-row--stacked {
    grid-template-columns: minmax(0, 1fr);
}

.general-settings-row__label {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgb(71, 85, 105);
}

.general-settings-row__value {
    color: rgb(15, 23, 42);
    word-break: break-word;
}

.general-settings-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.9rem;
}

.general-settings-badge.is-yes {
    background: rgba(22, 163, 74, 0.14);
    color: rgb(21, 128, 61);
}

.general-settings-badge.is-no {
    background: rgba(148, 163, 184, 0.16);
    color: rgb(71, 85, 105);
}

.general-settings-richtext {
    color: rgb(15, 23, 42);
}

.general-settings-richtext :deep(p) {
    margin: 0 0 0.6em;
}

.general-settings-richtext :deep(p:last-child) {
    margin-bottom: 0;
}

.general-settings-richtext :deep(p:empty),
.general-settings-richtext :deep(p > br:only-child) {
    min-height: 1em;
}

.general-settings-muted {
    color: rgb(100, 116, 139);
}

.settings-icon-card__avatar {
    background: rgba(59, 130, 246, 0.12);
}

.settings-icon-preview {
    border: 1px dashed rgba(59, 130, 246, 0.35);
    background: rgba(239, 246, 255, 0.72);
    display: flex;
    justify-content: center;
}

@media (max-width: 760px) {
    .general-settings-row {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>

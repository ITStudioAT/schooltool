<template>
    <div class="note-manager">
        <!-- Loading State -->
        <v-progress-linear
            v-if="isLoading"
            indeterminate
            color="primary"
            class="mb-4"
        ></v-progress-linear>

        <!-- Error State -->
        <v-alert
            v-if="error"
            type="error"
            variant="tonal"
            class="mb-4"
            @click="clearError"
            closable
        >
            {{ error }}
        </v-alert>

        <!-- Empty State -->
        <v-alert
            v-if="!isLoading && notes.length === 0"
            type="info"
            variant="tonal"
            class="mb-4"
        >
            Noch keine Notizen vorhanden. Erstellen Sie Ihre erste Notiz!
        </v-alert>

        <!-- Pinned Notes -->
        <div v-if="pinnedNotes.length > 0" class="mb-6">
            <h3 class="text-h6 mb-3 d-flex align-center">
                <v-icon color="warning" class="mr-2">mdi-pin</v-icon>
                Angepinnte Notizen
            </h3>
            <v-row>
                <v-col
                    v-for="note in pinnedNotes"
                    :key="`pinned-${note.id}`"
                    cols="12"
                    md="6"
                    lg="4"
                >
                    <note-card
                        :note="note"
                        @edit="editNote(note)"
                        @delete="confirmDelete(note)"
                        @toggle-pin="togglePin(note.id)"
                    />
                </v-col>
            </v-row>
        </div>

        <!-- Unpinned Notes -->
        <div v-if="unpinnedNotes.length > 0">
            <h3 class="text-h6 mb-3">Alle Notizen</h3>
            <v-row>
                <v-col
                    v-for="note in unpinnedNotes"
                    :key="note.id"
                    cols="12"
                    md="6"
                    lg="4"
                >
                    <note-card
                        :note="note"
                        @edit="editNote(note)"
                        @delete="confirmDelete(note)"
                        @toggle-pin="togglePin(note.id)"
                    />
                </v-col>
            </v-row>
        </div>

        <!-- Create/Edit Dialog -->
        <v-dialog v-model="showEditDialog" max-width="600">
            <v-card>
                <v-card-title>
                    {{ editingNote ? 'Notiz bearbeiten' : 'Neue Notiz erstellen' }}
                </v-card-title>
                <v-card-text>
                    <v-form @submit.prevent="saveNote" ref="noteForm">
                        <v-text-field
                            v-model="form.title"
                            label="Titel"
                            :rules="[v => !!v || 'Titel ist erforderlich']"
                            required
                            class="mb-3"
                        ></v-text-field>

                        <v-textarea
                            v-model="form.content"
                            label="Inhalt"
                            :rules="[v => !!v || 'Inhalt ist erforderlich']"
                            required
                            rows="5"
                            auto-grow
                            class="mb-3"
                        ></v-textarea>

                        <v-checkbox
                            v-model="form.is_pinned"
                            label="Anpinnen"
                            color="warning"
                        ></v-checkbox>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="showEditDialog = false" variant="text">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="primary"
                        @click="saveNote"
                        :loading="isSubmitting"
                        variant="flat"
                    >
                        {{ editingNote ? 'Aktualisieren' : 'Erstellen' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Confirmation Dialog -->
        <v-dialog v-model="showDeleteDialog" max-width="400">
            <v-card>
                <v-card-title class="text-h6">
                    Notiz löschen
                </v-card-title>
                <v-card-text>
                    Möchten Sie die Notiz "{{ noteToDelete?.title }}" wirklich löschen?
                    Diese Aktion kann nicht rückgängig gemacht werden.
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="showDeleteDialog = false" variant="text">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="error"
                        @click="confirmDeleteAction"
                        :loading="isSubmitting"
                        variant="flat"
                    >
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { mapState, mapActions } from 'pinia'
import { useNoteStore } from '@/stores/homepage/NoteStore'
import NoteCard from './NoteCard.vue'

export default {
    name: 'NoteManager',
    
    components: {
        NoteCard
    },
    
    data() {
        return {
            showEditDialog: false,
            showDeleteDialog: false,
            editingNote: null,
            noteToDelete: null,
            noteForm: null,
            isSubmitting: false,
            form: {
                title: '',
                content: '',
                is_pinned: false,
            }
        }
    },
    
    computed: {
        ...mapState(useNoteStore, ['notes', 'pinnedNotes', 'unpinnedNotes', 'isLoading', 'error'])
    },
    
    mounted() {
        this.fetchNotes()
    },
    
    methods: {
        ...mapActions(useNoteStore, ['fetchNotes', 'createNote', 'updateNote', 'deleteNote', 'togglePin', 'clearError']),
        
        editNote(note) {
            this.editingNote = note
            this.form.title = note.title
            this.form.content = note.content
            this.form.is_pinned = note.is_pinned
            this.showEditDialog = true
        },
        
        confirmDelete(note) {
            this.noteToDelete = note
            this.showDeleteDialog = true
        },
        
        async saveNote() {
            if (!this.noteForm) return
            
            const { valid } = await this.noteForm.validate()
            if (!valid) return

            this.isSubmitting = true
            try {
                if (this.editingNote) {
                    await this.updateNote(this.editingNote.id, this.form)
                } else {
                    await this.createNote(this.form)
                }
                
                // Reset form and close dialog
                this.resetForm()
                this.showEditDialog = false
                this.editingNote = null
            } catch (error) {
                // Error is handled by the store
            } finally {
                this.isSubmitting = false
            }
        },
        
        async confirmDeleteAction() {
            if (!this.noteToDelete) return
            
            this.isSubmitting = true
            try {
                await this.deleteNote(this.noteToDelete.id)
                this.showDeleteDialog = false
                this.noteToDelete = null
            } catch (error) {
                // Error is handled by the store
            } finally {
                this.isSubmitting = false
            }
        },
        
        async togglePin(id) {
            try {
                await this.togglePin(id)
            } catch (error) {
                // Error is handled by the store
            }
        },
        
        resetForm() {
            this.form.title = ''
            this.form.content = ''
            this.form.is_pinned = false
            if (this.noteForm) {
                this.noteForm.reset()
            }
        }
    },
    
    watch: {
        showEditDialog(newVal) {
            if (!newVal) {
                this.resetForm()
                this.editingNote = null
            }
        }
    }
}
</script>

<style scoped>
.note-manager {
    margin: 20px 0;
}

.note-card {
    transition: transform 0.2s;
}

.note-card:hover {
    transform: translateY(-2px);
}
</style>
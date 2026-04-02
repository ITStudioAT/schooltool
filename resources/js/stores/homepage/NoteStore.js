import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useNoteStore = defineStore('NoteStore', {
    state: () => {
        return {
            notes: [],
            isLoading: false,
            error: null,
            currentNote: null,
        }
    },

    getters: {
        pinnedNotes: (state) => state.notes.filter(note => note.is_pinned),
        unpinnedNotes: (state) => state.notes.filter(note => !note.is_pinned),
        noteCount: (state) => state.notes.length,
        pinnedCount: (state) => state.notes.filter(note => note.is_pinned).length,
        loading: (state) => state.isLoading,
    },

    actions: {
        async fetchNotes() {
            const notification = useNotificationStore()
            this.loading = true
            this.error = null

            try {
                const response = await axios.get('/api/homepage/notes')
                if (response.data.success) {
                    this.notes = response.data.data
                } else {
                    throw new Error(response.data.message || 'Failed to fetch notes')
                }
            } catch (error) {
                this.error = error.message
                const status = error.response ? error.response.status : 500
                const message = error.response && error.response.data ? error.response.data.message : 'Fehler beim Laden der Notizen.'
                notification.notify({
                    status: status,
                    message: message,
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.loading = false
            }
        },

        async createNote(noteData) {
            const notification = useNotificationStore()
            this.loading = true
            this.error = null

            try {
                const response = await axios.post('/api/homepage/notes', noteData)
                if (response.data.success) {
                    this.notes.unshift(response.data.data)
                    notification.notify({
                        status: 201,
                        message: 'Notiz erfolgreich erstellt.',
                        type: 'success',
                        timeout: 3000,
                    })
                    return response.data.data
                } else {
                    throw new Error(response.data.message || 'Failed to create note')
                }
            } catch (error) {
                this.error = error.message
                const status = error.response ? error.response.status : 500
                const message = error.response && error.response.data ? error.response.data.message : 'Fehler beim Erstellen der Notiz.'
                notification.notify({
                    status: status,
                    message: message,
                    type: 'error',
                    timeout: 3000,
                })
                throw error
            } finally {
                this.loading = false
            }
        },

        async updateNote(id, noteData) {
            const notification = useNotificationStore()
            this.loading = true
            this.error = null

            try {
                const response = await axios.put(`/api/homepage/notes/${id}`, noteData)
                if (response.data.success) {
                    const index = this.notes.findIndex(note => note.id === id)
                    if (index !== -1) {
                        this.notes[index] = response.data.data
                    }
                    notification.notify({
                        status: 200,
                        message: 'Notiz erfolgreich aktualisiert.',
                        type: 'success',
                        timeout: 3000,
                    })
                    return response.data.data
                } else {
                    throw new Error(response.data.message || 'Failed to update note')
                }
            } catch (error) {
                this.error = error.message
                const status = error.response ? error.response.status : 500
                const message = error.response && error.response.data ? error.response.data.message : 'Fehler beim Aktualisieren der Notiz.'
                notification.notify({
                    status: status,
                    message: message,
                    type: 'error',
                    timeout: 3000,
                })
                throw error
            } finally {
                this.loading = false
            }
        },

        async deleteNote(id) {
            const notification = useNotificationStore()
            this.loading = true
            this.error = null

            try {
                const response = await axios.delete(`/api/homepage/notes/${id}`)
                if (response.data.success) {
                    this.notes = this.notes.filter(note => note.id !== id)
                    notification.notify({
                        status: 200,
                        message: 'Notiz erfolgreich gelöscht.',
                        type: 'success',
                        timeout: 3000,
                    })
                } else {
                    throw new Error(response.data.message || 'Failed to delete note')
                }
            } catch (error) {
                this.error = error.message
                const status = error.response ? error.response.status : 500
                const message = error.response && error.response.data ? error.response.data.message : 'Fehler beim Löschen der Notiz.'
                notification.notify({
                    status: status,
                    message: message,
                    type: 'error',
                    timeout: 3000,
                })
                throw error
            } finally {
                this.loading = false
            }
        },

        async togglePin(id) {
            const notification = useNotificationStore()
            this.loading = true
            this.error = null

            try {
                const response = await axios.post(`/api/homepage/notes/${id}/toggle-pin`)
                if (response.data.success) {
                    const index = this.notes.findIndex(note => note.id === id)
                    if (index !== -1) {
                        this.notes[index] = response.data.data
                    }
                    notification.notify({
                        status: 200,
                        message: 'Pin-Status aktualisiert.',
                        type: 'success',
                        timeout: 3000,
                    })
                    return response.data.data
                } else {
                    throw new Error(response.data.message || 'Failed to toggle pin')
                }
            } catch (error) {
                this.error = error.message
                const status = error.response ? error.response.status : 500
                const message = error.response && error.response.data ? error.response.data.message : 'Fehler beim Ändern des Pin-Status.'
                notification.notify({
                    status: status,
                    message: message,
                    type: 'error',
                    timeout: 3000,
                })
                throw error
            } finally {
                this.loading = false
            }
        },

        setCurrentNote(note) {
            this.currentNote = note
        },

        clearCurrentNote() {
            this.currentNote = null
        },

        clearError() {
            this.error = null
        },
    },
})

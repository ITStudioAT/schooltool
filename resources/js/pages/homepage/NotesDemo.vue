<template>
  <v-container fluid class="notes-demo-page ma-0 w-100 pa-2">
    <AdminSectionHero
      class="mb-3"
      eyebrow="Demo"
      title="Notes Management"
      :active-section="activeSection"
      :chips="headerChips"
      :show-current-user-chip="true"
      secondary-color="#3b82f6"
      right-orb-color="#93c5fd" />

    <v-sheet rounded="xl" class="notes-nav mb-2">
      <div class="notes-nav__buttons">
        <v-btn
          rounded="xl"
          color="primary"
          variant="flat"
          class="notes-nav__button"
          @click="showCreateDialog = true">
          <v-icon size="18" icon="mdi-plus" class="mr-2" />
          <span class="notes-nav__button-copy">
            <span class="notes-nav__button-title">Neue Notiz</span>
            <span class="notes-nav__button-meta">Erstelle eine neue Notiz</span>
          </span>
        </v-btn>
        
        <v-btn
          rounded="xl"
          color="secondary"
          variant="tonal"
          class="notes-nav__button"
          @click="refreshNotes">
          <v-icon size="18" icon="mdi-refresh" class="mr-2" />
          <span class="notes-nav__button-copy">
            <span class="notes-nav__button-title">Aktualisieren</span>
            <span class="notes-nav__button-meta">Lade alle Notizen neu</span>
          </span>
        </v-btn>
      </div>
    </v-sheet>

    <div class="notes-content">
      <NoteManager ref="noteManager" />
    </div>
  </v-container>
</template>

<script>
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import NoteManager from '@/components/NoteManager.vue'

export default {
  name: 'NotesDemo',
  components: {
    AdminSectionHero,
    NoteManager
  },
  
  data() {
    return {
      activeSection: {
        key: 'notes',
        label: 'Notizen Verwaltung',
        icon: 'mdi-note-text',
        note: 'Verwalten Sie Ihre persönlichen Notizen'
      },
      showCreateDialog: false,
      user: null
    }
  },
  
  computed: {
    headerChips() {
      const chips = [
        {
          key: 'total-notes',
          text: `${this.$refs.noteManager?.notes?.length || 0} Notizen`,
          icon: 'mdi-note-multiple',
          color: 'primary',
          variant: 'tonal'
        }
      ]
      
      // We'll get user info from the NoteManager component if available
      return chips
    }
  },
  
  methods: {
    refreshNotes() {
      if (this.$refs.noteManager && this.$refs.noteManager.fetchNotes) {
        this.$refs.noteManager.fetchNotes()
      }
    }
  }
}
</script>

<style scoped>
.notes-demo-page {
  min-height: 100vh;
  background-color: #f8fafc;
}

.notes-nav {
  background: white;
  padding: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
}

.notes-nav__buttons {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.notes-nav__button {
  height: 48px;
  padding: 0 20px;
}

.notes-nav__button-copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.2;
}

.notes-nav__button-title {
  font-size: 14px;
  font-weight: 600;
}

.notes-nav__button-meta {
  font-size: 12px;
  opacity: 0.7;
  margin-top: 2px;
}

.notes-content {
  background: white;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
}
</style>
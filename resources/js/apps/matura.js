import '../bootstrap.js'
import { createApp } from 'vue'
import vuetify from '../../plugins/admin.js'
import MaturaStation from '../pages/admin/helpers/matura/MaturaStation.vue'
createApp(MaturaStation).use(vuetify).mount('#app')

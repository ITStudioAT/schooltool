<template>
    <v-col cols="12" md="6" xl="4" v-if="offers">
        <its-grid-box color="primary" title="Angebote" class="w-100" :disabled="action != ''">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <!-- SEARCHFIELD -->
                        <SearchField :store="offerStore" selected_field="selected_offers" />

                        <!-- // NUR EINFACHAUSWAHL MÖGLICH Abwählen / Auswählen
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="selectAll">Alle auswählen [{{ offers.length - selected_offers.length }}]</v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="unselectAll">Alle abwählen [{{ selected_offers.length }}]</v-btn>
                        </v-card>
                        -->

                        <!-- Andere Selektionen: -->
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="toggleAccepted">
                                <div class="d-flex flex-row align-center ga-2" v-if="select_accepted == 'all'">
                                    <div>Alle</div>
                                    <div>
                                        <v-icon size="small" icon="mdi-check" color="success" />
                                        <v-icon size="small" icon="mdi-help" color="warning" />
                                    </div>
                                </div>
                                <div class="d-flex flex-row align-center ga-2" v-if="select_accepted == 'yes'">
                                    <div>Nur genehmigte</div>
                                    <v-icon size="small" icon="mdi-check" color="success" />
                                </div>
                                <div class="d-flex flex-row align-center ga-2" v-if="select_accepted == 'no'">
                                    <div>Nur offene</div>
                                    <v-icon size="small" icon="mdi-help" color="warning" />
                                </div>
                            </v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="toggleOnline">
                                <div class="d-flex flex-row align-center ga-2" v-if="select_online == 'all'">
                                    <div>Alle</div>
                                    <div>
                                        <v-icon size="small" icon="mdi-cloud" color="success" />
                                        <v-icon size="small" icon="mdi-cloud-off" color="error" />
                                    </div>
                                </div>
                                <div class="d-flex flex-row align-center ga-2" v-if="select_online == 'yes'">
                                    <div>Nur online</div>
                                    <div>
                                        <v-icon size="small" icon="mdi-cloud" color="success" />
                                    </div>
                                </div>
                                <div class="d-flex flex-row align-center ga-2" v-if="select_online == 'no'">
                                    <div>Nur offline</div>
                                    <div>
                                        <v-icon size="small" icon="mdi-cloud-off" color="error" />
                                    </div>
                                </div>
                            </v-btn>
                        </v-card>

                        <!-- RECORDS -->
                        <v-list dense variant="elevated" select-strategy="single-leaf" v-model:selected="selected_offers" color="success-lighten-2">
                            <v-list-item dense v-for="item in offers" :key="item.id" :value="item.id">
                                <template v-slot:title>
                                    <div class="w-100">
                                        <div class="d-flex flex-row align-center ga-2">
                                            <v-icon size="small" color="success" icon="mdi-cloud-check" v-if="item.is_active" />
                                            <v-icon size="small" color="error-lighten-3" icon="mdi-cloud-off" v-if="!item.is_active" />
                                            {{ item.subject.short_name + ': ' + item.title }}
                                        </div>
                                        <div class="d-flex flex-row align-center ga-2">
                                            {{ item?.user?.last_name + ' ' + item?.user?.first_name + ' (' + item?.user?.schoolclass + ', ' + item?.user?.email + ')' }}
                                        </div>

                                        <div class="text-body-2 d-flex flex-row align-center ga-2 w-100" v-if="!item.accepted_at">
                                            <v-icon size="small" color="warning" icon="mdi-help" />
                                            <div class="opacity-60">{{ item.email_mentor }}</div>
                                        </div>
                                        <div class="text-body-2 d-flex flex-row align-center ga-2 w-100" v-if="item.accepted_at">
                                            <v-icon size="small" color="success" icon="mdi-check" />
                                            <div class="opacity-60">{{ item.email_mentor + ' (' + item.accepted_at + ')' }}</div>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>

                        <!-- PAGINATION-->
                    </v-card-text>
                </v-card>
                <!-- MENÜ -->
                <v-card tile flat color="transparent" style="width: 150px" class="d-flex flex-column ga-2">
                    <!-- GENAU EINES AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_offers.length == 1">
                        <v-btn
                            block
                            tile
                            flat
                            color="warning"
                            class="text-caption"
                            prepend-icon="mdi-help"
                            @click="doRecordtoggleAccepted(selectedOffer.id)"
                            v-if="selectedOffer.accepted_at">
                            Nicht genehm.
                        </v-btn>
                        <v-btn
                            block
                            tile
                            flat
                            color="success"
                            class="text-caption"
                            prepend-icon="mdi-check"
                            @click="doRecordtoggleAccepted(selectedOffer.id)"
                            v-if="!selectedOffer.accepted_at">
                            Genehmigen
                        </v-btn>
                        <div v-if="selectedOffer.accepted_at">
                            <v-btn
                                block
                                tile
                                flat
                                color="warning"
                                class="text-caption"
                                prepend-icon="mdi-cloud-off"
                                @click="doRecordtoggleActive(selectedOffer.id)"
                                v-if="selectedOffer.is_active">
                                Offline
                            </v-btn>
                            <v-btn
                                block
                                tile
                                flat
                                color="success"
                                class="text-caption"
                                prepend-icon="mdi-cloud"
                                @click="doRecordtoggleActive(selectedOffer.id)"
                                v-if="!selectedOffer.is_active">
                                Online
                            </v-btn>
                        </div>
                        <v-btn block tile flat color="warning" class="text-caption" prepend-icon="mdi-delete" @click="delete_level++" v-if="delete_level == 0">Löschen</v-btn>
                        <v-btn block tile flat color="success" class="text-caption" prepend-icon="mdi-delete-off" @click="delete_level = 0" v-if="delete_level == 1">Löschen</v-btn>
                        <v-btn block tile flat color="error" class="text-caption" prepend-icon="mdi-delete" @click="doDelete(selectedOffer)" v-if="delete_level == 1">
                            Löschen
                        </v-btn>
                    </div>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>
    <!-- ##### ANGEBOT IM DETAIL ##### -->
    <v-col cols="12" md="6" xl="4" v-if="selected_offers.length == 1">
        <its-grid-box
            color="primary"
            :subtitle="selectedOffer.subject?.long_name"
            :title="selectedOffer.subject?.short_name + ': ' + selectedOffer?.title"
            class="w-100"
            :disabled="action != ''">
            <!-- Beschreibung -->
            <div class="bg-primary-lighten-3">
                <label class="text-subtitle-2 mt-2 d-block">Beschreibung des Angebots:</label>
                <div class="text-body-1" style="white-space: pre-line">
                    {{ selectedOffer.description }}
                </div>
            </div>

            <!-- Anbieter -->
            <div>
                <label class="text-subtitle-2 mt-2 d-block">Anbieter:</label>
                <div class="text-body-1">
                    <div class="d-flex align-center ga-2">
                        <div>
                            {{ selectedOffer.user.last_name + ' ' + selectedOffer.user.first_name }}
                        </div>
                        <div>{{ selectedOffer.user.schoolclass }}</div>
                    </div>
                    <div class="d-flex flex-row align-center ga-2">
                        <v-icon icon="mdi-mail" />
                        <div>{{ selectedOffer.user.email }}</div>
                    </div>
                </div>
            </div>

            <!-- Klassen  -->
            <div class="bg-primary-lighten-3">
                <label class="text-subtitle-2 mt-2 d-block">Klassen:</label>
                <div v-if="unterstufeClasses.length > 0" class="mb-2">
                    <strong>Unterstufe:</strong>
                    <div class="text-body-2">
                        {{ unterstufeClasses.join(', ') }}
                    </div>
                </div>
                <div v-if="oberstufeClasses.length > 0">
                    <strong>Oberstufe:</strong>
                    <div class="text-body-2">
                        {{ oberstufeClasses.join(', ') }}
                    </div>
                </div>
            </div>

            <!-- Gültig bis -->
            <div>
                <label class="text-subtitle-2 mt-2 d-block">Gültig bis:</label>
                <div class="text-body-1" v-if="!selectedOffer.active_until">UNENDLICH</div>
                <div class="text-body-1" v-if="selectedOffer.active_until">{{ formattedActiveUntil }}</div>
            </div>

            <!-- Gruppenangebot -->
            <div>
                <label class="text-subtitle-2 mt-2 d-block">Gruppenangebot:</label>
                <div class="text-body-1" v-if="!selectedOffer.is_group">NEIN (nur Einzelunterricht)</div>
                <div class="text-body-1" v-if="selectedOffer.is_group">JA (maximal {{ selectedOffer.max_group_members }} Teilnehmer)</div>
            </div>

            <!-- Beschreibung -->
            <div class="bg-primary-lighten-3">
                <label class="text-subtitle-2 mt-2 d-block">Kosten pro Stunde:</label>
                <div class="text-body-1">
                    {{ parseFloat(selectedOffer.price_per_hour) + ' Euro' }}
                </div>
            </div>

            <!-- Freigabe -->
            <div>
                <label class="text-subtitle-2 mt-2 d-block">Freigabe des Angebots:</label>
                <div class="text-body-1" v-if="!selectedOffer.must_be_accepted">Angebot wurde automatisch freigegeben.</div>
                <div class="text-body-1" v-if="selectedOffer.must_be_accepted">
                    <div>Freigabe muss erteilt werden</div>
                    <div class="d-flex flex-row align-center ga-2">
                        <div>Freigabe:</div>
                        <div class="d-flex flex-row align-center ga-2" v-if="selectedOffer.accepted_at">
                            <div>Freigabe erteilt!</div>
                            <v-icon icon="mdi-check" color="success" size="small" />
                        </div>
                        <div class="d-flex flex-row align-center ga-2" v-if="!selectedOffer.accepted_at">
                            <div>ausstehend!</div>
                            <v-icon icon="mdi-help" color="warning" size="small" />
                        </div>
                    </div>
                    <div class="d-flex flex-row align-center ga-2">
                        <div>Mentor:</div>
                        <div class="d-flex flex-row align-center ga-2">
                            <v-icon icon="mdi-mail" />
                            {{ selectedOffer?.email_mentor }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Online/Offline -->
            <div class="bg-primary-lighten-3">
                <label class="text-subtitle-2 mt-2 d-block">Status:</label>
                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="selectedOffer.is_active">
                    <v-icon icon="mdi-cloud" color="success" />
                    <div>ONLINE</div>
                </div>

                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="!selectedOffer.is_active">
                    <v-icon icon="mdi-cloud-off" color="error" />
                    <div>OFFLINE</div>
                </div>
            </div>

            <!-- Anzahl Klicks -->
            <div class="bg-primary-lighten-3">
                <label class="text-subtitle-2 mt-2 d-block">Informationen:</label>
                <div class="text-body-1 d-flex flex-row align-center ga-2">
                    <div>Anzahl Klicks:</div>
                    <div>{{ selectedOffer?.click_count }}</div>
                </div>
            </div>
        </its-grid-box>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'

import { useOfferStore } from '@/stores/admin/tutoring/OfferStore'

// SPECIFIC

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField, ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.offerStore = useOfferStore()
        this.select_only_me_concerning = false
        await this.offerStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            offerStore: null,
            is_valid: false,
            delete_level: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useOfferStore, ['offers', 'selected_offers', 'select_accepted', 'select_online', 'meta', 'select_only_me_concerning']),

        selectedOffer() {
            const id = this.selected_offers[0]
            const offer = this.offers.find((offer) => offer.id === id)
            return offer
        },

        selectedClasses() {
            return Object.entries(this.selectedOffer.classes || {})
                .filter(([key, value]) => value === true)
                .map(([key]) => parseInt(key))
                .sort((a, b) => a - b)
        },

        unterstufeClasses() {
            return this.selectedClasses.filter((num) => num <= 4).map((num) => `${num}. Klasse`)
        },

        oberstufeClasses() {
            return this.selectedClasses.filter((num) => num > 4).map((num) => `${num}. Klasse`)
        },
        formattedActiveUntil() {
            if (!this.selectedOffer.active_until) return ''

            const date = new Date(this.selectedOffer.active_until)
            const weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag']
            const weekday = weekdays[date.getDay()]

            const day = String(date.getDate()).padStart(2, '0')
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const year = date.getFullYear()

            return `${day}.${month}.${year} (${weekday})`
        },
    },

    watch: {},

    methods: {
        async doDelete(offer) {
            console.log(offer)
            this.selected_offers = []
            this.delete_level = 0
            await this.offerStore.delete(offer)
            await this.offerStore.index(this.meta.current_page)
        },
        async doRecordtoggleAccepted(id) {
            await this.offerStore.toggleAccepted(id)
            await this.offerStore.index(this.meta.current_page)
        },
        async doRecordtoggleActive(id) {
            await this.offerStore.toggleActive(id)
            await this.offerStore.index(this.meta.current_page)
        },

        async toggleAccepted() {
            switch (this.select_accepted) {
                case 'all':
                    this.select_accepted = 'yes'
                    break
                case 'yes':
                    this.select_accepted = 'no'
                    break
                case 'no':
                    this.select_accepted = 'all'
                    break
            }

            this.selected_offers = []
            await this.offerStore.index()
        },
        async toggleOnline() {
            switch (this.select_online) {
                case 'all':
                    this.select_online = 'yes'
                    break
                case 'yes':
                    this.select_online = 'no'
                    break
                case 'no':
                    this.select_online = 'all'
                    break
            }
            this.selected_offers = []
            await this.offerStore.index()
        },

        selectAll() {
            this.selected_offers = this.offers.map((item) => item.id)
        },
        unselectAll() {
            this.selected_offers = []
        },
    },
}
</script>

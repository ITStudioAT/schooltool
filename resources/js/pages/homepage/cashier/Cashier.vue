<template>
    <v-container fluid class="ma-0 w-100 h-100 pa-2 d-flex align-center justify-center bg-cashier_background text-cashier_text">
        <div class="d-flex flex-column align-center justify-center">
            <!-- PRICE DISPLAY -->
            <div class="text-h1" :class="priceClass">
                {{ price_str }}
            </div>

            <!-- HIDDEN INPUT for RFID -->
            <v-text-field
                ref="rfidInput"
                v-model="rfid"
                @keydown.enter="finishRFID"
                :disabled="mode !== 'waiting'"
                hide-details
                style="opacity: 0; position: absolute; pointer-events: none" />

            <div>RFID: {{ rfid }}</div>
            <div>MODE: {{ mode }}</div>
        </div>
    </v-container>
</template>

<script>
export default {
    data() {
        return {
            priceCents: 0,
            isNegative: false,
            mode: 'idle', // idle | waiting | done
            rfid: '',
        }
    },

    computed: {
        price_str() {
            const abs = Math.abs(this.priceCents)
            const euros = (abs / 100).toFixed(2).replace('.', ',')
            return this.isNegative ? '-' + euros : euros
        },
        priceClass() {
            if (this.mode === 'waiting') return 'price-wait'
            if (this.mode === 'done') return 'price-ok'
            return 'price-idle'
        },
    },

    mounted() {
        window.addEventListener('keydown', this.onKeyDown)
    },
    unmounted() {
        window.removeEventListener('keydown', this.onKeyDown)
    },

    methods: {
        // =========================================================
        // MAIN KEY HANDLING
        // =========================================================
        onKeyDown(e) {
            const key = e.key

            // NEW: RFID mode starts with "+" or "Add"
            if ((key === '+' || key === 'Add') && this.mode === 'idle') {
                this.startRFIDMode()
                e.preventDefault()
                return
            }

            // ENTER während der Betragseingabe = Eingabe löschen
            if (key === 'Enter' && this.mode === 'idle') {
                this.clearPrice() // Betrag löschen
                e.preventDefault()
                return
            }

            // In RFID mode: ignore normal keyboard input
            if (this.mode !== 'idle') return

            console.log(key + ': ' + key.charCodeAt(0))

            // NEGATIVE SIGN
            if (key === '-' && this.priceCents === 0 && !this.isNegative) {
                this.isNegative = true
                return
            }

            // DIGITS
            if (key >= '0' && key <= '9') {
                this.appendDigit(Number(key))
                return
            }

            // CLEAR
            if (['c', 'C', 'x', 'X'].includes(key)) {
                this.clearPrice()
                return
            }

            // DELETE
            if (key === 'Backspace' || key === 'Delete') {
                this.deleteOneDigit()
            }
        },

        // =========================================================
        // PRICE LOGIC
        // =========================================================
        appendDigit(digit) {
            const abs = Math.abs(this.priceCents)
            const newVal = abs * 10 + digit
            this.priceCents = this.isNegative ? -newVal : newVal
        },
        deleteOneDigit() {
            const abs = Math.abs(this.priceCents)
            const newAbs = Math.floor(abs / 10)
            if (newAbs === 0) {
                this.clearPrice()
            } else {
                this.priceCents = this.isNegative ? -newAbs : newAbs
            }
        },
        clearPrice() {
            this.priceCents = 0
            this.isNegative = false
            this.mode = 'idle'
        },

        // =========================================================
        // RFID MODE
        // =========================================================
        startRFIDMode() {
            this.mode = 'waiting' // Red + Bold visualization
            this.rfid = '' // Clear previous scan
            this.$nextTick(() => {
                this.$refs.rfidInput.focus()
            })
        },

        finishRFID() {
            if (this.rfid.length >= 1) {
                this.mode = 'done' // Green + Bold
            } else {
                this.mode = 'idle'
            }
        },
    },
}
</script>

<style>
.price-idle {
    font-weight: 400;
    color: white;
}

.price-wait {
    font-weight: 900;
    color: red;
}

.price-ok {
    font-weight: 900;
    color: green;
}
</style>

<template>
    <div class="restaurant-page">
        <section class="restaurant-hero">
            <div class="restaurant-shell">
                <div class="restaurant-top-bar">
                    <router-link :to="'/?school=' + (currentSchoolShortName || '')" class="restaurant-back-link">
                        <v-icon size="18">mdi-arrow-left</v-icon>
                        <span>Zur Startseite</span>
                    </router-link>
                    <a href="/admin/restaurant/menu-plans" class="restaurant-action-text">Zur Verwaltung</a>
                </div>

                <div class="restaurant-hero-card">
                    <div class="restaurant-hero-copy">
                        <div class="restaurant-eyebrow">SchoolTool Restaurant</div>
                        <h1 class="restaurant-title">Restaurant</h1>
                        <div v-if="currentSchoolShortName && !schoolFromUrl" class="restaurant-school-selector">
                            <label class="restaurant-school-selector__label">Schule auswählen</label>
                            <v-select
                                v-model="selectedSchoolShortName"
                                :items="selectableSchools"
                                item-title="long_name"
                                item-value="short_name"
                                placeholder="Schule wählen..."
                                variant="outlined"
                                density="compact"
                                hide-details
                                class="restaurant-school-selector__select"
                                @update:model-value="onSchoolSelected"
                            />
                        </div>

                        <div v-if="currentSchoolShortName" class="restaurant-actions">
                            <router-link :to="'/?school=' + (currentSchoolShortName || '')" class="restaurant-action restaurant-action--secondary">Zur Übersicht</router-link>
                        </div>
                    </div>

                    <div v-if="currentSchoolShortName" class="restaurant-status-card">
                        <div class="restaurant-status-card__label">{{ schoolInfoName }}</div>
                        <div
                            v-if="restaurantIntroHtml"
                            class="restaurant-status-card__intro"
                            v-html="restaurantIntroHtml" />
                        <div v-else class="restaurant-status-card__intro restaurant-status-card__intro--empty">
                            Kein Informationstext hinterlegt.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="!currentSchoolShortName" class="restaurant-no-school">
            <div class="restaurant-shell">
                <div class="restaurant-no-school-card">
                    <v-icon icon="mdi-school-outline" size="48" class="restaurant-no-school-card__icon" />
                    <h2 class="restaurant-no-school-card__title">Bitte wählen Sie Ihre Schule</h2>
                    <p class="restaurant-no-school-card__text">Wählen Sie Ihre Schule, um den Speiseplan und die Bestellmöglichkeiten zu sehen.</p>

                    <div v-if="selectableSchools.length > 10" class="restaurant-school-search">
                        <v-text-field
                            v-model="schoolSearch"
                            placeholder="Schule suchen..."
                            variant="outlined"
                            density="compact"
                            hide-details
                            prepend-inner-icon="mdi-magnify"
                            class="restaurant-school-search__input"
                        />
                    </div>

                    <div class="restaurant-school-list">
                        <button
                            v-for="school in (selectableSchools.length > 10 ? filteredSchools : selectableSchools)"
                            :key="school.short_name"
                            class="restaurant-school-item"
                            @click="onSchoolSelected(school.short_name)"
                        >
                            <v-icon icon="mdi-domain" size="20" class="restaurant-school-item__icon" />
                            <span class="restaurant-school-item__name">{{ school.long_name || school.short_name }}</span>
                            <v-icon icon="mdi-chevron-right" size="18" class="restaurant-school-item__arrow" />
                        </button>
                        <p v-if="selectableSchools.length > 10 && filteredSchools.length === 0" class="restaurant-school-list__empty">
                            Keine Schule gefunden.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="currentSchoolShortName" class="restaurant-auth">
            <div class="restaurant-shell">
                <div v-if="restaurantAuthUser" class="restaurant-auth-stack">
                    <div class="restaurant-auth-card">
                        <div class="restaurant-auth-card__icon">
                            <v-icon size="28">mdi-account-check-outline</v-icon>
                        </div>
                        <div class="restaurant-auth-card__copy">
                            <h2 class="restaurant-auth-card__title">{{ restaurantAuthDisplayName }}</h2>
                            <p v-if="restaurantAuthEmail" class="restaurant-auth-card__meta">E-Mail: {{ restaurantAuthEmail }}</p>
                            <p class="restaurant-auth-card__text">
                                {{ restaurantRequiresSepa
                                    ? 'Bevor Sie Menüs bestellen können, muss zuerst das SEPA-Lastschriftmandat bestätigt werden.'
                                    : 'Sie sind angemeldet und können jetzt Menüs bestellen.' }}
                            </p>
                        </div>
                        <div class="restaurant-auth-card__actions">
                            <v-btn
                                v-if="restaurantRequiresSepa"
                                color="#ea580c"
                                variant="flat"
                                rounded="lg"
                                class="text-none font-weight-bold"
                                @click="openSepaDialogFromConfig">
                                SEPA bestätigen
                            </v-btn>
                            <v-btn color="#ea580c" variant="outlined" rounded="lg" class="text-none font-weight-bold" @click="downloadRestaurantOverviewPdf">Meine Menüs drucken</v-btn>
                            <v-btn color="#ea580c" variant="flat" rounded="lg" class="text-none font-weight-bold" @click="openRestaurantPasswordDialog">Passwort ändern</v-btn>
                            <v-btn color="#ea580c" variant="outlined" rounded="lg" class="text-none font-weight-bold" @click="logoutRestaurantUser">Abmelden</v-btn>
                        </div>
                    </div>

                    <div v-if="groupedUserBookings.length" class="restaurant-auth-bookings">
                        <div class="restaurant-auth-bookings__header">
                            <div>
                                <div class="restaurant-auth-bookings__eyebrow">Bereits gebucht</div>
                                <h3 class="restaurant-auth-bookings__title">Ihre Menüs</h3>
                            </div>
                            <div class="restaurant-auth-bookings__count">{{ userBookings.length }} Buchung<span v-if="userBookings.length !== 1">en</span></div>
                        </div>

                        <div class="restaurant-auth-bookings__list">
                            <div
                                v-for="group in groupedUserBookings"
                                :key="`auth-booking-date-${group.dateKey}`"
                                class="restaurant-auth-bookings__item"
                                :class="{ 'restaurant-auth-bookings__item--today': group.dateKey === todayDateKey }">
                                <div class="restaurant-auth-bookings__entries">
                                    <div
                                        v-for="menuGroup in group.menuGroups"
                                        :key="`auth-booking-menu-${group.dateKey}-${menuGroup.menuKey}`"
                                        class="restaurant-auth-bookings__menu-group">
                                        <div class="restaurant-auth-bookings__menu-items">
                                            <div
                                                v-for="booking in menuGroup.bookings"
                                                :key="`auth-booking-${booking.id}`"
                                                class="restaurant-auth-bookings__entry">
                                                <div class="restaurant-auth-bookings__copy">
                                                    <div class="restaurant-auth-bookings__item-meta restaurant-auth-bookings__item-meta--inline">
                                                        <strong class="restaurant-auth-bookings__item-meta-part">{{ group.dayLabel }}, {{ group.dateLabel }}</strong>
                                                        <span class="restaurant-auth-bookings__item-meta-part">{{ menuGroup.menuTitle }}</span>
                                                        <strong>{{ booking.quantity }}x</strong>
                                                        <span v-if="booking.eating_time"> um {{ formatEatingTime(booking.eating_time) }} Uhr</span>
                                                        <span v-if="bookingRecipientNames(booking)"> · {{ bookingRecipientNames(booking) }}</span>
                                                    </div>
                                                </div>

                                                <v-btn
                                                    v-if="booking.can_cancel"
                                                    color="#ea580c"
                                                    variant="text"
                                                    size="small"
                                                    class="text-none font-weight-bold"
                                                    :loading="isBookingCancellationLoading(booking.id)"
                                                    :disabled="isBookingCancellationLoading(booking.id)"
                                                    @click="requestBookingCancellation(booking)">
                                                    Stornieren
                                                </v-btn>

                                                <span v-else class="restaurant-auth-bookings__locked">Nicht stornierbar!</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="restaurant-auth-card">
                    <div class="restaurant-auth-card__icon">
                        <v-icon size="28">mdi-account-circle-outline</v-icon>
                    </div>
                    <div class="restaurant-auth-card__copy">
                        <h2 class="restaurant-auth-card__title">Anmelden/Registrieren</h2>
                        <p class="restaurant-auth-card__text">Melden Sie sich an, um Bestellungen aufzugeben und Ihren Speiseplan einzusehen.</p>
                    </div>
                    <div class="restaurant-auth-card__actions">
                        <v-btn color="#ea580c" variant="flat" rounded="lg" class="text-none font-weight-bold" @click="openLoginDialog">Anmelden/Registrieren</v-btn>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="currentSchoolShortName && menuPlans.length" class="restaurant-plans">
            <div class="restaurant-shell">
                <div class="rp-section-header">
                    <v-icon icon="mdi-silverware-fork-knife" size="22" class="rp-section-header__icon" />
                    <h2 class="rp-section-header__title">Aktuelle Speisepläne</h2>
                </div>

                <v-alert
                    v-if="bookingSuccess"
                    type="success"
                    variant="tonal"
                    density="comfortable"
                    class="mb-4">
                    {{ bookingSuccess }}
                </v-alert>

                <v-alert
                    v-if="bookingError"
                    type="error"
                    variant="tonal"
                    density="comfortable"
                    class="mb-4">
                    {{ bookingError }}
                </v-alert>

                <div v-for="plan in menuPlans" :key="plan.id" class="rp-plan" :class="{ 'rp-plan--orderable': plan.is_orderable }">
                    <div class="rp-plan__header">
                        <div class="rp-plan__header-left">
                            <div class="rp-plan__title">{{ plan.title || "Menüplan" }}</div>
                            <div class="rp-plan__range">{{ formatDate(plan.start_date) }} - {{ formatDate(plan.end_date) }}</div>
                        </div>
                        <div v-if="plan.is_orderable || (!plan.is_orderable && plan.order_start_at && new Date(plan.order_start_at).getTime() > Date.now())" class="rp-plan__badge-group">
                            <div v-if="plan.is_orderable" class="rp-plan__badge">
                                <v-icon icon="mdi-cart-check" size="15" />
                                <span>Bestellbar</span>
                            </div>
                            <div v-else class="rp-plan__badge">
                                <v-icon icon="mdi-clock-outline" size="15" />
                                <span>Bald bestellbar</span>
                            </div>
                            <div v-if="countdownFor(plan)" class="rp-plan__timer">
                                <v-icon icon="mdi-timer-outline" size="13" />
                                <span>noch {{ countdownFor(plan) }}</span>
                            </div>
                        </div>
                        <v-btn color="white" variant="outlined" rounded="lg" size="small" class="text-none font-weight-bold rp-plan__print-btn" @click="downloadMenuPlanPdf(plan.id)">
                            <v-icon icon="mdi-printer" size="16" class="mr-1" />
                            Menüplan drucken
                        </v-btn>
                    </div>

                    <div class="rp-days">
                        <div
                            v-for="group in groupEntriesByDate(plan.entries)"
                            :key="group.date"
                            class="rp-day">
                            <div class="rp-day__header">
                                <span class="rp-day__weekday">{{ weekdayLabel(group.date) }}</span>
                                <span class="rp-day__date">{{ formatDateShort(group.date) }}</span>
                            </div>

                            <div class="rp-day__menus">
                                <div v-for="entry in group.entries" :key="entry.id" class="rp-menu">
                                    <div class="rp-menu__top">
                                        <div class="rp-menu__title">{{ entry.menu_title || entry.menu?.title || "Menü" }}</div>
                                        <div v-if="entry.price" class="rp-menu__price">{{ formatPrice(entry.price) }}</div>
                                    </div>

                                    <div v-if="entryFoods(entry).length" class="rp-menu__foods">
                                        <div v-for="food in entryFoods(entry)" :key="food.id" class="rp-menu__food-block">
                                            <span class="rp-menu__food">
                                                {{ food.title }}
                                                <span v-if="food.allergens?.length" class="rp-menu__allergens">({{ food.allergens.join(', ') }})</span>
                                            </span>
                                            <div v-if="food.description" class="rp-menu__food-description">{{ food.description }}</div>
                                            <div v-if="food.ingredient_icons?.length" class="rp-menu__icons-row">
                                                <img
                                                    v-for="icon in food.ingredient_icons"
                                                    :key="icon.id"
                                                    :src="icon.image_url"
                                                    :alt="icon.title"
                                                    :title="icon.title"
                                                    class="rp-menu__ingredient-icon"
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="entry.eating_times?.length" class="rp-menu__times">
                                        <v-icon icon="mdi-clock-outline" size="13" class="rp-menu__times-icon" />
                                        <span v-for="(et, i) in entry.eating_times" :key="et.id">
                                            {{ formatEatingTime(et.eating_time) }} Uhr<span v-if="i < entry.eating_times.length - 1">, </span>
                                        </span>
                                    </div>

                                    <div v-if="entry.comments" class="rp-menu__comments">{{ entry.comments }}</div>

                                    <div v-if="restaurantAuthUser && entryHasBookings(entry.id)" class="rp-menu__booking-state">
                                        <div class="rp-menu__booking-summary">
                                            <v-icon icon="mdi-check-circle" size="18" class="rp-menu__booking-summary-icon" />
                                            <span>Gebucht: {{ bookedQuantityForEntry(entry.id) }}x</span>
                                        </div>

                                        <div class="rp-menu__booking-list">
                                            <div v-for="booking in userBookingsForEntry(entry.id)" :key="booking.id" class="rp-menu__booking-item">
                                                <div class="rp-menu__booking-copy">
                                                    <strong>{{ booking.quantity }}x</strong>
                                                    <span v-if="booking.eating_time"> um {{ formatEatingTime(booking.eating_time) }} Uhr</span>
                                                    <span v-if="bookingRecipientNames(booking)"> - {{ bookingRecipientNames(booking) }}</span>
                                                </div>

                                                <v-btn
                                                    v-if="booking.can_cancel"
                                                    color="#ea580c"
                                                    variant="text"
                                                    size="small"
                                                    class="text-none font-weight-bold"
                                                    :loading="isBookingCancellationLoading(booking.id)"
                                                    :disabled="isBookingCancellationLoading(booking.id)"
                                            @click="requestBookingCancellation(booking)">
                                            Stornieren
                                        </v-btn>

                                                <span v-else class="rp-menu__booking-locked">Nicht stornierbar!</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="restaurantAuthUser && plan.is_orderable" class="rp-menu__actions">
                                        <v-btn
                                            color="#ea580c"
                                            variant="flat"
                                            size="small"
                                            class="text-none font-weight-bold"
                                            :disabled="restaurantRequiresSepa"
                                            @click="bookMenu(entry)">
                                            {{ entryHasBookings(entry.id) ? 'Weiteres buchen' : 'Buchen' }}
                                        </v-btn>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="currentSchoolShortName" class="restaurant-content">
            <div class="restaurant-shell">
                <div class="rp-empty">
                    <v-icon icon="mdi-silverware-fork-knife" size="40" class="rp-empty__icon" />
                    <p class="rp-empty__text">Derzeit sind keine Speisepläne verfügbar.</p>
                </div>
            </div>
        </section>

        <v-dialog v-model="showPasswordDialog" max-width="440">
            <v-card rounded="xl">
                <v-card-title class="pt-5 px-6 font-weight-bold">Passwort ändern</v-card-title>
                <v-card-text class="px-6">
                    <div v-if="restaurantAuthEmail" class="restaurant-login-email mb-3">
                        <div class="restaurant-login-email__label">E-Mail</div>
                        <div class="restaurant-login-email__value">{{ restaurantAuthEmail }}</div>
                    </div>

                    <p class="restaurant-login-intro">Vergeben Sie ein neues Passwort für spätere Anmeldungen.</p>

                    <v-alert
                        v-if="restaurantPasswordSuccess"
                        type="success"
                        variant="tonal"
                        density="comfortable"
                        class="mb-3">
                        {{ restaurantPasswordSuccess }}
                    </v-alert>

                    <v-alert
                        v-if="restaurantPasswordError"
                        type="error"
                        variant="tonal"
                        density="comfortable"
                        class="mb-3">
                        {{ restaurantPasswordError }}
                    </v-alert>

                    <v-form ref="restaurantPasswordForm" @submit.prevent="submitRestaurantPasswordChange">
                        <v-text-field
                            v-model="restaurantPassword"
                            label="Neues Passwort"
                            type="password"
                            variant="outlined"
                            density="compact"
                            class="mb-3"
                            :disabled="restaurantPasswordLoading"
                            :rules="[required(), minLength(8), maxLength(255)]" />

                        <v-text-field
                            v-model="restaurantPasswordConfirmation"
                            label="Passwort wiederholen"
                            type="password"
                            variant="outlined"
                            density="compact"
                            :disabled="restaurantPasswordLoading"
                            :rules="[required(), minLength(8), maxLength(255), restaurantPasswordMatchRule]" />
                    </v-form>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        color="#9a3412"
                        class="text-none"
                        :disabled="restaurantPasswordLoading"
                        @click="closeRestaurantPasswordDialog">
                        Schließen
                    </v-btn>
                    <v-btn
                        color="#ea580c"
                        variant="flat"
                        rounded="lg"
                        class="text-none font-weight-bold"
                        :loading="restaurantPasswordLoading"
                        :disabled="!canSubmitRestaurantPasswordChange"
                        @click="submitRestaurantPasswordChange">
                        Passwort speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showLoginDialog" persistent max-width="440">
            <v-card rounded="xl">
                <v-card-title class="pt-5 px-6 font-weight-bold">Anmelden</v-card-title>
                <v-card-text class="px-6">
                    <div v-if="schoolInfoName" class="restaurant-login-school">
                        <v-icon icon="mdi-domain" size="16" class="restaurant-login-school__icon" />
                        <span>{{ schoolInfoName }}</span>
                    </div>

                    <v-form v-if="!loginCheckResult" ref="loginEmailForm" @submit.prevent="submitLoginEmailCheck">
                        <v-text-field
                            v-model="loginEmail"
                            autofocus
                            label="E-Mail"
                            variant="outlined"
                            density="compact"
                            class="mb-3"
                            :disabled="loginCheckLoading || loginCheckResult?.status === 'REGISTER_REQUIRED'"
                            :rules="[required(), mail(), maxLength(255)]"
                            @keyup.enter="submitLoginEmailCheck" />
                    </v-form>

                    <v-alert
                        v-if="loginCheckError"
                        type="error"
                        variant="tonal"
                        density="comfortable"
                        class="mb-3">
                        {{ loginCheckError }}
                    </v-alert>

                    <div v-if="loginCheckResult?.email" class="restaurant-login-email mb-3">
                        <div class="restaurant-login-email__label">E-Mail</div>
                        <div class="restaurant-login-email__value">{{ loginCheckResult.email }}</div>
                    </div>

                    <div v-if="loginCheckResult?.status === 'USER_FOUND'" class="restaurant-login-state">
                        <p class="restaurant-login-state__text">
                            Möchten Sie sich per Code oder per Passwort anmelden?
                        </p>

                        <div class="restaurant-login-state__actions">
                            <v-btn
                                color="#ea580c"
                                :variant="loginMode === 'code' ? 'flat' : 'outlined'"
                                rounded="lg"
                                class="text-none font-weight-bold"
                                :disabled="!selectedLoginUser || loginLoading"
                                @click="startCodeLogin">
                                Mit Code
                            </v-btn>
                            <v-btn
                                color="#ea580c"
                                :variant="loginMode === 'password' ? 'flat' : 'outlined'"
                                rounded="lg"
                                class="text-none font-weight-bold"
                                :disabled="!selectedLoginUser || loginLoading"
                                @click="startPasswordLogin">
                                Mit Passwort
                            </v-btn>
                        </div>

                        <v-alert
                            v-if="loginActionMessage"
                            type="info"
                            variant="tonal"
                            density="comfortable"
                            class="mt-3">
                            {{ loginActionMessage }}
                        </v-alert>

                        <v-alert
                            v-if="loginAuthError"
                            type="error"
                            variant="tonal"
                            density="comfortable"
                            class="mt-3">
                            {{ loginAuthError }}
                        </v-alert>

                        <v-form
                            v-if="loginMode === 'code'"
                            ref="loginCodeForm"
                            class="mt-4"
                            @submit.prevent="submitRestaurantCodeLogin">
                            <v-otp-input
                                v-model="loginCodeToken"
                                autofocus
                                class="mb-3" />

                            <div class="d-flex justify-space-between align-center ga-3">
                                <v-btn
                                    variant="text"
                                    color="#ea580c"
                                    class="text-none"
                                    :disabled="loginLoading || !selectedLoginUser"
                                    @click.prevent="sendRestaurantLoginCode">
                                    Code erneut senden
                                </v-btn>
                                <v-btn
                                    color="#ea580c"
                                    variant="flat"
                                    rounded="lg"
                                    class="text-none font-weight-bold"
                                    :loading="loginLoading"
                                    :disabled="!canSubmitLoginCode"
                                    type="submit">
                                    Anmelden
                                </v-btn>
                            </div>
                        </v-form>

                        <v-form
                            v-if="loginMode === 'password'"
                            ref="loginPasswordForm"
                            class="mt-4"
                            @submit.prevent="submitRestaurantPasswordLogin">
                            <v-text-field
                                v-model="loginPassword"
                                autofocus
                                label="Passwort"
                                type="password"
                                variant="outlined"
                                density="compact"
                                class="mb-3"
                                :disabled="loginLoading"
                                :rules="[required(), minLength(8), maxLength(255)]" />

                            <div class="d-flex justify-end">
                                <v-btn
                                    color="#ea580c"
                                    variant="flat"
                                    rounded="lg"
                                    class="text-none font-weight-bold"
                                    :loading="loginLoading"
                                    :disabled="!canSubmitLoginPassword"
                                    type="submit">
                                    Anmelden
                                </v-btn>
                            </div>
                        </v-form>
                    </div>

                    <div v-else-if="loginCheckResult?.status === 'REGISTER_REQUIRED'" class="restaurant-login-state">
                        <p class="restaurant-login-state__text">
                            Diese E-Mail-Adresse wurde noch nicht für das Restaurant gefunden. Möchten Sie sich registrieren?
                        </p>
                    </div>

                    <div v-else-if="loginCheckResult?.status === 'PENDING_CONFIRMATION'" class="restaurant-login-state">
                        <p class="restaurant-login-state__text">
                            {{ loginCheckResult.message }}
                        </p>
                    </div>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn
                        v-if="loginCheckResult?.status !== 'PENDING_CONFIRMATION'"
                        variant="text"
                        color="secondary"
                        @click="closeLoginDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        v-if="!loginCheckResult || loginCheckResult?.status === 'REGISTER_REQUIRED' || loginCheckResult?.status === 'PENDING_CONFIRMATION'"
                        variant="flat"
                        color="#ea580c"
                        class="text-none font-weight-bold"
                        :loading="loginCheckLoading"
                        :disabled="!canSubmitLoginEmail"
                        @click="handleLoginPrimaryAction">
                        {{ loginPrimaryActionLabel }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showRegisterDialog" persistent max-width="440">
            <v-card rounded="xl">
                <v-card-title class="pt-5 px-6 font-weight-bold">Registrieren</v-card-title>
                <v-card-text class="px-6">
                    <div v-if="schoolInfoName" class="restaurant-login-school">
                        <v-icon icon="mdi-domain" size="16" class="restaurant-login-school__icon" />
                        <span>{{ schoolInfoName }}</span>
                    </div>

                    <p class="restaurant-login-intro">{{ registerIntroText }}</p>

                    <v-form
                        v-if="registerResult?.status !== 'REGISTERED'"
                        ref="registerForm"
                        @submit.prevent="handleRegisterPrimaryAction">
                        <div v-if="registerEmail" class="restaurant-login-email mb-3">
                            <div class="restaurant-login-email__label">E-Mail</div>
                            <div class="restaurant-login-email__value">{{ registerEmail }}</div>
                        </div>

                        <div v-if="registerSourceInfoText" class="restaurant-login-state mb-3">
                            <p class="restaurant-login-state__text">
                                {{ registerSourceInfoText }}
                            </p>
                        </div>

                        <div
                            v-if="loginCheckResult?.parent_contact?.children?.length"
                            class="restaurant-login-state__matches mb-3">
                            <div
                                v-for="child in loginCheckResult.parent_contact.children"
                                :key="child"
                                class="restaurant-login-state__match">
                                <strong>{{ child }}</strong>
                            </div>
                        </div>

                        <div v-if="registerIsWaitingForEmailConfirmation" class="mb-3">
                            <v-otp-input
                                v-model="registerEmailToken"
                                autofocus
                                class="mb-3" />

                            <div class="d-flex justify-end">
                                <v-btn
                                    variant="text"
                                    color="#ea580c"
                                    class="text-none"
                                    :disabled="registerLoading"
                                    @click.prevent="retryRestaurantRegistrationEmail">
                                    Code erneut senden
                                </v-btn>
                            </div>
                        </div>

                        <v-text-field
                            v-if="registerRequiresManualNameFields && registerHasConfirmedEmail"
                            v-model="registerLastName"
                            autofocus
                            label="Nachname"
                            variant="outlined"
                            density="compact"
                            class="mb-3"
                            :rules="[required(), maxLength(255)]" />

                        <v-text-field
                            v-if="registerRequiresManualNameFields && registerHasConfirmedEmail"
                            v-model="registerFirstName"
                            label="Vorname"
                            variant="outlined"
                            density="compact"
                            class="mb-3"
                            :rules="[required(), maxLength(255)]" />

                        <div v-if="registerShouldAskForPasswordChoice" class="restaurant-login-state mb-3">
                            <p class="restaurant-login-state__text">
                                Möchten Sie jetzt ein Passwort für spätere Anmeldungen festlegen?
                            </p>

                            <div class="restaurant-login-state__actions">
                                <v-btn
                                    color="#ea580c"
                                    :variant="registerPasswordIntent === 'yes' ? 'flat' : 'outlined'"
                                    rounded="lg"
                                    class="text-none font-weight-bold"
                                    @click.prevent="setRegisterPasswordIntent('yes')">
                                    Ja
                                </v-btn>
                                <v-btn
                                    color="#ea580c"
                                    :variant="registerPasswordIntent === 'no' ? 'flat' : 'outlined'"
                                    rounded="lg"
                                    class="text-none font-weight-bold"
                                    @click.prevent="setRegisterPasswordIntent('no')">
                                    Nein
                                </v-btn>
                            </div>
                        </div>

                        <v-text-field
                            v-if="registerShouldAskForPasswordInput"
                            v-model="registerPassword"
                            label="Passwort"
                            type="password"
                            variant="outlined"
                            density="compact"
                            class="mb-3"
                            :rules="[required(), minLength(8), maxLength(255)]" />
                    </v-form>

                    <v-alert
                        v-if="registerError"
                        type="error"
                        variant="tonal"
                        density="comfortable"
                        class="mb-3">
                        {{ registerError }}
                    </v-alert>

                    <v-alert
                        v-if="registerResult"
                        :type="registerResult.status === 'CONFIRM_EMAIL' ? 'info' : 'success'"
                        variant="tonal"
                        density="comfortable">
                        {{ registerResult.message }}
                    </v-alert>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" color="secondary" @click="closeRegisterDialog">
                        {{ registerResult?.status === 'REGISTERED' ? 'Schließen' : 'Abbrechen' }}
                    </v-btn>
                    <v-btn
                        v-if="registerResult?.status !== 'REGISTERED'"
                        variant="flat"
                        color="#ea580c"
                        class="text-none font-weight-bold"
                        :loading="registerLoading"
                        :disabled="!canSubmitRegister"
                        @click="handleRegisterPrimaryAction">
                        {{ registerPrimaryActionLabel }}
                    </v-btn>
        </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showSepaDialog" persistent max-width="920">
            <v-card rounded="xl">
                <v-card-title class="pt-5 px-6 font-weight-bold">SEPA-Lastschriftmandat</v-card-title>
                <v-card-text class="px-6">
                    <div v-if="schoolInfoName" class="restaurant-login-school mb-3">
                        <v-icon icon="mdi-domain" size="16" class="restaurant-login-school__icon" />
                        <span>{{ schoolInfoName }}</span>
                    </div>

                    <div v-if="sepaDialogEmail" class="restaurant-login-email mb-4">
                        <div class="restaurant-login-email__label">E-Mail</div>
                        <div class="restaurant-login-email__value">{{ sepaDialogEmail }}</div>
                    </div>

                    <v-alert
                        v-if="sepaError"
                        type="error"
                        variant="tonal"
                        density="comfortable"
                        class="mb-3">
                        {{ sepaError }}
                    </v-alert>

                    <v-alert
                        v-if="sepaSuccess"
                        type="success"
                        variant="tonal"
                        density="comfortable"
                        class="mb-3">
                        {{ sepaSuccess }}
                    </v-alert>

                    <template v-if="sepaDialogStage === 'form'">
                        <p class="restaurant-login-intro mb-4">
                            Bitte füllen Sie das SEPA-Lastschriftmandat vollständig aus und bestätigen Sie es online.
                        </p>

                        <v-form ref="sepaFormRef" v-model="sepaFormValid" @submit.prevent="submitRestaurantSepaMandate">
                            <div class="restaurant-sepa-panel mb-4">
                                <div class="restaurant-sepa-panel__title">Angaben zum Zahlungsempfänger (Gläubiger)</div>
                                <div class="restaurant-sepa-richtext" v-html="sepaPayeeHtml || '<p>Nicht hinterlegt</p>'" />
                            </div>

                            <div class="restaurant-sepa-panel mb-4">
                                <div class="restaurant-sepa-panel__title">Angaben zum Zahlungspflichtigen (Debitor / Kontoinhaber)</div>

                                <v-text-field
                                    v-model="sepaForm.account_holder_name"
                                    label="Name des Kontoinhabers (Vor- und Nachname)"
                                    variant="outlined"
                                    density="compact"
                                    class="mb-3"
                                    :rules="[required(), maxLength(255)]" />

                                <v-textarea
                                    v-model="sepaForm.address_line"
                                    label="Straße und Hausnummer"
                                    variant="outlined"
                                    density="compact"
                                    class="mb-3"
                                    rows="2"
                                    auto-grow
                                    :rules="[required(), maxLength(500)]" />

                                <v-row dense class="mb-3">
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="sepaForm.postal_code"
                                            label="PLZ"
                                            variant="outlined"
                                            density="compact"
                                            :rules="[required(), maxLength(16)]" />
                                    </v-col>

                                    <v-col cols="12" md="8">
                                        <v-text-field
                                            v-model="sepaForm.city"
                                            label="Ort"
                                            variant="outlined"
                                            density="compact"
                                            :rules="[required(), maxLength(255)]" />
                                    </v-col>
                                </v-row>

                                <v-text-field
                                    v-model="sepaForm.country"
                                    label="Land"
                                    placeholder="Österreich"
                                    variant="outlined"
                                    density="compact"
                                    class="mb-3"
                                    :rules="[required(), maxLength(255)]" />

                                <v-text-field
                                    v-model="sepaForm.iban"
                                    label="IBAN"
                                    placeholder="AT12 3456 7890 1234 5678"
                                    variant="outlined"
                                    density="compact"
                                    class="mb-3 restaurant-sepa-iban-field"
                                    inputmode="text"
                                    autocapitalize="characters"
                                    spellcheck="false"
                                    :rules="[required(), iban(), maxLength(64)]"
                                    @update:model-value="onSepaIbanInput" />

                                <v-text-field
                                    v-model="sepaForm.bic"
                                    label="BIC (optional im SEPA-Raum)"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[maxLength(64)]" />
                            </div>

                            <div class="restaurant-sepa-panel mb-4">
                                <div class="restaurant-sepa-panel__title">Angaben zum Kind</div>

                                <div
                                    v-for="(child, index) in sepaForm.child_entries"
                                    :key="`sepa-child-${index}`"
                                    class="restaurant-sepa-child-row">
                                    <v-text-field
                                        v-model="child.name"
                                        label="Name des Kindes"
                                        variant="outlined"
                                        density="compact"
                                        class="mb-3"
                                        :rules="[required(), maxLength(255)]" />

                                    <v-text-field
                                        v-model="child.schoolclass"
                                        label="Klasse des Kindes"
                                        variant="outlined"
                                        density="compact"
                                        class="mb-3"
                                        :rules="[required(), maxLength(255)]" />

                                    <v-btn
                                        v-if="sepaForm.child_entries.length > 1"
                                        variant="text"
                                        color="#ea580c"
                                        class="text-none mb-3"
                                        @click.prevent="removeSepaChild(index)">
                                        Kind entfernen
                                    </v-btn>
                                </div>

                                <v-btn
                                    variant="outlined"
                                    color="#ea580c"
                                    class="text-none font-weight-bold"
                                    @click.prevent="addSepaChild">
                                    Weiteres Kind hinzufügen
                                </v-btn>
                            </div>

                            <div class="restaurant-sepa-panel mb-4">
                                <div class="restaurant-sepa-panel__title">SEPA-Ermächtigung</div>
                                <div class="restaurant-sepa-richtext mb-3" v-html="sepaMandateTextHtml || '<p>Nicht hinterlegt</p>'" />
                                <v-checkbox
                                    v-model="sepaForm.accepted"
                                    color="#ea580c"
                                    hide-details
                                    label="Ich akzeptiere die SEPA-Ermächtigung und erteile das Mandat." />
                            </div>
                        </v-form>
                    </template>

                    <template v-else-if="sepaDialogStage === 'code'">
                        <p class="restaurant-login-intro mb-4">
                            Bitte geben Sie jetzt den 6-stelligen Bestätigungscode aus der E-Mail ein.
                        </p>

                        <v-otp-input
                            v-model="sepaCode"
                            autofocus
                            class="mb-3" />

                        <div class="d-flex justify-end">
                            <v-btn
                                variant="text"
                                color="#ea580c"
                                class="text-none"
                                :disabled="sepaLoading || !sepaFlow?.flow_uuid"
                                @click.prevent="resendRestaurantSepaCode">
                                Code erneut senden
                            </v-btn>
                        </div>
                    </template>

                    <template v-else>
                        <p class="restaurant-login-intro mb-4">
                            Bitte prüfen Sie das online bestätigte SEPA-Lastschriftmandat.
                        </p>

                        <div class="restaurant-sepa-preview">
                            <div class="restaurant-sepa-preview__title">SEPA-Lastschriftmandat</div>

                            <div class="restaurant-sepa-panel mb-4">
                                <div class="restaurant-sepa-panel__title">Angaben zum Zahlungsempfänger (Gläubiger)</div>
                                <div class="restaurant-sepa-richtext" v-html="sepaPayeeHtml || '<p>Nicht hinterlegt</p>'" />
                            </div>

                            <div class="restaurant-sepa-panel mb-4">
                                <div class="restaurant-sepa-panel__title">Angaben zum Zahlungspflichtigen (Debitor / Kontoinhaber)</div>
                                <div class="restaurant-sepa-preview__line"><strong>Name:</strong> {{ sepaForm.account_holder_name }}</div>
                                <div class="restaurant-sepa-preview__line"><strong>Straße:</strong> {{ sepaForm.address_line }}</div>
                                <div class="restaurant-sepa-preview__line"><strong>PLZ:</strong> {{ sepaForm.postal_code }}</div>
                                <div class="restaurant-sepa-preview__line"><strong>Ort:</strong> {{ sepaForm.city }}</div>
                                <div class="restaurant-sepa-preview__line"><strong>Land:</strong> {{ sepaForm.country }}</div>
                                <div class="restaurant-sepa-preview__line"><strong>IBAN:</strong> {{ sepaForm.iban }}</div>
                                <div class="restaurant-sepa-preview__line"><strong>BIC:</strong> {{ sepaForm.bic || 'Nicht angegeben' }}</div>
                            </div>

                            <div class="restaurant-sepa-panel mb-4">
                                <div class="restaurant-sepa-panel__title">Angaben zum Kind</div>
                                <div
                                    v-for="(child, index) in sepaForm.child_entries"
                                    :key="`sepa-preview-child-${index}`"
                                    class="restaurant-sepa-preview__line">
                                    <strong>{{ child.name }}</strong><span v-if="child.schoolclass"> · {{ child.schoolclass }}</span>
                                </div>
                            </div>

                            <div class="restaurant-sepa-panel mb-4">
                                <div class="restaurant-sepa-panel__title">SEPA-Ermächtigung</div>
                                <div class="restaurant-sepa-richtext" v-html="sepaMandateTextHtml || '<p>Nicht hinterlegt</p>'" />
                            </div>

                            <div class="restaurant-sepa-signature-row">
                                <div class="restaurant-sepa-signature-box">
                                    <div class="restaurant-sepa-signature-box__label">Ort</div>
                                    <div class="restaurant-sepa-signature-box__value">{{ sepaForm.city || 'Nicht angegeben' }}</div>
                                </div>
                                <div class="restaurant-sepa-signature-box">
                                    <div class="restaurant-sepa-signature-box__label">Datum</div>
                                    <div class="restaurant-sepa-signature-box__value">{{ sepaConfirmedDateLabel }}</div>
                                </div>
                                <div class="restaurant-sepa-signature-box">
                                    <div class="restaurant-sepa-signature-box__label">Unterschrift Kontoinhaber/in</div>
                                    <div class="restaurant-sepa-signature-box__value">{{ sepaSignatureLabel }}</div>
                                    <div class="restaurant-sepa-signature-box__meta">Online bestätigt</div>
                                </div>
                            </div>

                            <v-checkbox
                                v-model="sepaApprovalAccepted"
                                color="#ea580c"
                                hide-details
                                class="mt-2"
                                label="Einverstanden" />
                        </div>
                    </template>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        color="secondary"
                        :disabled="sepaLoading"
                        @click="closeSepaDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        v-if="sepaDialogStage === 'form'"
                        variant="flat"
                        color="#ea580c"
                        class="text-none font-weight-bold"
                        :loading="sepaLoading"
                        :disabled="!canSubmitSepaMandate"
                        @click="submitRestaurantSepaMandate">
                        Mandat erteilen
                    </v-btn>
                    <v-btn
                        v-else-if="sepaDialogStage === 'code'"
                        variant="flat"
                        color="#ea580c"
                        class="text-none font-weight-bold"
                        :loading="sepaLoading"
                        :disabled="sepaCode.trim().length !== 6"
                        @click="confirmRestaurantSepaCode">
                        Code bestätigen
                    </v-btn>
                    <v-btn
                        v-else
                        variant="flat"
                        color="#ea580c"
                        class="text-none font-weight-bold"
                        :loading="sepaLoading"
                        :disabled="!sepaApprovalAccepted"
                        @click="completeRestaurantSepaFlow">
                        Genehmigen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showBookingDialog" persistent max-width="500">
            <v-card rounded="xl">
                <v-card-title class="pt-5 px-6 font-weight-bold">Menü buchen</v-card-title>
                <v-card-text class="px-6">
                    <div v-if="selectedMenuEntry" class="mb-4">
                        <div class="restaurant-login-email mb-2">
                            <div class="restaurant-login-email__label">Menü</div>
                            <div class="restaurant-login-email__value">{{ selectedMenuEntry.menu_title || selectedMenuEntry.menu?.title || 'Menü' }}</div>
                        </div>
                        <div v-if="selectedMenuEntry.price" class="restaurant-login-email mb-2">
                            <div class="restaurant-login-email__label">Preis</div>
                            <div class="restaurant-login-email__value">{{ formatPrice(selectedMenuEntry.price) }}</div>
                        </div>
                        <div v-if="selectedMenuEntry.plan_date" class="restaurant-login-email">
                            <div class="restaurant-login-email__label">Datum</div>
                            <div class="restaurant-login-email__value">{{ formatDateWithWeekday(selectedMenuEntry.plan_date) }}</div>
                        </div>
                    </div>

                    <v-alert
                        v-if="bookingSuccess"
                        type="success"
                        variant="tonal"
                        density="comfortable"
                        class="mb-3">
                        {{ bookingSuccess }}
                    </v-alert>

                    <v-alert
                        v-if="bookingError"
                        type="error"
                        variant="tonal"
                        density="comfortable"
                        class="mb-3">
                        {{ bookingError }}
                    </v-alert>

                    <v-alert
                        v-if="bookingChildOptionsLoading"
                        type="info"
                        variant="tonal"
                        density="comfortable"
                        class="mb-3">
                        Die Kinderauswahl wird geladen...
                    </v-alert>

                    <v-form @submit.prevent="submitBooking">
                        <div v-if="selectedMenuEntry?.eating_times?.length" class="mb-3">
                            <div class="booking-time-picker">
                                <div class="booking-time-picker__label">Speisezeit</div>
                                <div class="booking-time-picker__options">
                                    <button
                                        v-for="eatingTime in selectedMenuEntry.eating_times"
                                        :key="eatingTime.id"
                                        type="button"
                                        class="booking-time-picker__option"
                                        :class="{
                                            'booking-time-picker__option--active': bookingData.restaurant_eating_time_id === eatingTime.id,
                                        }"
                                        :aria-pressed="bookingData.restaurant_eating_time_id === eatingTime.id"
                                        @click="bookingData.restaurant_eating_time_id = eatingTime.id">
                                        {{ formatEatingTime(eatingTime.eating_time) }} Uhr
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="booking-quantity-picker mb-3">
                            <div class="booking-quantity-picker__label">Anzahl</div>
                            <div class="booking-quantity-picker__options">
                                <button
                                    v-for="quantity in bookingQuantityOptions"
                                    :key="quantity"
                                    type="button"
                                    class="booking-quantity-picker__option"
                                    :class="{
                                        'booking-quantity-picker__option--active': bookingData.quantity === quantity,
                                    }"
                                    :aria-pressed="bookingData.quantity === quantity"
                                    @click="setBookingQuantity(quantity)">
                                    {{ quantity }}
                                </button>
                                <button
                                    v-if="!bookingQuantityExpanded"
                                    type="button"
                                    class="booking-quantity-picker__more"
                                    aria-expanded="false"
                                    @click="bookingQuantityExpanded = true">
                                    Mehr
                                </button>
                            </div>
                        </div>

                        <div class="booking-recipient-section mb-3">
                            <div class="booking-recipient-section__label">Für wen ist das Menü?</div>

                            <div v-if="bookingData.quantity === 1">
                                <div class="booking-recipient-summary">
                                    Standardmäßig für <strong>{{ bookingSingleRecipientDisplayName }}</strong>.
                                </div>

                                <div v-if="bookingHasMultipleChildOptions" class="booking-child-picker">
                                    <div class="booking-child-picker__label">Kind schnell auswählen</div>
                                    <div class="booking-child-picker__options">
                                        <button
                                            v-for="child in childOptions"
                                            :key="child.id"
                                            type="button"
                                            class="booking-child-picker__option"
                                            :class="{
                                                'booking-child-picker__option--active': isSingleChildSelected(child),
                                            }"
                                            :aria-pressed="isSingleChildSelected(child)"
                                            @click="selectSingleChildRecipient(child)">
                                            {{ child.name }}
                                        </button>
                                    </div>
                                </div>

                                <label class="booking-recipient-toggle">
                                    <input
                                        type="checkbox"
                                        :checked="bookingRecipientOverride"
                                        @change="setSingleRecipientOverride($event.target.checked)" />
                                    <span>Für jemanden anderen</span>
                                </label>

                                <div v-if="shouldShowSingleRecipientEditor" class="booking-recipient-field-wrap mt-3">
                                    <label class="booking-recipient-field__label" for="booking-recipient-0">Name</label>
                                    <input
                                        id="booking-recipient-0"
                                        v-model="bookingData.recipients[0].name"
                                        @input="onRecipientNameInput(0)"
                                        type="text"
                                        maxlength="255"
                                        class="booking-recipient-field"
                                        autocomplete="off" />
                                </div>
                            </div>

                            <div v-else class="booking-recipient-list">
                                <div
                                    v-for="(recipient, index) in bookingRecipients"
                                    :key="`booking-recipient-${index}`"
                                    class="booking-recipient-field-wrap">
                                    <label class="booking-recipient-field__label" :for="`booking-recipient-${index}`">
                                        {{ recipientFieldLabel(index) }}
                                    </label>
                                    <input
                                        :id="`booking-recipient-${index}`"
                                        v-model="bookingData.recipients[index].name"
                                        @input="onRecipientNameInput(index)"
                                        type="text"
                                        maxlength="255"
                                        class="booking-recipient-field"
                                        autocomplete="off" />
                                    <div
                                        v-if="bookingIsImport116Parent && recipient.type === 'child' && recipient.import116_id"
                                        class="booking-recipient-field__hint">
                                        Aus den hinterlegten Kindern übernommen
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="false && restaurantAuthUser?.import116_parent">
                            <div v-if="childOptions.length === 1" class="mb-3">
                                <v-text-field
                                    v-model="bookingData.child_name"
                                    :label="`Für welches Kind (${childOptions[0].name})`"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[required(), maxLength(255)]"
                                    required />
                                <input type="hidden" v-model="bookingData.child_type" value="child" />
                            </div>
                            <div v-else-if="childOptions.length > 1" class="mb-3">
                                <v-select
                                    v-model="bookingData.child_name"
                                    :items="[
                                        ...childOptions.map(child => ({ title: child.name, value: child.name })),
                                        { title: 'Andere Person', value: 'other_person' }
                                    ]"
                                    label="Für wen bestellen?"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[required()]"
                                    required />
                                
                                <div v-if="bookingData.child_name === 'other_person'" class="mt-3">
                                    <v-text-field
                                        v-model="bookingData.child_name"
                                        label="Name der Person"
                                        variant="outlined"
                                        density="compact"
                                        :rules="[required(), maxLength(255)]"
                                        required />
                                    <input type="hidden" v-model="bookingData.child_type" value="other_person" />
                                </div>
                                <div v-else>
                                    <input type="hidden" v-model="bookingData.child_type" value="child" />
                                </div>
                            </div>
                            <div v-else class="mb-3">
                                <v-text-field
                                    v-model="bookingData.child_name"
                                    label="Für wen bestellen? (Name)"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[required(), maxLength(255)]"
                                    required />
                                <v-select
                                    v-model="bookingData.child_type"
                                    :items="[
                                        { title: 'Kind', value: 'child' },
                                        { title: 'Andere Person', value: 'other_person' }
                                    ]"
                                    label="Typ"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[required()]"
                                    required />
                            </div>
                        </div>

                    </v-form>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        color="#9a3412"
                        class="text-none"
                        :disabled="bookingLoading"
                        @click="closeBookingDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="#ea580c"
                        variant="flat"
                        rounded="lg"
                        class="text-none font-weight-bold"
                        :disabled="!canSubmitBooking"
                        :loading="bookingLoading"
                        @click="submitBooking">
                        Buchen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showCancelBookingDialog" persistent max-width="420">
            <v-card rounded="xl">
                <v-card-title class="pt-5 px-6 font-weight-bold">Buchung stornieren?</v-card-title>
                <v-card-text class="px-6">
                    <p class="restaurant-login-state__text mb-3">
                        Möchten Sie diese Buchung wirklich stornieren?
                    </p>

                    <div v-if="pendingCancellationBooking" class="restaurant-login-email">
                        <div class="restaurant-login-email__label">Buchung</div>
                        <div class="restaurant-login-email__value">
                            {{ pendingCancellationBooking.menu_title || 'Menü' }}
                        </div>
                        <div class="restaurant-auth-bookings__item-meta">
                            <strong>{{ pendingCancellationBooking.quantity }}x</strong>
                            <span v-if="pendingCancellationBooking.eating_time"> um {{ formatEatingTime(pendingCancellationBooking.eating_time) }} Uhr</span>
                            <span v-if="bookingRecipientNames(pendingCancellationBooking)"> · {{ bookingRecipientNames(pendingCancellationBooking) }}</span>
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        color="secondary"
                        :disabled="pendingCancellationLoading"
                        @click="closeCancelBookingDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        variant="flat"
                        color="#ea580c"
                        class="text-none font-weight-bold"
                        :loading="pendingCancellationLoading"
                        :disabled="pendingCancellationLoading"
                        @click="confirmBookingCancellation">
                        Ja, stornieren
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useValidationRulesSetup } from '@/helpers/rules'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
import axios from 'axios'

export default {
    name: 'HomepageRestaurantPage',

    async beforeMount() {
        this.homepageStore = useHomepageStore()

        const schoolFromUrl = this.$route?.query?.school ?? null
        this.schoolFromUrl = !!schoolFromUrl

        if (! this.config) {
            await this.homepageStore.loadConfig(schoolFromUrl, this.$route?.query?.app ?? null)
        }

        this.sepaFlow = this.config?.restaurant?.sepa_flow || null

        if (this.config?.school?.short_name) {
            this.selectedSchoolShortName = this.config.school.short_name
        }

        await this.homepageStore.loadSchoolsForTool('Restaurant')

        if (!this.currentSchoolShortName && this.selectableSchools.length === 1) {
            await this.onSchoolSelected(this.selectableSchools[0].short_name)
        }

        await this.loadMenuPlans()

        if (this.restaurantRequiresSepa && this.sepaFlow) {
            this.openSepaDialog(this.sepaFlow, 'login')
        }
    },

    data() {
        return {
            homepageStore: null,
            selectedSchoolShortName: null,
            schoolFromUrl: false,
            menuPlans: [],
            userBookings: [],
            showLoginDialog: false,
            showRegisterDialog: false,
            showPasswordDialog: false,
            loginEmail: '',
            loginCheckLoading: false,
            loginCheckResult: null,
            loginCheckError: '',
            loginSelectedUserId: null,
            loginMode: null,
            loginCodeToken: '',
            loginPassword: '',
            loginLoading: false,
            loginAuthError: '',
            loginActionMessage: '',
            registerFirstName: '',
            registerLastName: '',
            registerEmailToken: '',
            registerConfirmationToken: '',
            registerPasswordChoicePrompted: false,
            registerPasswordIntent: null,
            registerPassword: '',
            registerLoading: false,
            registerResult: null,
            registerError: '',
            showSepaDialog: false,
            sepaLoading: false,
            sepaError: '',
            sepaSuccess: '',
            sepaFlow: null,
            sepaContextAction: null,
            sepaCode: '',
            sepaApprovalAccepted: false,
            sepaFormValid: false,
            sepaForm: {
                account_holder_name: '',
                address_line: '',
                postal_code: '',
                city: '',
                country: 'Österreich',
                iban: '',
                bic: '',
                accepted: false,
                child_entries: [
                    { name: '', schoolclass: '' },
                ],
            },
            restaurantPassword: '',
            restaurantPasswordConfirmation: '',
            restaurantPasswordLoading: false,
            restaurantPasswordError: '',
            restaurantPasswordSuccess: '',
            schoolSearch: '',
        tickNow: Date.now(),
        tickInterval: null,
        // Booking dialog
        showBookingDialog: false,
        selectedMenuEntry: null,
        bookingData: {
            restaurant_menu_plan_entry_id: null,
            restaurant_eating_time_id: null,
            quantity: 1,
            price: null,
            recipients: [],
            child_name: '',
            child_type: null,
            notes: '',
        },
        bookingLoading: false,
        bookingError: '',
        bookingSuccess: '',
        bookingCancellationIds: [],
        showCancelBookingDialog: false,
        pendingCancellationBooking: null,
        pendingCancellationLoading: false,
        bookingChildOptionsLoading: false,
        bookingQuantityExpanded: false,
        childOptions: [],
        bookingFormContext: {
            is_import116_parent: false,
            self_name: '',
            booking_defaults: {
                recipients: [],
                single_recipient_customized: false,
            },
        },
        bookingRecipientOverride: false,
        }
    },

    mounted() {
        this.tickInterval = setInterval(() => { this.tickNow = Date.now() }, 1000)
    },

    beforeUnmount() {
        if (this.tickInterval) clearInterval(this.tickInterval)
    },

    computed: {
        ...mapWritableState(useHomepageStore, ['config']),
        restaurantIntroHtml() {
            return this.config?.restaurant?.user_information_intro_html || ''
        },
        restaurantAuthUser() {
            return this.config?.auth_check === true ? this.config?.auth_user || null : null
        },
        restaurantAuthDisplayName() {
            const firstName = this.restaurantAuthUser?.first_name?.trim?.() || ''
            const lastName = this.restaurantAuthUser?.last_name?.trim?.() || ''
            const fullName = [firstName, lastName].filter(Boolean).join(' ').trim()

            return fullName || this.restaurantAuthUser?.email || ''
        },
        restaurantAuthEmail() {
            return this.restaurantAuthUser?.email?.trim?.() || ''
        },
        restaurantRequiresSepa() {
            return this.config?.restaurant?.sepa_online_enabled === true
                && this.restaurantAuthUser?.has_sepa !== true
        },
        sepaDialogEmail() {
            return this.sepaFlow?.email?.trim?.() || this.restaurantAuthEmail || this.registerEmail
        },
        sepaDialogStage() {
            if (this.sepaFlow?.confirmed_at) {
                return 'preview'
            }

            if (this.sepaFlow?.status === 'pending_code') {
                return 'code'
            }

            return 'form'
        },
        canSubmitSepaMandate() {
            if (this.sepaLoading) {
                return false
            }

            if (
                this.sepaForm.account_holder_name.trim() === ''
                || this.sepaForm.address_line.trim() === ''
                || this.sepaForm.postal_code.trim() === ''
                || this.sepaForm.city.trim() === ''
                || this.sepaForm.country.trim() === ''
                || this.sepaForm.iban.trim() === ''
            ) {
                return false
            }

            if (!this.sepaForm.accepted) {
                return false
            }

            return this.sepaForm.child_entries.some((child) => child.name.trim() !== '' && child.schoolclass.trim() !== '')
        },
        sepaPayeeHtml() {
            return this.sepaFlow?.sepa_payee || this.config?.restaurant?.sepa_payee || ''
        },
        sepaMandateTextHtml() {
            return this.sepaFlow?.sepa_mandate_text || this.config?.restaurant?.sepa_mandate_text || ''
        },
        sepaConfirmedDateLabel() {
            const isoDate = this.sepaFlow?.confirmed_at
            if (!isoDate) {
                return ''
            }

            return new Date(isoDate).toLocaleDateString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
        },
        sepaSignatureLabel() {
            return this.sepaFlow?.signature_uuid || this.sepaFlow?.flow_uuid || ''
        },
        todayDateKey() {
            const today = new Date(this.tickNow)
            const year = today.getFullYear()
            const month = String(today.getMonth() + 1).padStart(2, '0')
            const day = String(today.getDate()).padStart(2, '0')

            return `${year}-${month}-${day}`
        },
        sortedUserBookings() {
            return [...this.userBookings].sort((left, right) => {
                const leftDate = (left?.plan_date || '').toString()
                const rightDate = (right?.plan_date || '').toString()

                if (leftDate !== rightDate) {
                    return leftDate.localeCompare(rightDate)
                }

                const leftTime = (left?.eating_time || '').toString()
                const rightTime = (right?.eating_time || '').toString()

                if (leftTime !== rightTime) {
                    return leftTime.localeCompare(rightTime)
                }

                const leftTitle = (left?.menu_title || '').toString()
                const rightTitle = (right?.menu_title || '').toString()

                return leftTitle.localeCompare(rightTitle)
            })
        },
        groupedUserBookings() {
            const groupedBookings = this.sortedUserBookings.reduce((groups, booking) => {
                const parsedDate = this.parseRestaurantDate(booking?.plan_date)
                const dateKey = parsedDate
                    ? parsedDate.toISOString().slice(0, 10)
                    : (booking?.plan_date || '').toString().trim() || `booking-${booking?.id || groups.length}`

                const existingGroup = groups.find((group) => group.dateKey === dateKey)

                if (existingGroup) {
                    existingGroup.bookings.push(booking)
                    return groups
                }

                groups.push({
                    dateKey,
                    dayLabel: this.weekdayLabel(booking?.plan_date),
                    dateLabel: this.formatDateShort(booking?.plan_date),
                    bookings: [booking],
                })

                return groups
            }, [])

            return groupedBookings.map((group) => {
                const menuGroups = group.bookings.reduce((menus, booking) => {
                    const menuTitle = (booking?.menu_title || '').toString().trim() || 'Menü'
                    const existingMenuGroup = menus.find((menuGroup) => menuGroup.menuKey === menuTitle)

                    if (existingMenuGroup) {
                        existingMenuGroup.bookings.push(booking)
                        return menus
                    }

                    menus.push({
                        menuKey: menuTitle,
                        menuTitle,
                        bookings: [booking],
                    })

                    return menus
                }, [])

                return {
                    ...group,
                    menuGroups,
                }
            })
        },
        schoolInfoName() {
            return this.config?.school?.long_name || this.config?.school?.short_name || 'Keine Schule ausgewählt'
        },
        orderableMenuPlansCount() {
            return Number(this.config?.restaurant?.orderable_menu_plans_count || 0)
        },
        visibleMenuPlansCount() {
            return Number(this.config?.restaurant?.visible_menu_plans_count || 0)
        },
        selectableSchools() {
            return this.homepageStore?.schools || []
        },
        filteredSchools() {
            if (!this.schoolSearch) return this.selectableSchools
            const q = this.schoolSearch.toLowerCase()
            return this.selectableSchools.filter(s =>
                (s.long_name || '').toLowerCase().includes(q) ||
                (s.short_name || '').toLowerCase().includes(q)
            )
        },
        currentSchoolShortName() {
            return this.config?.school?.short_name || null
        },
        currentSchoolId() {
            return Number(this.config?.school?.id || 0) || null
        },
        canSubmitLoginEmail() {
            return !!this.currentSchoolId && this.loginEmail.trim() !== ''
        },
        selectedLoginUser() {
            return this.loginCheckResult?.matched_users?.find(user => user.id === this.loginSelectedUserId) || null
        },
        canSubmitLoginCode() {
            return !!this.selectedLoginUser && this.loginCodeToken.trim().length === 6 && !this.loginLoading
        },
        canSubmitLoginPassword() {
            return !!this.selectedLoginUser && this.loginPassword.length >= 8 && !this.loginLoading
        },
        restaurantPasswordMatchRule() {
            return () => {
                if (this.restaurantPassword !== this.restaurantPasswordConfirmation) {
                    return 'Die Passwörter stimmen nicht überein.'
                }

                return true
            }
        },
        canSubmitRestaurantPasswordChange() {
            return this.restaurantPassword.length >= 8
                && this.restaurantPasswordConfirmation.length >= 8
                && this.restaurantPassword === this.restaurantPasswordConfirmation
                && !this.restaurantPasswordLoading
        },
        bookingQuantityOptions() {
            return this.bookingQuantityExpanded ? [1, 2, 3, 4] : [1]
        },
        bookingRequiresEatingTime() {
            return (this.selectedMenuEntry?.eating_times?.length || 0) > 0
        },
        bookingIsImport116Parent() {
            return this.bookingFormContext?.is_import116_parent === true
        },
        bookingRecipients() {
            return Array.isArray(this.bookingData.recipients) ? this.bookingData.recipients : []
        },
        bookingHasMultipleChildOptions() {
            return this.bookingData.quantity === 1 && this.bookingIsImport116Parent && this.childOptions.length > 1
        },
        bookingSingleRecipient() {
            return this.bookingRecipients[0] || null
        },
        bookingSingleRecipientBase() {
            return this.buildBaseRecipients(1)[0] || this.defaultBlankRecipient()
        },
        shouldShowSingleRecipientEditor() {
            return this.bookingData.quantity === 1 && this.bookingRecipientOverride
        },
        bookingSingleRecipientDisplayName() {
            return this.bookingSingleRecipient?.name?.trim?.()
                || this.bookingSingleRecipientBase?.name?.trim?.()
                || 'jemanden'
        },
        canSubmitBooking() {
            if (this.bookingLoading || !this.selectedMenuEntry) {
                return false
            }

            if (this.bookingRequiresEatingTime && !this.bookingData.restaurant_eating_time_id) {
                return false
            }

            if (this.bookingRecipients.length !== Number(this.bookingData.quantity || 0)) {
                return false
            }

            return this.bookingRecipients.every((recipient) => (recipient?.name || '').trim() !== '')
        },
        loginPrimaryActionLabel() {
            if (this.loginCheckResult?.status === 'REGISTER_REQUIRED') {
                return 'Registrieren'
            }

            if (this.loginCheckResult?.status === 'PENDING_CONFIRMATION') {
                return 'Schließen'
            }

            return 'Weiter'
        },
        registerEmail() {
            return (this.loginCheckResult?.email || this.loginEmail || '').trim()
        },
        registerSource() {
            return this.loginCheckResult?.registration_source || null
        },
        registerRequiresManualNameFields() {
            return this.registerSource === 'new_user'
        },
        restaurantNewUsersMustConfirmEmail() {
            return this.config?.restaurant?.new_users_must_confirm_email === true
        },
        registerIsWaitingForEmailConfirmation() {
            return this.registerSource === 'new_user' && this.registerResult?.status === 'CONFIRM_EMAIL'
        },
        registerHasConfirmedEmail() {
            return this.registerSource === 'new_user'
                && this.registerResult?.status === 'ENTER_USER_DATA'
                && this.registerConfirmationToken.trim() !== ''
        },
        registerShouldOfferPasswordChoice() {
            return this.registerSource === 'new_user'
                && this.registerHasConfirmedEmail
        },
        registerShouldAskForPasswordChoice() {
            return this.registerShouldOfferPasswordChoice && this.registerPasswordChoicePrompted
        },
        registerShouldAskForPasswordInput() {
            return this.registerShouldAskForPasswordChoice && this.registerPasswordIntent === 'yes'
        },
        canSubmitRegister() {
            if (!this.currentSchoolId || !this.registerEmail || this.registerLoading) {
                return false
            }

            if (this.registerIsWaitingForEmailConfirmation) {
                return this.registerEmailToken.trim().length === 6
            }

            if (!this.registerRequiresManualNameFields) {
                return true
            }

            if (!this.registerHasConfirmedEmail) {
                return this.registerResult === null
            }

            if (this.registerShouldOfferPasswordChoice && !this.registerPasswordChoicePrompted) {
                return this.registerFirstName.trim() !== '' && this.registerLastName.trim() !== ''
            }

            if (this.registerShouldAskForPasswordChoice && this.registerPasswordIntent === null) {
                return false
            }

            if (this.registerShouldAskForPasswordInput) {
                return this.registerPassword.length >= 8
            }

            return this.registerFirstName.trim() !== '' && this.registerLastName.trim() !== ''
        },
        registerIntroText() {
            if (this.registerSource === 'new_user' && !this.registerResult) {
                return 'Bitte bestaetigen Sie zuerst Ihre E-Mail-Adresse.'
            }
            if (this.registerResult?.status === 'CONFIRM_EMAIL') {
                return 'Bitte geben Sie jetzt den 6-stelligen Code aus der E-Mail ein.'
            }

            if (this.registerResult?.status === 'ENTER_USER_DATA') {
                if (this.registerShouldAskForPasswordChoice && this.registerPasswordIntent === null) {
                    return 'Möchten Sie jetzt noch ein Passwort festlegen?'
                }

                if (this.registerShouldAskForPasswordInput) {
                    return 'Bitte geben Sie jetzt Ihr gewünschtes Passwort ein.'
                }
                return 'Die E-Mail-Adresse ist bestätigt. Bitte ergänzen Sie jetzt Nachname und Vorname.'
            }

            if (this.registerResult?.status === 'REGISTERED') {
                return ''
            }

            return 'Bitte prüfen Sie die Angaben für die Registrierung.'
        },
        registerPrimaryActionLabel() {
            if (this.registerIsWaitingForEmailConfirmation) {
                return 'Code bestaetigen'
            }

            if (this.registerShouldOfferPasswordChoice && !this.registerPasswordChoicePrompted) {
                return 'Weiter'
            }

            if (this.registerShouldAskForPasswordChoice && this.registerPasswordIntent === null) {
                return 'Weiter'
            }

            if (this.registerSource === 'new_user' && !this.registerHasConfirmedEmail) {
                return 'Code senden'
            }

            return 'Registrieren'
        },
        registerSourceInfoText() {
            if (this.registerSource === 'new_user') {
                if (!this.registerHasConfirmedEmail) {
                    return 'Diese E-Mail-Adresse ist noch in keiner Liste vorhanden. Vor dem Speichern muss die E-Mail-Adresse bestaetigt werden.'
                }

                if (this.registerShouldAskForPasswordChoice && this.registerPasswordIntent === null) {
                    return 'Ihre Angaben sind gespeichert. Sie können jetzt optional noch ein Passwort für spätere Logins festlegen.'
                }

                if (this.registerShouldAskForPasswordInput) {
                    return 'Bitte vergeben Sie jetzt ein Passwort mit mindestens 8 Zeichen.'
                }

                return this.restaurantNewUsersMustConfirmEmail
                    ? ''
                    : 'Diese E-Mail-Adresse ist noch in keiner Liste vorhanden. Nach dem Speichern wird ein neuer lunch_user angelegt und direkt angemeldet.'
            }

            switch (this.registerSource) {
            case 'existing_user':
                return 'Das bestehende Benutzerkonto dieser Schule wird für das Restaurant freigeschaltet.'
            case 'teacher_list':
                return 'Die E-Mail-Adresse wurde in der Lehrerliste gefunden und wird in die Benutzerverwaltung übernommen.'
            case 'import116_student':
                return 'Die E-Mail-Adresse wurde in den Import116-Schülerdaten gefunden und wird in die Benutzerverwaltung übernommen.'
            case 'import116_parent':
                return 'Die E-Mail-Adresse wurde als Elternkontakt im Import116 gefunden. Es wird ein eigenes Restaurantkonto für diesen Elternkontakt angelegt.'
            case 'new_user':
                return 'Diese E-Mail-Adresse ist noch in keiner Liste vorhanden. Bitte ergänzen Sie Nachname und Vorname.'
            default:
                return ''
            }
        },
    },

    methods: {
        ...useValidationRulesSetup(),

        openLoginDialog() {
            this.resetLoginDialogState()
            this.showLoginDialog = true
        },

        closeLoginDialog() {
            this.showLoginDialog = false
            this.resetLoginDialogState()
        },

        openRegisterDialog() {
            this.openLoginDialog()
        },

        openRestaurantPasswordDialog() {
            this.resetRestaurantPasswordDialogState()
            this.showPasswordDialog = true
        },

        closeRestaurantPasswordDialog() {
            this.showPasswordDialog = false
            this.resetRestaurantPasswordDialogState()
        },

        closeRegisterDialog() {
            this.showRegisterDialog = false
            this.resetRegisterDialogState()
        },

        openSepaDialog(flow = null, contextAction = null) {
            const nextFlow = flow || this.config?.restaurant?.sepa_flow || null
            if (!nextFlow) {
                return
            }

            this.sepaFlow = nextFlow
            this.sepaContextAction = contextAction || this.sepaContextAction || 'login'
            this.sepaCode = ''
            this.sepaError = ''
            this.sepaSuccess = ''
            this.sepaApprovalAccepted = false
            this.syncSepaFormFromFlow()
            this.showSepaDialog = true
        },

        openSepaDialogFromConfig() {
            this.openSepaDialog(this.config?.restaurant?.sepa_flow || null, 'login')
        },

        closeSepaDialog() {
            this.showSepaDialog = false
            this.sepaError = ''
            this.sepaSuccess = ''
            this.sepaApprovalAccepted = false
        },

        syncSepaFormFromFlow() {
            const flow = this.sepaFlow || {}
            const childEntries = Array.isArray(flow.child_entries) && flow.child_entries.length > 0
                ? flow.child_entries.map((child) => ({
                    name: (child?.name || '').toString(),
                    schoolclass: (child?.schoolclass || '').toString(),
                }))
                : [{ name: '', schoolclass: '' }]

            this.sepaForm = {
                account_holder_name: (flow.account_holder_name || '').toString(),
                address_line: (flow.address_line || '').toString(),
                postal_code: (flow.postal_code || '').toString(),
                city: (flow.city || '').toString(),
                country: (flow.country || 'Österreich').toString(),
                iban: this.formatSepaIbanDisplayValue(flow.iban || ''),
                bic: (flow.bic || '').toString(),
                accepted: flow.confirmed_at !== null || flow.accepted_at !== null,
                child_entries: childEntries,
            }
        },

        onSepaIbanInput(value) {
            this.sepaForm.iban = this.formatSepaIbanDisplayValue(value)
        },

        formatSepaIbanDisplayValue(value) {
            const normalized = this.normalizeSepaIbanValue(value)
            return normalized.replace(/(.{4})/g, '$1 ').trim()
        },

        normalizeSepaIbanValue(value) {
            return String(value || '')
                .replace(/[^A-Za-z0-9]/g, '')
                .toUpperCase()
                .slice(0, 34)
        },

        addSepaChild() {
            this.sepaForm.child_entries.push({ name: '', schoolclass: '' })
        },

        removeSepaChild(index) {
            if (this.sepaForm.child_entries.length <= 1) {
                return
            }

            this.sepaForm.child_entries.splice(index, 1)
        },

        async submitRestaurantSepaMandate() {
            if (!this.canSubmitSepaMandate || !this.sepaFlow?.flow_uuid) {
                return
            }

            this.sepaFormValid = false
            await this.$refs.sepaFormRef?.validate()

            if (!this.sepaFormValid) {
                return
            }

            this.sepaLoading = true
            this.sepaError = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/sepa/store', {
                    data: {
                        flow_uuid: this.sepaFlow.flow_uuid,
                        account_holder_name: this.sepaForm.account_holder_name.trim(),
                        address_line: this.sepaForm.address_line.trim(),
                        postal_code: this.sepaForm.postal_code.trim(),
                        city: this.sepaForm.city.trim(),
                        country: this.sepaForm.country.trim() || 'Österreich',
                        iban: this.normalizeSepaIbanValue(this.sepaForm.iban),
                        bic: this.sepaForm.bic.trim() || null,
                        child_entries: this.sepaForm.child_entries
                            .map((child) => ({
                                name: (child.name || '').trim(),
                                schoolclass: (child.schoolclass || '').trim(),
                            }))
                            .filter((child) => child.name !== '' || child.schoolclass !== ''),
                        accepted: this.sepaForm.accepted,
                    },
                })

                this.sepaFlow = response.data?.flow || this.sepaFlow
                this.sepaSuccess = response.data?.message || 'Der Bestätigungscode wurde gesendet.'
                this.sepaCode = ''
                this.syncSepaFormFromFlow()
            } catch (error) {
                this.sepaError = error.response?.data?.message || 'Das SEPA-Lastschriftmandat konnte nicht gespeichert werden.'
            } finally {
                this.sepaLoading = false
            }
        },

        async confirmRestaurantSepaCode() {
            if (this.sepaCode.trim().length !== 6 || !this.sepaFlow?.flow_uuid) {
                return
            }

            this.sepaLoading = true
            this.sepaError = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/sepa/confirm_code', {
                    data: {
                        flow_uuid: this.sepaFlow.flow_uuid,
                        code: this.sepaCode.trim(),
                    },
                })

                this.sepaFlow = response.data?.flow || this.sepaFlow
                this.sepaSuccess = response.data?.message || 'Das SEPA-Lastschriftmandat wurde bestätigt.'
                this.syncSepaFormFromFlow()
            } catch (error) {
                this.sepaError = error.response?.data?.message || 'Der Code ist falsch oder abgelaufen.'
            } finally {
                this.sepaLoading = false
            }
        },

        async resendRestaurantSepaCode() {
            if (!this.sepaFlow?.flow_uuid || this.sepaLoading) {
                return
            }

            this.sepaLoading = true
            this.sepaError = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/sepa/resend_code', {
                    data: {
                        flow_uuid: this.sepaFlow.flow_uuid,
                    },
                })

                this.sepaFlow = response.data?.flow || this.sepaFlow
                this.sepaSuccess = response.data?.message || 'Ein neuer Bestätigungscode wurde gesendet.'
                this.sepaCode = ''
            } catch (error) {
                this.sepaError = error.response?.data?.message || 'Der Bestätigungscode konnte nicht erneut gesendet werden.'
            } finally {
                this.sepaLoading = false
            }
        },

        async completeRestaurantSepaFlow() {
            if (!this.sepaFlow?.flow_uuid || !this.sepaApprovalAccepted) {
                return
            }

            this.sepaLoading = true
            this.sepaError = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/sepa/complete', {
                    data: {
                        flow_uuid: this.sepaFlow.flow_uuid,
                    },
                })

                this.sepaSuccess = response.data?.message || 'Das SEPA-Lastschriftmandat wurde gespeichert.'
                this.showSepaDialog = false
                this.sepaFlow = null

                await this.homepageStore.loadConfig(this.currentSchoolShortName, 'restaurant')
                await this.loadMenuPlans()

                if (response.data?.logged_in === true && this.currentSchoolShortName) {
                    window.location.href = `/homepage/restaurant?school=${this.currentSchoolShortName}`

                    return
                }

                if (this.sepaContextAction === 'register') {
                    this.registerResult = {
                        status: 'REGISTERED',
                        message: response.data?.message || 'Das SEPA-Lastschriftmandat wurde gespeichert.',
                    }
                }
            } catch (error) {
                this.sepaError = error.response?.data?.message || 'Das SEPA-Lastschriftmandat konnte nicht abgeschlossen werden.'
            } finally {
                this.sepaLoading = false
            }
        },

        async switchToRegisterDialog() {
            this.showLoginDialog = false
            this.resetRegisterDialogState()
            this.showRegisterDialog = true

            if (this.registerSource === 'new_user') {
                await this.$nextTick()
                await this.submitRestaurantRegistration()
            }
        },

        async onSchoolSelected(shortName) {
            if (shortName) {
                this.$router.replace({ query: { ...this.$route.query, school: shortName } })
                await this.homepageStore.loadConfig(shortName, 'restaurant')
                this.selectedSchoolShortName = this.config?.school?.short_name || null
                this.resetLoginDialogState()
                this.resetRegisterDialogState()
                this.resetRestaurantPasswordDialogState()
                this.showRegisterDialog = false
                this.showPasswordDialog = false
                this.sepaFlow = this.config?.restaurant?.sepa_flow || null
                if (this.restaurantRequiresSepa && this.sepaFlow) {
                    this.openSepaDialog(this.sepaFlow, 'login')
                }
                await this.loadMenuPlans()
            }
        },

        async logoutRestaurantUser() {
            await this.homepageStore.logout()
            await this.homepageStore.loadConfig(this.currentSchoolShortName, 'restaurant')
            this.userBookings = []
            this.resetLoginDialogState()
            this.resetRegisterDialogState()
            this.resetRestaurantPasswordDialogState()
            this.showLoginDialog = false
            this.showRegisterDialog = false
            this.showPasswordDialog = false
            this.showSepaDialog = false
            this.sepaFlow = null
        },

        async submitRestaurantPasswordChange() {
            if (!this.canSubmitRestaurantPasswordChange) {
                return
            }

            const form = this.$refs.restaurantPasswordForm
            const validationResult = await form?.validate?.()
            const isValid = validationResult?.valid ?? false

            if (!isValid || !this.currentSchoolId) {
                return
            }

            this.restaurantPasswordLoading = true
            this.restaurantPasswordError = ''
            this.restaurantPasswordSuccess = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/change_password', {
                    data: {
                        school_id: this.currentSchoolId,
                        new_password: this.restaurantPassword,
                        confirm_password: this.restaurantPasswordConfirmation,
                    },
                })

                this.restaurantPasswordSuccess = response.data?.message || 'Passwort erfolgreich geändert.'
                this.restaurantPassword = ''
                this.restaurantPasswordConfirmation = ''
                form?.reset?.()
            } catch (error) {
                this.restaurantPasswordError = error.response?.data?.message || 'Das Passwort konnte nicht geändert werden.'
            } finally {
                this.restaurantPasswordLoading = false
            }
        },

        async loadMenuPlans() {
            if (! this.currentSchoolShortName) {
                this.menuPlans = []
                this.userBookings = []
                return
            }

            try {
                const response = await axios.get('/api/homepage/restaurant/menu-plans', {
                    params: { school: this.currentSchoolShortName },
                })
                this.menuPlans = response.data?.plans || []
            } catch {
                this.menuPlans = []
            }

            await this.loadUserBookings()
        },

        async loadUserBookings() {
            if (! this.restaurantAuthUser) {
                this.userBookings = []
                return
            }

            try {
                const response = await axios.get('/api/homepage/restaurant/bookings')
                this.userBookings = response.data?.bookings || []
            } catch {
                this.userBookings = []
            }
        },

        downloadRestaurantOverviewPdf() {
            if (!this.restaurantAuthUser || !this.currentSchoolShortName) {
                return
            }

            const url = `/api/homepage/restaurant/print?school=${encodeURIComponent(this.currentSchoolShortName)}`
            window.open(url, '_blank', 'noopener')
        },

        downloadMenuPlanPdf(planId) {
            window.open(`/api/homepage/restaurant/menu-plans/${planId}/print`, '_blank', 'noopener')
        },

        groupEntriesByDate(entries) {
            if (! entries?.length) return []

            const grouped = {}
            for (const entry of entries) {
                const date = entry.plan_date
                if (! grouped[date]) {
                    grouped[date] = { date, entries: [] }
                }
                grouped[date].entries.push(entry)
            }

            return Object.values(grouped).sort((a, b) => a.date.localeCompare(b.date))
        },

        entryFoods(entry) {
            return [...(entry?.foods || entry?.menu?.foods || [])]
                .sort((left, right) => Number(left.course_number || 99) - Number(right.course_number || 99))
        },

        weekdayLabel(isoDate) {
            const parsedDate = this.parseRestaurantDate(isoDate)

            if (!parsedDate) {
                return ''
            }

            const days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag']
            return days[parsedDate.getDay()]
        },

        parseRestaurantDate(value) {
            const normalizedValue = (value || '').toString().trim()

            if (!normalizedValue) {
                return null
            }

            const parsedDate = /^\d{4}-\d{2}-\d{2}$/.test(normalizedValue)
                ? new Date(`${normalizedValue}T00:00:00`)
                : new Date(normalizedValue)

            return Number.isNaN(parsedDate.getTime()) ? null : parsedDate
        },

        formatDate(isoDate) {
            const parsedDate = this.parseRestaurantDate(isoDate)

            if (!parsedDate) {
                return ''
            }

            return parsedDate.toLocaleDateString('de-AT', {
                day: '2-digit', month: '2-digit', year: 'numeric',
            })
        },

        formatDateWithWeekday(isoDate) {
            const parsedDate = this.parseRestaurantDate(isoDate)

            if (!parsedDate) {
                return ''
            }

            return parsedDate.toLocaleDateString('de-AT', {
                weekday: 'long',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
        },

        formatDateShort(isoDate) {
            const parsedDate = this.parseRestaurantDate(isoDate)

            if (!parsedDate) {
                return ''
            }

            return parsedDate.toLocaleDateString('de-AT', {
                day: '2-digit', month: '2-digit',
            })
        },

        formatPrice(value) {
            const num = parseFloat(value)
            return isNaN(num) ? value : `€ ${num.toFixed(2).replace('.', ',')}`
        },

        formatEatingTime(value) {
            const normalized = (value || '').toString().trim()
            if (! normalized) return ''

            const parts = normalized.split(':')
            if (parts.length < 2) return normalized

            return parts.slice(0, 2).join(':')
        },

        userBookingsForEntry(entryId) {
            return this.userBookings.filter((booking) => Number(booking.menu_plan_entry_id) === Number(entryId))
        },

        entryHasBookings(entryId) {
            return this.userBookingsForEntry(entryId).length > 0
        },

        bookedQuantityForEntry(entryId) {
            return this.userBookingsForEntry(entryId).reduce((sum, booking) => sum + Number(booking.quantity || 0), 0)
        },

        isBookingCancellationLoading(bookingId) {
            return this.bookingCancellationIds.includes(Number(bookingId))
        },

        bookingRecipientNames(booking) {
            const recipients = Array.isArray(booking?.recipients) ? booking.recipients : []
            const names = recipients
                .map((recipient) => (recipient?.name || '').trim())
                .filter(Boolean)

            if (names.length > 0) {
                return names.join(', ')
            }

            return (booking?.child_name || '').trim()
        },

        defaultBlankRecipient() {
            return {
                name: '',
                type: 'other_person',
                import116_id: null,
            }
        },

        normalizeBookingRecipient(recipient = null) {
            return {
                name: (recipient?.name || '').toString().trim(),
                type: ['self', 'child', 'other_person'].includes(recipient?.type) ? recipient.type : 'other_person',
                import116_id: Number(recipient?.import116_id || 0) || null,
            }
        },

        defaultSelfRecipientName() {
            return (this.bookingFormContext?.self_name || this.restaurantAuthDisplayName || this.restaurantAuthEmail || '').trim()
        },

        isSelectableChildRecipient(recipient) {
            const import116Id = Number(recipient?.import116_id || 0)

            if (!import116Id) {
                return false
            }

            return this.childOptions.some((child) => Number(child?.id || 0) === import116Id)
        },

        buildBaseRecipients(quantity) {
            const normalizedQuantity = Math.max(1, Number(quantity || 1))

            if (this.bookingIsImport116Parent) {
                return Array.from({ length: normalizedQuantity }, (_, index) => {
                    const child = this.childOptions[index]

                    if (!child?.name) {
                        return this.defaultBlankRecipient()
                    }

                    return {
                        name: child.name,
                        type: 'child',
                        import116_id: Number(child.id || 0) || null,
                    }
                })
            }

            return Array.from({ length: normalizedQuantity }, (_, index) => {
                if (index === 0) {
                    return {
                        name: this.defaultSelfRecipientName(),
                        type: 'self',
                        import116_id: null,
                    }
                }

                return this.defaultBlankRecipient()
            })
        },

        isCustomizedRecipient(recipient, baseRecipient) {
            const normalizedRecipient = this.normalizeBookingRecipient(recipient)
            const normalizedBaseRecipient = this.normalizeBookingRecipient(baseRecipient)

            return normalizedRecipient.name !== normalizedBaseRecipient.name
                || normalizedRecipient.type !== normalizedBaseRecipient.type
                || normalizedRecipient.import116_id !== normalizedBaseRecipient.import116_id
        },

        syncBookingRecipients(quantity, { preserveCurrent = true } = {}) {
            const normalizedQuantity = Math.max(1, Number(quantity || 1))
            const baseRecipients = this.buildBaseRecipients(normalizedQuantity)
            const rememberedRecipients = Array.isArray(this.bookingFormContext?.booking_defaults?.recipients)
                ? this.bookingFormContext.booking_defaults.recipients.map((recipient) => this.normalizeBookingRecipient(recipient))
                : []
            const currentRecipients = preserveCurrent && Array.isArray(this.bookingData.recipients)
                ? this.bookingData.recipients.map((recipient) => this.normalizeBookingRecipient(recipient))
                : []

            const recipients = Array.from({ length: normalizedQuantity }, (_, index) => {
                const baseRecipient = this.normalizeBookingRecipient(baseRecipients[index] || this.defaultBlankRecipient())
                const currentRecipient = currentRecipients[index]
                const rememberedRecipient = rememberedRecipients[index]

                if (this.bookingIsImport116Parent && normalizedQuantity > 1) {
                    if (this.isSelectableChildRecipient(currentRecipient) || this.isSelectableChildRecipient(rememberedRecipient)) {
                        return baseRecipient
                    }
                }

                if (currentRecipient?.name) {
                    return currentRecipient
                }

                if (rememberedRecipient?.name) {
                    return rememberedRecipient
                }

                return baseRecipient
            })

            if (normalizedQuantity === 1 && !this.bookingRecipientOverride) {
                const currentSingleRecipient = currentRecipients[0]
                const rememberedSingleRecipient = rememberedRecipients[0]

                if (this.bookingIsImport116Parent && this.isSelectableChildRecipient(currentSingleRecipient)) {
                    recipients[0] = this.normalizeBookingRecipient(currentSingleRecipient)
                } else if (this.bookingIsImport116Parent && this.isSelectableChildRecipient(rememberedSingleRecipient)) {
                    recipients[0] = this.normalizeBookingRecipient(rememberedSingleRecipient)
                } else {
                    recipients[0] = this.normalizeBookingRecipient(baseRecipients[0] || this.defaultBlankRecipient())
                }
            }

            this.bookingData = {
                ...this.bookingData,
                quantity: normalizedQuantity,
                recipients,
            }
        },

        applyBookingFormContext(payload = {}) {
            const options = Array.isArray(payload?.options) ? payload.options : []
            const rememberedRecipients = Array.isArray(payload?.booking_defaults?.recipients)
                ? payload.booking_defaults.recipients.map((recipient) => this.normalizeBookingRecipient(recipient))
                : []

            this.childOptions = options
            this.bookingFormContext = {
                is_import116_parent: payload?.is_import116_parent === true,
                self_name: (payload?.self_name || this.restaurantAuthDisplayName || this.restaurantAuthEmail || '').trim(),
                booking_defaults: {
                    recipients: rememberedRecipients,
                    single_recipient_customized: payload?.booking_defaults?.single_recipient_customized === true,
                },
            }

            const baseSingleRecipient = this.buildBaseRecipients(1)[0] || this.defaultBlankRecipient()
            const firstRememberedRecipient = rememberedRecipients[0] || null

            this.bookingRecipientOverride = this.bookingData.quantity === 1 && (
                this.bookingFormContext.booking_defaults.single_recipient_customized
                || !baseSingleRecipient.name
                || (firstRememberedRecipient
                    ? !this.isSelectableChildRecipient(firstRememberedRecipient)
                        && this.isCustomizedRecipient(firstRememberedRecipient, baseSingleRecipient)
                    : false)
            )

            this.syncBookingRecipients(this.bookingData.quantity, { preserveCurrent: false })
        },

        async loadBookingFormContext() {
            this.bookingChildOptionsLoading = true

            try {
                const response = await axios.get('/api/homepage/restaurant/child-options')
                this.applyBookingFormContext(response.data || {})
            } catch (error) {
                this.childOptions = []
                this.bookingFormContext = {
                    is_import116_parent: false,
                    self_name: this.defaultSelfRecipientName(),
                    booking_defaults: {
                        recipients: [],
                        single_recipient_customized: false,
                    },
                }
                this.bookingRecipientOverride = !this.defaultSelfRecipientName()
                this.syncBookingRecipients(this.bookingData.quantity, { preserveCurrent: false })
                this.bookingError = error.response?.data?.message || 'Die Buchungsvorgaben konnten nicht geladen werden.'
            } finally {
                this.bookingChildOptionsLoading = false
            }
        },

        setBookingQuantity(quantity) {
            const normalizedQuantity = Math.max(1, Number(quantity || 1))

            if (normalizedQuantity === 1) {
                const firstRecipient = this.bookingRecipients[0] || this.bookingFormContext?.booking_defaults?.recipients?.[0] || null
                this.bookingRecipientOverride = !this.bookingSingleRecipientBase?.name
                    || (firstRecipient
                        ? !this.isSelectableChildRecipient(firstRecipient)
                            && this.isCustomizedRecipient(firstRecipient, this.bookingSingleRecipientBase)
                        : false)
                    || this.bookingFormContext?.booking_defaults?.single_recipient_customized === true
            } else {
                this.bookingRecipientOverride = false
            }

            this.syncBookingRecipients(normalizedQuantity)
        },

        setSingleRecipientOverride(enabled) {
            this.bookingRecipientOverride = enabled === true

            if (!this.bookingRecipientOverride) {
                this.syncBookingRecipients(1, { preserveCurrent: false })
                return
            }

            if (this.bookingIsImport116Parent) {
                this.bookingData = {
                    ...this.bookingData,
                    recipients: [{
                        name: '',
                        type: 'other_person',
                        import116_id: null,
                    }],
                }

                return
            }

            if (!this.bookingRecipients[0]) {
                this.syncBookingRecipients(1)
            }
        },

        isSingleChildSelected(child) {
            return Number(this.bookingSingleRecipient?.import116_id || 0) === Number(child?.id || 0)
        },

        selectSingleChildRecipient(child) {
            if (!child?.name) {
                return
            }

            this.bookingRecipientOverride = false
            this.bookingData = {
                ...this.bookingData,
                recipients: [{
                    name: child.name,
                    type: 'child',
                    import116_id: Number(child.id || 0) || null,
                }],
            }
        },

        onRecipientNameInput(index) {
            const recipient = this.bookingRecipients[index]

            if (!recipient) {
                return
            }

            const trimmedName = (recipient.name || '').trim()
            const linkedChild = this.childOptions.find((child) => Number(child?.id || 0) === Number(recipient.import116_id || 0))

            if (linkedChild && trimmedName !== linkedChild.name) {
                this.bookingData.recipients[index] = {
                    ...recipient,
                    type: 'other_person',
                    import116_id: null,
                }

                return
            }

            if (!this.bookingIsImport116Parent && this.bookingRecipientOverride && index === 0) {
                const selfName = this.defaultSelfRecipientName()

                this.bookingData.recipients[index] = {
                    ...recipient,
                    type: trimmedName !== '' && trimmedName !== selfName ? 'other_person' : 'self',
                    import116_id: null,
                }
            }
        },

        recipientFieldLabel(index) {
            if (this.bookingIsImport116Parent && this.childOptions[index]?.name) {
                return `Menü ${index + 1} (${this.childOptions[index].name})`
            }

            if (!this.bookingIsImport116Parent && index === 0) {
                return `Menü ${index + 1} (${this.defaultSelfRecipientName() || 'Sie'})`
            }

            return `Menü ${index + 1}`
        },

        async submitLoginEmailCheck() {
            const form = this.$refs.loginEmailForm
            const validationResult = await form?.validate?.()
            const isValid = validationResult?.valid ?? false

            if (!isValid || ! this.canSubmitLoginEmail || this.loginCheckLoading) {
                return
            }

            this.loginCheckLoading = true
            this.loginCheckError = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/check_email', {
                    data: {
                        school_id: this.currentSchoolId,
                        email: this.loginEmail.trim(),
                    },
                })

                this.loginCheckResult = response.data || null
                this.loginSelectedUserId = this.loginCheckResult?.matched_users?.[0]?.id || null
                this.loginMode = null
                this.loginCodeToken = ''
                this.loginPassword = ''
                this.loginLoading = false
                this.loginAuthError = ''
                this.loginActionMessage = ''
            } catch (error) {
                this.loginCheckResult = null
                this.loginCheckError = error.response?.data?.message || 'Die E-Mail-Adresse konnte nicht geprüft werden.'
            } finally {
                this.loginCheckLoading = false
            }
        },

        selectLoginUser(userId) {
            this.loginSelectedUserId = userId
            this.loginCodeToken = ''
            this.loginPassword = ''
            this.loginAuthError = ''
            this.loginActionMessage = ''
        },

        async startCodeLogin() {
            if (!this.selectedLoginUser) {
                return
            }

            this.loginMode = 'code'
            this.loginPassword = ''
            this.loginAuthError = ''
            await this.sendRestaurantLoginCode()
        },

        startPasswordLogin() {
            if (!this.selectedLoginUser) {
                return
            }

            this.loginMode = 'password'
            this.loginCodeToken = ''
            this.loginAuthError = ''
            this.loginActionMessage = ''
        },

        async sendRestaurantLoginCode() {
            if (!this.selectedLoginUser || this.loginLoading) {
                return
            }

            this.loginLoading = true
            this.loginAuthError = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/send_login_code', {
                    data: {
                        school_id: this.currentSchoolId,
                        email: this.loginCheckResult?.email || this.loginEmail.trim(),
                        user_id: this.selectedLoginUser.id,
                    },
                })

                this.loginActionMessage = response.data?.message || 'Der Login-Code wurde gesendet.'
                this.loginCodeToken = ''
            } catch (error) {
                this.loginAuthError = error.response?.data?.message || 'Der Login-Code konnte nicht gesendet werden.'
            } finally {
                this.loginLoading = false
            }
        },

        async submitRestaurantCodeLogin() {
            if (!this.canSubmitLoginCode) {
                return
            }

            this.loginLoading = true
            this.loginAuthError = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/login_with_code', {
                    data: {
                        school_id: this.currentSchoolId,
                        email: this.loginCheckResult?.email || this.loginEmail.trim(),
                        user_id: this.selectedLoginUser.id,
                        token_2fa: this.loginCodeToken.trim(),
                    },
                })

                if (response.data?.status === 'LOGGED_IN') {
                    window.location.href = `/homepage/restaurant?school=${this.currentSchoolShortName}`

                    return
                }

                if (response.data?.status === 'SEPA_REQUIRED') {
                    this.loginActionMessage = ''
                    this.openSepaDialog(response.data?.sepa_flow || null, 'login')
                    return
                }

                this.loginActionMessage = ''
                this.loginAuthError = response.data?.message || 'Der Code ist falsch oder abgelaufen.'
            } catch (error) {
                this.loginAuthError = error.response?.data?.message || 'Die Anmeldung mit Code ist fehlgeschlagen.'
            } finally {
                this.loginLoading = false
            }
        },

        async submitRestaurantPasswordLogin() {
            if (!this.canSubmitLoginPassword) {
                return
            }

            const form = this.$refs.loginPasswordForm
            const validationResult = await form?.validate?.()
            const isValid = validationResult?.valid ?? false

            if (!isValid) {
                return
            }

            this.loginLoading = true
            this.loginAuthError = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/login_with_password', {
                    data: {
                        school_id: this.currentSchoolId,
                        email: this.loginCheckResult?.email || this.loginEmail.trim(),
                        user_id: this.selectedLoginUser.id,
                        password: this.loginPassword,
                    },
                })

                if (response.data?.status === 'LOGGED_IN') {
                    window.location.href = `/homepage/restaurant?school=${this.currentSchoolShortName}`

                    return
                }

                if (response.data?.status === 'SEPA_REQUIRED') {
                    this.openSepaDialog(response.data?.sepa_flow || null, 'login')
                    return
                }

                this.loginAuthError = response.data?.message || 'Das Passwort ist falsch.'
            } catch (error) {
                this.loginAuthError = error.response?.data?.message || 'Die Anmeldung mit Passwort ist fehlgeschlagen.'
            } finally {
                this.loginLoading = false
            }
        },

        async handleLoginPrimaryAction() {
            if (this.loginCheckResult?.status === 'REGISTER_REQUIRED') {
                await this.switchToRegisterDialog()
                return
            }

            if (this.loginCheckResult?.status === 'PENDING_CONFIRMATION') {
                this.closeLoginDialog()
                return
            }

            await this.submitLoginEmailCheck()
        },

        async handleRegisterPrimaryAction() {
            if (this.registerIsWaitingForEmailConfirmation) {
                await this.confirmRestaurantRegistrationEmail()
                return
            }

            if (this.registerShouldOfferPasswordChoice && !this.registerPasswordChoicePrompted) {
                const form = this.$refs.registerForm
                const validationResult = await form?.validate?.()
                const isValid = validationResult?.valid ?? false

                if (!isValid || this.registerFirstName.trim() === '' || this.registerLastName.trim() === '') {
                    return
                }

                this.registerPasswordChoicePrompted = true
                return
            }

            await this.submitRestaurantRegistration()
        },

        async submitRestaurantRegistration() {
            const form = this.$refs.registerForm
            const validationResult = await form?.validate?.()
            const isValid = validationResult?.valid ?? !this.registerIsWaitingForEmailConfirmation

            if (!isValid || !this.canSubmitRegister) {
                return
            }

            this.registerLoading = true
            this.registerError = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/register', {
                    data: {
                        school_id: this.currentSchoolId,
                        email: this.registerEmail,
                        first_name: this.registerRequiresManualNameFields && this.registerHasConfirmedEmail ? this.registerFirstName.trim() : null,
                        last_name: this.registerRequiresManualNameFields && this.registerHasConfirmedEmail ? this.registerLastName.trim() : null,
                        confirmation_token: this.registerHasConfirmedEmail ? this.registerConfirmationToken : null,
                        password: this.registerShouldAskForPasswordInput ? this.registerPassword : null,
                    },
                })

                this.registerResult = response.data || null
                if (this.registerResult?.status === 'SEPA_REQUIRED') {
                    this.openSepaDialog(this.registerResult?.sepa_flow || null, 'register')
                }
                if (this.registerResult?.status === 'CONFIRM_EMAIL') {
                    this.registerEmailToken = ''
                    this.registerConfirmationToken = ''
                }
            } catch (error) {
                if (!this.registerIsWaitingForEmailConfirmation && !this.registerHasConfirmedEmail) {
                    this.registerResult = null
                }
                this.registerError = error.response?.data?.message || 'Die Registrierung konnte nicht gespeichert werden.'
            } finally {
                this.registerLoading = false
            }
        },

        async confirmRestaurantRegistrationEmail() {
            if (!this.registerIsWaitingForEmailConfirmation || this.registerEmailToken.trim().length !== 6) {
                return
            }

            this.registerLoading = true
            this.registerError = ''

            try {
                const response = await axios.post('/api/homepage/restaurant/confirm_email', {
                    data: {
                        school_id: this.currentSchoolId,
                        email: this.registerEmail,
                        token_2fa: this.registerEmailToken.trim(),
                    },
                })

                this.registerResult = response.data || null
                this.registerConfirmationToken = this.registerResult?.confirmation_token || ''
            } catch (error) {
                this.registerError = error.response?.data?.message || 'Die E-Mail-Adresse konnte nicht bestaetigt werden.'
            } finally {
                this.registerLoading = false
            }
        },

        async retryRestaurantRegistrationEmail() {
            this.registerResult = null
            this.registerEmailToken = ''
            this.registerConfirmationToken = ''
            this.registerPasswordChoicePrompted = false
            this.registerPasswordIntent = null
            this.registerPassword = ''

            await this.submitRestaurantRegistration()
        },

        setRegisterPasswordIntent(intent) {
            this.registerPasswordIntent = intent

            if (intent !== 'yes') {
                this.registerPassword = ''
            }
        },

        resetLoginDialogState() {
            this.loginEmail = ''
            this.loginCheckLoading = false
            this.loginCheckResult = null
            this.loginCheckError = ''
            this.loginSelectedUserId = null
            this.loginMode = null
            this.loginCodeToken = ''
            this.loginPassword = ''
            this.loginLoading = false
            this.loginAuthError = ''
            this.loginActionMessage = ''
        },

        resetRegisterDialogState() {
            this.registerFirstName = ''
            this.registerLastName = ''
            this.registerEmailToken = ''
            this.registerConfirmationToken = ''
            this.registerPasswordChoicePrompted = false
            this.registerPasswordIntent = null
            this.registerPassword = ''
            this.registerLoading = false
            this.registerResult = null
            this.registerError = ''
            this.sepaContextAction = null
            this.sepaCode = ''
        },

        resetRestaurantPasswordDialogState() {
            this.restaurantPassword = ''
            this.restaurantPasswordConfirmation = ''
            this.restaurantPasswordLoading = false
            this.restaurantPasswordError = ''
            this.restaurantPasswordSuccess = ''
        },

        countdownFor(plan) {
            // If plan is orderable, show countdown to order end
            if (plan.is_orderable && plan.orderable_until) {
                const diff = new Date(plan.orderable_until).getTime() - this.tickNow
                if (diff <= 0) return null

                const totalSeconds = Math.floor(diff / 1000)
                const days = Math.floor(totalSeconds / 86400)
                const hours = Math.floor((totalSeconds % 86400) / 3600)
                const minutes = Math.floor((totalSeconds % 3600) / 60)
                const seconds = totalSeconds % 60

                if (days > 0) return `${days} Tage ${hours} Stunden ${minutes} Minuten`
                if (hours > 0) return `${hours} Stunden ${minutes} Minuten ${seconds} Sekunden`
                return `${minutes} Minuten ${seconds} Sekunden`
            }
            
            // If plan is not orderable but has order_start_at in the future, show countdown to order start
            if (!plan.is_orderable && plan.order_start_at) {
                const diff = new Date(plan.order_start_at).getTime() - this.tickNow
                if (diff <= 0) return null

                const totalSeconds = Math.floor(diff / 1000)
                const days = Math.floor(totalSeconds / 86400)
                const hours = Math.floor((totalSeconds % 86400) / 3600)
                const minutes = Math.floor((totalSeconds % 3600) / 60)
                const seconds = totalSeconds % 60

                if (days > 0) return `${days} Tage ${hours} Stunden ${minutes} Minuten`
                if (hours > 0) return `${hours} Stunden ${minutes} Minuten ${seconds} Sekunden`
                return `${minutes} Minuten ${seconds} Sekunden`
            }
            
            return null
        },

        async bookMenu(entry) {
            if (this.restaurantRequiresSepa) {
                this.openSepaDialogFromConfig()
                return
            }

            this.selectedMenuEntry = entry
            this.bookingData = {
                restaurant_menu_plan_entry_id: entry.id,
                restaurant_eating_time_id: null,
                quantity: 1,
                price: entry.price,
                recipients: [],
                child_name: '',
                child_type: null,
                notes: '',
            }
            this.bookingError = ''
            this.bookingSuccess = ''
            this.bookingChildOptionsLoading = false
            this.bookingQuantityExpanded = false
            this.childOptions = []
            this.bookingRecipientOverride = false
            this.showBookingDialog = true

            await this.loadBookingFormContext()
        },
        
        async submitBooking() {
            if (!this.restaurantAuthUser || !this.selectedMenuEntry) {
                return
            }

            if (!this.canSubmitBooking) {
                return
            }
            
            this.bookingLoading = true
            this.bookingError = ''
            const payload = {
                ...this.bookingData,
                recipients: this.bookingRecipients.map((recipient) => ({
                    name: (recipient?.name || '').trim(),
                    type: recipient?.type || 'other_person',
                    import116_id: recipient?.import116_id || null,
                })),
                single_recipient_customized: this.bookingData.quantity === 1 ? this.bookingRecipientOverride : false,
            }
             
            try {
                const response = await axios.post('/api/homepage/restaurant/bookings', {
                    data: payload,
                })
                
                this.bookingSuccess = response.data.message || 'Menü erfolgreich gebucht.';
                this.bookingFormContext = {
                    ...this.bookingFormContext,
                    booking_defaults: {
                        recipients: payload.recipients,
                        single_recipient_customized: payload.single_recipient_customized,
                    },
                }
                this.showBookingDialog = false
                
                // Reload menu plans to show updated booking status
                await this.loadMenuPlans();
                
                // Show success message
                setTimeout(() => {
                    this.bookingSuccess = '';
                }, 3000);
                
            } catch (error) {
                this.bookingError = error.response?.data?.message || 'Die Buchung konnte nicht gespeichert werden.';
                if (error.response?.data?.errors) {
                    this.bookingError += ' ' + error.response.data.errors.join(' ');
                }
            } finally {
                this.bookingLoading = false;
            }
        },

        requestBookingCancellation(booking) {
            const bookingId = Number(booking?.id || 0)

            if (!bookingId || this.isBookingCancellationLoading(bookingId)) {
                return
            }

            this.pendingCancellationBooking = booking
            this.showCancelBookingDialog = true
        },

        closeCancelBookingDialog() {
            if (this.pendingCancellationLoading) {
                return
            }

            this.showCancelBookingDialog = false
            this.pendingCancellationBooking = null
        },

        async confirmBookingCancellation() {
            if (!this.pendingCancellationBooking) {
                return
            }

            await this.cancelBooking(this.pendingCancellationBooking)
        },

        async cancelBooking(booking) {
            const bookingId = Number(booking?.id || 0)

            if (!bookingId || this.isBookingCancellationLoading(bookingId)) {
                return
            }

            this.pendingCancellationLoading = true
            this.bookingCancellationIds = [...this.bookingCancellationIds, bookingId]
            this.bookingError = ''
            this.bookingSuccess = ''

            try {
                const response = await axios.delete(`/api/homepage/restaurant/bookings/${bookingId}`)

                this.bookingSuccess = response.data?.message || 'Buchung erfolgreich storniert.'
                await this.loadMenuPlans()
                this.showCancelBookingDialog = false
                this.pendingCancellationBooking = null

                setTimeout(() => {
                    this.bookingSuccess = ''
                }, 3000)
            } catch (error) {
                this.bookingError = error.response?.data?.message || 'Die Buchung konnte nicht storniert werden.'
            } finally {
                this.pendingCancellationLoading = false
                this.bookingCancellationIds = this.bookingCancellationIds.filter((id) => id !== bookingId)
            }
        },
        
        closeBookingDialog() {
            this.showBookingDialog = false
            this.selectedMenuEntry = null
            this.bookingData = {
                restaurant_menu_plan_entry_id: null,
                restaurant_eating_time_id: null,
                quantity: 1,
                price: null,
                recipients: [],
                child_name: '',
                child_type: null,
                notes: '',
            }
            this.bookingLoading = false
            this.bookingError = ''
            this.bookingSuccess = ''
            this.bookingChildOptionsLoading = false
            this.bookingQuantityExpanded = false
            this.childOptions = []
            this.bookingRecipientOverride = false
        },
    },
}
</script>

<style scoped>
.restaurant-page {
    min-height: 100vh;
    background:
        radial-gradient(ellipse at 30% 0%, rgba(234, 88, 12, 0.13), transparent 50%),
        radial-gradient(ellipse at 70% 100%, rgba(180, 83, 9, 0.08), transparent 50%),
        linear-gradient(180deg, #3a3532 0%, #4a4340 40%, #3a3532 100%);
    color: #f5f5f4;
}

.restaurant-shell {
    width: min(1120px, calc(100% - 32px));
    margin: 0 auto;
}

.restaurant-hero {
    padding: 32px 0 36px;
}

.restaurant-top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.restaurant-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #fb923c;
    font-weight: 700;
    text-decoration: none;
}

.restaurant-hero-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, 1fr);
    gap: 22px;
    padding: 28px;
    border-radius: 28px;
    background: rgba(255, 253, 250, 0.96);
    border: 1px solid rgba(214, 211, 209, 0.5);
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(10px);
}

.restaurant-eyebrow {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(234, 88, 12, 0.1);
    color: #c2410c;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.restaurant-title {
    margin: 18px 0 12px;
    font-size: clamp(2.4rem, 4vw, 4rem);
    line-height: 0.94;
    font-weight: 900;
    color: #292524;
}

.restaurant-lead {
    max-width: 62ch;
    font-size: 1.02rem;
    line-height: 1.7;
    color: #57534e;
}

.restaurant-lead--richtext :deep(p) {
    margin: 0 0 0.7em;
}

.restaurant-lead--richtext :deep(p:last-child) {
    margin-bottom: 0;
}

.restaurant-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

.restaurant-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 0 18px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 800;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.restaurant-action:hover {
    transform: translateY(-1px);
}

.restaurant-action--secondary {
    background: rgba(234, 88, 12, 0.08);
    color: #c2410c;
    border: 1px solid rgba(234, 88, 12, 0.25);
}

.restaurant-action-text {
    display: inline-flex;
    align-items: center;
    color: #fb923c;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    opacity: 0.7;
    transition: opacity 0.15s ease;
}

.restaurant-action-text:hover {
    opacity: 1;
    text-decoration: underline;
}

.restaurant-school-selector {
    margin-top: 4px;
}

.restaurant-school-selector__label {
    display: block;
    font-size: 0.85rem;
    font-weight: 700;
    color: #c2410c;
    margin-bottom: 6px;
}

.restaurant-school-selector__select {
    max-width: 380px;
}

.restaurant-no-school {
    padding: 0 0 24px;
}

.restaurant-no-school-card {
    text-align: center;
    padding: 48px 24px;
    border-radius: 22px;
    background: rgba(255, 253, 250, 0.96);
    border: 2px dashed rgba(234, 88, 12, 0.3);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.restaurant-no-school-card__icon {
    color: #ea580c;
    margin-bottom: 16px;
}

.restaurant-no-school-card__title {
    margin: 0 0 8px;
    font-size: 1.3rem;
    font-weight: 800;
    color: #292524;
}

.restaurant-no-school-card__text {
    margin: 0 auto 28px;
    font-size: 1rem;
    line-height: 1.6;
    color: #57534e;
    max-width: 48ch;
}

.restaurant-school-search {
    max-width: 400px;
    margin: 0 auto 20px;
}

.restaurant-school-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 10px;
    max-width: 700px;
    margin: 0 auto;
}

.restaurant-school-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-radius: 14px;
    background: rgba(245, 243, 240, 0.8);
    border: 1px solid rgba(214, 211, 209, 0.5);
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: left;
    font-family: inherit;
    font-size: 0.95rem;
    color: #44403c;
}

.restaurant-school-item:hover {
    background: rgba(255, 237, 213, 0.7);
    border-color: rgba(234, 88, 12, 0.3);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.restaurant-school-item__icon {
    color: #ea580c;
    flex-shrink: 0;
}

.restaurant-school-item__name {
    flex: 1;
    font-weight: 700;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.restaurant-school-item__arrow {
    color: #a8a29e;
    flex-shrink: 0;
    transition: color 0.15s ease;
}

.restaurant-school-item:hover .restaurant-school-item__arrow {
    color: #ea580c;
}

.restaurant-school-list__empty {
    grid-column: 1 / -1;
    text-align: center;
    color: #a8a29e;
    font-size: 0.9rem;
    padding: 12px 0;
    margin: 0;
}

.restaurant-auth {
    padding: 0 0 24px;
}

.restaurant-auth-stack {
    display: grid;
    gap: 14px;
}

.restaurant-auth-card {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 20px 26px;
    border-radius: 22px;
    background: rgba(255, 253, 250, 0.96);
    border: 1px solid rgba(214, 211, 209, 0.5);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.restaurant-auth-card__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: rgba(234, 88, 12, 0.1);
    color: #ea580c;
    flex-shrink: 0;
}

.restaurant-auth-card__copy {
    flex: 1;
    min-width: 0;
}

.restaurant-auth-card__title {
    margin: 0 0 4px;
    font-size: 1.1rem;
    font-weight: 800;
    color: #292524;
}

.restaurant-auth-card__text {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
    color: #57534e;
}

.restaurant-auth-card__meta {
    margin: 0 0 4px;
    font-size: 0.86rem;
    font-weight: 700;
    color: #c2410c;
    word-break: break-word;
}

.restaurant-auth-card__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

.restaurant-auth-bookings {
    display: grid;
    gap: 8px;
    padding: 10px 12px;
    border-radius: 14px;
    background: rgba(255, 253, 250, 0.96);
    border: 1px solid rgba(214, 211, 209, 0.5);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}

.restaurant-auth-bookings__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}

.restaurant-auth-bookings__eyebrow {
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: #c2410c;
}

.restaurant-auth-bookings__title {
    margin: 2px 0 0;
    font-size: 0.86rem;
    font-weight: 800;
    color: #292524;
}

.restaurant-auth-bookings__count {
    flex-shrink: 0;
    padding: 4px 8px;
    border-radius: 999px;
    background: rgba(234, 88, 12, 0.1);
    color: #c2410c;
    font-size: 0.7rem;
    font-weight: 800;
}

.restaurant-auth-bookings__list {
    display: grid;
    gap: 6px;
}

.restaurant-auth-bookings__item {
    display: grid;
    gap: 6px;
    padding: 8px 10px;
    border-radius: 12px;
    background: #f5f3f0;
    border: 1px solid rgba(214, 211, 209, 0.6);
}

.restaurant-auth-bookings__item--today {
    background: linear-gradient(135deg, rgba(255, 237, 213, 0.95), rgba(255, 247, 237, 0.92));
    border-color: rgba(234, 88, 12, 0.38);
    box-shadow: inset 0 0 0 1px rgba(249, 115, 22, 0.12);
}

.restaurant-auth-bookings__entries {
    display: grid;
    gap: 6px;
}

.restaurant-auth-bookings__menu-group {
    display: grid;
    gap: 4px;
}

.restaurant-auth-bookings__menu-items {
    display: grid;
    gap: 5px;
}

.restaurant-auth-bookings__entry {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 6px 8px;
    border-radius: 10px;
    background: rgba(245, 243, 240, 0.6);
}

.restaurant-auth-bookings__copy {
    min-width: 0;
    flex: 1;
}

.restaurant-auth-bookings__item-title {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 800;
    color: #292524;
}

.restaurant-auth-bookings__today-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(234, 88, 12, 0.12);
    color: #c2410c;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.restaurant-auth-bookings__entry-title {
    font-size: 0.78rem;
    font-weight: 800;
    color: #292524;
}

.restaurant-auth-bookings__item-meta {
    margin-top: 1px;
    font-size: 0.75rem;
    color: #78716c;
    line-height: 1.4;
}

.restaurant-auth-bookings__item-meta--inline {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 0;
}

.restaurant-auth-bookings__item-meta-part {
    color: #292524;
    font-weight: 800;
    white-space: nowrap;
}

.restaurant-auth-bookings__locked {
    flex-shrink: 0;
    font-size: 0.72rem;
    font-weight: 700;
    color: #78716c;
}

.restaurant-login-intro {
    margin: 0 0 12px;
    color: #a8a29e;
    line-height: 1.55;
}

.restaurant-login-school {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    padding: 8px 12px;
    border-radius: 999px;
    background: rgba(251, 146, 60, 0.12);
    color: #fb923c;
    font-size: 0.85rem;
    font-weight: 700;
}

.restaurant-login-school__icon {
    color: #fdba74;
}

.restaurant-login-email {
    padding: 12px 14px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(251, 146, 60, 0.16), rgba(255, 247, 237, 0.95));
    border: 1px solid rgba(251, 146, 60, 0.28);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

.restaurant-login-email__label {
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #fb923c;
    margin-bottom: 4px;
}

.restaurant-login-email__value {
    font-size: 1rem;
    font-weight: 800;
    color: #fafaf9;
    word-break: break-word;
}

.restaurant-login-state {
    display: grid;
    gap: 12px;
}

.restaurant-login-state__text {
    margin: 0;
    color: #a8a29e;
    line-height: 1.55;
}

.restaurant-login-state__matches {
    display: grid;
    gap: 8px;
}

.restaurant-login-state__match {
    display: grid;
    gap: 2px;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(82, 76, 72, 0.5);
    border: 1px solid rgba(251, 146, 60, 0.16);
    color: #a8a29e;
    font-size: 0.85rem;
    cursor: pointer;
}

.restaurant-login-state__match strong {
    color: #e7e5e4;
}

.restaurant-login-state__match--selected {
    border-color: rgba(234, 88, 12, 0.45);
    background: rgba(82, 76, 72, 0.6);
}

.restaurant-login-state__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.restaurant-status-card {
    align-self: stretch;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
    padding: 22px;
    border-radius: 22px;
    background: linear-gradient(160deg, #7c2d12 0%, #c2410c 54%, #f97316 100%);
    color: #fff7ed;
}

.restaurant-status-card__label {
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.74;
}

.restaurant-status-card__intro {
    font-size: 0.92rem;
    line-height: 1.7;
    opacity: 0.94;
    margin-top: 8px;
}

.restaurant-status-card__intro :deep(p) {
    margin: 0 0 0.5em;
}

.restaurant-status-card__intro :deep(p:last-child) {
    margin-bottom: 0;
}

.restaurant-status-card__intro--empty {
    opacity: 0.5;
    font-style: italic;
}

/* ---- Menu Plans Section ---- */

.restaurant-plans,
.restaurant-content {
    padding: 0 0 56px;
}

.rp-section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.rp-section-header__icon {
    color: #fb923c;
}

.rp-section-header__title {
    font-size: 1.3rem;
    font-weight: 800;
    color: #fafaf9;
    margin: 0;
}

.rp-plan {
    border-radius: 22px;
    background: rgba(255, 253, 250, 0.96);
    border: 1px solid rgba(214, 211, 209, 0.5);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.rp-plan + .rp-plan {
    margin-top: 20px;
}

.rp-plan--orderable {
    border-color: rgba(34, 197, 94, 0.35);
    box-shadow: 0 16px 40px rgba(34, 197, 94, 0.08);
}

.rp-plan__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 18px 22px;
    background: linear-gradient(135deg, #7c2d12 0%, #c2410c 100%);
    color: #fff;
}

.rp-plan--orderable .rp-plan__header {
    background: linear-gradient(135deg, #14532d 0%, #15803d 60%, #22c55e 100%);
}

.rp-plan__header-left {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.rp-plan__title {
    font-size: 1.12rem;
    font-weight: 800;
}

.rp-plan__range {
    font-size: 0.85rem;
    opacity: 0.85;
}

.rp-plan__badge-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.rp-plan__badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    font-size: 0.8rem;
    font-weight: 700;
    white-space: nowrap;
    backdrop-filter: blur(4px);
}

.rp-plan__timer {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.rp-days {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0;
}

.rp-day {
    border-bottom: 1px solid rgba(214, 211, 209, 0.5);
    border-right: 1px solid rgba(214, 211, 209, 0.5);
}

.rp-day__header {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 14px 18px 8px;
    background: rgba(245, 243, 240, 0.6);
    border-bottom: 1px solid rgba(214, 211, 209, 0.4);
}

.rp-day__weekday {
    font-size: 1.05rem;
    font-weight: 800;
    color: #c2410c;
}

.rp-day__date {
    font-size: 0.82rem;
    color: #78716c;
}

.rp-day__menus {
    padding: 10px 18px 14px;
    display: grid;
    gap: 10px;
}

.rp-menu {
    padding: 10px 14px;
    border-radius: 12px;
    background: #f5f3f0;
    border: 1px solid rgba(214, 211, 209, 0.5);
}

.rp-menu__top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
}

.rp-menu__title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #292524;
}

.rp-menu__price {
    font-size: 0.88rem;
    font-weight: 800;
    color: #c2410c;
    white-space: nowrap;
}

.rp-menu__foods {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 6px;
}

.rp-menu__food-block {
    display: flex;
    flex-direction: column;
}

.rp-menu__food {
    font-size: 0.82rem;
    font-weight: 700;
    color: #44403c;
    line-height: 1.5;
}

.rp-menu__food-description {
    margin-top: 1px;
    font-size: 0.78rem;
    color: #78716c;
    line-height: 1.45;
}

.rp-menu__icons-row {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 3px;
    margin-bottom: 2px;
}

.rp-menu__ingredient-icon {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    transition: transform 0.2s ease;
    cursor: pointer;
}

.rp-menu__ingredient-icon:hover {
    transform: scale(3);
    z-index: 10;
    position: relative;
}

.rp-menu__allergens {
    font-size: 0.75rem;
    color: #a8a29e;
}

.rp-menu__times {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 6px;
    font-size: 0.78rem;
    color: #78716c;
}

.rp-menu__times-icon {
    color: #a8a29e;
}

.rp-menu__comments {
    margin-top: 6px;
    font-size: 0.8rem;
    color: #78716c;
    font-style: italic;
    line-height: 1.5;
}

.rp-menu__booking-state {
    margin-top: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    background: rgba(34, 197, 94, 0.14);
    border: 1px solid rgba(34, 197, 94, 0.35);
}

.rp-menu__booking-summary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    font-weight: 800;
    color: #15803d;
}

.rp-menu__booking-summary-icon {
    color: #16a34a;
}

.rp-menu__booking-list {
    display: grid;
    gap: 8px;
    margin-top: 8px;
}

.rp-menu__booking-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding-top: 8px;
    border-top: 1px solid rgba(34, 197, 94, 0.14);
}

.rp-menu__booking-item:first-child {
    padding-top: 0;
    border-top: none;
}

.rp-menu__booking-copy {
    font-size: 0.78rem;
    color: #166534;
    line-height: 1.45;
}

.rp-menu__booking-locked {
    font-size: 0.74rem;
    font-weight: 700;
    color: #a8a29e;
}

.rp-menu__actions {
    margin-top: 10px;
    display: flex;
    justify-content: flex-end;
}

.booking-time-picker {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.booking-time-picker__label {
    font-size: 0.88rem;
    font-weight: 700;
    color: #7c2d12;
}

.booking-time-picker__options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.booking-time-picker__option {
    border: 1px solid rgba(251, 146, 60, 0.28);
    background: rgba(82, 76, 72, 0.55);
    color: #fb923c;
    border-radius: 999px;
    padding: 10px 16px;
    font-size: 0.92rem;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}

.booking-time-picker__option:hover {
    transform: translateY(-1px);
    border-color: rgba(234, 88, 12, 0.42);
    box-shadow: 0 8px 18px rgba(154, 52, 18, 0.12);
}

.booking-time-picker__option:focus-visible {
    outline: 2px solid rgba(234, 88, 12, 0.55);
    outline-offset: 2px;
}

.booking-time-picker__option--active {
    background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
    border-color: transparent;
    color: #fff7ed;
    box-shadow: 0 10px 20px rgba(194, 65, 12, 0.2);
}

.booking-quantity-picker {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.booking-quantity-picker__label {
    font-size: 0.88rem;
    font-weight: 700;
    color: #7c2d12;
}

.booking-quantity-picker__options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.booking-quantity-picker__option {
    border: 1px solid rgba(251, 146, 60, 0.28);
    background: rgba(82, 76, 72, 0.55);
    color: #fb923c;
    border-radius: 999px;
    padding: 10px 18px;
    font-size: 0.92rem;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}

.booking-quantity-picker__option:hover {
    transform: translateY(-1px);
    border-color: rgba(234, 88, 12, 0.42);
    box-shadow: 0 8px 18px rgba(154, 52, 18, 0.12);
}

.booking-quantity-picker__option:focus-visible {
    outline: 2px solid rgba(234, 88, 12, 0.55);
    outline-offset: 2px;
}

.booking-quantity-picker__option--active {
    background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
    border-color: transparent;
    color: #fff7ed;
    box-shadow: 0 10px 20px rgba(194, 65, 12, 0.2);
}

.booking-quantity-picker__more {
    border: 1px dashed rgba(251, 146, 60, 0.42);
    background: rgba(82, 76, 72, 0.42);
    color: #fb923c;
    border-radius: 999px;
    padding: 10px 18px;
    font-size: 0.92rem;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}

.booking-quantity-picker__more:hover {
    transform: translateY(-1px);
    border-color: rgba(234, 88, 12, 0.55);
    box-shadow: 0 8px 18px rgba(154, 52, 18, 0.12);
}

.booking-quantity-picker__more:focus-visible {
    outline: 2px solid rgba(234, 88, 12, 0.55);
    outline-offset: 2px;
}

.booking-recipient-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.booking-recipient-section__label {
    font-size: 0.88rem;
    font-weight: 700;
    color: #7c2d12;
}

.booking-recipient-summary {
    font-size: 0.88rem;
    color: #7c2d12;
}

.booking-recipient-toggle {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #fb923c;
    cursor: pointer;
}

.booking-recipient-toggle input {
    accent-color: #ea580c;
}

.booking-child-picker {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.booking-child-picker__label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #7c2d12;
}

.booking-child-picker__options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.booking-child-picker__option {
    border: 1px solid rgba(251, 146, 60, 0.28);
    background: rgba(82, 76, 72, 0.55);
    color: #fb923c;
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 0.84rem;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}

.booking-child-picker__option:hover {
    transform: translateY(-1px);
    border-color: rgba(234, 88, 12, 0.42);
    box-shadow: 0 8px 18px rgba(154, 52, 18, 0.12);
}

.booking-child-picker__option:focus-visible {
    outline: 2px solid rgba(234, 88, 12, 0.55);
    outline-offset: 2px;
}

.booking-child-picker__option--active {
    background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
    border-color: transparent;
    color: #fff7ed;
    box-shadow: 0 10px 20px rgba(194, 65, 12, 0.2);
}

.restaurant-sepa-panel {
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 18px;
    background: rgba(82, 76, 72, 0.48);
    padding: 1rem 1.1rem;
}

.restaurant-sepa-panel__title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #e7e5e4;
    margin-bottom: 0.85rem;
}

.restaurant-sepa-richtext {
    color: #d6d3d1;
    line-height: 1.7;
}

.restaurant-sepa-iban-field :deep(input) {
    font-size: 1.06rem;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    font-variant-numeric: tabular-nums;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
}

.restaurant-sepa-iban-field :deep(.v-field__input) {
    padding-top: 8px;
    padding-bottom: 8px;
}

.restaurant-sepa-child-row {
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    margin-bottom: 1rem;
    padding-bottom: 0.2rem;
}

.restaurant-sepa-child-row:last-of-type {
    border-bottom: 0;
    margin-bottom: 0.5rem;
}

.restaurant-sepa-preview__title {
    font-size: 1.35rem;
    font-weight: 900;
    color: #e7e5e4;
    margin-bottom: 1rem;
}

.restaurant-sepa-preview__line {
    color: #d6d3d1;
    line-height: 1.7;
}

.restaurant-sepa-signature-row {
    display: grid;
    gap: 0.9rem;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.restaurant-sepa-signature-box {
    border: 1px solid rgba(15, 23, 42, 0.12);
    border-radius: 16px;
    background: rgba(58, 53, 50, 0.8);
    padding: 0.9rem 1rem;
}

.restaurant-sepa-signature-box__label {
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 0.45rem;
}

.restaurant-sepa-signature-box__value {
    color: #e7e5e4;
    font-weight: 700;
    line-height: 1.5;
    word-break: break-word;
}

.restaurant-sepa-signature-box__meta {
    color: #0f766e;
    font-size: 0.82rem;
    font-weight: 700;
    margin-top: 0.35rem;
}

.booking-recipient-list {
    display: grid;
    gap: 12px;
}

.booking-recipient-field-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.booking-recipient-field__label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #7c2d12;
}

.booking-recipient-field {
    width: 100%;
    border: 1px solid rgba(251, 146, 60, 0.28);
    background: rgba(82, 76, 72, 0.55);
    color: #e7e5e4;
    border-radius: 14px;
    padding: 11px 14px;
    font-size: 0.95rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.booking-recipient-field:focus {
    outline: none;
    border-color: rgba(234, 88, 12, 0.55);
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.16);
    background: #4a4543;
}

.booking-recipient-field__hint {
    font-size: 0.75rem;
    color: #78716c;
}

.rp-empty {
    text-align: center;
    padding: 48px 24px;
    border-radius: 22px;
    background: rgba(255, 253, 250, 0.96);
    border: 1px solid rgba(214, 211, 209, 0.5);
}

.rp-empty__icon {
    color: #d4a373;
    opacity: 0.5;
    margin-bottom: 12px;
}

.rp-empty__text {
    margin: 0;
    font-size: 1rem;
    color: #78716c;
}

@media (max-width: 960px) {
    .restaurant-hero-card {
        grid-template-columns: 1fr;
    }

    .restaurant-auth-card {
        flex-direction: column;
        text-align: center;
    }

    .restaurant-auth-card__actions {
        justify-content: center;
    }

    .restaurant-auth-bookings__header,
    .restaurant-auth-bookings__entry {
        flex-direction: column;
        align-items: flex-start;
    }

    .rp-days {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    }
}

@media (max-width: 640px) {
    .restaurant-hero {
        padding-top: 20px;
    }

    .restaurant-shell {
        width: min(100% - 20px, 1120px);
    }

    .restaurant-hero-card {
        padding: 20px;
    }

    .restaurant-title {
        font-size: 2.4rem;
    }

    .restaurant-actions {
        flex-direction: column;
    }

    .rp-days {
        grid-template-columns: 1fr;
    }

    .rp-plan__header {
        padding: 14px 16px;
    }

    .rp-plan__badge-group {
        width: 100%;
        justify-content: flex-start;
    }

    .rp-plan__timer {
        flex-basis: 100%;
    }

    .rp-day__header {
        padding: 12px 14px 6px;
    }

    .rp-day__menus {
        padding: 8px 14px 12px;
    }

    .rp-menu {
        padding: 8px 10px;
    }
}
</style>

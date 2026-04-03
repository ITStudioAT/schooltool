<template>
    <div class="restaurant-page">
        <section class="restaurant-hero">
            <div class="restaurant-shell">
                <div class="restaurant-top-bar">
                    <router-link to="/" class="restaurant-back-link">
                        <v-icon size="18">mdi-arrow-left</v-icon>
                        <span>Zur Startseite</span>
                    </router-link>
                    <a href="/admin/restaurant/menu-plans" class="restaurant-action-text">Zur Verwaltung</a>
                </div>

                <div class="restaurant-hero-card">
                    <div class="restaurant-hero-copy">
                        <div class="restaurant-eyebrow">SchoolTool Restaurant</div>
                        <h1 class="restaurant-title">Restaurant</h1>
                        <div v-if="currentSchoolShortName" class="restaurant-school-selector">
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
                            <router-link to="/" class="restaurant-action restaurant-action--secondary">Zur Übersicht</router-link>
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
                <div v-if="restaurantAuthUser" class="restaurant-auth-card">
                    <div class="restaurant-auth-card__icon">
                        <v-icon size="28">mdi-account-check-outline</v-icon>
                    </div>
                    <div class="restaurant-auth-card__copy">
                        <h2 class="restaurant-auth-card__title">{{ restaurantAuthDisplayName }}</h2>
                        <p v-if="restaurantAuthEmail" class="restaurant-auth-card__meta">E-Mail: {{ restaurantAuthEmail }}</p>
                        <p class="restaurant-auth-card__text">Sie sind angemeldet und können jetzt Menüs bestellen.</p>
                    </div>
                    <div class="restaurant-auth-card__actions">
                        <v-btn color="#ea580c" variant="flat" rounded="lg" class="text-none font-weight-bold" @click="openRestaurantPasswordDialog">Passwort ändern</v-btn>
                        <v-btn color="#ea580c" variant="outlined" rounded="lg" class="text-none font-weight-bold" @click="logoutRestaurantUser">Abmelden</v-btn>
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

                <div v-for="plan in menuPlans" :key="plan.id" class="rp-plan" :class="{ 'rp-plan--orderable': plan.is_orderable }">
                    <div class="rp-plan__header">
                        <div class="rp-plan__header-left">
                            <div class="rp-plan__title">{{ plan.title || "Menüplan" }}</div>
                            <div class="rp-plan__range">{{ formatDate(plan.start_date) }} - {{ formatDate(plan.end_date) }}</div>
                        </div>
                        <div v-if="plan.is_orderable" class="rp-plan__badge-group">
                            <div class="rp-plan__badge">
                                <v-icon icon="mdi-cart-check" size="15" />
                                <span>Bestellbar</span>
                            </div>
                            <div v-if="countdownFor(plan)" class="rp-plan__timer">
                                <v-icon icon="mdi-timer-outline" size="13" />
                                <span>noch {{ countdownFor(plan) }}</span>
                            </div>
                        </div>
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

                                    <div v-if="entry.menu?.foods?.length" class="rp-menu__foods">
                                        <span v-for="food in entry.menu.foods" :key="food.id" class="rp-menu__food">
                                            {{ food.title }}
                                            <span v-if="food.allergens?.length" class="rp-menu__allergens">({{ food.allergens.join(', ') }})</span>
                                        </span>
                                    </div>

                                    <div v-if="entry.eating_times?.length" class="rp-menu__times">
                                        <v-icon icon="mdi-clock-outline" size="13" class="rp-menu__times-icon" />
                                        <span v-for="(et, i) in entry.eating_times" :key="et.id">
                                            {{ formatEatingTime(et.eating_time) }} Uhr<span v-if="i < entry.eating_times.length - 1">, </span>
                                        </span>
                                    </div>

                                    <div v-if="entry.comments" class="rp-menu__comments">{{ entry.comments }}</div>

                                    <div v-if="restaurantAuthUser && plan.is_orderable" class="rp-menu__actions">
                                        <v-btn
                                            color="#ea580c"
                                            variant="flat"
                                            size="small"
                                            class="text-none font-weight-bold"
                                            @click="bookMenu(entry)">
                                            Buchen
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

                    <div v-if="loginCheckResult?.email" class="restaurant-login-email mb-3">
                        <div class="restaurant-login-email__label">E-Mail</div>
                        <div class="restaurant-login-email__value">{{ loginCheckResult.email }}</div>
                    </div>

                    <v-alert
                        v-if="loginCheckError"
                        type="error"
                        variant="tonal"
                        density="comfortable"
                        class="mb-3">
                        {{ loginCheckError }}
                    </v-alert>

                    <div v-if="loginCheckResult?.status === 'USER_FOUND'" class="restaurant-login-state">
                        <p class="restaurant-login-state__text">
                            <template v-if="loginCheckResult.match_source === 'parent'">
                                Die E-Mail-Adresse wurde über einen Elternkontakt gefunden.
                            </template>
                            <template v-else>
                                Die E-Mail-Adresse gehört zu einem Mittagskonto.
                            </template>
                            Möchten Sie sich per Code oder per Passwort anmelden?
                        </p>

                        <div
                            v-if="loginCheckResult.matched_users?.length"
                            class="restaurant-login-state__matches">
                            <div
                                v-for="user in loginCheckResult.matched_users"
                                :key="user.id"
                                class="restaurant-login-state__match"
                                :class="{ 'restaurant-login-state__match--selected': loginSelectedUserId === user.id }"
                                @click="selectLoginUser(user.id)">
                                <strong>{{ user.name }}</strong>
                                <span v-if="user.schoolclass">{{ user.schoolclass }}</span>
                                <span v-if="user.matched_children?.length">
                                    {{ user.matched_children.join(', ') }}
                                </span>
                            </div>
                        </div>

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
                                    @click="bookingData.quantity = quantity">
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

                        <div v-if="restaurantAuthUser?.import116_parent">
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

        if (! this.config) {
            await this.homepageStore.loadConfig(schoolFromUrl, this.$route?.query?.app ?? null)
        }

        if (this.config?.school?.short_name) {
            this.selectedSchoolShortName = this.config.school.short_name
        }

        await this.homepageStore.loadSchoolsForTool('Restaurant')

        if (!this.currentSchoolShortName && this.selectableSchools.length === 1) {
            await this.onSchoolSelected(this.selectableSchools[0].short_name)
        }

        await this.loadMenuPlans()
    },

    data() {
        return {
            homepageStore: null,
            selectedSchoolShortName: null,
            menuPlans: [],
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
            child_name: '',
            child_type: null,
            notes: '',
        },
        bookingLoading: false,
        bookingError: '',
        bookingSuccess: '',
        bookingChildOptionsLoading: false,
        bookingQuantityExpanded: false,
        childOptions: [],
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
        canSubmitBooking() {
            if (this.bookingLoading || !this.selectedMenuEntry) {
                return false
            }

            if (this.bookingRequiresEatingTime && !this.bookingData.restaurant_eating_time_id) {
                return false
            }

            return true
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
                await this.loadMenuPlans()
            }
        },

        async logoutRestaurantUser() {
            await this.homepageStore.logout()
            await this.homepageStore.loadConfig(this.currentSchoolShortName, 'restaurant')
            this.resetLoginDialogState()
            this.resetRegisterDialogState()
            this.resetRestaurantPasswordDialogState()
            this.showLoginDialog = false
            this.showRegisterDialog = false
            this.showPasswordDialog = false
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

        weekdayLabel(isoDate) {
            const days = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa']
            return days[new Date(`${isoDate}T00:00:00`).getDay()]
        },

        formatDate(isoDate) {
            return new Date(`${isoDate}T00:00:00`).toLocaleDateString('de-AT', {
                day: '2-digit', month: '2-digit', year: 'numeric',
            })
        },

        formatDateWithWeekday(isoDate) {
            return new Date(`${isoDate}T00:00:00`).toLocaleDateString('de-AT', {
                weekday: 'long',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
        },

        formatDateShort(isoDate) {
            return new Date(`${isoDate}T00:00:00`).toLocaleDateString('de-AT', {
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
        },

        resetRestaurantPasswordDialogState() {
            this.restaurantPassword = ''
            this.restaurantPasswordConfirmation = ''
            this.restaurantPasswordLoading = false
            this.restaurantPasswordError = ''
            this.restaurantPasswordSuccess = ''
        },

        countdownFor(plan) {
            if (! plan.orderable_until) return null

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
        },

        async bookMenu(entry) {
            this.selectedMenuEntry = entry
            this.bookingData = {
                restaurant_menu_plan_entry_id: entry.id,
                restaurant_eating_time_id: null,
                quantity: 1,
                price: entry.price,
                child_name: '',
                child_type: null,
                notes: '',
            }
            this.bookingError = ''
            this.bookingSuccess = ''
            this.bookingChildOptionsLoading = false
            this.bookingQuantityExpanded = false
            this.childOptions = []
            this.showBookingDialog = true

            if (this.restaurantAuthUser?.import116_parent) {
                this.bookingChildOptionsLoading = true

                try {
                    const response = await axios.get('/api/homepage/restaurant/child-options')
                    this.childOptions = response.data.options || []
                } catch (error) {
                    this.childOptions = []
                    this.bookingError = error.response?.data?.message || 'Die Kinderauswahl konnte nicht geladen werden.'
                } finally {
                    this.bookingChildOptionsLoading = false
                }
            }
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
            
            try {
                const response = await axios.post('/api/homepage/restaurant/bookings', {
                    data: this.bookingData,
                })
                
                this.bookingSuccess = response.data.message || 'Menü erfolgreich gebucht.';
                this.showBookingDialog = false;
                
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
        
        closeBookingDialog() {
            this.showBookingDialog = false
            this.selectedMenuEntry = null
            this.bookingData = {
                restaurant_menu_plan_entry_id: null,
                restaurant_eating_time_id: null,
                quantity: 1,
                price: null,
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
        },
    },
}
</script>

<style scoped>
.restaurant-page {
    min-height: 100vh;
    background:
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='220' viewBox='0 0 220 220'%3E%3Cg fill='none' stroke='%23c2410c' stroke-width='0.8' stroke-linecap='round' stroke-linejoin='round' opacity='0.32'%3E%3C!-- fork --%3E%3Cg transform='translate(20,20)'%3E%3Cline x1='10' y1='0' x2='10' y2='28'/%3E%3Cline x1='6' y1='0' x2='6' y2='12'/%3E%3Cline x1='14' y1='0' x2='14' y2='12'/%3E%3Cline x1='6' y1='12' x2='14' y2='12'/%3E%3C/g%3E%3C!-- knife --%3E%3Cg transform='translate(80,30)'%3E%3Cline x1='8' y1='0' x2='8' y2='28'/%3E%3Cpath d='M8 0 Q14 4 14 14 L8 14'/%3E%3C/g%3E%3C!-- plate --%3E%3Cg transform='translate(150,18)'%3E%3Ccircle cx='14' cy='14' r='14'/%3E%3Ccircle cx='14' cy='14' r='9'/%3E%3C/g%3E%3C!-- chef hat --%3E%3Cg transform='translate(40,100)'%3E%3Cpath d='M4 22 L4 14 Q4 4 12 4 Q20 4 20 14 L20 22'/%3E%3Cline x1='4' y1='22' x2='20' y2='22'/%3E%3C/g%3E%3C!-- steam --%3E%3Cg transform='translate(115,95)'%3E%3Cpath d='M6 20 Q2 14 6 10 Q10 6 6 0'/%3E%3Cpath d='M14 20 Q10 14 14 10 Q18 6 14 0'/%3E%3C/g%3E%3C!-- spoon --%3E%3Cg transform='translate(175,100)'%3E%3Cellipse cx='8' cy='6' rx='6' ry='8'/%3E%3Cline x1='8' y1='14' x2='8' y2='30'/%3E%3C/g%3E%3C!-- glass --%3E%3Cg transform='translate(25,175)'%3E%3Cpath d='M4 0 L6 18 L14 18 L16 0 Z'/%3E%3Cline x1='10' y1='18' x2='10' y2='24'/%3E%3Cline x1='5' y1='24' x2='15' y2='24'/%3E%3C/g%3E%3C!-- cloche --%3E%3Cg transform='translate(90,170)'%3E%3Cpath d='M2 22 Q2 8 16 8 Q30 8 30 22'/%3E%3Cline x1='0' y1='22' x2='32' y2='22'/%3E%3Cline x1='16' y1='4' x2='16' y2='8'/%3E%3Ccircle cx='16' cy='3' r='2'/%3E%3C/g%3E%3C!-- rolling pin --%3E%3Cg transform='translate(165,175)'%3E%3Crect x='6' y='4' width='20' height='8' rx='4'/%3E%3Cline x1='2' y1='8' x2='6' y2='8'/%3E%3Cline x1='26' y1='8' x2='30' y2='8'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat,
        radial-gradient(circle at top left, rgba(255, 213, 128, 0.55), transparent 34%),
        radial-gradient(circle at top right, rgba(255, 247, 237, 0.7), transparent 28%),
        linear-gradient(180deg, #fff7ed 0%, #ffedd5 26%, #fff 100%);
    color: #1f2937;
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
    color: #9a3412;
    font-weight: 700;
    text-decoration: none;
}

.restaurant-hero-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, 1fr);
    gap: 22px;
    padding: 28px;
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(251, 146, 60, 0.24);
    box-shadow: 0 24px 70px rgba(194, 65, 12, 0.12);
    backdrop-filter: blur(10px);
}

.restaurant-eyebrow {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(251, 146, 60, 0.12);
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
    color: #111827;
}

.restaurant-lead {
    max-width: 62ch;
    font-size: 1.02rem;
    line-height: 1.7;
    color: #4b5563;
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
    background: rgba(255, 255, 255, 0.92);
    color: #9a3412;
    border: 1px solid rgba(251, 146, 60, 0.28);
}

.restaurant-action-text {
    display: inline-flex;
    align-items: center;
    color: #9a3412;
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
    color: #9a3412;
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
    background: rgba(255, 255, 255, 0.88);
    border: 2px dashed rgba(251, 146, 60, 0.4);
    box-shadow: 0 10px 30px rgba(120, 53, 15, 0.06);
}

.restaurant-no-school-card__icon {
    color: #c2410c;
    margin-bottom: 16px;
}

.restaurant-no-school-card__title {
    margin: 0 0 8px;
    font-size: 1.3rem;
    font-weight: 800;
    color: #9a3412;
}

.restaurant-no-school-card__text {
    margin: 0 auto 28px;
    font-size: 1rem;
    line-height: 1.6;
    color: #6b7280;
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
    background: rgba(255, 247, 237, 0.7);
    border: 1px solid rgba(251, 146, 60, 0.18);
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: left;
    font-family: inherit;
    font-size: 0.95rem;
    color: #1f2937;
}

.restaurant-school-item:hover {
    background: rgba(251, 146, 60, 0.15);
    border-color: rgba(251, 146, 60, 0.4);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(120, 53, 15, 0.1);
}

.restaurant-school-item__icon {
    color: #c2410c;
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
    color: #9ca3af;
    flex-shrink: 0;
    transition: color 0.15s ease;
}

.restaurant-school-item:hover .restaurant-school-item__arrow {
    color: #c2410c;
}

.restaurant-school-list__empty {
    grid-column: 1 / -1;
    text-align: center;
    color: #9ca3af;
    font-size: 0.9rem;
    padding: 12px 0;
    margin: 0;
}

.restaurant-auth {
    padding: 0 0 24px;
}

.restaurant-auth-card {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 20px 26px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(251, 146, 60, 0.18);
    box-shadow: 0 10px 30px rgba(120, 53, 15, 0.06);
}

.restaurant-auth-card__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: rgba(251, 146, 60, 0.12);
    color: #c2410c;
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
    color: #111827;
}

.restaurant-auth-card__text {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
    color: #6b7280;
}

.restaurant-auth-card__meta {
    margin: 0 0 4px;
    font-size: 0.86rem;
    font-weight: 700;
    color: #9a3412;
    word-break: break-word;
}

.restaurant-auth-card__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

.restaurant-login-intro {
    margin: 0 0 12px;
    color: #4b5563;
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
    color: #9a3412;
    font-size: 0.85rem;
    font-weight: 700;
}

.restaurant-login-school__icon {
    color: #c2410c;
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
    color: #9a3412;
    margin-bottom: 4px;
}

.restaurant-login-email__value {
    font-size: 1rem;
    font-weight: 800;
    color: #111827;
    word-break: break-word;
}

.restaurant-login-state {
    display: grid;
    gap: 12px;
}

.restaurant-login-state__text {
    margin: 0;
    color: #4b5563;
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
    background: rgba(255, 247, 237, 0.8);
    border: 1px solid rgba(251, 146, 60, 0.16);
    color: #6b7280;
    font-size: 0.85rem;
    cursor: pointer;
}

.restaurant-login-state__match strong {
    color: #1f2937;
}

.restaurant-login-state__match--selected {
    border-color: rgba(234, 88, 12, 0.45);
    background: rgba(255, 237, 213, 0.95);
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
    color: #c2410c;
}

.rp-section-header__title {
    font-size: 1.3rem;
    font-weight: 800;
    color: #1f2937;
    margin: 0;
}

.rp-plan {
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(251, 146, 60, 0.18);
    box-shadow: 0 16px 40px rgba(120, 53, 15, 0.08);
    overflow: hidden;
}

.rp-plan + .rp-plan {
    margin-top: 20px;
}

.rp-plan--orderable {
    border-color: rgba(34, 197, 94, 0.3);
    box-shadow: 0 16px 40px rgba(34, 197, 94, 0.1);
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
    border-bottom: 1px solid rgba(251, 146, 60, 0.12);
    border-right: 1px solid rgba(251, 146, 60, 0.12);
}

.rp-day__header {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 14px 18px 8px;
    background: rgba(255, 247, 237, 0.5);
    border-bottom: 1px solid rgba(251, 146, 60, 0.1);
}

.rp-day__weekday {
    font-size: 0.88rem;
    font-weight: 800;
    color: #9a3412;
}

.rp-day__date {
    font-size: 0.82rem;
    color: #6b7280;
}

.rp-day__menus {
    padding: 10px 18px 14px;
    display: grid;
    gap: 10px;
}

.rp-menu {
    padding: 10px 14px;
    border-radius: 12px;
    background: rgba(255, 247, 237, 0.6);
    border: 1px solid rgba(251, 146, 60, 0.12);
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
    color: #1f2937;
}

.rp-menu__price {
    font-size: 0.88rem;
    font-weight: 800;
    color: #c2410c;
    white-space: nowrap;
}

.rp-menu__foods {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 8px;
    margin-top: 6px;
}

.rp-menu__food {
    font-size: 0.82rem;
    color: #4b5563;
    line-height: 1.5;
}

.rp-menu__allergens {
    font-size: 0.75rem;
    color: #9ca3af;
}

.rp-menu__times {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 6px;
    font-size: 0.78rem;
    color: #6b7280;
}

.rp-menu__times-icon {
    color: #9ca3af;
}

.rp-menu__comments {
    margin-top: 6px;
    font-size: 0.8rem;
    color: #6b7280;
    font-style: italic;
    line-height: 1.5;
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
    background: rgba(255, 247, 237, 0.9);
    color: #9a3412;
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
    background: rgba(255, 247, 237, 0.9);
    color: #9a3412;
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
    background: rgba(255, 247, 237, 0.72);
    color: #9a3412;
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

.rp-empty {
    text-align: center;
    padding: 48px 24px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(251, 146, 60, 0.12);
}

.rp-empty__icon {
    color: #d4a373;
    opacity: 0.5;
    margin-bottom: 12px;
}

.rp-empty__text {
    margin: 0;
    font-size: 1rem;
    color: #6b7280;
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

<template>
    <div class="offer-card" :class="{ 'offer-card-own': is_mark }" :data-testid="offerCardTestId" @click="$emit('clickCard')">
        <div class="card-glow"></div>

        <!-- Card Header -->
        <div class="card-header">
            <div class="subject-badge">
                <span class="subject-short">{{ subject?.split(':')[0] || '?' }}</span>
            </div>
            <div class="header-info">
                <h3 class="offer-title">{{ title }}</h3>
                <div class="school-name">{{ school_long_name || school_short_name }}</div>
            </div>
            <div class="header-icons">
                <v-icon v-if="is_mark" size="20" color="orange" class="own-badge" title="Dein Angebot">mdi-star</v-icon>
                <v-icon v-if="my_request?.sent_at" size="18" color="primary" title="Anfrage gesendet">mdi-send-check</v-icon>
                <v-icon v-if="my_request?.seen_at" size="18" color="success" title="Gesehen">mdi-eye-check</v-icon>
                <v-icon v-if="my_request?.mail_at" size="18" color="info" title="E-Mail erhalten">mdi-email-check</v-icon>
            </div>
        </div>

        <!-- Subject Chip -->
        <div class="subject-chip-wrapper">
            <span class="subject-chip">{{ subject }}</span>
        </div>

        <!-- Description -->
        <div class="card-description" v-if="description">
            <p>{{ description.substr(0, 140) }}<span v-if="description.length > 140">...</span></p>
        </div>

        <!-- Card Footer -->
        <div class="card-footer">
            <div class="footer-text" v-if="subtext">{{ subtext }}</div>
            <div class="view-action">
                <span>{{ button || 'Anschauen' }}</span>
                <v-icon size="18">mdi-arrow-right</v-icon>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: [
        'school_short_name',
        'school_long_name',
        'subject',
        'color',
        'title',
        'description',
        'subtext',
        'justify',
        'button',
        'is_mark',
        'mark_icon',
        'mark_color',
        'my_request',
        'offer_id',
    ],
    emits: ['clickCard'],
    computed: {
        offerCardTestId() {
            return this.offer_id ? `tutoring-offer-card-${this.offer_id}` : 'tutoring-offer-card'
        },
    },
}
</script>

<style scoped>
.offer-card {
    position: relative;
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex;
    flex-direction: column;
    height: 100%;
}

.offer-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.offer-card-own {
    border: 2px solid rgba(243, 146, 0, 0.3);
}

/* Card Glow */
.card-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3AAA35, #4BC044);
    transition: height 0.3s ease;
}

.offer-card:hover .card-glow {
    height: 5px;
}

.offer-card-own .card-glow {
    background: linear-gradient(90deg, #F39200, #FFB74D);
}

/* Card Header */
.card-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 18px 18px 12px;
    background: linear-gradient(135deg, rgba(58, 170, 53, 0.06), rgba(58, 170, 53, 0.02));
}

.offer-card-own .card-header {
    background: linear-gradient(135deg, rgba(243, 146, 0, 0.06), rgba(243, 146, 0, 0.02));
}

.subject-badge {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #3AAA35, #2d8a2a);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.3s ease;
}

.offer-card:hover .subject-badge {
    transform: scale(1.08);
}

.offer-card-own .subject-badge {
    background: linear-gradient(135deg, #F39200, #d67f00);
}

.subject-short {
    color: white;
    font-weight: 700;
    font-size: 0.85rem;
}

.header-info {
    flex: 1;
    min-width: 0;
}

.offer-title {
    font-size: 1rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 4px 0;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.school-name {
    font-size: 0.75rem;
    color: #78909C;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.header-icons {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}

.own-badge {
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Subject Chip */
.subject-chip-wrapper {
    padding: 0 18px 12px;
}

.subject-chip {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(58, 170, 53, 0.1);
    color: #2E7D32;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.offer-card-own .subject-chip {
    background: rgba(243, 146, 0, 0.1);
    color: #E65100;
}

/* Card Description */
.card-description {
    padding: 0 18px 16px;
    flex: 1;
}

.card-description p {
    font-size: 0.85rem;
    color: #607D8B;
    line-height: 1.5;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Card Footer */
.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: #f8f9fa;
    border-top: 1px solid #e8e8e8;
    margin-top: auto;
}

.footer-text {
    font-size: 0.8rem;
    color: #78909C;
}

.view-action {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    font-size: 0.85rem;
    color: #3AAA35;
    transition: gap 0.3s ease;
}

.offer-card-own .view-action {
    color: #F39200;
}

.offer-card:hover .view-action {
    gap: 10px;
}

/* Responsive */
@media (max-width: 400px) {
    .card-header {
        padding: 14px;
    }

    .subject-badge {
        width: 38px;
        height: 38px;
    }

    .offer-title {
        font-size: 0.95rem;
    }
}
</style>

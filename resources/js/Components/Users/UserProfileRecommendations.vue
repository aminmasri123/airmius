<script setup>
defineProps({
    profileUser: { type: Object, required: true },
    viewer: { type: Object, required: true },
    recommendationForm: { type: Object, required: true },
    relationshipLabel: { type: Function, required: true },
    sendRecommendation: { type: Function, required: true },
    approveRecommendation: { type: Function, required: true },
    rejectRecommendation: { type: Function, required: true },
})
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
        <h2 class="text-sm font-bold uppercase tracking-wide text-secondary">Empfehlungen</h2>

        <form v-if="!viewer.is_self" class="mt-4 space-y-3 rounded-xl border border-border bg-bg p-4" @submit.prevent="sendRecommendation">
            <div>
                <select v-model="recommendationForm.relationship" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    <option value="visitor">Besucher</option>
                    <option value="friend">Freund</option>
                    <option value="team_member">Teamkollege</option>
                    <option value="trainer">Trainer</option>
                    <option value="club_admin">Verein</option>
                </select>
                <p v-if="recommendationForm.errors.relationship" class="mt-1 text-xs text-error">{{ recommendationForm.errors.relationship }}</p>
            </div>

            <div>
                <textarea v-model="recommendationForm.body" rows="4" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Empfehlung schreiben"></textarea>
                <p class="mt-1 text-xs" :class="recommendationForm.errors.body ? 'text-error' : 'text-secondary'">
                    {{ recommendationForm.errors.body || 'Mindestens 20 Zeichen.' }}
                </p>
            </div>

            <button
                class="w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                :disabled="recommendationForm.processing"
            >
                {{ recommendationForm.processing ? 'Wird gesendet...' : 'Senden' }}
            </button>
        </form>

        <div class="mt-4 space-y-3">
            <article v-for="recommendation in profileUser.recommendations" :key="recommendation.id" class="rounded-xl border border-border bg-bg p-4">
                <p class="text-sm leading-relaxed text-primary">{{ recommendation.body }}</p>
                <p class="mt-3 text-xs text-secondary">
                    {{ recommendation.author.name }} - {{ relationshipLabel(recommendation.relationship) }} - {{ recommendation.status === 'pending' ? 'wartet auf Freigabe' : 'veröffentlicht' }}
                </p>
                <div v-if="viewer.is_self && recommendation.status === 'pending'" class="mt-3 flex gap-2">
                    <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm text-buttonTextPrimary" @click="approveRecommendation(recommendation)">Freigeben</button>
                    <button class="rounded-lg border border-border px-3 py-2 text-sm text-primary" @click="rejectRecommendation(recommendation)">Ablehnen</button>
                </div>
            </article>
            <p v-if="!profileUser.recommendations.length" class="text-sm text-secondary">Noch keine Empfehlungen sichtbar.</p>
        </div>
    </section>
</template>


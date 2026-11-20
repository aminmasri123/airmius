<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import copy from '@/../data/team-role-offers-copy.json'
const props = defineProps({ offers: { type: Array, default: () => [] }, showHint: Boolean })
const { locale } = useI18n()
const text = computed(() => copy[locale.value?.split('-')[0]] || copy.en)
const busy = ref(false)
const error = ref('')
const respond = (offer, decision) => {
    if (busy.value) return
    busy.value = true
    error.value = ''
    router.post(route('auth.team-role-offers.respond', [offer.id, decision]), {}, {
        preserveScroll: true,
        onError: () => { error.value = text.value.failed },
        onFinish: () => { busy.value = false },
    })
}
</script>

<template>
    <section v-if="offers.length || showHint" class="my-3 rounded-xl border border-border p-4 text-primary">
        <h3 class="font-bold">{{ text.title }}</h3>
        <p class="mt-1 text-sm text-secondary">{{ text.hint }}</p>
        <p v-if="error" role="alert" class="mt-2 text-error">{{ error }}</p>
        <article v-for="offer in offers" :key="offer.id" class="mt-3 rounded-lg bg-muted p-3">
            <p class="font-semibold">{{ offer.recipient_name }} · {{ text[offer.role] || offer.role }}</p>
            <p class="text-sm">{{ text.active }}: {{ text[offer.previous_role] || offer.previous_role }}</p>
            <p class="text-sm text-secondary">{{ text.pending }}</p>
            <p class="text-xs text-secondary">{{ text.expires }}: {{ new Date(offer.expires_at).toLocaleDateString(locale) }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <button v-if="offer.can_respond" :disabled="busy" class="rounded bg-buttonPrimary px-3 py-2 text-buttonTextPrimary" @click="respond(offer, 'accept')">{{ text.accept }}</button>
                <button v-if="offer.can_respond" :disabled="busy" class="rounded border border-border px-3 py-2" @click="respond(offer, 'decline')">{{ text.decline }}</button>
                <button v-if="offer.can_cancel" :disabled="busy" class="rounded border border-border px-3 py-2" @click="respond(offer, 'cancel')">{{ text.cancel }}</button>
            </div>
        </article>
    </section>
</template>

<script>
import { ref } from 'vue'

const pending = ref(false)
</script>

<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    productId: { type: [Number, String], required: true },
    authenticated: Boolean,
    canLogin: Boolean,
})

const page = usePage()
const { t } = useI18n()
const savedIds = computed(() => new Set(page.props.wishlistProductIds || []))
const saved = computed(() => savedIds.value.has(Number(props.productId)))
const label = computed(() => saved.value
    ? t('Aus Wunschliste entfernen')
    : t('Zur Wunschliste hinzufügen'))
const buttonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-border bg-card text-xl text-primary shadow-sm hover:text-error focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-wait'

function toggle() {
    const action = saved.value ? 'destroy' : 'store'

    router.visit(route(`auth.commerce.products.wishlist.${action}`, props.productId), {
        method: saved.value ? 'delete' : 'post',
        only: ['wishlistProductIds'],
        preserveScroll: true,
        preserveState: true,
        onStart: () => { pending.value = true },
        onFinish: () => { pending.value = false },
    })
}
</script>

<template>
    <button
        v-if="authenticated"
        type="button"
        :class="[buttonClass, { 'text-error': saved }]"
        :aria-label="label"
        :aria-pressed="saved"
        :title="label"
        :disabled="pending"
        @click="toggle"
    >
        <i :class="saved ? 'las la-heart' : 'lar la-heart'" aria-hidden="true"></i>
    </button>
    <Link
        v-else-if="canLogin"
        :href="route('login', { redirect: page.url })"
        :class="buttonClass"
        :aria-label="$t('Anmelden') + ' · ' + label"
        :title="$t('Anmelden') + ' · ' + label"
    >
        <i class="lar la-heart" aria-hidden="true"></i>
    </Link>
</template>

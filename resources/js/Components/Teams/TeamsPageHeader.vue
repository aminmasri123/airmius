<script setup>
defineProps({
    canCreateClub: { type: Boolean, default: false },
})

const emit = defineEmits(['open-club-modal'])
</script>

<template>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-primary">
                {{ $t('Vereine & Teams') }}
            </h1>

            <p class="text-sm text-secondary">
                {{ $t('Verwalte Vereinsstruktur, Teams, Rollen und Einladungen') }}
            </p>
        </div>

        <button
            v-if="canCreateClub"
            type="button"
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-buttonPrimary text-buttonTextPrimary shadow sm:hidden"
            :aria-label="$t('Verein registrieren')"
            @click="emit('open-club-modal')"
        >
            <i class="las la-plus text-2xl"></i>
        </button>

        <button
            v-if="canCreateClub"
            type="button"
            class="hidden rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover sm:inline-flex"
            @click="emit('open-club-modal')"
        >
            + {{ $t('Verein registrieren') }}
        </button>
    </div>
</template>

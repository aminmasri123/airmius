<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t } = useI18n()
const tx = (value, params = {}) => t(value, params)

defineProps({
    awards: { type: Array, default: () => [] },
})
</script>

<template>
    <Head :title="tx('Meine Badges')" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <h1 class="text-2xl font-bold text-primary">{{ tx('Meine Badges') }}</h1>
            <p class="mt-1 text-sm text-secondary">{{ tx('Auszeichnungen aus deinem Profil, Trainerarbeit, Teams und Vereinen.') }}</p>
        </section>

        <section v-if="awards.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="award in awards"
                :key="award.id"
                :href="route('auth.badges.show', award.id)"
                class="surface-card block p-4 transition hover:-translate-y-0.5 hover:border-buttonPrimary"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-buttonPrimary text-buttonTextPrimary">
                        <i :class="[award.badge?.icon || 'las la-medal', 'text-xl']"></i>
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-semibold text-primary">{{ award.badge?.name }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ award.badge?.description }}</p>
                        <p class="mt-2 text-xs uppercase tracking-wide text-secondary">
                            {{ award.badge?.actor_type }} - {{ award.reason || award.badge?.trigger }}
                        </p>
                        <p v-if="award.meta?.xp !== undefined" class="mt-1 text-xs text-secondary">
                            {{ award.meta.xp }} XP - {{ tx('Level') }} {{ award.meta.level }}
                        </p>
                    </div>
                </div>
            </Link>
        </section>

        <section v-else class="surface-card p-6 text-sm text-secondary">
            {{ tx('Noch keine Badges vorhanden.') }}
        </section>
    </div>
</template>

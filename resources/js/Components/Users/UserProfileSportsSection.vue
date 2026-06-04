<script setup>
import SearchableSelect from '@/Components/SearchableSelect.vue'

defineProps({
    profileUser: { type: Object, required: true },
    viewer: { type: Object, required: true },
    sportForm: { type: Object, required: true },
    sports: { type: Array, default: () => [] },
    sportLabel: { type: Function, required: true },
    statusLabel: { type: Function, required: true },
    levelLabel: { type: Function, required: true },
})

const emit = defineEmits(['add-sport'])
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-primary">Sportliches Profil</h2>
                <p class="mt-1 text-sm text-secondary">Sportarten, Ziele und Erfahrungslevel.</p>
            </div>
            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                {{ profileUser.sport_profiles.length }} Sportarten
            </span>
        </div>

        <form
            v-if="viewer.is_self"
            class="mt-5 grid gap-3 rounded-xl border border-border bg-bg p-4 md:grid-cols-[1fr_170px_170px_auto]"
            @submit.prevent="emit('add-sport')"
        >
            <SearchableSelect
                v-model="sportForm.sport_id"
                :options="sports"
                value-key="id"
                translation-prefix="sports"
                category-translation-prefix="sport_categories"
                placeholder="Sportart suchen"
            />
            <select v-model="sportForm.status" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                <option value="active">Betreibe ich</option>
                <option value="wants_to_learn">Möchte ich lernen</option>
                <option value="coach">Trainiere ich</option>
                <option value="interested">Interessiert mich</option>
            </select>
            <select v-model="sportForm.experience_level" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                <option value="beginner">Einsteiger</option>
                <option value="intermediate">Fortgeschritten</option>
                <option value="advanced">Erfahren</option>
                <option value="expert">Experte</option>
            </select>
            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                Hinzufügen
            </button>
        </form>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <article
                v-for="profile in profileUser.sport_profiles"
                :key="profile.id"
                class="rounded-xl border border-border bg-bg p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-primary">{{ sportLabel(profile.sport) }}</h3>
                        <p class="mt-1 text-sm text-secondary">{{ statusLabel(profile.status) }}</p>
                    </div>
                    <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-primary">
                        {{ levelLabel(profile.experience_level) }}
                    </span>
                </div>
                <div v-if="profile.performance_metrics?.length" class="mt-4 grid gap-2">
                    <div
                        v-for="metric in profile.performance_metrics"
                        :key="metric.key"
                        class="rounded-lg border border-border bg-card px-3 py-2"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                            {{ metric.label }}
                        </p>
                        <p class="mt-1 break-words text-sm font-semibold text-primary">
                            {{ metric.value }}
                        </p>
                    </div>
                </div>
            </article>
            <p v-if="!profileUser.sport_profiles.length" class="text-sm text-secondary">Noch keine Sportarten hinterlegt.</p>
        </div>
    </section>
</template>


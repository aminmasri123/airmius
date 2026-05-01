<script setup>
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

const props = defineProps({
    rules: { type: Object, default: () => ({}) },
    actorTypes: { type: Array, default: () => ['sportler', 'trainer', 'verein'] },
})

const selectedActor = ref(props.actorTypes[0] || 'sportler')

const flattenRules = (actor) => Object.values(props.rules[actor] || {}).flat()

const form = useForm({
    rules: flattenRules(selectedActor.value).map((rule) => ({ ...rule })),
})

watch(selectedActor, (actor) => {
    form.rules = flattenRules(actor).map((rule) => ({ ...rule }))
})

const groupedRules = computed(() => {
    return form.rules.reduce((groups, rule) => {
        groups[rule.category] ??= []
        groups[rule.category].push(rule)
        return groups
    }, {})
})

const actorLabel = (actor) => ({
    sportler: 'Sportler',
    trainer: 'Trainer',
    verein: 'Vereine',
}[actor] || actor)

const save = () => {
    form.put(route('gamification-rules.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AppLayout>
        <Head title="Gamification verwalten" />

        <div class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-air-blue">Admin</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Gamification-Regeln</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-secondary">
                        Verwalte XP, Daily Limits, Trust-Auswirkungen und aktive Regeln zentral. Änderungen wirken auf neue Aktionen und halten das System fair, steuerbar und jugendschutzfreundlich.
                    </p>
                </div>

                <button
                    class="rounded-lg bg-buttonPrimary px-5 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                    :disabled="form.processing"
                    @click="save"
                >
                    Speichern
                </button>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="actor in actorTypes"
                    :key="actor"
                    type="button"
                    class="rounded-full border px-4 py-2 text-sm font-semibold"
                    :class="selectedActor === actor ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-card text-primary hover:bg-muted'"
                    @click="selectedActor = actor"
                >
                    {{ actorLabel(actor) }}
                </button>
            </div>

            <section class="grid gap-5">
                <div
                    v-for="(items, category) in groupedRules"
                    :key="category"
                    class="rounded-xl border border-border bg-card"
                >
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <div>
                            <h2 class="font-semibold text-primary">{{ category }}</h2>
                            <p class="text-xs text-secondary">{{ items.length }} Regeln</p>
                        </div>
                    </div>

                    <div class="divide-y divide-border">
                        <article
                            v-for="rule in items"
                            :key="rule.id"
                            class="grid gap-4 p-4 xl:grid-cols-[1.2fr_120px_140px_120px_100px]"
                        >
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input
                                        v-model="rule.label"
                                        class="min-w-0 flex-1 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary"
                                    >
                                    <span class="rounded-full bg-inputBg px-2 py-1 font-mono text-xs text-secondary">{{ rule.key }}</span>
                                    <span v-if="rule.is_penalty" class="rounded-full bg-error/10 px-2 py-1 text-xs font-semibold text-error">Strafe</span>
                                </div>
                                <textarea
                                    v-model="rule.description"
                                    rows="2"
                                    class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                ></textarea>
                            </div>

                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase text-secondary">XP</span>
                                <input v-model.number="rule.xp_amount" type="number" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase text-secondary">Daily Limit</span>
                                <input v-model.number="rule.daily_limit" type="number" min="1" placeholder="kein Limit" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase text-secondary">Trust</span>
                                <input v-model.number="rule.trust_delta" type="number" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                            </label>

                            <label class="flex items-center gap-2 self-center rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <input v-model="rule.is_active" type="checkbox" class="rounded border-border bg-card">
                                Aktiv
                            </label>
                        </article>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

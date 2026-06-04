<script setup>
defineProps({
    sportCv: { type: Object, default: () => ({}) },
})
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-buttonPrimary px-3 py-1 text-xs font-bold text-buttonTextPrimary">Sport-CV</span>
                    <span class="rounded-full border border-border bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                        LinkedIn für Sport
                    </span>
                </div>

                <h2 class="mt-4 text-2xl font-bold text-primary">{{ sportCv.headline || 'Sport-CV aufbauen' }}</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">
                    {{ sportCv.summary || 'Pflege Sportarten, Bestwerte, Skills und Empfehlungen, damit dein sportlicher Lebenslauf sichtbar wird.' }}
                </p>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <article
                        v-for="metric in sportCv.best_metrics || []"
                        :key="`${metric.sport?.id || 'sport'}-${metric.key}`"
                        class="rounded-xl border border-border bg-inputBg p-4"
                    >
                        <div class="truncate text-xs font-semibold uppercase tracking-wide text-secondary">
                            {{ metric.sport?.name || 'Sport' }}
                        </div>
                        <div class="mt-1 text-sm font-semibold text-primary">{{ metric.label }}</div>
                        <div class="mt-2 break-words text-xl font-bold text-primary">{{ metric.value }}</div>
                    </article>

                    <p
                        v-if="!(sportCv.best_metrics || []).length"
                        class="rounded-xl border border-dashed border-border bg-bg p-4 text-sm text-secondary sm:col-span-2"
                    >
                        Noch keine sichtbaren Bestwerte. Du kannst im Sportprofil einzelne Metriken freigeben.
                    </p>
                </div>
            </div>

            <aside class="border-t border-border bg-bg p-5 sm:p-6 lg:border-l lg:border-t-0">
                <div class="space-y-5">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-secondary">Top-Skills</h3>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="skill in sportCv.top_skills || []"
                                :key="skill.id"
                                class="rounded-lg border border-border bg-card p-3"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-primary">{{ skill.name }}</div>
                                        <div class="mt-1 truncate text-xs text-secondary">{{ skill.sport?.name || 'Sport' }}</div>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                                        L{{ skill.level }}
                                    </span>
                                </div>
                                <div class="mt-2 text-xs text-secondary">{{ skill.endorsements_count }} Endorsements</div>
                            </div>
                            <p v-if="!(sportCv.top_skills || []).length" class="text-sm text-secondary">Noch keine sichtbaren Skills.</p>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-secondary">Proof</h3>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div
                                v-for="item in sportCv.proof || []"
                                :key="item.label"
                                class="rounded-lg border border-border bg-card p-3"
                            >
                                <div class="text-lg font-bold text-primary">{{ item.value }}</div>
                                <div class="text-xs text-secondary">{{ item.label }}</div>
                            </div>
                        </div>
                    </div>

                    <p class="rounded-lg border border-border bg-card p-3 text-xs leading-5 text-secondary">
                        {{ sportCv.privacy_note || 'Du entscheidest, welche Profilbereiche sichtbar sind.' }}
                    </p>
                </div>
            </aside>
        </div>
    </section>
</template>


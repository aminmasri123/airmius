<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    dailyFlow: { type: Object, default: () => ({}) },
})

const stepTone = (key) => ({
    training: 'bg-sky-500',
    route: 'bg-emerald-500',
    nutrition: 'bg-amber-500',
    hydration: 'bg-air-blue',
    reminders: 'bg-violet-500',
}[key] || 'bg-buttonPrimary')
</script>

<template>
    <section class="surface-card overflow-hidden xl:col-span-12">
        <div class="grid gap-0 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
            <div class="border-b border-border p-4 sm:p-5 lg:border-b-0 lg:border-r">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Heute</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">Dein Sport-Flow</h2>
                    </div>

                    <div class="shrink-0 rounded-xl border border-border bg-inputBg px-4 py-3 text-center">
                        <div class="text-2xl font-bold text-primary">{{ dailyFlow.score || 0 }}%</div>
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-secondary">bereit</div>
                    </div>
                </div>

                <p class="mt-4 text-sm leading-6 text-secondary">
                    {{ dailyFlow.summary || 'Starte deinen Tag mit Training, Route, Ernährung oder Wasser.' }}
                </p>

                <div class="mt-4 rounded-xl border border-air-blue/30 bg-air-blue/10 p-4">
                    <div class="flex gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-air-blue text-white">
                            <i class="las la-brain"></i>
                        </div>
                        <p class="text-sm leading-6 text-primary">
                            {{ dailyFlow.coach_note || 'KI-Coach: Sobald du Aktivität einträgst, bekommst du den nächsten sinnvollen Schritt.' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-5">
                <Link
                    v-for="step in dailyFlow.steps || []"
                    :key="step.key"
                    :href="step.href"
                    class="group flex min-h-44 flex-col rounded-xl border border-border bg-inputBg p-4 transition hover:-translate-y-0.5 hover:border-air-blue/50 hover:bg-card"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-card text-primary">
                            <i :class="step.icon"></i>
                        </div>
                        <span class="text-xs font-bold text-secondary">{{ Math.min(100, Number(step.progress || 0)) }}%</span>
                    </div>

                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-border">
                        <div
                            :class="['h-full rounded-full', stepTone(step.key)]"
                            :style="{ width: `${Math.min(100, Number(step.progress || 0))}%` }"
                        ></div>
                    </div>

                    <div class="mt-4 min-w-0 flex-1">
                        <h3 class="truncate text-sm font-bold text-primary">{{ step.title }}</h3>
                        <p class="mt-1 line-clamp-2 text-sm leading-5 text-secondary">{{ step.body }}</p>
                        <p class="mt-2 truncate text-xs font-semibold text-secondary">{{ step.meta }}</p>
                    </div>

                    <div class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-air-blue">
                        {{ step.cta }}
                        <i class="las la-arrow-right transition group-hover:translate-x-0.5"></i>
                    </div>
                </Link>
            </div>
        </div>
    </section>
</template>

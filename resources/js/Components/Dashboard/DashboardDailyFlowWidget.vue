<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    dailyFlow: { type: Object, default: () => ({}) },
})

const { t, locale } = useI18n()
const week = computed(() => props.dailyFlow.week || {})
const primaryAction = computed(() => props.dailyFlow.primary_action || null)
const localeCode = computed(() => ({ de: 'de-DE', en: 'en-US', fr: 'fr-FR', ar: 'ar' }[locale.value] || 'de-DE'))
const dayLabel = (date) => new Intl.DateTimeFormat(localeCode.value, { weekday: 'narrow' }).format(new Date(`${date}T12:00:00`))
const weekStatusLabel = computed(() => t(`athlete_today.week_${week.value.status || 'open'}`))
const weekStatusTone = computed(() => ({
    on_track: 'border-success/30 bg-success/10 text-success',
    watch: 'border-warning/30 bg-warning/10 text-warning',
    behind: 'border-error/30 bg-error/10 text-error',
    open: 'border-border bg-inputBg text-secondary',
}[week.value.status] || 'border-border bg-inputBg text-secondary'))
const dayTone = (state) => ({
    completed: 'border-success bg-success text-white',
    partial: 'border-warning bg-warning/15 text-warning',
    missed: 'border-error bg-error/15 text-error',
    planned: 'border-air-blue bg-air-blue/15 text-air-blue',
    future: 'border-border bg-card text-secondary',
    rest: 'border-border bg-inputBg text-secondary',
}[state] || 'border-border bg-inputBg text-secondary')
const dayTitle = (day) => t(`athlete_today.day_${day.state || 'rest'}`)

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
            <div class="border-b border-border p-4 sm:p-5 lg:border-b-0 lg:border-e">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ t('athlete_today.eyebrow') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ t('athlete_today.title') }}</h2>
                    </div>

                    <div class="shrink-0 rounded-xl border border-border bg-inputBg px-4 py-3 text-center">
                        <div class="text-2xl font-bold text-primary">{{ dailyFlow.score || 0 }}%</div>
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-secondary">{{ t('athlete_today.progress') }}</div>
                    </div>
                </div>

                <p class="mt-4 text-sm leading-6 text-secondary">
                    {{ dailyFlow.summary || t('athlete_today.summary_fallback') }}
                </p>

                <div class="mt-4 rounded-xl border border-air-blue/30 bg-air-blue/10 p-4">
                    <div class="flex gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-air-blue text-white">
                            <i class="las la-brain"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-air-blue">{{ t('athlete_today.coach') }}</p>
                            <p class="mt-1 text-sm leading-6 text-primary">{{ dailyFlow.coach_note || t('athlete_today.coach_fallback') }}</p>
                        </div>
                    </div>
                </div>

                <Link
                    v-if="primaryAction"
                    :href="primaryAction.href"
                    class="mt-4 flex items-center gap-3 rounded-xl border border-air-blue/40 bg-card p-3 transition hover:border-air-blue"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary"><i :class="primaryAction.icon"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-bold uppercase tracking-wide text-secondary">{{ t('athlete_today.primary_action') }}</span>
                        <span class="mt-0.5 block font-bold text-primary">{{ primaryAction.label }}</span>
                        <span class="mt-0.5 block text-xs leading-5 text-secondary">{{ primaryAction.reason }}</span>
                    </span>
                    <i class="las la-arrow-right text-air-blue rtl:rotate-180"></i>
                </Link>

                <div v-if="week.days?.length" class="mt-4 rounded-xl border border-border bg-inputBg/60 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-bold text-primary">{{ t('athlete_today.week_title') }}</p>
                        <span :class="['rounded-full border px-2.5 py-1 text-[11px] font-bold', weekStatusTone]">{{ weekStatusLabel }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-7 gap-1.5">
                        <div v-for="day in week.days" :key="day.date" class="text-center">
                            <span class="text-[10px] font-bold uppercase text-secondary">{{ dayLabel(day.date) }}</span>
                            <div :class="['mx-auto mt-1 flex h-8 w-8 items-center justify-center rounded-full border text-xs font-bold', dayTone(day.state), day.is_today ? 'ring-2 ring-air-blue/30 ring-offset-2 ring-offset-card' : '']" :title="dayTitle(day)">
                                <i v-if="day.state === 'completed'" class="las la-check"></i>
                                <span v-else>{{ day.planned_sessions || '·' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-border">
                        <div class="h-full rounded-full bg-gradient-to-r from-air-blue to-emerald-400" :style="{ width: `${Math.min(100, Number(week.progress_percent || 0))}%` }"></div>
                    </div>
                    <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                        <div><p class="font-bold text-primary">{{ week.planned_sessions || 0 }}</p><p class="text-[10px] text-secondary">{{ t('athlete_today.planned') }}</p></div>
                        <div><p class="font-bold text-primary">{{ week.completed_sessions || 0 }}</p><p class="text-[10px] text-secondary">{{ t('athlete_today.completed') }}</p></div>
                        <div><p class="font-bold text-primary">{{ week.completed_minutes || 0 }}</p><p class="text-[10px] text-secondary">{{ t('athlete_today.minutes') }}</p></div>
                        <div><p class="font-bold text-primary">{{ week.adherence_percent || 0 }}%</p><p class="text-[10px] text-secondary">{{ t('athlete_today.adherence') }}</p></div>
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

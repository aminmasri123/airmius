<script setup>
import { Link } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    teamId: { type: Number, required: true },
})

const { t, locale } = useI18n()
const flow = ref(null)
const loading = ref(true)
const error = ref('')
let requestController = null

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const primaryAction = computed(() => flow.value?.today?.primary_action || null)
const nextEvent = computed(() => flow.value?.attendance?.next_event || null)
const attendance = computed(() => flow.value?.attendance?.summary || {})
const tasks = computed(() => flow.value?.tasks?.items || [])
const cashBox = computed(() => flow.value?.cash_box || {})
const missingResponses = computed(() => flow.value?.attendance?.missing_responses || [])

const formatNumber = (value) => new Intl.NumberFormat(localeCode.value).format(Number(value || 0))
const formatMoney = (value, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: currency || 'EUR',
}).format(Number(value || 0))
const formatDateTime = (value) => value
    ? new Intl.DateTimeFormat(localeCode.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : t('team_home.not_scheduled')
const priorityTone = (priority) => ({
    high: 'border-error/30 bg-error/10 text-error',
    medium: 'border-warning/30 bg-warning/10 text-warning',
    low: 'border-success/30 bg-success/10 text-success',
}[priority] || 'border-border bg-inputBg text-secondary')

const load = async () => {
    requestController?.abort()
    const controller = new AbortController()
    requestController = controller
    loading.value = true
    error.value = ''

    try {
        const response = await window.axios.get(`/api/v1/teams/${props.teamId}/daily-life`, {
            headers: { 'X-Locale': locale.value },
            signal: controller.signal,
        })
        if (requestController === controller) {
            flow.value = response.data?.data || null
        }
    } catch (requestError) {
        if (requestError?.code !== 'ERR_CANCELED') {
            error.value = requestError.response?.data?.message || t('team_home.load_error')
        }
    } finally {
        if (requestController === controller && !controller.signal.aborted) loading.value = false
    }
}

onMounted(load)
onBeforeUnmount(() => requestController?.abort())
watch(locale, load)
</script>

<template>
    <section id="team-today" class="surface-card overflow-hidden">
        <div class="border-b border-border bg-gradient-to-r from-air-blue/15 via-card to-emerald-400/10 p-4 sm:p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-air-blue">{{ t('team_home.eyebrow') }}</p>
                    <h2 class="mt-1 text-xl font-black text-primary">{{ t('team_home.title') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ t('team_home.description') }}</p>
                </div>
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-primary transition hover:border-air-blue hover:text-air-blue disabled:opacity-50"
                    :disabled="loading"
                    :aria-label="t('team_home.refresh')"
                    @click="load"
                >
                    <i class="las la-sync" :class="{ 'animate-spin': loading }" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div v-if="loading && !flow" class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4" aria-busy="true">
            <div v-for="index in 4" :key="index" class="h-24 animate-pulse rounded-xl bg-inputBg"></div>
        </div>

        <div v-else-if="error && !flow" class="p-5">
            <div class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error">
                <p>{{ error }}</p>
                <button type="button" class="mt-3 font-bold underline" @click="load">{{ t('team_home.retry') }}</button>
            </div>
        </div>

        <div v-else-if="flow" class="grid gap-5 p-4 sm:p-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(300px,0.75fr)]">
            <div class="min-w-0 space-y-4">
                <Link
                    v-if="primaryAction"
                    :href="primaryAction.href"
                    class="flex items-center gap-3 rounded-xl border border-air-blue/35 bg-air-blue/10 p-4 transition hover:border-air-blue"
                >
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-buttonPrimary text-buttonTextPrimary">
                        <i :class="primaryAction.icon" aria-hidden="true"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-bold uppercase tracking-wide text-air-blue">{{ t('team_home.primary_action') }}</span>
                        <span class="mt-0.5 block font-black text-primary">{{ primaryAction.label }}</span>
                        <span class="mt-1 block text-sm leading-5 text-secondary">{{ primaryAction.reason }}</span>
                    </span>
                    <i class="las la-arrow-right shrink-0 text-air-blue rtl:rotate-180" aria-hidden="true"></i>
                </Link>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl border border-border bg-inputBg p-3">
                        <p class="text-xs font-semibold text-secondary">{{ t('team_home.response_rate') }}</p>
                        <p class="mt-1 text-xl font-black text-primary">{{ formatNumber(attendance.response_rate) }}%</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg p-3">
                        <p class="text-xs font-semibold text-secondary">{{ t('team_home.open_tasks') }}</p>
                        <p class="mt-1 text-xl font-black text-primary">{{ formatNumber(flow.today.open_tasks) }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg p-3">
                        <p class="text-xs font-semibold text-secondary">{{ t('team_home.free_seats') }}</p>
                        <p class="mt-1 text-xl font-black text-primary">{{ formatNumber(flow.today.available_carpool_seats) }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-inputBg p-3">
                        <p class="text-xs font-semibold text-secondary">{{ cashBox.scope === 'self' ? t('team_home.my_open_fees') : t('team_home.open_fees') }}</p>
                        <p class="mt-1 text-xl font-black text-primary">{{ formatNumber(flow.today.open_fees) }}</p>
                    </div>
                </div>

                <div v-if="nextEvent" class="rounded-xl border border-border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ t('team_home.next_event') }}</p>
                            <h3 class="mt-1 truncate font-black text-primary">{{ nextEvent.title }}</h3>
                            <p class="mt-1 text-sm text-secondary">{{ formatDateTime(nextEvent.start_time) }}</p>
                            <p v-if="nextEvent.location_name" class="mt-1 truncate text-sm text-secondary"><i class="las la-map-marker me-1" aria-hidden="true"></i>{{ nextEvent.location_name }}</p>
                        </div>
                        <Link :href="route('auth.events.show', nextEvent.id)" class="shrink-0 rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                            {{ t('team_home.open_event') }}
                        </Link>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-border p-5 text-center text-sm text-secondary">
                    {{ t('team_home.no_upcoming_event') }}
                </div>

                <div v-if="missingResponses.length" class="rounded-xl border border-border p-4">
                    <p class="text-sm font-black text-primary">{{ t('team_home.missing_responses') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span v-for="member in missingResponses" :key="member.id" class="rounded-full border border-warning/30 bg-warning/10 px-3 py-1 text-xs font-semibold text-warning">
                            {{ member.name }}
                        </span>
                    </div>
                </div>
            </div>

            <aside class="min-w-0 rounded-xl border border-border bg-inputBg p-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-black text-primary">{{ t('team_home.tasks_title') }}</h3>
                    <span class="rounded-full bg-card px-2.5 py-1 text-xs font-bold text-secondary">{{ tasks.length }}</span>
                </div>
                <div class="mt-3 space-y-2">
                    <div v-for="task in tasks" :key="task.key" class="rounded-xl border border-border bg-card p-3">
                        <div class="flex items-start gap-3">
                            <span :class="['mt-0.5 rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase', priorityTone(task.priority)]">
                                {{ t(`team_home.priority_${task.priority}`) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-primary">{{ t(`team_home.tasks.${task.key}`, { count: task.count }) }}</p>
                                <p v-if="task.due_at" class="mt-1 text-xs text-secondary">{{ formatDateTime(task.due_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-border bg-card p-3 text-xs leading-5 text-secondary">
                    <i class="las la-shield-alt me-1 text-success" aria-hidden="true"></i>
                    {{ flow.access.can_manage_operations ? t('team_home.team_scope_notice') : t('team_home.personal_scope_notice') }}
                    <span v-if="cashBox.counts?.open" class="mt-1 block font-semibold text-primary">
                        {{ t('team_home.open_amount') }}: {{ formatMoney(cashBox.totals?.open, cashBox.open_items?.[0]?.currency || 'EUR') }}
                    </span>
                </div>
            </aside>
        </div>
    </section>
</template>

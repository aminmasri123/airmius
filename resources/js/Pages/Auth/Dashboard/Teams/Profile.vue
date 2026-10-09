<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { confirmDialog } from '@/services/dialogService'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import TeamDailyHomeWidget from '@/Components/Teams/TeamDailyHomeWidget.vue'
import AppEmptyState from '@/Components/UI/AppEmptyState.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    teamProfile: Object,
    posts: { type: Array, default: () => [] },
    viewer: Object,
})

const page = usePage()
const storageUrl = (path) => path?.startsWith('http') ? path : `${page.props.uploads?.url || '/storage'}/${path}`
const { t, te, locale, messages } = useI18n({ useScope: 'global' })
const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const formatDate = (value) => new Intl.DateTimeFormat(localeCode.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
const tAuto = (value, params = {}) => {
    const source = String(value ?? '').trim()
    if (!source || locale.value === 'de') return source
    const dictionary = messages.value?.[locale.value]?.auto || {}
    if (dictionary[source]) return dictionary[source]
    return te(source) ? t(source, params) : source
}
const sportLabel = (value) => {
    if (!value) return tAuto('Team')

    const key = `sports.${value}`

    return te(key) ? t(key) : value
}
const requestJoin = () => router.post(route('auth.teams.join-requests.store', props.teamProfile.id), {}, { preserveScroll: true })
const logoInput = ref(null)
const coverInput = ref(null)
const penaltiesLoading = ref(false)
const penaltiesError = ref('')
const penalties = ref({
    can_manage: false,
    rules: [],
    fees: [],
    summary: {
        open_amount: 0,
        paid_amount: 0,
        open_count: 0,
    },
})
const editingRuleId = ref(null)
const ruleForm = ref({
    title: '',
    trigger: 'late',
    calculation_type: 'per_minute',
    amount: '',
    threshold_minutes: '',
    max_amount: '',
    unit_label: '',
    description: '',
    is_active: true,
})
const feeForm = ref({
    user_id: '',
    penalty_rule_id: '',
    amount: '',
    minutes: '',
    note: '',
    due_date: '',
})
const imageForm = useForm({
    logo: null,
    cover_image: null,
})

const viewerCanManage = computed(() => Boolean(props.viewer?.can_manage))
const activePenaltyRules = computed(() => penalties.value.rules.filter((rule) => rule.is_active !== false))
const canManagePenalties = computed(() => penalties.value.can_manage || viewerCanManage.value)
const selectedPenaltyRule = computed(() => activePenaltyRules.value.find((rule) => Number(rule.id) === Number(feeForm.value.penalty_rule_id)))
const ruleNeedsMinuteThreshold = computed(() => ruleForm.value.calculation_type === 'threshold_fixed')
const attendanceStats = computed(() => props.teamProfile.attendance_stats || null)

const triggerLabels = computed(() => ({
    late: tAuto('Zu spät'),
    absence: tAuto('Fehlt'),
    forgotten_equipment: tAuto('Ausrüstung vergessen'),
    custom: tAuto('Individuell'),
}))

const calculationLabels = computed(() => ({
    fixed: tAuto('Fester Betrag'),
    per_minute: tAuto('Pro Minute'),
    threshold_fixed: tAuto('Ab Minuten-Grenze'),
    item: tAuto('Sachstrafe'),
}))

const feeStatusLabel = (status) => ({
    open: tAuto('Offen'),
    paid: tAuto('Bezahlt'),
    cancelled: tAuto('Storniert'),
}[status] || tAuto(status || 'Unbekannt'))

const formatMoney = (amount, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: currency || 'EUR',
}).format(Number(amount || 0))

const resetRuleForm = () => {
    editingRuleId.value = null
    ruleForm.value = {
        title: '',
        trigger: 'late',
        calculation_type: 'per_minute',
        amount: '',
        threshold_minutes: '',
        max_amount: '',
        unit_label: '',
        description: '',
        is_active: true,
    }
}

const resetFeeForm = () => {
    feeForm.value = {
        user_id: '',
        penalty_rule_id: '',
        amount: '',
        minutes: '',
        note: '',
        due_date: '',
    }
}

const penaltyRuleLabel = (rule) => {
    if (!rule) return tAuto('Manueller Betrag')
    if (rule.calculation_type === 'item') return `${rule.title} (${rule.unit_label || tAuto('Sachstrafe')})`
    if (rule.calculation_type === 'per_minute') return `${rule.title} (${formatMoney(rule.amount, rule.currency)} / Min.)`
    if (rule.calculation_type === 'threshold_fixed') return `${rule.title} (ab ${rule.threshold_minutes || 0} Min.)`
    return `${rule.title} (${formatMoney(rule.amount, rule.currency)})`
}

const loadPenalties = async () => {
    penaltiesLoading.value = true
    penaltiesError.value = ''

    try {
        const response = await window.axios.get(route('auth.teams.penalties.index', props.teamProfile.id))
        penalties.value = response.data.data
    } catch (error) {
        penaltiesError.value = error.response?.data?.message || tAuto('Strafkatalog konnte nicht geladen werden.')
    } finally {
        penaltiesLoading.value = false
    }
}

const optimisticRuleFromPayload = (payload, id) => ({
    id,
    team_id: props.teamProfile.id,
    title: payload.title,
    trigger: payload.trigger,
    calculation_type: payload.calculation_type,
    amount: payload.amount,
    currency: 'EUR',
    threshold_minutes: payload.threshold_minutes,
    max_amount: payload.max_amount,
    unit_label: payload.unit_label,
    description: payload.description,
    is_active: payload.is_active,
    sort_order: 0,
    is_saving: true,
})

const submitRule = async () => {
    penaltiesError.value = ''
    const payload = {
        title: ruleForm.value.title,
        trigger: ruleForm.value.trigger,
        calculation_type: ruleForm.value.calculation_type,
        amount: ruleForm.value.amount === '' ? null : Number(ruleForm.value.amount),
        threshold_minutes: ruleNeedsMinuteThreshold.value && ruleForm.value.threshold_minutes !== '' ? Number(ruleForm.value.threshold_minutes) : null,
        max_amount: ruleForm.value.max_amount === '' ? null : Number(ruleForm.value.max_amount),
        unit_label: ruleForm.value.unit_label || null,
        description: ruleForm.value.description || null,
        is_active: ruleForm.value.is_active,
    }
    const previousRules = [...penalties.value.rules]
    const targetRuleId = editingRuleId.value
    const optimisticId = targetRuleId || `temp-${Date.now()}`
    const optimisticRule = optimisticRuleFromPayload(payload, optimisticId)

    penalties.value.rules = targetRuleId
        ? penalties.value.rules.map((rule) => Number(rule.id) === Number(targetRuleId) ? { ...rule, ...optimisticRule, id: targetRuleId } : rule)
        : [optimisticRule, ...penalties.value.rules]

    resetRuleForm()

    try {
        let response
        if (targetRuleId) {
            response = await window.axios.put(route('auth.teams.penalty-rules.update', [props.teamProfile.id, targetRuleId]), payload)
        } else {
            response = await window.axios.post(route('auth.teams.penalty-rules.store', props.teamProfile.id), payload)
        }

        const savedRule = response.data?.data
        if (savedRule) {
            penalties.value.rules = penalties.value.rules.map((rule) => String(rule.id) === String(optimisticId) ? savedRule : rule)
        } else {
            await loadPenalties()
        }
    } catch (error) {
        penalties.value.rules = previousRules
        penaltiesError.value = error.response?.data?.message || tAuto('Regel konnte nicht gespeichert werden.')
    }
}

const editRule = (rule) => {
    editingRuleId.value = rule.id
    ruleForm.value = {
        title: rule.title || '',
        trigger: rule.trigger || 'custom',
        calculation_type: rule.calculation_type || 'fixed',
        amount: rule.amount ?? '',
        threshold_minutes: rule.threshold_minutes ?? '',
        max_amount: rule.max_amount ?? '',
        unit_label: rule.unit_label || '',
        description: rule.description || '',
        is_active: rule.is_active !== false,
    }
}

const deactivateRule = async (rule) => {
    if (!window.confirm(t('teams_profile.messages.deactivate_rule', { title: rule.title }))) return

    try {
        await window.axios.delete(route('auth.teams.penalty-rules.destroy', [props.teamProfile.id, rule.id]))
        await loadPenalties()
    } catch (error) {
        penaltiesError.value = error.response?.data?.message || tAuto('Regel konnte nicht deaktiviert werden.')
    }
}

const submitFee = async () => {
    penaltiesError.value = ''
    const payload = {
        user_id: Number(feeForm.value.user_id),
        penalty_rule_id: feeForm.value.penalty_rule_id ? Number(feeForm.value.penalty_rule_id) : null,
        amount: feeForm.value.amount === '' ? null : Number(feeForm.value.amount),
        minutes: feeForm.value.minutes === '' ? null : Number(feeForm.value.minutes),
        note: feeForm.value.note || null,
        due_date: feeForm.value.due_date || null,
    }

    try {
        await window.axios.post(route('auth.teams.penalty-fees.store', props.teamProfile.id), payload)
        resetFeeForm()
        await loadPenalties()
    } catch (error) {
        penaltiesError.value = error.response?.data?.message || tAuto('Strafe konnte nicht gebucht werden.')
    }
}

const markFeePaid = async (fee) => {
    try {
        await window.axios.post(route('auth.teams.penalty-fees.paid', [props.teamProfile.id, fee.id]), { account: feePaymentAccount.value, club_money_account_id: feeMoneyAccountId.value })
        await loadPenalties()
    } catch (error) {
        penaltiesError.value = error.response?.data?.message || tAuto('Buchung konnte nicht bezahlt markiert werden.')
    }
}

const feePaymentAccount = ref('cash')
const feeMoneyAccountId = ref(null)
const refundFee = async fee => {
    if (!await confirmDialog({ message: 'Zahlung tatsächlich zurückerstattet?', title: 'Erstattung' })) return
    try {
        await window.axios.post(route('auth.teams.penalty-fees.refund', [props.teamProfile.id, fee.id]), { refunded_on: new Date().toISOString().slice(0, 10) })
        await loadPenalties()
    } catch (error) {
        penaltiesError.value = error.response?.data?.message || 'Erstattung konnte nicht erfasst werden.'
    }
}

const cancelFee = async (fee) => {
    if (!window.confirm(t('teams_profile.messages.cancel_fee'))) return

    try {
        await window.axios.post(route('auth.teams.penalty-fees.cancel', [props.teamProfile.id, fee.id]))
        await loadPenalties()
    } catch (error) {
        penaltiesError.value = error.response?.data?.message || tAuto('Buchung konnte nicht storniert werden.')
    }
}

watch(() => ruleForm.value.calculation_type, (type) => {
    if (type !== 'threshold_fixed') {
        ruleForm.value.threshold_minutes = ''
    }
})

onMounted(loadPenalties)

const uploadImage = (field, event) => {
    const file = event.target.files?.[0] || null

    if (!file) {
        return
    }

    imageForm.logo = field === 'logo' ? file : null
    imageForm.cover_image = field === 'cover_image' ? file : null
    imageForm.post(route('auth.teams.images.update', props.teamProfile.id), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            imageForm.reset()
            if (logoInput.value) logoInput.value.value = null
            if (coverInput.value) coverInput.value.value = null
        },
    })
}
</script>

<template>
    <AppLayout :title="teamProfile.name">
        <Head :title="teamProfile.name" />

        <div class="mx-auto max-w-5xl space-y-6">
            <ClubWorkspaceNav
                active="structure"
                :description="tAuto('Teamprofil, Zugehörigkeit, Mitglieder und sichtbare Teambeiträge.')"
            />

            <section class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="relative h-40 bg-gradient-to-r from-buttonPrimary to-borderHover">
                    <img v-if="teamProfile.cover_image" :src="storageUrl(teamProfile.cover_image)" :alt="teamProfile.name" width="1200" height="320" loading="eager" decoding="async" fetchpriority="high" class="h-full w-full object-cover" />
                    <button
                        v-if="viewer.can_manage"
                        type="button"
                        class="absolute bottom-3 right-3 rounded-lg bg-card/90 px-3 py-2 text-sm font-semibold text-primary shadow hover:bg-card"
                        @click="coverInput?.click()"
                    >
                        <i class="las la-camera"></i> {{ tAuto('Titelbild') }}
                    </button>
                    <input ref="coverInput" type="file" accept="image/*" class="hidden" @change="uploadImage('cover_image', $event)" />
                </div>
                <div class="px-5 pb-5">
                    <div class="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="flex items-end gap-4">
                            <div class="group relative flex h-24 w-24 items-center justify-center overflow-hidden rounded-lg border-4 border-card bg-inputBg text-3xl font-bold text-primary">
                                <img v-if="teamProfile.logo" :src="storageUrl(teamProfile.logo)" :alt="teamProfile.name" width="96" height="96" loading="eager" decoding="async" class="h-full w-full object-cover" />
                                <span v-else>{{ initials(teamProfile.name) }}</span>
                                <button
                                    v-if="viewer.can_manage"
                                    type="button"
                                    class="absolute inset-0 flex items-center justify-center bg-black/50 text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100"
                                    @click="logoInput?.click()"
                                >
                                    <i class="las la-camera text-xl"></i>
                                </button>
                                <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="uploadImage('logo', $event)" />
                            </div>
                            <div class="pb-1">
                                <h1 class="text-2xl font-bold leading-tight text-primary">{{ teamProfile.name }}</h1>
                                <p v-if="teamProfile.club" class="mt-1 text-sm text-secondary">
                                    <span class="font-semibold text-secondary">{{ tAuto('Verein') }}:</span>
                                    <Link :href="route('auth.clubs.show', teamProfile.club.id)" class="ml-1 font-medium text-primary underline decoration-border hover:text-air-blue">
                                        {{ teamProfile.club.name }}
                                    </Link>
                                </p>
                                <p class="mt-1 text-sm text-secondary">{{ sportLabel(teamProfile.sport_type) }}</p>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <Link :href="route('auth.teams.index')" class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:bg-inputBg">
                                {{ tAuto('Teams') }}
                            </Link>
                            <button
                                v-if="!viewer.is_member && !viewer.has_pending_join_request"
                                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                @click="requestJoin"
                            >
                                {{ tAuto('Beitritt anfragen') }}
                            </button>
                            <span v-else-if="viewer.has_pending_join_request" class="rounded-lg border border-border px-4 py-2 text-sm text-secondary">
                                {{ tAuto('Anfrage gesendet') }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ teamProfile.users_count }}</div>
                    <div class="text-sm text-secondary">{{ tAuto('Mitglieder') }}</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ teamProfile.events_count }}</div>
                    <div class="text-sm text-secondary">{{ tAuto('Events') }}</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ teamProfile.files_count }}</div>
                    <div class="text-sm text-secondary">{{ tAuto('Dateien') }}</div>
                </div>
            </section>

            <TeamDailyHomeWidget :team-id="teamProfile.id" />

            <section v-if="attendanceStats" class="rounded-lg border border-border bg-card p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tAuto('Training') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ tAuto('Trainingsbeteiligung') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ tAuto('Gezählt werden abgeschlossene Trainingseinheiten. Zusage und Verspätet zählen als Teilnahme.') }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm sm:min-w-72">
                        <div class="rounded-lg border border-border bg-inputBg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Trainings') }}</p>
                            <p class="mt-1 text-2xl font-bold text-primary">{{ attendanceStats.trainings_total }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-3">
                            <p class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Mitglieder') }}</p>
                            <p class="mt-1 text-2xl font-bold text-primary">{{ attendanceStats.members_total }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-lg border border-border">
                    <div class="hidden grid-cols-[1.4fr_repeat(6,minmax(72px,1fr))] gap-3 bg-inputBg px-4 py-3 text-xs font-bold uppercase tracking-wide text-secondary lg:grid">
                        <span>{{ tAuto('Spieler') }}</span>
                        <span>{{ tAuto('Dabei') }}</span>
                        <span>{{ tAuto('Spät') }}</span>
                        <span>{{ tAuto('Vielleicht') }}</span>
                        <span>{{ tAuto('Absage') }}</span>
                        <span>{{ tAuto('Keine Antw.') }}</span>
                        <span>{{ tAuto('Quote') }}</span>
                    </div>
                    <div v-for="member in attendanceStats.members" :key="member.user_id" class="border-t border-border bg-card p-4 first:border-t-0 lg:grid lg:grid-cols-[1.4fr_repeat(6,minmax(72px,1fr))] lg:items-center lg:gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-primary">{{ member.name }}</p>
                            <p class="text-xs text-secondary">{{ member.attended }} {{ tAuto('von') }} {{ member.trainings_total }} {{ tAuto('Trainings') }}</p>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-sm lg:contents">
                            <span class="rounded-lg bg-success/10 px-2 py-1 font-semibold text-success lg:bg-transparent lg:p-0">{{ member.yes }}</span>
                            <span class="rounded-lg bg-air-blue/10 px-2 py-1 font-semibold text-air-blue lg:bg-transparent lg:p-0">{{ member.late }}</span>
                            <span class="rounded-lg bg-warning/10 px-2 py-1 font-semibold text-warning lg:bg-transparent lg:p-0">{{ member.maybe }}</span>
                            <span class="rounded-lg bg-error/10 px-2 py-1 font-semibold text-error lg:bg-transparent lg:p-0">{{ member.no }}</span>
                            <span class="rounded-lg bg-muted px-2 py-1 font-semibold text-secondary lg:bg-transparent lg:p-0">{{ member.no_response }}</span>
                            <span class="rounded-lg border border-border px-2 py-1 font-bold text-primary lg:border-0 lg:p-0">{{ member.attendance_rate }}%</span>
                        </div>
                    </div>
                    <div v-if="!attendanceStats.members.length" class="bg-card p-4 text-sm text-secondary">
                        {{ tAuto('Noch keine Mitglieder oder Trainingseinheiten vorhanden.') }}
                    </div>
                </div>
            </section>

            <section id="team-cash-box" class="min-w-0 scroll-mt-24 overflow-hidden rounded-lg border border-border bg-card p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tAuto('Teamkasse') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ tAuto('Strafkatalog & Kasse') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ tAuto('Individuelle Regeln für') }} {{ teamProfile.name }} {{ tAuto('und offene Strafbuchungen.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-inputBg disabled:opacity-60"
                        :disabled="penaltiesLoading"
                        @click="loadPenalties"
                    >
                        <i class="las la-sync"></i> {{ tAuto('Aktualisieren') }}
                    </button>
                </div>

                <div v-if="penaltiesError" class="mt-4 rounded-lg border border-error/40 bg-error/10 px-4 py-3 text-sm font-semibold text-error">
                    {{ penaltiesError }}
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-border bg-inputBg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Offen') }}</p>
                        <p class="mt-1 text-2xl font-bold text-primary">{{ formatMoney(penalties.summary?.open_amount) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-inputBg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Bezahlt') }}</p>
                        <p class="mt-1 text-2xl font-bold text-primary">{{ formatMoney(penalties.summary?.paid_amount) }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-inputBg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ tAuto('Offene Fälle') }}</p>
                        <p class="mt-1 text-2xl font-bold text-primary">{{ penalties.summary?.open_count || 0 }}</p>
                    </div>
                </div>

                <div class="mt-6 grid min-w-0 gap-5 xl:grid-cols-[420px_1fr]">
                    <div class="min-w-0 space-y-4">
                        <form v-if="canManagePenalties" class="rounded-lg border border-border bg-inputBg p-4" @submit.prevent="submitRule">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-bold text-primary">{{ editingRuleId ? tAuto('Regel bearbeiten') : tAuto('Neue Regel') }}</h3>
                                <button v-if="editingRuleId" type="button" class="text-sm font-semibold text-secondary hover:text-primary" @click="resetRuleForm">
                                    {{ tAuto('Abbrechen') }}
                                </button>
                            </div>

                            <div class="mt-4 grid gap-3">
                                <label class="text-sm font-semibold text-primary">
                                    {{ tAuto('Titel') }}
                                    <input v-model="ruleForm.title" required class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" :placeholder="tAuto('Zu spät zum Training')">
                                </label>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="text-sm font-semibold text-primary">
                                        {{ tAuto('Auslöser') }}
                                        <select v-model="ruleForm.trigger" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary">
                                            <option value="late">{{ tAuto('Zu spät') }}</option>
                                            <option value="absence">{{ tAuto('Fehlt') }}</option>
                                            <option value="forgotten_equipment">{{ tAuto('Ausrüstung vergessen') }}</option>
                                            <option value="custom">{{ tAuto('Individuell') }}</option>
                                        </select>
                                    </label>
                                    <label class="text-sm font-semibold text-primary">
                                        {{ tAuto('Berechnung') }}
                                        <select v-model="ruleForm.calculation_type" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary">
                                            <option value="fixed">{{ tAuto('Fester Betrag') }}</option>
                                            <option value="per_minute">{{ tAuto('Pro Minute') }}</option>
                                            <option value="threshold_fixed">{{ tAuto('Ab Minuten-Grenze') }}</option>
                                            <option value="item">{{ tAuto('Sachstrafe') }}</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="grid gap-3" :class="ruleNeedsMinuteThreshold ? 'sm:grid-cols-3' : 'sm:grid-cols-2'">
                                    <label class="text-sm font-semibold text-primary">
                                        {{ tAuto('Betrag') }}
                                        <input v-model="ruleForm.amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="1.00">
                                    </label>
                                    <label v-if="ruleNeedsMinuteThreshold" class="text-sm font-semibold text-primary">
                                        {{ tAuto('Grenze Min.') }}
                                        <input v-model="ruleForm.threshold_minutes" type="number" min="0" step="1" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="10">
                                    </label>
                                    <label class="text-sm font-semibold text-primary">
                                        {{ tAuto('Max.') }}
                                        <input v-model="ruleForm.max_amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="optional">
                                    </label>
                                </div>
                                <label class="text-sm font-semibold text-primary">
                                    {{ tAuto('Sachstrafe / Einheit') }}
                                    <input v-model="ruleForm.unit_label" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="Kiste, Kuchen, Teamdienst">
                                </label>
                                <label class="text-sm font-semibold text-primary">
                                    {{ tAuto('Beschreibung') }}
                                    <textarea v-model="ruleForm.description" rows="2" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="Optionaler Hinweis für das Team"></textarea>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-primary">
                                    <input v-model="ruleForm.is_active" type="checkbox" class="peer sr-only">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-md border border-border bg-card text-xs font-black text-transparent transition peer-checked:border-buttonPrimary peer-checked:bg-buttonPrimary peer-checked:text-buttonTextPrimary">
                                        ✓
                                    </span>
                                    {{ tAuto('Aktiv') }}
                                </label>
                            </div>

                            <button type="submit" class="mt-4 w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                                {{ editingRuleId ? tAuto('Regel speichern') : tAuto('Regel anlegen') }}
                            </button>
                        </form>

                        <form v-if="canManagePenalties" class="rounded-lg border border-border bg-inputBg p-4" @submit.prevent="submitFee">
                            <h3 class="font-bold text-primary">{{ tAuto('Strafe buchen') }}</h3>
                            <div class="mt-4 grid gap-3">
                                <label class="text-sm font-semibold text-primary">
                                    {{ tAuto('Mitglied') }}
                                    <select v-model="feeForm.user_id" required class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary">
                                        <option value="">{{ tAuto('Auswählen') }}</option>
                                        <option v-for="member in teamProfile.members" :key="member.id" :value="member.id">
                                            {{ member.name }}
                                        </option>
                                    </select>
                                </label>
                                <label class="text-sm font-semibold text-primary">
                                    {{ tAuto('Regel') }}
                                    <select v-model="feeForm.penalty_rule_id" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary">
                                        <option value="">{{ tAuto('Manueller Betrag') }}</option>
                                        <option v-for="rule in activePenaltyRules" :key="rule.id" :value="rule.id">
                                            {{ penaltyRuleLabel(rule) }}
                                        </option>
                                    </select>
                                </label>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <label class="text-sm font-semibold text-primary">
                                        {{ tAuto('Minuten') }}
                                        <input v-model="feeForm.minutes" type="number" min="0" step="1" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="0">
                                    </label>
                                    <label class="text-sm font-semibold text-primary">
                                        {{ tAuto('Betrag') }}
                                        <input v-model="feeForm.amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" :placeholder="selectedPenaltyRule ? 'Automatisch' : '5.00'">
                                    </label>
                                    <label class="text-sm font-semibold text-primary">
                                        {{ tAuto('Fällig') }}
                                        <input v-model="feeForm.due_date" type="date" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary">
                                    </label>
                                </div>
                                <label class="text-sm font-semibold text-primary">
                                    {{ tAuto('Notiz') }}
                                    <input v-model="feeForm.note" class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="z.B. Training Dienstag">
                                </label>
                            </div>
                            <button type="submit" class="mt-4 w-full rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                                {{ tAuto('Strafe buchen') }}
                            </button>
                        </form>
                    </div>

                    <div class="min-w-0 space-y-4">
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <h3 class="font-bold text-primary">{{ tAuto('Aktive Regeln') }}</h3>
                            <div class="mt-4 divide-y divide-border overflow-hidden rounded-lg border border-border">
                                <div v-for="rule in activePenaltyRules" :key="rule.id" class="bg-card p-3">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="font-semibold text-primary">{{ rule.title }}</p>
                                            <p class="mt-1 text-xs text-secondary">
                                                {{ triggerLabels[rule.trigger] || rule.trigger }} · {{ calculationLabels[rule.calculation_type] || rule.calculation_type }}
                                                <span v-if="rule.calculation_type !== 'item'"> · {{ formatMoney(rule.amount, rule.currency) }}</span>
                                                <span v-if="rule.threshold_minutes"> · {{ tAuto('ab') }} {{ rule.threshold_minutes }} {{ tAuto('Min.') }}</span>
                                                <span v-if="rule.max_amount"> · {{ tAuto('max.') }} {{ formatMoney(rule.max_amount, rule.currency) }}</span>
                                                <span v-if="rule.unit_label"> · {{ rule.unit_label }}</span>
                                                <span v-if="rule.is_saving"> · {{ tAuto('wird gespeichert…') }}</span>
                                            </p>
                                            <p v-if="rule.description" class="mt-2 text-sm text-secondary">{{ rule.description }}</p>
                                        </div>
                                        <div v-if="canManagePenalties" class="flex gap-2">
                                            <button type="button" class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-inputBg" @click="editRule(rule)">
                                                {{ tAuto('Bearbeiten') }}
                                            </button>
                                            <button type="button" class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-secondary hover:text-error" @click="deactivateRule(rule)">
                                                {{ tAuto('Deaktivieren') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="!activePenaltyRules.length" class="bg-card p-4 text-sm text-secondary">
                                    {{ tAuto('Noch keine aktiven Regeln.') }}
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <h3 class="font-bold text-primary">{{ tAuto('Buchungen') }}</h3>
                            <label v-if="canManagePenalties" class="mt-3 block text-sm text-secondary">Zahlart
                                <select v-model="feePaymentAccount" class="ml-2 rounded-lg border-border bg-inputBg text-primary" @change="feeMoneyAccountId = null"><option value="cash">Bar</option><option value="bank">Bank</option></select>
                            </label>
                            <label v-if="canManagePenalties && penalties.money_accounts?.length" class="mt-2 block text-sm text-secondary">Teamkonto
                                <select v-model="feeMoneyAccountId" class="ml-2 max-w-full rounded-lg border-border bg-inputBg text-primary"><option :value="null">Standard</option><option v-for="account in penalties.money_accounts.filter(a => a.type === feePaymentAccount)" :key="account.id" :value="account.id">{{ account.name }}</option></select>
                            </label>
                            <div class="mt-4 overflow-hidden rounded-lg border border-border">
                                <div v-for="fee in penalties.fees" :key="fee.id" class="border-b border-border bg-card p-3 last:border-b-0">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="font-semibold text-primary">{{ fee.member?.name || tAuto('Mitglied') }}</p>
                                            <p class="mt-1 text-sm text-secondary">
                                                {{ fee.rule?.title || fee.note || tAuto('Strafe') }} · {{ formatMoney(fee.amount, fee.currency) }}
                                            </p>
                                            <p class="mt-1 text-xs text-secondary">
                                                {{ tAuto('Status:') }} {{ feeStatusLabel(fee.status) }}
                                                <span v-if="fee.due_date"> · {{ tAuto('fällig') }} {{ formatDate(fee.due_date) }}</span>
                                                <span v-if="fee.paid_at"> · {{ tAuto('bezahlt') }} {{ formatDate(fee.paid_at) }}</span>
                                            </p>
                                        </div>
                                        <div v-if="canManagePenalties && fee.status === 'open'" class="flex gap-2">
                                            <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-1 text-xs font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover" @click="markFeePaid(fee)">
                                                {{ tAuto('Bezahlt') }}
                                            </button>
                                            <button type="button" class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-secondary hover:text-error" @click="cancelFee(fee)">
                                                {{ tAuto('Storno') }}
                                            </button>
                                        </div>
                                        <button v-if="canManagePenalties && fee.status === 'paid' && fee.club_finance_entry_id" type="button" class="text-sm text-secondary" @click="refundFee(fee)">Erstattung erfassen</button>
                                    </div>
                                </div>
                                <div v-if="!penalties.fees.length" class="bg-card p-4 text-sm text-secondary">
                                    {{ tAuto('Noch keine Buchungen.') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                <section class="space-y-4">
                    <article v-for="post in posts" :key="post.id" class="rounded-lg border border-border bg-card p-4">
                        <div class="flex items-center gap-3">
                            <img :src="post.user.profile_photo_url" :alt="post.user.name" width="40" height="40" loading="lazy" decoding="async" class="h-10 w-10 rounded-full object-cover">
                            <div>
                                <Link :href="route('auth.users.show', post.user.id)" class="text-sm font-semibold text-primary hover:underline">
                                    {{ post.user.name }}
                                </Link>
                                <p class="text-xs text-secondary">{{ formatDate(post.created_at) }}</p>
                            </div>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ post.content }}</p>
                        <div class="mt-3 flex gap-4 text-xs text-secondary">
                            <span>{{ post.likes_count }} {{ tAuto('Likes') }}</span>
                            <span>{{ post.comments_count }} {{ tAuto('Kommentare') }}</span>
                        </div>
                    </article>
                    <div v-if="!posts.length" class="rounded-lg border border-border bg-card p-8 text-center text-sm text-secondary">
                        {{ tAuto('Noch keine sichtbaren Beiträge.') }}
                    </div>
                </section>

                <aside id="team-members" class="scroll-mt-24 rounded-lg border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ tAuto('Mitglieder') }}</h2>
                    <div class="mt-4 space-y-3">
                        <Link v-for="member in teamProfile.members" :key="member.id" :href="route('auth.users.show', member.id)" class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                            <img :src="member.profile_photo_url" :alt="member.name" width="36" height="36" loading="lazy" decoding="async" class="h-9 w-9 rounded-full object-cover">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-primary">{{ member.name }}</p>
                                <p class="text-xs text-secondary">{{ tAuto(member.pivot?.role || 'Mitglied') }}</p>
                            </div>
                        </Link>
                        <AppEmptyState
                            v-if="!teamProfile.members.length"
                            :title="tAuto('Noch keine Mitglieder')"
                            :description="tAuto('Teammitglieder erscheinen hier, sobald sie dem Team zugeordnet wurden.')"
                            compact
                        >
                            <template #icon>
                                <i class="las la-user-friends text-xl" aria-hidden="true"></i>
                            </template>
                        </AppEmptyState>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

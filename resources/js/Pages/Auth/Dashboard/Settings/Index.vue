<script setup>
import CountrySelect from "@/Components/CountrySelect.vue"
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import { useTheme } from '@/services/useTheme'
import LanguageDropdown from '@/Components/LanguageDropdown.vue'
import DeleteConfirmModal from '@/Components/Auth/DeleteConfirmModal.vue'
import { useI18n } from 'vue-i18n'

// Jetstream Components
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue'
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue'
import SectionBorder from '@/Components/SectionBorder.vue'
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue'
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue'
import MultiSelectDropdown from '@/Components/Settings/MultiSelectDropdown.vue'

defineOptions({ layout: AppLayout })
const { t, te, locale } = useI18n()
const page = usePage()

const localeCode = computed(() => ({
    de: 'de-DE',
    en: 'en-US',
    fr: 'fr-FR',
    ar: 'ar-SA',
}[locale.value] || 'de-DE'))

// Props
const props = defineProps({
    activeSettingsTab: {
        type: String,
        default: 'profile',
    },
    profileAddress: {
        type: Object,
        default: () => ({}),
    },
    privacySettings: {
        type: Object,
        default: () => ({}),
    },
    notificationPreferences: {
        type: Object,
        default: () => ({ channels: {}, quiet_time: 'late' }),
    },
    navigationModules: {
        type: Object,
        default: () => ({ available: [], enabled: [], definitions: [] }),
    },
    eventDefaults: {
        type: Object,
        default: () => ({ radius_km: null, sport_ids: [], filters: {} }),
    },
    sports: {
        type: Array,
        default: () => [],
    },
    sportProfiles: {
        type: Array,
        default: () => [],
    },
    confirmsTwoFactorAuthentication: Boolean,
    billingHistory: {
        type: Object,
        default: () => ({ invoices: [], payments: [], subscription_invoices: [], airmius_bank: {} }),
    },
    currentUserSubscriptions: {
        type: Array,
        default: () => [],
    },
    socialAccounts: {
        type: Array,
        default: () => [],
    },
    sportIntegrations: {
        type: Object,
        default: () => ({ providers: {}, accounts: [], activities: [] }),
    },
    privacyProviders: {
        type: Object,
        default: () => ({ summary: { total: 0, login: 0, sport: 0 }, items: [] }),
    },
    userRoles: {
        type: Array,
        default: () => [],
    },
    roleApplications: {
        type: Array,
        default: () => [],
    },
    activities: {
        type: Array,
        default: () => [],
    },
    sessions: {
        type: Array,
        default: () => [], // FIX gegen undefined
    },
})

const cloneSportIntegrations = (value = {}) => ({
    providers: { ...(value.providers || {}) },
    accounts: (value.accounts || []).map((account) => ({ ...account })),
    activities: (value.activities || []).map((activity) => ({ ...activity })),
})
const sportIntegrationState = ref(cloneSportIntegrations(props.sportIntegrations))
watch(() => props.sportIntegrations, (value) => {
    sportIntegrationState.value = cloneSportIntegrations(value)
}, { deep: true })

// Tabs
const settingsTabs = [
    'profile',
    'address',
    'billing',
    'roles',
    'areas',
    'activities',
    'integrations',
    'design',
    'language',
    'notifications',
    'privacy',
    'sport-profile',
    'security',
]
const settingsTabProps = Object.freeze({
    profile: [],
    address: ['sports'],
    billing: ['billingHistory', 'currentUserSubscriptions'],
    roles: ['sports', 'userRoles', 'roleApplications'],
    areas: [],
    activities: ['activities'],
    integrations: ['socialAccounts', 'sportIntegrations'],
    design: [],
    language: [],
    notifications: [],
    privacy: ['privacyProviders'],
    'sport-profile': ['sports', 'sportProfiles'],
    security: [],
})
const initialTab = settingsTabs.includes(props.activeSettingsTab) ? props.activeSettingsTab : 'profile'
const activeTab = ref(initialTab)
const pendingTab = ref(null)
const failedTab = ref(null)
const loadedTabs = ref(new Set([initialTab]))
const loadedTabProps = ref(new Set(settingsTabProps[initialTab] || []))
const settingsTabUrl = (tab) => {
    const url = new URL(window.location.href)

    if (tab === 'profile') {
        url.searchParams.delete('tab')
    } else {
        url.searchParams.set('tab', tab)
    }

    return `${url.pathname}${url.search}${url.hash}`
}
const setActiveTab = (tab) => {
    if (!settingsTabs.includes(tab) || pendingTab.value) return

    failedTab.value = null

    const missingProps = (settingsTabProps[tab] || [])
        .filter((prop) => !loadedTabProps.value.has(prop))

    if (loadedTabs.value.has(tab) || missingProps.length === 0) {
        activeTab.value = tab
        loadedTabs.value = new Set([...loadedTabs.value, tab])
        window.history.replaceState({}, '', settingsTabUrl(tab))
        return
    }

    pendingTab.value = tab
    let succeeded = false

    router.get(settingsTabUrl(tab), {}, {
        only: ['activeSettingsTab', ...missingProps],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
            succeeded = true
            activeTab.value = tab
            loadedTabs.value = new Set([...loadedTabs.value, tab])
            loadedTabProps.value = new Set([...loadedTabProps.value, ...missingProps])
        },
        onError: () => {
            failedTab.value = tab
        },
        onFinish: () => {
            if (!succeeded && failedTab.value === null) failedTab.value = tab
            pendingTab.value = null
        },
    })
}
const retryFailedTab = () => {
    const tab = failedTab.value

    failedTab.value = null
    if (tab) setActiveTab(tab)
}
const connectedPrivacyProviders = computed(() => props.privacyProviders?.items || [])
const privacyProviderName = (value) => String(value || '')
    .split(/[_-]/)
    .filter(Boolean)
    .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
    .join(' ')
const trainerApplication = computed(() => props.roleApplications.find((application) => application.type === 'trainer'))
const roleApplicationForm = useForm({
    type: 'trainer',
    message: '',
    application_data: {
        sports: '',
        sport_ids: [],
        sport_skill_ids: [],
        specialties: '',
        experience: '',
        certification: '',
    },
})
const trainerSportIds = ref([])
const trainerSkillIds = ref([])
const trainerApplicationSkillOptions = computed(() => {
    const selectedSportIds = new Set(trainerSportIds.value.map((id) => String(id)))
    const options = new Map()

    props.sports
        .filter((sport) => !selectedSportIds.size || selectedSportIds.has(String(sport.id)))
        .flatMap((sport) => (sport.skills || []).map((skill) => ({
            ...skill,
            sport_name: sport.name,
        })))
        .forEach((skill) => {
            const key = String(skill.name || skill.key || skill.id).toLowerCase()
            if (!options.has(key)) options.set(key, skill)
        })

    return Array.from(options.values())
})
const allTrainerApplicationSkillOptions = computed(() => {
    const selectedSportIds = new Set()
    const options = new Map()

    props.sports
        .flatMap((sport) => (sport.skills || []).map((skill) => ({
            ...skill,
            sport_name: sport.name,
        })))
        .forEach((skill) => {
            const key = String(skill.name || skill.key || skill.id).toLowerCase()
            if (!options.has(key)) options.set(key, skill)
        })

    return Array.from(options.values())
})
const trainerSelectedSportsLabel = computed(() => props.sports
    .filter((sport) => trainerSportIds.value.map(String).includes(String(sport.id)))
    .map((sport) => sport.name)
    .join(', '))
const trainerSelectedSkillsLabel = computed(() => allTrainerApplicationSkillOptions.value
    .filter((skill) => trainerSkillIds.value.map(String).includes(String(skill.id)))
    .map((skill) => skill.name)
    .join(', '))
const trainerApplicationNames = (value) => Array.isArray(value)
    ? value.map((item) => String(item).trim()).filter(Boolean)
    : String(value || '').split(',').map((item) => item.trim()).filter(Boolean)
const trainerApplicationIds = (value) => Array.isArray(value)
    ? value.map(Number).filter((value) => Number.isInteger(value) && value > 0)
    : []
const syncTrainerApplicationSelections = () => {
    roleApplicationForm.application_data.sport_ids = [...trainerSportIds.value]
    roleApplicationForm.application_data.sport_skill_ids = [...trainerSkillIds.value]
    roleApplicationForm.application_data.sports = trainerSelectedSportsLabel.value
    roleApplicationForm.application_data.specialties = trainerSelectedSkillsLabel.value
}
const initializeTrainerApplicationSelections = (application) => {
    const data = application?.application_data || {}
    trainerSportIds.value = trainerApplicationIds(data.sport_ids)
    trainerSkillIds.value = trainerApplicationIds(data.sport_skill_ids)

    if (!trainerSportIds.value.length) {
        const legacySports = trainerApplicationNames(data.sports)
        trainerSportIds.value = props.sports
            .filter((sport) => legacySports.some((name) => name.toLowerCase() === String(sport.name).toLowerCase()))
            .map((sport) => Number(sport.id))
    }

    if (!trainerSkillIds.value.length) {
        const legacySkills = trainerApplicationNames(data.specialties).map((name) => name.toLowerCase())
        trainerSkillIds.value = allTrainerApplicationSkillOptions.value
            .filter((skill) => legacySkills.includes(String(skill.name).toLowerCase()))
            .map((skill) => Number(skill.id))
    }

    syncTrainerApplicationSelections()
}
watch(() => trainerApplication.value?.application_data, (applicationData) => {
    initializeTrainerApplicationSelections({ application_data: applicationData })
}, { immediate: true, deep: true })
watch([trainerSportIds, trainerSkillIds], () => {
    if (trainerSportIds.value.length) {
        const allowed = new Set(trainerApplicationSkillOptions.value.map((skill) => String(skill.id)))
        const next = trainerSkillIds.value.filter((id) => allowed.has(String(id)))
        if (next.length !== trainerSkillIds.value.length) {
            trainerSkillIds.value = next
            return
        }
    }

    syncTrainerApplicationSelections()
}, { deep: true })
const submitTrainerApplication = () => {
    if (!trainerSportIds.value.length || !trainerSkillIds.value.length || !roleApplicationForm.application_data.experience.trim()) {
        if (!trainerSportIds.value.length) {
            roleApplicationForm.setError('application_data.sport_ids', settingsText('roles.sports_required', 'Bitte wähle mindestens eine Sportart aus.'))
        }
        if (!trainerSkillIds.value.length) {
            roleApplicationForm.setError('application_data.sport_skill_ids', settingsText('roles.specialties_required', 'Bitte wähle mindestens einen Schwerpunkt aus.'))
        }
        if (!roleApplicationForm.application_data.experience.trim()) {
            roleApplicationForm.setError('application_data.experience', settingsText('roles.experience_required', 'Bitte gib deine Erfahrung an.'))
        }
        return
    }

    roleApplicationForm.post(route('auth.role-applications.store'), {
        preserveScroll: true,
        onFinish: () => roleApplicationForm.reset('message', 'application_data'),
    })
}
const openPaymentModal = ref({
    show: false,
    action: null,
    invoice: null,
})
const subscriptionCancelModal = ref({
    show: false,
    subscription: null,
})
const disconnectIntegrationModal = ref({
    show: false,
    account: null,
})
const sportActivityDeleteModal = ref({
    show: false,
    activity: null,
    mode: null,
})
const sportActivityEditModal = ref({
    show: false,
    activity: null,
})
const sportActivityEditForm = useForm({
    title: '',
})
const manualActivityImageInput = ref(null)
const manualActivityForm = useForm({
    title: '',
    activity_type: 'Training',
    started_at: '',
    duration_minutes: '',
    distance_km: '',
    calories: '',
    image: null,
})
const manualActivityTypeOptions = ['Training', 'Laufen', 'Radfahren', 'Schwimmen', 'Fußball', 'Fitness', 'Krafttraining', 'Yoga', 'Gehen', 'Sonstiges']
const integrationNotice = ref(null)
const busyIntegrationAccounts = ref(new Set())
const busyIntegrationProviders = ref(new Set())
const busySportActivities = ref(new Set())
const manualActivitySaving = ref(false)
const bankTransferModal = ref({
    show: false,
    type: null,
    invoice: null,
})
const sportProfileNotice = ref(null)
const savingSportProfileId = ref(null)
const selectedSportProfileId = ref('')
const sportProfileSearch = ref('')
const sportProfilePickerOpen = ref(false)
const selectedSportProfileIds = ref([])
const activeSportProfileId = ref('')
const sportProfileRemoveModal = ref({
    show: false,
    profile: null,
})
const sportProfileForms = reactive({})

watch(() => props.sportProfiles, (profiles) => {
    const selected = profiles
        .filter((profile) => profile.has_profile)
        .map((profile) => Number(profile.sport.id))

    selectedSportProfileIds.value = selected
    if (!selected.includes(Number(activeSportProfileId.value))) {
        activeSportProfileId.value = selected[0] || ''
    }

    profiles.forEach((profile) => {
        sportProfileForms[profile.sport.id] = {
            status: profile.status || 'active',
            experience_level: profile.experience_level || 'beginner',
            visibility: profile.visibility || 'private',
            metrics: Object.fromEntries((profile.fields || []).map((field) => [field.key, profile.metrics?.[field.key] ?? ''])),
            metric_visibility: Object.fromEntries((profile.fields || []).map((field) => [field.key, profile.metric_visibility?.[field.key] || field.default_visibility || 'private'])),
            unknown_metrics: Object.fromEntries((profile.fields || []).map((field) => [field.key, (profile.metrics?._unknown_fields || []).includes(field.key)])),
        }
    })
}, { immediate: true })

const tabClass = (tab) =>
    `px-4 py-2 rounded-lg text-sm font-semibold transition ${
        activeTab.value === tab
            ? 'bg-buttonPrimary text-buttonTextPrimary'
            : 'bg-muted text-secondary'
    }`

// Theme
const { setTheme } = useTheme()
const addressNotice = ref(null)
const privacyNotice = ref(null)
const notificationNotice = ref(null)
const currentTheme = ref(page.props.auth?.user?.theme || localStorage.getItem('theme') || 'air')
const navigationNotice = ref(null)
const navigationModuleOptions = computed(() => props.navigationModules.definitions || [])
const navigationModuleSelected = (key) => (form.enabled_navigation_modules || []).includes(key)
const toggleNavigationModule = (key) => {
    const selected = [...(form.enabled_navigation_modules || [])]

    form.enabled_navigation_modules = selected.includes(key)
        ? selected.filter((value) => value !== key)
        : [...selected, key]
}
const navigationModuleLabel = (key, fallback) => settingsText(`modules.labels.${key}`, fallback)
const navigationModuleDescription = (key, fallback) => settingsText(`modules.descriptions.${key}`, fallback)
const themeOptions = [
    { key: 'air', label: 'Air', descriptionKey: 'air', description: 'Klar, leicht und fokussiert.', colors: ['#0ea5e9', '#10b981', '#f7fbff'] },
    { key: 'dark', label: 'Dark', descriptionKey: 'dark', description: 'Konzentriert für späte Sessions.', colors: ['#0c1016', '#60a5fa', '#34d399'] },
    { key: 'womanly', label: 'Womanly', descriptionKey: 'womanly', description: 'Warm, stark und elegant.', colors: ['#be185d', '#fde8f2', '#0f9f6e'] },
    { key: 'champion', label: 'Champion', descriptionKey: 'champion', description: 'Goldene Energie für Gewinner.', colors: ['#b45309', '#f59e0b', '#fffaf0'] },
    { key: 'sprint', label: 'Sprint', descriptionKey: 'sprint', description: 'Frisch, schnell und aktiv.', colors: ['#059669', '#10b981', '#f5fff9'] },
    { key: 'arena', label: 'Arena', descriptionKey: 'arena', description: 'Ruhig, robust und professionell.', colors: ['#334155', '#64748b', '#f8fafc'] },
    { key: 'pulse', label: 'Pulse', descriptionKey: 'pulse', description: 'Dynamisch und motivierend.', colors: ['#ea580c', '#f97316', '#fff7ed'] },
    { key: 'trail', label: 'Trail', descriptionKey: 'trail', description: 'Natürlich, ausdauernd und bodenstaendig.', colors: ['#4d7c0f', '#65a30d', '#f6f8f2'] },
    { key: 'bazaar', label: 'Bazaar Rush', descriptionKey: 'bazaar', description: 'Lebendig, verkaufsstark und frisch für Marketplace-Flows.', colors: ['#00a8c6', '#ff8a00', '#ffffff'] },
]

// Form
const form = useForm({
    theme: currentTheme.value,
    country: props.profileAddress.country || 'DE',
    street: props.profileAddress.street || '',
    house_number: props.profileAddress.house_number || '',
    postal_code: props.profileAddress.postal_code || '',
    city: props.profileAddress.city || '',
    state: props.profileAddress.state || '',
    event_radius_km: props.eventDefaults.radius_km || 20,
    event_default_sport_ids: props.eventDefaults.sport_ids || [],
    default_post_visibility: props.privacySettings.default_post_visibility || 'public',
    profile_visibility: props.privacySettings.profile_visibility || 'public',
    direct_message_privacy: props.privacySettings.direct_message_privacy || 'everyone',
    friend_request_privacy: props.privacySettings.friend_request_privacy || 'everyone',
    notification_channels: {
        push: false,
        email: true,
        chat: true,
        club: true,
        billing: true,
        marketing: false,
        ...(props.notificationPreferences.channels || {}),
    },
    notification_quiet_time: props.notificationPreferences.quiet_time || 'late',
    ads_personalization_consent: Boolean(props.privacySettings.ads_personalization_consent),
    ads_measurement_consent: Boolean(props.privacySettings.ads_measurement_consent),
    product_analytics_consent: Boolean(props.privacySettings.product_analytics_consent),
    enabled_navigation_modules: props.navigationModules.enabled || [],
})

// Actions
const saveAddress = (showFeedback = true) => {
    if (showFeedback) {
        addressNotice.value = null
    }

    form.put(route('auth.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            if (showFeedback) {
                addressNotice.value = {
                    type: 'success',
                    message: settingsText('address.saved', 'Adresse wurde erfolgreich gespeichert.'),
                }
            }
        },
        onError: () => {
            if (showFeedback) {
                addressNotice.value = {
                    type: 'error',
                    message: settingsText('address.save_failed', 'Adresse konnte nicht gespeichert werden. Bitte prüfe die Eingaben.'),
                }
            }
        },
    })
}

const saveNavigationModules = () => {
    navigationNotice.value = null

    form.put(route('auth.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            navigationNotice.value = {
                type: 'success',
                message: settingsText('modules.saved', 'Bereiche wurden gespeichert.'),
            }
        },
        onError: () => {
            navigationNotice.value = {
                type: 'error',
                message: settingsText('modules.save_failed', 'Bereiche konnten nicht gespeichert werden.'),
            }
        },
    })
}

const saveNotificationPreferences = () => {
    notificationNotice.value = null

    form.put(route('auth.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            notificationNotice.value = {
                type: 'success',
                message: settingsText('notification_preferences.saved', 'Benachrichtigungseinstellungen wurden gespeichert.'),
            }
        },
        onError: () => {
            notificationNotice.value = {
                type: 'error',
                message: settingsText('notification_preferences.save_failed', 'Benachrichtigungseinstellungen konnten nicht gespeichert werden.'),
            }
        },
    })
}

const withdrawPrivacyConsents = () => {
    privacyNotice.value = null

    router.post(route('auth.settings.privacy.withdraw-consents'), {
        consents: ['all'],
    }, {
        preserveScroll: true,
        onSuccess: () => {
            form.ads_personalization_consent = false
            form.ads_measurement_consent = false
            form.product_analytics_consent = false
            privacyNotice.value = {
                type: 'success',
                message: settingsText('privacy.withdraw_success', 'Einwilligungen wurden widerrufen.'),
            }
        },
        onError: () => {
            privacyNotice.value = {
                type: 'error',
                message: settingsText('privacy.withdraw_failed', 'Einwilligungen konnten nicht widerrufen werden.'),
            }
        },
    })
}

const updateTheme = (theme) => {
    setTheme(theme)
    currentTheme.value = theme
    form.theme = theme
    saveAddress(false)
}

const toggleDefaultSport = (sportId) => {
    const id = Number(sportId)
    const selected = (form.event_default_sport_ids || []).map(Number)

    form.event_default_sport_ids = selected.includes(id)
        ? selected.filter((value) => value !== id)
        : [...selected, id]
}

const i18nText = (key, fallback, params = {}) => (te(key) ? t(key, params) : fallback)
const settingsText = (key, fallback, params = {}) => i18nText(`settings.${key}`, fallback, params)
const sportProfileText = (key, fallback, params = {}) => i18nText(`settings.sport_profile.${key}`, fallback, params)
const notificationChannelOptions = computed(() => [
    { key: 'push', icon: 'las la-mobile-alt' },
    { key: 'email', icon: 'las la-envelope' },
    { key: 'chat', icon: 'las la-comment-dots' },
    { key: 'club', icon: 'las la-users' },
    { key: 'billing', icon: 'las la-receipt' },
    { key: 'marketing', icon: 'las la-bullhorn' },
])
const notificationQuietOptions = ['none', 'late', 'early', 'weekend']
const themeDescription = (themeOption) => settingsText(`design.themes.${themeOption.descriptionKey}`, themeOption.description)
const roleName = (role) => settingsText(`roles.names.${role.name}`, role.name)
const roleDescription = (role) => settingsText(
    `roles.descriptions.${role.name}`,
    role.description || settingsText('roles.no_description', 'Keine Beschreibung vorhanden.'),
)
const sportStatusLabel = (value) => sportProfileText(`statuses.${value}`, value)
const sportExperienceLabel = (value) => sportProfileText(`levels.${value}`, value)
const metricVisibilityLabel = (value) => sportProfileText(`visibility.${value}`, value)
const sportProfileGroupLabel = (value) => sportProfileText(`groups.${value}`, value)
const sportMetricLabel = (field) => sportProfileText(`fields.${field.key}`, field.label)
const sportMetricPlaceholder = (field) => sportProfileText(`placeholders.${field.key}`, field.help || '')
const trainingDayOptions = [
    { key: 'monday', short: 'Mo', long: 'Montag' },
    { key: 'tuesday', short: 'Di', long: 'Dienstag' },
    { key: 'wednesday', short: 'Mi', long: 'Mittwoch' },
    { key: 'thursday', short: 'Do', long: 'Donnerstag' },
    { key: 'friday', short: 'Fr', long: 'Freitag' },
    { key: 'saturday', short: 'Sa', long: 'Samstag' },
    { key: 'sunday', short: 'So', long: 'Sonntag' },
]
const trainingDayAliases = {
    monday: ['monday', 'mon', 'mo', 'montag', 'lundi', 'lun', 'الاثنين'],
    tuesday: ['tuesday', 'tue', 'di', 'dienstag', 'mardi', 'mar', 'الثلاثاء'],
    wednesday: ['wednesday', 'wed', 'mi', 'mittwoch', 'mercredi', 'mer', 'الأربعاء', 'الاربعاء'],
    thursday: ['thursday', 'thu', 'do', 'donnerstag', 'jeudi', 'jeu', 'الخميس'],
    friday: ['friday', 'fri', 'fr', 'freitag', 'vendredi', 'ven', 'الجمعة'],
    saturday: ['saturday', 'sat', 'sa', 'samstag', 'samedi', 'sam', 'السبت'],
    sunday: ['sunday', 'sun', 'so', 'sonntag', 'dimanche', 'dim', 'الأحد', 'الاحد'],
}
const weekendAliases = ['weekend', 'weekends', 'wochenende', 'week-end', 'عطلة', 'نهاية']

const todayDate = new Date().toISOString().slice(0, 10)
const isExperienceDateField = (field) => field.type === 'date' || field.key === 'experience'
const isTrainingDaysField = (field) => ['available_days', 'training_days'].includes(field.key)
const runningBestTimeKeys = [
    'run_best_100m_time',
    'run_best_200m_time',
    'run_best_400m_time',
    'run_best_800m_time',
    'run_best_1500m_time',
    'run_best_3000m_time',
    'best_5k_time',
    'best_10k_time',
    'best_half_marathon_time',
    'best_marathon_time',
]
const strengthPerformanceKeys = [
    'bodyweight_kg',
    'bench_press_1rm_kg',
    'squat_1rm_kg',
    'deadlift_1rm_kg',
    'overhead_press_1rm_kg',
    'leg_press_1rm_kg',
    'pullups_max_reps',
    'dips_max_reps',
    'pushups_max_reps',
    'plank_seconds',
    'wall_sit_seconds',
    'burpees_1min',
    'jump_rope_1min',
]
const cyclingPerformanceKeys = [
    'weekly_elevation_m',
    'ftp_watts',
    'power_20min_watts',
    'threshold_hr_bpm',
    'max_hr_bpm',
    'avg_speed_kmh',
    'cadence_rpm',
]
const swimmingBestTimeKeys = [
    'swim_best_50m_time',
    'swim_best_100m_time',
    'swim_best_200m_time',
    'swim_best_400m_time',
    'swim_best_800m_time',
    'swim_best_1500m_time',
]
const teamPerformanceKeys = [
    'matches_per_week',
    'match_minutes',
    'sprint_30m_time',
    'cooper_12min_m',
    'yo_yo_level',
    'vertical_jump_cm',
]
const performanceSectionConfigs = {
    running: {
        keys: runningBestTimeKeys,
        titleKey: 'running_best_times_title',
        title: 'Bestzeiten',
        hintKey: 'running_best_times_hint',
        hint: 'Optional: Trage nur die Distanzen ein, die du wirklich kennst. Das hilft der KI bei Pace, Intervallen und Regeneration.',
        classes: 'border-air-blue/30 bg-air-blue/5',
    },
    strength: {
        keys: strengthPerformanceKeys,
        titleKey: 'strength_performance_title',
        title: 'Kraftwerte & Fitness-Tests',
        hintKey: 'strength_performance_hint',
        hint: 'Optional: Trage geschätzte oder getestete Werte ein. Das hilft der KI bei Übungsauswahl, Intensität, Progression und Regeneration.',
        classes: 'border-success/30 bg-success/5',
    },
    cycling: {
        keys: cyclingPerformanceKeys,
        titleKey: 'cycling_performance_title',
        title: 'Radsport-Leistungswerte',
        hintKey: 'cycling_performance_hint',
        hint: 'Optional: Leistung, Puls, Höhenmeter und Trittfrequenz machen Radpläne deutlich genauer.',
        classes: 'border-info/30 bg-info/5',
    },
    swimming: {
        keys: swimmingBestTimeKeys,
        titleKey: 'swimming_best_times_title',
        title: 'Schwimmzeiten',
        hintKey: 'swimming_best_times_hint',
        hint: 'Optional: Zeiten über mehrere Distanzen helfen bei Intervallen, Techniktempo und Ausdauerbereichen.',
        classes: 'border-air-blue/30 bg-air-blue/5',
    },
    team: {
        keys: teamPerformanceKeys,
        titleKey: 'team_performance_title',
        title: 'Spiel- & Athletikwerte',
        hintKey: 'team_performance_hint',
        hint: 'Optional: Spielbelastung, Sprint, Ausdauer und Sprungkraft helfen bei Belastungssteuerung und Athletik.',
        classes: 'border-warning/30 bg-warning/5',
    },
}
const isRunningBestTimeField = (field) => runningBestTimeKeys.includes(field.key)
const isStrengthPerformanceField = (field) => strengthPerformanceKeys.includes(field.key)
const groupedPerformanceKeys = Object.values(performanceSectionConfigs).flatMap((config) => config.keys)
const isGroupedPerformanceField = (field) => groupedPerformanceKeys.includes(field.key)
const runningBestTimeFields = (profile) => profile.group === 'running'
    ? (profile.fields || []).filter(isRunningBestTimeField)
    : []
const strengthPerformanceFields = (profile) => profile.group === 'strength'
    ? (profile.fields || []).filter(isStrengthPerformanceField)
    : []
const performanceSectionsForSportProfile = (profile) => {
    const config = performanceSectionConfigs[profile.group]

    if (!config) return []

    const fields = (profile.fields || []).filter((field) => config.keys.includes(field.key))

    if (!fields.length) return []

    return [{
        ...config,
        key: profile.group,
        fields,
    }]
}
const sportProfileRegularFields = (profile) => (profile.fields || []).filter((field) => !isGroupedPerformanceField(field))
const sportMetricInputType = (field) => {
    if (isExperienceDateField(field)) return 'date'

    return field.type === 'number' ? 'number' : 'text'
}
const trainingDayLabel = (key, type = 'short') => {
    const option = trainingDayOptions.find((day) => day.key === key)

    return sportProfileText(`days.${key}.${type}`, option?.[type] || key)
}
const normalizedTrainingDayTokens = (value) => String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .split(/[\s,;|/+]+/)
    .filter(Boolean)
const selectedTrainingDays = (form, field) => {
    const tokens = normalizedTrainingDayTokens(form.metrics[field.key])
    const selected = new Set()

    if (tokens.some((token) => weekendAliases.includes(token))) {
        selected.add('saturday')
        selected.add('sunday')
    }

    trainingDayOptions.forEach((day) => {
        if ((trainingDayAliases[day.key] || []).some((alias) => tokens.includes(alias))) {
            selected.add(day.key)
        }
    })

    return trainingDayOptions
        .map((day) => day.key)
        .filter((key) => selected.has(key))
}
const isTrainingDaySelected = (form, field, key) => selectedTrainingDays(form, field).includes(key)
const toggleTrainingDay = (form, field, key) => {
    form.unknown_metrics[field.key] = false
    const selected = new Set(selectedTrainingDays(form, field))

    if (selected.has(key)) {
        selected.delete(key)
    } else {
        selected.add(key)
    }

    form.metrics[field.key] = trainingDayOptions
        .map((day) => day.key)
        .filter((dayKey) => selected.has(dayKey))
        .join(',')
}
const isMetricUnknown = (form, field) => Boolean(form.unknown_metrics?.[field.key])
const clearMetricUnknown = (form, field) => {
    if (form.unknown_metrics?.[field.key]) {
        form.unknown_metrics[field.key] = false
    }
}
const toggleMetricUnknown = (form, field) => {
    form.unknown_metrics[field.key] = !form.unknown_metrics[field.key]

    if (form.unknown_metrics[field.key]) {
        form.metrics[field.key] = ''
    }
}
const sportExperienceDuration = (dateValue) => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(String(dateValue || ''))) return ''

    const start = new Date(`${dateValue}T00:00:00`)
    const today = new Date()

    if (Number.isNaN(start.getTime())) return ''
    if (start > today) return sportProfileText('duration.future', 'Startdatum liegt in der Zukunft')

    let years = today.getFullYear() - start.getFullYear()
    let months = today.getMonth() - start.getMonth()

    if (today.getDate() < start.getDate()) {
        months -= 1
    }

    if (months < 0) {
        years -= 1
        months += 12
    }

    const parts = []

    if (years > 0) {
        parts.push(`${years} ${years === 1 ? sportProfileText('duration.year', 'Jahr') : sportProfileText('duration.years', 'Jahre')}`)
    }

    if (months > 0) {
        parts.push(`${months} ${months === 1 ? sportProfileText('duration.month', 'Monat') : sportProfileText('duration.months', 'Monate')}`)
    }

    const durationValue = parts.length
        ? parts.join(` ${sportProfileText('duration.and', 'und')} `)
        : sportProfileText('duration.less_than_month', 'weniger als 1 Monat')

    return sportProfileText('duration.experience', '{value} Erfahrung', { value: durationValue })
}

const selectedSportProfiles = computed(() => props.sportProfiles.filter((profile) => selectedSportProfileIds.value.includes(Number(profile.sport.id))))
const availableSportProfiles = computed(() => props.sportProfiles.filter((profile) => !selectedSportProfileIds.value.includes(Number(profile.sport.id))))
watch(selectedSportProfileIds, (ids) => {
    const selected = ids.map(Number)

    if (!selected.length) {
        activeSportProfileId.value = ''
        return
    }

    if (!selected.includes(Number(activeSportProfileId.value))) {
        activeSportProfileId.value = selected[0]
    }
}, { immediate: true })
const filteredAvailableSportProfiles = computed(() => {
    const query = sportProfileSearch.value.trim().toLowerCase()

    if (!query) return availableSportProfiles.value

    return availableSportProfiles.value.filter((profile) => [
        profile.sport.name,
        profile.sport.slug,
        profile.sport.category,
        profile.group,
    ].filter(Boolean).some((value) => String(value).toLowerCase().includes(query)))
})
const selectedSportProfile = computed(() => props.sportProfiles.find((profile) => Number(profile.sport.id) === Number(selectedSportProfileId.value)) || null)

const chooseSportProfile = (profile) => {
    selectedSportProfileId.value = profile.sport.id
    sportProfileSearch.value = profile.sport.name
    sportProfilePickerOpen.value = false
}

const clearSportProfileChoice = () => {
    selectedSportProfileId.value = ''
    sportProfileSearch.value = ''
    sportProfilePickerOpen.value = true
}

const addSelectedSportProfile = () => {
    const sportId = Number(selectedSportProfileId.value)
    const profile = props.sportProfiles.find((item) => Number(item.sport.id) === sportId)

    if (!profile || selectedSportProfileIds.value.includes(sportId)) return

    const previousSelectedIds = [...selectedSportProfileIds.value]

    selectedSportProfileIds.value = [...selectedSportProfileIds.value, sportId]
    activeSportProfileId.value = sportId
    selectedSportProfileId.value = ''
    sportProfileSearch.value = ''
    sportProfilePickerOpen.value = false
    sportProfileNotice.value = null
    savingSportProfileId.value = sportId

    router.put(route('auth.settings.sport-profiles.update', sportId), sportProfileForms[sportId], {
        preserveScroll: true,
        onSuccess: () => {
            sportProfileNotice.value = {
                type: 'success',
                message: sportProfileText('notices.added', '{sport} wurde hinzugefügt und dauerhaft gespeichert.', { sport: profile.sport.name }),
            }
        },
        onError: () => {
            selectedSportProfileIds.value = previousSelectedIds
            activeSportProfileId.value = previousSelectedIds[0] || ''
            sportProfileNotice.value = {
                type: 'error',
                message: sportProfileText('notices.add_failed', '{sport} konnte nicht hinzugefügt werden.', { sport: profile.sport.name }),
            }
        },
        onFinish: () => {
            savingSportProfileId.value = null
        },
    })
}

const saveSportProfile = (profile) => {
    const sportId = profile.sport.id

    sportProfileNotice.value = null
    savingSportProfileId.value = sportId

    router.put(route('auth.settings.sport-profiles.update', sportId), sportProfileForms[sportId], {
        preserveScroll: true,
        onSuccess: () => {
            sportProfileNotice.value = {
                type: 'success',
                message: sportProfileText('notices.saved', '{sport}: Leistungsdaten gespeichert.', { sport: profile.sport.name }),
            }
        },
        onError: () => {
            sportProfileNotice.value = {
                type: 'error',
                message: sportProfileText('notices.save_failed', '{sport}: Bitte prüfe die Eingaben.', { sport: profile.sport.name }),
            }
        },
        onFinish: () => {
            savingSportProfileId.value = null
        },
    })
}

const openSportProfileRemoveModal = (profile) => {
    sportProfileRemoveModal.value = {
        show: true,
        profile,
    }
}

const closeSportProfileRemoveModal = () => {
    sportProfileRemoveModal.value = {
        show: false,
        profile: null,
    }
}

const confirmSportProfileRemove = () => {
    const profile = sportProfileRemoveModal.value.profile
    if (!profile) return

    const sportId = Number(profile.sport.id)
    savingSportProfileId.value = sportId

    router.delete(route('auth.settings.sport-profiles.destroy', sportId), {
        preserveScroll: true,
        onSuccess: () => {
            selectedSportProfileIds.value = selectedSportProfileIds.value.filter((id) => id !== sportId)
            if (Number(activeSportProfileId.value) === sportId) {
                activeSportProfileId.value = selectedSportProfileIds.value[0] || ''
            }
            sportProfileNotice.value = {
                type: 'success',
                message: sportProfileText('notices.removed', '{sport} wurde aus deinem Sportprofil entfernt.', { sport: profile.sport.name }),
            }
            closeSportProfileRemoveModal()
        },
        onError: () => {
            sportProfileNotice.value = {
                type: 'error',
                message: sportProfileText('notices.remove_failed', '{sport} konnte nicht entfernt werden.', { sport: profile.sport.name }),
            }
        },
        onFinish: () => {
            savingSportProfileId.value = null
        },
    })
}

const formatMoney = (value) => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value || 0))

const formatDate = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat(localeCode.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

const formatTime = (value) => {
    if (!value) return '-'
    return new Intl.DateTimeFormat(localeCode.value, { hour: '2-digit', minute: '2-digit' }).format(new Date(value))
}

const invoiceStatusLabel = (status) => settingsText(`billing.statuses.${status}`, ({
    open: 'Offen',
    awaiting_transfer: 'Warte auf Überweisung',
    paid: 'Bezahlt',
    overdue: 'Überfällig',
    cancelled: 'Storniert',
    active: 'Aktiv',
    trialing: 'Testphase',
    past_due: 'Zahlung offen',
    cancels_at_period_end: 'Gekündigt zum Periodenende',
}[status] || status))
const billingSummary = computed(() => props.billingHistory.summary || {})

const isPayableClubInvoice = (invoice) => ['open', 'overdue', 'awaiting_transfer'].includes(invoice.status)
const isOpenSubscriptionPayment = (invoice) => Boolean(invoice.payment_checkout_id)
    && ['open', 'overdue', 'awaiting_transfer'].includes(invoice.status)
    && ['pending', 'awaiting_transfer'].includes(invoice.checkout?.status)
const canDeleteOpenSubscriptionPayment = (invoice) => Boolean(invoice.payment_checkout_id)
    && invoice.status !== 'paid'
    && !invoice.paid_at
    && ['pending', 'awaiting_transfer', 'cancelled'].includes(invoice.checkout?.status)

const formatIban = (value) => String(value || '')
    .replace(/\s+/g, '')
    .replace(/(.{4})/g, '$1 ')
    .trim()

const paymentReference = (invoice) => invoice.payment_reference || invoice.number || settingsText('billing.invoice_reference', 'Rechnung {id}', { id: invoice.id })

const openBankTransferModal = (type, invoice) => {
    bankTransferModal.value = {
        show: true,
        type,
        invoice,
    }
}

const closeBankTransferModal = () => {
    bankTransferModal.value = {
        show: false,
        type: null,
        invoice: null,
    }
}

const hasAirmiusBank = () => Boolean(props.billingHistory.airmius_bank?.iban)
const hasClubBank = (invoice) => Boolean(invoice.club?.sepa_iban)

const bankTransferRows = () => {
    const invoice = bankTransferModal.value.invoice
    if (!invoice) return []

    if (bankTransferModal.value.type === 'airmius') {
        const bank = props.billingHistory.airmius_bank || {}

        return [
            [settingsText('billing.bank.recipient', 'Empfänger'), 'Airmius'],
            [settingsText('billing.bank.account_holder', 'Kontoinhaber'), bank.bank_account_holder || 'Airmius'],
            ...(bank.bank_name ? [[settingsText('billing.bank.bank', 'Bank'), bank.bank_name]] : []),
            ['IBAN', formatIban(bank.iban)],
            ...(bank.bic ? [['BIC', bank.bic]] : []),
            [settingsText('billing.bank.amount', 'Betrag'), formatMoney(Number(invoice.amount_cents || 0) / 100)],
            [settingsText('billing.bank.reference', 'Verwendungszweck'), paymentReference(invoice)],
        ]
    }

    const club = invoice.club || {}

    return [
        [settingsText('billing.bank.recipient', 'Empfänger'), club.name || '-'],
        [settingsText('billing.bank.account_holder', 'Kontoinhaber'), club.sepa_account_holder || club.name || '-'],
        ['IBAN', formatIban(club.sepa_iban)],
        ...(club.sepa_bic ? [['BIC', club.sepa_bic]] : []),
        [settingsText('billing.bank.amount', 'Betrag'), formatMoney(invoice.amount)],
        [settingsText('billing.bank.reference', 'Verwendungszweck'), paymentReference(invoice)],
    ]
}

const openPaymentActionModal = (action, invoice) => {
    openPaymentModal.value = {
        show: true,
        action,
        invoice,
    }
}

const closeOpenPaymentModal = () => {
    openPaymentModal.value = {
        show: false,
        action: null,
        invoice: null,
    }
}

const openPaymentModalTitle = () => openPaymentModal.value.action === 'delete'
    ? settingsText('billing.open_payment.delete_title', 'Offene Zahlung löschen')
    : settingsText('billing.open_payment.cancel_title', 'Offene Zahlung abbrechen')

const openPaymentModalMessage = () => {
    const invoice = openPaymentModal.value.invoice
    const number = invoice?.number ? ` ${invoice.number}` : ''

    if (openPaymentModal.value.action === 'delete') {
        return settingsText('billing.open_payment.delete_message', 'Die offene Zahlung{number} wird dauerhaft gelöscht. Das ist nur für unbezahlte, nicht aktivierte Zahlungen möglich.', { number })
    }

    return settingsText('billing.open_payment.cancel_message', 'Die offene Zahlung{number} wird abgebrochen und als storniert markiert.', { number })
}

const openPaymentModalConfirmText = () => openPaymentModal.value.action === 'delete'
    ? settingsText('actions.delete', 'Löschen')
    : settingsText('actions.cancel', 'Abbrechen')

const confirmOpenPaymentAction = () => {
    const invoice = openPaymentModal.value.invoice
    const action = openPaymentModal.value.action

    if (!invoice) return

    if (action === 'delete') {
        deleteOpenSubscriptionPayment(invoice)
        return
    }

    cancelOpenSubscriptionPayment(invoice)
}

const cancelOpenSubscriptionPayment = (invoice) => {
    if (!isOpenSubscriptionPayment(invoice)) return

    router.post(route('auth.settings.subscription-invoices.cancel-open-payment', invoice.id), {}, {
        preserveScroll: true,
        onFinish: closeOpenPaymentModal,
    })
}

const deleteOpenSubscriptionPayment = (invoice) => {
    if (!canDeleteOpenSubscriptionPayment(invoice)) return

    router.delete(route('auth.settings.subscription-invoices.destroy-open-payment', invoice.id), {
        preserveScroll: true,
        onFinish: closeOpenPaymentModal,
    })
}

const openProviderPortal = (subscription) => {
    router.post(route('auth.user-subscriptions.provider-portal', subscription.id), {}, { preserveScroll: true })
}

const isSubscriptionCancellable = (subscription) => !['cancelled', 'cancels_at_period_end'].includes(subscription.status)

const canOpenStripePortal = (subscription) => subscription.payment_provider === 'stripe'
    && ['trialing', 'active', 'past_due', 'cancels_at_period_end'].includes(subscription.status)
    && Boolean(subscription.provider_customer_id)

const subscriptionCancelModalMessage = () => {
    const subscription = subscriptionCancelModal.value.subscription
    const plan = subscription?.plan?.name || settingsText('billing.this_subscription', 'dieses Abo')
    const endsAt = subscription?.current_period_ends_at || subscription?.trial_ends_at

    return settingsText(
        'billing.cancel_subscription.message',
        'Möchtest du dein {plan} zum Ende der aktuellen Laufzeit kündigen? {end}',
        {
            plan,
            end: endsAt ? settingsText('billing.cancel_subscription.ends_at', 'Es endet am {date}.', { date: formatDate(endsAt) }) : '',
        },
    )
}

const openSubscriptionCancelModal = (subscription) => {
    subscriptionCancelModal.value = {
        show: true,
        subscription,
    }
}

const closeSubscriptionCancelModal = () => {
    subscriptionCancelModal.value = {
        show: false,
        subscription: null,
    }
}

const confirmSubscriptionCancel = () => {
    const subscription = subscriptionCancelModal.value.subscription
    if (!subscription) return

    router.post(route('auth.user-subscriptions.cancel', subscription.id), {}, {
        preserveScroll: true,
        onFinish: closeSubscriptionCancelModal,
    })
}

const cancelSubscription = (subscription) => {
    if (!isSubscriptionCancellable(subscription)) return

    openSubscriptionCancelModal(subscription)
}

const socialAccountFor = (provider) =>
    props.socialAccounts.find((account) => account.provider === provider)

const connectedAccountFor = (provider) =>
    sportIntegrationState.value.accounts.find((account) => account.provider === provider)

const replaceIntegrationAccount = (account) => {
    if (!account?.id) return
    sportIntegrationState.value.accounts = [
        account,
        ...sportIntegrationState.value.accounts.filter((item) => item.id !== account.id),
    ]
}

const upsertSportActivity = (activity) => {
    if (!activity?.id) return
    sportIntegrationState.value.activities = [
        activity,
        ...sportIntegrationState.value.activities.filter((item) => item.id !== activity.id),
    ]
}

const integrationErrorMessage = (error, fallback) => error?.response?.data?.message
    || Object.values(error?.response?.data?.errors || {}).flat()[0]
    || fallback

const showIntegrationNotice = (type, message) => {
    integrationNotice.value = { type, message }
}

const integrationStatusLabel = (status) => settingsText(`integrations.statuses.${status}`, ({
    connected: 'Verbunden',
    requested: 'Vorgemerkt',
    native_ready: 'Für Import bereit',
    disconnected: 'Getrennt',
    error: 'Fehler',
}[status] || status))

const integrationStatusClass = (status) => ({
    connected: 'border-success/40 bg-success/15 text-success',
    native_ready: 'border-air-blue/40 bg-air-blue/15 text-air-blue',
    requested: 'border-warning/40 bg-warning/15 text-warning',
    error: 'border-danger/40 bg-danger/15 text-danger',
}[status] || 'border-border bg-muted text-secondary')

const integrationProviderActionLabel = (provider) => provider.status === 'live_oauth'
    ? settingsText('integrations.connect', 'Verbinden')
    : provider.status === 'native_bridge'
        ? settingsText('integrations.prepare_import', 'Import vorbereiten')
        : settingsText('integrations.request', 'Vormerken')

const sportIntegrationProviderDescription = (key, provider) =>
    settingsText(`integrations.provider_descriptions.${key}`, provider.description)

const requestIntegrationProvider = async (key, provider) => {
    if (busyIntegrationProviders.value.has(key)) return
    busyIntegrationProviders.value = new Set([...busyIntegrationProviders.value, key])
    try {
        const response = await window.axios.post(route('api.v1.sport-integrations.request', key))
        const data = response.data?.data || {}
        replaceIntegrationAccount({
            id: data.account_id,
            provider: data.provider || key,
            display_name: provider.label,
            status: data.status,
            sync_summary: {},
        })
        showIntegrationNotice('success', settingsText('integrations.requested', 'Sport-App wurde für den sicheren Import vorbereitet.'))
    } catch (error) {
        showIntegrationNotice('error', integrationErrorMessage(error, settingsText('integrations.request_failed', 'Sport-App konnte nicht vorbereitet werden.')))
    } finally {
        busyIntegrationProviders.value = new Set([...busyIntegrationProviders.value].filter((providerKey) => providerKey !== key))
    }
}

const syncIntegration = async (account) => {
    if (busyIntegrationAccounts.value.has(account.id)) return
    busyIntegrationAccounts.value = new Set([...busyIntegrationAccounts.value, account.id])
    integrationNotice.value = null
    try {
        const response = await window.axios.post(route('api.v1.sport-integrations.sync', account.id))
        replaceIntegrationAccount(response.data?.data?.account)
        showIntegrationNotice('success', response.data?.data?.message || settingsText('integrations.sync_completed', 'Synchronisation wurde geprüft.'))
    } catch (error) {
        const accountData = error?.response?.data?.data?.account
        if (accountData) replaceIntegrationAccount(accountData)
        showIntegrationNotice('error', integrationErrorMessage(error, settingsText('integrations.sync_failed', 'Synchronisation konnte nicht abgeschlossen werden.')))
    } finally {
        busyIntegrationAccounts.value = new Set([...busyIntegrationAccounts.value].filter((id) => id !== account.id))
    }
}

const openDisconnectIntegrationModal = (account) => {
    disconnectIntegrationModal.value = {
        show: true,
        account,
    }
}

const closeDisconnectIntegrationModal = () => {
    disconnectIntegrationModal.value = {
        show: false,
        account: null,
    }
}

const disconnectIntegration = async (account) => {
    if (busyIntegrationAccounts.value.has(account.id)) return
    busyIntegrationAccounts.value = new Set([...busyIntegrationAccounts.value, account.id])
    integrationNotice.value = null
    try {
        const response = await window.axios.delete(route('api.v1.sport-integrations.disconnect', account.id))
        sportIntegrationState.value.accounts = sportIntegrationState.value.accounts.filter((item) => item.id !== account.id)
        showIntegrationNotice('success', response.data?.message_text || settingsText('integrations.disconnected', 'Sport-App wurde getrennt.'))
        closeDisconnectIntegrationModal()
    } catch (error) {
        showIntegrationNotice('error', integrationErrorMessage(error, settingsText('integrations.disconnect_failed', 'Sport-App konnte nicht getrennt werden.')))
    } finally {
        busyIntegrationAccounts.value = new Set([...busyIntegrationAccounts.value].filter((id) => id !== account.id))
    }
}

const openSportActivityDeleteModal = (activity = null) => {
    sportActivityDeleteModal.value = {
        show: true,
        activity,
        mode: activity ? 'single' : 'all',
    }
}

const closeSportActivityDeleteModal = () => {
    sportActivityDeleteModal.value = {
        show: false,
        activity: null,
        mode: null,
    }
}

const sportActivityDeleteTitle = () => sportActivityDeleteModal.value.mode === 'all'
    ? settingsText('integrations.activities.delete_all_title', 'Alle importierten Aktivitäten löschen')
    : settingsText('integrations.activities.delete_title', 'Importierte Aktivität löschen')

const sportActivityDeleteMessage = () => sportActivityDeleteModal.value.mode === 'all'
    ? settingsText('integrations.activities.delete_all_message', 'Alle importierten Sportaktivitäten werden dauerhaft aus deinem Airmius Konto gelöscht. Die Verbindung zu Google Fit oder anderen Apps bleibt bestehen.')
    : settingsText('integrations.activities.delete_message', 'Diese importierte Sportaktivität wird dauerhaft aus deinem Airmius Konto gelöscht.')

const confirmSportActivityDelete = async () => {
    if (sportActivityDeleteModal.value.mode === 'all') {
        try {
            const response = await window.axios.delete(route('auth.sport-activities.destroy-all'))
            sportIntegrationState.value.activities = []
            showIntegrationNotice('success', response.data?.message || settingsText('integrations.activities.deleted_all', 'Alle Aktivitäten wurden gelöscht.'))
            closeSportActivityDeleteModal()
        } catch (error) {
            showIntegrationNotice('error', integrationErrorMessage(error, settingsText('integrations.activities.delete_failed', 'Aktivitäten konnten nicht gelöscht werden.')))
        }

        return
    }

    const activity = sportActivityDeleteModal.value.activity
    if (!activity) return

    if (busySportActivities.value.has(activity.id)) return
    busySportActivities.value = new Set([...busySportActivities.value, activity.id])
    try {
        const response = await window.axios.delete(route('api.v1.sport-integrations.activities.destroy', activity.id))
        sportIntegrationState.value.activities = sportIntegrationState.value.activities.filter((item) => item.id !== activity.id)
        showIntegrationNotice('success', response.data?.message_text || settingsText('integrations.activities.deleted', 'Aktivität wurde gelöscht.'))
        closeSportActivityDeleteModal()
    } catch (error) {
        showIntegrationNotice('error', integrationErrorMessage(error, settingsText('integrations.activities.delete_failed', 'Aktivität konnte nicht gelöscht werden.')))
    } finally {
        busySportActivities.value = new Set([...busySportActivities.value].filter((id) => id !== activity.id))
    }
}

const openSportActivityEditModal = (activity) => {
    sportActivityEditModal.value = {
        show: true,
        activity,
    }
    sportActivityEditForm.title = activity.title || activity.activity_type || ''
    sportActivityEditForm.clearErrors()
}

const closeSportActivityEditModal = () => {
    sportActivityEditModal.value = {
        show: false,
        activity: null,
    }
    sportActivityEditForm.reset()
    sportActivityEditForm.clearErrors()
}

const updateSportActivityTitle = async () => {
    const activity = sportActivityEditModal.value.activity
    if (!activity) return

    if (busySportActivities.value.has(activity.id)) return
    busySportActivities.value = new Set([...busySportActivities.value, activity.id])
    try {
        const response = await window.axios.put(route('api.v1.sport-integrations.activities.update', activity.id), {
            title: sportActivityEditForm.title,
        })
        upsertSportActivity(response.data?.data?.activity)
        showIntegrationNotice('success', response.data?.message_text || settingsText('integrations.activities.updated', 'Aktivität wurde aktualisiert.'))
        closeSportActivityEditModal()
    } catch (error) {
        sportActivityEditForm.setError('title', integrationErrorMessage(error, settingsText('integrations.activities.update_failed', 'Aktivität konnte nicht aktualisiert werden.')))
    } finally {
        busySportActivities.value = new Set([...busySportActivities.value].filter((id) => id !== activity.id))
    }
}

const storeManualActivity = async () => {
    if (manualActivitySaving.value) return
    manualActivitySaving.value = true
    manualActivityForm.clearErrors()
    const payload = new FormData()
    Object.entries(manualActivityForm.data()).forEach(([key, value]) => {
        if (value !== null && value !== '') payload.append(key, value)
    })
    try {
        const response = await window.axios.post(route('auth.sport-activities.store'), payload, {
            // Let Axios add the multipart boundary generated for this FormData.
            headers: { Accept: 'application/json' },
        })
        upsertSportActivity(response.data?.data?.activity)
        showIntegrationNotice('success', response.data?.message || settingsText('integrations.activities.created', 'Training wurde gespeichert.'))
        manualActivityForm.reset()
        manualActivityForm.activity_type = 'Training'
        if (manualActivityImageInput.value) manualActivityImageInput.value.value = ''
    } catch (error) {
        const errors = error?.response?.data?.errors || {}
        Object.entries(errors).forEach(([key, messages]) => manualActivityForm.setError(key, messages[0]))
        if (!Object.keys(errors).length) {
            showIntegrationNotice('error', integrationErrorMessage(error, settingsText('integrations.activities.create_failed', 'Training konnte nicht gespeichert werden.')))
        }
    } finally {
        manualActivitySaving.value = false
    }
}

const setManualActivityImage = (event) => {
    manualActivityForm.image = event.target.files?.[0] || null
}

const formatDuration = (seconds) => {
    if (!seconds) return '-'
    const minutes = Math.round(seconds / 60)
    if (minutes < 60) return `${minutes} min`

    const hours = Math.floor(minutes / 60)
    const rest = minutes % 60

    return rest > 0 ? `${hours} h ${rest} min` : `${hours} h`
}

const formatDistance = (meters) => {
    if (!meters) return '-'
    return `${(meters / 1000).toFixed(2).replace('.', ',')} km`
}

const formatProvider = (provider) => settingsText(`integrations.providers.${provider}`, ({
    manual: 'Manuell',
    google_fit: 'Google Fit',
    strava: 'Strava',
    garmin: 'Garmin',
    mi_fitness: 'Mi Fitness',
    fitbit: 'Fitbit',
    polar: 'Polar',
}[provider] || provider))

const manualActivityTypeLabel = (type) => settingsText(`integrations.manual_activity.types.${type}`, type)

const sportActivityTitle = (activity) => {
    if (activity.title && activity.title !== 'Google Fit Tagesaktivität') {
        return activity.title
    }

    return activity.activity_type || settingsText('integrations.activities.daily_activity', 'Tagesaktivität')
}

const sportActivitySubtitle = (activity) => {
    if (activity.metrics?.source_kind === 'manual_entry') {
        return settingsText('integrations.activities.manual_entry', 'Manuell eingetragen')
    }

    if (activity.metrics?.source_kind === 'daily_summary') {
        const parts = [settingsText('integrations.activities.daily_summary', 'Tageszusammenfassung')]
        if (activity.metrics?.active_minutes) {
            parts.push(settingsText('integrations.activities.active_minutes', '{minutes} aktive Minuten', { minutes: activity.metrics.active_minutes }))
        }

        return parts.join(' · ')
    }

    return activity.metrics?.earliest_start_time
        ? settingsText('integrations.activities.start_about', 'Start ca. {time}', { time: activity.metrics.earliest_start_time })
        : ''
}

const sportActivityTime = (activity) => {
    if (activity.metrics?.earliest_start_time) {
        return activity.metrics.earliest_start_time
    }

    return activity.metrics?.source_kind === 'daily_summary' ? '-' : formatTime(activity.started_at)
}

const activityLabel = (type) => settingsText(`activities.types.${type}`, ({
    'post.created': 'Beitrag erstellt',
    'post.updated': 'Beitrag aktualisiert',
    'post.deleted': 'Beitrag gelöscht',
    'post.commented': 'Beitrag kommentiert',
    'user.followed': 'Person gefolgt',
    'friend.requested': 'Freundschaftsanfrage gesendet',
    'friend.accepted': 'Freundschaft akzeptiert',
    'comment.created': 'Kommentar geschrieben',
    'comment.updated': 'Kommentar bearbeitet',
    'comment.deleted': 'Kommentar gelöscht',
}[type] || type))

const activityScope = (activity) => activity.team?.name || activity.club?.name || settingsText('activities.personal', 'Persönlich')

const activityDescription = (activity) => activity.data?.title || activity.data?.content || activity.data?.message || ''
</script>

<template>
    <Head :title="t('Einstellungen')" />

    <div class="space-y-5">

        <!-- HEADER -->
        <div class="surface-card p-5">
            <h1 class="text-xl font-semibold text-primary">{{ t('Einstellungen') }}</h1>
            <p class="mt-1 text-sm text-secondary">
                {{ t('settings.header_subtitle') }}
            </p>
        </div>

        <div
            v-if="$page.props.flash?.success || $page.props.flash?.error"
            class="rounded-lg border px-4 py-3 text-sm font-semibold"
            :class="$page.props.flash?.success
                ? 'border-success/30 bg-success/10 text-success'
                : 'border-error/30 bg-error/10 text-error'"
        >
            {{ $page.props.flash?.success || $page.props.flash?.error }}
        </div>

        <!-- TABS -->
        <div role="tablist" class="surface-card p-3 flex flex-wrap gap-2" :aria-busy="Boolean(pendingTab)">
            <button type="button" role="tab" :aria-selected="activeTab === 'profile'" @click="setActiveTab('profile')" :class="tabClass('profile')">{{ t('Profil') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'address'" @click="setActiveTab('address')" :class="tabClass('address')">{{ t('Adresse') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'billing'" @click="setActiveTab('billing')" :class="tabClass('billing')">{{ t('Zahlungen') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'roles'" @click="setActiveTab('roles')" :class="tabClass('roles')">{{ t('Rollen') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'areas'" @click="setActiveTab('areas')" :class="tabClass('areas')">{{ settingsText('modules.tab', 'Bereiche') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'activities'" @click="setActiveTab('activities')" :class="tabClass('activities')">{{ t('Aktivitäten') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'integrations'" @click="setActiveTab('integrations')" :class="tabClass('integrations')">{{ t('Verknüpfungen') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'design'" @click="setActiveTab('design')" :class="tabClass('design')">{{ t('Design') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'language'" @click="setActiveTab('language')" :class="tabClass('language')">{{ t('Sprache') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'notifications'" @click="setActiveTab('notifications')" :class="tabClass('notifications')">{{ settingsText('notification_preferences.tab', 'Benachrichtigungen') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'privacy'" @click="setActiveTab('privacy')" :class="tabClass('privacy')">{{ t('Privatsphäre') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'sport-profile'" @click="setActiveTab('sport-profile')" :class="tabClass('sport-profile')">{{ sportProfileText('tab', 'Sportprofil') }}</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'security'" @click="setActiveTab('security')" :class="tabClass('security')">{{ t('Sicherheit') }}</button>
        </div>

        <div
            v-if="pendingTab"
            class="flex items-center gap-3 rounded-lg border border-air-blue/30 bg-air-blue/10 px-4 py-3 text-sm font-semibold text-primary"
            role="status"
            aria-live="polite"
        >
            <i class="las la-spinner animate-spin text-lg text-air-blue" aria-hidden="true"></i>
            {{ t('search.loading') }}
        </div>
        <div
            v-else-if="failedTab"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm text-error"
            role="alert"
        >
            <span>{{ t('global_feedback.unexpected') }}</span>
            <button type="button" class="btn-secondary" @click="retryFailedTab">
                {{ t('search.retry') }}
            </button>
        </div>

        <!-- PROFIL -->
        <div v-if="activeTab === 'profile'" class="surface-card p-5 space-y-6">
            <UpdateProfileInformationForm :user="$page.props.auth.user" />
        </div>

        <!-- SPORTPROFIL -->
        <div v-if="activeTab === 'sport-profile'" class="space-y-5">
            <section class="surface-card p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ sportProfileText('eyebrow', 'KI-Trainingspläne') }}</p>
                        <h2 class="mt-1 text-xl font-semibold text-primary">{{ sportProfileText('title', 'Sportprofil & Leistungsdaten') }}</h2>
                        <p class="mt-2 max-w-3xl text-sm text-secondary">
                            {{ sportProfileText('subtitle', 'Diese Daten machen KI-Pläne persönlicher und sicherer. Airmius nutzt sie für Pace, Umfang, Regeneration, Verletzungsrisiko und realistische Steigerung.') }}
                        </p>
                    </div>
                    <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                        {{ sportProfileText('default_private', 'Standard: privat') }}
                    </span>
                </div>

                <div
                    v-if="sportProfileNotice"
                    class="mt-4 rounded-lg border px-4 py-3 text-sm font-semibold"
                    :class="sportProfileNotice.type === 'success'
                        ? 'border-success/30 bg-success/10 text-success'
                        : 'border-error/30 bg-error/10 text-error'"
                >
                    {{ sportProfileNotice.message }}
                </div>

                <div class="mt-5 rounded-2xl border border-border bg-bg p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <label class="block flex-1 text-sm font-semibold text-primary">
                            {{ sportProfileText('add_sport', 'Sportart hinzufügen') }}
                            <div class="relative mt-2">
                                <div class="flex min-h-11 items-center gap-2 rounded-xl border border-border bg-inputBg px-3 focus-within:border-air-blue">
                                    <i class="las la-search text-lg text-secondary"></i>
                                    <input
                                        id="sport-profile-search"
                                        v-model="sportProfileSearch"
                                        type="search"
                                        class="min-w-0 flex-1 border-0 bg-transparent py-2 text-sm font-semibold text-primary outline-none placeholder:text-secondary"
                                        :placeholder="availableSportProfiles.length ? sportProfileText('search_or_select', 'Sportart suchen oder auswählen') : sportProfileText('all_selected', 'Alle ausgewählten Sportarten sind bereits hinzugefügt')"
                                        :disabled="!availableSportProfiles.length"
                                        autocomplete="off"
                                        @focus="sportProfilePickerOpen = true"
                                        @input="selectedSportProfileId = ''; sportProfilePickerOpen = true"
                                        @keydown.escape="sportProfilePickerOpen = false"
                                    />
                                    <button
                                        v-if="selectedSportProfile"
                                        type="button"
                                        class="rounded-full border border-border px-2 py-1 text-xs font-semibold text-secondary hover:bg-muted"
                                        @click="clearSportProfileChoice"
                                    >
                                        {{ sportProfileText('change', 'Ändern') }}
                                    </button>
                                </div>

                                <div
                                    v-if="sportProfilePickerOpen && availableSportProfiles.length"
                                    class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-border bg-card p-2 shadow-2xl"
                                >
                                    <button
                                        v-for="profile in filteredAvailableSportProfiles"
                                        :key="profile.sport.id"
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm transition hover:bg-muted"
                                        @click="chooseSportProfile(profile)"
                                    >
                                        <span>
                                            <span class="block font-semibold text-primary">{{ profile.sport.name }}</span>
                                            <span class="text-xs text-secondary">{{ profile.group }}{{ profile.sport.category ? ` · ${profile.sport.category}` : '' }}</span>
                                        </span>
                                        <i class="las la-plus text-lg text-air-blue"></i>
                                    </button>
                                    <p v-if="!filteredAvailableSportProfiles.length" class="px-3 py-4 text-sm text-secondary">
                                        {{ sportProfileText('no_sport_found', 'Keine Sportart gefunden.') }}
                                    </p>
                                </div>
                            </div>
                            <select v-model="selectedSportProfileId" class="hidden" :disabled="!availableSportProfiles.length">
                                <option value="">{{ availableSportProfiles.length ? sportProfileText('select_sport', 'Sportart auswählen') : sportProfileText('all_selected', 'Alle ausgewählten Sportarten sind bereits hinzugefügt') }}</option>
                                <option v-for="profile in availableSportProfiles" :key="profile.sport.id" :value="profile.sport.id">
                                    {{ profile.sport.name }}
                                </option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                            :disabled="!selectedSportProfileId || savingSportProfileId"
                            @click="addSelectedSportProfile"
                        >
                            {{ sportProfileText('add', 'Hinzufügen') }}
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-secondary">
                        {{ sportProfileText('add_hint', 'Es werden nur Sportarten angezeigt, die du hier auswählst und wirklich betreibst.') }}
                    </p>
                </div>
            </section>

            <div v-if="selectedSportProfiles.length" class="space-y-4">
                <div class="surface-card p-3">
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <button
                            v-for="profile in selectedSportProfiles"
                            :key="profile.sport.id"
                            type="button"
                            class="min-w-[220px] rounded-xl border p-3 text-left transition"
                            :class="Number(activeSportProfileId) === Number(profile.sport.id)
                                ? 'border-air-blue bg-air-blue/15 text-primary'
                                : 'border-border bg-bg text-secondary hover:border-air-blue/60 hover:text-primary'"
                            @click="activeSportProfileId = Number(profile.sport.id)"
                        >
                            <span class="block text-xs font-semibold uppercase tracking-wide">
                                {{ sportProfileGroupLabel(profile.group) }}
                            </span>
                            <span class="mt-1 flex items-center justify-between gap-3">
                                <span class="truncate text-sm font-semibold">{{ profile.sport.name }}</span>
                                <span
                                    class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-semibold"
                                    :class="profile.readiness.ready ? 'border-success/30 bg-success/10 text-success' : 'border-warning/30 bg-warning/10 text-warning'"
                                >
                                    {{ profile.readiness.score }}%
                                </span>
                            </span>
                        </button>
                    </div>
                </div>

                <article
                    v-for="profile in selectedSportProfiles"
                    :key="profile.sport.id"
                    v-show="Number(activeSportProfileId) === Number(profile.sport.id)"
                    class="surface-card overflow-hidden"
                >
                    <div class="border-b border-border p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ sportProfileGroupLabel(profile.group) }}</p>
                                <h3 class="mt-1 text-lg font-semibold text-primary">{{ profile.sport.name }}</h3>
                                <p class="mt-1 text-sm text-secondary">
                                    {{ profile.readiness.ready ? sportProfileText('ready', 'Bereit für KI-Trainingspläne.') : sportProfileText('not_ready', 'Noch nicht vollständig für zuverlässige KI-Pläne.') }}
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center justify-center rounded-full border px-3 py-1 text-xs font-semibold"
                                :class="profile.readiness.ready ? 'border-success/30 bg-success/10 text-success' : 'border-warning/30 bg-warning/10 text-warning'"
                            >
                                {{ profile.readiness.score }}%
                            </span>
                        </div>

                        <button
                            type="button"
                            class="mt-4 rounded-xl border border-error/30 px-3 py-2 text-xs font-semibold text-error hover:bg-error/10"
                            :disabled="savingSportProfileId === profile.sport.id"
                            @click="openSportProfileRemoveModal(profile)"
                        >
                            {{ sportProfileText('remove_sport', 'Sportart entfernen') }}
                        </button>

                        <div v-if="profile.readiness.missing?.length" class="mt-4 rounded-xl border border-warning/30 bg-warning/10 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-warning">{{ sportProfileText('missing_title', 'Fehlt noch') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs font-semibold text-warning">
                                <span v-for="field in profile.readiness.missing" :key="field.key" class="rounded-full bg-bg px-2.5 py-1">
                                    {{ sportMetricLabel(field) }}
                                </span>
                            </div>
                        </div>
                        <div v-if="profile.readiness.unknown?.length" class="mt-4 rounded-xl border border-air-blue/30 bg-air-blue/10 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ sportProfileText('unknown_title', 'Wird geschätzt') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs font-semibold text-air-blue">
                                <span v-for="field in profile.readiness.unknown" :key="field.key" class="rounded-full bg-bg px-2.5 py-1">
                                    {{ sportMetricLabel(field) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <form
                        v-if="sportProfileForms[profile.sport.id]"
                        class="space-y-4 p-5"
                        @submit.prevent="saveSportProfile(profile)"
                    >
                        <div class="grid gap-3 md:grid-cols-3">
                            <label class="block text-sm font-semibold text-primary">{{ sportProfileText('status_label', 'Status') }}
                                <select v-model="sportProfileForms[profile.sport.id].status" class="input mt-2">
                                    <option value="active">{{ sportStatusLabel('active') }}</option>
                                    <option value="wants_to_learn">{{ sportStatusLabel('wants_to_learn') }}</option>
                                    <option value="coach">{{ sportStatusLabel('coach') }}</option>
                                    <option value="interested">{{ sportStatusLabel('interested') }}</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary">{{ sportProfileText('level_label', 'Niveau') }}
                                <select v-model="sportProfileForms[profile.sport.id].experience_level" class="input mt-2">
                                    <option value="beginner">{{ sportExperienceLabel('beginner') }}</option>
                                    <option value="intermediate">{{ sportExperienceLabel('intermediate') }}</option>
                                    <option value="advanced">{{ sportExperienceLabel('advanced') }}</option>
                                    <option value="expert">{{ sportExperienceLabel('expert') }}</option>
                                    <option value="elite">{{ sportExperienceLabel('elite') }}</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary">{{ sportProfileText('profile_visibility_label', 'Profil-Sichtbarkeit') }}
                                <select v-model="sportProfileForms[profile.sport.id].visibility" class="input mt-2">
                                    <option value="private">{{ metricVisibilityLabel('private') }}</option>
                                    <option value="trainer">{{ metricVisibilityLabel('trainer') }}</option>
                                    <option value="public">{{ metricVisibilityLabel('public') }}</option>
                                </select>
                            </label>
                        </div>

                        <div class="grid gap-3">
                            <section
                                v-for="section in performanceSectionsForSportProfile(profile)"
                                :key="section.key"
                                class="rounded-xl border p-3"
                                :class="section.classes"
                            >
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-primary">
                                            {{ sportProfileText(section.titleKey, section.title) }}
                                        </p>
                                        <p class="text-xs text-secondary">
                                            {{ sportProfileText(section.hintKey, section.hint) }}
                                        </p>
                                    </div>
                                    <span class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                        {{ sportProfileText('optional', 'Optional') }}
                                    </span>
                                </div>

                                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    <div
                                        v-for="field in section.fields"
                                        :key="field.key"
                                        class="rounded-xl border border-border bg-bg p-3"
                                    >
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-secondary">
                                            {{ sportMetricLabel(field) }}
                                            <span v-if="field.unit" class="normal-case text-secondary">({{ field.unit }})</span>
                                            <input
                                                v-model="sportProfileForms[profile.sport.id].metrics[field.key]"
                                                :type="sportMetricInputType(field)"
                                                :step="field.type === 'number' ? '0.01' : undefined"
                                                :min="field.type === 'number' ? 0 : undefined"
                                                :disabled="isMetricUnknown(sportProfileForms[profile.sport.id], field)"
                                                class="input mt-2 text-sm normal-case tracking-normal"
                                                :placeholder="isMetricUnknown(sportProfileForms[profile.sport.id], field) ? sportProfileText('unknown_placeholder', 'Wird vorsichtig geschätzt') : sportMetricPlaceholder(field)"
                                                @input="clearMetricUnknown(sportProfileForms[profile.sport.id], field)"
                                            />
                                        </label>
                                        <button
                                            v-if="field.required"
                                            type="button"
                                            class="mt-2 rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
                                            :class="isMetricUnknown(sportProfileForms[profile.sport.id], field) ? 'border-air-blue/50 bg-air-blue/10 text-air-blue' : 'border-border text-secondary hover:text-primary'"
                                            @click="toggleMetricUnknown(sportProfileForms[profile.sport.id], field)"
                                        >
                                            {{ isMetricUnknown(sportProfileForms[profile.sport.id], field) ? sportProfileText('unknown_marked', 'Wird geschätzt') : sportProfileText('unknown_action', 'Weiß ich nicht') }}
                                        </button>
                                        <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-secondary">
                                            {{ sportProfileText('visible_label', 'Sichtbar') }}
                                            <select v-model="sportProfileForms[profile.sport.id].metric_visibility[field.key]" class="input mt-1 text-sm normal-case tracking-normal">
                                                <option value="private">{{ metricVisibilityLabel('private') }}</option>
                                                <option value="trainer">{{ metricVisibilityLabel('trainer') }}</option>
                                                <option value="public">{{ metricVisibilityLabel('public') }}</option>
                                            </select>
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <div
                                v-for="field in sportProfileRegularFields(profile)"
                                :key="field.key"
                                class="rounded-xl border border-border bg-bg p-3"
                            >
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <label class="block flex-1 text-sm font-semibold text-primary">
                                        {{ sportMetricLabel(field) }}
                                        <span v-if="field.required" class="text-error">*</span>
                                        <span v-if="field.unit" class="text-secondary">({{ field.unit }})</span>
                                        <div
                                            v-if="isTrainingDaysField(field)"
                                            class="mt-3 flex flex-wrap gap-2"
                                        >
                                            <button
                                                v-for="day in trainingDayOptions"
                                                :key="day.key"
                                                type="button"
                                                class="min-w-12 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                                                :class="isTrainingDaySelected(sportProfileForms[profile.sport.id], field, day.key)
                                                    ? 'border-air-blue bg-air-blue/20 text-air-blue'
                                                    : 'border-border bg-inputBg text-secondary hover:border-air-blue/60 hover:text-primary'"
                                                :disabled="isMetricUnknown(sportProfileForms[profile.sport.id], field)"
                                                :aria-pressed="isTrainingDaySelected(sportProfileForms[profile.sport.id], field, day.key)"
                                                :title="trainingDayLabel(day.key, 'long')"
                                                @click="toggleTrainingDay(sportProfileForms[profile.sport.id], field, day.key)"
                                            >
                                                {{ trainingDayLabel(day.key, 'short') }}
                                            </button>
                                        </div>
                                        <textarea
                                            v-else-if="field.type === 'textarea'"
                                            v-model="sportProfileForms[profile.sport.id].metrics[field.key]"
                                            rows="2"
                                            :disabled="isMetricUnknown(sportProfileForms[profile.sport.id], field)"
                                            class="input mt-2"
                                            :placeholder="isMetricUnknown(sportProfileForms[profile.sport.id], field) ? sportProfileText('unknown_placeholder', 'Wird vorsichtig geschätzt') : sportMetricPlaceholder(field)"
                                            @input="clearMetricUnknown(sportProfileForms[profile.sport.id], field)"
                                        />
                                        <input
                                            v-else
                                            v-model="sportProfileForms[profile.sport.id].metrics[field.key]"
                                            :type="sportMetricInputType(field)"
                                            :step="field.type === 'number' && !isExperienceDateField(field) ? '0.01' : undefined"
                                            :max="isExperienceDateField(field) ? todayDate : undefined"
                                            :disabled="isMetricUnknown(sportProfileForms[profile.sport.id], field)"
                                            class="input mt-2"
                                            :placeholder="isMetricUnknown(sportProfileForms[profile.sport.id], field) ? sportProfileText('unknown_placeholder', 'Wird vorsichtig geschätzt') : sportMetricPlaceholder(field)"
                                            @input="clearMetricUnknown(sportProfileForms[profile.sport.id], field)"
                                        />
                                        <button
                                            v-if="field.required"
                                            type="button"
                                            class="mt-2 rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
                                            :class="isMetricUnknown(sportProfileForms[profile.sport.id], field) ? 'border-air-blue/50 bg-air-blue/10 text-air-blue' : 'border-border text-secondary hover:text-primary'"
                                            @click="toggleMetricUnknown(sportProfileForms[profile.sport.id], field)"
                                        >
                                            {{ isMetricUnknown(sportProfileForms[profile.sport.id], field) ? sportProfileText('unknown_marked', 'Wird geschätzt') : sportProfileText('unknown_action', 'Weiß ich nicht') }}
                                        </button>
                                        <span
                                            v-if="isExperienceDateField(field) && sportExperienceDuration(sportProfileForms[profile.sport.id].metrics[field.key])"
                                            class="mt-2 block text-xs font-semibold text-air-blue"
                                        >
                                            {{ sportExperienceDuration(sportProfileForms[profile.sport.id].metrics[field.key]) }}
                                        </span>
                                    </label>
                                    <label class="block w-full text-xs font-semibold uppercase tracking-wide text-secondary md:w-40">
                                        {{ sportProfileText('visible_label', 'Sichtbar') }}
                                        <select v-model="sportProfileForms[profile.sport.id].metric_visibility[field.key]" class="input mt-2 text-sm normal-case tracking-normal">
                                            <option value="private">{{ metricVisibilityLabel('private') }}</option>
                                            <option value="trainer">{{ metricVisibilityLabel('trainer') }}</option>
                                            <option value="public">{{ metricVisibilityLabel('public') }}</option>
                                        </select>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                            :disabled="savingSportProfileId === profile.sport.id"
                        >
                            {{ savingSportProfileId === profile.sport.id ? sportProfileText('saving', 'Speichert...') : sportProfileText('save_profile', 'Sportprofil speichern') }}
                        </button>
                    </form>
                </article>
            </div>

            <div v-else class="surface-card p-6 text-sm text-secondary">
                {{ sportProfileText('empty_selected', 'Noch keine Sportart ausgewählt. Füge oben zuerst die Sportart hinzu, die du wirklich trainierst.') }}
            </div>
        </div>

        <!-- ROLLEN -->
        <div v-if="activeTab === 'roles'" class="surface-card p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">{{ settingsText('roles.title', 'Meine Rollen') }}</h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ settingsText('roles.description', 'Hier siehst du, welche Plattform-Rollen deinem Konto aktuell zugeordnet sind.') }}
                    </p>
                </div>
                <span class="text-sm font-semibold text-secondary">{{ settingsText('roles.count', '{count} Rollen', { count: userRoles.length }) }}</span>
            </div>

            <div v-if="userRoles.length" class="mt-5 grid gap-3 md:grid-cols-2">
                <article
                    v-for="role in userRoles"
                    :key="role.id"
                    class="rounded-lg border border-border bg-bg p-4"
                >
                    <div>
                        <div class="min-w-0">
                            <p class="break-words font-semibold text-primary">{{ roleName(role) }}</p>
                            <p class="mt-1 text-sm text-secondary">{{ roleDescription(role) }}</p>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="mt-5 rounded-lg border border-dashed border-border bg-bg p-6 text-sm text-secondary">
                {{ settingsText('roles.empty', 'Deinem Konto ist noch keine Rolle zugewiesen.') }}
            </div>

            <div class="mt-6 rounded-xl border border-air-blue/30 bg-air-blue/5 p-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="font-semibold text-primary">{{ settingsText('roles.apply_title', 'Weitere Funktion aktivieren') }}</h3>
                        <p class="mt-1 max-w-2xl text-sm text-secondary">
                            {{ settingsText('roles.apply_description', 'Trainer und Sportler können ihren Arbeitsbereich sofort aktivieren. Airmius prüft den Antrag anschließend.') }}
                        </p>
                    </div>

                    <div v-if="trainerApplication?.status !== 'pending'" class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-semibold text-primary">
                                {{ settingsText('roles.sports', 'Sportarten') }}
                            </label>
                            <MultiSelectDropdown
                                v-model="trainerSportIds"
                                class="mt-1"
                                :options="sports"
                                :placeholder="settingsText('roles.sports_placeholder', 'Sportarten auswählen')"
                                :empty-text="settingsText('roles.sports_empty', 'Keine Sportarten verfügbar.')"
                            />
                            <p class="mt-1 text-xs text-secondary">
                                {{ settingsText('roles.multiple_hint', 'Mehrere Auswahlen sind möglich.') }}
                            </p>
                            <p v-if="roleApplicationForm.errors['application_data.sport_ids']" class="mt-1 text-sm text-error">
                                {{ roleApplicationForm.errors['application_data.sport_ids'] }}
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-primary">
                                {{ settingsText('roles.specialties', 'Schwerpunkte') }}
                            </label>
                            <MultiSelectDropdown
                                v-model="trainerSkillIds"
                                class="mt-1"
                                :options="trainerApplicationSkillOptions"
                                :placeholder="settingsText('roles.specialties_placeholder', 'Schwerpunkte auswählen')"
                                :empty-text="settingsText('roles.specialties_empty', 'Wähle zuerst eine Sportart aus.')"
                            />
                            <p class="mt-1 text-xs text-secondary">
                                {{ settingsText('roles.multiple_hint', 'Mehrere Auswahlen sind möglich.') }}
                            </p>
                            <p v-if="roleApplicationForm.errors['application_data.sport_skill_ids']" class="mt-1 text-sm text-error">
                                {{ roleApplicationForm.errors['application_data.sport_skill_ids'] }}
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-primary" for="trainer-experience">
                                {{ settingsText('roles.experience', 'Erfahrung') }}
                            </label>
                            <textarea
                                id="trainer-experience"
                                v-model="roleApplicationForm.application_data.experience"
                                rows="2"
                                required
                                class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                                :placeholder="settingsText('roles.experience_placeholder', 'Beschreibe kurz deine Erfahrung als Trainer.')"
                            ></textarea>
                            <p v-if="roleApplicationForm.errors['application_data.experience']" class="mt-1 text-sm text-error">
                                {{ roleApplicationForm.errors['application_data.experience'] }}
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-primary" for="trainer-certification">
                                {{ settingsText('roles.certification', 'Zertifikate') }}
                            </label>
                            <input
                                id="trainer-certification"
                                v-model="roleApplicationForm.application_data.certification"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                                :placeholder="settingsText('roles.certification_placeholder', 'Optional: Lizenzen oder Zertifikate')"
                            />
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-primary" for="trainer-message">
                                {{ settingsText('roles.message', 'Nachricht') }}
                            </label>
                            <textarea
                                id="trainer-message"
                                v-model="roleApplicationForm.message"
                                rows="2"
                                class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                                :placeholder="settingsText('roles.message_placeholder', 'Optional: zusätzliche Informationen für Airmius')"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span
                            v-if="trainerApplication?.status === 'pending'"
                            class="rounded-lg border border-warning/30 bg-warning/10 px-4 py-2 text-sm font-semibold text-warning"
                        >
                            {{ settingsText('roles.trainer_pending', 'Trainerantrag wird geprüft') }}
                        </span>
                        <button
                            v-else-if="trainerApplication?.status !== 'pending'"
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                            :disabled="roleApplicationForm.processing"
                            @click="submitTrainerApplication"
                        >
                            {{ roleApplicationForm.processing ? settingsText('roles.applying', 'Wird aktiviert...') : settingsText('roles.apply_trainer', 'Trainer werden') }}
                        </button>
                        <Link
                            :href="route('auth.teams.index', { create_club: 1 })"
                            class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-primary hover:border-borderHover"
                        >
                            {{ settingsText('roles.apply_club', 'Verein anmelden') }}
                        </Link>
                    </div>
                </div>

                <p v-if="roleApplicationForm.errors.type || roleApplicationForm.errors.message" class="mt-3 text-sm text-error">
                    {{ roleApplicationForm.errors.type || roleApplicationForm.errors.message }}
                </p>
                <p v-if="trainerApplication?.status === 'rejected' && trainerApplication.review_notes" class="mt-3 text-sm text-error">
                    {{ settingsText('roles.rejection_note', 'Hinweis von Airmius:') }} {{ trainerApplication.review_notes }}
                </p>
            </div>
        </div>

        <!-- BEREICHE -->
        <div v-if="activeTab === 'areas'" class="surface-card p-5">
            <div>
                <h2 class="text-lg font-semibold text-primary">{{ settingsText('modules.title', 'Bereiche im Sidebar') }}</h2>
                <p class="mt-1 max-w-3xl text-sm text-secondary">
                    {{ settingsText('modules.description', 'Wähle, welche deiner verfügbaren Bereiche im Sidebar angezeigt werden. Diese Auswahl ändert keine Rollen oder Berechtigungen.') }}
                </p>
            </div>

            <div
                v-if="navigationNotice"
                class="mt-4 rounded-lg border px-4 py-3 text-sm font-semibold"
                :class="navigationNotice.type === 'success'
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-error/30 bg-error/10 text-error'"
            >
                {{ navigationNotice.message }}
            </div>

            <div v-if="navigationModuleOptions.length" class="mt-5 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <label
                    v-for="module in navigationModuleOptions"
                    :key="module.key"
                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-bg p-4 transition hover:border-borderHover"
                    :class="navigationModuleSelected(module.key) ? 'ring-2 ring-air-blue/30' : 'opacity-80'"
                >
                    <input
                        type="checkbox"
                        class="mt-1 rounded border-border bg-inputBg"
                        :checked="navigationModuleSelected(module.key)"
                        @change="toggleNavigationModule(module.key)"
                    />
                    <span>
                        <span class="flex items-center gap-2 font-semibold text-primary">
                            <i :class="module.icon || 'las la-layer-group'" class="text-air-blue"></i>
                            {{ navigationModuleLabel(module.key, module.label) }}
                        </span>
                        <span class="mt-1 block text-sm text-secondary">
                            {{ navigationModuleDescription(module.key, module.description) }}
                        </span>
                    </span>
                </label>
            </div>

            <div v-else class="mt-5 rounded-lg border border-dashed border-border bg-bg p-5 text-sm text-secondary">
                {{ settingsText('modules.empty', 'Für dein Konto sind keine zusätzlichen Bereiche verfügbar.') }}
            </div>

            <div class="mt-5 rounded-xl border border-air-blue/30 bg-air-blue/5 p-4 text-sm text-secondary">
                <i class="las la-newspaper me-1 text-air-blue"></i>
                {{ settingsText('modules.feed_note', 'Der Feed bleibt für alle Konten immer sichtbar und kann hier nicht deaktiviert werden.') }}
            </div>

            <button
                type="button"
                class="btn-primary mt-5 disabled:opacity-60"
                :disabled="form.processing"
                @click="saveNavigationModules"
            >
                {{ form.processing ? settingsText('modules.saving', 'Speichert...') : settingsText('modules.save', 'Bereiche speichern') }}
            </button>
        </div>

        <!-- AKTIVITÄTEN -->
        <div v-if="activeTab === 'activities'" class="surface-card p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">{{ settingsText('activities.title', 'Meine Aktivitäten') }}</h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ settingsText('activities.description', 'Hier erscheinen nur Aktionen, die von deinem eigenen Konto erstellt wurden.') }}
                    </p>
                </div>
                <span class="text-sm font-semibold text-secondary">{{ settingsText('activities.count', '{count} Einträge', { count: activities.length }) }}</span>
            </div>

            <div v-if="activities.length" class="mt-5 divide-y divide-border rounded-lg border border-border bg-bg">
                <article
                    v-for="activity in activities"
                    :key="activity.id"
                    class="flex gap-3 p-4"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-buttonPrimary text-buttonTextPrimary">
                        <i class="las la-history text-lg"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <p class="font-semibold text-primary">{{ activityLabel(activity.type) }}</p>
                            <time class="text-xs text-secondary">{{ formatDate(activity.created_at) }}</time>
                        </div>
                        <p class="mt-1 text-sm text-secondary">{{ activityScope(activity) }}</p>
                        <p v-if="activityDescription(activity)" class="mt-2 line-clamp-2 text-sm text-primary">
                            {{ activityDescription(activity) }}
                        </p>
                    </div>
                </article>
            </div>

            <div v-else class="mt-5 rounded-lg border border-dashed border-border bg-bg p-6 text-sm text-secondary">
                {{ settingsText('activities.empty', 'Noch keine eigenen Aktivitäten vorhanden.') }}
            </div>
        </div>

        <!-- SICHERHEIT -->
        <div v-if="activeTab === 'security'" class="surface-card p-5 space-y-6">

            <UpdatePasswordForm />

            <SectionBorder />

            <TwoFactorAuthenticationForm
                :requires-confirmation="confirmsTwoFactorAuthentication"
            />

            <SectionBorder />

            <LogoutOtherBrowserSessionsForm :sessions="sessions || []" />

            <SectionBorder />

            <DeleteUserForm />

        </div>

        <!-- DESIGN -->
        <div v-if="activeTab === 'design'" class="surface-card p-5">
            <h2 class="text-sm font-semibold text-secondary mb-3">{{ settingsText('design.title', 'Design') }}</h2>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <button
                    v-for="themeOption in themeOptions"
                    :key="themeOption.key"
                    type="button"
                    class="rounded-lg border bg-bg p-4 text-left transition hover:border-borderHover hover:bg-muted"
                    :class="currentTheme === themeOption.key ? 'border-buttonPrimary ring-2 ring-buttonPrimary/20' : 'border-border'"
                    @click="updateTheme(themeOption.key)"
                >
                    <span class="flex items-center gap-2">
                        <span
                            v-for="color in themeOption.colors"
                            :key="color"
                            class="h-5 w-5 rounded-full border border-border"
                            :style="{ backgroundColor: color }"
                        ></span>
                    </span>
                    <span class="mt-3 block font-semibold text-primary">{{ themeOption.label }}</span>
                    <span class="mt-1 block text-xs text-secondary">{{ themeDescription(themeOption) }}</span>
                    <span v-if="currentTheme === themeOption.key" class="mt-3 inline-flex text-xs font-semibold text-buttonPrimary">
                        {{ settingsText('design.active', 'Aktiv') }}
                    </span>
                </button>
            </div>

        </div>

        <!-- SPRACHE -->
        <div v-if="activeTab === 'language'" class="surface-card relative z-20 overflow-visible p-5">
            <h2 class="text-sm font-semibold text-secondary mb-3">{{ t('Sprache') }}</h2>
            <p class="mb-3 text-sm text-secondary">
                {{ t('settings.language_description') }}
            </p>
            <LanguageDropdown align="start" />
        </div>

        <!-- BENACHRICHTIGUNGEN -->
        <div v-if="activeTab === 'notifications'" class="surface-card p-5">
            <form class="space-y-6" @submit.prevent="saveNotificationPreferences">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">
                        {{ settingsText('notification_preferences.eyebrow', 'Zustellung') }}
                    </p>
                    <h2 class="mt-1 text-xl font-semibold text-primary">
                        {{ settingsText('notification_preferences.title', 'Benachrichtigungen steuern') }}
                    </h2>
                    <p class="mt-2 max-w-3xl text-sm text-secondary">
                        {{ settingsText('notification_preferences.description', 'Lege zentral fest, welche Themen Airmius zustellt und wann Push-Nachrichten pausieren.') }}
                    </p>
                </div>

                <div
                    v-if="notificationNotice"
                    role="status"
                    class="rounded-lg border px-4 py-3 text-sm font-semibold"
                    :class="notificationNotice.type === 'success'
                        ? 'border-success/30 bg-success/10 text-success'
                        : 'border-error/30 bg-error/10 text-error'"
                >
                    {{ notificationNotice.message }}
                </div>

                <fieldset>
                    <legend class="text-sm font-semibold text-primary">
                        {{ settingsText('notification_preferences.channels_title', 'Kanäle und Themen') }}
                    </legend>
                    <p class="mt-1 text-sm text-secondary">
                        {{ settingsText('notification_preferences.channels_description', 'Push und E-Mail steuern die Zustellung; die weiteren Schalter filtern Themen.') }}
                    </p>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <label
                            v-for="channel in notificationChannelOptions"
                            :key="channel.key"
                            class="flex min-h-20 cursor-pointer items-start gap-3 rounded-xl border border-border bg-bg p-4 transition hover:border-borderHover"
                        >
                            <input
                                v-model="form.notification_channels[channel.key]"
                                type="checkbox"
                                class="mt-1 rounded border-border bg-inputBg text-buttonPrimary focus:ring-buttonPrimary"
                            >
                            <span class="min-w-0">
                                <span class="flex items-center gap-2 font-semibold text-primary">
                                    <i :class="[channel.icon, 'text-lg text-air-blue']"></i>
                                    {{ settingsText(`notification_preferences.channels.${channel.key}.label`, channel.key) }}
                                </span>
                                <span class="mt-1 block text-xs leading-5 text-secondary">
                                    {{ settingsText(`notification_preferences.channels.${channel.key}.hint`, '') }}
                                </span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <div class="grid gap-4 rounded-xl border border-border bg-bg p-4 md:grid-cols-[minmax(0,1fr)_minmax(14rem,20rem)] md:items-end">
                    <div>
                        <label for="notification-quiet-time" class="font-semibold text-primary">
                            {{ settingsText('notification_preferences.quiet_title', 'Ruhezeit') }}
                        </label>
                        <p class="mt-1 text-sm text-secondary">
                            {{ settingsText('notification_preferences.quiet_description', 'Normale Push-Nachrichten werden gesammelt und nach der Ruhezeit zugestellt.') }}
                        </p>
                    </div>
                    <select
                        id="notification-quiet-time"
                        v-model="form.notification_quiet_time"
                        class="min-h-11 w-full rounded-lg border border-border bg-inputBg px-3 text-primary focus:border-buttonPrimary focus:ring-buttonPrimary"
                    >
                        <option v-for="option in notificationQuietOptions" :key="option" :value="option">
                            {{ settingsText(`notification_preferences.quiet.${option}`, option) }}
                        </option>
                    </select>
                </div>

                <p class="rounded-lg border border-info/30 bg-info/10 px-4 py-3 text-sm text-primary">
                    <i class="las la-shield-alt me-2 text-info" aria-hidden="true"></i>
                    {{ settingsText('notification_preferences.critical_notice', 'Kritische Sicherheitsmeldungen bleiben aktiv und dürfen Ruhezeiten umgehen.') }}
                </p>

                <button
                    type="submit"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-buttonPrimary px-5 py-2.5 font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing
                        ? settingsText('notification_preferences.saving', 'Speichert …')
                        : settingsText('notification_preferences.save', 'Einstellungen speichern') }}
                </button>
            </form>
        </div>

        <!-- ADRESSE -->
        <div v-if="activeTab === 'address'" class="surface-card p-5">

            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveAddress">

                <div class="md:col-span-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">
                        {{ settingsText('address.title', 'Adresse') }}
                    </h2>
                </div>

                <div
                    v-if="addressNotice"
                    class="md:col-span-2 rounded-lg border px-4 py-3 text-sm"
                    :class="addressNotice.type === 'success'
                        ? 'border-success/30 bg-success/10 text-success'
                        : 'border-error/30 bg-error/10 text-error'"
                >
                    {{ addressNotice.message }}
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">
                        {{ settingsText('address.country', 'Land') }} <span class="text-error">*</span>
                    </label>
                    <CountrySelect v-model="form.country" required :label="settingsText('address.country', 'Land')" />
                    <p v-if="form.errors.country" class="mt-1 text-sm text-error">{{ form.errors.country }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">{{ settingsText('address.city', 'Stadt') }}</label>
                    <input v-model="form.city" class="input" />
                    <p v-if="form.errors.city" class="mt-1 text-sm text-error">{{ form.errors.city }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">{{ settingsText('address.postal_code', 'PLZ') }}</label>
                    <input v-model="form.postal_code" class="input" />
                    <p v-if="form.errors.postal_code" class="mt-1 text-sm text-error">{{ form.errors.postal_code }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">{{ settingsText('address.state', 'Bundesland') }}</label>
                    <input v-model="form.state" class="input" />
                    <p v-if="form.errors.state" class="mt-1 text-sm text-error">{{ form.errors.state }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">{{ settingsText('address.street', 'Straße') }}</label>
                    <input v-model="form.street" class="input" />
                    <p v-if="form.errors.street" class="mt-1 text-sm text-error">{{ form.errors.street }}</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">{{ settingsText('address.house_number', 'Hausnummer') }}</label>
                    <input v-model="form.house_number" class="input" />
                    <p v-if="form.errors.house_number" class="mt-1 text-sm text-error">{{ form.errors.house_number }}</p>
                </div>

                <div class="md:col-span-2 mt-4 border-t border-border pt-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">
                        {{ settingsText('address.event_defaults', 'Event-Defaults') }}
                    </h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ settingsText('address.event_defaults_description', 'Diese Werte werden automatisch für deine Eventliste genutzt, solange du dort keine eigenen Filter setzt.') }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">{{ settingsText('address.event_zone', 'Eventzone') }}</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input
                            v-model="form.event_radius_km"
                            class="input"
                            min="1"
                            max="500"
                            type="number"
                        />
                        <span class="text-sm font-semibold text-secondary">km</span>
                    </div>
                    <p class="mt-1 text-xs text-secondary">
                        {{ settingsText('address.event_zone_help', 'Aktuell adressbasiert über PLZ/Stadt/Vereinsadresse.') }}
                    </p>
                    <p v-if="form.errors.event_radius_km" class="mt-1 text-sm text-error">{{ form.errors.event_radius_km }}</p>
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-semibold text-primary">{{ settingsText('address.event_sports', 'Sportarten für Eventvorschläge') }}</label>
                    <div class="mt-2 grid max-h-64 gap-2 overflow-y-auto rounded-lg border border-border bg-bg p-3 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="sport in sports"
                            :key="sport.id"
                            type="button"
                            class="rounded-lg border px-3 py-2 text-left text-sm transition"
                            :class="(form.event_default_sport_ids || []).map(Number).includes(Number(sport.id))
                                ? 'border-buttonPrimary bg-buttonPrimary/10 text-primary'
                                : 'border-border bg-inputBg text-secondary hover:border-borderHover hover:text-primary'"
                            @click="toggleDefaultSport(sport.id)"
                        >
                            <span class="block font-semibold">{{ sport.name }}</span>
                            <span class="text-xs">{{ sport.category || settingsText('address.sport_fallback', 'Sport') }}</span>
                        </button>
                    </div>
                    <p v-if="form.errors.event_default_sport_ids" class="mt-1 text-sm text-error">{{ form.errors.event_default_sport_ids }}</p>
                </div>

                <div class="md:col-span-2">
                    <button class="btn-primary" :disabled="form.processing">
                        {{ settingsText('address.save_button', 'Adresse & Event-Defaults speichern') }}
                    </button>
                </div>

            </form>
        </div>

        <!-- PRIVATSPHÄRE -->
        <div v-if="activeTab === 'privacy'" class="surface-card p-5">
            <form class="space-y-5" @submit.prevent="saveAddress">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ settingsText('privacy.title', 'Privatsphäre') }}</h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ settingsText('privacy.description', 'Lege fest, wer dein Profil sehen, dich direkt kontaktieren oder dir Freundschaftsanfragen senden darf.') }}
                    </p>
                </div>

                <div
                    v-if="privacyNotice"
                    class="rounded-lg border px-4 py-3 text-sm"
                    :class="privacyNotice.type === 'success'
                        ? 'border-success/30 bg-success/10 text-success'
                        : 'border-error/30 bg-error/10 text-error'"
                >
                    {{ privacyNotice.message }}
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">{{ t('feed.default_visibility') }}</span>
                    <select v-model="form.default_post_visibility" class="input">
                        <option v-for="visibility in ['public', 'friends', 'private', 'organization', 'team']" :key="visibility" :value="visibility">{{ t(`feed.visibility.${visibility}`) }}</option>
                    </select>
                    <p v-if="form.errors.default_post_visibility" class="mt-1 text-sm text-error">{{ form.errors.default_post_visibility }}</p>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-primary">{{ settingsText('privacy.profile_visibility', 'Profil-Sichtbarkeit') }}</span>
                    <select v-model="form.profile_visibility" class="input">
                        <option value="public">{{ settingsText('privacy.options.public', 'Öffentlich') }}</option>
                        <option value="private">{{ settingsText('privacy.options.private', 'Privat') }}</option>
                        <option value="friends">{{ settingsText('privacy.options.friends', 'Nur Freunde') }}</option>
                    </select>
                    <p class="mt-1 text-xs text-secondary">
                        {{ settingsText('privacy.profile_visibility_help', 'Diese Einstellung steuert, ob andere dein Profil und deine Profilinhalte sehen können.') }}
                    </p>
                    <p v-if="form.errors.profile_visibility" class="mt-1 text-sm text-error">{{ form.errors.profile_visibility }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">{{ settingsText('privacy.direct_messages', 'Nachrichten erhalten') }}</span>
                    <select v-model="form.direct_message_privacy" class="input">
                        <option value="everyone">{{ settingsText('privacy.options.everyone', 'Alle angemeldeten Personen') }}</option>
                        <option value="friends">{{ settingsText('privacy.options.friends', 'Nur Freunde') }}</option>
                    </select>
                    <p v-if="form.errors.direct_message_privacy" class="mt-1 text-sm text-error">{{ form.errors.direct_message_privacy }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">{{ settingsText('privacy.friend_requests', 'Freundschaftsanfragen erhalten') }}</span>
                    <select v-model="form.friend_request_privacy" class="input">
                        <option value="everyone">{{ settingsText('privacy.options.everyone', 'Alle angemeldeten Personen') }}</option>
                        <option value="friends">{{ settingsText('privacy.options.friends', 'Nur Freunde') }}</option>
                    </select>
                    <p v-if="form.errors.friend_request_privacy" class="mt-1 text-sm text-error">{{ form.errors.friend_request_privacy }}</p>
                </label>

                <div class="rounded-lg border border-border bg-bg p-4">
                    <h3 class="text-sm font-semibold text-primary">{{ settingsText('privacy.ads_title', 'Werbung & Messung') }}</h3>
                    <p class="mt-1 text-sm text-secondary">
                        {{ settingsText('privacy.ads_description', 'Ohne Einwilligung zeigen wir nur kontextuelle Anzeigen und speichern keine personalisierten Retargeting-Signale.') }}
                    </p>
                    <label class="mt-4 flex items-start gap-3 text-sm text-primary">
                        <input v-model="form.ads_personalization_consent" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">{{ settingsText('privacy.ads_personalization', 'Personalisierte Anzeigen erlauben') }}</span>
                            <span class="text-xs text-secondary">{{ settingsText('privacy.ads_personalization_help', 'Nutzt z. B. vorherige Marketplace-Interessen, um passendere Anzeigen zu zeigen.') }}</span>
                        </span>
                    </label>
                    <label class="mt-4 flex items-start gap-3 text-sm text-primary">
                        <input v-model="form.ads_measurement_consent" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">{{ settingsText('privacy.ads_measurement', 'Conversion-Messung erlauben') }}</span>
                            <span class="text-xs text-secondary">{{ settingsText('privacy.ads_measurement_help', 'Ordnet Klicks anonymisierten Kampagnenereignissen wie Checkout oder Kauf zu.') }}</span>
                        </span>
                    </label>
                    <div class="mt-4 border-t border-border pt-4">
                        <label class="flex items-start gap-3 text-sm text-primary">
                            <input v-model="form.product_analytics_consent" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                            <span>
                                <span class="block font-semibold">{{ settingsText('privacy.product_analytics', 'Anonyme Produktverbesserung erlauben') }}</span>
                                <span class="text-xs leading-5 text-secondary">{{ settingsText('privacy.product_analytics_help', 'Erlaubt ausschließlich zusammengefasste Nutzungskennzahlen aus vorhandenen Airmius-Aktionen. Werbeeinwilligungen werden nicht wiederverwendet; es gibt keine zusätzlichen Tracking-Cookies oder SDKs.') }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-bg p-4">
                    <h3 class="text-sm font-semibold text-primary">
                        {{ settingsText('privacy.connected_providers', 'Verbundene Anbieter') }}
                    </h3>
                    <p class="mt-1 text-sm text-secondary">
                        {{ settingsText('privacy.connected_providers_help', 'Hier siehst du verbundene Login- und Sportanbieter. Tokens und externe Kennungen werden nie angezeigt.') }}
                    </p>
                    <div v-if="connectedPrivacyProviders.length" class="mt-4 grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="(provider, index) in connectedPrivacyProviders"
                            :key="`${provider.kind}-${provider.provider}-${index}`"
                            class="flex items-center gap-3 rounded-lg border border-border bg-surface px-3 py-2"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
                                <i :class="provider.kind === 'sport' ? 'las la-running' : 'las la-sign-in-alt'"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-primary">{{ privacyProviderName(provider.provider) }}</span>
                                <span class="block text-xs text-secondary">
                                    {{ provider.kind === 'sport'
                                        ? settingsText('privacy.sport_provider', 'Sportdaten-Anbieter')
                                        : settingsText('privacy.login_provider', 'Anmeldekonto') }}
                                </span>
                            </span>
                            <i class="las la-check-circle text-success" aria-hidden="true"></i>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-secondary">
                        {{ settingsText('privacy.no_connected_providers', 'Es ist aktuell kein Anbieter verbunden.') }}
                    </p>
                    <button type="button" class="btn-secondary mt-4" @click="setActiveTab('integrations')">
                        {{ settingsText('privacy.manage_providers', 'Anbieter verwalten') }}
                    </button>
                </div>

                <div class="rounded-lg border border-border bg-bg p-4">
                    <h3 class="text-sm font-semibold text-primary">{{ settingsText('privacy.rights_title', 'Datenschutzrechte') }}</h3>
                    <p class="mt-1 text-sm text-secondary">
                        {{ settingsText('privacy.rights_description', 'Export, Berichtigung, Löschung und Widerruf sind über Konto und Einstellungen erreichbar.') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a
                            :href="route('auth.settings.privacy.export')"
                            class="btn-secondary"
                        >
                            {{ settingsText('privacy.export_button', 'Datenauskunft herunterladen') }}
                        </a>
                        <button
                            type="button"
                            class="btn-secondary"
                            @click="withdrawPrivacyConsents"
                        >
                            {{ settingsText('privacy.withdraw_button', 'Einwilligungen widerrufen') }}
                        </button>
                        <Link :href="route('auth.settings.privacy.erasure')" class="btn-secondary">
                            {{ settingsText('privacy.erase_data_button', 'Daten löschen, Konto behalten') }}
                        </Link>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button class="btn-primary" :disabled="form.processing">
                        {{ settingsText('privacy.save_button', 'Privatsphäre speichern') }}
                    </button>
                    <Link :href="route('profile.show')" class="btn-secondary">
                        {{ settingsText('privacy.correct_profile_button', 'Profildaten berichtigen') }}
                    </Link>
                </div>
            </form>
        </div>

        <div v-if="activeTab === 'billing'" class="space-y-5">
            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ settingsText('billing.subscriptions_title', 'Meine Airmius Abos') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ settingsText('billing.subscriptions_description', 'Aktuelle persönliche Airmius Pläne und Laufzeiten.') }}
                </p>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div v-for="subscription in currentUserSubscriptions" :key="subscription.id" class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">{{ subscription.plan?.name || settingsText('billing.airmius_subscription', 'Airmius Abo') }}</p>
                                <p class="mt-1 text-sm text-secondary">{{ invoiceStatusLabel(subscription.status) }}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap justify-end gap-2">
                                <button
                                    v-if="canOpenStripePortal(subscription)"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                    @click="openProviderPortal(subscription)"
                                >
                                    {{ settingsText('billing.payment_portal', 'Zahlungsportal') }}
                                </button>
                                <p
                                    v-else-if="subscription.payment_provider === 'stripe'"
                                    class="text-xs text-secondary"
                                >
                                    {{ settingsText('billing.payment_portal_inactive', 'Zahlungsportal ist für dieses Abo momentan nicht aktiv.') }}
                                </p>

                                <button
                                    v-if="isSubscriptionCancellable(subscription)"
                                    type="button"
                                    class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                    @click="cancelSubscription(subscription)"
                                >
                                    {{ settingsText('billing.cancel', 'Kündigen') }}
                                </button>
                            </div>
                        </div>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-secondary">{{ settingsText('billing.payment_method', 'Zahlungsart') }}</dt>
                                <dd class="text-primary">{{ subscription.payment_provider || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-secondary">{{ settingsText('billing.runs_until', 'Läuft bis') }}</dt>
                                <dd class="text-primary">{{ formatDate(subscription.current_period_ends_at || subscription.trial_ends_at) }}</dd>
                            </div>
                            <div v-if="subscription.cancels_at" class="flex justify-between gap-3">
                                <dt class="text-secondary">{{ settingsText('billing.cancelled_at', 'Gekündigt zum') }}</dt>
                                <dd class="text-primary">{{ formatDate(subscription.cancels_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <p v-if="!currentUserSubscriptions.length" class="mt-4 text-sm text-secondary">
                    {{ settingsText('billing.no_subscription', 'Du hast noch kein persönliches Airmius Abo.') }}
                </p>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase text-secondary">{{ settingsText('billing.summary.open', 'Offen') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ billingSummary.open_count || 0 }}</p>
                    <p class="mt-1 text-xs text-secondary">{{ formatMoney(billingSummary.open_amount) }}</p>
                </div>
                <div class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase text-secondary">{{ settingsText('billing.summary.paid', 'Bezahlt') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ billingSummary.paid_count || 0 }}</p>
                    <p class="mt-1 text-xs text-secondary">{{ formatMoney(billingSummary.paid_amount) }}</p>
                </div>
                <div class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase text-secondary">{{ settingsText('billing.summary.overdue', 'Überfällig') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ billingSummary.overdue_count || 0 }}</p>
                    <p class="mt-1 text-xs text-secondary">{{ formatMoney(billingSummary.overdue_amount) }}</p>
                </div>
                <div class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase text-secondary">{{ settingsText('billing.summary.total', 'Alle Rechnungen') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ billingSummary.total_count || 0 }}</p>
                    <p class="mt-1 text-xs text-secondary">
                        {{ billingSummary.club_invoice_count || 0 }} Verein · {{ billingSummary.subscription_invoice_count || 0 }} Airmius
                    </p>
                </div>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ settingsText('billing.subscription_invoices_title', 'Airmius Abo-Rechnungen') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ settingsText('billing.subscription_invoices_description', 'Rechnungen für Airmius Pläne und Plattform-Abos.') }}
                </p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.number', 'Nr.') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.plan', 'Plan') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.club', 'Verein') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.amount', 'Betrag') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.due', 'Fällig') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.status', 'Status') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.pay', 'Zahlen') }}</th>
                                <th class="py-2 pr-4 text-right">PDF</th>
                                <th class="py-2 pr-4 text-right">{{ settingsText('billing.table.action', 'Aktion') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="invoice in billingHistory.subscription_invoices" :key="invoice.id">
                                <td class="py-3 pr-4 text-primary">{{ invoice.number }}</td>
                                <td class="py-3 pr-4 text-primary">{{ invoice.plan?.name || invoice.title || '-' }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoice.club?.name || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(Number(invoice.amount_cents || 0) / 100) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_at) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoiceStatusLabel(invoice.status) }}</td>
                                <td class="py-3 pr-4">
                                    <button
                                        v-if="isPayableClubInvoice(invoice) && hasAirmiusBank()"
                                        type="button"
                                        class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                        @click="openBankTransferModal('airmius', invoice)"
                                    >
                                        {{ settingsText('billing.bank_details', 'Bankdaten') }}
                                    </button>
                                    <span v-else-if="isPayableClubInvoice(invoice)" class="text-xs text-warning">{{ settingsText('billing.bank_details_missing', 'Bankdaten fehlen') }}</span>
                                    <span v-else class="text-xs text-secondary">-</span>
                                </td>
                                <td class="py-3 pr-4 text-right">
                                    <a :href="route('auth.subscription-invoices.download', invoice.id)" download class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted">
                                        Download
                                    </a>
                                </td>
                                <td class="py-3 pr-4 text-right">
                                    <div v-if="isOpenSubscriptionPayment(invoice) || canDeleteOpenSubscriptionPayment(invoice)" class="flex flex-wrap justify-end gap-2">
                                        <button
                                            v-if="isOpenSubscriptionPayment(invoice)"
                                            type="button"
                                            class="rounded-lg border border-warning px-3 py-1 text-xs font-semibold text-warning hover:bg-warning/10"
                                            @click="openPaymentActionModal('cancel', invoice)"
                                        >
                                            {{ settingsText('actions.cancel', 'Abbrechen') }}
                                        </button>
                                        <button
                                            v-if="canDeleteOpenSubscriptionPayment(invoice)"
                                            type="button"
                                            class="rounded-lg border border-error px-3 py-1 text-xs font-semibold text-error hover:bg-error/10"
                                            @click="openPaymentActionModal('delete', invoice)"
                                        >
                                            {{ settingsText('actions.delete', 'Löschen') }}
                                        </button>
                                    </div>
                                    <span v-else class="text-xs text-secondary">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!billingHistory.subscription_invoices.length" class="py-6 text-sm text-secondary">
                        {{ settingsText('billing.no_subscription_invoices', 'Noch keine Airmius Abo-Rechnungen vorhanden.') }}
                    </p>
                </div>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ settingsText('billing.my_invoices_title', 'Meine Rechnungen') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ settingsText('billing.my_invoices_description', 'Hier siehst du offene und bezahlte Vereinsbeiträge.') }}
                </p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.number', 'Nr.') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.club', 'Verein') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.title', 'Titel') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.amount', 'Betrag') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.due', 'Fällig') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.status', 'Status') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.pay', 'Zahlen') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="invoice in billingHistory.invoices" :key="invoice.id">
                                <td class="py-3 pr-4 text-primary">{{ invoice.number }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoice.club?.name || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ invoice.title || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(invoice.amount) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(invoice.due_date) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ invoiceStatusLabel(invoice.status) }}</td>
                                <td class="py-3 pr-4">
                                    <button
                                        v-if="isPayableClubInvoice(invoice) && hasClubBank(invoice)"
                                        type="button"
                                        class="rounded-lg border border-border px-3 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                        @click="openBankTransferModal('club', invoice)"
                                    >
                                        {{ settingsText('billing.bank_details', 'Bankdaten') }}
                                    </button>
                                    <span v-else-if="isPayableClubInvoice(invoice)" class="text-xs text-warning">{{ settingsText('billing.bank_details_missing', 'Bankdaten fehlen') }}</span>
                                    <span v-else class="text-xs text-secondary">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!billingHistory.invoices.length" class="py-6 text-sm text-secondary">
                        {{ settingsText('billing.no_invoices', 'Noch keine Rechnungen vorhanden.') }}
                    </p>
                </div>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ settingsText('billing.payment_history_title', 'Zahlungshistorie') }}</h2>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.date', 'Datum') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.club', 'Verein') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.invoice', 'Rechnung') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.amount', 'Betrag') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('billing.table.status', 'Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="payment in billingHistory.payments" :key="payment.id">
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(payment.paid_at || payment.created_at) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ payment.club?.name || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ payment.invoice?.number || '-' }}</td>
                                <td class="py-3 pr-4 text-primary">{{ formatMoney(payment.amount) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ payment.status === 'paid' ? invoiceStatusLabel('paid') : invoiceStatusLabel(payment.status) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!billingHistory.payments.length" class="py-6 text-sm text-secondary">
                        {{ settingsText('billing.no_payments', 'Noch keine Zahlungen markiert.') }}
                    </p>
                </div>
            </section>
        </div>

        <div v-if="activeTab === 'integrations'" class="space-y-5">
            <div
                v-if="integrationNotice"
                role="status"
                aria-live="polite"
                class="rounded-xl border px-4 py-3 text-sm font-semibold"
                :class="integrationNotice.type === 'success'
                    ? 'border-success/40 bg-success/10 text-success'
                    : 'border-danger/40 bg-danger/10 text-danger'"
            >
                {{ integrationNotice.message }}
            </div>
            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ settingsText('integrations.login_title', 'Login-Verknüpfungen') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ settingsText('integrations.login_description', 'Nutze Google oder Outlook für eine schnelle Anmeldung.') }}
                </p>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div
                        class="rounded-lg border p-4 transition"
                        :class="socialAccountFor('google') ? 'border-success/40 bg-success/10' : 'border-border bg-bg'"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">Google</p>
                                <p class="text-sm text-secondary">
                                    {{ socialAccountFor('google')?.email || settingsText('integrations.not_connected', 'Noch nicht verbunden') }}
                                </p>
                            </div>
                            <span
                                v-if="socialAccountFor('google')"
                                class="rounded-lg bg-success px-3 py-2 text-sm font-semibold text-white"
                            >
                                {{ settingsText('integrations.connected', 'Verbunden') }}
                            </span>
                            <a v-else :href="route('social-auth.redirect', 'google')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                                {{ settingsText('integrations.connect', 'Verbinden') }}
                            </a>
                        </div>
                    </div>

                    <div
                        class="rounded-lg border p-4 transition"
                        :class="socialAccountFor('microsoft') ? 'border-success/40 bg-success/10' : 'border-border bg-bg'"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-primary">Outlook / Microsoft</p>
                                <p class="text-sm text-secondary">
                                    {{ socialAccountFor('microsoft')?.email || settingsText('integrations.not_connected', 'Noch nicht verbunden') }}
                                </p>
                            </div>
                            <span
                                v-if="socialAccountFor('microsoft')"
                                class="rounded-lg bg-success px-3 py-2 text-sm font-semibold text-white"
                            >
                                {{ settingsText('integrations.connected', 'Verbunden') }}
                            </span>
                            <a v-else :href="route('social-auth.redirect', 'microsoft')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                                {{ settingsText('integrations.connect', 'Verbinden') }}
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="surface-card p-5">
                <h2 class="text-lg font-semibold text-primary">{{ settingsText('integrations.sport_apps_title', 'Sportprogramme synchronisieren') }}</h2>
                <p class="mt-1 text-sm text-secondary">
                    {{ settingsText('integrations.sport_apps_description', 'Verknüpfe Sport-Apps, damit Trainingsdaten sicher in dein Airmius Profil synchronisiert werden.') }}
                </p>

                <div class="mt-4 grid gap-3 lg:grid-cols-3">
                    <article
                        v-for="(provider, key) in sportIntegrationState.providers"
                        :key="key"
                        class="rounded-lg border p-4 transition"
                        :class="connectedAccountFor(key)?.status === 'connected' ? 'border-success/40 bg-bg' : 'border-border bg-bg'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-primary">{{ provider.label }}</p>
                                <p class="mt-1 text-sm text-secondary">{{ sportIntegrationProviderDescription(key, provider) }}</p>
                            </div>
                            <span
                                v-if="connectedAccountFor(key)"
                                class="shrink-0 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-semibold"
                                :class="integrationStatusClass(connectedAccountFor(key).status)"
                            >
                                {{ integrationStatusLabel(connectedAccountFor(key).status) }}
                            </span>
                        </div>

                        <p v-if="connectedAccountFor(key)?.last_synced_at" class="mt-3 text-xs text-secondary">
                            {{ settingsText('integrations.last_synced', 'Zuletzt synchronisiert: {date}', { date: formatDate(connectedAccountFor(key).last_synced_at) }) }}
                        </p>
                        <p v-if="connectedAccountFor(key)?.sync_summary?.message" class="mt-2 text-xs text-secondary">
                            {{ connectedAccountFor(key).sync_summary.message }}
                        </p>
                        <dl v-if="connectedAccountFor(key)?.sync_summary?.google_status || connectedAccountFor(key)?.sync_summary?.bucket_count !== undefined" class="mt-2 space-y-1 text-xs text-secondary">
                            <div v-if="connectedAccountFor(key)?.sync_summary?.google_status" class="flex gap-2">
                                <dt>{{ settingsText('integrations.google_status', 'Google Status:') }}</dt>
                                <dd class="font-semibold text-primary">{{ connectedAccountFor(key).sync_summary.google_status }}</dd>
                            </div>
                            <div v-if="connectedAccountFor(key)?.sync_summary?.google_error" class="flex gap-2">
                                <dt>{{ settingsText('integrations.google_error', 'Google Fehler:') }}</dt>
                                <dd class="font-semibold text-primary">{{ connectedAccountFor(key).sync_summary.google_error }}</dd>
                            </div>
                            <div v-if="connectedAccountFor(key)?.sync_summary?.bucket_count !== undefined" class="flex gap-2">
                                <dt>{{ settingsText('integrations.day_ranges', 'Tagesbereiche:') }}</dt>
                                <dd class="font-semibold text-primary">{{ connectedAccountFor(key).sync_summary.bucket_count }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a
                                v-if="!connectedAccountFor(key) && provider.status === 'live_oauth'"
                                :href="route('auth.sport-integrations.connect', provider.route_key || key)"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                            >
                                {{ integrationProviderActionLabel(provider) }}
                            </a>
                            <button
                                v-else-if="!connectedAccountFor(key)"
                                type="button"
                                class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary disabled:cursor-wait disabled:opacity-60"
                                :disabled="busyIntegrationProviders.has(key)"
                                @click="requestIntegrationProvider(key, provider)"
                            >
                                {{ integrationProviderActionLabel(provider) }}
                            </button>
                            <button
                                v-if="connectedAccountFor(key)?.status === 'connected' && provider.supports_direct_sync"
                                type="button"
                                class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary"
                                :disabled="busyIntegrationAccounts.has(connectedAccountFor(key).id)"
                                @click="syncIntegration(connectedAccountFor(key))"
                            >
                                {{ settingsText('integrations.check_sync', 'Sync prüfen') }}
                            </button>
                            <button
                                v-if="connectedAccountFor(key)"
                                type="button"
                                class="rounded-lg border border-danger/40 px-3 py-2 text-sm font-semibold text-danger"
                                :disabled="busyIntegrationAccounts.has(connectedAccountFor(key).id)"
                                @click="openDisconnectIntegrationModal(connectedAccountFor(key))"
                            >
                                {{ settingsText('actions.remove', 'Entfernen') }}
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section class="surface-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-primary">{{ settingsText('integrations.activities.title', 'Importierte Aktivitäten') }}</h2>
                    <button
                        v-if="sportIntegrationState.activities.length"
                        type="button"
                        class="rounded-lg border border-danger/40 px-3 py-2 text-sm font-semibold text-danger"
                        @click="openSportActivityDeleteModal()"
                    >
                        {{ settingsText('integrations.activities.delete_all', 'Alle löschen') }}
                    </button>
                </div>
                <form class="mt-5 rounded-xl border border-border bg-muted/30 p-4" @submit.prevent="storeManualActivity">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-secondary">{{ settingsText('integrations.manual_activity.title', 'Manuell eintragen') }}</h3>
                            <p class="mt-1 text-sm text-secondary">
                                {{ settingsText('integrations.manual_activity.description', 'Füge eigene Trainingseinheiten hinzu, auch wenn keine Sport-App verbunden ist.') }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                            :disabled="manualActivitySaving"
                        >
                            {{ settingsText('integrations.manual_activity.save', 'Training speichern') }}
                        </button>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <label class="block text-sm font-semibold text-primary">
                            {{ settingsText('integrations.manual_activity.name', 'Name') }}
                            <input
                                v-model="manualActivityForm.title"
                                type="text"
                                maxlength="120"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                                :placeholder="settingsText('integrations.manual_activity.name_placeholder', 'z. B. Lauftraining')"
                                required
                            />
                            <span v-if="manualActivityForm.errors.title" class="mt-1 block text-xs text-danger">
                                {{ manualActivityForm.errors.title }}
                            </span>
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            {{ settingsText('integrations.manual_activity.sport_type', 'Sportart') }}
                            <select
                                v-model="manualActivityForm.activity_type"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                            >
                                <option v-for="type in manualActivityTypeOptions" :key="type" :value="type">
                                    {{ manualActivityTypeLabel(type) }}
                                </option>
                            </select>
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            {{ settingsText('integrations.manual_activity.date_time', 'Datum und Zeit') }}
                            <input
                                v-model="manualActivityForm.started_at"
                                type="datetime-local"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                                required
                            />
                            <span v-if="manualActivityForm.errors.started_at" class="mt-1 block text-xs text-danger">
                                {{ manualActivityForm.errors.started_at }}
                            </span>
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            {{ settingsText('integrations.manual_activity.image', 'Bild') }}
                            <input
                                ref="manualActivityImageInput"
                                type="file"
                                accept="image/*"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary"
                                @change="setManualActivityImage"
                            />
                            <span v-if="manualActivityForm.errors.image" class="mt-1 block text-xs text-danger">
                                {{ manualActivityForm.errors.image }}
                            </span>
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            {{ settingsText('integrations.manual_activity.duration_minutes', 'Dauer in Minuten') }}
                            <input
                                v-model="manualActivityForm.duration_minutes"
                                type="number"
                                min="0"
                                max="14400"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                                placeholder="60"
                            />
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            {{ settingsText('integrations.manual_activity.distance_km', 'Distanz in km') }}
                            <input
                                v-model="manualActivityForm.distance_km"
                                type="number"
                                min="0"
                                max="10000"
                                step="0.01"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                                placeholder="5,00"
                            />
                        </label>

                        <label class="block text-sm font-semibold text-primary">
                            {{ settingsText('integrations.manual_activity.calories', 'Kalorien') }}
                            <input
                                v-model="manualActivityForm.calories"
                                type="number"
                                min="0"
                                max="200000"
                                class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                                placeholder="450"
                            />
                        </label>
                    </div>
                </form>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-secondary">
                            <tr>
                                <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.image', 'Bild') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.date', 'Datum') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.time', 'Zeit') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.source', 'Quelle') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.sport_type', 'Sportart') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.duration', 'Dauer') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.distance', 'Distanz') }}</th>
                                <th class="py-2 pr-4">{{ settingsText('integrations.activities.table.calories', 'Kalorien') }}</th>
                                <th class="py-2 pr-4 text-right">{{ settingsText('integrations.activities.table.action', 'Aktion') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="activity in sportIntegrationState.activities" :key="activity.id">
                                <td class="py-3 pr-4">
                                    <img
                                        v-if="activity.image_url"
                                        :src="activity.image_url"
                                        alt=""
                                        class="h-12 w-12 rounded-lg border border-border object-cover"
                                    />
                                    <span v-else class="inline-flex h-12 w-12 items-center justify-center rounded-lg border border-border text-xs text-secondary">
                                        -
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDate(activity.started_at) }}</td>
                                <td class="py-3 pr-4 text-secondary">
                                    {{ sportActivityTime(activity) }}
                                </td>
                                <td class="py-3 pr-4 text-secondary">{{ formatProvider(activity.provider) }}</td>
                                <td class="py-3 pr-4">
                                    <p class="font-semibold text-primary">{{ sportActivityTitle(activity) }}</p>
                                    <p v-if="sportActivitySubtitle(activity)" class="mt-1 text-xs text-secondary">
                                        {{ sportActivitySubtitle(activity) }}
                                    </p>
                                </td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDuration(activity.duration_seconds) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ formatDistance(activity.distance_meters) }}</td>
                                <td class="py-3 pr-4 text-secondary">{{ activity.calories || '-' }}</td>
                                <td class="py-3 pr-4">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            :disabled="busySportActivities.has(activity.id)"
                                            @click="openSportActivityEditModal(activity)"
                                        >
                                            {{ settingsText('actions.rename', 'Umbenennen') }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger"
                                            :disabled="busySportActivities.has(activity.id)"
                                            @click="openSportActivityDeleteModal(activity)"
                                        >
                                            {{ settingsText('actions.delete', 'Löschen') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!sportIntegrationState.activities.length" class="py-6 text-sm text-secondary">
                        {{ settingsText('integrations.activities.empty', 'Noch keine Aktivitäten importiert.') }}
                    </p>
                </div>
            </section>
        </div>

        <DeleteConfirmModal
            :show="sportProfileRemoveModal.show"
            :title="sportProfileText('remove_sport', 'Sportart entfernen')"
            :message="sportProfileText('remove_message', 'Möchtest du {sport} aus deinem Sportprofil entfernen? Die hinterlegten Leistungsdaten werden gelöscht.', { sport: sportProfileRemoveModal.profile?.sport?.name || sportProfileText('this_sport', 'diese Sportart') })"
            :confirm-text="sportProfileText('remove_confirm', 'entfernen')"
            :cancel-text="sportProfileText('back', 'Zurück')"
            @confirm="confirmSportProfileRemove"
            @cancel="closeSportProfileRemoveModal"
        />

        <DeleteConfirmModal
            :show="openPaymentModal.show"
            :title="openPaymentModalTitle()"
            :message="openPaymentModalMessage()"
            :confirm-text="openPaymentModalConfirmText()"
            :cancel-text="settingsText('actions.back', 'Zurück')"
            @confirm="confirmOpenPaymentAction"
            @cancel="closeOpenPaymentModal"
        />

        <DeleteConfirmModal
            :show="subscriptionCancelModal.show"
            :title="settingsText('billing.cancel_subscription.title', 'Abo kündigen')"
            :message="subscriptionCancelModalMessage()"
            :confirm-text="settingsText('billing.cancel_subscription.confirm', 'kündigen')"
            :cancel-text="settingsText('actions.back', 'Zurück')"
            @confirm="confirmSubscriptionCancel"
            @cancel="closeSubscriptionCancelModal"
        />

        <DeleteConfirmModal
            :show="disconnectIntegrationModal.show"
            :title="settingsText('integrations.disconnect.title', 'Sport-App entfernen')"
            :message="settingsText('integrations.disconnect.message', 'Bist du sicher, dass du diese Sport-App-Verknüpfung entfernen möchtest? Gespeicherte Tokens werden gelöscht und die App muss danach neu verbunden werden.')"
            :confirm-text="settingsText('actions.remove', 'Entfernen')"
            :cancel-text="settingsText('actions.cancel', 'Abbrechen')"
            @confirm="disconnectIntegration(disconnectIntegrationModal.account)"
            @cancel="closeDisconnectIntegrationModal"
        />

        <DeleteConfirmModal
            :show="sportActivityDeleteModal.show"
            :title="sportActivityDeleteTitle()"
            :message="sportActivityDeleteMessage()"
            :confirm-text="settingsText('actions.delete', 'Löschen')"
            :cancel-text="settingsText('actions.back', 'Zurück')"
            @confirm="confirmSportActivityDelete"
            @cancel="closeSportActivityDeleteModal"
        />

        <div
            v-if="sportActivityEditModal.show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-6"
            @click.self="closeSportActivityEditModal"
        >
            <form
                class="w-full max-w-lg rounded-xl border border-border bg-bg p-5 shadow-2xl"
                @submit.prevent="updateSportActivityTitle"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ settingsText('integrations.activities.rename_title', 'Aktivität umbenennen') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ settingsText('integrations.activities.rename_description', 'Der neue Name wird nur in Airmius gespeichert.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-3 py-1 text-sm font-semibold text-primary hover:bg-muted"
                        @click="closeSportActivityEditModal"
                    >
                        {{ settingsText('actions.close', 'Schließen') }}
                    </button>
                </div>

                <label class="mt-5 block text-sm font-semibold text-primary" for="sport-activity-title">
                    {{ settingsText('integrations.manual_activity.name', 'Name') }}
                </label>
                <input
                    id="sport-activity-title"
                    v-model="sportActivityEditForm.title"
                    type="text"
                    maxlength="120"
                    class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary"
                    required
                />
                <p v-if="sportActivityEditForm.errors.title" class="mt-2 text-sm text-danger">
                    {{ sportActivityEditForm.errors.title }}
                </p>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary"
                        @click="closeSportActivityEditModal"
                    >
                        {{ settingsText('actions.cancel', 'Abbrechen') }}
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                        :disabled="sportActivityEditForm.processing"
                    >
                        {{ settingsText('actions.save', 'Speichern') }}
                    </button>
                </div>
            </form>
        </div>

        <div
            v-if="bankTransferModal.show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-6"
            @click.self="closeBankTransferModal"
        >
            <div class="w-full max-w-lg rounded-xl border border-border bg-bg p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ settingsText('billing.bank_transfer_title', 'Per Überweisung zahlen') }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ settingsText('billing.bank_transfer_description', 'Nutze diese Daten für deine Banküberweisung.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-border px-3 py-1 text-sm font-semibold text-primary hover:bg-muted"
                        @click="closeBankTransferModal"
                    >
                        {{ settingsText('actions.close', 'Schließen') }}
                    </button>
                </div>

                <dl class="mt-5 divide-y divide-border rounded-lg border border-border bg-card">
                    <div
                        v-for="[label, value] in bankTransferRows()"
                        :key="label"
                        class="grid gap-2 px-4 py-3 text-sm sm:grid-cols-[150px_1fr]"
                    >
                        <dt class="text-secondary">{{ label }}</dt>
                        <dd class="break-words font-semibold text-primary sm:text-right">{{ value }}</dd>
                    </div>
                </dl>
            </div>
        </div>

    </div>
</template>

<style scoped>
.input {
    @apply mt-1 w-full rounded-lg border-border bg-inputBg text-primary;
}

.btn {
    @apply rounded-lg border border-border bg-muted px-4 py-2 hover:border-borderHover;
}

.btn-primary {
    @apply rounded-lg bg-buttonPrimary px-4 py-2 text-buttonTextPrimary;
}

.btn-secondary {
    @apply rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary transition hover:border-borderHover hover:bg-muted;
}
</style>

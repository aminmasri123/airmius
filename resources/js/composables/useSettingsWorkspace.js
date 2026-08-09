import { router, useForm } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import { useTheme } from '@/services/useTheme'
import {
    manualActivityTypeOptions,
    performanceSectionConfigs,
    runningBestTimeKeys,
    settingsThemeOptions as themeOptions,
    strengthPerformanceKeys,
    trainingDayAliases,
    trainingDayOptions,
    weekendAliases,
} from '@/support/settingsOptions'

export function useSettingsWorkspace({ props, t, te }) {
    // Tabs
    const settingsTabKeys = [
        'profile',
        'address',
        'billing',
        'roles',
        'activities',
        'integrations',
        'design',
        'language',
        'privacy',
        'sport-profile',
        'security',
    ]
    const initialTab = new URLSearchParams(window.location.search).get('tab')
    const activeTab = ref(settingsTabKeys.includes(initialTab) ? initialTab : 'profile')
    const setActiveTab = (tab) => {
        if (!settingsTabKeys.includes(tab)) return
    
        activeTab.value = tab
    
        const url = new URL(window.location.href)
    
        if (tab === 'profile') {
            url.searchParams.delete('tab')
        } else {
            url.searchParams.set('tab', tab)
        }
    
        window.history.replaceState({}, '', url)
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
    const manualActivityForm = useForm({
        title: '',
        activity_type: 'Training',
        started_at: '',
        duration_minutes: '',
        distance_km: '',
        calories: '',
        image: null,
    })
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
    const selectedSportProfileIds = ref(props.sportProfiles.filter((profile) => profile.has_profile).map((profile) => Number(profile.sport.id)))
    const activeSportProfileId = ref(selectedSportProfileIds.value[0] || '')
    const sportProfileRemoveModal = ref({
        show: false,
        profile: null,
    })
    const sportProfileForms = reactive({})
    
    props.sportProfiles.forEach((profile) => {
        sportProfileForms[profile.sport.id] = {
            status: profile.status || 'active',
            experience_level: profile.experience_level || 'beginner',
            visibility: profile.visibility || 'private',
            metrics: Object.fromEntries((profile.fields || []).map((field) => [field.key, profile.metrics?.[field.key] ?? ''])),
            metric_visibility: Object.fromEntries((profile.fields || []).map((field) => [field.key, profile.metric_visibility?.[field.key] || field.default_visibility || 'private'])),
            unknown_metrics: Object.fromEntries((profile.fields || []).map((field) => [field.key, (profile.metrics?._unknown_fields || []).includes(field.key)])),
        }
    })
    
    // Theme
    const { setTheme } = useTheme()
    const addressNotice = ref(null)
    
    // Form
    const form = useForm({
        theme: '',
        country: props.profileAddress.country || 'DE',
        street: props.profileAddress.street || '',
        house_number: props.profileAddress.house_number || '',
        postal_code: props.profileAddress.postal_code || '',
        city: props.profileAddress.city || '',
        state: props.profileAddress.state || '',
        event_radius_km: props.eventDefaults.radius_km || 20,
        event_default_sport_ids: props.eventDefaults.sport_ids || [],
        profile_visibility: props.privacySettings.profile_visibility || 'public',
        direct_message_privacy: props.privacySettings.direct_message_privacy || 'everyone',
        friend_request_privacy: props.privacySettings.friend_request_privacy || 'everyone',
        ads_personalization_consent: Boolean(props.privacySettings.ads_personalization_consent),
        ads_measurement_consent: Boolean(props.privacySettings.ads_measurement_consent),
        product_analytics_consent: Boolean(props.privacySettings.product_analytics_consent),
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
    
    const updateTheme = (theme) => {
        setTheme(theme)
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
    const settingsTabItems = computed(() => [
        { key: 'profile', label: t('Profil'), icon: 'las la-user-circle' },
        { key: 'address', label: t('Adresse'), icon: 'las la-map-marker' },
        { key: 'billing', label: t('Zahlungen'), icon: 'las la-receipt' },
        { key: 'roles', label: t('Rollen'), icon: 'las la-user-shield' },
        { key: 'activities', label: t('Aktivitäten'), icon: 'las la-running' },
        { key: 'integrations', label: t('Verknüpfungen'), icon: 'las la-link' },
        { key: 'design', label: t('Design'), icon: 'las la-palette' },
        { key: 'language', label: t('Sprache'), icon: 'las la-language' },
        { key: 'privacy', label: t('Privatsphäre'), icon: 'las la-lock' },
        { key: 'sport-profile', label: sportProfileText('tab', 'Sportprofil'), icon: 'las la-medal' },
        { key: 'security', label: t('Sicherheit'), icon: 'las la-shield-alt' },
    ])
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
    const todayDate = new Date().toISOString().slice(0, 10)
    const isExperienceDateField = (field) => field.type === 'date' || field.key === 'experience'
    const isTrainingDaysField = (field) => ['available_days', 'training_days'].includes(field.key)
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
    
    const formatMoney = (value) => new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value || 0))
    
    const formatDate = (value) => {
        if (!value) return '-'
        return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
    }
    
    const formatTime = (value) => {
        if (!value) return '-'
        return new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit' }).format(new Date(value))
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
        props.sportIntegrations.accounts.find((account) => account.provider === provider)
    
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
    
    const syncIntegration = (account) => {
        router.post(route('auth.sport-integrations.sync', account.id), {}, { preserveScroll: true })
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
    
    const disconnectIntegration = (account) => {
        router.delete(route('auth.sport-integrations.destroy', account.id), {
            preserveScroll: true,
            onFinish: closeDisconnectIntegrationModal,
        })
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
    
    const confirmSportActivityDelete = () => {
        if (sportActivityDeleteModal.value.mode === 'all') {
            router.delete(route('auth.sport-activities.destroy-all'), {
                preserveScroll: true,
                onFinish: closeSportActivityDeleteModal,
            })
    
            return
        }
    
        const activity = sportActivityDeleteModal.value.activity
        if (!activity) return
    
        router.delete(route('auth.sport-activities.destroy', activity.id), {
            preserveScroll: true,
            onFinish: closeSportActivityDeleteModal,
        })
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
    
    const updateSportActivityTitle = () => {
        const activity = sportActivityEditModal.value.activity
        if (!activity) return
    
        sportActivityEditForm.put(route('auth.sport-activities.update', activity.id), {
            preserveScroll: true,
            onSuccess: closeSportActivityEditModal,
        })
    }
    
    const storeManualActivity = () => {
        manualActivityForm.post(route('auth.sport-activities.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                manualActivityForm.reset()
                manualActivityForm.activity_type = 'Training'
            },
        })
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
    return {
        manualActivityTypeOptions,
        themeOptions,
        trainingDayOptions,
        settingsTabKeys,
        initialTab,
        activeTab,
        setActiveTab,
        openPaymentModal,
        subscriptionCancelModal,
        disconnectIntegrationModal,
        sportActivityDeleteModal,
        sportActivityEditModal,
        sportActivityEditForm,
        manualActivityForm,
        bankTransferModal,
        sportProfileNotice,
        savingSportProfileId,
        selectedSportProfileId,
        sportProfileSearch,
        sportProfilePickerOpen,
        selectedSportProfileIds,
        activeSportProfileId,
        sportProfileRemoveModal,
        sportProfileForms,
        addressNotice,
        form,
        saveAddress,
        updateTheme,
        toggleDefaultSport,
        i18nText,
        settingsText,
        sportProfileText,
        settingsTabItems,
        themeDescription,
        roleName,
        roleDescription,
        sportStatusLabel,
        sportExperienceLabel,
        metricVisibilityLabel,
        sportProfileGroupLabel,
        sportMetricLabel,
        sportMetricPlaceholder,
        todayDate,
        isExperienceDateField,
        isTrainingDaysField,
        isRunningBestTimeField,
        isStrengthPerformanceField,
        groupedPerformanceKeys,
        isGroupedPerformanceField,
        runningBestTimeFields,
        strengthPerformanceFields,
        performanceSectionsForSportProfile,
        sportProfileRegularFields,
        sportMetricInputType,
        trainingDayLabel,
        normalizedTrainingDayTokens,
        selectedTrainingDays,
        isTrainingDaySelected,
        toggleTrainingDay,
        isMetricUnknown,
        clearMetricUnknown,
        toggleMetricUnknown,
        sportExperienceDuration,
        selectedSportProfiles,
        availableSportProfiles,
        filteredAvailableSportProfiles,
        selectedSportProfile,
        chooseSportProfile,
        clearSportProfileChoice,
        addSelectedSportProfile,
        saveSportProfile,
        openSportProfileRemoveModal,
        closeSportProfileRemoveModal,
        confirmSportProfileRemove,
        formatMoney,
        formatDate,
        formatTime,
        invoiceStatusLabel,
        isPayableClubInvoice,
        isOpenSubscriptionPayment,
        canDeleteOpenSubscriptionPayment,
        formatIban,
        paymentReference,
        openBankTransferModal,
        closeBankTransferModal,
        hasAirmiusBank,
        hasClubBank,
        bankTransferRows,
        openPaymentActionModal,
        closeOpenPaymentModal,
        openPaymentModalTitle,
        openPaymentModalMessage,
        openPaymentModalConfirmText,
        confirmOpenPaymentAction,
        cancelOpenSubscriptionPayment,
        deleteOpenSubscriptionPayment,
        openProviderPortal,
        isSubscriptionCancellable,
        canOpenStripePortal,
        subscriptionCancelModalMessage,
        openSubscriptionCancelModal,
        closeSubscriptionCancelModal,
        confirmSubscriptionCancel,
        cancelSubscription,
        socialAccountFor,
        connectedAccountFor,
        integrationStatusLabel,
        integrationStatusClass,
        integrationProviderActionLabel,
        sportIntegrationProviderDescription,
        syncIntegration,
        openDisconnectIntegrationModal,
        closeDisconnectIntegrationModal,
        disconnectIntegration,
        openSportActivityDeleteModal,
        closeSportActivityDeleteModal,
        sportActivityDeleteTitle,
        sportActivityDeleteMessage,
        confirmSportActivityDelete,
        openSportActivityEditModal,
        closeSportActivityEditModal,
        updateSportActivityTitle,
        storeManualActivity,
        setManualActivityImage,
        formatDuration,
        formatDistance,
        formatProvider,
        manualActivityTypeLabel,
        sportActivityTitle,
        sportActivitySubtitle,
        sportActivityTime,
        activityLabel,
        activityScope,
        activityDescription,
    }
}

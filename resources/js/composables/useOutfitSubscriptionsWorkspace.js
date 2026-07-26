import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from '@/services/useTheme'

export function useOutfitSubscriptionsWorkspace(props) {
    const { t, te, locale } = useI18n()
    const tx = (key, fallback, values = {}) => {
        const translated = t(key, values)
        return translated === key ? fallback : translated
    }
    const { isDark } = useTheme()
    const page = usePage()

    const currentUser = computed(() => page.props.auth?.user || {})
    const countryCode = (value) => {
        const code = String(value || '').trim().toUpperCase()

        return code.length === 2 ? code : 'DE'
    }

    const profileForm = useForm({
        sport_focus: props.styleProfile?.sport_focus || '',
        sizes_text: (props.styleProfile?.sizes || []).join(', '),
        fit_preference: props.styleProfile?.fit_preference || 'regular',
        colors_text: (props.styleProfile?.colors || []).join(', '),
        excluded_colors_text: (props.styleProfile?.excluded_colors || []).join(', '),
        brand_style: props.styleProfile?.brand_style || 'minimal',
        notes: props.styleProfile?.notes || '',
    })

    const issueForm = useForm({
        issue_type: 'exchange',
        issue_description: '',
        issue_requested_resolution: '',
        issue_exchange_size: '',
    })

    const pendingCancelSubscription = ref(null)
    const pendingIssueDelivery = ref(null)
    const pendingSubscribePlan = ref(null)
    const subscribeAcceptedTerms = ref(false)
    const subscribeAcceptedContract = ref(false)
    const subscribePaymentProvider = ref('bank_transfer')
    const shippingName = ref(currentUser.value.name || '')
    const shippingCountry = ref(countryCode(currentUser.value.country))
    const shippingStreet = ref(currentUser.value.street || '')
    const shippingHouseNumber = ref(currentUser.value.house_number || '')
    const shippingPostalCode = ref(currentUser.value.postal_code || '')
    const shippingCity = ref(currentUser.value.city || '')
    const shippingState = ref(currentUser.value.state || '')
    const shippingNote = ref('')
    const subscribingPlanId = ref(null)
    const profileFeedback = ref(null)

    const activeSubscriptions = computed(() => props.subscriptions.filter((subscription) => [
        'active',
        'paused',
        'payment_paused',
        'cancels_at_period_end',
    ].includes(subscription.status)))

    const hasShippingAddress = computed(() => Boolean(
        shippingName.value &&
        shippingCountry.value &&
        shippingStreet.value &&
        shippingPostalCode.value &&
        shippingCity.value
    ))

    const nextDelivery = computed(() => activeSubscriptions.value
        .map((subscription) => subscription.next_delivery_at)
        .filter(Boolean)
        .sort()[0] || null)

    const hasStyleProfile = computed(() => Boolean(
        profileForm.sport_focus ||
        profileForm.sizes_text ||
        profileForm.colors_text ||
        profileForm.excluded_colors_text ||
        profileForm.notes
    ))

    const toList = (value) => String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)

    const sportLabel = (value) => {
        const sport = props.sports.find((item) => item.slug === value || item.name === value || String(item.id) === String(value))

        if (!sport) return value

        const key = `sports.${sport.slug}`

        return te(key) ? t(key) : sport.name
    }

    const initials = (name) => (name || '?')
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase()

    const sponsorLogoUrl = (sponsor) => isDark.value
        ? (sponsor?.logo_dark_url || sponsor?.logo_light_url || sponsor?.logo_url)
        : (sponsor?.logo_light_url || sponsor?.logo_dark_url || sponsor?.logo_url)

    const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
    const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
        style: 'currency',
        currency,
    }).format(Number(cents || 0) / 100)

    const formatDate = (value) => {
        if (!value) return tx('outfit_workspace.not_planned', 'Noch nicht geplant')

        return new Intl.DateTimeFormat(localeCode.value, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(new Date(value))
    }

    const scrollToPlans = () => {
        document.getElementById('outfit-plans')?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        })
    }

    const statusLabel = (status) => tx(`outfit_workspace.status.${status}`, status || '-')

    const issueTypeLabel = (type) => tx(`outfit_workspace.issue_types.${type}`, type || '-')

    const issueStatusLabel = (status) => tx(`outfit_workspace.issue_status.${status}`, status || '-')

    const paymentProviderLabel = (provider) => tx(`outfit_workspace.payment.${provider}`, provider || '-')

    const shippingAddressLine = (address) => [
        [address?.street, address?.house_number].filter(Boolean).join(' '),
        [address?.postal_code, address?.city].filter(Boolean).join(' '),
        [address?.state, address?.country].filter(Boolean).join(', '),
    ].filter(Boolean).join(', ')

    const isPendingPayment = (subscription) => subscription?.status === 'pending_payment'
    const cancelActionLabel = (subscription) => isPendingPayment(subscription)
        ? tx('outfit_workspace.actions.cancel_pending', 'Abbrechen')
        : tx('outfit_workspace.actions.cancel', 'Kündigen')
    const planContractRules = (plan) => plan?.contract_rules || props.contractRules
    const planContractTerms = (plan) => (plan?.contract_terms?.length ? plan.contract_terms : [
        tx('outfit_workspace.contract_terms.monthly', 'Das Outfit-Abo ist ein monatliches Abonnement mit wiederkehrender Zahlung.'),
        tx('outfit_workspace.contract_terms.payment', 'Die erste Lieferung wird erst nach bestätigter Zahlung vorbereitet.'),
        tx('outfit_workspace.contract_terms.future', 'Pause und Kündigung gelten nur für zukünftige Lieferungen.'),
    ])

    const brandingLabel = (type) => tx(`outfit_workspace.branding.${type === 'sponsor_logo' ? 'sponsor' : type === 'club_logo' ? 'club' : type === 'custom' ? 'custom' : 'default'}`, 'Branding')

    const updateProfile = () => {
        profileFeedback.value = null

        profileForm.transform((data) => ({
            sport_focus: data.sport_focus,
            sizes: toList(data.sizes_text),
            fit_preference: data.fit_preference,
            colors: toList(data.colors_text),
            excluded_colors: toList(data.excluded_colors_text),
            brand_style: data.brand_style,
            notes: data.notes,
        })).put(route('auth.outfit-subscriptions.profile.update'), {
            preserveScroll: true,
            onSuccess: () => {
                profileFeedback.value = {
                    type: 'success',
                    message: tx('outfit_workspace.feedback.profile_saved', 'Style-Profil wurde gespeichert.'),
                }
            },
            onError: () => {
                profileFeedback.value = {
                    type: 'error',
                    message: tx('outfit_workspace.feedback.profile_failed', 'Style-Profil konnte nicht gespeichert werden. Bitte prüfe deine Angaben.'),
                }
            },
        })
    }

    const subscribe = (plan) => {
        pendingSubscribePlan.value = plan
        subscribeAcceptedTerms.value = false
        subscribeAcceptedContract.value = false
        subscribePaymentProvider.value = 'bank_transfer'
    }

    const closeSubscribeModal = () => {
        if (subscribingPlanId.value) return

        pendingSubscribePlan.value = null
        subscribeAcceptedTerms.value = false
        subscribeAcceptedContract.value = false
        subscribePaymentProvider.value = 'bank_transfer'
    }

    const confirmSubscribe = () => {
        if (!pendingSubscribePlan.value || !subscribeAcceptedTerms.value || !subscribeAcceptedContract.value || !hasShippingAddress.value) return

        subscribingPlanId.value = pendingSubscribePlan.value.id

        router.post(route('auth.outfit-subscriptions.store', pendingSubscribePlan.value.id), {
            accepted_terms: subscribeAcceptedTerms.value,
            accepted_contract: subscribeAcceptedContract.value,
            payment_provider: subscribePaymentProvider.value,
            shipping_name: shippingName.value,
            shipping_country: shippingCountry.value,
            shipping_street: shippingStreet.value,
            shipping_house_number: shippingHouseNumber.value,
            shipping_postal_code: shippingPostalCode.value,
            shipping_city: shippingCity.value,
            shipping_state: shippingState.value,
            shipping_note: shippingNote.value,
        }, {
            preserveScroll: true,
            onFinish: () => {
                subscribingPlanId.value = null
                pendingSubscribePlan.value = null
                subscribeAcceptedTerms.value = false
                subscribeAcceptedContract.value = false
                subscribePaymentProvider.value = 'bank_transfer'
            },
        })
    }

    const pause = (subscription) => router.post(route('auth.outfit-subscriptions.pause', subscription.id), {}, { preserveScroll: true })
    const resume = (subscription) => router.post(route('auth.outfit-subscriptions.resume', subscription.id), {}, { preserveScroll: true })
    const requestCancel = (subscription) => {
        pendingCancelSubscription.value = subscription
    }
    const closeCancelModal = () => {
        pendingCancelSubscription.value = null
    }
    const confirmCancel = () => {
        if (!pendingCancelSubscription.value) return

        router.post(route('auth.outfit-subscriptions.cancel', pendingCancelSubscription.value.id), {}, {
            preserveScroll: true,
            onFinish: closeCancelModal,
        })
    }

    const canRequestIssue = (delivery) => ['shipped', 'delivered'].includes(delivery.status) &&
        !['open', 'reviewing', 'approved', 'return_waiting', 'replacement_preparing'].includes(delivery.issue_status)

    const openIssueModal = (delivery) => {
        issueForm.reset()
        issueForm.clearErrors()
        issueForm.issue_type = 'exchange'
        pendingIssueDelivery.value = delivery
    }

    const closeIssueModal = () => {
        if (issueForm.processing) return

        pendingIssueDelivery.value = null
    }

    const submitIssue = () => {
        if (!pendingIssueDelivery.value) return

        issueForm.post(route('auth.outfit-deliveries.issue.request', pendingIssueDelivery.value.id), {
            preserveScroll: true,
            onSuccess: closeIssueModal,
        })
    }

    return {
        page,
        profileForm,
        issueForm,
        pendingCancelSubscription,
        pendingIssueDelivery,
        pendingSubscribePlan,
        subscribeAcceptedTerms,
        subscribeAcceptedContract,
        subscribePaymentProvider,
        shippingName,
        shippingCountry,
        shippingStreet,
        shippingHouseNumber,
        shippingPostalCode,
        shippingCity,
        shippingState,
        shippingNote,
        subscribingPlanId,
        profileFeedback,
        activeSubscriptions,
        hasShippingAddress,
        nextDelivery,
        hasStyleProfile,
        sportLabel,
        initials,
        sponsorLogoUrl,
        formatMoney,
        formatDate,
        scrollToPlans,
        statusLabel,
        issueTypeLabel,
        issueStatusLabel,
        paymentProviderLabel,
        shippingAddressLine,
        isPendingPayment,
        cancelActionLabel,
        planContractRules,
        planContractTerms,
        brandingLabel,
        updateProfile,
        subscribe,
        closeSubscribeModal,
        confirmSubscribe,
        pause,
        resume,
        requestCancel,
        closeCancelModal,
        confirmCancel,
        canRequestIssue,
        openIssueModal,
        closeIssueModal,
        submitIssue,
    }
}


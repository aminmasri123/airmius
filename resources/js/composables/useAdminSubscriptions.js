import { router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { centsToMajor, moneyInputAttrs, transformMoneyFields } from '@/utils/currency'

export function useAdminSubscriptions(props) {
    const selectedActor = ref('all')
    const clubSearch = ref('')
    const userSearch = ref('')
    const editingPlanId = ref(null)
    const clubForms = ref({})
    const userForms = ref({})
    const planForms = ref({})

    const actorLabels = {
        all: 'Alle',
        sportler: 'Sportler',
        trainer: 'Trainer',
        verein: 'Vereine',
        eltern: 'Eltern',
        sponsor: 'Sponsoren',
        anbieter: 'Anbieter',
        enterprise: 'Enterprise',
    }

    const actorIcons = {
        all: 'las la-layer-group',
        sportler: 'las la-running',
        trainer: 'las la-chalkboard-teacher',
        verein: 'las la-users',
        eltern: 'las la-shield-alt',
        sponsor: 'las la-bullhorn',
        anbieter: 'las la-store',
        enterprise: 'las la-network-wired',
    }

    const statusLabels = {
        trialing: 'Testphase',
        active: 'Aktiv',
        past_due: 'Zahlung offen',
        cancelled: 'Gekuendigt',
        cancels_at_period_end: 'Gekuendigt zum Periodenende',
    }

    const actors = computed(() => [
        'all',
        ...Object.keys(actorLabels).filter((actor) => actor !== 'all' && props.plans.some((plan) => plan.target_actor === actor)),
    ])

    const filteredPlans = computed(() => {
        if (selectedActor.value === 'all') return props.plans

        return props.plans.filter((plan) => plan.target_actor === selectedActor.value)
    })

    const clubPlans = computed(() => props.plans.filter((plan) => plan.target_actor === 'verein'))
    const userPlans = computed(() => props.plans.filter((plan) => plan.target_actor !== 'verein'))
    const selectedUserPlans = computed(() => {
        if (selectedActor.value === 'all' || selectedActor.value === 'verein') {
            return userPlans.value
        }

        return props.plans.filter((plan) => plan.target_actor === selectedActor.value)
    })

    const filteredClubs = computed(() => {
        const query = clubSearch.value.trim().toLowerCase()
        if (!query) return props.clubs

        return props.clubs.filter((club) =>
            `${club.name} ${club.plan?.name || ''}`.toLowerCase().includes(query),
        )
    })

    const subscriptionForUser = (user) => {
        if (selectedActor.value === 'all' || selectedActor.value === 'verein') return null

        return (user.subscriptions || []).find((subscription) => subscription.plan?.target_actor === selectedActor.value) || null
    }

    const filteredUsers = computed(() => {
        const query = userSearch.value.trim().toLowerCase()
        if (!query) return props.users

        return props.users.filter((user) => {
            const subscription = subscriptionForUser(user)

            return `${user.name || ''} ${user.first_name || ''} ${user.last_name || ''} ${user.email || ''} ${subscription?.plan?.name || ''}`
                .toLowerCase()
                .includes(query)
        })
    })

    const summary = computed(() => ({
        plans: props.plans.length,
        publicPlans: props.plans.filter((plan) => plan.is_public && plan.is_active).length,
        clubSubscriptions: props.plans.reduce((total, plan) => total + Number(plan.club_subscriptions_count || 0), 0),
        userSubscriptions: props.plans.reduce((total, plan) => total + Number(plan.user_subscriptions_count || 0), 0),
    }))

    const formatPrice = (cents, currency = 'EUR') => {
        const value = Number(cents || 0) / 100

        return value
            ? new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(value)
            : `0 ${currency}`
    }

    const limitLabel = (value, suffix = '') => value ? `${value}${suffix}` : 'Unbegrenzt'
    const displayUserName = (user) => user.name || `${user.first_name || ''} ${user.last_name || ''}`.trim() || '-'

    const formForPlan = (plan) => {
        planForms.value[plan.id] ??= useForm({
            target_actor: plan.target_actor || 'verein',
            description: plan.description || '',
            monthly_price_cents: centsToMajor(plan.monthly_price_cents),
            yearly_price_cents: centsToMajor(plan.yearly_price_cents),
            member_limit: plan.member_limit || '',
            team_limit: plan.team_limit || '',
            storage_gb: plan.storage_gb || 1,
            minimum_term_months: plan.minimum_term_months ?? 0,
            cancellation_notice_days: plan.cancellation_notice_days ?? 0,
            cta_label: plan.cta_label || '',
            badge: plan.badge || '',
            is_public: Boolean(plan.is_public),
            is_active: Boolean(plan.is_active),
            country_prices: (plan.country_prices || []).map((price) => ({
                country_code: price.country_code || 'DE',
                currency: price.currency || plan.currency || 'EUR',
                monthly_price_cents: centsToMajor(price.monthly_price_cents ?? plan.monthly_price_cents),
                yearly_price_cents: centsToMajor(price.yearly_price_cents ?? plan.yearly_price_cents),
                is_active: Boolean(price.is_active),
            })),
        })

        return planForms.value[plan.id]
    }

    const addCountryPrice = (plan) => {
        formForPlan(plan).country_prices.push({
            country_code: 'CH',
            currency: 'CHF',
            monthly_price_cents: centsToMajor(plan.monthly_price_cents),
            yearly_price_cents: centsToMajor(plan.yearly_price_cents),
            is_active: true,
        })
    }

    const removeCountryPrice = (plan, index) => {
        formForPlan(plan).country_prices.splice(index, 1)
    }

    const formForClub = (club) => {
        clubForms.value[club.id] ??= useForm({
            subscription_plan_id: club.plan?.id || clubPlans.value[0]?.id || '',
            status: club.subscription?.status || 'active',
            trial_ends_at: club.subscription?.trial_ends_at || '',
            current_period_ends_at: club.subscription?.current_period_ends_at || '',
            payment_provider: club.subscription?.payment_provider || '',
        })

        return clubForms.value[club.id]
    }

    const formForUserSubscription = (subscription) => {
        userForms.value[subscription.id] ??= useForm({
            user_subscription_id: subscription.id,
            subscription_plan_id: subscription.plan?.id || userPlans.value[0]?.id || '',
            status: subscription.status || 'active',
            trial_ends_at: subscription.trial_ends_at || '',
            current_period_ends_at: subscription.current_period_ends_at || '',
            payment_provider: subscription.payment_provider || '',
        })

        return userForms.value[subscription.id]
    }

    const formForUser = (user) => {
        const subscription = subscriptionForUser(user)
        const key = `${selectedActor.value}-${user.id}`

        userForms.value[key] ??= useForm({
            user_subscription_id: subscription?.id || '',
            subscription_plan_id: subscription?.plan?.id || selectedUserPlans.value[0]?.id || '',
            status: subscription?.status || 'active',
            trial_ends_at: subscription?.trial_ends_at || '',
            current_period_ends_at: subscription?.current_period_ends_at || '',
            payment_provider: subscription?.payment_provider || '',
        })

        return userForms.value[key]
    }

    const savePlan = (plan) => {
        formForPlan(plan)
            .transform((data) => ({
                ...transformMoneyFields(data, ['monthly_price_cents', 'yearly_price_cents']),
                country_prices: (data.country_prices || []).map((price) => transformMoneyFields(price, ['monthly_price_cents', 'yearly_price_cents'])),
            }))
            .put(route('admin.subscription-plans.update', plan.id), {
                preserveScroll: true,
                onSuccess: () => {
                    editingPlanId.value = null
                },
            })
    }

    const saveClub = (club) => {
        formForClub(club).put(route('admin.clubs.subscription.update', club.id), {
            preserveScroll: true,
        })
    }

    const saveUserSubscription = (subscription) => {
        formForUserSubscription(subscription).put(route('admin.users.subscription.update', subscription.user.id), {
            preserveScroll: true,
        })
    }

    const saveUser = (user) => {
        formForUser(user).put(route('admin.users.subscription.update', user.id), {
            preserveScroll: true,
        })
    }

    const cancelClubSubscription = (club, mode = 'period_end') => {
        if (!club.subscription) return

        router.post(route('admin.club-subscriptions.cancel', club.subscription.id), { mode }, { preserveScroll: true })
    }

    const renewClubSubscription = (club, months = 1) => {
        if (!club.subscription) return

        router.post(route('admin.club-subscriptions.renew', club.subscription.id), { months }, { preserveScroll: true })
    }

    const cancelUserSubscription = (subscription, mode = 'period_end') => {
        if (!subscription) return

        router.post(route('admin.user-subscriptions.cancel', subscription.id), { mode }, { preserveScroll: true })
    }

    const renewUserSubscription = (subscription, months = 1) => {
        if (!subscription) return

        router.post(route('admin.user-subscriptions.renew', subscription.id), { months }, { preserveScroll: true })
    }

    const markTransferPaid = (checkout) => {
        router.post(route('admin.subscription-checkouts.mark-paid', checkout.id), {}, {
            preserveScroll: true,
        })
    }

    return {
        selectedActor,
        clubSearch,
        userSearch,
        editingPlanId,
        actorLabels,
        actorIcons,
        statusLabels,
        actors,
        filteredPlans,
        filteredClubs,
        filteredUsers,
        clubPlans,
        userPlans,
        selectedUserPlans,
        summary,
        moneyInputAttrs,
        formatPrice,
        limitLabel,
        displayUserName,
        subscriptionForUser,
        formForPlan,
        formForClub,
        formForUser,
        formForUserSubscription,
        addCountryPrice,
        removeCountryPrice,
        savePlan,
        saveClub,
        saveUser,
        saveUserSubscription,
        cancelClubSubscription,
        renewClubSubscription,
        cancelUserSubscription,
        renewUserSubscription,
        markTransferPaid,
    }
}

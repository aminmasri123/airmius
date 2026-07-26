<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import AdSlot from '@/Components/Ads/AdSlot.vue'
import UserCard from '@/Components/Auth/UserCard.vue'
import { useTheme } from '@/services/useTheme'
import { applyLogoFallback, logoWordmark } from '@/services/logoAssets'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    authUser: { type: Object, default: null },
    cart: { type: Object, default: () => ({ items_count: 0 }) },
    products: { type: Object, default: () => ({ data: [], links: [], total: 0, per_page: 40 }) },
    featuredProducts: { type: Array, default: () => [] },
    flashDeals: { type: Array, default: () => [] },
    essentialDeals: { type: Array, default: () => [] },
    learningDeals: { type: Array, default: () => [] },
    serviceDeals: { type: Array, default: () => [] },
    outfitPlans: { type: Array, default: () => [] },
    sportCategories: { type: Array, default: () => [] },
    officialStores: { type: Array, default: () => [] },
    providerLocations: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    segments: { type: Array, default: () => [] },
    sortOptions: { type: Array, default: () => [] },
    availabilityOptions: { type: Array, default: () => [] },
    trustBenefits: { type: Array, default: () => [] },
    pricingCountries: { type: Array, default: () => [] },
    marketplaceVisuals: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const { isDark } = useTheme()
const { t, te, locale } = useI18n()
const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')
const currentUser = computed(() => props.authUser || page.props.auth?.user || null)
const isRtlLocale = computed(() => locale.value === 'ar' || page.props.direction === 'rtl')
const marketplaceLogo = computed(() => logoWordmark(isDark.value))
const marketplaceReturnTo = '/marketplace'
const loginHref = computed(() => route('login', { redirect: marketplaceReturnTo }))
const registerHref = computed(() => route('register', { redirect: marketplaceReturnTo }))
const form = ref({
    search: props.filters.search || '',
    category: props.filters.category || '',
    segment: props.filters.segment || '',
    country: props.filters.country || '',
    sort: props.filters.sort || 'recommended',
    availability: props.filters.availability || '',
})
const mobileFilterOpen = ref(false)
const advancedFilterOpen = ref(false)

const marketplaceLiteralTranslations = {
    ar: {
        'AIRMIUS': 'إيرميوس',
        'AIRMIUS Marketplace': 'AIRMIUS ماركت بليس',
        'Airmius Marketplace': 'Airmius ماركت بليس',
        'AIRMIUS MARKETPLACE': 'AIRMIUS ماركت بليس',
        'Airmius Sport Marketplace': 'Airmius ماركت بليس الرياضي',
        'Sportfokussierter Marketplace für Produkte, Kurse, Camps und Services. Gäste können direkt ohne Konto bestellen.': 'سوق رياضي للمنتجات والدورات والمخيمات والخدمات. يمكن للضيوف الطلب مباشرة بدون حساب.',
        'Sport Deals, Kurse, Camps und Services passend zu deinem Design': 'عروض رياضية ودورات ومخيمات وخدمات تناسب احتياجك',
        'Sport Deals für Training und Team': 'عروض رياضية للتدريب والفريق',
        'Sport Deals für Training, Team und Wettkampf': 'عروض رياضية للتدريب والفريق والمنافسات',
        'Produkte, Kurse, Camps und Services an einem Ort.': 'منتجات ودورات ومخيمات وخدمات في مكان واحد.',
        'Weniger scrollen, schneller finden: Kategorien, Aktionen und kuratierte Reihen statt alle Produkte auf einmal.': 'تصفح أقل واعثر أسرع: فئات وعروض ومجموعات مختارة بدل عرض كل المنتجات دفعة واحدة.',
        'Warenkorb': 'سلة التسوق',
        'Anmelden': 'تسجيل الدخول',
        'Registrieren': 'إنشاء حساب',
        'Was suchst du?': 'ماذا تبحث؟',
        'Suche leeren': 'مسح البحث',
        'Filter': 'فلتر',
        'Suchen': 'بحث',
        'Reset': 'إعادة ضبط',
        'Bereich': 'القسم',
        'Verfügbarkeit': 'التوفر',
        'Sortierung': 'الترتيب',
        'Land': 'البلد',
        'Land automatisch': 'البلد تلقائيا',
        'Filter & Kategorien': 'الفلاتر والفئات',
        '{count} Treffer': '{count} نتيجة',
        'Kategorie': 'الفئة',
        'Schnellwahl': 'اختيار سريع',
        'Sportarten': 'الرياضات',
        'Produktbereiche': 'أقسام المنتجات',
        'Anwenden': 'تطبيق',
        'Entdecken': 'اكتشف',
        'Jetzt entdecken': 'اكتشف الآن',
        'Hilfe & Bestellung': 'المساعدة والطلب',
        'Gastbestellung, Login-Bestellung und Anbieterangebote sind verfügbar.': 'طلبات الضيوف والطلبات بعد تسجيل الدخول وعروض المزودين متاحة.',
        'Anbieter werden': 'كن مزودا',
        'Vereine, Trainer und Shops können Angebote einstellen.': 'يمكن للأندية والمدربين والمتاجر إضافة عروض.',
        'Produkt': 'منتج',
        'Kurs': 'دورة',
        'Camp': 'مخيم',
        'Service': 'خدمة',
        'Outfit-Abo': 'اشتراك ملابس',
        'Angebot': 'عرض',
        'Angebote': 'عروض',
        'Alle Bereiche': 'كل الأقسام',
        'Alle': 'الكل',
        'Aktuell': 'حاليا',
        'Heute beliebt': 'رائج اليوم',
        'Produkte': 'منتجات',
        'Equipment': 'معدات',
        'Kurse': 'دورات',
        'Online & vor Ort': 'أونلاين وحضوريا',
        'Camps': 'مخيمات',
        'Events & Training': 'فعاليات وتدريب',
        'Services': 'خدمات',
        'Analyse & Beratung': 'تحليل واستشارة',
        'Sportkleidung': 'ملابس رياضية',
        'brutto': 'إجمالي',
        'netto': 'صافي',
        '{count} Teile je Box': '{count} قطع في كل صندوق',
        'Monatlich kündbar': 'يمكن إلغاؤه شهريا',
        'Digital / Termin': 'رقمي / موعد',
        'Auf Anfrage': 'حسب الطلب',
        'Aktuell vergriffen': 'غير متوفر حاليا',
        'Nur {count} verfügbar': 'متوفر فقط {count}',
        'Auf Lager': 'متوفر في المخزون',
        'Abholstation': 'نقطة استلام',
        'Boutique': 'بوتيك',
        'Filiale': 'فرع',
        'Lager': 'مستودع',
        'Partnerstandort': 'موقع شريك',
        'Standort': 'موقع',
        'Sportangebot aus dem Airmius Marketplace.': 'عرض رياضي من Airmius ماركت بليس.',
        'Standorte': 'المواقع',
        'Filialen, Boutiquen und Abholstationen': 'فروع وبوتيكات ونقاط استلام',
        'Öffentliche Anbieterstandorte, die du besuchen oder für Abholung nutzen kannst.': 'مواقع مزودين عامة يمكنك زيارتها أو استخدامها للاستلام.',
        '{count} Standorte': '{count} مواقع',
        'Abholung': 'استلام',
        'Rückgabe': 'إرجاع',
        'Ansehen': 'عرض',
        'Aktiv:': 'نشط:',
        'Alle anzeigen': 'عرض الكل',
        'Aktuelle Angebote': 'العروض الحالية',
        'Schnellvergleich nach Preis, Steuer und Anbieter': 'مقارنة سريعة حسب السعر والضريبة والمزود',
        'Alles für deinen Sportalltag': 'كل شيء ليومك الرياضي',
        'Offizielle Stores': 'متاجر رسمية',
        'Kurse & Camps': 'دورات ومخيمات',
        'Services & Analysen': 'خدمات وتحليلات',
        'Outfit-Abos': 'اشتراكات الملابس',
        'Mehr sehen': 'عرض المزيد',
        'Alle Angebote': 'كل العروض',
        '{count} Treffer, angezeigt werden maximal {perPage} pro Seite.': '{count} نتيجة، يتم عرض {perPage} كحد أقصى في الصفحة.',
        'Angebot einstellen': 'إضافة عرض',
        'Details ansehen': 'عرض التفاصيل',
        'Keine passenden Angebote gefunden.': 'لم يتم العثور على عروض مناسبة.',
        'Probiere einen allgemeineren Suchbegriff, entferne Filter oder springe direkt in einen beliebten Bereich.': 'جرّب كلمة بحث أوسع، أزل الفلاتر أو انتقل مباشرة إلى قسم شائع.',
        'Filter zurücksetzen': 'إعادة ضبط الفلاتر',
        'Teamwear': 'ملابس الفرق',
        'Training': 'تدريب',
        'Equipment & Gear': 'معدات وتجهيزات',
        'Recovery': 'استشفاء',
        'Nutrition': 'تغذية',
        'Courses': 'دورات',
        'Camps': 'مخيمات',
        'Services': 'خدمات',
        'All': 'الكل',
        'Recommended': 'موصى به',
        'Newest': 'الأحدث',
        'Price ascending': 'السعر تصاعديا',
        'Price descending': 'السعر تنازليا',
        'Available': 'متوفر',
        'Digital': 'رقمي',
        'Pickup': 'استلام',
        'Schuhe': 'أحذية',
        'Bekleidung': 'ملابس',
        'Analyse': 'تحليل',
        'Ernährung': 'تغذية',
        'Pläne & Kurse': 'خطط ودورات',
        'Team & Verein': 'فريق وناد',
        'Running': 'الجري',
        'Fußball': 'كرة القدم',
        'Fitness': 'لياقة',
        'Teamsport': 'رياضة جماعية',
        'Empfohlen': 'موصى به',
        'Neueste': 'الأحدث',
        'Preis aufsteigend': 'السعر تصاعديا',
        'Preis absteigend': 'السعر تنازليا',
        'Alle Verfügbarkeiten': 'كل حالات التوفر',
        'Sofort verfügbar': 'متوفر فورا',
        'Versandartikel': 'منتج قابل للشحن',
        'Gastkauf möglich': 'الشراء كضيف ممكن',
        'Direkt bestellen, Konto optional.': 'اطلب مباشرة، الحساب اختياري.',
        'Preis transparent': 'سعر شفاف',
        'Brutto, netto, Steuer und Versand werden ausgewiesen.': 'يتم عرض الإجمالي والصافي والضريبة والشحن.',
        'Anbieter sichtbar': 'المزود واضح',
        'Verein, Trainer oder Shop bleiben klar erkennbar.': 'يبقى النادي أو المدرب أو المتجر واضحا.',
        'Bestellstatus': 'حالة الطلب',
        'Updates und Belege werden per E-Mail zugestellt.': 'تصلك التحديثات والإيصالات عبر البريد الإلكتروني.',
        'Sponsor Deal': 'عرض راع',
        'Monatsabo': 'اشتراك شهري',
        'Versand nach Bestellung': 'الشحن بعد الطلب',
        'Ohne Versand': 'بدون شحن',
        'MwSt.': 'ضريبة القيمة المضافة',
        'USt.': 'ضريبة القيمة المضافة',
        'Tax': 'ضريبة',
        'AIRMIUS Marketplace': 'سوق إيرميوس',
        'Airmius Marketplace': 'سوق إيرميوس',
        'AIRMIUS MARKETPLACE': 'سوق إيرميوس',
        'Airmius Sport Marketplace': 'سوق إيرميوس الرياضي',
    },
}

const interpolate = (text, params = {}) => Object.entries(params).reduce(
    (carry, [key, value]) => carry.replaceAll(`{${key}}`, value),
    text,
)

const mt = (value, params = {}) => {
    if (!value) return value

    const key = String(value)
    const localValue = marketplaceLiteralTranslations[locale.value]?.[key]

    if (localValue) {
        return interpolate(localValue, params)
    }

    return te(key) ? t(key, params) : interpolate(key, params)
}

const translated = (value) => value ? mt(String(value)) : value
const translatedOption = (option) => ({
    ...option,
    label: translated(option.label),
    description: translated(option.description),
    discount: translated(option.discount),
})

const categoryLabels = computed(() => ({
    product: mt('Produkt'),
    course: mt('Kurs'),
    camp: mt('Camp'),
    service: mt('Service'),
    outfit_subscription: mt('Outfit-Abo'),
}))

const productItems = computed(() => props.products?.data || [])
const allOfferItems = computed(() => [...productItems.value, ...(props.outfitPlans || [])])
const paginationLinks = computed(() => (props.products?.links || []).filter((link) => link.url))
const totalProducts = computed(() => (props.products?.total || productItems.value.length) + (props.outfitPlans?.length || 0))
const cartItemCount = computed(() => Number(props.cart?.items_count || 0))
const heroProduct = computed(() => props.featuredProducts[0] || props.flashDeals[0] || productItems.value[0] || null)
const heroSideProducts = computed(() => (props.featuredProducts.length ? props.featuredProducts : props.flashDeals).slice(1, 4))
const sideBannerUrl = computed(() => props.marketplaceVisuals.side_banner || '/images/marketplace/airmius-marketplace-side-banner.png')
const sideBannerDimensions = computed(() => props.marketplaceVisuals.dimensions?.side_banner || { width: 192, height: 1080 })
const sideBannerStyle = computed(() => ({
    backgroundImage: `linear-gradient(180deg, rgba(5, 11, 22, 0.28), rgba(5, 11, 22, 0.45)), url("${sideBannerUrl.value}")`,
    width: `${Math.max(148, Math.min(192, Number(sideBannerDimensions.value.width || 192)))}px`,
}))
const heroImageUrl = computed(() => props.marketplaceVisuals.hero_banner || heroProduct.value?.image_url || '')
const mobilePromoSlides = computed(() => {
    const pool = [
        heroProduct.value,
        ...props.flashDeals,
        ...props.featuredProducts,
        ...props.essentialDeals,
    ].filter(Boolean)

    const uniqueProducts = Array.from(new Map(pool.map((item) => [item.id || item.title, item])).values()).slice(0, 5)

    if (!uniqueProducts.length) {
        return [
            {
                id: 'marketplace-start',
                title: mt('Sport Deals für Training und Team'),
                subtitle: mt('Produkte, Kurse, Camps und Services an einem Ort.'),
                image_url: props.marketplaceVisuals.hero_banner || '',
                href: route('guest.marketplace'),
                badge: mt('AIRMIUS'),
            },
        ]
    }

    return uniqueProducts.map((item, index) => ({
        id: item.id || `mobile-slide-${index}`,
        title: index === 0 ? mt('Sport Deals für Training und Team') : item.title,
        subtitle: translated(item.badge) || availabilityLabel(item),
        image_url: index === 0 ? (props.marketplaceVisuals.hero_banner || item.image_url) : item.image_url,
        href: item.show_url || route('guest.marketplace'),
        badge: index === 0 ? mt('AIRMIUS MARKETPLACE') : (categoryLabels.value[item.category] || mt('Angebot')),
    }))
})
const activeSegment = computed(() => props.segments.find((segment) => segment.value === form.value.segment) || props.segments[0] || null)
const activeCategory = computed(() => props.categories.find((category) => category.value === form.value.category) || props.categories[0] || null)
const activeAvailability = computed(() => props.availabilityOptions.find((option) => option.value === form.value.availability) || props.availabilityOptions[0] || null)
const localizedCategories = computed(() => props.categories.map(translatedOption))
const localizedSegments = computed(() => props.segments.map(translatedOption))
const localizedSortOptions = computed(() => props.sortOptions.map(translatedOption))
const localizedAvailabilityOptions = computed(() => props.availabilityOptions.map(translatedOption))
const localizedSportCategories = computed(() => props.sportCategories.map(translatedOption))
const localizedOfficialStores = computed(() => props.officialStores.map(translatedOption))
const localizedTrustBenefits = computed(() => props.trustBenefits.map(translatedOption))
const marketplaceLocations = computed(() => props.providerLocations || [])
const activeSegmentLabel = computed(() => translated(activeSegment.value?.label) || mt('Alle Bereiche'))
const activeCategoryLabel = computed(() => translated(activeCategory.value?.label) || mt('Alle'))
const activeAvailabilityLabel = computed(() => translated(activeAvailability.value?.label) || mt('Alle'))
const segmentLookup = computed(() => Object.fromEntries(props.segments.map((segment) => [segment.value || 'all', translatedOption(segment)])))
const productGroups = computed(() => {
    const groups = allOfferItems.value.reduce((carry, product) => {
        const key = product.segment || 'equipment'

        if (!carry[key]) {
            carry[key] = []
        }

        carry[key].push(product)

        return carry
    }, {})

    return Object.entries(groups).map(([key, items]) => ({
        key,
        label: translated(segmentLookup.value[key]?.label) || categoryLabels.value[items[0]?.category] || mt('Angebote'),
        icon: segmentLookup.value[key]?.icon || 'las la-shopping-bag',
        items,
    }))
})

const quickTiles = computed(() => [
    { label: mt('Aktuell'), hint: mt('Heute beliebt'), icon: 'las la-bolt', category: '', search: '' },
    { label: mt('Produkte'), hint: mt('Equipment'), icon: 'las la-shopping-bag', category: 'product', search: '' },
    { label: mt('Kurse'), hint: mt('Online & vor Ort'), icon: 'las la-video', category: 'course', search: '' },
    { label: mt('Camps'), hint: mt('Events & Training'), icon: 'las la-campground', category: 'camp', search: '' },
    { label: mt('Services'), hint: mt('Analyse & Beratung'), icon: 'las la-hands-helping', category: 'service', search: '' },
    { label: mt('Outfit-Abo'), hint: mt('Sportkleidung'), icon: 'las la-tshirt', category: 'outfit_subscription', search: '' },
])

const localeCode = computed(() => ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE')
const formatPrice = (cents, currency = 'EUR') => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: currency || 'EUR',
}).format((cents || 0) / 100)

const price = (item) => item.price || {
    gross_cents: item.price_cents,
    net_cents: item.price_cents,
    tax_cents: 0,
    currency: item.currency || 'EUR',
    tax_rate: 0,
    tax_label: 'Tax',
}

const grossPrice = (item) => formatPrice(price(item).gross_cents, price(item).currency)
const netPrice = (item) => formatPrice(price(item).net_cents, price(item).currency)
const taxInfo = (item) => {
    const quote = price(item)

    return `${netPrice(item)} ${mt('netto')} · ${formatPrice(quote.tax_cents, quote.currency)} ${quote.tax_label} (${quote.tax_rate}%)`
}

const availabilityLabel = (item) => {
    if (item.category === 'outfit_subscription') {
        return item.items_per_box ? mt('{count} Teile je Box', { count: item.items_per_box }) : mt('Monatlich kündbar')
    }

    if (item.offer_type === 'online_course' || item.offer_type === 'training_plan' || item.category === 'service') {
        return mt('Digital / Termin')
    }

    if (!item.manages_stock) {
        return mt('Auf Anfrage')
    }

    const stock = Number(item.stock_quantity || 0)

    if (stock <= 0) {
        return mt('Aktuell vergriffen')
    }

    return stock <= 5 ? mt('Nur {count} verfügbar', { count: stock }) : mt('Auf Lager')
}

const locationTypeLabel = (type) => ({
    pickup: mt('Abholstation'),
    boutique: mt('Boutique'),
    branch: mt('Filiale'),
    warehouse: mt('Lager'),
    partner: mt('Partnerstandort'),
}[type] || mt('Standort'))

const shortDescription = (text, length = 92) => {
    if (!text) return mt('Sportangebot aus dem Airmius Marketplace.')
    if (text.length <= length) return text

    return `${text.slice(0, length).trim()}...`
}

const search = () => {
    router.get(route('guest.marketplace'), {
        search: form.value.search || undefined,
        category: form.value.category || undefined,
        segment: form.value.segment || undefined,
        country: form.value.country || undefined,
        sort: form.value.sort && form.value.sort !== 'recommended' ? form.value.sort : undefined,
        availability: form.value.availability || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

const reset = () => {
    form.value.search = ''
    form.value.category = ''
    form.value.segment = ''
    form.value.country = ''
    form.value.sort = 'recommended'
    form.value.availability = ''
    search()
}

const searchCategory = (category) => {
    form.value.search = category.query || ''
    form.value.category = category.category || ''
    form.value.segment = category.segment || ''
    search()
}

const selectQuickTile = (tile) => {
    form.value.search = tile.search || ''
    form.value.category = tile.category || ''
    form.value.segment = tile.segment || ''
    search()
}

const selectSegment = (segment) => {
    form.value.segment = segment.value || ''
    search()
}
</script>

<template>
    <SeoHead
        :title="mt('Airmius Sport Marketplace')"
        :description="mt('Sportfokussierter Marketplace für Produkte, Kurse, Camps und Services. Gäste können direkt ohne Konto bestellen.')"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Subnav vertical />

        <aside
            class="pointer-events-none fixed left-0 top-0 z-0 hidden h-screen overflow-hidden bg-buttonPrimary/10 bg-cover bg-center opacity-50 2xl:block"
            :style="sideBannerStyle"
            aria-hidden="true"
        >
            <div class="absolute inset-0 bg-bg/35"></div>
        </aside>

        <aside
            class="pointer-events-none fixed right-0 top-0 z-0 hidden h-screen scale-x-[-1] overflow-hidden bg-buttonPrimary/10 bg-cover bg-center opacity-50 2xl:block"
            :style="sideBannerStyle"
            aria-hidden="true"
        >
            <div class="absolute inset-0 bg-bg/35"></div>
        </aside>

        <main class="relative z-10 mx-auto max-w-[86rem] pb-24 pt-0 md:pb-14">
            <section class="border-b border-border bg-bg px-3 py-2 shadow-sm sm:px-4 sm:py-3">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-card px-3 py-2 text-primary shadow-sm sm:flex-nowrap sm:gap-3 sm:px-5 sm:py-3">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <img :src="marketplaceLogo" alt="AIRMIUS" class="h-9 w-auto max-w-[8.25rem] shrink-0 object-contain sm:h-12 sm:max-w-none" @error="applyLogoFallback">
                        <div class="hidden min-w-0 sm:block">
                            <p class="font-heading text-lg font-900 leading-tight sm:text-2xl">{{ mt("Marketplace") }}</p>
                            <p class="truncate text-xs font-semibold text-secondary sm:text-sm">
                                {{ mt("Sport Deals, Kurse, Camps und Services passend zu deinem Design") }}
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center justify-end gap-1.5 text-xs font-black sm:gap-2 sm:text-sm">
                        <Link
                            v-if="currentUser"
                            :href="route('auth.commerce.cart.index')"
                            class="relative inline-flex h-10 w-10 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary"
                            :aria-label="mt('Warenkorb')"
                            :title="mt('Warenkorb')"
                        >
                            <i class="las la-shopping-cart text-xl"></i>
                            <span
                                v-if="cartItemCount"
                                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-error px-1 text-[11px] font-black leading-none text-white ring-2 ring-card"
                            >
                                {{ cartItemCount }}
                            </span>
                        </Link>
                        <UserCard v-if="currentUser" />
                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="loginHref"
                                class="rounded-full border border-border px-2.5 py-2 text-secondary transition hover:border-buttonPrimary hover:text-primary sm:px-3"
                            >
                                {{ mt("Anmelden") }}
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="registerHref"
                                class="rounded-full bg-buttonPrimary px-2.5 py-2 text-buttonTextPrimary transition hover:bg-buttonPrimaryHover sm:px-3"
                            >
                                {{ mt("Registrieren") }}
                            </Link>
                        </template>
                    </div>
                </div>
            </section>

            <section class="bg-card/70 px-3 py-3 shadow-sm backdrop-blur sm:px-4">
                <form class="mx-auto grid max-w-7xl grid-cols-[minmax(0,1fr)_auto_auto] gap-2 rounded-xl border border-border bg-bg/80 p-2 shadow-sm sm:p-3 lg:grid-cols-[minmax(12rem,18rem)_minmax(18rem,1fr)_auto] lg:items-center lg:gap-3" @submit.prevent="search">
                    <div class="relative order-1 min-w-0 lg:order-2 lg:min-w-[22rem] xl:min-w-[30rem]">
                        <i class="las la-search absolute left-4 top-1/2 -translate-y-1/2 text-2xl text-buttonPrimary"></i>
                        <input
                            v-model="form.search"
                            class="h-12 w-full rounded-xl border-border bg-inputBg py-3 pl-12 pr-12 text-sm font-semibold text-primary outline-none transition placeholder:text-secondary/70 focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25"
                            :placeholder="mt('Was suchst du?')"
                        />
                        <button
                            v-if="form.search"
                            type="button"
                            class="absolute right-3 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-muted text-secondary transition hover:bg-buttonPrimary hover:text-buttonTextPrimary"
                            :aria-label="mt('Suche leeren')"
                            @click="form.search = ''"
                        >
                            <i class="las la-times"></i>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="order-2 flex h-12 items-center justify-center rounded-xl border border-border bg-card px-3 text-sm font-black text-primary transition hover:border-buttonPrimary hover:text-buttonPrimary lg:hidden"
                        :aria-expanded="mobileFilterOpen"
                        aria-controls="marketplace-mobile-filter"
                        @click="mobileFilterOpen = !mobileFilterOpen"
                    >
                        <i class="las la-sliders-h text-xl"></i>
                        <span class="sr-only">{{ mt("Filter") }}</span>
                    </button>

                    <div class="hidden gap-2 lg:order-1 lg:flex lg:items-center">
                        <label class="relative block">
                            <select v-model="form.category" class="h-12 w-full rounded border-border bg-inputBg px-4 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                                <option v-for="category in localizedCategories" :key="category.value" :value="category.value">
                                    {{ category.label }}
                                </option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="inline-flex h-12 items-center gap-2 rounded border border-border bg-card px-4 text-sm font-black text-primary transition hover:border-buttonPrimary hover:text-buttonPrimary"
                            :aria-expanded="advancedFilterOpen"
                            @click="advancedFilterOpen = !advancedFilterOpen"
                        >
                            <i class="las la-sliders-h text-lg"></i>
                            {{ mt("Filter") }}
                        </button>
                    </div>

                    <div class="order-3 grid grid-cols-[auto] gap-2 lg:order-3 lg:w-auto lg:shrink-0 lg:grid-cols-[1fr_auto]">
                        <button class="flex h-12 w-12 items-center justify-center rounded-xl bg-buttonPrimary text-sm font-black text-buttonTextPrimary shadow-sm transition hover:bg-buttonPrimaryHover focus:outline-none focus:ring-2 focus:ring-buttonPrimary/30 lg:w-auto lg:px-6">
                            <i class="las la-search text-xl lg:hidden"></i>
                            <span class="hidden lg:inline">{{ mt("Suchen") }}</span>
                        </button>
                        <button type="button" class="hidden h-12 rounded border border-border bg-card px-4 text-sm font-bold text-primary transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-buttonPrimary/20 sm:block" @click="reset">
                            {{ mt("Reset") }}
                        </button>
                    </div>

                    <div
                        v-if="advancedFilterOpen"
                        class="order-4 col-span-3 hidden grid-cols-4 gap-3 rounded-xl border border-border bg-card p-3 lg:grid"
                    >
                        <label class="relative block">
                            <span class="mb-1 block text-[11px] font-black uppercase text-secondary">{{ mt("Bereich") }}</span>
                            <select v-model="form.segment" class="h-11 w-full rounded border-border bg-inputBg px-3 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                                <option v-for="segment in localizedSegments" :key="segment.value || 'all'" :value="segment.value">
                                    {{ segment.label }}
                                </option>
                            </select>
                        </label>
                        <label class="relative block">
                            <span class="mb-1 block text-[11px] font-black uppercase text-secondary">{{ mt("Verfügbarkeit") }}</span>
                            <select v-model="form.availability" class="h-11 w-full rounded border-border bg-inputBg px-3 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                                <option v-for="option in localizedAvailabilityOptions" :key="option.value || 'all'" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <label class="relative block">
                            <span class="mb-1 block text-[11px] font-black uppercase text-secondary">{{ mt("Sortierung") }}</span>
                            <select v-model="form.sort" class="h-11 w-full rounded border-border bg-inputBg px-3 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                                <option v-for="option in localizedSortOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <label class="relative block">
                            <span class="mb-1 block text-[11px] font-black uppercase text-secondary">{{ mt("Land") }}</span>
                            <select v-model="form.country" class="h-11 w-full rounded border-border bg-inputBg px-3 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25" @change="search">
                                <option value="">{{ mt("Land automatisch") }}</option>
                                <option v-for="country in pricingCountries" :key="country.country" :value="country.country">
                                    {{ country.label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div
                        v-if="mobileFilterOpen"
                        id="marketplace-mobile-filter"
                        class="order-4 col-span-3 grid max-h-[70vh] gap-3 overflow-y-auto rounded-xl border border-border bg-card p-3 shadow-2xl shadow-black/20 lg:hidden"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-black text-primary">{{ mt("Filter & Kategorien") }}</p>
                            <span class="rounded-full bg-muted px-2 py-1 text-[11px] font-bold text-secondary">{{ mt('{count} Treffer', { count: totalProducts }) }}</span>
                        </div>

                        <div class="grid gap-2">
                            <label class="relative block">
                                <span class="sr-only">{{ mt("Kategorie") }}</span>
                                <select v-model="form.category" class="h-11 w-full rounded-xl border-border bg-inputBg px-3 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                                    <option v-for="category in localizedCategories" :key="category.value" :value="category.value">
                                        {{ category.label }}
                                    </option>
                                </select>
                            </label>
                            <label class="relative block">
                                <span class="sr-only">{{ mt("Bereich") }}</span>
                                <select v-model="form.segment" class="h-11 w-full rounded-xl border-border bg-inputBg px-3 pr-9 text-sm font-bold text-primary outline-none transition focus:border-buttonPrimary focus:ring-2 focus:ring-buttonPrimary/25">
                                    <option v-for="segment in localizedSegments" :key="segment.value || 'all'" :value="segment.value">
                                        {{ segment.label }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <section>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <p class="text-xs font-black uppercase text-secondary">{{ mt("Schnellwahl") }}</p>
                                <button type="button" class="text-xs font-bold text-buttonPrimary" @click="reset(); mobileFilterOpen = false">{{ mt("Reset") }}</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="tile in quickTiles"
                                    :key="`mobile-filter-${tile.label}`"
                                    type="button"
                                    class="flex items-center gap-2 rounded-xl border border-border bg-inputBg p-2 text-left text-xs font-black text-primary"
                                    @click="selectQuickTile(tile); mobileFilterOpen = false"
                                >
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary">
                                        <i :class="[tile.icon, 'text-lg']"></i>
                                    </span>
                                    <span class="truncate">{{ tile.label }}</span>
                                </button>
                            </div>
                        </section>

                        <section>
                            <p class="mb-2 text-xs font-black uppercase text-secondary">{{ mt("Sportarten") }}</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="category in localizedSportCategories"
                                    :key="`mobile-filter-sport-${category.label}`"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border border-border bg-inputBg px-3 py-2 text-xs font-black text-primary"
                                    @click="searchCategory(category); mobileFilterOpen = false"
                                >
                                    <i :class="[category.icon, 'text-base text-buttonPrimary']"></i>
                                    {{ category.label }}
                                </button>
                            </div>
                        </section>

                        <section>
                            <p class="mb-2 text-xs font-black uppercase text-secondary">{{ mt("Produktbereiche") }}</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="segment in localizedSegments"
                                    :key="`mobile-filter-segment-${segment.value || 'all'}`"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-black transition"
                                    :class="form.segment === segment.value ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-inputBg text-primary'"
                                    @click="selectSegment(segment); mobileFilterOpen = false"
                                >
                                    <i :class="[segment.icon, 'text-base']"></i>
                                    {{ segment.label }}
                                </button>
                            </div>
                        </section>

                        <div class="grid grid-cols-[1fr_auto] gap-2">
                            <button class="h-11 rounded-xl bg-buttonPrimary px-4 text-sm font-black text-buttonTextPrimary shadow-sm transition hover:bg-buttonPrimaryHover focus:outline-none focus:ring-2 focus:ring-buttonPrimary/30" @click="mobileFilterOpen = false">
                                {{ mt("Anwenden") }}
                            </button>
                            <button type="button" class="h-11 rounded-xl border border-border bg-card px-4 text-sm font-bold text-primary transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-buttonPrimary/20" @click="reset(); mobileFilterOpen = false">
                                {{ mt("Reset") }}
                            </button>
                        </div>
                    </div>
                </form>

                <div class="mx-auto mt-2 flex max-w-7xl gap-2 overflow-x-auto pb-1 text-xs font-semibold text-secondary sm:mt-3 sm:flex-wrap">
                    <span class="shrink-0 rounded bg-buttonPrimary/15 px-2 py-1 text-buttonPrimary">{{ mt('{count} Treffer', { count: totalProducts }) }}</span>
                    <span v-if="form.category" class="rounded bg-muted px-2 py-1">{{ activeCategoryLabel }}</span>
                    <span v-if="form.segment" class="rounded bg-muted px-2 py-1">{{ activeSegmentLabel }}</span>
                    <span v-if="form.availability" class="hidden rounded bg-muted px-2 py-1 sm:inline">{{ activeAvailabilityLabel }}</span>
                    <span v-if="form.country" class="hidden rounded bg-muted px-2 py-1 sm:inline">{{ form.country }}</span>
                    <span v-if="form.sort && form.sort !== 'recommended'" class="hidden rounded bg-muted px-2 py-1 sm:inline">{{ localizedSortOptions.find((option) => option.value === form.sort)?.label }}</span>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-3 py-3 md:hidden">
                <div class="-mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <Link
                        v-for="slide in mobilePromoSlides"
                        :key="`mobile-promo-${slide.id}`"
                        :href="slide.href"
                        class="relative flex min-h-[10.5rem] w-[82vw] max-w-[22rem] shrink-0 snap-start overflow-hidden rounded-2xl border border-border bg-buttonPrimary shadow-sm"
                    >
                        <img
                            v-if="slide.image_url"
                            :src="slide.image_url"
                            :alt="slide.title"
                            class="absolute inset-0 h-full w-full object-cover"
                        />
                        <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/45 to-black/10"></div>
                        <div class="relative flex h-full min-h-[10.5rem] flex-col justify-between p-4 text-white">
                            <span class="w-fit rounded-full bg-white/15 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-white backdrop-blur">
                                {{ translated(slide.badge) }}
                            </span>
                            <div>
                                <h1 class="line-clamp-2 font-heading text-2xl font-900 leading-tight">{{ slide.title }}</h1>
                                <p class="mt-1 line-clamp-2 text-xs font-semibold leading-5 text-white/85">{{ slide.subtitle }}</p>
                                <span class="mt-3 inline-flex items-center gap-2 rounded-full bg-white px-3 py-2 text-xs font-black text-bg">
                                    {{ mt("Entdecken") }}
                                    <i class="las la-arrow-right text-base"></i>
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>

                <div v-if="flashDeals.length" class="-mx-3 mt-3 flex snap-x gap-3 overflow-x-auto px-3 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <Link
                        v-for="product in flashDeals.slice(0, 8)"
                        :key="`mobile-deal-${product.id}`"
                        :href="product.show_url"
                        class="flex h-64 w-36 shrink-0 snap-start flex-col overflow-hidden rounded-xl border border-border bg-card"
                    >
                        <div class="relative h-36 shrink-0 overflow-hidden bg-inputBg">
                            <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover" />
                            <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-5xl text-buttonPrimary']"></i>
                            <span class="absolute left-2 top-2 rounded bg-card/90 px-2 py-1 text-[10px] font-black text-buttonPrimary">{{ translated(product.badge) }}</span>
                        </div>
                        <div class="flex min-h-0 flex-1 flex-col justify-between p-2">
                            <p class="line-clamp-2 min-h-[2rem] text-[11px] font-bold leading-4 text-primary">{{ product.title }}</p>
                            <p class="mt-1 text-sm font-black text-primary">{{ grossPrice(product) }}</p>
                        </div>
                    </Link>
                </div>
            </section>

            <section
                class="mx-auto grid max-w-7xl gap-3 px-3 py-3 sm:px-4 sm:py-4"
                :class="isRtlLocale ? 'xl:grid-cols-[minmax(0,1fr)_15rem]' : 'xl:grid-cols-[15rem_minmax(0,1fr)]'"
            >
                <div class="hidden md:block xl:hidden">
                    <div class="flex gap-2 overflow-x-auto pb-1" :class="isRtlLocale ? 'justify-end' : ''">
                        <button
                            v-for="category in localizedSportCategories"
                            :key="category.label"
                            class="inline-flex shrink-0 items-center gap-2 rounded-full border border-border bg-card px-4 py-2.5 text-sm font-black text-primary shadow-sm transition hover:border-buttonPrimary hover:text-buttonPrimary"
                            :class="isRtlLocale ? 'flex-row-reverse text-right' : ''"
                            @click="searchCategory(category)"
                        >
                            <i :class="[category.icon, 'text-base text-buttonPrimary']"></i>
                            {{ category.label }}
                        </button>
                    </div>
                </div>

                <aside
                    class="hidden rounded border border-border bg-card p-3 shadow-sm xl:block"
                    :class="isRtlLocale ? 'order-2' : 'order-1'"
                >
                    <button
                        v-for="category in localizedSportCategories"
                        :key="category.label"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-sm font-bold leading-5 text-primary transition hover:bg-muted hover:text-buttonPrimary"
                        :class="isRtlLocale ? 'flex-row-reverse text-right' : 'text-left'"
                        @click="searchCategory(category)"
                    >
                        <i :class="[category.icon, 'shrink-0 text-2xl text-buttonPrimary']"></i>
                        <span class="min-w-0 flex-1 whitespace-normal">{{ category.label }}</span>
                    </button>
                </aside>

                <section class="hidden gap-4 md:grid lg:grid-cols-[minmax(0,1fr)_18rem]" :class="isRtlLocale ? 'order-1' : 'order-2'">
                    <Link
                        :href="heroProduct?.show_url || route('guest.marketplace')"
                            class="relative min-h-[15rem] overflow-hidden rounded-xl border border-border bg-buttonPrimary shadow-sm md:min-h-[22rem]"
                    >
                        <img
                            v-if="heroImageUrl"
                            :src="heroImageUrl"
                            :alt="heroProduct?.title || 'Airmius Marketplace'"
                            class="absolute inset-0 h-full w-full object-cover"
                        />
                        <div class="absolute inset-0 bg-gradient-to-r from-black/75 via-black/35 to-transparent"></div>
                        <div class="relative flex min-h-[15rem] max-w-2xl flex-col justify-end p-5 text-white md:min-h-[22rem] md:justify-center md:p-8">
                            <p class="text-xs font-black uppercase tracking-wide text-white/80">{{ mt("Marketplace") }}</p>
                            <h1 class="mt-2 font-heading text-2xl font-900 leading-tight md:text-5xl">
                                {{ mt("Sport Deals für Training, Team und Wettkampf") }}
                            </h1>
                            <p class="mt-3 hidden max-w-xl text-sm leading-6 text-white/90 sm:block md:mt-4">
                                {{ mt("Weniger scrollen, schneller finden: Kategorien, Aktionen und kuratierte Reihen statt alle Produkte auf einmal.") }}
                            </p>
                            <span class="mt-4 inline-flex w-fit rounded-xl bg-buttonPrimary px-4 py-2.5 text-sm font-black text-buttonTextPrimary md:mt-5 md:py-3">
                                {{ mt("Jetzt entdecken") }}
                            </span>
                        </div>
                    </Link>

                    <div class="grid gap-4">
                        <div class="rounded border border-border bg-card p-4 shadow-sm">
                            <p class="text-sm font-black text-primary">{{ mt("Hilfe & Bestellung") }}</p>
                            <p class="mt-1 text-xs leading-5 text-secondary">{{ mt("Gastbestellung, Login-Bestellung und Anbieterangebote sind verfügbar.") }}</p>
                        </div>
                        <Link :href="currentUser ? route('auth.commerce.index') : route('login')" class="rounded border border-border bg-card p-4 shadow-sm transition hover:bg-muted">
                            <p class="text-sm font-black text-primary">{{ mt("Anbieter werden") }}</p>
                            <p class="mt-1 text-xs leading-5 text-secondary">{{ mt("Vereine, Trainer und Shops können Angebote einstellen.") }}</p>
                        </Link>
                        <Link
                            v-for="product in heroSideProducts"
                            :key="product.id"
                            :href="product.show_url"
                            class="flex gap-3 rounded border border-border bg-card p-3 shadow-sm transition hover:bg-muted"
                        >
                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded bg-inputBg">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover" />
                                <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-2xl text-buttonPrimary']"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 break-words text-xs font-black leading-4 text-primary">{{ product.title }}</p>
                                <p class="text-xs font-bold text-buttonPrimary">{{ grossPrice(product) }} {{ mt('brutto') }}</p>
                            </div>
                        </Link>
                    </div>
                </section>

            </section>

            <section class="mx-auto hidden max-w-7xl px-3 sm:block sm:px-4">
                <div class="flex gap-2 overflow-x-auto rounded bg-card p-2 shadow-sm sm:grid sm:grid-cols-2 sm:gap-3 sm:overflow-visible sm:p-4 lg:grid-cols-6">
                    <button
                        v-for="tile in quickTiles"
                        :key="tile.label"
                        class="flex min-w-[9rem] shrink-0 items-center gap-2 rounded bg-muted p-2 text-left transition hover:border-borderHover hover:bg-table sm:min-w-0 sm:gap-3 sm:p-3"
                        @click="selectQuickTile(tile)"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary sm:h-12 sm:w-12">
                            <i :class="[tile.icon, 'text-xl sm:text-2xl']"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-black text-primary">{{ tile.label }}</span>
                            <span class="hidden truncate text-xs text-secondary sm:block">{{ tile.hint }}</span>
                        </span>
                    </button>
                </div>
            </section>

            <section class="mx-auto mt-4 hidden max-w-7xl px-4 md:block">
                <div class="grid gap-3 rounded border border-border bg-card p-4 shadow-sm md:grid-cols-4">
                    <div
                        v-for="benefit in localizedTrustBenefits"
                        :key="benefit.label"
                        class="flex gap-3 rounded bg-bg p-3"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-buttonPrimary/10 text-buttonPrimary">
                            <i :class="[benefit.icon, 'text-xl']"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-black text-primary">{{ benefit.label }}</span>
                            <span class="mt-1 block text-xs leading-5 text-secondary">{{ benefit.description }}</span>
                        </span>
                    </div>
                </div>
            </section>

            <section v-if="marketplaceLocations.length" class="mx-auto mt-4 max-w-7xl px-3 sm:px-4">
                <div class="rounded border border-border bg-card shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-buttonPrimary">{{ mt('Standorte') }}</p>
                            <h2 class="text-lg font-black text-primary">{{ mt('Filialen, Boutiquen und Abholstationen') }}</h2>
                            <p class="mt-1 text-sm text-secondary">{{ mt('Öffentliche Anbieterstandorte, die du besuchen oder für Abholung nutzen kannst.') }}</p>
                        </div>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs font-bold text-secondary">
                            {{ mt('{count} Standorte', { count: marketplaceLocations.length }) }}
                        </span>
                    </div>

                    <div class="flex gap-3 overflow-x-auto p-3 [scrollbar-width:thin] md:grid md:grid-cols-2 md:overflow-visible lg:grid-cols-3">
                        <article
                            v-for="location in marketplaceLocations"
                            :key="`marketplace-location-${location.id}`"
                            class="min-w-[18rem] rounded border border-border bg-bg p-4 md:min-w-0"
                        >
                            <div class="flex items-start gap-3">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded bg-buttonPrimary/10 text-sm font-black text-buttonPrimary">
                                    <img v-if="location.image_url || location.provider?.logo_url" :src="location.image_url || location.provider.logo_url" :alt="location.name" class="h-full w-full object-cover" />
                                    <span v-else>{{ location.provider?.initials || 'AM' }}</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-buttonPrimary/10 px-2 py-1 text-[11px] font-black text-buttonPrimary">{{ locationTypeLabel(location.type) }}</span>
                                        <span v-if="location.pickup_enabled" class="rounded-full bg-success/10 px-2 py-1 text-[11px] font-black text-success">{{ mt('Abholung') }}</span>
                                        <span v-if="location.returns_enabled" class="rounded-full bg-warning/10 px-2 py-1 text-[11px] font-black text-warning">{{ mt('Rückgabe') }}</span>
                                    </div>
                                    <h3 class="mt-2 line-clamp-2 text-base font-black text-primary">{{ location.name }}</h3>
                                    <p class="mt-1 text-sm font-semibold text-secondary">{{ location.address }}</p>
                                </div>
                            </div>

                            <div class="mt-3 space-y-2 text-xs text-secondary">
                                <p v-if="location.opening_hours" class="flex gap-2">
                                    <i class="las la-clock mt-0.5 text-base text-buttonPrimary"></i>
                                    <span>{{ location.opening_hours }}</span>
                                </p>
                                <p v-if="location.note" class="flex gap-2">
                                    <i class="las la-info-circle mt-0.5 text-base text-buttonPrimary"></i>
                                    <span>{{ location.note }}</span>
                                </p>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3 border-t border-border pt-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-primary">{{ location.provider?.name }}</p>
                                    <p class="truncate text-xs text-secondary">{{ translated(location.provider?.type) }}</p>
                                </div>
                                <Link
                                    v-if="location.provider?.url"
                                    :href="location.provider.url"
                                    class="shrink-0 rounded border border-border px-3 py-2 text-xs font-black text-primary transition hover:border-buttonPrimary hover:text-buttonPrimary"
                                >
                                    {{ mt('Ansehen') }}
                                </Link>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-3 hidden max-w-7xl px-3 sm:mt-4 sm:block sm:px-4">
                <div class="rounded bg-card p-3 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-black text-primary">{{ mt("Produktbereiche") }}</h2>
                            <p class="text-xs text-secondary">{{ mt('Aktiv:') }} {{ activeSegmentLabel }}</p>
                        </div>
                        <button class="text-xs font-bold text-buttonPrimary" @click="selectSegment({ value: '' })">{{ mt("Alle anzeigen") }}</button>
                    </div>
                    <div class="mt-3 flex gap-2 overflow-x-auto overscroll-x-contain rounded-lg pb-2 [scrollbar-color:theme(colors.border)_transparent] [scrollbar-width:thin]">
                        <button
                            v-for="segment in localizedSegments"
                            :key="segment.value || 'all'"
                            type="button"
                            class="flex shrink-0 items-center gap-2 rounded border px-3 py-2 text-xs font-bold transition"
                            :class="form.segment === segment.value ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-inputBg text-primary hover:border-borderHover'"
                            @click="selectSegment(segment)"
                        >
                            <i :class="[segment.icon, 'text-base']"></i>
                            {{ segment.label }}
                        </button>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-3 max-w-7xl px-3 sm:mt-4 sm:px-4">
                <div class="rounded bg-buttonPrimary px-3 py-2 text-buttonTextPrimary shadow-sm sm:px-4 sm:py-3">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="flex items-center gap-2 text-lg font-black">
                            <i class="las la-bolt text-2xl"></i>
                            {{ mt("Aktuelle Angebote") }}
                        </h2>
                        <span class="hidden text-sm font-bold sm:inline">{{ mt("Schnellvergleich nach Preis, Steuer und Anbieter") }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 rounded-b bg-card p-3 shadow-sm md:grid-cols-4 xl:grid-cols-6">
                    <Link
                        v-for="product in flashDeals"
                        :key="product.id"
                        :href="product.show_url"
                        class="group flex h-full flex-col overflow-hidden rounded border border-border bg-card transition hover:border-borderHover"
                    >
                        <div class="relative h-32 shrink-0 overflow-hidden bg-inputBg sm:h-auto sm:aspect-[4/3]">
                            <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover transition group-hover:scale-105" />
                            <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-5xl text-buttonPrimary']"></i>
                            <span class="absolute left-2 top-2 rounded bg-card/90 px-2 py-1 text-[11px] font-black text-buttonPrimary">{{ translated(product.badge) }}</span>
                        </div>
                        <div class="flex flex-1 flex-col p-2">
                            <h3 class="line-clamp-2 min-h-[2.25rem] text-xs font-semibold text-primary">{{ product.title }}</h3>
                            <p class="mt-1 text-sm font-black text-primary">{{ grossPrice(product) }}</p>
                                                <p class="text-[11px] text-secondary">{{ mt('brutto') }} · {{ netPrice(product) }} {{ mt('netto') }}</p>
                            <p class="mt-1 truncate text-[11px] font-semibold text-secondary">{{ translated(product.delivery_label) }}</p>
                            <p v-if="product.old_price_cents" class="text-[11px] text-secondary line-through">{{ formatPrice(product.old_price_cents, price(product).currency) }}</p>
                        </div>
                    </Link>
                </div>
            </section>

            <section class="mx-auto mt-4 hidden max-w-7xl px-4 md:block">
                <AdSlot placement="marketplace_card" variant="banner" :fallback="false" />
            </section>

            <section class="mx-auto mt-4 hidden max-w-7xl gap-4 px-4 md:grid xl:grid-cols-[1fr_20rem]">
                <div class="rounded bg-card p-4 shadow-sm">
                    <h2 class="text-center text-lg font-black text-primary">{{ mt("Alles für deinen Sportalltag") }}</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
                        <Link
                            v-for="product in essentialDeals"
                            :key="product.id"
                            :href="product.show_url"
                            class="text-center"
                        >
                            <div class="mx-auto aspect-square max-w-[8rem] overflow-hidden rounded-full bg-buttonPrimary/20">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover" />
                                <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-5xl text-white']"></i>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs font-semibold text-primary">{{ product.title }}</p>
                        </Link>
                    </div>
                </div>

                <div class="rounded bg-card p-4 shadow-sm">
                    <h2 class="text-lg font-black text-primary">{{ mt("Offizielle Stores") }}</h2>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <div
                            v-for="store in localizedOfficialStores"
                            :key="store.name"
                            class="rounded border border-border bg-muted p-3 text-center"
                        >
                            <i :class="[store.icon, 'text-3xl text-buttonPrimary']"></i>
                            <p class="mt-1 truncate text-xs font-black text-primary">{{ store.name }}</p>
                            <p class="text-xs font-bold text-error">{{ store.discount }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-4 hidden max-w-7xl space-y-4 px-4 md:block">
                <div v-if="learningDeals.length" class="rounded bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <h2 class="text-lg font-black text-primary">{{ mt("Kurse & Camps") }}</h2>
                        <button class="text-sm font-bold text-buttonPrimary" @click="selectQuickTile({ category: 'course' })">{{ mt("Mehr sehen") }}</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 p-3 md:grid-cols-5">
                        <Link v-for="product in learningDeals" :key="product.id" :href="product.show_url" class="group">
                            <div class="aspect-[4/3] overflow-hidden rounded bg-inputBg">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover transition group-hover:scale-105" />
                                <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-5xl text-buttonPrimary']"></i>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs font-semibold text-primary">{{ product.title }}</p>
                            <p class="text-sm font-black text-primary">{{ grossPrice(product) }}</p>
                                    <p class="text-[11px] text-secondary">{{ netPrice(product) }} {{ mt('netto') }}</p>
                        </Link>
                    </div>
                </div>

                <div v-if="serviceDeals.length" class="rounded bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <h2 class="text-lg font-black text-primary">{{ mt("Services & Analysen") }}</h2>
                        <button class="text-sm font-bold text-buttonPrimary" @click="selectQuickTile({ category: 'service' })">{{ mt("Mehr sehen") }}</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 p-3 md:grid-cols-5">
                        <Link v-for="product in serviceDeals" :key="product.id" :href="product.show_url" class="group">
                            <div class="aspect-[4/3] overflow-hidden rounded bg-inputBg">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover transition group-hover:scale-105" />
                                <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-5xl text-buttonPrimary']"></i>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs font-semibold text-primary">{{ product.title }}</p>
                            <p class="text-sm font-black text-primary">{{ grossPrice(product) }}</p>
                                        <p class="text-[11px] text-secondary">{{ netPrice(product) }} {{ mt('netto') }}</p>
                        </Link>
                    </div>
                </div>

                <div v-if="outfitPlans.length" class="rounded bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <h2 class="text-lg font-black text-primary">{{ mt("Outfit-Abos") }}</h2>
                        <button class="text-sm font-bold text-buttonPrimary" @click="selectQuickTile({ category: 'outfit_subscription' })">{{ mt("Mehr sehen") }}</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 p-3 md:grid-cols-4">
                        <Link v-for="plan in outfitPlans" :key="plan.id" :href="route('login')" class="rounded border border-border p-3 transition hover:border-borderHover">
                            <div class="flex h-24 items-center justify-center rounded bg-muted">
                                <i class="las la-tshirt text-5xl text-buttonPrimary"></i>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs font-semibold text-primary">{{ plan.title }}</p>
                            <p class="text-sm font-black text-primary">{{ grossPrice(plan) }}</p>
                                        <p class="text-[11px] text-secondary">{{ netPrice(plan) }} {{ mt('netto') }}</p>
                        </Link>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-3 max-w-7xl px-3 sm:mt-4 sm:px-4">
                <div class="rounded bg-card shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-3 py-3 sm:px-4">
                        <div>
                            <h2 class="text-base font-black text-primary sm:text-lg">{{ mt("Alle Angebote") }}</h2>
                            <p class="text-xs text-secondary">
                                {{ mt('{count} Treffer, angezeigt werden maximal {perPage} pro Seite.', { count: totalProducts, perPage: products.per_page || 40 }) }}
                            </p>
                        </div>
                        <Link :href="currentUser ? route('auth.commerce.index') : route('login')" class="hidden rounded bg-buttonPrimary px-4 py-2 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover sm:inline-flex">
                            {{ mt("Angebot einstellen") }}
                        </Link>
                    </div>

                    <div class="space-y-4 p-2 sm:space-y-5 sm:p-3">
                        <div v-for="group in productGroups" :key="group.key" class="rounded border border-border bg-bg/40 p-2 sm:p-3">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="flex items-center gap-2 text-base font-black text-primary">
                                    <i :class="[group.icon, 'text-xl text-buttonPrimary']"></i>
                                    {{ group.label }}
                                </h3>
                                <span class="rounded bg-muted px-2 py-1 text-xs font-bold text-secondary">{{ group.items.length }} Angebote</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-4 xl:grid-cols-5">
                                <article
                                    v-for="product in group.items"
                                    :key="product.id"
                                    class="group flex h-full overflow-hidden rounded border border-border bg-card transition hover:border-borderHover"
                                >
                                    <Link :href="product.show_url" class="flex w-full flex-col">
                                        <div class="relative h-36 shrink-0 overflow-hidden bg-inputBg sm:h-auto sm:aspect-square">
                                            <img v-if="product.image_url" :src="product.image_url" :alt="product.title" class="h-full w-full object-cover transition group-hover:scale-105" />
                                            <i v-else :class="[product.visual_icon, 'flex h-full items-center justify-center text-6xl text-buttonPrimary']"></i>
                                            <span class="absolute left-2 top-2 rounded bg-card/90 px-2 py-1 text-[11px] font-black text-buttonPrimary">{{ translated(product.badge) }}</span>
                                        </div>
                                        <div class="flex flex-1 flex-col p-2 sm:p-3">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-secondary">
                                                {{ categoryLabels[product.category] || translated(product.category) }}
                                            </p>
                                            <h3 class="mt-1 line-clamp-2 min-h-[2.25rem] text-xs font-bold text-primary group-hover:text-buttonPrimary sm:min-h-[2.5rem] sm:text-sm">
                                                {{ product.title }}
                                            </h3>
                                            <p class="mt-1 hidden text-xs leading-5 text-secondary sm:line-clamp-2">
                                                {{ shortDescription(product.description, 78) }}
                                            </p>
                                            <div class="mt-auto pt-3">
                                                <p class="text-base font-black text-primary sm:text-lg">{{ grossPrice(product) }}</p>
                                                <p class="hidden text-xs text-secondary sm:block">{{ taxInfo(product) }}</p>
                                                <p v-if="product.old_price_cents" class="text-xs text-secondary line-through">{{ formatPrice(product.old_price_cents, price(product).currency) }}</p>
                                            </div>
                                            <div class="mt-2 hidden items-center justify-between gap-2 text-xs text-secondary sm:flex">
                                                <span class="inline-flex min-w-0 items-center gap-1">
                                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded-full bg-buttonPrimary/10 text-[9px] font-black text-buttonPrimary">
                                                        <img v-if="product.provider_profile?.logo_url" :src="product.provider_profile.logo_url" :alt="product.provider_profile.name" class="h-full w-full object-cover" />
                                                        <span v-else>{{ product.provider_profile?.initials || 'AM' }}</span>
                                                    </span>
                                                    <span class="truncate">{{ product.provider_profile?.name || product.provider_name || 'Airmius Marketplace' }}</span>
                                                    <i v-if="product.provider_profile?.verified" class="las la-check-circle text-base text-success"></i>
                                                </span>
                                                <span class="shrink-0 font-semibold text-primary">{{ availabilityLabel(product) }}</span>
                                            </div>
                                            <div class="mt-3 hidden flex-wrap gap-1 sm:flex">
                                                <span
                                                    v-for="badge in product.trust_badges?.slice(0, 2)"
                                                    :key="`${product.id}-${badge}`"
                                                    class="rounded bg-muted px-2 py-1 text-[11px] font-bold text-secondary"
                                                >
                                                    {{ badge }}
                                                </span>
                                            </div>
                                            <span class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded border border-buttonPrimary/40 px-3 py-2 text-xs font-black text-buttonPrimary transition group-hover:bg-buttonPrimary group-hover:text-buttonTextPrimary">
                                                {{ mt("Details ansehen") }}
                                                <i class="las la-arrow-right text-base"></i>
                                            </span>
                                        </div>
                                    </Link>
                                </article>
                            </div>
                        </div>

                        <div v-if="!allOfferItems.length" class="rounded border border-border bg-muted p-8 text-center">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-buttonPrimary/10 text-buttonPrimary">
                                <i class="las la-search text-3xl"></i>
                            </span>
                            <p class="mt-4 text-lg font-bold text-primary">{{ mt("Keine passenden Angebote gefunden.") }}</p>
                            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-secondary">
                                {{ mt("Probiere einen allgemeineren Suchbegriff, entferne Filter oder springe direkt in einen beliebten Bereich.") }}
                            </p>
                            <div class="mt-5 flex flex-wrap justify-center gap-2">
                                <button class="rounded bg-buttonPrimary px-4 py-2 text-sm font-black text-buttonTextPrimary hover:bg-buttonPrimaryHover" @click="reset">
                                    {{ mt("Filter zurücksetzen") }}
                                </button>
                                <button
                                    v-for="tile in quickTiles.slice(1, 5)"
                                    :key="`empty-${tile.label}`"
                                    class="rounded border border-border bg-card px-4 py-2 text-sm font-semibold text-primary hover:bg-bg"
                                    @click="selectQuickTile(tile)"
                                >
                                    {{ tile.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="paginationLinks.length > 1" class="flex flex-wrap justify-center gap-2 border-t border-border px-4 py-4">
                        <Link
                            v-for="link in paginationLinks"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url"
                            preserve-scroll
                            class="rounded border px-3 py-2 text-sm font-bold"
                            :class="link.active ? 'border-borderHover bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-card text-primary hover:bg-muted'"
                        >
                            {{ paginationLabel(link.label) }}
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import LanguageDropdown from '@/Components/LanguageDropdown.vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import UserCard from '@/Components/Auth/UserCard.vue'
import SkipLink from '@/Components/Guest/SkipLink.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    toggleMobile: Function,
})

const mobileOpen = ref(false)
const menuPanel = ref(null)
const menuButton = ref(null)
const closeButton = ref(null)
const page = usePage()
const isRtl = computed(() => page.props.direction === 'rtl')
let lockedScrollY = 0
let previousBodyStyle = null

const scrollTo = (id) => {
    const el = document.getElementById(id)

    if (!el) {
        mobileOpen.value = false
        router.visit(`${route('welcome')}#${id}`)
        return
    }

    const offset = 80
    const elementPosition = el.getBoundingClientRect().top + window.pageYOffset

    window.scrollTo({
        top: elementPosition - offset,
        behavior: 'smooth',
    })

    mobileOpen.value = false
}

const toggleMobile = () => {
    mobileOpen.value = !mobileOpen.value
}

const closeMobile = () => {
    mobileOpen.value = false
}

const restoreBody = () => {
    if (!previousBodyStyle) return

    Object.assign(document.body.style, previousBodyStyle)
    previousBodyStyle = null
    window.scrollTo({ top: lockedScrollY, behavior: 'auto' })
}

const handleMenuKeydown = (event) => {
    if (event.key === 'Escape') {
        event.preventDefault()
        closeMobile()
        return
    }

    if (event.key !== 'Tab' || !menuPanel.value) return

    const focusable = Array.from(menuPanel.value.querySelectorAll(
        'a[href], button:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )).filter((element) => !element.hasAttribute('hidden'))

    if (!focusable.length) return

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

watch(mobileOpen, async (open) => {
    if (open) {
        lockedScrollY = window.scrollY
        previousBodyStyle = {
            overflow: document.body.style.overflow,
            position: document.body.style.position,
            width: document.body.style.width,
            top: document.body.style.top,
        }
        document.body.style.overflow = 'hidden'
        document.body.style.position = 'fixed'
        document.body.style.width = '100%'
        document.body.style.top = `-${lockedScrollY}px`
        await nextTick()
        closeButton.value?.focus()
        return
    }

    restoreBody()
    await nextTick()
    menuButton.value?.focus()
})

watch(() => page.url, closeMobile)

onBeforeUnmount(() => {
    restoreBody()
})

const navItems = [
    { id: 'vorteile', label: 'guest.nav.benefits' },
    { id: 'funktionen', label: 'guest.nav.features' },
    { id: 'sportarten', label: 'guest.nav.sports' },
    { id: 'shop', label: 'guest.nav.shop', href: route('guest.marketplace') },
    { id: 'über', label: 'guest.nav.about' },
    { id: 'kontakt', label: 'guest.nav.contact' },
]
</script>

<template>
    <SkipLink />

    <nav id="nav" class="fixed inset-x-0 top-0 z-50 w-full nav-blur border-b backdrop-blur" :aria-label="$t('guest.nav.main_aria')">
        <div class="relative mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
            <button
                type="button"
                @click="scrollTo('hero')"
                class="flex shrink-0 items-center font-heading font-900 text-xl tracking-tight"
                :aria-label="$t('guest.nav.home_aria')"
            >
                <ApplicationLogo class="h-10 w-auto max-w-[11rem]" />
            </button>

            <div class="hidden min-w-0 flex-1 items-center justify-center gap-4 overflow-hidden text-sm font-medium text-secondary lg:flex xl:gap-6">
                <template v-for="item in navItems" :key="item.id">
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        class="whitespace-nowrap hover:text-primary transition"
                    >
                        {{ $t(item.label) }}
                    </Link>
                    <button
                        v-else
                        type="button"
                        @click="scrollTo(item.id)"
                        class="whitespace-nowrap hover:text-primary transition"
                    >
                        {{ $t(item.label) }}
                    </button>
                </template>
                <Link :href="route('guest.blog.index')" class="whitespace-nowrap hover:text-primary transition">
                    {{ $t('guest.nav.blog') }}
                </Link>
            </div>

            <div
                class="ms-auto flex shrink-0 items-center gap-2 sm:gap-3"
            >
                <LanguageDropdown />

                <Link
                    v-if="props.canLogin && !$page.props.auth.user"
                    :href="route('login')"
                    class="hidden lg:inline-block text-sm font-semibold text-air-blue hover:text-borderHover transition"
                >
                    {{ $t('guest.nav.login') }}
                </Link>

                <Link
                    v-if="props.canRegister && !$page.props.auth.user"
                    :href="route('register')"
                    class="hidden rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover lg:inline-flex"
                >
                    {{ $t('guest.nav.register') }}
                </Link>

                <Link
                    v-if="$page.props.auth.user"
                    :href="route('auth.feed.index')"
                    class="hidden sm:inline-flex items-center text-sm font-semibold text-air-blue hover:text-borderHover transition"
                >
                    <i class="las la-rocket"></i><span class="ms-2">{{ $t('guest.nav.feed') }}</span>
                </Link>

                <div v-if="$page.props.auth.user">
                    <UserCard />
                </div>

                <button
                    ref="menuButton"
                    type="button"
                    class="lg:hidden text-primary hover:text-air-blue p-2"
                    :aria-expanded="mobileOpen"
                    aria-controls="guest-mobile-menu"
                    :aria-label="$t('guest.nav.open_menu_aria')"
                    @click="toggleMobile"
                >
                    <i class="las la-bars text-2xl"></i>
                </button>
            </div>
        </div>
    </nav>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileOpen"
                class="fixed inset-0 z-[99999] lg:hidden"
                role="dialog"
                aria-modal="true"
                aria-labelledby="guest-mobile-menu-title"
                @keydown="handleMenuKeydown"
            >
                <button
                    type="button"
                    class="absolute inset-0 bg-black/70 backdrop-blur-sm"
                    :aria-label="$t('guest.nav.close_menu_aria')"
                    @click="mobileOpen = false"
                ></button>

                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    :enter-from-class="isRtl ? '-translate-x-full' : 'translate-x-full'"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="translate-x-0"
                    :leave-to-class="isRtl ? '-translate-x-full' : 'translate-x-full'"
                >
                    <div
                        v-if="mobileOpen"
                        id="guest-mobile-menu"
                        ref="menuPanel"
                        class="absolute end-0 top-0 flex h-full w-full flex-col border-s border-border bg-card"
                    >
                        <div class="flex justify-between items-center p-5 border-b border-border">
                            <span id="guest-mobile-menu-title" class="text-primary font-bold text-lg">{{ $t('guest.nav.menu') }}</span>
                            <button ref="closeButton" type="button" :aria-label="$t('guest.nav.close_menu_aria')" @click="closeMobile" class="text-primary text-2xl p-1">×</button>
                        </div>

                        <div class="flex-1 overflow-y-auto px-6 py-6 flex flex-col gap-1">
                            <template v-for="item in navItems" :key="item.id">
                                <Link
                                    v-if="item.href"
                                    :href="item.href"
                                    @click="mobileOpen = false"
                                    class="py-3 text-start text-lg text-secondary hover:text-primary transition"
                                >
                                    {{ $t(item.label) }}
                                </Link>
                                <button
                                    v-else
                                    type="button"
                                    @click="scrollTo(item.id)"
                                    class="py-3 text-start text-lg text-secondary hover:text-primary transition"
                                >
                                    {{ $t(item.label) }}
                                </button>
                            </template>

                            <Link
                                :href="route('guest.blog.index')"
                                @click="mobileOpen = false"
                                class="py-3 text-start text-lg text-secondary hover:text-primary transition"
                            >
                                {{ $t('guest.nav.blog') }}
                            </Link>

                            <Link
                                :href="route('guest.werbeagentur')"
                                @click="mobileOpen = false"
                                class="py-3 text-start text-lg text-secondary hover:text-primary transition"
                            >
                                {{ $t('guest.nav.agency') }}
                            </Link>

                            <div class="border-t border-border my-4"></div>

                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('auth.feed.index')"
                                @click="mobileOpen = false"
                                class="py-3 text-start text-lg text-air-blue hover:text-borderHover transition"
                            >
                                {{ $t('guest.nav.feed') }}
                            </Link>

                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('auth.users.show', $page.props.auth.user.id)"
                                @click="mobileOpen = false"
                                class="py-3 text-start text-lg text-air-blue hover:text-borderHover transition"
                            >
                                {{ $t('guest.nav.profile') }}
                            </Link>

                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('auth.settings')"
                                @click="mobileOpen = false"
                                class="py-3 text-start text-lg text-air-blue hover:text-borderHover transition"
                            >
                                {{ $t('guest.nav.settings') }}
                            </Link>

                            <Link
                                v-if="props.canLogin && !$page.props.auth.user"
                                :href="route('login')"
                                @click="mobileOpen = false"
                                class="py-3 text-start text-lg text-air-blue hover:text-borderHover transition"
                            >
                                {{ $t('guest.nav.login') }}
                            </Link>

                            <Link
                                v-if="props.canRegister && !$page.props.auth.user"
                                :href="route('register')"
                                @click="mobileOpen = false"
                                class="py-3 text-start text-lg font-semibold text-air-blue hover:text-borderHover transition"
                            >
                                {{ $t('guest.nav.register') }}
                            </Link>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

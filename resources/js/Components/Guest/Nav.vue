<script setup>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import LanguageDropdown from '@/Components/LanguageDropdown.vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import UserCard from '@/Components/Auth/UserCard.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    toggleMobile: Function,
})

const mobileOpen = ref(false)

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

watch(mobileOpen, (val) => {
    document.body.style.overflow = val ? 'hidden' : ''
    document.body.style.position = val ? 'fixed' : ''
    document.body.style.width = val ? '100%' : ''
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
    <nav id="nav" class="fixed top-0 left-0 w-full z-50 nav-blur border-b backdrop-blur">
        <div class="relative mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
            <button @click="scrollTo('hero')" class="flex shrink-0 items-center gap-2 font-heading font-900 text-xl tracking-tight">
                <ApplicationLogo class="w-8 h-8" />
                <span class="text-primary font-[--ubuntu]">AIRMIUS</span>
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

            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
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
                    <i class="las la-rocket"></i><span class="ml-2">{{ $t('guest.nav.feed') }}</span>
                </Link>

                <div v-if="$page.props.auth.user">
                    <UserCard />
                </div>

                <button @click="toggleMobile" class="lg:hidden text-primary hover:text-air-blue p-2">
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
            <div v-if="mobileOpen" class="fixed inset-0 z-[99999] lg:hidden">
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="mobileOpen = false"></div>

                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <div v-if="mobileOpen" class="absolute right-0 top-0 h-full w-full bg-card border-l border-border flex flex-col">
                        <div class="flex justify-between items-center p-5 border-b border-border">
                            <span class="text-primary font-bold text-lg">{{ $t('guest.nav.menu') }}</span>
                            <button @click="mobileOpen = false" class="text-primary text-2xl p-1">&times;</button>
                        </div>

                        <div class="flex-1 overflow-y-auto px-6 py-6 flex flex-col gap-1">
                            <template v-for="item in navItems" :key="item.id">
                                <Link
                                    v-if="item.href"
                                    :href="item.href"
                                    @click="mobileOpen = false"
                                    class="text-left py-3 text-lg text-secondary hover:text-primary transition"
                                >
                                    {{ $t(item.label) }}
                                </Link>
                                <button
                                    v-else
                                    @click="scrollTo(item.id)"
                                    class="text-left py-3 text-lg text-secondary hover:text-primary transition"
                                >
                                    {{ $t(item.label) }}
                                </button>
                            </template>

                            <Link
                                :href="route('guest.blog.index')"
                                @click="mobileOpen = false"
                                class="text-left py-3 text-lg text-secondary hover:text-primary transition"
                            >
                                {{ $t('guest.nav.blog') }}
                            </Link>

                            <Link
                                :href="route('guest.werbeagentur')"
                                @click="mobileOpen = false"
                                class="text-left py-3 text-lg text-secondary hover:text-primary transition"
                            >
                                {{ $t('guest.nav.agency') }}
                            </Link>

                            <div class="border-t border-border my-4"></div>

                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('auth.feed.index')"
                                @click="mobileOpen = false"
                                class="text-left py-3 text-lg text-air-blue hover:text-borderHover transition"
                            >
                                {{ $t('guest.nav.feed') }}
                            </Link>

                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('auth.users.show', $page.props.auth.user.id)"
                                @click="mobileOpen = false"
                                class="text-left py-3 text-lg text-air-blue hover:text-borderHover transition"
                            >
                                {{ $t('guest.nav.profile') }}
                            </Link>

                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('auth.settings')"
                                @click="mobileOpen = false"
                                class="text-left py-3 text-lg text-air-blue hover:text-borderHover transition"
                            >
                                {{ $t('guest.nav.settings') }}
                            </Link>

                            <Link
                                v-if="props.canLogin && !$page.props.auth.user"
                                :href="route('login')"
                                @click="mobileOpen = false"
                                class="text-left py-3 text-lg text-air-blue hover:text-borderHover transition"
                            >
                                {{ $t('guest.nav.login') }}
                            </Link>

                            <Link
                                v-if="props.canRegister && !$page.props.auth.user"
                                :href="route('register')"
                                @click="mobileOpen = false"
                                class="text-left py-3 text-lg font-semibold text-air-blue hover:text-borderHover transition"
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


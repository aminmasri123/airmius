<script setup>
import { ref, watch } from 'vue'

import LanguageDropdown from '@/Components/LanguageDropdown.vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import { Link } from '@inertiajs/vue3';
    const props = defineProps({
        canLogin: Boolean,
        toggleMobile: Function,
    })

const mobileOpen = ref(false)

    const scrollTo = (id) => {
        const el = document.getElementById(id)
        if (el) {
            const offset = 80 // 👈 dein Abstand
            const elementPosition = el.getBoundingClientRect().top + window.pageYOffset
            const offsetPosition = elementPosition - offset

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            })

            mobileOpen.value = false
        }
    }

    const toggleMobile = () => {
        mobileOpen.value = !mobileOpen.value
    }
    watch(mobileOpen, (val) => {
        if (val) {
            document.body.style.overflow = 'hidden'
            document.body.style.position = 'fixed'
            document.body.style.width = '100%'
        } else {
            document.body.style.overflow = ''
            document.body.style.position = ''
            document.body.style.width = ''
        }
    })
</script>

<template>
    <nav id="nav" class="fixed top-0 left-0 w-full z-50 nav-blur border-b border-white/5 bg-white/10 backdrop-blur">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
                <button @click="scrollTo('hero')"
                    class="flex items-center gap-2 font-heading font-900 text-xl tracking-tight">
                    <ApplicationLogo class="w-8 h-8" />
                    <span class="text-white font-[--ubuntu]">AIRMIUS</span>
                </button>

                <!-- Desktop Links: erst ab md anzeigen -->
                <div class="hidden md:flex items-center gap-6 text-sm font-medium text-gray-300">
                    <button @click="scrollTo('vorteile')" class="hover:text-white transition">Vorteile</button>
                    <button @click="scrollTo('funktionen')" class="hover:text-white transition">Funktionen</button>
                    <button @click="scrollTo('sportarten')" class="hover:text-white transition">Sportarten</button>
                    <button @click="scrollTo('ueber')" class="hover:text-white transition">Über uns</button>
                    <button @click="scrollTo('blog')" class="hover:text-white transition">Blog</button>
                    <button @click="scrollTo('kontakt')" class="hover:text-white transition">Kontakt</button>
                </div>

                <div class="flex items-center gap-3">
                    <LanguageDropdown />

                    <!-- Login nur Desktop -->


                    <!-- CTA nur Desktop -->
                    <!-- <button @click="scrollTo('hero')"
                        class="hidden md:block bg-air-blue hover:bg-blue-600 text-white text-sm font-semibold px-5 py-2 rounded-full transition">
                        Jetzt starten
                    </button> -->
                    <Link v-if="props.canLogin && !$page.props.auth.user" :href="route('login')"
                        class="hidden lg:inline-block text-sm font-semibold text-air-blue hover:text-blue-400 transition">
                        {{ $t('Anmelden') }}
                    </Link>

                    <Link :href="route('dashboard')"><i class="las la-rocket"></i><span class="ml-2">  {{$t("Feed")}}</span></Link>
                    <!-- Burger nur Mobile -->
                    <button @click="toggleMobile" class="md:hidden text-gray-300 hover:text-white p-2">
                        <i class="las la-bars text-2xl"></i>
                    </button>
                </div>
            </div>
    </nav>


    <!-- MOBILE MENU -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0"
                enter-to-class="opacity-100" leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="mobileOpen" class="fixed inset-0 z-[99999] md:hidden">
                    <div class="absolute inset-0 bg-black/90 backdrop-blur-sm" @click="mobileOpen = false"></div>

                    <Transition enter-active-class="transition duration-300 ease-out"
                        enter-from-class="translate-x-full" enter-to-class="translate-x-0"
                        leave-active-class="transition duration-200 ease-in" leave-from-class="translate-x-0"
                        leave-to-class="translate-x-full">
                        <div v-if="mobileOpen"
                            class="absolute right-0 top-0 h-full w-full max-w- bg-air-dark border-l border-white/10 flex flex-col">
                            <div class="flex justify-between items-center p-5 border-b border-white/10">
                                <span class="text-white font-bold text-lg">Menü</span>
                                <button @click="mobileOpen = false" class="text-white text-2xl p-1">✕</button>
                            </div>

                            <div class="flex-1 overflow-y-auto px-6 py-6 flex flex-col gap-1">
                                <button @click="scrollTo('vorteile')"
                                    class="text-left py-3 text-lg text-gray-300 hover:text-white transition">Vorteile</button>
                                <button @click="scrollTo('funktionen')"
                                    class="text-left py-3 text-lg text-gray-300 hover:text-white transition">Funktionen</button>
                                <button @click="scrollTo('sportarten')"
                                    class="text-left py-3 text-lg text-gray-300 hover:text-white transition">Sportarten</button>
                                <button @click="scrollTo('ueber')"
                                    class="text-left py-3 text-lg text-gray-300 hover:text-white transition">Über
                                    uns</button>
                                <button @click="scrollTo('blog')"
                                    class="text-left py-3 text-lg text-gray-300 hover:text-white transition">Blog</button>
                                <button @click="scrollTo('kontakt')"
                                    class="text-left py-3 text-lg text-gray-300 hover:text-white transition">Kontakt</button>

                                <div class="border-t border-white/10 my-4"></div>

                                <Link v-if="props.canLogin" :href="route('login')" @click="mobileOpen = false"
                                    class="text-left py-3 text-lg text-air-blue hover:text-blue-400 transition">
                                    {{ $t('Anmelden') }}
                                </Link>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

</template>

<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue'

</script>



<template>
    <footer class="border-t border-white/5 py-12 px-4">
            <div class="max-w-6xl mx-auto">
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-10">
                    <div>
                        <button @click="scrollTo('hero')" class="font-heading font-900 text-xl tracking-tight">
                        <ApplicationLogo class="w-16 h-16" />
                            <span class="text-white font-[--ubuntu]">AIRMIUS</span>
                        </button>
                        <p class="text-sm text-gray-500 mt-3 leading-relaxed">Das soziale Netzwerk für deinen Sport. Für
                            Sportler, Teams und Vereine. Organisation, Kommunikation und Vernetzung – vereint in einer
                            App.</p>
                    </div>
                    <div>
                        <h4 class="font-heading font-600 text-sm mb-3 text-gray-300">Produkt</h4>
                        <div class="space-y-2 text-sm text-gray-500">
                            <button @click="scrollTo('funktionen')"
                                class="block hover:text-white transition">Funktionen</button>
                            <button @click="scrollTo('vorteile')"
                                class="block hover:text-white transition">Vorteile</button>
                            <button @click="scrollTo('sportarten')"
                                class="block hover:text-white transition">Sportarten</button>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-heading font-600 text-sm mb-3 text-gray-300">Unternehmen</h4>
                        <div class="space-y-2 text-sm text-gray-500">
                            <button @click="scrollTo('ueber')" class="block hover:text-white transition">Über
                                uns</button>
                            <button @click="scrollTo('blog')" class="block hover:text-white transition">Blog</button>
                            <button @click="scrollTo('kontakt')"
                                class="block hover:text-white transition">Kontakt</button>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-heading font-600 text-sm mb-3 text-gray-300">Rechtliches</h4>
                        <div class="space-y-2 text-sm text-gray-500">
                            <a href="#" class="block hover:text-white transition">Impressum</a>
                            <a href="#" class="block hover:text-white transition">Datenschutz</a>
                            <a href="#" class="block hover:text-white transition">AGB</a>
                        </div>
                    </div>
                </div>
                <div
                    class="border-t border-white/5 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600">
                    <span>© 2025 AIRMIUS. Alle Rechte vorbehalten.</span>
                    <span>Made with ❤️ in Germany</span>
                </div>
            </div>
        </footer>
</template>

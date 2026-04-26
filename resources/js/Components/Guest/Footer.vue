<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue'

const scrollTo = (id) => {
    const el = document.getElementById(id)
    if (!el) return

    const offset = 80
    const elementPosition = el.getBoundingClientRect().top + window.pageYOffset

    window.scrollTo({
        top: elementPosition - offset,
        behavior: 'smooth',
    })
}
</script>

<template>
    <footer class="border-t border-border py-12 px-4 bg-card/40">
        <div class="max-w-6xl mx-auto">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-10">
                <div>
                    <button @click="scrollTo('hero')" class="font-heading font-900 text-xl tracking-tight text-left">
                        <ApplicationLogo class="w-16 h-16" />
                        <span class="text-primary font-[--ubuntu]">AIRMIUS</span>
                    </button>
                    <p class="text-sm text-secondary mt-3 leading-relaxed">
                        Das soziale Netzwerk für deinen Sport. Für Sportler, Teams und Vereine.
                        Organisation, Kommunikation und Vernetzung in einer App.
                    </p>
                </div>

                <div>
                    <h4 class="font-heading font-600 text-sm mb-3 text-primary">Produkt</h4>
                    <div class="space-y-2 text-sm text-secondary">
                        <button @click="scrollTo('funktionen')" class="block hover:text-primary transition">Funktionen</button>
                        <button @click="scrollTo('vorteile')" class="block hover:text-primary transition">Vorteile</button>
                        <button @click="scrollTo('sportarten')" class="block hover:text-primary transition">Sportarten</button>
                    </div>
                </div>

                <div>
                    <h4 class="font-heading font-600 text-sm mb-3 text-primary">Unternehmen</h4>
                    <div class="space-y-2 text-sm text-secondary">
                        <button @click="scrollTo('ueber')" class="block hover:text-primary transition">Über uns</button>
                        <button @click="scrollTo('blog')" class="block hover:text-primary transition">Blog</button>
                        <button @click="scrollTo('kontakt')" class="block hover:text-primary transition">Kontakt</button>
                    </div>
                </div>

                <div>
                    <h4 class="font-heading font-600 text-sm mb-3 text-primary">Rechtliches</h4>
                    <div class="space-y-2 text-sm text-secondary">
                        <a href="#" class="block hover:text-primary transition">Impressum</a>
                        <a href="#" class="block hover:text-primary transition">Datenschutz</a>
                        <a href="#" class="block hover:text-primary transition">AGB</a>
                    </div>
                </div>
            </div>

            <div class="border-t border-border pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-secondary">
                <span>© 2026 AIRMIUS. Alle Rechte vorbehalten.</span>
                <span>Made with care in Germany</span>
            </div>
        </div>
    </footer>
</template>

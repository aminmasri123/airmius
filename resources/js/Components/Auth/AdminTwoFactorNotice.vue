<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const page = usePage()
const { locale } = useI18n()
const visible = computed(() => {
    const user = page.props.auth?.user
    return user && !user.two_factor_enabled && user.roles?.some(role => ['super_admin', 'admin', 'system_admin'].includes(role))
})
const translations = {
    de: ['Adminrolle aktiv – Sicherheitseinrichtung fehlt', 'Deine Adminrolle ist gespeichert. Adminfunktionen bleiben gesperrt, bis du die Zwei-Faktor-Authentifizierung eingerichtet und bestätigt hast.', 'Zwei-Faktor-Authentifizierung einrichten'],
    en: ['Admin role active – security setup required', 'Your admin role is saved. Admin features remain locked until you set up and confirm two-factor authentication.', 'Set up two-factor authentication'],
    fr: ['Rôle administrateur actif – sécurité à configurer', 'Votre rôle est enregistré. Les fonctions administrateur restent verrouillées jusqu’à la configuration et la confirmation de l’authentification à deux facteurs.', 'Configurer l’authentification à deux facteurs'],
    ar: ['دور المسؤول نشط – يلزم إعداد الأمان', 'تم حفظ دور المسؤول. تبقى ميزات الإدارة مقفلة حتى إعداد المصادقة الثنائية وتأكيدها.', 'إعداد المصادقة الثنائية'],
}
const copy = computed(() => translations[String(locale.value).split('-')[0]] || translations.de)
</script>

<template>
    <aside v-if="visible" class="mb-5 space-y-2 rounded-xl border border-border bg-card p-4 text-primary" role="note">
        <h2 class="font-semibold">{{ copy[0] }}</h2>
        <p class="text-sm text-secondary">{{ copy[1] }}</p>
        <Link :href="route('auth.settings', { tab: 'security' })" class="inline-block rounded-lg bg-buttonPrimary px-4 py-2 text-sm text-buttonTextPrimary">{{ copy[2] }}</Link>
    </aside>
</template>

<script setup>
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue'
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue'
import SectionBorder from '@/Components/SectionBorder.vue'
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue'
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue'

defineProps({
    confirmsTwoFactorAuthentication: { type: Boolean, default: false },
    sessions: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="surface-card space-y-6 p-5">
        <UpdatePasswordForm />

        <SectionBorder />

        <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />

        <SectionBorder />

        <LogoutOtherBrowserSessionsForm :sessions="sessions || []" />

        <SectionBorder />

        <DeleteUserForm />
    </div>
</template>

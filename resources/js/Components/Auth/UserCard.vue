<!-- Components/UserCard.vue -->
<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted, ref } from 'vue'

const page = usePage()
const user = page.props.auth.user

const open = ref(false)
const dropdownRef = ref(null)
const initials = (name) => (name || '?')
    .split(' ')
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()

const logout = () => {
    localStorage.removeItem('logout')
    localStorage.setItem('logout', Date.now())
    router.post(route('logout'))
}

const closeOnOutsideClick = (event) => {
    if (!open.value || !dropdownRef.value) return

    if (!dropdownRef.value.contains(event.target)) {
        open.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', closeOnOutsideClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', closeOnOutsideClick)
})
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <!-- Button -->
        <button
            @click="open = !open"
            class="flex items-center gap-3 rounded-xl p-2 hover:bg-muted transition"
        >
            <!-- Avatar -->
            <div>
                <img
                    v-if="user.profile_photo_thumb"
                    :src="user.profile_photo_thumb"
                    :alt="user.name"
                    class="h-10 w-10 rounded-full object-cover"
                />
                <div
                    v-else
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary"
                >
                    {{ initials(user.name) }}
                </div>
            </div>

            <!-- Name -->
            <div class="hidden text-start sm:block">
                <div class="text-sm font-semibold">{{ user.name }}</div>
                <div class="text-xs text-secondary">{{ user.roles?.[0] || $t('Member') }}</div>
            </div>

            <i class="las la-chevron-down text-lg text-secondary"></i>
        </button>

        <!-- Dropdown -->
        <div
            v-if="open"
            class="absolute end-0 mt-2 w-48 rounded-xl border border-border bg-card shadow-lg z-50"
        >
            <!-- <Link
                :href="route('profile.show')"
                class="block px-4 py-2 text-sm hover:bg-muted"
                @click="open = false"
            >
                {{ $t('Profile') }}
            </Link> -->
            <Link
                :href="route('auth.users.show', user.id)"
                class="block px-4 py-2 text-sm hover:bg-muted"
                @click="open = false"
            >
                {{ $t('Profile') }}
            </Link>

            <Link
                :href="route('auth.settings')"
                class="block px-4 py-2 text-sm hover:bg-muted"
                @click="open = false"
            >
                {{ $t('Settings') }}
            </Link>

            <button
                @click="logout"
                class="w-full px-4 py-2 text-start text-sm hover:bg-muted"
            >
                {{ $t('Logout') }}
            </button>
        </div>
    </div>
</template>

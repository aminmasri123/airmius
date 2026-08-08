<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    workspace: { type: Object, required: true },
    homeHref: { type: String, required: true },
})

const emit = defineEmits(['navigate'])
const page = usePage()
const { t } = useI18n()
const menu = ref(null)
const context = computed(() => page.props.workspaceContext || { current: null, clubs: [] })
const clubs = computed(() => context.value.clubs || [])
const currentClub = computed(() => context.value.current?.type === 'club' ? context.value.current : null)
const displayName = computed(() => currentClub.value?.name || (props.workspace.translated ? t(props.workspace.label) : props.workspace.label))
const canSwitch = computed(() => clubs.value.length > 0)

const close = () => {
    if (menu.value) menu.value.open = false
}

const selectClub = (club) => {
    if (currentClub.value?.id === club.id) {
        close()
        return
    }

    router.post(route('auth.workspaces.club.select', club.id), {}, {
        preserveScroll: true,
        only: ['workspaceContext', 'auth', 'flash'],
        onFinish: close,
    })
}

const selectPersonal = () => {
    if (!currentClub.value) {
        close()
        return
    }

    router.delete(route('auth.workspaces.club.clear'), {
        preserveScroll: true,
        only: ['workspaceContext', 'auth', 'flash'],
        onFinish: close,
    })
}
</script>

<template>
    <details v-if="canSwitch" ref="menu" class="group relative">
        <summary
            class="flex cursor-pointer list-none items-center gap-3 rounded-2xl border border-border bg-inputBg/70 p-3 transition hover:border-borderHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            :aria-label="t('shell.workspace.choose')"
        >
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-buttonPrimary text-buttonTextPrimary">
                <i :class="[currentClub ? 'las la-building' : workspace.icon, 'text-xl']"></i>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-secondary">{{ t('shell.workspace.label') }}</span>
                <span class="mt-0.5 block truncate text-sm font-bold text-primary">{{ displayName }}</span>
            </span>
            <i class="las la-angle-down text-secondary transition group-open:rotate-180"></i>
        </summary>

        <div class="absolute inset-x-0 top-full z-30 mt-2 overflow-hidden rounded-2xl border border-border bg-card p-2 shadow-2xl">
            <button
                type="button"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-start text-sm hover:bg-muted"
                :class="!currentClub ? 'font-bold text-primary' : 'text-secondary'"
                @click="selectPersonal"
            >
                <i class="las la-user-circle text-lg"></i>
                <span class="min-w-0 flex-1 truncate">{{ t('shell.workspace.personal') }}</span>
                <i v-if="!currentClub" class="las la-check"></i>
            </button>

            <button
                v-for="club in clubs"
                :key="club.id"
                type="button"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-start text-sm hover:bg-muted"
                :class="currentClub?.id === club.id ? 'font-bold text-primary' : 'text-secondary'"
                @click="selectClub(club)"
            >
                <i class="las la-building text-lg"></i>
                <span class="min-w-0 flex-1 truncate">{{ club.name }}</span>
                <i v-if="currentClub?.id === club.id" class="las la-check"></i>
            </button>

            <div class="my-1 border-t border-border"></div>
            <Link
                :href="route('auth.workspaces.index')"
                class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                @click="emit('navigate'); close()"
            >
                <i class="las la-layer-group text-lg"></i>
                <span>{{ t('shell.workspace.all') }}</span>
            </Link>
        </div>
    </details>

    <Link
        v-else
        :href="homeHref"
        class="flex items-center gap-3 rounded-2xl border border-border bg-inputBg/70 p-3 transition hover:border-borderHover"
        @click="emit('navigate')"
    >
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-buttonPrimary text-buttonTextPrimary">
            <i :class="[workspace.icon, 'text-xl']"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-secondary">{{ t('shell.workspace.label') }}</span>
            <span class="mt-0.5 block truncate text-sm font-bold text-primary">{{ displayName }}</span>
        </span>
        <i class="las la-angle-right text-secondary"></i>
    </Link>
</template>

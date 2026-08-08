<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ApplicationMark from '../ApplicationMark.vue'
import NavGroup from '@/Components/NavGroup.vue'
import NavItem from './NavItem.vue'
import WorkspaceSwitcher from './WorkspaceSwitcher.vue'
import { useAirmiusShellNavigation } from '@/composables/useAirmiusShellNavigation'

defineProps({ open: Boolean })
const emit = defineEmits(['close'])
const page = usePage()
const { groups, primarySpaces, roleHome, workspace } = useAirmiusShellNavigation()

const isRtl = computed(() => page.props.direction === 'rtl')
const storageUsage = computed(() => page.props.auth?.user?.storage_usage || null)
const formatStorage = (bytes) => {
    const value = Number(bytes || 0)
    if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`
    if (value < 1024 * 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`

    return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[55] bg-black/60 backdrop-blur-[2px] md:hidden" @click="emit('close')" />

    <aside
        class="fixed inset-y-0 top-0 z-[60] flex h-dvh w-[min(19rem,100vw)] flex-col border-border bg-card shadow-2xl transition-transform duration-200 md:w-72 md:translate-x-0 md:shadow-none"
        :class="[
            isRtl ? 'right-0 border-l' : 'left-0 border-r',
            open ? 'translate-x-0' : (isRtl ? 'translate-x-full' : '-translate-x-full')
        ]"
        :aria-label="$t('shell.navigation_label')"
    >
        <div class="flex min-h-16 items-center justify-between border-b border-border px-4">
            <Link :href="roleHome" class="block w-40 shrink-0" @click="emit('close')">
                <ApplicationMark />
            </Link>
            <button type="button" class="rounded-xl p-2 hover:bg-muted md:hidden" :aria-label="$t('shell.close')" @click="emit('close')">
                <i class="las la-times text-xl text-primary"></i>
            </button>
        </div>

        <div class="px-3 pt-3">
            <WorkspaceSwitcher :workspace="workspace" :home-href="roleHome" @navigate="emit('close')" />
        </div>

        <nav class="custom-scrollbar mt-3 flex-1 overflow-y-auto px-3 pb-5">
            <p class="px-3 pb-2 text-[10px] font-black uppercase tracking-[0.16em] text-secondary">
                {{ $t('shell.spaces.label') }}
            </p>
            <div class="space-y-1">
                <NavItem
                    v-for="space in primarySpaces"
                    :key="space.key"
                    :href="space.href"
                    :label="space.label"
                    :icon="space.icon"
                    :active-paths="space.activePaths"
                    @navigate="emit('close')"
                />
            </div>

            <div class="my-4 border-t border-border"></div>

            <p class="px-3 pb-1 text-[10px] font-black uppercase tracking-[0.16em] text-secondary">
                {{ $t('shell.tools_label') }}
            </p>
            <div class="space-y-1">
                <NavGroup
                    v-for="group in groups"
                    :key="group.key"
                    :label="group.label"
                    :icon="group.icon"
                    :initial-open="group.items.some((item) => item.active) || group.key === 'today'"
                >
                    <NavItem
                        v-for="item in group.items"
                        :key="`${group.key}-${item.key}`"
                        :href="item.href"
                        :label="item.label"
                        :icon="item.icon"
                        :badge="item.badge"
                        :active-paths="item.activePaths"
                        @navigate="emit('close')"
                    />
                </NavGroup>
            </div>

            <Link
                v-if="storageUsage"
                :href="route('auth.files.index')"
                class="mt-4 block rounded-2xl border border-border bg-inputBg/60 p-3"
                @click="emit('close')"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-secondary">{{ $t('shell.storage') }}</span>
                    <span class="text-xs font-bold text-primary">{{ storageUsage.used_percent }}%</span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${storageUsage.used_percent}%` }"></div>
                </div>
                <p class="mt-2 text-xs text-secondary">{{ formatStorage(storageUsage.remaining_bytes) }} {{ $t('shell.storage_free') }}</p>
            </Link>
        </nav>

        <div class="border-t border-border px-4 py-3">
            <div class="flex items-center gap-2 text-xs text-secondary">
                <span class="h-2 w-2 rounded-full bg-success"></span>
                <span>{{ $t('shell.privacy_active') }}</span>
                <i class="las la-lock ms-auto"></i>
            </div>
        </div>
    </aside>
</template>

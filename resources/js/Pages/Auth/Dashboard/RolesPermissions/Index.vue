<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { confirmDialog } from '@/services/dialogService'

const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
    permissionGroups: {
        type: Array,
        default: () => [],
    },
})

const { t } = useI18n()

const selectedRoleId = ref(props.roles[0]?.id || null)
const notice = ref(null)

const setNotice = (type, message) => {
    notice.value = { type, message }
}

const selectedRole = computed(() => {
    return props.roles.find((role) => role.id === selectedRoleId.value) || props.roles[0] || null
})

const allPermissionNames = computed(() => {
    return props.permissionGroups.flatMap((group) => group.items.map((permission) => permission.name))
})

const roleForm = useForm({
    description: selectedRole.value?.description || '',
    permissions: selectedRole.value?.permissions?.map((permission) => permission.name) || [],
})

const createRoleForm = useForm({
    name: '',
    description: '',
    permissions: [],
})

const createPermissionForm = useForm({
    name: '',
    description: '',
})

watch(selectedRole, (role) => {
    roleForm.description = role?.description || ''
    roleForm.permissions = role?.permissions?.map((permission) => permission.name) || []
})

const saveRole = () => {
    if (!selectedRole.value) return

    roleForm.put(route('roles.update', selectedRole.value.id), {
        preserveScroll: true,
        onSuccess: () => setNotice('success', 'Rolle wurde gespeichert.'),
        onError: () => setNotice('error', 'Rolle konnte nicht gespeichert werden. Bitte prüfe die Eingaben.'),
    })
}

const createRole = () => {
    createRoleForm.post(route('roles.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createRoleForm.reset()
            setNotice('success', 'Rolle wurde erstellt.')
        },
        onError: () => setNotice('error', 'Rolle konnte nicht erstellt werden. Bitte prüfe die Eingaben.'),
    })
}

const createPermission = () => {
    createPermissionForm.post(route('permissions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createPermissionForm.reset()
            setNotice('success', 'Berechtigung wurde erstellt.')
        },
        onError: () => setNotice('error', 'Berechtigung konnte nicht erstellt werden. Bitte prüfe die Eingaben.'),
    })
}

const deleteRole = async () => {
    if (!selectedRole.value || selectedRole.value.is_system) return

    const confirmed = await confirmDialog({
        title: 'Rolle löschen',
        message: t('roles.confirm_delete', { role: selectedRole.value.name }),
        confirmLabel: 'Löschen',
        danger: true,
    })

    if (!confirmed) return

    router.delete(route('roles.destroy', selectedRole.value.id), {
        preserveScroll: true,
        onSuccess: () => setNotice('success', 'Rolle wurde gelöscht.'),
        onError: () => setNotice('error', 'Rolle konnte nicht gelöscht werden.'),
    })
}

const toggleGroup = (group) => {
    const names = group.items.map((permission) => permission.name)
    const hasAll = names.every((name) => roleForm.permissions.includes(name))

    if (hasAll) {
        roleForm.permissions = roleForm.permissions.filter((name) => !names.includes(name))
        return
    }

    roleForm.permissions = [...new Set([...roleForm.permissions, ...names])]
}

const selectAll = () => {
    roleForm.permissions = [...allPermissionNames.value]
}

const clearAll = () => {
    roleForm.permissions = []
}

const isPermissionSelected = (permissionName) => {
    return roleForm.permissions.includes(permissionName)
}

const togglePermission = (permissionName) => {
    if (isPermissionSelected(permissionName)) {
        roleForm.permissions = roleForm.permissions.filter((name) => name !== permissionName)
        return
    }

    roleForm.permissions = [...roleForm.permissions, permissionName]
}

const selectedCountForGroup = (group) => {
    return group.items.filter((permission) => isPermissionSelected(permission.name)).length
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('roles.title')" />

        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold text-primary">{{ $t('roles.title') }}</h1>
                <p class="mt-2 text-sm text-secondary">
                    {{ $t('roles.subtitle') }}
                </p>
            </div>

            <div
                v-if="notice"
                class="rounded-lg border px-4 py-3 text-sm"
                :class="notice.type === 'success'
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-error/30 bg-error/10 text-error'"
            >
                {{ notice.message }}
            </div>

            <div class="grid gap-6 xl:grid-cols-[320px_1fr]">
                <section class="rounded-lg border border-border bg-card">
                    <div class="border-b border-border px-4 py-3">
                        <h2 class="text-sm font-semibold text-primary">{{ $t('roles.roles') }}</h2>
                    </div>

                    <div>
                        <button
                            v-for="role in roles"
                            :key="role.id"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 border-b border-border px-4 py-3 text-left transition hover:bg-muted"
                            :class="selectedRole?.id === role.id ? 'border-l-4 border-l-buttonPrimary bg-inputBg' : ''"
                            @click="selectedRoleId = role.id"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-primary">{{ role.name }}</span>
                                <span class="block truncate text-xs text-secondary">{{ role.description || $t('common.no_description') }}</span>
                            </span>
                            <span
                                class="shrink-0 rounded border px-2 py-1 text-xs"
                                :class="selectedRole?.id === role.id ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-secondary'"
                            >
                                {{ role.permissions.length }}
                            </span>
                        </button>
                    </div>
                </section>

                <section v-if="selectedRole" class="rounded-lg border border-border bg-card">
                    <div class="flex flex-col gap-3 border-b border-border px-4 py-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">{{ selectedRole.name }}</h2>
                            <p class="text-sm text-secondary">
                                {{ $t('roles.summary', { users: selectedRole.users_count, permissions: roleForm.permissions.length }) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded border border-border px-3 py-2 text-sm text-primary hover:bg-muted" @click="selectAll">
                                {{ $t('common.all') }}
                            </button>
                            <button type="button" class="rounded border border-border px-3 py-2 text-sm text-primary hover:bg-muted" @click="clearAll">
                                {{ $t('common.none') }}
                            </button>
                            <button
                                type="button"
                                class="rounded bg-buttonPrimary px-4 py-2 text-sm text-buttonTextPrimary hover:bg-primary/80 disabled:opacity-50"
                                :disabled="roleForm.processing"
                                @click="saveRole"
                            >
                                {{ $t('actions.save') }}
                            </button>
                            <button
                                v-if="!selectedRole.is_system"
                                type="button"
                                class="rounded bg-error px-4 py-2 text-sm text-white hover:bg-error/80"
                                @click="deleteRole"
                            >
                                {{ $t('actions.delete') }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-5 p-4">
                        <div>
                            <label class="block text-sm font-medium text-primary">{{ $t('common.description') }}</label>
                            <input
                                v-model="roleForm.description"
                                type="text"
                                class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-borderHover focus:ring-borderHover"
                            >
                            <div v-if="roleForm.errors.description" class="mt-1 text-sm text-error">{{ roleForm.errors.description }}</div>
                        </div>

                        <div class="rounded-lg border border-border bg-inputBg p-3">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-primary">{{ $t('roles.selected_permissions') }}</h3>
                                <span class="rounded bg-card px-2 py-1 text-xs text-secondary">{{ roleForm.permissions.length }}</span>
                            </div>

                            <div v-if="roleForm.permissions.length" class="mt-3 flex max-h-32 flex-wrap gap-2 overflow-y-auto custom-scrollbar">
                                <button
                                    v-for="permissionName in roleForm.permissions"
                                    :key="permissionName"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border border-buttonPrimary bg-buttonPrimary px-3 py-1 text-xs font-semibold text-buttonTextPrimary"
                                    @click="togglePermission(permissionName)"
                                    :title="`${permissionName} entfernen`"
                                >
                                    <span>{{ permissionName }}</span>
                                    <i class="las la-times"></i>
                                </button>
                            </div>
                            <p v-else class="mt-2 text-sm text-secondary">
                                {{ $t('roles.no_permissions_selected') }}
                            </p>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="group in permissionGroups"
                                :key="group.group"
                                class="rounded-lg border border-border"
                            >
                                <div class="flex items-center justify-between border-b border-border px-3 py-2">
                                    <div>
                                        <h3 class="text-sm font-semibold uppercase text-primary">{{ group.group }}</h3>
                                        <p class="text-xs text-secondary">
                                            {{ $t('roles.group_selected', { selected: selectedCountForGroup(group), total: group.items.length }) }}
                                        </p>
                                    </div>
                                    <button type="button" class="rounded border border-border px-3 py-1 text-xs text-secondary hover:bg-muted hover:text-primary" @click="toggleGroup(group)">
                                        {{ $t('actions.toggle') }}
                                    </button>
                                </div>

                                <div class="grid gap-2 p-3 md:grid-cols-2 xl:grid-cols-3">
                                    <button
                                        v-for="permission in group.items"
                                        :key="permission.id"
                                        type="button"
                                        class="flex min-h-16 items-start gap-3 rounded border px-3 py-3 text-left text-sm transition"
                                        :class="isPermissionSelected(permission.name)
                                            ? 'border-buttonPrimary bg-buttonPrimary/10 text-primary shadow-sm'
                                            : 'border-border bg-card text-primary hover:border-borderHover hover:bg-inputBg'"
                                        :aria-pressed="isPermissionSelected(permission.name)"
                                        @click="togglePermission(permission.name)"
                                    >
                                        <span
                                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border text-xs"
                                            :class="isPermissionSelected(permission.name)
                                                ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                                : 'border-border bg-inputBg text-transparent'"
                                        >
                                            <i class="las la-check"></i>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block break-words font-medium">{{ permission.name }}</span>
                                            <span v-if="permission.description" class="block text-xs text-secondary">
                                                {{ permission.description }}
                                            </span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <div v-if="roleForm.errors.permissions" class="text-sm text-error">{{ roleForm.errors.permissions }}</div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-lg border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold text-primary">{{ $t('roles.new_role') }}</h2>
                    <form class="mt-4 space-y-3" @submit.prevent="createRole">
                        <input
                            v-model="createRoleForm.name"
                            type="text"
                            placeholder="z.B. sport_director"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-borderHover focus:ring-borderHover"
                        >
                        <div v-if="createRoleForm.errors.name" class="text-sm text-error">{{ createRoleForm.errors.name }}</div>

                        <input
                            v-model="createRoleForm.description"
                            type="text"
                            :placeholder="$t('common.description')"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-borderHover focus:ring-borderHover"
                        >
                        <div v-if="createRoleForm.errors.description" class="text-sm text-error">{{ createRoleForm.errors.description }}</div>

                        <button type="submit" class="rounded bg-buttonPrimary px-4 py-2 text-sm text-buttonTextPrimary hover:bg-primary/80 disabled:opacity-50" :disabled="createRoleForm.processing">
                            {{ $t('roles.create_role') }}
                        </button>
                    </form>
                </section>

                <section class="rounded-lg border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold text-primary">{{ $t('roles.new_permission') }}</h2>
                    <form class="mt-4 space-y-3" @submit.prevent="createPermission">
                        <input
                            v-model="createPermissionForm.name"
                            type="text"
                            placeholder="z.B. reports.view"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-borderHover focus:ring-borderHover"
                        >
                        <div v-if="createPermissionForm.errors.name" class="text-sm text-error">{{ createPermissionForm.errors.name }}</div>

                        <input
                            v-model="createPermissionForm.description"
                            type="text"
                            :placeholder="$t('common.description')"
                            class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary focus:border-borderHover focus:ring-borderHover"
                        >
                        <div v-if="createPermissionForm.errors.description" class="text-sm text-error">{{ createPermissionForm.errors.description }}</div>

                        <button type="submit" class="rounded bg-buttonPrimary px-4 py-2 text-sm text-buttonTextPrimary hover:bg-primary/80 disabled:opacity-50" :disabled="createPermissionForm.processing">
                            {{ $t('roles.create_permission') }}
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

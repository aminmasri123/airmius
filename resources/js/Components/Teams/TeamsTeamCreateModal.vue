<script setup>
import Modal from '@/Components/Modal.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'

defineProps({
    errors: {
        type: Object,
        default: () => ({}),
    },
    selectedClub: {
        type: Object,
        default: null,
    },
    show: {
        type: Boolean,
        default: false,
    },
    sports: {
        type: Array,
        default: () => [],
    },
    teamFormFor: {
        type: Function,
        required: true,
    },
})

defineEmits(['close', 'create'])
</script>

<template>
    <Modal :show="show" @close="$emit('close')">
        <form v-if="selectedClub" class="space-y-4" @submit.prevent="$emit('create')">
            <h2 class="font-bold text-primary">
                {{ $t('teams_workspace.team_create.title') }}
            </h2>

            <input
                v-model="teamFormFor(selectedClub).name"
                class="w-full rounded border border-border bg-inputBg p-3 text-primary"
                :placeholder="$t('teams_workspace.team_create.name')"
            >

            <p v-if="errors.name" class="text-sm text-error">
                {{ errors.name }}
            </p>

            <SearchableSelect
                v-model="teamFormFor(selectedClub).sport_type"
                :options="sports"
                value-key="slug"
                translation-prefix="sports"
                category-translation-prefix="sport_categories"
                :placeholder="$t('teams_workspace.team_create.sport')"
            />

            <p v-if="errors.club_id" class="text-sm text-error">
                {{ errors.club_id }}
            </p>

            <p v-if="errors.sport_type" class="text-sm text-error">
                {{ errors.sport_type }}
            </p>

            <button
                type="submit"
                class="w-full rounded bg-buttonPrimary py-3 text-buttonTextPrimary"
            >
                {{ $t('teams_workspace.team_create.submit') }}
            </button>
        </form>
    </Modal>
</template>

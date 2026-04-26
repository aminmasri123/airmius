<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'

defineOptions({ layout: AppLayout })

defineProps({
    clubs: Array
})

const showClubModal = ref(false)
const clubForm = ref({
    name: ''
})

const createClub = () => {
    router.post('/clubs', clubForm.value)
    showClubModal.value = false
}
const showModal = ref(false)
const editing = ref(false)
const form = ref({
    id: null,
    name: ''
})
</script>

<template>

    <Head title="Teams" />

    <div class="space-y-6">

        <!-- Header -->

        <div class="flex justify-between mb-6">
            <h1 class="text-2xl font-bold">Meine Vereine & Teams</h1>

            <button @click="showClubModal = true" class="bg-buttonPrimary text-buttonTextPrimary px-4 py-2 rounded-lg hover:bg-buttonPrimaryHover transition">
                + Verein
            </button>
        </div>

        <!-- Club Header -->
        <div v-for="club in clubs" :key="club.id" class="mb-10">
            <div class="flex items-center middle mb-4 space-x-4">
                <div class="w-10 h-10 rounded-lg border border-border">
                    <img v-if="club.logo" :src="club.logo" alt="Club Logo"
                        class="w-full h-full object-cover rounded-lg" />
                    <div v-else class="w-full h-full flex items-center justify-center text-secondary bg-inputBg rounded-lg">
                        {{ club.name.charAt(0).toUpperCase() }}
                    </div>
                </div>
                <!-- Club Header -->
                <div class="">
                    <h2 class="text-xl font-semibold text-primary">
                        {{ club.name }} - Meine Teams
                    </h2>
                    <p class="text-sm text-secondary">
                        Du bist in {{ club.teams.length }} Team(s) aktiv
                    </p>
                </div>
            </div>
            <!-- Teams Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">

                <div v-for="team in club.teams" :key="team.id"
                    class="surface-card p-5 hover:border-borderHover transition duration-300 cursor-pointer">

                    <!-- Top Row -->
                    <div class="flex justify-between items-start mb-4">

                        <!-- Icon -->
                        <div class="w-10 h-10 rounded-lg border border-border">
                            <img v-if="team.logo_url" :src="team.logo_url" alt="Team Logo"
                                class="w-full h-full object-cover rounded-lg" />
                            <div v-else class="w-full h-full flex items-center justify-center text-secondary bg-inputBg rounded-lg">
                                {{ team.name.charAt(0).toUpperCase() }}
                            </div>
                        </div>

                        <!-- Status -->
                        <span class="text-xs px-3 py-1 rounded-full bg-borderHover text-primary">
                            AKTIV
                        </span>
                    </div>

                    <!-- Team Name -->
                    <h3 class="text-lg font-semibold text-primary mb-2">
                        {{ team.name }}
                    </h3>

                    <!-- Info -->
                    <p class="text-sm text-secondary">
                        Fußball · {{ team.members_count ?? 12 }} Mitglieder
                    </p>

                    <p class="text-sm text-secondary mt-1">
                        Trainer: {{ team.coach_name ?? 'Unbekannt' }}
                    </p>

                </div>

            </div>
        </div>

    </div>
    <Modal :show="showClubModal" @close="showClubModal = false" max-width="md" closeable>
        <div class="space-y-4 text-primary">
            <h2 class="text-lg font-bold">Verein erstellen</h2>

            <input v-model="clubForm.name" placeholder="Vereinsname" class="w-full border border-border bg-inputBg p-2 rounded-lg focus:border-borderHover focus:ring-borderHover" />

            <button @click="createClub" class="bg-buttonPrimary text-buttonTextPrimary w-full py-2 rounded-lg hover:bg-buttonPrimaryHover transition">
                Speichern
            </button>
        </div>
    </Modal>
</template>

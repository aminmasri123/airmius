<script setup>
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({
    clubProfile: { type: Object, required: true },
    initials: { type: Function, required: true },
    storageUrl: { type: Function, required: true },
    viewer: { type: Object, required: true },
})

const emit = defineEmits(['leave-club', 'upload-image'])
const coverInput = ref(null)
const logoInput = ref(null)
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-border bg-card">
        <div class="relative h-40 bg-gradient-to-r from-buttonPrimary to-borderHover">
            <img
                v-if="clubProfile.cover_image"
                :src="storageUrl(clubProfile.cover_image)"
                :alt="clubProfile.name"
                class="h-full w-full object-cover"
            />
            <button
                v-if="viewer.can_manage"
                type="button"
                class="absolute bottom-3 right-3 rounded-lg bg-card/90 px-3 py-2 text-sm font-semibold text-primary shadow hover:bg-card"
                @click="coverInput?.click()"
            >
                <i class="las la-camera"></i> Titelbild
            </button>
            <input
                ref="coverInput"
                type="file"
                accept="image/*"
                class="hidden"
                @change="emit('upload-image', 'cover_image', $event)"
            />
        </div>
        <div class="px-5 pb-5">
            <div class="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex items-end gap-4">
                    <div class="group relative flex h-24 w-24 items-center justify-center overflow-hidden rounded-lg border-4 border-card bg-inputBg text-3xl font-bold text-primary">
                        <img
                            v-if="clubProfile.logo"
                            :src="storageUrl(clubProfile.logo)"
                            :alt="clubProfile.name"
                            class="h-full w-full object-cover"
                        />
                        <span v-else>{{ initials(clubProfile.name) }}</span>
                        <button
                            v-if="viewer.can_manage"
                            type="button"
                            class="absolute inset-0 flex items-center justify-center bg-black/50 text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100"
                            @click="logoInput?.click()"
                        >
                            <i class="las la-camera text-xl"></i>
                        </button>
                        <input
                            ref="logoInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="emit('upload-image', 'logo', $event)"
                        />
                    </div>
                    <div class="pb-1">
                        <h1 class="text-2xl font-bold text-primary">{{ clubProfile.name }}</h1>
                        <p class="text-sm text-secondary">Verein · {{ viewer.is_member ? 'Mitglied' : 'Profil' }}</p>
                    </div>
                </div>

                <Link :href="route('auth.teams.index')" class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:bg-inputBg">
                    Teams ansehen
                </Link>
                <button
                    v-if="viewer.is_member && !viewer.can_manage"
                    type="button"
                    class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                    @click="$emit('leave-club')"
                >
                    Verein verlassen
                </button>
            </div>
        </div>
    </section>
</template>


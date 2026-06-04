<script setup>
import SearchableSelect from '@/Components/SearchableSelect.vue'

defineProps({
    profileForm: { type: Object, required: true },
    profileFeedback: { type: Object, default: null },
    sports: { type: Array, default: () => [] },
    updateProfile: { type: Function, required: true },
})
</script>

<template>
    <form id="style-profile" class="rounded-lg border border-border bg-card p-5 shadow-sm" @submit.prevent="updateProfile">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-primary">Style-Profil</h2>
                <p class="mt-1 text-sm text-secondary">Diese Angaben steuern die Zusammenstellung deiner Boxen.</p>
            </div>
            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="profileForm.processing">
                Speichern
            </button>
        </div>

        <div
            v-if="profileFeedback"
            class="mt-4 rounded-lg border px-4 py-3 text-sm font-semibold"
            :class="profileFeedback.type === 'success'
                ? 'border-success/30 bg-success/10 text-success'
                : 'border-error/30 bg-error/10 text-error'"
            role="status"
        >
            {{ profileFeedback.message }}
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-sm font-semibold text-primary">Sportfokus</span>
                <SearchableSelect
                    v-model="profileForm.sport_focus"
                    :options="sports"
                    value-key="slug"
                    translation-prefix="sports"
                    category-translation-prefix="sport_categories"
                    class="mt-1"
                    placeholder="Sportart suchen"
                />
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-primary">Passform</span>
                <select v-model="profileForm.fit_preference" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                    <option value="slim">Slim</option>
                    <option value="regular">Regular</option>
                    <option value="relaxed">Locker</option>
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-primary">Grössen</span>
                <input v-model="profileForm.sizes_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="M, L, 42" />
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-primary">Lieblingsfarben</span>
                <input v-model="profileForm.colors_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Schwarz, Blau, Weiß" />
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-primary">Ausschlussfarben</span>
                <input v-model="profileForm.excluded_colors_text" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Gelb, Pink" />
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-primary">Stil</span>
                <select v-model="profileForm.brand_style" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                    <option value="minimal">Minimal</option>
                    <option value="bold">Auffällig</option>
                    <option value="classic">Klassisch</option>
                    <option value="team">Team-orientiert</option>
                </select>
            </label>
            <label class="block sm:col-span-2">
                <span class="text-sm font-semibold text-primary">Notizen</span>
                <textarea v-model="profileForm.notes" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Materialwünsche, Marken, No-Gos, besondere Hinweise"></textarea>
            </label>
        </div>
    </form>
</template>


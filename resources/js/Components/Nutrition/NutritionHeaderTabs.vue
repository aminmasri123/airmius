<script setup>
defineProps({
    activeSection: { type: String, required: true },
    nutritionSections: { type: Array, default: () => [] },
    selectedDateValue: { type: String, required: true },
    tAuto: { type: Function, required: true },
})

defineEmits(['change-date', 'set-active-section', 'update:selectedDateValue'])
</script>

<template>
    <div class="space-y-4">
        <section class="rounded-2xl border border-border bg-card p-3 sm:p-4 lg:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Airmius Fuel') }}</p>
                    <p class="mt-1 hidden max-w-2xl text-sm leading-6 text-secondary sm:block">
                        {{ tAuto('Heute sehen, schnell erfassen, Ziele ruhig anpassen. Kein überladener Tagesflow mehr.') }}
                    </p>
                </div>
                <div class="flex w-full gap-2 sm:w-auto">
                    <input
                        :value="selectedDateValue"
                        type="date"
                        class="min-w-0 flex-1 rounded-xl border border-border bg-inputBg px-3 py-2 text-sm text-primary sm:w-44"
                        @change="$emit('update:selectedDateValue', $event.target.value); $emit('change-date')"
                    >
                    <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary" @click="$emit('change-date')">
                        {{ tAuto('Laden') }}
                    </button>
                </div>
            </div>
        </section>

        <nav class="custom-scrollbar -mx-1 flex gap-2 overflow-x-auto px-1 pb-1 sm:mx-0 sm:grid sm:grid-cols-5 sm:overflow-visible sm:px-0 sm:pb-0">
            <button
                v-for="section in nutritionSections"
                :key="section.key"
                type="button"
                :class="[
                    'flex min-w-[88px] flex-col items-center justify-center gap-1 rounded-2xl border px-3 py-3 text-center transition sm:min-w-0 sm:flex-row sm:justify-start sm:gap-3 sm:rounded-xl sm:text-start',
                    activeSection === section.key
                        ? 'border-air-blue bg-air-blue/15 text-primary shadow-lg shadow-air-blue/10'
                        : 'border-transparent text-secondary hover:border-border hover:bg-inputBg'
                ]"
                @click="$emit('set-active-section', section.key)"
            >
                <i :class="[section.icon, 'text-xl sm:text-xl']"></i>
                <span class="min-w-0">
                    <span class="block text-xs font-bold sm:text-sm">{{ section.label }}</span>
                    <span class="hidden truncate text-xs opacity-80 sm:block">{{ section.hint }}</span>
                </span>
            </button>
        </nav>
    </div>
</template>


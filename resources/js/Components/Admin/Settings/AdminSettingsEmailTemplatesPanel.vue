<script setup>
import { computed } from 'vue'

const props = defineProps({
    activeEmailKey: { type: String, default: null },
    emailTemplates: { type: Array, default: () => [] },
    form: { type: Object, required: true },
    placeholderFor: { type: Function, required: true },
})

const emit = defineEmits(['update:activeEmailKey'])

const activeTemplate = computed(() => props.emailTemplates.find((template) => template.key === props.activeEmailKey) || props.emailTemplates[0])
</script>

<template>
    <div class="rounded-lg border border-border bg-bg p-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">E-Mail-Vorlagen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Bearbeite Betreff, Anrede, Inhalt und Buttontexte aller System-E-Mails.
                </p>
            </div>
            <span class="rounded-full bg-air-blue/15 px-3 py-1 text-xs font-semibold text-air-blue">
                {{ emailTemplates.length }} Vorlagen
            </span>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-[18rem_1fr]">
            <div class="custom-scrollbar max-h-[34rem] space-y-2 overflow-y-auto pr-1">
                <button
                    v-for="template in emailTemplates"
                    :key="template.key"
                    type="button"
                    class="w-full rounded-lg border px-3 py-2 text-left transition"
                    :class="activeTemplate?.key === template.key ? 'border-buttonPrimary bg-buttonPrimary/10 text-primary' : 'border-border bg-card text-secondary hover:border-borderHover'"
                    @click="emit('update:activeEmailKey', template.key)"
                >
                    <span class="block text-sm font-semibold">{{ template.label }}</span>
                    <span class="mt-1 block text-xs leading-5">{{ template.description }}</span>
                </button>
            </div>

            <div v-if="activeTemplate && form.email_templates[activeTemplate.key]" class="space-y-4 rounded-lg border border-border bg-card p-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Aktive Vorlage</p>
                    <h3 class="mt-1 text-lg font-semibold text-primary">{{ activeTemplate.label }}</h3>
                    <p class="mt-1 text-sm text-secondary">{{ activeTemplate.description }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-primary">Betreff</label>
                        <input
                            v-model="form.email_templates[activeTemplate.key].subject"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                        >
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Anrede</label>
                        <input
                            v-model="form.email_templates[activeTemplate.key].greeting"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                        >
                    </div>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">{{ $t('Inhalt') }}</label>
                    <textarea
                        v-model="form.email_templates[activeTemplate.key].body"
                        rows="10"
                        class="mt-1 block w-full rounded-lg border-border bg-inputBg font-mono text-sm text-primary"
                    />
                    <p class="mt-1 text-xs text-secondary">
                        Jede neue Zeile wird als eigener Absatz in der E-Mail ausgegeben.
                    </p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-primary">Buttontext</label>
                    <input
                        v-model="form.email_templates[activeTemplate.key].action_label"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                        placeholder="Leer lassen, wenn diese E-Mail keinen Button hat"
                    >
                </div>

                <div class="rounded-lg border border-border bg-bg p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Platzhalter</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="variable in activeTemplate.variables"
                            :key="variable"
                            class="rounded-lg border border-border bg-card px-2 py-1 font-mono text-xs text-primary"
                        >
                            {{ placeholderFor(variable) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

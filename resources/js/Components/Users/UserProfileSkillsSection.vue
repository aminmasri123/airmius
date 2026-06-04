<script setup>
defineProps({
    profileUser: { type: Object, required: true },
    viewer: { type: Object, required: true },
    groupedSkills: { type: Object, default: () => ({}) },
    sportLabel: { type: Function, required: true },
    levelLabel: { type: Function, required: true },
    relationshipLabel: { type: Function, required: true },
    skillFormFor: { type: Function, required: true },
})

const emit = defineEmits(['endorse-skill', 'update-skill'])
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <div>
            <h2 class="text-lg font-bold text-primary">Skills & Bestätigungen</h2>
            <p class="mt-1 text-sm text-secondary">Skills entstehen aus den gewählten Sportarten und können bestätigt werden.</p>
        </div>

        <div class="mt-5 space-y-5">
            <article
                v-for="group in groupedSkills"
                :key="group.sport?.id || 'other'"
                class="rounded-xl border border-border bg-bg p-4"
            >
                <h3 class="font-semibold text-primary">{{ sportLabel(group.sport) }}</h3>

                <div class="mt-4 grid gap-3">
                    <div
                        v-for="skill in group.skills"
                        :key="skill.id"
                        class="rounded-xl border border-border bg-card p-4"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h4 class="font-semibold text-primary">{{ skill.skill.name }}</h4>
                                <p class="mt-1 text-sm text-secondary">{{ skill.skill.description }}</p>
                                <p class="mt-2 text-xs font-semibold text-secondary">
                                    Eigenes Level: {{ levelLabel(skill.self_level) }} - {{ skill.endorsements_count }} Bestätigungen
                                </p>
                            </div>

                            <button
                                v-if="!viewer.is_self"
                                type="button"
                                class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-inputBg disabled:opacity-60"
                                :disabled="skill.viewer_has_endorsed"
                                @click="emit('endorse-skill', skill)"
                            >
                                {{ skill.viewer_has_endorsed ? 'Bestätigt' : 'Bestätigen' }}
                            </button>
                        </div>

                        <form v-if="viewer.is_self" class="mt-3 grid gap-2 sm:grid-cols-[180px_1fr_auto]" @submit.prevent="emit('update-skill', skill)">
                            <select v-model="skillFormFor(skill).self_level" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="learning">Lerne ich</option>
                                <option value="developing">In Entwicklung</option>
                                <option value="solid">Solide</option>
                                <option value="strong">Stark</option>
                                <option value="expert">Experte</option>
                            </select>
                            <input
                                v-model="skillFormFor(skill).notes"
                                class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                placeholder="Kurze Notiz, optional"
                            >
                            <button class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
                        </form>

                        <form v-else-if="!skill.viewer_has_endorsed" class="mt-3 grid gap-2 sm:grid-cols-[150px_150px_1fr]" @submit.prevent="emit('endorse-skill', skill)">
                            <select v-model="skillFormFor(skill).relationship" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="visitor">Besucher</option>
                                <option value="friend">Freund</option>
                                <option value="team_member">Teamkollege</option>
                                <option value="trainer">Trainer</option>
                                <option value="club_admin">Verein</option>
                            </select>
                            <select v-model="skillFormFor(skill).level" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                                <option value="confirmed">Kann ich bestätigen</option>
                                <option value="good">Gut</option>
                                <option value="strong">Stark</option>
                                <option value="exceptional">Außergewöhnlich</option>
                            </select>
                            <input
                                v-model="skillFormFor(skill).comment"
                                class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                                placeholder="Kommentar, optional"
                            >
                        </form>

                        <div v-if="skill.endorsements.length" class="mt-3 flex flex-wrap gap-2">
                            <span v-for="endorsement in skill.endorsements" :key="endorsement.id" class="rounded-full bg-inputBg px-3 py-1 text-xs text-secondary">
                                {{ endorsement.endorser.name }} - {{ relationshipLabel(endorsement.relationship) }}
                            </span>
                        </div>
                    </div>
                </div>
            </article>

            <p v-if="!profileUser.sport_skills.length" class="rounded-xl border border-dashed border-border bg-bg p-4 text-sm text-secondary">
                Sobald Sportarten hinzugefügt werden, erscheinen hier passende Skills.
            </p>
        </div>
    </section>
</template>


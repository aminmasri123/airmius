<script setup>
defineProps({
    applyRecipe: { type: Function, required: true },
    applyTrainingSuggestion: { type: Function, required: true },
    filteredRecipes: { type: Array, default: () => [] },
    recipeFilter: { type: String, default: 'all' },
    tAuto: { type: Function, required: true },
    trainingSuggestions: { type: Array, default: () => [] },
})

defineEmits(['update:recipeFilter'])
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-[minmax(0,0.85fr),minmax(320px,0.55fr)]">
        <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Rezepte') }}</p>
                    <h2 class="mt-1 text-xl font-bold text-primary">{{ tAuto('Schnell übernehmen') }}</h2>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" :class="['rounded-lg border px-3 py-2 text-xs font-bold', recipeFilter === 'all' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="$emit('update:recipeFilter', 'all')">
                        {{ tAuto('Alle') }}
                    </button>
                    <button type="button" :class="['rounded-lg border px-3 py-2 text-xs font-bold', recipeFilter === 'goal' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="$emit('update:recipeFilter', 'goal')">
                        {{ tAuto('Ziel') }}
                    </button>
                    <button type="button" :class="['rounded-lg border px-3 py-2 text-xs font-bold', recipeFilter === 'style' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="$emit('update:recipeFilter', 'style')">
                        {{ tAuto('Stil') }}
                    </button>
                </div>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <article v-for="recipe in filteredRecipes" :key="recipe.key" class="rounded-xl border border-border bg-inputBg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-secondary">{{ tAuto(recipe.category) }} - {{ recipe.prep_minutes }} {{ tAuto('min') }}</p>
                            <h3 class="mt-1 font-bold text-primary">{{ tAuto(recipe.title) }}</h3>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-primary hover:bg-muted" @click="applyRecipe(recipe)">
                            {{ tAuto('Nutzen') }}
                        </button>
                    </div>
                    <div class="mt-3 grid grid-cols-4 gap-2 text-center text-[11px] text-secondary">
                        <span class="rounded-lg bg-card p-2"><b class="block text-primary">{{ recipe.calories }}</b>kcal</span>
                        <span class="rounded-lg bg-card p-2"><b class="block text-primary">{{ recipe.protein_g }}g</b>P</span>
                        <span class="rounded-lg bg-card p-2"><b class="block text-primary">{{ recipe.carbs_g }}g</b>C</span>
                        <span class="rounded-lg bg-card p-2"><b class="block text-primary">{{ recipe.fat_g }}g</b>F</span>
                    </div>
                </article>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
            <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Training-Bezug') }}</p>
            <div class="mt-3 space-y-2">
                <article v-for="suggestion in trainingSuggestions" :key="suggestion.title + suggestion.body" class="rounded-xl border border-border bg-inputBg p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-primary">{{ tAuto(suggestion.title) }}</p>
                            <p class="mt-1 text-sm leading-6 text-secondary">{{ tAuto(suggestion.body) }}</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-primary hover:bg-muted" @click="applyTrainingSuggestion(suggestion)">
                            {{ tAuto('Nutzen') }}
                        </button>
                    </div>
                </article>
            </div>
        </section>
    </section>
</template>


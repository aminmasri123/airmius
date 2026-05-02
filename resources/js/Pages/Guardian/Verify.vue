<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'

defineProps({
    email: {
        type: String,
        default: '',
    },
})

const page = usePage()
const form = useForm({
    code: '',
})

const submit = () => {
    form.post(route('guardian-access.confirm'))
}
</script>

<template>
    <Head title="Eltern-Code bestätigen" />

    <AuthenticationCard>
        <div class="mx-auto h-36 w-36 md:h-48 md:w-48">
            <AuthenticationCardLogo />
        </div>

        <div class="rounded-lg border border-border bg-card p-6">
            <h1 class="text-xl font-semibold text-primary">Code bestätigen</h1>
            <p class="mt-2 text-sm leading-6 text-secondary">
                Wir haben einen 6-stelligen Code an
                <span class="font-semibold text-primary">{{ email || 'deine E-Mail' }}</span>
                gesendet, wenn diese Adresse bei uns gespeichert ist.
            </p>

            <div v-if="page.props.flash?.status || page.props.status" class="mt-4 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-success">
                {{ page.props.flash?.status || page.props.status }}
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="code" value="Code" />
                    <TextInput
                        id="code"
                        v-model="form.code"
                        inputmode="numeric"
                        maxlength="6"
                        class="mt-1 block w-full text-center text-2xl tracking-widest"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.code" />
                </div>

                <PrimaryButton :disabled="form.processing" :class="{ 'opacity-60': form.processing }">
                    Zugang öffnen
                </PrimaryButton>
            </form>

            <div class="mt-5 text-sm text-secondary">
                <Link :href="route('guardian-access.create')" class="underline hover:text-primary">Andere E-Mail verwenden</Link>
            </div>
        </div>
    </AuthenticationCard>
</template>

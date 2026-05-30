<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    user: Object,
});

const { t } = useI18n();

const initials = (name) => (name || '?')
    .split(' ')
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()
const form = useForm({
    _method: 'PUT',
    first_name: props.user.first_name || (props.user.name || '').split(' ')[0] || '',
    last_name: props.user.last_name || (props.user.name || '').split(' ').slice(1).join(' ') || '',
    email: props.user.email,
    athlete_license_number: props.user.athlete_license_number || '',
    bio: props.user.bio || '',
    profile_visibility: props.user.profile_visibility || 'public',
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);
const notice = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    notice.value = null;

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            clearPhotoFileInput();
            notice.value = {
                type: 'success',
                message: t('settings.profile.saved'),
            };
        },
        onError: () => {
            notice.value = {
                type: 'error',
                message: t('settings.profile.save_failed'),
            };
        },
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (!photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <FormSection @submitted="updateProfileInformation">
        <template #title>
            {{ t('settings.profile.title') }}
        </template>

        <template #description>
            {{ t('settings.profile.description') }}
        </template>

        <template #form>
            <div
                v-if="notice"
                class="col-span-6 rounded-lg border px-4 py-3 text-sm"
                :class="notice.type === 'success'
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-error/30 bg-error/10 text-error'"
            >
                {{ notice.message }}
            </div>

            <!-- Profile Photo -->
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="col-span-6">
                <!-- Profile Photo File Input -->
                <input id="photo" ref="photoInput" type="file" class="hidden" @change="updatePhotoPreview">

                <InputLabel for="photo" :value="t('settings.profile.photo')" />

                <!-- Current Profile Photo -->
                <div v-show="!photoPreview" class="mt-2">
                    <img v-if="user.profile_photo_url !== null && user.profile_photo_url !== ''"
                        :src="user.profile_photo_url" class="h-16 w-16 rounded-full object-cover" />
                    <div v-else
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                        {{ initials(user.name) }}
                    </div>
                </div>

                <!-- New Profile Photo Preview -->
                <div v-show="photoPreview" class="mt-2">
                    <span class="block rounded-full size-20 bg-cover bg-no-repeat bg-center"
                        :style="'background-image: url(\'' + photoPreview + '\');'" />
                </div>

                <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewPhoto">
                    {{ t('settings.profile.select_new_photo') }}
                </SecondaryButton>

                <SecondaryButton v-if="user.profile_photo_path" type="button" class="mt-2" @click.prevent="deletePhoto">
                    {{ t('settings.profile.remove_photo') }}
                </SecondaryButton>

                <InputError :message="form.errors.photo" class="mt-2" />
            </div>

            <!-- Name -->
            <div class="col-span-6 sm:col-span-3">
                <InputLabel for="first_name" :value="t('settings.profile.first_name')" />
                <TextInput id="first_name" v-model="form.first_name" type="text" class="mt-1 block w-full" required
                    autocomplete="given-name" />
                <InputError :message="form.errors.first_name" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <InputLabel for="last_name" :value="t('settings.profile.last_name')" />
                <TextInput id="last_name" v-model="form.last_name" type="text" class="mt-1 block w-full" required
                    autocomplete="family-name" />
                <InputError :message="form.errors.last_name" class="mt-2" />
            </div>

            <!-- Email -->
            <div class="col-span-6">
                <InputLabel for="email" :value="t('settings.profile.email')" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required
                    autocomplete="username" />
                <InputError :message="form.errors.email" class="mt-2" />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                    <p class="text-sm mt-2">
                        {{ t('settings.profile.email_unverified') }}

                        <Link :href="route('verification.send')" method="post" as="button"
                            class="underline text-sm text-secondary hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            @click.prevent="sendEmailVerification">
                            {{ t('settings.profile.resend_verification') }}
                        </Link>
                    </p>

                    <div v-show="verificationLinkSent" class="mt-2 font-medium text-sm text-green-600">
                        {{ t('settings.profile.verification_sent') }}
                    </div>
                </div>
            </div>

            <div class="col-span-6">
                <InputLabel for="profile_visibility" :value="t('settings.profile.profile_visibility')" />
                <select id="profile_visibility" v-model="form.profile_visibility"
                    class="mt-1 block w-full rounded-md border-border bg-inputBg text-primary shadow-sm focus:border-borderHover focus:ring-borderHover">
                    <option value="public">{{ t('settings.profile.visibility_public') }}</option>
                    <option value="private">{{ t('settings.profile.visibility_private') }}</option>
                </select>
                <InputError :message="form.errors.profile_visibility" class="mt-2" />
            </div>

            <div class="col-span-6">
                <InputLabel for="athlete_license_number" :value="t('settings.profile.license_number')" />
                <TextInput
                    id="athlete_license_number"
                    v-model="form.athlete_license_number"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="off"
                    :placeholder="t('settings.profile.license_placeholder')"
                />
                <p class="mt-2 text-sm text-secondary">
                    {{ t('settings.profile.license_help') }}
                </p>
                <InputError :message="form.errors.athlete_license_number" class="mt-2" />
            </div>

            <div class="col-span-6">
                <InputLabel for="bio" :value="t('settings.profile.bio')" />
                <textarea id="bio" v-model="form.bio" rows="4"
                    class="mt-1 block w-full rounded-md border-border bg-inputBg text-primary shadow-sm focus:border-borderHover focus:ring-borderHover" />
                <InputError :message="form.errors.bio" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                {{ t('settings.actions.saved') }}
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ t('settings.actions.save') }}
            </PrimaryButton>
        </template>
    </FormSection>
</template>

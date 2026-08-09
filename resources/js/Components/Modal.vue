<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppButton from './UI/AppButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
    ariaLabel: {
        type: String,
        default: '',
    },
    ariaLabelledby: {
        type: String,
        default: '',
    },
    ariaDescribedby: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);
const { locale } = useI18n({ useScope: 'global' });
const dialog = ref();
const showSlot = ref(props.show);
let closeTimer = null;
let previouslyFocused = null;
let previousBodyOverflow = '';
let bodyScrollLocked = false;

const dialogCopy = {
    de: { label: 'Dialog', close: 'Dialog schließen' },
    en: { label: 'Dialog', close: 'Close dialog' },
    fr: { label: 'Dialogue', close: 'Fermer le dialogue' },
    ar: { label: 'مربع حوار', close: 'إغلاق مربع الحوار' },
};

const activeCopy = computed(() => {
    const language = String(locale.value || 'de').toLowerCase().split('-')[0];

    return dialogCopy[language] || dialogCopy.de;
});

const resolvedAriaLabel = computed(() => {
    if (props.ariaLabelledby) {
        return undefined;
    }

    return props.ariaLabel || activeCopy.value.label;
});

const lockBodyScroll = () => {
    if (bodyScrollLocked) {
        return;
    }

    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    bodyScrollLocked = true;
};

const unlockBodyScroll = () => {
    if (! bodyScrollLocked) {
        return;
    }

    document.body.style.overflow = previousBodyOverflow;
    bodyScrollLocked = false;
};

const restoreFocus = () => {
    const focusTarget = previouslyFocused;
    previouslyFocused = null;

    if (focusTarget instanceof HTMLElement && focusTarget.isConnected) {
        window.requestAnimationFrame(() => focusTarget.focus());
    }
};

const openDialog = () => {
    if (! dialog.value) {
        return;
    }

    if (! dialog.value.open) {
        previouslyFocused = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
    }

    if (typeof dialog.value.showModal === 'function') {
        if (! dialog.value.open) {
            dialog.value.showModal();
        }

        return;
    }

    dialog.value.setAttribute('open', 'open');
};

const closeDialog = () => {
    if (! dialog.value) {
        return;
    }

    if (typeof dialog.value.close === 'function' && dialog.value.open) {
        dialog.value.close();

        return;
    }

    dialog.value.removeAttribute('open');
    restoreFocus();
};

watch(() => props.show, async (show) => {
    if (closeTimer) {
        window.clearTimeout(closeTimer);
        closeTimer = null;
    }

    if (show) {
        lockBodyScroll();
        showSlot.value = true;
        await nextTick();
        openDialog();
    } else {
        unlockBodyScroll();
        closeTimer = window.setTimeout(() => {
            closeDialog();
            showSlot.value = false;
            closeTimer = null;
        }, 200);
    }
});

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const handleCancel = (event) => {
    event.preventDefault();

    if (props.show) {
        close();
    }
};

onMounted(async () => {
    if (! props.show) {
        return;
    }

    lockBodyScroll();
    showSlot.value = true;
    await nextTick();
    openDialog();
});

onUnmounted(() => {
    if (closeTimer) {
        window.clearTimeout(closeTimer);
    }

    unlockBodyScroll();
    restoreFocus();
});

const maxWidthClass = computed(() => {
    return {
        'sm': 'sm:max-w-sm',
        'md': 'sm:max-w-md',
        'lg': 'sm:max-w-lg',
        'xl': 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
</script>

<template>
    <dialog
        ref="dialog"
        class="z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent"
        :aria-label="resolvedAriaLabel"
        :aria-labelledby="ariaLabelledby || undefined"
        :aria-describedby="ariaDescribedby || undefined"
        @cancel="handleCancel"
        @close="restoreFocus"
    >
        <div class="fixed inset-0 z-50 flex items-center justify-center px-3 py-4 sm:px-4 sm:py-6" scroll-region>
            <transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0"
                enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-show="show" class="fixed inset-0 transform transition-all" @click.self="close">
                    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" />
                </div>
            </transition>

            <transition enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100" leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                <div v-show="show"
                    class="surface-card relative max-h-[calc(100dvh-2rem)] w-[calc(100vw-1.5rem)] overflow-hidden p-3 transform transition-all sm:mx-auto sm:w-full sm:p-4"
                    :class="maxWidthClass">
                    <AppButton
                        v-if="closeable"
                        type="button"
                        variant="ghost"
                        size="sm"
                        icon-only
                        class="absolute end-3 top-3 z-10"
                        :aria-label="activeCopy.close"
                        :title="activeCopy.close"
                        @click="close"
                    >
                        <i class="las la-times text-lg" aria-hidden="true"></i>
                    </AppButton>

                    <slot v-if="showSlot" />
                </div>
            </transition>
        </div>
    </dialog>
</template>

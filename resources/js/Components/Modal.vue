<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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
});

const emit = defineEmits(['close']);
const dialog = ref();
const showSlot = ref(props.show);

const openDialog = () => {
    if (! dialog.value) {
        return;
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
};

watch(() => props.show, () => {
    if (props.show) {
        document.body.style.overflow = 'hidden';
        showSlot.value = true;
        openDialog();
    } else {
        document.body.style.overflow = null;
        setTimeout(() => {
            closeDialog();
            showSlot.value = false;
        }, 200);
    }
});

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();

        if (props.show) {
            close();
        }
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = null;
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
    <dialog class="z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent " ref="dialog">
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
                        class="absolute right-3 top-3 z-10"
                        aria-label="Dialog schliessen"
                        title="Dialog schliessen"
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

<template>
    <button type="button" @click="goBack" class="absolute top-4 left-4 md:top-16 md:left-24 text-primary text-sm">
        <i class="las la-chevron-circle-left la-lg"></i>
    </button>

        <div class="min-h-screen flex flex-col sm:justify-center items-center px-4 pt-6 sm:pt-0 bg-bg text-primary">

            <div>
                <slot name="logo" />
            </div>

            <div class="surface-card w-full sm:max-w-md mt-6 px-6 py-5 overflow-hidden">
                <slot />
            </div>
        </div>

</template>

<script setup>
const goBack = () => {
    window.history.back()
}

</script>

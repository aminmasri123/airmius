<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
    slides: { type: Array, default: () => [] },
    title: { type: String, default: 'Sport Plattform' },
    subtitle: { type: String, default: 'Team Management - Kommunikation - Events' },
})

const fallbackSlides = [
    { src: '/img/login/airmius-auth-team-platform.png', alt: 'Airmius Team-Plattform' },
    { src: '/img/login/airmius-auth-club-operations.png', alt: 'Airmius Vereinsorganisation' },
    { src: '/img/login/airmius-auth-community-events.png', alt: 'Airmius Community und Events' },
    { src: '/img/login/airmius-auth-marketplace-services.png', alt: 'Airmius Marketplace Services' },
]

const images = computed(() => {
    const source = props.slides.length ? props.slides : fallbackSlides

    return source
        .filter((slide) => slide?.src)
        .map((slide, index) => ({
            src: slide.src,
            alt: slide.alt || `Airmius Login-Slider Bild ${index + 1}`,
        }))
})

const current = ref(0)

let interval

const showPrevious = () => {
    current.value = (current.value - 1 + images.value.length) % images.value.length
}

const showNext = () => {
    current.value = (current.value + 1) % images.value.length
}

onMounted(() => {
    if (images.value.length > 1) {
        interval = setInterval(showNext, 8000)
    }
})

onUnmounted(() => {
    if (interval) {
        clearInterval(interval)
    }
})
</script>

<template>
    <div class="hidden md:block md:w-1/2 bg-bg">
        <div v-if="images.length" class="hidden h-screen md:flex text-white items-center justify-center p-6 lg:p-10">
            <div class="relative flex h-full w-full items-center justify-center overflow-hidden rounded-xl border border-border bg-card">
                <div
                    class="absolute inset-0 scale-110 bg-cover bg-center opacity-35 blur-2xl transition-all duration-700"
                    :style="{ backgroundImage: `url(${images[current].src})` }"
                ></div>

                <div class="relative z-10 aspect-[9/16] h-[min(92vh,56rem)] overflow-hidden rounded-xl bg-black shadow-2xl">
                    <div
                        v-for="(img, index) in images"
                        :key="img.src"
                        :class="[
                            'absolute inset-0 transition-all duration-700',
                            current === index ? 'opacity-100 scale-100' : 'opacity-0 scale-105',
                        ]"
                    >
                        <img
                            :src="img.src"
                            :alt="img.alt"
                            class="h-full w-full object-contain transition-transform duration-[8000ms] ease-in-out"
                            :class="current === index ? 'scale-105' : 'scale-110'"
                        />
                    </div>

                    <button
                        v-if="images.length > 1"
                        type="button"
                        @click="showPrevious"
                        class="absolute left-4 top-1/2 z-20 rounded-full bg-black/40 p-3 hover:bg-black/60"
                    >
                        <i class="las la-angle-left"></i>
                    </button>

                    <button
                        v-if="images.length > 1"
                        type="button"
                        @click="showNext"
                        class="absolute right-4 top-1/2 z-20 rounded-full bg-black/40 p-3 hover:bg-black/60"
                    >
                        <i class="las la-angle-right"></i>
                    </button>

                    <div class="pointer-events-none absolute inset-0 flex items-end justify-center p-5">
                        <div class="w-full rounded-lg bg-black/55 p-5 text-center text-white backdrop-blur">
                            <h2 class="text-2xl font-bold">{{ $t(title) }}</h2>
                            <p class="text-sm opacity-80">{{ $t(subtitle) }}</p>

                            <div v-if="images.length > 1" class="pointer-events-auto mt-3 flex justify-center gap-2">
                                <button
                                    v-for="(img, index) in images"
                                    :key="`${img.src}-dot`"
                                    type="button"
                                    @click="current = index"
                                    class="h-2.5 w-2.5 rounded-full transition-all duration-300"
                                    :class="current === index ? 'bg-primary scale-125' : 'bg-primary/40 hover:bg-primary/70'"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

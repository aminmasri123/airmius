<script setup>
import { Link } from '@inertiajs/vue3';

</script>

<template>
        <div class="fixed bottom-0 md:top-16 left-0 right-0 max-w-7xl mx-auto h-16 sm:px-6 rounded-b-lg bg-white/90 items-center z-40 transition-all duration-300">

                <!-- Desktop -->
                <div class="hidden md:flex items-center justify-center gap-6 py-2  text-sm font-medium text-black">

                    <Link class="pb-1 text-center transition hover:text-gray-700">
                        <p><i class="las la-bullhorn la-2x"></i></p>
                        {{ $t("Top Inhalte") }}
                    </Link>

                    <Link class="pb-1 text-center transition hover:text-gray-700">
                        <p><i class="las la-briefcase la-2x"></i></p>
                        {{ $t("Jobs") }}
                    </Link>

                    <Link class="pb-1 text-center transition hover:text-gray-700" >
                        <p><i class="las la-chalkboard-teacher la-2x"></i></p>
                        {{ $t("E-Learning") }}
                    </Link>

                    <Link class="pb-1 text-center transition hover:text-gray-700">
                        <p><i class="las la-warehouse la-2x"></i></p>
                        {{ $t("Vereine") }}
                    </Link>

                    <Link class="pb-1 text-center transition hover:text-gray-700">
                        <p><i class="las la-shopping-bag la-2x"></i></p>
                        {{ $t("Marketplace") }}
                    </Link>
                </div>

                <!-- Mobile Scrollbar -->
                <div class="md:hidden flex w-full justify-center gap-8 bg-white py-2 text-xs text-black">
                    <button class=" py-1 rounded-full whitespace-nowrap transition">
                        <p><i class="las la-bullhorn la-2x"></i></p>
                        {{ $t("Top Inhalte") }}
                    </button>

                    <button class=" py-1 rounded-full whitespace-nowrap transition">
                        <p><i class="las la-briefcase la-2x"></i></p>
                        {{ $t("Jobs") }}
                    </button>

                    <button class=" py-1 rounded-full whitespace-nowrap transition">
                        <p><i class="las la-chalkboard-teacher la-2x"></i></p>
                        {{ $t("E-Learning") }}
                    </button>
                    <button class=" py-1 rounded-full whitespace-nowrap transition">
                        <p><i class="las la-shopping-bag la-2x"></i></p>
                        {{ $t("Shop") }}
                    </button>

                    <button class=" py-1 rounded-full whitespace-nowrap transition">
                        <p><i class="las la-warehouse la-2x"></i></p>
                        {{ $t("Vereine") }}
                    </button>

                </div>

    </div>
</template>

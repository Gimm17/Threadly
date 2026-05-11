<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex bg-surface">
        <!-- Left Side: Branding / Visual (Hidden on mobile) -->
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-inverse-surface items-center justify-center">
            <!-- Decorative Background Elements -->
            <div class="absolute top-0 left-0 w-full h-full opacity-20 pointer-events-none">
                <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full bg-primary blur-[100px]"></div>
                <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-secondary blur-[100px]"></div>
            </div>
            
            <div class="relative z-10 p-12 text-center text-on-primary">
                <Link href="/" class="inline-block mb-8 transition-transform hover:scale-105 duration-300">
                    <ApplicationLogo class="h-24 w-auto fill-current text-primary-fixed" />
                </Link>
                <h1 class="text-display-metric mb-4">ThreadsAI Manager</h1>
                <p class="text-headline-md text-primary-fixed-dim font-normal max-w-lg mx-auto">
                    Platform cerdas untuk mengelola, menganalisis, dan menjadwalkan konten Threads Anda.
                </p>
            </div>
        </div>

        <!-- Right Side: Form Content -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 relative">
            <div class="absolute top-0 right-0 w-full h-full opacity-30 pointer-events-none lg:hidden">
                <div class="absolute top-[-20%] right-[-20%] w-[60%] h-[60%] rounded-full bg-primary-container blur-[120px]"></div>
            </div>

            <div class="w-full max-w-md relative z-10">
                <!-- Mobile Logo -->
                <div class="lg:hidden text-center mb-8">
                    <Link href="/">
                        <ApplicationLogo class="h-16 w-auto fill-current text-primary mx-auto" />
                    </Link>
                </div>
                
                <div class="card bg-surface-container-lowest border-outline-variant/30 backdrop-blur-sm shadow-elevated">
                    <slot />
                </div>
                
                <div class="text-center mt-8">
                    <p class="text-body-sm text-on-surface-variant">
                        &copy; {{ new Date().getFullYear() }} Gimora Digital. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

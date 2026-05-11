<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div class="mb-8 text-center">
            <h2 class="text-headline-lg text-on-surface mb-2">Selamat Datang</h2>
            <p class="text-body-sm text-on-surface-variant">Silakan masuk ke akun Anda untuk melanjutkan.</p>
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-success">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="email" value="Email Address" class="text-on-surface font-semibold" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="nama@email.com"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex justify-between items-center mt-1">
                    <InputLabel for="password" value="Password" class="text-on-surface font-semibold" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs font-semibold text-primary hover:text-primary-container transition-default"
                    >
                        Lupa password?
                    </Link>
                </div>

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center">
                <label class="flex items-center cursor-pointer">
                    <Checkbox name="remember" v-model:checked="form.remember" class="text-primary border-outline-variant focus:ring-primary rounded" />
                    <span class="ms-2 text-sm text-on-surface-variant select-none"
                        >Ingat saya</span
                    >
                </label>
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full justify-center py-3 text-base font-semibold bg-primary hover:bg-inverse-primary text-on-primary hover:text-on-primary-container rounded-xl shadow-md transition-default"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">Memproses...</span>
                    <span v-else>Masuk ke Dashboard</span>
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>

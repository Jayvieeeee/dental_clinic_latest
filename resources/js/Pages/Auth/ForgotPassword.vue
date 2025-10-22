<script setup>
import AuthBackgroundLayout from '@/Layouts/AuthBackroundLayout.vue'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({
  status: {
    type: String,
  },
})

const form = useForm({
  email: '',
})

const showEmptyError = ref(false)

const submit = () => {
  // Check if email is empty
  if (form.email.trim() === '') {
    showEmptyError.value = true
    return
  }
  
  showEmptyError.value = false
  form.post(route('password.email'))
}
</script>

<template>
  <AuthBackgroundLayout title="Welcome to District Smiles Dental Center">
    <Head title="Forgot Password" />

    <div
      class="backdrop-blur-md bg-light border-2 border-white rounded-2xl shadow-2xl 
             p-8 sm:p-10 w-full max-w-md mx-auto text-center"
    >
      <!-- Header -->
      <h2 class="text-xl sm:text-2xl font-semibold mb-4 text-gray-900 drop-shadow-sm">
        Forgot Password
      </h2>

      <!-- Success Message -->
      <div
        v-if="status"
        class="mb-4 text-sm font-medium text-green-600 bg-green-50 border border-green-300 rounded-lg p-3"
      >
        {{ status }}
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-5">
        <div>
          <InputLabel for="email" />

          <TextInput
            id="email"
            type="email"
            placeholder="Email"
            v-model="form.email"
            autofocus
            autocomplete="username"
            class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
            :class="{ 'border-2 border-red-500': showEmptyError }"
          />

          <!-- Show error message when email is empty -->
          <div v-if="showEmptyError" class="mt-2 text-red-500 text-sm text-left">
            Please enter your email address.
          </div>

          <InputError class="mt-2 text-red-500 text-sm" :message="form.errors.email" />
        </div>

        <PrimaryButton
          type="submit"
          :disabled="form.processing"
          class="w-1/2 bg-white font-semibold rounded-full shadow-md hover:shadow-lg uppercase"
        >
          Send
        </PrimaryButton>

        <!-- Back to Login -->
        <div class="text-center mt-4">
          <p class="text-md text-gray-700">
            Remembered your password?
            <Link
              href="/login"
              class="text-dark font-semibold ml-1 hover:underline hover:text-black transition-colors duration-200">
              Go back to Login
            </Link>
          </p>
        </div>
      </form>
    </div>
  </AuthBackgroundLayout>
</template>
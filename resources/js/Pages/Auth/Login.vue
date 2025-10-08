<script setup>
import { ref } from 'vue'
import AuthBackgroundLayout from "@/Layouts/AuthBackroundLayout.vue"
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { Link } from '@inertiajs/vue3'

const showPassword = ref(false)
const username = ref('')
const password = ref('')

const handleLogin = () => {
  console.log('Login attempt', { username: username.value, password: password.value })
}
</script>

<template>
  <AuthBackgroundLayout title="Welcome to District Smiles Dental Center">
    <div
      class="backdrop-blur-md bg-light border-2 border-white rounded-2xl shadow-2xl 
             p-8 sm:p-10 w-full max-w-md mx-auto text-center"
    >
      <h2 class="text-xl sm:text-2xl font-semibold mb-8 text-gray-900 drop-shadow-sm">
        Login to District Smiles Dental Center
      </h2>

      <form @submit.prevent="handleLogin" class="space-y-5">
        <!-- Username -->
        <div>
           <InputLabel for="email"/>
            <input
            v-model="email"
            id="email"
            type="email"
            placeholder="Username / Email"
            class="w-full px-5 py-3.5 pr-12 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
            />
        </div>

        <!-- Password -->
        <div class="relative">
          <InputLabel for="password" />
          <input
            v-model="password"
            id="password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Password"
            class="w-full px-5 py-3.5 pr-12 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />

        </div>

        <!-- Login Button -->
        <PrimaryButton
          type="submit"
          class="w-1/2 bg-white font-semibold rounded-full shadow-md hover:shadow-lg"
        >
          LOGIN
        </PrimaryButton>

        <!-- Forgot Password -->
        <div class="text-center mt-4">
        <Link
            href="/forgot-password"
            class="text-medium font-medium hover:underline">
            Forgot Password?
        </Link>
        </div>


        <!-- Sign Up -->
        <div class="text-center mt-3">
          <p class="text-lg">
            Don't have an account?
            <Link href="/register" class="text-dark font-semibold ml-1 hover:underline hover:text-black transition-colors duration-200">
              Sign Up
            </Link>
          </p>
        </div>
      </form>
    </div>
  </AuthBackgroundLayout>
</template>

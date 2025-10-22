<script setup>
import { ref } from 'vue'
import AuthBackgroundLayout from "@/Layouts/AuthBackroundLayout.vue"
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/solid'

const form = useForm({
  username: '',
  first_name: '',
  last_name: '',
  email: '',
  contact_no: '',
  password: '',
  password_confirmation: '',
})

const showPassword = ref(false)
const showConfirmPassword = ref(false)

const handleRegister = () => {
  form.post(route('register'))
}
</script>

<template>
  <AuthBackgroundLayout title="Welcome to District Smiles Dental Center">
    <div
      class="backdrop-blur-md bg-light border-2 border-white rounded-2xl shadow-2xl 
             p-8 sm:p-10 my-10 w-full max-w-md mx-auto text-center"
    >
      <h2 class="text-xl sm:text-2xl font-semibold mb-8 text-gray-900 drop-shadow-sm">
        Create an Account
      </h2>

      <form @submit.prevent="handleRegister" class="space-y-4">

        <!-- First Name -->
        <div>
          <InputLabel for="firstName" />
          <input
            id="firstName"
            v-model="form.first_name"
            type="text"
            placeholder="First Name"
            class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />
          <p v-if="form.errors.first_name" class="text-red-600 text-sm mt-1">
            {{ form.errors.first_name }}
          </p>
        </div>

        <!-- Last Name -->
        <div>
          <InputLabel for="lastName" />
          <input
            id="lastName"
            v-model="form.last_name"
            type="text"
            placeholder="Last Name"
            class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />
          <p v-if="form.errors.last_name" class="text-red-600 text-sm mt-1">
            {{ form.errors.last_name }}
          </p>
        </div>

        <!-- Email -->
        <div>
          <InputLabel for="email" />
          <input
            id="email"
            v-model="form.email"
            type="email"
            placeholder="Email"
            class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />
          <p v-if="form.errors.email" class="text-red-600 text-sm mt-1">
            {{ form.errors.email }}
          </p>
        </div>

        <!-- Contact Number -->
        <div>
          <InputLabel for="contactNumber" />
          <input
            id="contactNumber"
            v-model="form.contact_no"
            type="text"
            placeholder="Contact Number"
            class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />
          <p v-if="form.errors.contact_no" class="text-red-600 text-sm mt-1">
            {{ form.errors.contact_no }}
          </p>
        </div>

        <!-- Username -->
        <div>
          <InputLabel for="username" />
          <input
            id="username"
            v-model="form.username"
            type="text"
            placeholder="Username"
            class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />
          <p v-if="form.errors.username" class="text-red-600 text-sm mt-1">
            {{ form.errors.username }}
          </p>
        </div>

        <!-- Password -->
        <div class="relative">
          <InputLabel for="password" />
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Password"
            class="w-full px-5 py-3.5 pr-12 rounded-xl border-0 bg-white/90 
                  text-gray-800 placeholder-gray-500 focus:ring-2 
                  focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />

          <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-teal-600"
          >
            <EyeIcon v-if="!showPassword" class="h-5 w-5" />
            <EyeSlashIcon v-else class="h-5 w-5" />
          </button>

          <p v-if="form.errors.password" class="text-red-600 text-sm mt-1">
            {{ form.errors.password }}
          </p>
        </div>

        <!-- Confirm Password -->
        <div class="relative">
          <InputLabel for="confirmPassword" />
          <input
            id="confirmPassword"
            v-model="form.password_confirmation"
            :type="showConfirmPassword ? 'text' : 'password'"
            placeholder="Confirm Password"
            class="w-full px-5 py-3.5 pr-12 rounded-xl border-0 bg-white/90 
                  text-gray-800 placeholder-gray-500 focus:ring-2 
                  focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />

          <button
            type="button"
            @click="showConfirmPassword = !showConfirmPassword"
            class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-teal-600"
          >
            <EyeIcon v-if="!showConfirmPassword" class="h-5 w-5" />
            <EyeSlashIcon v-else class="h-5 w-5" />
          </button>
        </div>


        <!-- Sign Up Button -->
        <PrimaryButton
          type="submit"
          class="w-1/2 bg-white font-semibold rounded-full shadow-md hover:shadow-lg"
        >
          SIGN UP
        </PrimaryButton>

        <!-- Login link -->
        <div class="text-center mt-3">
          <p class="text-lg">
            Already have an account?
            <Link href="/login" class="text-dark font-semibold ml-1 hover:underline hover:text-black transition-colors duration-200">
              Login
            </Link>
          </p>
        </div>
      </form>
    </div>
  </AuthBackgroundLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

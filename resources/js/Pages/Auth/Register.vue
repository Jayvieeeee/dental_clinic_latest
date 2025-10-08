<script setup>
import { ref } from 'vue'
import AuthBackgroundLayout from "@/Layouts/AuthBackroundLayout.vue"
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { Link } from '@inertiajs/vue3'

const form = ref({
  firstName: '',
  lastName: '',
  email: '',
  contactNumber: '',
  username: '',
  password: '',
  confirmPassword: ''
})

const showPassword = ref(false)
const showConfirmPassword = ref(false)

const handleRegister = () => {
  console.log('Registering:', form.value)
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
            v-model="form.firstName"
            type="text"
            placeholder="First Name"
            class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />
        </div>

        <!-- Last Name -->
        <div>
          <InputLabel for="lastName" />
          <input
            id="lastName"
            v-model="form.lastName"
            type="text"
            placeholder="Last Name"
            class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />
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
        </div>

        <!-- Contact Number -->
        <div>
          <InputLabel for="contactNumber" />
          <input
            id="contactNumber"
            v-model="form.contactNumber"
            type="text"
            placeholder="Contact Number"
            class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />
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
            class="absolute inset-y-0 right-4 flex items-center text-gray-500 hover:text-teal-600"
            @click="showPassword = !showPassword"
          >
            <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
          </button>
        </div>

        <!-- Confirm Password -->
        <div class="relative">
          <InputLabel for="confirmPassword" />
          <input
            id="confirmPassword"
            v-model="form.confirmPassword"
            :type="showConfirmPassword ? 'text' : 'password'"
            placeholder="Confirm Password"
            class="w-full px-5 py-3.5 pr-12 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-4 flex items-center text-gray-500 hover:text-teal-600"
            @click="showConfirmPassword = !showConfirmPassword"
          >
            <i :class="showConfirmPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
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

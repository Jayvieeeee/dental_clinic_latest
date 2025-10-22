<script setup>
import { ref, watch, onMounted } from 'vue'
import AuthBackgroundLayout from "@/Layouts/AuthBackroundLayout.vue"
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { Link, usePage, useForm } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/solid'

// Form state
const identifier = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)

const page = usePage()
const showSuccessModal = ref(false)
const successMessage = ref('')

onMounted(() => {
  if (page.props.flash?.success) {
    successMessage.value = page.props.flash.success
    showSuccessModal.value = true
  }
})

const errorMessage = ref('')

const form = useForm({
  identifier: '', // handle both username or email
  password: '',
})

// when login
const handleLogin = () => {
  errorMessage.value = ''
  form.post(route('login'), {
  onError: (errors) => {
    console.error('Login failed:', errors)

    if (errors.general) {
      errorMessage.value = errors.general
    }

    if (errors.identifier) {
      form.errors.identifier = errors.identifier
    }
    if (errors.password) {
      form.errors.password = errors.password
    }
  },
})
}

// Automatically clear error when typing
watch([identifier, password], () => {
  if (errorMessage.value) {
    errorMessage.value = ''
  }
})
</script>

<template>
  <AuthBackgroundLayout title="Welcome to District Smiles Dental Center">
    <div
      class="backdrop-blur-md bg-light border-2 border-white rounded-2xl shadow-2xl 
             p-8 sm:p-10 w-full max-w-md mx-auto text-center relative"
    >
      <h2 class="text-xl sm:text-2xl font-semibold mb-8 text-gray-900 drop-shadow-sm">
        Login to District Smiles Dental Center
      </h2>

      <form @submit.prevent="handleLogin" class="space-y-5">
        <!-- Username or Email -->
        <div>
          <InputLabel for="identifier" />
          <input
            v-model="form.identifier"
            id="identifier"
            type="text"
            placeholder="Username or Email"
            class="w-full px-5 py-3.5 pr-12 rounded-xl border-0 bg-white/90 
                   text-gray-800 placeholder-gray-500 focus:ring-2 
                   focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
          />
        </div>
          <p v-if="form.errors.identifier" class="text-red-600 text-sm text-left mt-1">
               {{ form.errors.identifier }}
           </p>

    <!-- Password -->
    <div class="relative">
      <InputLabel for="password" />
      <input
        v-model="form.password"
        id="password"
        :type="showPassword ? 'text' : 'password'"
        placeholder="Password"
        class="w-full px-5 py-3.5 pr-12 rounded-xl border-0 bg-white/90 
              text-gray-800 placeholder-gray-500 focus:ring-2 
              focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
      />

      <!-- Toggle Show/Hide Password (Heroicons) -->
      <button
        type="button"
        @click="showPassword = !showPassword"
        class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
      >
        <EyeIcon v-if="!showPassword" class="h-5 w-5" />
        <EyeSlashIcon v-else class="h-5 w-5" />
      </button>
    </div>

    <p v-if="form.errors.password" class="text-red-600 text-sm text-left mt-1">
      {{ form.errors.password }}
    </p>


        <!-- Error Message -->
        <Transition name="fade">
          <p
            v-if="errorMessage"
            class="text-red-600 text-sm font-medium mt-2"
          >
            {{ errorMessage }}
          </p>
        </Transition>

        <!-- Login Button -->
        <PrimaryButton
          type="submit"
          class="w-1/2 bg-white font-semibold rounded-full shadow-md hover:shadow-lg"
          :disabled="loading"
        >
          <span v-if="!loading">LOGIN</span>
          <span v-else>Logging in...</span>
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
            <Link
              href="/register"
              class="text-dark font-semibold ml-1 hover:underline hover:text-black transition-colors duration-200"
            >
              Sign Up
            </Link>
          </p>
        </div>
      </form>
    </div>

    <!-- Loading overlay -->
    <transition name="fade">
      <div
        v-if="loading"
        class="fixed inset-0 bg-black bg-opacity-60 flex flex-col items-center justify-center z-[1000]"
      >
        <div class="loader mb-4"></div>
        <p class="text-white text-lg font-medium">Logging in...</p>
      </div>
    </transition>

    <!-- Modal -->
    <Modal :show="showSuccessModal" @close="showSuccessModal = false">
      <div class="p-8 text-center">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Registration Successful!</h2>
        <p class="text-gray-600 mb-6">{{ successMessage }}</p>
        <PrimaryButton
          @click="showSuccessModal = false"
          class="bg-neutral text-white rounded-full px-6 py-2 hover:bg-teal-600 transition"
        >
          OK
        </PrimaryButton>
      </div>
    </Modal>
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

.loader {
  border: 4px solid rgba(255, 255, 255, 0.3);
  border-top: 4px solid #14b8a6; /* teal */
  border-radius: 50%;
  width: 48px;
  height: 48px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>

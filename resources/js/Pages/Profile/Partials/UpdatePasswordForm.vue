<script setup>
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import Modal from '@/Components/Modal.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/solid'

const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)

const showSuccessModal = ref(false) 

const passwordInput = ref(null)
const currentPasswordInput = ref(null)

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const updatePassword = () => {
  form.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      showSuccessModal.value = true 
    },
    onError: () => {
      if (form.errors.password) {
        form.reset('password', 'password_confirmation')
        passwordInput.value?.focus()
      }
      if (form.errors.current_password) {
        form.reset('current_password')
        currentPasswordInput.value?.focus()
      }
    },
  })
}
</script>

<template>
  <section class="max-w-2xl w-full pb-10 px-5">
    <h2 class="text-2xl font-semibold mb-8 text-left tracking-wide">
      Change Password
    </h2>

    <form @submit.prevent="updatePassword" class="space-y-6">
      <!-- Old Password -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4">
        <InputLabel
          for="current_password"
          value="Old Password"
          class="sm:w-40 w-full text-gray-700"
        />
        <div class="relative flex-1 mt-2 sm:mt-0">
          <TextInput
            id="current_password"
            ref="currentPasswordInput"
            v-model="form.current_password"
            :type="showCurrent ? 'text' : 'password'"
            class="block w-full pr-10 rounded-xl border-2 border-black focus:ring-2 focus:ring-teal-600 focus:border-teal-600"
            autocomplete="current-password"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-3 flex items-center text-gray-500 focus:outline-none"
            @click="showCurrent = !showCurrent"
          >
            <component
              :is="showCurrent ? EyeSlashIcon : EyeIcon"
              class="h-5 w-5"
            />
          </button>
        </div>
      </div>
      <InputError
        :message="form.errors.current_password"
        class="mt-2 ml-[10.5rem]"
      />

      <!-- New Password -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4">
        <InputLabel
          for="password"
          value="New Password"
          class="sm:w-40 w-full text-gray-700"
        />
        <div class="relative flex-1 mt-2 sm:mt-0">
          <TextInput
            id="password"
            ref="passwordInput"
            v-model="form.password"
            :type="showNew ? 'text' : 'password'"
            class="block w-full pr-10 rounded-xl border-2 border-black focus:ring-2 focus:ring-teal-600 focus:border-teal-600"
            autocomplete="new-password"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-3 flex items-center text-gray-500 focus:outline-none"
            @click="showNew = !showNew"
          >
            <component
              :is="showNew ? EyeSlashIcon : EyeIcon"
              class="h-5 w-5"
            />
          </button>
        </div>
      </div>
      <InputError :message="form.errors.password" class="mt-2 ml-[10.5rem]" />

      <!-- Confirm Password -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4">
        <InputLabel
          for="password_confirmation"
          value="Confirm Password"
          class="sm:w-40 w-full text-gray-700"
        />
        <div class="relative flex-1 mt-2 sm:mt-0">
          <TextInput
            id="password_confirmation"
            v-model="form.password_confirmation"
            :type="showConfirm ? 'text' : 'password'"
            class="block w-full pr-10 rounded-xl border-2 border-black focus:ring-2 focus:ring-teal-600 focus:border-teal-600"
            autocomplete="new-password"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-3 flex items-center text-gray-500 focus:outline-none"
            @click="showConfirm = !showConfirm"
          >
            <component
              :is="showConfirm ? EyeSlashIcon : EyeIcon"
              class="h-5 w-5"
            />
          </button>
        </div>
      </div>
      <InputError
        :message="form.errors.password_confirmation"
        class="mt-2 ml-[10.5rem]"
      />

      <!-- Submit Button -->
      <div class="flex justify-center pt-4">
        <PrimaryButton
          :disabled="form.processing"
          class="bg-dark text-white px-10 py-3 rounded-full text-base font-medium hover:bg-light transition"
        >
          Change Password
        </PrimaryButton>
      </div>
    </form>

    <!-- Modal -->
    <Modal :show="showSuccessModal" @close="showSuccessModal = false">
      <div class="p-8 text-center">
        <h2 class="text-2xl font-semibold text-gray-800 mb-2">
          Password Updated!
        </h2>
        <p class="text-gray-600 mb-6">
          Your password has been changed successfully.
        </p>
        <PrimaryButton
          class="bg-dark text-white px-6 py-2 rounded-full hover:bg-light transition"
          @click="showSuccessModal = false"
        >
          OK
        </PrimaryButton>
      </div>
    </Modal>
  </section>
</template>

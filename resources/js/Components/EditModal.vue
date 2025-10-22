<script setup>
import { ref, watch, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
  show: Boolean,
  title: String,
  fields: Array,
  routeName: String,
})

const emit = defineEmits(['close', 'success'])

// Reactive form object, recreated every time modal opens
let form = useForm({})

// Local validation errors
const validationErrors = ref({})

// Original field values for change detection
const originalValues = ref({})

// Watch for fields change and create a new form
watch(
  () => props.fields,
  (newFields) => {
    if (!newFields || !newFields.length) return

    const initialData = {}
    originalValues.value = {}
    
    newFields.forEach(f => {
      initialData[f.name] = f.value || ''
      originalValues.value[f.name] = f.value || ''
    })

    form = useForm(initialData)
    // Clear validation errors when fields change
    validationErrors.value = {}
  },
  { immediate: true }
)

// Check if data has changed
const hasChanges = computed(() => {
  return props.fields.some(field => {
    const currentValue = form[field.name]?.toString().trim()
    const originalValue = originalValues.value[field.name]?.toString().trim()
    return currentValue !== originalValue
  })
})

// Basic client-side validation
const validateForm = () => {
  validationErrors.value = {}
  let isValid = true

  // Check if any changes were made
  if (!hasChanges.value) {
    validationErrors.value.general = 'No changes were made'
    return false
  }

  props.fields.forEach(field => {
    const value = form[field.name]?.toString().trim()
    
    // Check if field is empty
    if (!value || value === '') {
      validationErrors.value[field.name] = `${field.label} is required`
      isValid = false
    }
  })

  return isValid
}

const submit = () => {
  // Validate before submitting
  if (!validateForm()) {
    return
  }

  form.patch(route(props.routeName), {
    preserveScroll: true,
    onSuccess: () => {
      emit('close')
      emit('success')
      form.reset()
      validationErrors.value = {}
    },
    onError: (errors) => {
      if (errors.email) {
        validationErrors.value.email = errors.email
      }
    },
  })
}

// Close modal and reset errors
const closeModal = () => {
  emit('close')
  validationErrors.value = {}
  form.clearErrors()
}
</script>

<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/40 flex justify-center items-center z-50"
  >
    <div
      class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md relative animate-fadeIn"
    >
      <!-- Close Button -->
      <button
        @click="closeModal"
        class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-lg"
      >
        ✕
      </button>

      <!-- Title -->
      <h2 class="text-2xl font-semibold text-cyan-800 mb-6 text-center">
        {{ title }}
      </h2>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-4">
        <!-- General Error -->
        <div v-if="validationErrors.general" class="bg-red-50 border border-red-200 rounded-lg p-3">
          <p class="text-red-600 text-sm text-center">{{ validationErrors.general }}</p>
        </div>

        <div v-for="(field, index) in fields" :key="index">
          <InputLabel :for="field.name" :value="field.label" />
          <TextInput
            :id="field.name"
            v-model="form[field.name]"
            class="w-full rounded-xl border-gray-300"
            :class="{ 
              'border-red-500': validationErrors[field.name] || form.errors[field.name],
              'border-green-500': hasChanges && form[field.name]?.toString().trim() !== originalValues[field.name]?.toString().trim()
            }"
            :type="field.name === 'email' ? 'email' : 'text'"
            @input="() => {
              // Clear validation error when user starts typing
              if (validationErrors[field.name]) {
                validationErrors[field.name] = ''
              }
              // Clear form errors when user types
              if (form.errors[field.name]) {
                form.clearErrors(field.name)
              }
            }"
          />
          <!-- Show validation errors -->
          <InputError 
            v-if="validationErrors[field.name]" 
            :message="validationErrors[field.name]" 
            class="mt-2" 
          />
          <!-- Show server-side errors -->
          <InputError 
            v-else-if="form.errors[field.name]" 
            :message="form.errors[field.name]" 
            class="mt-2" 
          />
        </div>

        <div class="flex justify-center mt-6">
          <PrimaryButton
            :disabled="form.processing || !hasChanges"
            class="bg-dark hover:bg-light text-white px-8 py-1.5 rounded-full transition-colors"
            :class="{ 'opacity-50 cursor-not-allowed': !hasChanges }"
          >
            {{ form.processing ? 'Saving...' : 'Save' }}
          </PrimaryButton>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.97);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
.animate-fadeIn {
  animation: fadeIn 0.25s ease-in-out;
}
</style>
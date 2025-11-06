<template>
  <Modal :show="modelValue" @close="$emit('update:modelValue', false)" max-width="2xl">
    <div class="p-6">
      <!-- Header -->
      <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Complete Payment</h2>
        <p class="text-gray-600 mt-2">Pay ₱300.00 to confirm your appointment</p>
      </div>

      <!-- Appointment Summary -->
      <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <h3 class="font-semibold text-lg mb-3">Appointment Details</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600">Service:</span>
            <span class="font-medium">{{ appointmentData.service }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Date:</span>
            <span class="font-medium">{{ appointmentData.date }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Time:</span>
            <span class="font-medium">{{ appointmentData.time }}</span>
          </div>
          <div class="flex justify-between border-t pt-2 mt-2">
            <span class="text-gray-600 font-semibold">Total Amount:</span>
            <span class="font-bold text-lg">₱300.00</span>
          </div>
        </div>
      </div>

      <!-- Payment Methods -->
      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-3">Choose Payment Method</label>
        
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div class="border border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors">
            <img src="https://images.paymongo.com/gcash.png" alt="GCash" class="h-8 mx-auto mb-2">
            <span class="text-sm font-medium">GCash</span>
          </div>
          <div class="border border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors">
            <img src="https://images.paymongo.com/grab_pay.png" alt="GrabPay" class="h-8 mx-auto mb-2">
            <span class="text-sm font-medium">GrabPay</span>
          </div>
          <div class="border border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors">
            <img src="https://images.paymongo.com/paymaya.png" alt="Maya" class="h-8 mx-auto mb-2">
            <span class="text-sm font-medium">Maya</span>
          </div>
          <div class="border border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors">
            <img src="https://images.paymongo.com/visa_mastercard.png" alt="Credit/Debit Card" class="h-8 mx-auto mb-2">
            <span class="text-sm font-medium">Card</span>
          </div>
        </div>

        <p class="text-xs text-gray-500 text-center">
          You'll be redirected to a secure payment page
        </p>
      </div>

      <!-- Pay Button -->
      <button
        @click="createCheckoutSession"
        :disabled="loading"
        :class="[
          'w-full py-3 bg-green-500 text-white rounded-md hover:bg-green-600 font-semibold transition-colors mb-3',
          loading ? 'opacity-50 cursor-not-allowed' : ''
        ]"
      >
        {{ loading ? 'Creating Payment...' : 'Proceed to Payment' }}
      </button>

      <!-- Cancel Button -->
      <button
        @click="cancelPayment"
        :disabled="loading"
        :class="[
          'w-full py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors',
          loading ? 'opacity-50 cursor-not-allowed' : ''
        ]"
      >
        Cancel
      </button>

      <!-- Error Message -->
      <div v-if="error" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
        <p class="text-red-700 text-sm">{{ error }}</p>
      </div>

      <!-- Loading Overlay -->
      <div v-if="loading" class="absolute inset-0 bg-white bg-opacity-80 flex items-center justify-center rounded-lg">
        <div class="text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
          <p class="text-gray-700 font-semibold">Preparing your payment...</p>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref } from 'vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  modelValue: Boolean,
  appointmentData: {
    type: Object,
    required: true,
    default: () => ({
      service: '',
      serviceId: '',
      date: '',
      time: '',
      scheduleId: '',
      customer: {
        firstName: '',
        lastName: '',
        email: ''
      }
    })
  }
})

const emit = defineEmits(['update:modelValue', 'payment-success', 'payment-cancelled'])

// Reactive state
const loading = ref(false)
const error = ref('')

const createCheckoutSession = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await fetch('/customer/payment/create', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        service_id: props.appointmentData.serviceId,
        service_name: props.appointmentData.service,
        appointment_date: props.appointmentData.date,
        schedule_id: props.appointmentData.scheduleId
      })
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.error || 'Failed to create payment session')
    }

    if (data.checkout_url) {
      // Redirect to PayMongo checkout
      window.location.href = data.checkout_url
    } else {
      throw new Error('No checkout URL received')
    }

  } catch (err) {
    console.error('Payment error:', err)
    error.value = err.message || 'Payment processing failed. Please try again.'
  } finally {
    loading.value = false
  }
}

const cancelPayment = () => {
  emit('payment-cancelled')
  emit('update:modelValue', false)
}
</script>
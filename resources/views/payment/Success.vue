<template>
  <CustomerLayout>
    <Head title="Payment Successful" />
    
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full space-y-8">
        <!-- Success Icon -->
        <div class="text-center">
          <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-green-100">
            <svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
          </div>
          
          <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
            Payment Successful!
          </h2>
          
          <p class="mt-2 text-sm text-gray-600">
            Thank you for your payment. Your appointment has been confirmed.
          </p>
        </div>

        <!-- Appointment Details -->
        <div class="bg-white shadow rounded-lg p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Appointment Details</h3>
          
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-gray-600">Service:</span>
              <span class="font-medium">{{ appointment?.service?.service_name || 'Dental Service' }}</span>
            </div>
            
            <div class="flex justify-between">
              <span class="text-gray-600">Date:</span>
              <span class="font-medium">{{ formatDate(appointment?.appointment_date) }}</span>
            </div>
            
            <div class="flex justify-between">
              <span class="text-gray-600">Time:</span>
              <span class="font-medium">{{ appointment?.schedule?.time_slot || 'Selected time' }}</span>
            </div>
            
            <div class="flex justify-between border-t pt-3 mt-3">
              <span class="text-gray-600 font-semibold">Amount Paid:</span>
              <span class="font-bold text-lg text-green-600">₱300.00</span>
            </div>
          </div>
        </div>

        <!-- Payment Details -->
        <div v-if="payment" class="bg-gray-50 rounded-lg p-4">
          <h4 class="text-sm font-medium text-gray-900 mb-2">Payment Information</h4>
          <div class="text-xs text-gray-600 space-y-1">
            <div>Method: {{ payment.payment_method }}</div>
            <div>Reference: {{ payment.transaction_reference }}</div>
            <div>Paid at: {{ formatDateTime(payment.paid_at) }}</div>
          </div>
        </div>

        <!-- Actions -->
        <div class="space-y-3">
          <PrimaryButton 
            @click="goToAppointments" 
            class="w-full bg-green-600 hover:bg-green-700"
          >
            View My Appointments
          </PrimaryButton>
          
          <button 
            @click="goHome" 
            class="w-full text-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
          >
            Back to Home
          </button>
        </div>

        <!-- Processing Message -->
        <div v-if="!appointment || appointment.status === 'pending'" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div class="flex items-center">
            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-3"></div>
            <p class="text-sm text-blue-700">
              We're verifying your payment. This may take a few moments...
            </p>
          </div>
        </div>
      </div>
    </div>
  </CustomerLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import CustomerLayout from '@/Layouts/CustomerLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const page = usePage()

const appointment = computed(() => page.props.appointment || {})
const payment = computed(() => page.props.payment || {})
const checkoutSessionId = computed(() => page.props.checkoutSessionId || '')

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateTime = (dateTimeString) => {
  if (!dateTimeString) return ''
  return new Date(dateTimeString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const goToAppointments = () => {
  router.visit(route('customer.appointments'))
}

const goHome = () => {
  router.visit(route('customer.home'))
}
</script>
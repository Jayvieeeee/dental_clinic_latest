<template>
  <div 
    v-if="show" 
    class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex justify-center items-center"
  >
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">

      <!-- Success Icon -->
      <div class="text-center">
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100">
          <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
        </div>
        <h2 class="mt-4 text-2xl font-bold">Payment Successful!</h2>
        <p class="text-sm text-gray-600 mt-1">Your appointment has been confirmed.</p>
      </div>

      <!-- Appointment Details -->
      <div class="mt-6 space-y-3 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-600">Service:</span>
          <span>{{ appointment?.service?.service_name }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-600">Date:</span>
          <span>{{ formatDate(appointment?.appointment_date) }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-600">Time:</span>
          <span>{{ appointment?.schedule?.time_slot }}</span>
        </div>
        <div class="flex justify-between pt-3 border-t">
          <span class="font-semibold">Amount Paid:</span>
          <span class="font-bold text-green-600">₱300.00</span>
        </div>
      </div>

      <!-- Buttons -->
      <div class="mt-6 space-y-2">
        <button @click="goToAppointments" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-md">
          View My Appointments
        </button>
        <button @click="goHome" class="w-full border py-2 rounded-md bg-white hover:bg-gray-50">
          Back to Home
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

defineProps({
  show: Boolean,
  appointment: Object,
  payment: Object,
})

const formatDate = (date) => date ? new Date(date).toLocaleDateString() : ''

const goToAppointments = () => router.visit(route('customer.view'))
const goHome = () => router.visit(route('customer.home'))
</script>

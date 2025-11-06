<template>
  <CustomerLayout>
    <Head title="Payment Cancelled" />
    
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full space-y-8 text-center">
        <!-- Cancelled Icon -->
        <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-red-100">
          <svg class="h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </div>
        
        <h2 class="text-3xl font-extrabold text-gray-900">
          Payment Cancelled
        </h2>
        
        <p class="text-sm text-gray-600">
          Your payment was cancelled. No appointment was created.
        </p>

        <!-- Info Box -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <p class="text-sm text-yellow-700">
            If this was a mistake, you can try again by going to your appointments page.
          </p>
        </div>

        <!-- Actions -->
        <div class="space-y-3">
          <PrimaryButton 
            @click="goToAppointments" 
            class="w-full bg-gray-800 hover:bg-gray-900"
          >
            Go to Appointments
          </PrimaryButton>
          
          <button 
            @click="goHome" 
            class="w-full text-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
          >
            Back to Home
          </button>

          <button 
            @click="tryAgain" 
            class="w-full text-center py-2 px-4 text-sm font-medium text-blue-600 hover:text-blue-500"
          >
            Try Payment Again
          </button>
        </div>
      </div>
    </div>
  </CustomerLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import CustomerLayout from '@/Layouts/CustomerLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  appointmentId: String
})

const goToAppointments = () => {
  router.visit(route('customer.appointments'))
}

const goHome = () => {
  router.visit(route('customer.home'))
}

const tryAgain = () => {
  // You can redirect to create new appointment or show payment modal again
  router.visit(route('customer.appointment.create'))
}
</script>
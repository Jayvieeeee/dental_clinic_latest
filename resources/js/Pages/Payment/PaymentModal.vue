<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  appointmentData: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:modelValue', 'payment-success', 'payment-cancelled'])

const isOpen = ref(props.modelValue)
watch(() => props.modelValue, (val) => (isOpen.value = val))

const amount = ref('₱300.00')
const loading = ref(false)
const showStatusModal = ref(false)
const statusMessage = ref('')
const statusType = ref('')

// ✅ Automatically update amount
watch(
  () => props.appointmentData,
  (data) => {
    amount.value = data?.amount ? `₱${parseFloat(data.amount).toFixed(2)}` : '₱300.00'
  },
  { immediate: true }
)

const closeModal = () => {
  isOpen.value = false
  emit('update:modelValue', false)
}

const showStatus = (message, type = 'info') => {
  statusMessage.value = message
  statusType.value = type
  showStatusModal.value = true
  setTimeout(() => (showStatusModal.value = false), 2000)
}

const handlePayNow = async () => {
  loading.value = true;

  try {
    const data = props.appointmentData;
    console.log('Sending payment data:', data);

    const appointmentId = data.appointment_id || data.appointmentId;
    const serviceId = data.serviceId ?? data.service_id;
    const scheduleId = data.scheduleId ?? data.schedule_id;
    const serviceName = data.service ?? data.service_name;
    const date = data.date; // This is the appointment date
    const time = data.time; // Get time from appointmentData

    if (!serviceId || !date || !time || !scheduleId) {
      console.error('Missing appointment info:', { serviceId, date, time, scheduleId });
      showStatus('❌ Missing appointment information. Please select service, date, and time.', 'error');
      loading.value = false;
      return;
    }

    // ✅ Match the exact field names expected by your backend validation
    const paymentData = {
      appointment_id: appointmentId,
      service_id: serviceId,
      schedule_id: scheduleId,
      service_name: serviceName,
      appointment_date: date, // Use appointment_date instead of schedule_datetime
    };

    console.log('Processed payment data for backend:', paymentData);

    const response = await axios.post('/customer/payment/create', paymentData);
    console.log('Payment response:', response.data);

    const checkoutUrl = response.data.checkout_url;
    if (checkoutUrl) {
      showStatus('Redirecting to payment...', 'success');
      setTimeout(() => (window.location.href = checkoutUrl), 1500);
    } else {
      showStatus('❌ Failed to generate payment link.', 'error');
    }
  } catch (error) {
    console.error('Payment error:', error);
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      const errorMessages = Object.values(errors).flat().join(', ');
      showStatus(`❌ ${errorMessages}`, 'error');
    } else if (error.response?.data?.error) {
      showStatus(`❌ ${error.response.data.error}`, 'error');
    } else {
      showStatus('❌ Payment initialization failed. Try again.', 'error');
    }
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 font-rem"
  >
    <div class="bg-light rounded-xl shadow-xl max-w-2xl w-full relative">
      <button
        @click="closeModal"
        class="absolute top-4 right-4 text-gray-600 hover:text-gray-900 text-2xl font-bold z-10"
      >
        ×
      </button>

      <div class="p-8">
        <h2 class="text-3xl font-bold text-teal-800 mb-6 text-center">Payment Confirmation</h2>

        <div class="p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Appointment Summary</h3>

          <div class="space-y-3">
            <div class="flex justify-between">
              <span>Service:</span>
              <span class="font-medium">{{ appointmentData.service || appointmentData.service_name }}</span>
            </div>

            <div class="flex justify-between">
              <span>Date:</span>
              <span class="font-medium">{{ appointmentData.date }}</span>
            </div>

            <div class="flex justify-between">
              <span>Time:</span>
              <span class="font-medium">{{ appointmentData.time }}</span>
            </div>

            <div class="border-t-2 border-black pt-3 mt-3">
              <div class="flex justify-between text-lg">
                <span class="font-semibold">Amount to pay:</span>
                <span class="font-bold text-dark">{{ amount }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="text-center">
          <PrimaryButton
            @click="handlePayNow"
            :disabled="loading"
            class="bg-dark hover:bg-neutral text-white font-semibold py-3 px-12 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-lg"
          >
            {{ loading ? 'Processing...' : 'Proceed to Payment' }}
          </PrimaryButton>

          <p class="text-gray-600 mt-5">
            You will be redirected to PayMongo to complete your payment securely.
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="showStatusModal"
      class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50"
    >
      <div
        class="bg-white px-10 py-6 rounded-lg shadow-lg text-center"
        :class="{
          'border-t-4 border-green-500': statusType === 'success',
          'border-t-4 border-red-500': statusType === 'error'
        }"
      >
        <p
          class="text-lg font-semibold"
          :class="{
            'text-green-600': statusType === 'success',
            'text-red-600': statusType === 'error'
          }"
        >
          {{ statusMessage }}
        </p>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, computed } from 'vue';
import { Head, usePage, useForm, router } from "@inertiajs/vue3";
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DateTimeModal from '@/Components/DateTimeModal.vue';
import PaymentModal from '@/Pages/Payment/PaymentModal.vue';
import Modal from '@/Components/Modal.vue';

const page = usePage();
const appointmentId = ref(null);

const user = computed(() => page.props.user ?? {});
const services = computed(() => page.props.services ?? []);

// --- form data ---
const form = ref({
  firstName: user.value.first_name || '',
  lastName: user.value.last_name || '',
  email: user.value.email || '',
  contactNumber: user.value.contact_no || '',
  service: '',
  serviceId: '',
  date: '',
  dateTime: '', 
  timeLabel: '', 
  scheduleId: '' 
});

const showSlotPicker = ref(false);
const showModal = ref(false);
const modalMessage = ref('');
const showPaymentModal = ref(false);

// Update service selection to include serviceId
const updateServiceSelection = (serviceName) => {
  form.value.service = serviceName;
  const selectedService = services.value.find(
    (s) => s.service_name === serviceName
  );
  form.value.serviceId = selectedService ? selectedService.service_id : '';
};

// --- open message modal ---
const openModal = (message) => {
  modalMessage.value = message;
  showModal.value = true;
};

// --- open slot picker ---
const chooseSlots = () => {
  if (!form.value.service) {
    openModal('Please select a dental service first.');
    return;
  }
  showSlotPicker.value = true;
};

// Handle datetime selection from modal - FIXED VERSION
const handleDateTimeSelected = (data) => {
  console.log('DateTime Selected Data:', data);
  
  // Make sure we're setting string values, not objects
  form.value.date = typeof data === 'object' ? data.date : '';
  form.value.dateTime = typeof data === 'object' ? data.time : '';
  form.value.timeLabel = typeof data === 'object' ? data.timeLabel : '';
  form.value.scheduleId = typeof data === 'object' ? data.scheduleId : '';
  
  console.log('Form after update:', {
    date: form.value.date,
    dateTime: form.value.dateTime,
    timeLabel: form.value.timeLabel,
    scheduleId: form.value.scheduleId
  });
};

// Individual update handlers for v-model
const updateSelectedDate = (date) => {
  form.value.date = date;
};

const updateSelectedTime = (time) => {
  form.value.dateTime = time;
};

const updateSelectedTimeLabel = (timeLabel) => {
  form.value.timeLabel = timeLabel;
};

const updateSelectedScheduleId = (scheduleId) => {
  form.value.scheduleId = scheduleId;
};

// --- open payment modal ---
const openPaymentModal = () => {
  if (!validateForm()) return;
  showPaymentModal.value = true;
};

// --- submit appointment without payment ---
const submitAppointment = () => {
  if (!validateForm()) return;

  const scheduleDatetime = `${form.value.date} ${form.value.dateTime}`;
  
  const appointmentForm = useForm({
    service_id: form.value.serviceId,
    schedule_datetime: scheduleDatetime,
    schedule_id: form.value.scheduleId,
  });

  appointmentForm.post(route('customer.appointment.store'), {
    onSuccess: () => {
      openModal('Appointment scheduled successfully!');
      setTimeout(() => {
        showModal.value = false;
        router.visit(route('customer.view'));
      }, 2000);
    },
    onError: (errors) => {
      console.error('Appointment errors:', errors);
      let errorMessage = 'An error occurred while scheduling the appointment.';
      
      if (errors.error) {
        errorMessage = errors.error;
      } else if (typeof errors === 'string') {
        errorMessage = errors;
      } else if (errors.message) {
        errorMessage = errors.message;
      } else if (Object.keys(errors).length > 0) {
        errorMessage = Object.values(errors)[0];
      }
      
      openModal(errorMessage);
    }
  });
};

// Form validation
const validateForm = () => {
  console.log('Validating form:', form.value);
  
  if (!form.value.service) {
    console.log('Validation failed: No service selected');
    openModal('Please select a dental service.');
    return false;
  }
  if (!form.value.date || !form.value.dateTime) {
    console.log('Validation failed: Date or time missing', {
      date: form.value.date,
      time: form.value.dateTime
    });
    openModal('Please choose an available date and time slot.');
    return false;
  }
  if (!form.value.scheduleId) {
    console.log('Validation failed: No schedule ID', {
      scheduleId: form.value.scheduleId
    });
    openModal('Please select a valid time slot.');
    return false;
  }
  
  console.log('Form validation passed');
  return true;
};

// ✅ handle PayMongo modal events
const handlePaymentSuccess = (paymentData) => {
  showPaymentModal.value = false;
  openModal('Payment successful! Your appointment has been confirmed.');
  setTimeout(() => {
    showModal.value = false;
    router.visit(route('customer.view'));
  }, 2000);
};

const handlePaymentCancelled = () => {
  showPaymentModal.value = false;
  openModal('Payment was cancelled. No appointment was created.');
};
</script>

<template>
  <Head title="Schedule Appointment" />
  <CustomerLayout>
    <div class="min-h-screen flex items-center justify-center py-10 px-4 sm:px-6 lg:px-8 font-rem">
      <div class="shadow-xl bg-[#EFEFEF]/20 rounded-2xl p-6 sm:p-8 md:p-10 w-full max-w-5xl">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-dark mb-8 sm:mb-10 uppercase text-center md:text-left">
          Schedule Appointment
        </h1>

        <div class="space-y-10">
          <!-- Personal Info -->
          <section class="px-2 sm:px-4 md:px-8">
            <h2 class="text-lg sm:text-xl font-bold text-dark mb-6">Personal Information</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 sm:gap-x-16 gap-y-4 sm:gap-y-6">
              <div>
                <p class="text-base sm:text-lg">
                  <span class="font-semibold">First Name:</span>
                  <span class="ml-2 break-all">{{ form.firstName }}</span>
                </p>
              </div>
              <div>
                <p class="text-base sm:text-lg">
                  <span class="font-semibold">Email:</span>
                  <span class="ml-2 break-all">{{ form.email }}</span>
                </p>
              </div>
              <div>
                <p class="text-base sm:text-lg">
                  <span class="font-semibold">Last Name:</span>
                  <span class="ml-2">{{ form.lastName }}</span>
                </p>
              </div>
              <div>
                <p class="text-base sm:text-lg">
                  <span class="font-semibold">Contact Number:</span>
                  <span class="ml-2">{{ form.contactNumber }}</span>
                </p>
              </div>
            </div>
          </section>

          <!-- Service -->
          <section class="px-4 py-10 sm:px-4 md:px-8">
            <h2 class="text-lg sm:text-xl font-bold text-dark mb-4">Dental Service</h2>
            <div class="relative w-full">
              <select
                v-model="form.service"
                @change="updateServiceSelection(form.service)"
                class="w-full px-4 py-3 border-2 border-dark rounded-lg focus:outline-none focus:ring-2 focus:ring-dark focus:border-transparent bg-white cursor-pointer text-gray-700 text-base appearance-none transition-all duration-200"
              >
                <option disabled value="">Select</option>
                <option
                  v-for="service in services"
                  :key="service.service_id"
                  :value="service.service_name"
                >
                  {{ service.service_name }}
                </option>
              </select>
            </div>
          </section>

          <!-- Date & Time -->
          <section class="px-2 sm:px-4 md:px-8">
            <h2 class="text-lg sm:text-xl font-bold text-dark mb-4">Date and Time</h2>
            <PrimaryButton
              @click="chooseSlots"
              class="bg-neutral hover:bg-dark text-sm px-6 sm:px-8 py-2 rounded-full transition-all duration-300 shadow-md"
            >
              Choose Available Slots
            </PrimaryButton>

            <!-- Show date and time -->
            <div v-if="form.date && form.timeLabel" class="mt-4 text-base text-gray-700">
              <span class="font-semibold">Selected:</span>
              <span class="ml-2">{{ form.date }} - {{ form.timeLabel }}</span>
            </div>
          </section>

          <!-- Payment Button-->
          <div class="px-2 sm:px-4 md:px-8">
            <PrimaryButton
              @click="openPaymentModal"
              class="bg-neutral hover:bg-dark text-sm px-8 sm:px-14 py-2 rounded-full transition-all duration-300 shadow-md"
            >
              Add Payment
            </PrimaryButton>
          </div>

          <!-- Submit -->
          <div class="flex justify-center sm:justify-end pt-4 sm:pt-6">
            <PrimaryButton
              @click="submitAppointment"
              class="bg-dark hover:bg-light text-white text-sm font-semibold px-8 py-2 rounded-full transition-all duration-300 shadow-md uppercase"
            >
              Submit
            </PrimaryButton>
          </div>
        </div>
      </div>
    </div>
  </CustomerLayout>

  <!-- Modals -->
  <Modal :show="showModal" @close="showModal = false">
    <div class="p-6 text-center">
      <p class="text-lg font-semibold text-gray-800">{{ modalMessage }}</p>
      <PrimaryButton @click="showModal = false" class="mt-6 bg-dark text-white px-6 py-2 rounded-full">
        OK
      </PrimaryButton>
    </div>
  </Modal>

  <!-- Date & Time Picker -->
  <DateTimeModal
    v-model="showSlotPicker"
    :selected-date="form.date"
    :selected-time="form.dateTime"
    :selected-time-label="form.timeLabel"
    :selected-schedule-id="form.scheduleId"
    @update:selectedDate="updateSelectedDate"
    @update:selectedTime="updateSelectedTime"
    @update:selectedTimeLabel="updateSelectedTimeLabel"
    @update:selectedScheduleId="updateSelectedScheduleId"
    @datetime-selected="handleDateTimeSelected"
  />

  <!-- Payment Modal -->
<PaymentModal
  v-model="showPaymentModal"
  :appointment-data="{
    service: form.service,
    serviceId: form.serviceId,
    date: form.date,
    time: form.timeLabel,
    scheduleDatetime: `${form.date} ${form.dateTime}`,
    scheduleId: form.scheduleId,
    customer: {
      firstName: form.firstName,
      lastName: form.lastName,
      email: form.email
    }
  }"
  @payment-success="handlePaymentSuccess"
  @payment-cancelled="handlePaymentCancelled"
/>
</template>
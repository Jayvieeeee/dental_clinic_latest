<script setup>
import { ref, computed } from 'vue';
import { Head, usePage, router } from "@inertiajs/vue3";
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
// Assuming these are your existing modals/components
import DateTimeModal from '@/Components/DateTimeModal.vue';
import PaymentModal from '@/Pages/Payment/PaymentModal.vue';
import Modal from '@/Components/Modal.vue';

const page = usePage();

const user = computed(() => page.props.user ?? {});
const services = computed(() => page.props.services ?? []);

// --- NEW STATE FOR SUCCESS MODAL ---
const showSuccessModal = ref(false);
const successAppointmentData = ref(null); // To store details for the success view
// ------------------------------------

// form data 
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

// update service selection to include serviceId
const updateServiceSelection = (serviceName) => {
    form.value.service = serviceName;
    const selectedService = services.value.find(
        (s) => s.service_name === serviceName
    );
    form.value.serviceId = selectedService ? selectedService.service_id : '';
};

// message modal
const openModal = (message) => {
    modalMessage.value = message;
    showModal.value = true;
};

// slot picker 
const chooseSlots = () => {
    if (!form.value.service) {
        openModal('Please select a dental service first.');
        return;
    }
    showSlotPicker.value = true;
};

const handleDateTimeSelected = (data) => {
    // console.log('DateTime Selected Data:', data);

    form.value.date = data.date || '';
    form.value.dateTime = data.time || ''; 	
    form.value.timeLabel = data.timeLabel || ''; 
    form.value.scheduleId = data.scheduleId || '';

    showSlotPicker.value = false; // Close the slot picker once selected
};

// update handlers for v-model 
const updateSelectedDate = (date) => {
    form.value.date = date;
};

const updateSelectedScheduleId = (scheduleId) => {
    form.value.scheduleId = scheduleId;
};

// payment modal
const openPaymentModal = () => {
    if (!validateForm()) return;
    showPaymentModal.value = true;
};

// form validation
const validateForm = () => {
    // console.log('Validating form:', form.value);
    
    if (!form.value.service) {
        openModal('Please select a dental service.');
        return false;
    }
    if (!form.value.date) {
        openModal('Please choose an available date.');
        return false;
    }
    if (!form.value.scheduleId) {
        openModal('Please select a valid time slot.');
        return false;
    }
    
    return true;
};

// Date/Time formatting helper for the modal
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    })
}

const formatDateTime = (dateTimeString) => {
    if (!dateTimeString) return '';
    return new Date(dateTimeString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

// --- UPDATED HANDLER ---
const handlePaymentSuccess = (paymentData) => {
    showPaymentModal.value = false;

    // 1. Prepare data for the success modal
    const selectedService = services.value.find(s => s.service_id === form.value.serviceId);

    successAppointmentData.value = {
        // You should pass the actual amount paid from the paymentData, 
        // but using a hardcoded placeholder for now based on your previous code.
        amount_paid: '₱300.00', 
        payment_method: paymentData.method || 'Online Payment', 
        transaction_reference: paymentData.reference || 'N/A',
        paid_at: new Date().toISOString(), // Use current time or paymentData.paid_at
        
        // Appointment details
        service_name: selectedService?.service_name || form.value.service,
        appointment_date: form.value.date,
        time_slot: form.value.timeLabel
    };

    // 2. Show the success modal
    showSuccessModal.value = true;
    
    // 3. Clear the form after a successful booking (optional, but good practice)
    form.value.service = '';
    form.value.serviceId = '';
    form.value.date = '';
    form.value.dateTime = '';
    form.value.timeLabel = '';
    form.value.scheduleId = '';
};
// -----------------------

const handlePaymentCancelled = () => {
    showPaymentModal.value = false;
    openModal('Payment was cancelled. Please try again.');
};

const goToAppointments = () => {
    showSuccessModal.value = false;
    router.visit(route('customer.view')); // Assuming 'customer.view' is your appointments list
}

const goHome = () => {
    showSuccessModal.value = false;
    router.visit(route('customer.home')); // Assuming 'customer.home' is your homepage
}
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

                    <section class="px-4 py-10 sm:px-4 md:px-8">
                        <h2 class="text-lg sm:text-xl font-bold text-dark mb-4">Dental Service</h2>
                        <div class="relative w-full">
                            <select
                                v-model="form.service"
                                @change="updateServiceSelection(form.service)"
                                class="w-full px-4 py-3 border-2 border-dark rounded-lg focus:outline-none focus:ring-2 focus:ring-dark focus:border-transparent bg-white cursor-pointer text-gray-700 text-base appearance-none transition-all duration-200">
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

                    <section class="px-2 sm:px-4 md:px-8">
                        <h2 class="text-lg sm:text-xl font-bold text-dark mb-4">Date and Time</h2>
                        <PrimaryButton
                            @click="chooseSlots"
                            class="bg-neutral hover:bg-dark text-sm px-6 sm:px-8 py-2 rounded-full transition-all duration-300 shadow-md">
                            Choose Available Slots
                        </PrimaryButton>

                        <div v-if="form.date && form.timeLabel" class="mt-4 text-base text-gray-700">
                            <span class="font-semibold">Selected:</span>
                            <span class="ml-2">{{ formatDate(form.date) }} - {{ form.timeLabel }}</span>
                        </div>
                    </section>

                    <div class="flex justify-center sm:justify-end pt-4 sm:pt-6">
                        <PrimaryButton
                            @click="openPaymentModal"
                            class="bg-neutral hover:bg-dark text-sm px-8 sm:px-14 py-2 rounded-full transition-all duration-300 shadow-md">
                            Proceed to Payment
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6 text-center">
                <p class="text-lg font-semibold text-gray-800">{{ modalMessage }}</p>
                <PrimaryButton @click="showModal = false" class="mt-6 bg-dark text-white px-6 py-2 rounded-full">
                    OK
                </PrimaryButton>
            </div>
        </Modal>

        <DateTimeModal
            :modelValue="showSlotPicker"
            :selectedDate="form.date"
            :selectedScheduleId="form.scheduleId"
            @update:modelValue="showSlotPicker = $event"
            @update:selectedDate="updateSelectedDate"
            @update:selectedScheduleId="updateSelectedScheduleId"
            @datetime-selected="handleDateTimeSelected"
        /> 	

        <PaymentModal
            v-model="showPaymentModal"
            :appointment-data="{
                service: form.service,
                serviceId: form.serviceId,
                date: form.date,
                time: form.timeLabel,
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

        <Modal :show="showSuccessModal" @close="showSuccessModal = false" max-width="sm">
            <div class="p-6">
                <button
                    @click="showSuccessModal = false"
                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                
                <div class="text-center pt-4">
                    <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100">
                        <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    
                    <h2 class="mt-4 text-2xl font-extrabold text-gray-900">
                        Payment Successful!
                    </h2>
                    
                    <p class="mt-1 text-sm text-gray-600">
                        Thank you for your payment. Your appointment has been confirmed.
                    </p>
                </div>

                <div class="bg-white rounded-lg p-5 mt-6 border border-gray-100">
                    <h3 class="text-md font-medium text-gray-900 mb-3">Appointment Details</h3>
                    
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Service:</span>
                            <span class="font-medium">{{ successAppointmentData?.service_name || 'N/A' }}</span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Date:</span>
                            <span class="font-medium">{{ formatDate(successAppointmentData?.appointment_date) }}</span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Time:</span>
                            <span class="font-medium">{{ successAppointmentData?.time_slot || 'N/A' }}</span>
                        </div>
                        
                        <div class="flex justify-between border-t pt-3 mt-3">
                            <span class="text-gray-600 font-semibold">Amount Paid:</span>
                            <span class="font-bold text-lg text-green-600">{{ successAppointmentData?.amount_paid || 'N/A' }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="successAppointmentData" class="bg-gray-50 rounded-lg p-4 mt-4">
                    <h4 class="text-xs font-medium text-gray-700 mb-2">Payment Information</h4>
                    <div class="text-xs text-gray-600 space-y-1">
                        <div>Method: {{ successAppointmentData.payment_method }}</div>
                        <div>Reference: {{ successAppointmentData.transaction_reference }}</div>
                        <div>Paid at: {{ formatDateTime(successAppointmentData.paid_at) }}</div>
                    </div>
                </div>
                
                <div class="mt-6 flex flex-col space-y-2">
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

            </div>
        </Modal>
    </CustomerLayout>
</template>
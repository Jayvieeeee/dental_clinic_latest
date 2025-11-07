<script setup>
import { ref, computed, watchEffect } from 'vue'
import { Head, usePage, router } from "@inertiajs/vue3"
import CustomerLayout from '@/Layouts/CustomerLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import DateTimeModal from '@/Components/DateTimeModal.vue'
import { useToast } from "vue-toastification"

const { props } = usePage()
const toast = useToast()

const appointments = ref(props.appointments || [])
const filterStatus = ref('All')
const showModal = ref(false)
const showCancelConfirm = ref(false)
const selected = ref(null)
const showReschedule = ref(false)
const selectedDate = ref("")
const selectedScheduleId = ref("")

// 🔄 Keep appointments reactive with Inertia updates
watchEffect(() => {
  appointments.value = usePage().props.appointments || []
})

const filteredAppointments = computed(() => {
  if (filterStatus.value === 'All') return appointments.value
  return appointments.value.filter(apt => apt.status === filterStatus.value)
})

const getStatusColor = (status) => {
  switch (status) {
    case 'Completed': return 'text-green-600'
    case 'Rescheduled': return 'text-blue-600'
    case 'Cancelled': return 'text-red-600'
    case 'Scheduled': return 'text-gray-700'
    case 'Confirmed': return 'text-teal-700'
    default: return 'text-gray-700'
  }
}

// 🗓️ NEW: Date Formatting Function for full month name
const formatAppointmentDate = (dateString) => {
  if (!dateString) return ''
  try {
    // Note: The date string from the backend must be in a format that new Date() can parse.
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long', // This option formats the month as a full name (e.g., January)
      day: 'numeric',
    })
  } catch (e) {
    console.error("Date formatting failed:", e)
    return dateString // Return original in case of error
  }
}

// 🔍 View details
const openDetails = (appointment) => {
  selected.value = appointment
  showModal.value = true
}

// 🔄 Reschedule
const openReschedule = () => {
  if (!selected.value) return

  // ❌ Prevent rescheduling of cancelled appointments
  if (selected.value.status === 'Cancelled') {
    toast.error("Cancelled appointments cannot be rescheduled.")
    return
  }

  selectedDate.value = selected.value.date_raw || ""
  selectedScheduleId.value = selected.value.schedule_id || ""
  showModal.value = false
  showReschedule.value = true
}

const handleDateTimeSelected = ({ date, scheduleId }) => {
  selectedDate.value = date
  selectedScheduleId.value = scheduleId
  submitReschedule()
}

const submitReschedule = () => {
  if (!selected.value || !selectedDate.value || !selectedScheduleId.value) {
    toast.error("Please select a date and schedule.")
    return
  }

  router.post(
    route("customer.appointment.reschedule", selected.value.id),
    {
      new_appointment_date: selectedDate.value,
      new_schedule_id: selectedScheduleId.value,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        showReschedule.value = false
        selectedDate.value = ""
        selectedScheduleId.value = ""
        toast.success("Appointment successfully rescheduled!")

        router.reload({
          only: ["appointments"],
          preserveScroll: true,
          preserveState: false,
          onFinish: () => toast.info("Appointments updated."),
        })
      },
      onError: () => toast.error("Reschedule failed. Please try again."),
    }
  )
}

// ❌ Cancel
const confirmCancel = () => {
  if (!selected.value) return
  showCancelConfirm.value = true
  showModal.value = false
}

const cancelAppointment = () => {
  if (!selected.value) return

  router.post(
    route("customer.appointment.cancel", selected.value.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Appointment cancelled successfully.")
        showCancelConfirm.value = false

        router.reload({
          only: ["appointments"],
          preserveScroll: true,
          preserveState: false,
        })
      },
      onError: () => {
        toast.error("Failed to cancel the appointment.")
        showCancelConfirm.value = false
      },
    }
  )
}
</script>

<template>
  <Head title="View Appointment" />
  <CustomerLayout>
    <div class="min-h-screen flex items-center justify-center py-10 px-6 font-rem">
      <div class="shadow-xl bg-[#EFEFEF]/20 rounded-2xl p-10 w-full max-w-6xl">
        <h1 class="text-3xl font-extrabold text-dark mb-10 uppercase">
          Appointments
        </h1>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
          <div class="w-full md:w-64">
            <select 
              v-model="filterStatus"
              class="w-full px-4 py-2 border-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent appearance-none bg-white cursor-pointer text-gray-700 text-base"
              style="background-image: url('/icons/arrow.svg'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 2.5em; padding-right: 3rem;">
              <option value="All">All</option>
              <option value="Completed">Completed</option>
              <option value="Confirmed">Confirmed</option>
              <option value="Rescheduled">Rescheduled</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>

          <p class="text-sm text-gray-700 text-right">
            Rescheduling/Cancellation is only allowed at least 12<br class="hidden md:block" />
            hours before the appointment.
          </p>
        </div>

        <div class="overflow-x-auto rounded-2xl shadow-lg">
          <table class="w-full border-collapse text-center">
            <thead>
              <tr class="bg-neutral text-white uppercase">
                <th class="py-5 px-6 border-r">Procedure</th>
                <th class="py-5 px-6 border-r">Date & Time</th>
                <th class="py-5 px-6 border-r">Payment Status</th>
                <th class="py-5 px-6 border-r">Status</th>
                <th class="py-5 px-6">Action</th>
              </tr>
            </thead>
            <tbody class="bg-white">
              <tr 
                v-for="appointment in filteredAppointments" 
                :key="appointment.id"
                class="border-b hover:bg-gray-50 transition-colors font-medium">
                
                <td class="py-5 px-6 text-gray-800 border-r-2 border-dark">{{ appointment.procedure }}</td>
                <td class="py-5 px-6 text-gray-800 border-r-2 border-dark">{{ formatAppointmentDate(appointment.date) }} | {{ appointment.time }}</td>

                <td class="py-5 px-6 border-r-2 border-dark font-semibold"
                    :class="{
                      'text-green-600': appointment.payment_status === 'Paid',
                      'text-yellow-600': appointment.payment_status === 'Pending',
                      'text-red-600': appointment.payment_status === 'Unpaid',
                    }">
                  {{ appointment.payment_status }}
                </td>

                <td class="py-5 px-6 border-r-2 border-dark font-semibold" :class="getStatusColor(appointment.status)">
                  {{ appointment.status }}
                </td>

                <td class="py-5 px-6">
                  <button 
                    @click="openDetails(appointment)"
                    class="text-gray-800 underline hover:text-teal-600 transition-colors font-medium">
                    View Details
                  </button>
                </td>
              </tr>

              <tr v-if="filteredAppointments.length === 0">
                <td colspan="5" class="py-8 text-center text-gray-500">No appointments found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </CustomerLayout>

  <AppModal :show="showModal" @close="showModal = false">
    <template #default>
      <p><strong>Procedure:</strong> {{ selected?.procedure }}</p>
      <p><strong>Date & Time:</strong> {{ formatAppointmentDate(selected?.date) }} | {{ selected?.time }}</p>
      <p><strong>Payment Status:</strong> {{ selected?.payment_status }}</p>
      <p><strong>Status:</strong> {{ selected?.status }}</p>
    </template>

    <template #actions>
      <div class="flex justify-center gap-8 pb-6 text-lg font-semibold">
        <button
          class="text-dark hover:underline disabled:opacity-40"
          :disabled="selected?.status === 'Cancelled'"
          @click="openReschedule">
          Reschedule
        </button>

        <button
          class="text-dark hover:underline"
          @click="confirmCancel">
          Cancel
        </button>
      </div>
    </template>
  </AppModal>

  <DateTimeModal
    v-model="showReschedule"
    :selectedDate="selectedDate"
    :selectedScheduleId="selectedScheduleId"
    :isReschedule="true"
    @datetime-selected="handleDateTimeSelected"
  />

  <AppModal :show="showCancelConfirm" @close="showCancelConfirm = false">
    <template #default>
      <div class="flex flex-col justify-center items-center text-center p-4 min-h-[130px]">
        <p class="text-xl">
          Are you sure you want to cancel this appointment?
          <span class="text-sm text-red-500 font-medium">This action cannot be undone.</span>
        </p>
      </div>
    </template>

    <template #actions>
      <div class="flex justify-center items-center gap-8 pb-12">
        <button
          @click="showCancelConfirm = false"
          class="px-5 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium transition">
          No, Keep It
        </button>
        <button
          @click="cancelAppointment"
          class="px-5 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium transition">
          Yes, Cancel It
        </button>
      </div>
    </template>
  </AppModal>
</template> 
<script setup>
import { ref, computed } from 'vue';
import { Head } from "@inertiajs/vue3"
import CustomerLayout from '@/Layouts/CustomerLayout.vue';

const filterStatus = ref('All');

const appointments = ref([
  {
    id: 1,
    procedure: 'Mouth Examination',
    date: '06-01-2025',
    time: '10:00 a.m - 1:00 p.m',
    status: 'Completed'
  },
  {
    id: 2,
    procedure: 'Oral Prophylaxis (Cleaning)',
    date: '07-12-2025',
    time: '10:00 a.m - 1:00 p.m',
    status: 'Completed'
  },
  {
    id: 3,
    procedure: 'Digital Panoramic x-ray',
    date: '08-13-2025',
    time: '3:00 p.m - 5:00 p.m',
    status: 'Completed'
  },
  {
    id: 4,
    procedure: 'Wisdom Tooth Removal',
    date: '08-23-2025',
    time: '1:00 p.m - 3:00 p.m',
    status: 'Completed'
  },
  {
    id: 5,
    procedure: 'Tooth Restoration (Pasta)',
    date: '09-07-2025',
    time: '1:00 p.m - 3:00 p.m',
    status: 'Rescheduled'
  },
  {
    id: 6,
    procedure: 'Teeth Whitening',
    date: '10-17-2025',
    time: '3:00 p.m - 5:00 p.m',
    status: 'Cancelled'
  },
  {
    id: 7,
    procedure: 'Oral Prophylaxis (Cleaning)',
    date: '10-10-2025',
    time: '3:00 p.m - 5:00 p.m',
    status: 'Completed'
  },
  {
    id: 8,
    procedure: 'Mouth Examination',
    date: '11-02-2025',
    time: '10:00 a.m - 1:00 p.m',
    status: 'Scheduled'
  }
]);

const filteredAppointments = computed(() => {
  if (filterStatus.value === 'All') {
    return appointments.value;
  }
  return appointments.value.filter(apt => apt.status === filterStatus.value);
});

const getStatusColor = (status) => {
  switch(status) {
    case 'Completed':
      return 'text-green-600';
    case 'Rescheduled':
      return 'text-blue-600';
    case 'Cancelled':
      return 'text-red-600';
    case 'Scheduled':
      return 'text-gray-700';
    default:
      return 'text-gray-700';
  }
};

const viewDetails = (appointmentId) => {
  console.log('View details for appointment:', appointmentId);
  // Navigate to details page or show modal
};
</script>

<template>
    <Head title="View Appointment" />
  <CustomerLayout>
    <div class="min-h-screen flex items-center justify-center py-10 px-6 font-rem">
      <div class="shadow-xl bg-[#EFEFEF]/20 rounded-2xl p-10 w-full max-w-6xl">
        
        <h1 class="text-3xl font-extrabold text-dark mb-10 uppercase">
          APPOINTMENTS
        </h1>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
          <!-- Filter Dropdown -->
          <div class="w-full md:w-64">
            <select 
              v-model="filterStatus"
              class="w-full px-4 py-2 border-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent appearance-none bg-white cursor-pointer text-gray-700 text-base"
              style="background-image: url('/icons/arrow.svg'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 2.5em; padding-right: 3rem;"
            >
              <option value="All">All</option>
              <option value="Completed">Completed</option>
              <option value="Scheduled">Scheduled</option>
              <option value="Rescheduled">Rescheduled</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>

          <!-- Info Text -->
          <p class="text-sm text-gray-700 text-right">
            Rescheduling/Cancellation is only allowed at least 12<br class="hidden md:block" />
            hours before the appointment.
          </p>
        </div>

        <!-- Appointments Table -->
        <div class="overflow-x-auto rounded-2xl shadow-lg">
          <table class="w-full border-collapse text-center">
            <!-- Table Header -->
            <thead>
              <tr class="bg-neutral text-white uppercase">
                <th class="py-4 px-6 text-base font-semibold uppercase border-r border-white/30">Procedure Type</th>
                <th class="py-4 px-6 text-base font-semibold uppercase border-r border-white/30">Date and Time</th>
                <th class="py-4 px-6 text-base font-semibold uppercase border-r border-white/30">Status</th>
                <th class="py-4 px-6 text-base font-semibold uppercase">Action</th>
              </tr>
            </thead>

            <!-- Table Body -->
            <tbody class="bg-white">
              <tr 
                v-for="(appointment, index) in filteredAppointments" 
                :key="appointment.id"
                class="border-b-2 border-neutral hover:bg-gray-50 transition-colors font-medium"
              >
                <td class="py-4 px-6 text-gray-800 border-r-2 border-neutral">{{ appointment.procedure }}</td>
                <td class="py-4 px-6 text-gray-800 border-r-2 border-neutral">{{ appointment.date }} | {{ appointment.time }}</td>
                <td class="py-4 px-6 font-semibold border-r-2 border-neutral" :class="getStatusColor(appointment.status)">
                  {{ appointment.status }}
                </td>
                <td class="py-4 px-6">
                  <button 
                    @click="viewDetails(appointment.id)"
                    class="text-gray-800 underline hover:text-teal-600 transition-colors font-medium"
                  >
                    View Details
                  </button>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="filteredAppointments.length === 0">
                <td colspan="4" class="py-8 px-6 text-center text-gray-500">
                  No appointments found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </CustomerLayout>
</template>
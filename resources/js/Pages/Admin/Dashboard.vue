<script setup>
import { ref, computed, onMounted } from 'vue'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

// Stats data
const totalAppointments = ref(10)
const patientsRegistered = ref(34)
const pendingAppointments = ref(25)

// Weekly dropdown
const selectedPeriod = ref('Weekly')
const periods = ['Daily', 'Weekly', 'Monthly', 'Yearly']

// Chart refs
const barChartRef = ref(null)
const donutChartRef = ref(null)
let barChartInstance = null
let donutChartInstance = null

// Appointments chart data
const appointmentsData = ref([
  { day: 'Mon', value: 0 },
  { day: 'Tue', value: 11 },
  { day: 'Wed', value: 10 },
  { day: 'Thurs', value: 10 },
  { day: 'Fri', value: 25 },
  { day: 'Sat', value: 29 },
  { day: 'Sun', value: 0 }
])

const totalValue = computed(() => {
  return appointmentsData.value.reduce((sum, item) => sum + item.value, 0)
})

// Appointment Status data
const appointmentStatus = ref([
  { label: 'Completed', percentage: 37, color: '#22c55e' },
  { label: 'Scheduled', percentage: 35, color: '#14b8a6' },
  { label: 'Rescheduled', percentage: 18, color: '#3b82f6' },
  { label: 'Cancelled', percentage: 6, color: '#ef4444' },
  { label: 'No show', percentage: 4, color: '#d1d5db' }
])

// Upcoming appointments
const upcomingAppointments = ref([
  {
    id: 1,
    name: 'Alex Ramos',
    procedure: 'Tooth Extraction / Root Canal Treatment/ Wisdom Tooth Removal',
    date: '10-15-2025',
    time: '1:00 p.m - 3:00 p.m'
  },
  {
    id: 2,
    name: 'Gregorio Garcia',
    procedure: 'Mouth Examination / Oral Prophylaxis (Cleaning)',
    date: '10-16-2025',
    time: '10:00 a.m - 1:00 p.m'
  },
  {
    id: 3,
    name: 'Chloe Rivera',
    procedure: 'Tooth Restoration (Pasta)',
    date: '10-16-2025',
    time: '1:00 p.m - 3:00 p.m'
  }
])

// Initialize Bar Chart
const initBarChart = () => {
  if (!barChartRef.value) return
  
  if (barChartInstance) {
    barChartInstance.destroy()
  }

  const ctx = barChartRef.value.getContext('2d')
  barChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: appointmentsData.value.map(d => d.day),
      datasets: [{
        label: 'Appointments',
        data: appointmentsData.value.map(d => d.value),
        backgroundColor: '#14b8a6',
        borderRadius: 8,
        barThickness: 40
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: '#1f2937',
          padding: 12,
          titleColor: '#fff',
          bodyColor: '#fff',
          cornerRadius: 8
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 60,
          ticks: {
            stepSize: 10,
            color: '#6b7280',
            font: {
              size: 12
            }
          },
          grid: {
            color: '#e5e7eb',
            drawBorder: false
          }
        },
        x: {
          ticks: {
            color: '#6b7280',
            font: {
              size: 12
            }
          },
          grid: {
            display: false
          }
        }
      }
    }
  })
}

// Initialize Donut Chart
const initDonutChart = () => {
  if (!donutChartRef.value) return
  
  if (donutChartInstance) {
    donutChartInstance.destroy()
  }

  const ctx = donutChartRef.value.getContext('2d')
  donutChartInstance = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: appointmentStatus.value.map(s => s.label),
      datasets: [{
        data: appointmentStatus.value.map(s => s.percentage),
        backgroundColor: appointmentStatus.value.map(s => s.color),
        borderWidth: 0,
        cutout: '70%'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: '#1f2937',
          padding: 12,
          titleColor: '#fff',
          bodyColor: '#fff',
          cornerRadius: 8,
          callbacks: {
            label: function(context) {
              return context.label + ': ' + context.parsed + '%'
            }
          }
        }
      }
    }
  })
}

onMounted(() => {
  initBarChart()
  initDonutChart()
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-4">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-4 flex justify-between items-center">
        <h1 class="text-3xl font-bold text-teal-700">HOME</h1>
        <div class="text-right">
          <p class="text-xs text-teal-600 font-medium">Sunday</p>
          <p class="text-sm font-semibold text-gray-800">October 12, 2025</p>
        </div>
      </div>

      <!-- Top Section -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        <!-- Left Stats Cards -->
        <div class="space-y-3">
          <!-- Total Appointments Card -->
          <div class="bg-white rounded-xl shadow-lg p-4 hover:shadow-xl transition-shadow">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 bg-gradient-to-br from-teal-100 to-teal-200 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-teal-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <p class="text-3xl font-bold text-gray-900">{{ totalAppointments }}</p>
                <p class="text-xs text-gray-600 mt-1">Total Appointments (Today)</p>
              </div>
            </div>
          </div>

          <!-- Patients Registered Card -->
          <div class="bg-white rounded-xl shadow-lg p-4 hover:shadow-xl transition-shadow">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 bg-gradient-to-br from-teal-100 to-teal-200 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-teal-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
              </div>
              <div>
                <p class="text-3xl font-bold text-gray-900">{{ patientsRegistered }}</p>
                <p class="text-xs text-gray-600 mt-1">Patients Registered</p>
              </div>
            </div>
          </div>

          <!-- Pending Appointments Card -->
          <div class="bg-white rounded-xl shadow-lg p-4 hover:shadow-xl transition-shadow">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 bg-gradient-to-br from-teal-100 to-teal-200 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-teal-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <p class="text-3xl font-bold text-gray-900">{{ pendingAppointments }}</p>
                <p class="text-xs text-gray-600 mt-1">Pending Appointments</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Appointments Chart -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-4">
          <div class="flex justify-between items-center mb-4">
            <div>
              <h2 class="text-lg font-bold text-gray-900">Appointments</h2>
              <p class="text-xs text-gray-600 mt-1">Total Value: <span class="font-bold text-teal-700">{{ totalValue }}</span></p>
            </div>
            <select
              v-model="selectedPeriod"
              class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white cursor-pointer"
            >
              <option v-for="period in periods" :key="period" :value="period">
                {{ period }}
              </option>
            </select>
          </div>

          <!-- Chart -->
          <div class="relative h-64">
            <canvas ref="barChartRef"></canvas>
          </div>
        </div>
      </div>

      <!-- Bottom Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Appointment Status -->
        <div class="bg-white rounded-xl shadow-lg p-4">
          <h2 class="text-lg font-bold text-gray-900 mb-4">Appointment Status</h2>
          
          <div class="flex items-center gap-6">
            <!-- Legend -->
            <div class="flex-1 space-y-2">
              <div
                v-for="status in appointmentStatus"
                :key="status.label"
                class="flex items-center justify-between"
              >
                <div class="flex items-center gap-2">
                  <div 
                    class="w-2.5 h-2.5 rounded-full"
                    :style="{ backgroundColor: status.color }"
                  />
                  <span class="text-xs text-gray-700 font-medium">{{ status.label }}</span>
                </div>
                <span class="text-xs font-bold text-gray-900">{{ status.percentage }}%</span>
              </div>
            </div>

            <!-- Donut Chart -->
            <div class="relative w-32 h-32">
              <canvas ref="donutChartRef"></canvas>
            </div>
          </div>
        </div>

        <!-- Upcoming Scheduled Appointments -->
        <div class="bg-white rounded-xl shadow-lg p-4">
          <h2 class="text-lg font-bold text-gray-900 mb-4">Upcoming Scheduled Appointments</h2>
          
          <div class="overflow-x-auto rounded-lg">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-teal-600 to-teal-700 text-white">
                  <th class="px-3 py-2 text-left text-xs font-semibold">Name</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold">Procedure Type</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold">Date and Time</th>
                </tr>
              </thead>
              <tbody class="bg-teal-50">
                <tr
                  v-for="appointment in upcomingAppointments"
                  :key="appointment.id"
                  class="border-b border-teal-100 hover:bg-teal-100 transition-colors"
                >
                  <td class="px-3 py-2 text-xs text-gray-900 font-medium">{{ appointment.name }}</td>
                  <td class="px-3 py-2 text-xs text-gray-700">{{ appointment.procedure }}</td>
                  <td class="px-3 py-2 text-xs text-gray-700">
                    <div>{{ appointment.date }}</div>
                    <div>{{ appointment.time }}</div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom select styling */
select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.5rem center;
  background-size: 1.5em 1.5em;
  padding-right: 2.5rem;
  appearance: none;
}
</style>
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { CalendarIcon, UserPlusIcon, ClockIcon } from '@heroicons/vue/24/outline'

// Props from controller
const props = defineProps({
    stats: Object,
    appointmentsData: Array,
    appointmentStatus: Array,
    upcomingAppointments: Array,
    currentDate: Object
})

// Stats data from backend
const totalAppointments = ref(props.stats.totalAppointments || 0)
const patientsRegistered = ref(props.stats.patientsRegistered || 0)
const pendingAppointments = ref(props.stats.pendingAppointments || 0)

// Weekly dropdown
const selectedPeriod = ref('Weekly')
const periods = ['Daily', 'Weekly', 'Monthly', 'Yearly']

// Appointments chart data from backend
const appointmentsData = ref(props.appointmentsData || [])
const appointmentStatus = ref(props.appointmentStatus || [])
const upcomingAppointments = ref(props.upcomingAppointments || [])

const totalValue = computed(() => {
    return appointmentsData.value.reduce((sum, item) => sum + item.value, 0)
})

const maxValue = computed(() => {
    const values = appointmentsData.value.map(item => item.value)
    return Math.max(...values, 1) // Ensure at least 1 to avoid division by zero
})

// Calculate donut chart segments
const calculateDonutSegments = () => {
    let currentAngle = -90 // Start from top
    return appointmentStatus.value.map(status => {
        const angle = (status.percentage / 100) * 360
        const segment = {
            ...status,
            startAngle: currentAngle,
            endAngle: currentAngle + angle
        }
        currentAngle += angle
        return segment
    })
}

const donutSegments = computed(() => calculateDonutSegments())

// Helper function to calculate bar height percentage
const getBarHeight = (value) => {
    if (maxValue.value === 0) return 0
    return (value / maxValue.value) * 100
}

// Fetch chart data when period changes
const fetchChartData = async (period) => {
    try {
        const response = await fetch(`/admin/dashboard/chart-data?period=${period}`)
        const data = await response.json()
        appointmentsData.value = data
    } catch (error) {
        console.error('Failed to fetch chart data:', error)
    }
}

// Watch for period changes
watch(selectedPeriod, (newPeriod) => {
    fetchChartData(newPeriod)
})

// Initialize with current date from backend
const currentDay = ref(props.currentDate.dayName || 'Sunday')
const currentFullDate = ref(props.currentDate.fullDate || 'October 12, 2025')
</script>

<template>
    <AdminLayout>
        <div class="min-h-screen bg-gray-100 p-8">
            <div class="max-w-7xl mx-auto">

                <!-- Top Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Left Stats Cards -->
                    <div class="space-y-4">
                        <!-- Total Appointments Card -->
                        <div class="bg-white rounded-2xl shadow-md p-6">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-teal-100 rounded-xl flex items-center justify-center">
                                    <CalendarIcon class="w-8 h-8 text-teal-700" />
                                </div>
                                <div>
                                    <p class="text-4xl font-bold text-gray-900">{{ totalAppointments }}</p>
                                    <p class="text-sm text-gray-600 mt-1">Total Appointments (Today)</p>
                                </div>
                            </div>
                        </div>

                        <!-- Patients Registered Card -->
                        <div class="bg-white rounded-2xl shadow-md p-6">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-teal-100 rounded-xl flex items-center justify-center">
                                    <UserPlusIcon class="w-8 h-8 text-teal-700" />
                                </div>
                                <div>
                                    <p class="text-4xl font-bold text-gray-900">{{ patientsRegistered }}</p>
                                    <p class="text-sm text-gray-600 mt-1">Patients Registered</p>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Appointments Card -->
                        <div class="bg-white rounded-2xl shadow-md p-6">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-teal-100 rounded-xl flex items-center justify-center">
                                    <ClockIcon class="w-8 h-8 text-teal-700" />
                                </div>
                                <div>
                                    <p class="text-4xl font-bold text-gray-900">{{ pendingAppointments }}</p>
                                    <p class="text-sm text-gray-600 mt-1">Pending Appointments</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appointments Chart -->
                    <div class="lg:col-span-2 bg-white rounded-2xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Appointments</h2>
                            <select
                                v-model="selectedPeriod"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                            >
                                <option v-for="period in periods" :key="period" :value="period">
                                    {{ period }}
                                </option>
                            </select>
                        </div>

                        <!-- Chart -->
                        <div class="relative h-80">
                            <!-- Y-axis labels -->
                            <div class="absolute left-0 top-0 bottom-8 flex flex-col justify-between text-xs text-gray-600 w-8">
                                <span>{{ Math.ceil(maxValue / 6) * 6 }}</span>
                                <span>{{ Math.ceil(maxValue / 6) * 5 }}</span>
                                <span>{{ Math.ceil(maxValue / 6) * 4 }}</span>
                                <span>{{ Math.ceil(maxValue / 6) * 3 }}</span>
                                <span>{{ Math.ceil(maxValue / 6) * 2 }}</span>
                                <span>{{ Math.ceil(maxValue / 6) * 1 }}</span>
                                <span>0</span>
                            </div>

                            <!-- Chart area -->
                            <div class="ml-12 h-full flex items-end justify-around gap-2 border-b border-l border-gray-300 pb-8">
                                <div
                                    v-for="item in appointmentsData"
                                    :key="item.day"
                                    class="flex-1 flex flex-col items-center"
                                >
                                    <!-- Bar -->
                                    <div class="relative w-full flex items-end justify-center" style="height: 280px;">
                                        <div
                                            v-if="item.value > 0"
                                            class="w-full bg-teal-600 rounded-t-lg relative flex items-start justify-center pt-2"
                                            :style="{ height: `${getBarHeight(item.value)}%` }"
                                        >
                                            <span class="text-xs font-semibold text-white">{{ item.value }}</span>
                                        </div>
                                    </div>
                                    <!-- Day label -->
                                    <span class="text-xs text-gray-600 mt-2">{{ item.day || item.hour || item.week || item.month }}</span>
                                </div>
                            </div>

                            <!-- Total Value -->
                            <div class="absolute left-32 top-1/2 -translate-y-1/2 text-center">
                                <p class="text-sm text-gray-600">Total Value</p>
                                <p class="text-3xl font-bold text-gray-900">{{ totalValue }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Appointment Status -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">Appointment Status</h2>
                        
                        <div class="flex items-center gap-8">
                            <!-- Legend -->
                            <div class="flex-1 space-y-3">
                                <div
                                    v-for="status in appointmentStatus"
                                    :key="status.label"
                                    class="flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2">
                                        <div :class="['w-3 h-3 rounded-full', status.color]"></div>
                                        <span class="text-sm text-gray-700">{{ status.label }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">{{ status.percentage }}%</span>
                                </div>
                            </div>

                            <!-- Donut Chart -->
                            <div class="relative w-48 h-48">
                                <svg viewBox="0 0 100 100" class="transform -rotate-90">
                                    <circle
                                        cx="50"
                                        cy="50"
                                        r="35"
                                        fill="none"
                                        stroke="#e5e7eb"
                                        stroke-width="15"
                                    />
                                    <circle
                                        v-for="(segment, index) in donutSegments"
                                        :key="index"
                                        cx="50"
                                        cy="50"
                                        r="35"
                                        fill="none"
                                        :stroke="segment.color.replace('bg-', '')"
                                        stroke-width="15"
                                        :stroke-dasharray="`${segment.percentage * 2.2} ${220 - segment.percentage * 2.2}`"
                                        :stroke-dashoffset="220 - (segment.startAngle + 90) * 2.2 / 360"
                                        class="transition-all duration-300"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Scheduled Appointments -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">Upcoming Scheduled Appointments</h2>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-teal-600 text-white">
                                        <th class="px-4 py-3 text-left text-sm font-semibold">Name</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold">Procedure Type</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold">Date and Time</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-teal-50">
                                    <tr
                                        v-for="appointment in upcomingAppointments"
                                        :key="appointment.id"
                                        class="border-b border-teal-200"
                                    >
                                        <td class="px-4 py-4 text-sm text-gray-900">{{ appointment.name }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-700">{{ appointment.procedure }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-700 whitespace-pre-line">{{ appointment.dateTime }}</td>
                                    </tr>
                                    <tr v-if="upcomingAppointments.length === 0">
                                        <td colspan="3" class="px-4 py-4 text-sm text-gray-500 text-center">
                                            No upcoming appointments
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
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
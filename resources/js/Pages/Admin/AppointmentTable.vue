<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import { ChevronLeftIcon, ChevronRightIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import AppointmentTableList from '@/Pages/Admin/AppointmentTableList.vue'

const { props } = usePage()
const toast = useToast()

const viewMode = ref('calendar')
const appointments = ref(props.stats)
const timeSlots = ref(props.timeSlots)

const statusFilter = ref('')
const searchQuery = ref('')

const appointmentData = ref(
  props.appointments
     .filter(a => { const status = a.status?.toLowerCase()
      return status !== 'cancelled' && status !== 'completed' })
    .map(a => ({
      id: a.id,         
      day: a.day,
      date: a.date,
      time: a.time,
      patient: a.patient,
      service: a.service,
      tools: a.tools,
      procedures: [a.service],
      scheduleId: a.schedule_id,
      status: a.status 
    }))
)

const currentDate = ref(new Date())

// Week days for the calendar
const weekDays = computed(() => {
  const start = new Date(currentDate.value)
  start.setDate(start.getDate() - start.getDay())

  return Array.from({ length: 7 }, (_, i) => {
    const date = new Date(start)
    date.setDate(start.getDate() + i)
    
    // Convert to local date string without timezone offset
    const localDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000))
    const dateString = localDate.toISOString().split('T')[0]
    
    return {
      day: ['SUN', 'MON', 'TUE', 'WED', 'THURS', 'FRI', 'SAT'][i],
      date: date.getDate(),
      fullDate: date,
      dateString: dateString
    }
  })
})

const monthYear = computed(() =>
  currentDate.value.toLocaleString('en-US', { month: 'long', year: 'numeric' }).toUpperCase()
)

const normalizeTime = (timeString) => {
  if (!timeString) return ''
  
  return timeString
    .replace(/(\d{1,2}):(\d{2})/g, (match, hours, minutes) => {
      // Remove leading zero from hours (01 -> 1, 10 -> 10)
      const normalizedHours = hours.replace(/^0+/, '')
      return `${normalizedHours}:${minutes}`
    })
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim()
}

const getAppointmentsForDateAndTime = (dayDate, time) => {
  const selectedDay = weekDays.value.find(d => d.date === dayDate)
  if (!selectedDay) return []
  
  const dateString = selectedDay.dateString

  const normalizedTimeSlot = normalizeTime(time)
  
  return appointmentData.value.filter(apt => {
    // Check if dates match
    if (apt.date !== dateString) return false
    
    const normalizedAppointmentTime = normalizeTime(apt.time)
    
    return normalizedAppointmentTime === normalizedTimeSlot
  })
}

// Week navigation
const previousWeek = () => {
  const newDate = new Date(currentDate.value)
  newDate.setDate(newDate.getDate() - 7)
  currentDate.value = newDate
}

const nextWeek = () => {
  const newDate = new Date(currentDate.value)
  newDate.setDate(newDate.getDate() + 7)
  currentDate.value = newDate
}

const filteredAppointments = computed(() => {
  let filtered = props.appointments 

  if (statusFilter.value) {
    filtered = filtered.filter(apt =>
      apt.status?.toLowerCase() === statusFilter.value.toLowerCase()
    )
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(apt =>
      apt.patient?.toLowerCase().includes(query) ||
      apt.procedure?.toLowerCase().includes(query) ||
      apt.service?.toLowerCase().includes(query)
    )
  }

  return filtered
})

// View appointment details
const openAppointmentDetails = (appointment) => {
  console.log('Viewing appointment details:', appointment)
}

// Reschedule handler
const handleReschedule = (appointment) => {
  const payload = {
    date: appointment.date,
    time: appointment.time,
    schedule_id: appointment.scheduleId,
  }

  router.patch(`/admin/appointments/${appointment.id}/reschedule`, payload, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Appointment successfully rescheduled!')
    },
    onError: (error) => {
      toast.error('Failed to reschedule appointment.')
      console.error(error)
    },
  })
}

const handleAppointmentCancelled = () => {
  router.reload()
}
</script>

<template>
  <AdminLayout>
    <div class="pt-24 pb-8 min-h-screen flex justify-center">
      <div class="w-full max-w-[1150px] flex flex-col items-center">
        <!-- Header -->
        <div class="flex justify-between items-center w-full mb-8 px-12">
          <h2 class="font-bold text-3xl text-[#1e3a56] tracking-wide">APPOINTMENTS</h2>
        </div>

        <div class="flex flex-col lg:flex-row gap-6 mb-8 px-12 w-full justify-between">
          <div class="flex-1 space-y-4">
            <div class="relative">
              <input
                type="text"
                placeholder="Search"
                class="w-full px-4 py-3 pl-11 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#6dc0b3] bg-white shadow-sm"
                v-model="searchQuery"
              />
              <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500" />
            </div>

            <div
              v-if="viewMode === 'table'"
              class="flex items-center gap-4 transition-all duration-300 ease-in-out">
              <label class="flex items-center gap-2">
                <select
                  class="border border-gray-300 rounded-md pr-24 pl-2 py-2 focus:outline-none focus:ring-2 focus:ring-dark"
                  v-model="statusFilter">
                  <option value="">All Appointments</option> 
                  <option value="pending">Pending</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option> 
                  <option value="rescheduled">Rescheduled</option>
                </select>
              </label>
            </div>

            <div
              class="flex items-center bg-white py-2 px-6 w-fit rounded-lg border border-dark mt-2">
              <button
                @click="viewMode = 'calendar'"
                :class="viewMode === 'calendar' ? 'font-bold text-dark' : 'text-gray-500'"
                class="px-4">
                Appointment Calendar
              </button>

              <div class="h-6 border-r-2 border-dark mx-3"></div>

              <button
                @click="viewMode = 'table'"
                :class="viewMode === 'table' ? 'font-bold text-dark' : 'text-gray-500'"
                class="px-4">
                Appointment Table List
              </button>
            </div>
          </div>

          <!-- Stats -->
          <div class="bg-white rounded-xl shadow-lg p-6 min-w-[400px] border border-gray-100">
            <h3 class="font-bold text-lg mb-4">Appointments Overview</h3>
            <div class="grid grid-cols-2 gap-4 text-sm text-center font-semibold">
              <div>Scheduled: {{ appointments.scheduled }}</div>
              <div>Cancelled: {{ appointments.cancelled }}</div>
              <div>Rescheduled: {{ appointments.rescheduled }}</div>
              <div>Completed: {{ appointments.completed }}</div>
            </div>
          </div>
        </div>

        <!-- Calendar View -->
        <div
          v-if="viewMode === 'calendar'"
          class="bg-white rounded-2xl shadow-md overflow-hidden w-[1100px] h-[550px] border border-gray-300 flex justify-center">
          <div class="flex flex-col w-full">
            <!-- Calendar Header -->
            <div class="flex items-center justify-center py-3 border-b-4 border-dark">
              <button @click="previousWeek" class="p-2 hover:bg-gray-100 rounded-full">
                <ChevronLeftIcon class="w-5 h-5 text-gray-700" />
              </button>
              <h3 class="text-lg font-semibold mx-8 text-gray-800 tracking-wide">
                {{ monthYear }}
              </h3>
              <button @click="nextWeek" class="p-2 hover:bg-gray-100 rounded-full">
                <ChevronRightIcon class="w-5 h-5 text-gray-700" />
              </button>
            </div>

            <!-- Calendar Table -->
            <div class="p-4 overflow-x-auto">
              <div class="bg-white min-h-full rounded-lg border-2 border-dark p-4">
                <table class="w-full border-collapse text-xs text-gray-700 table-fixed">
                  <thead>
                    <tr class="bg-[#f5f9f8] text-gray-800">
                      <th class="border-2 border-dark px-3 py-2 text-center font-semibold w-[130px]">TIME</th>
                      <th
                        v-for="day in weekDays"
                        :key="day.date"
                        class="border-2 border-dark px-2 py-2 text-center font-semibold w-[115px]">
                        <div>{{ day.day }}</div>
                        <div class="text-sm font-bold text-gray-900">{{ day.date }}</div>
                      </th>
                    </tr>
                  </thead>

                  <tbody class="bg-white">
                    <tr v-for="time in timeSlots" :key="time" class="h-[110px] bg-white">
                      <td class="border-2 border-dark px-3 py-2 font-semibold align-middle truncate bg-white">{{ time }}</td>
                      <td
                        v-for="day in weekDays"
                        :key="`${day.date}-${time}`"
                        class="border-2 border-dark text-xs h-[110px] w-[115px] align-top overflow-hidden bg-white">
                        <div
                          v-for="(apt, idx) in getAppointmentsForDateAndTime(day.date, time)"
                          :key="idx"
                          class="bg-light text-xs h-full flex flex-col justify-start p-1 truncate">
                          <div class="font-semibold mb-1 p-1 text-left truncate">{{ apt.patient }}</div>

                          <div
                            v-for="(proc, pIdx) in apt.procedures"
                            :key="pIdx"
                            class="text-[11px] text-left truncate">
                            {{ proc }}
                          </div>

                          <div
                            v-if="apt.tools && apt.tools.length"
                            class="text-[11px] text-left text-gray-600 mt-1 overflow-hidden h-[45px]">
                            <div
                              v-for="(tool, tIdx) in apt.tools"
                              :key="tIdx"
                              class="truncate">
                              {{ tool }}
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Table View -->
        <div v-else class="w-full">
          <AppointmentTableList 
            :appointments="filteredAppointments" 
            @view="openAppointmentDetails"
            @reschedule="handleReschedule"
            @cancel="handleAppointmentCancelled" 
          />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb {
  background: #14b8a6;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #0f766e;
}
</style>
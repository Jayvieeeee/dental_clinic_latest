<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  modelValue: Boolean,
  selectedDate: String,
  selectedTime: String,
  selectedTimeLabel: String,
 selectedScheduleId: [String, Number]
})

const emits = defineEmits([
  "update:modelValue",
  "update:selectedDate",
  "update:selectedTime", 
  "update:selectedTimeLabel",
  "update:selectedScheduleId", 
  "datetime-selected"
])

const localSelectedDate = ref(props.selectedDate)
const errorMessage = ref("")
const schedules = ref([])

// Fetch schedule data from backend
onMounted(async () => {
  try {
    const res = await axios.get('/customer/schedules')
    schedules.value = res.data
  } catch (error) {
    console.error("Failed to load schedules:", error)
  }
})

// Calendar state
const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())

const timeSlots = computed(() => {
  return schedules.value.map(slot => {
    const start = formatTime(slot.start_time)
    const end = formatTime(slot.end_time)
    return {
      label: `${start} - ${end}`,
      value: slot.start_time,
      scheduleId: slot.schedule_id // Make sure this matches your database column name
    }
  })
})

const formatTime = (time) => {
  const [hour, minute] = time.split(":").map(Number)
  const ampm = hour >= 12 ? "p.m" : "a.m"
  const formattedHour = hour % 12 || 12
  return `${formattedHour}:${String(minute).padStart(2, "0")} ${ampm}`
}

const monthNames = [
  "January","February","March","April","May","June",
  "July","August","September","October","November","December"
]
const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]

// Helpers
const getDaysInMonth = (y, m) => new Date(y, m + 1, 0).getDate()
const getFirstDayOfMonth = (y, m) => new Date(y, m, 1).getDay()

const daysInMonth = computed(() => getDaysInMonth(currentYear.value, currentMonth.value))
const firstDay = computed(() => getFirstDayOfMonth(currentYear.value, currentMonth.value))

const calendarDays = computed(() => {
  const days = []
  for (let i = 0; i < firstDay.value; i++) days.push(null)
  for (let d = 1; d <= daysInMonth.value; d++) days.push(d)
  return days
})

// Determine status for each date
const getDateStatus = (day) => {
  if (!day) return { isPast: false, isClosed: false }

  const dateObj = new Date(currentYear.value, currentMonth.value, day)
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  const isPast = dateObj < today
  const formattedDate = dateObj.toISOString().split("T")[0]
  const schedule = schedules.value.find(s => s.date === formattedDate)

  // Determine closure based on DB schedule or fixed rule
  const isClosed = schedule?.status === 'closed' || dateObj.getDay() === 0 || dateObj.getDay() === 1

  return { isPast, isClosed }
}

const selectDate = (day) => {
  const { isPast, isClosed } = getDateStatus(day)
  if (!day || isPast || isClosed) return

  const formatted = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  localSelectedDate.value = formatted
  emits("update:selectedDate", formatted)
}

const nextMonth = () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0
    currentYear.value++
  } else {
    currentMonth.value++
  }
}
const prevMonth = () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11
    currentYear.value--
  } else {
    currentMonth.value--
  }
}

// Handle time selection
const handleTimeSelect = (slot) => {
  emits("update:selectedTime", slot.value)
  emits("update:selectedTimeLabel", slot.label)
  emits("update:selectedScheduleId", slot.scheduleId)
  
  // Also emit the custom event with all data
  emits("datetime-selected", {
    date: localSelectedDate.value,
    time: slot.value,
    timeLabel: slot.label,
    scheduleId: slot.scheduleId
  })
}

// Confirm before closing
const confirmSelection = () => {
  if (!localSelectedDate.value) {
    errorMessage.value = "Please select a date."
    return
  }
  if (!props.selectedTime) {
    errorMessage.value = "Please select a time."
    return
  }

  errorMessage.value = ""
  emits("update:modelValue", false)
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl shadow-lg w-[90%] md:w-[700px] relative">
      <!-- Close button -->
      <button @click="$emit('update:modelValue', false)" class="absolute top-3 right-5 text-2xl text-gray-600 hover:text-black">✕</button>

      <h2 class="text-xl font-semibold mb-4 text-[#0E5C5C]">Choose Available Slots</h2>
      <p class="font-medium">Date</p>

      <div class="flex flex-col md:flex-row gap-10">
        <!-- Calendar -->
        <div class="flex-1 p-3 border-2 rounded-xl border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <span class="font-semibold">{{ monthNames[currentMonth] }} {{ currentYear }}</span>

            <div class="flex items-center">
              <button @click="prevMonth" class="p-1 hover:scale-105 transition-transform">
                <img src="/icons/arrow_back.png" alt="Previous" class="w-4 h-4" />
              </button>
              <button @click="nextMonth" class="p-1 hover:scale-105 transition-transform">
                <img src="/icons/arrow_forward.png" alt="Next" class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Days header -->
          <div class="grid grid-cols-7 gap-2 mb-2 text-sm font-semibold">
            <div v-for="d in dayNames" :key="d" class="text-center">{{ d }}</div>
          </div>

          <!-- Calendar Grid -->
          <div class="grid grid-cols-7 gap-2">
<!-- In your DateTimeModal template, fix this line: -->
        <div
          v-for="(day, index) in calendarDays"
          :key="index"
          class="p-2 text-center rounded cursor-pointer transition font-medium"
          :class="{
            'bg-gray-300 text-gray-500 cursor-not-allowed': getDateStatus(day).isPast,
            'bg-red-400 text-white cursor-not-allowed': getDateStatus(day).isClosed,
            'bg-[#0E5C5C] text-white': selectedDate && selectedDate.endsWith && selectedDate.endsWith(String(day).padStart(2, '0')),
            'hover:bg-[#0E5C5C] hover:text-white': !getDateStatus(day).isPast && !getDateStatus(day).isClosed
          }"
          @click="selectDate(day)"
        >
          {{ day }}
        </div>
          </div>
        </div>

        <!-- Time Slots -->
        <div class="flex-1">
          <h2 class="text-lg font-semibold mb-2">Time</h2>

          <div v-if="timeSlots.length" class="flex flex-col space-y-4">
            <label
              v-for="slot in timeSlots"
              :key="slot.value"
              class="flex items-center font-semibold space-x-3"
            >
              <input
                type="radio"
                class="w-5 h-5 accent-[#0E5C5C] focus:ring-0 cursor-pointer"
                :value="slot.value"
                :checked="slot.value === selectedTime"
                @change="handleTimeSelect(slot)"
              />
              <span>{{ slot.label }}</span>
            </label>
          </div>

          <div v-else class="text-gray-400 text-sm mt-4">
            No available time slots.
          </div>
        </div>

      </div>

      <!-- Error Message -->
      <p v-if="errorMessage" class="text-red-600 font-medium mt-4">{{ errorMessage }}</p>

      <!-- Confirm Button -->
      <div class="mt-6 flex justify-end">
        <button
          @click="confirmSelection"
          class="bg-[#0E5C5C] text-white px-6 py-2 rounded-full hover:bg-[#084646] transition-colors"
        >
          Confirm
        </button>
      </div>
    </div>
  </div>
</template>
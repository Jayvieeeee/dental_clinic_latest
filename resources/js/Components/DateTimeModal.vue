<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import AppModal from '@/Components/AppModal.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  modelValue: Boolean,
  selectedDate: String,
  selectedTime: String,
  selectedTimeLabel: String,
  selectedScheduleId: [String, Number],
  isReschedule: {
    type: Boolean,
    default: false
  }
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
const localSelectedTime = ref(props.selectedTime)
const localSelectedScheduleId = ref(props.selectedScheduleId)

const errorMessage = ref("")
const schedules = ref([])
const isLoading = ref(false)
const showConfirmModal = ref(false)
const scheduleCache = ref({})

let debounceTimer = null
const DEBOUNCE_DELAY = 500 // Wait 500ms after last change

const API_BASE = 'https://districtsmiles.online'

// Get tomorrow's date for minimum selection
const tomorrow = new Date()
tomorrow.setDate(tomorrow.getDate() + 1)
const minDate = tomorrow.toISOString().split('T')[0]

// Get max date (3 months from now)
const maxDate = new Date()
maxDate.setMonth(maxDate.getMonth() + 3)
const maxDateStr = maxDate.toISOString().split('T')[0]

const fetchSchedules = async (date, isPrefetch = false) => {
  if (!date) return

  // Check if date is valid (not today or past)
  const selectedDate = new Date(date)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  
  if (selectedDate <= today) {
    if (!isPrefetch) {
      errorMessage.value = "Please select a future date (tomorrow or later)."
      schedules.value = []
      isLoading.value = false
    }
    return
  }

  if (scheduleCache.value[date]) {
    schedules.value = scheduleCache.value[date]
    if (!isPrefetch) isLoading.value = false
    return
  }

  if (!isPrefetch) {
    isLoading.value = true
    errorMessage.value = ""
  }

  try {
    const res = await axios.get(`${API_BASE}/schedules/${date}/available-slots`)
    if (res.data.success === false) {
      if (!isPrefetch) {
        errorMessage.value = res.data.message || "No schedules available"
        schedules.value = []
      }
      return
    }

    const data = Array.isArray(res.data.available_slots)
      ? res.data.available_slots
      : []

    scheduleCache.value[date] = data

    if (!isPrefetch) {
      schedules.value = data
      errorMessage.value = data.length ? "" : "No available time slots for this date"
    }

    if (!isPrefetch) {
      preloadNextDays(date, 2)
    }

  } catch (error) {
    if (!isPrefetch) {
      if (error.response?.status === 401) {
        errorMessage.value = "Please log in to view schedules"
      } else if (error.response?.status === 422) {
        errorMessage.value = error.response.data.message || "Please select a future date"
      } else if (error.response?.status === 404) {
        errorMessage.value = "No schedules found for this date"
      } else {
        errorMessage.value = "Unable to load available schedules. Please try again."
      }
      schedules.value = []
    }
  } finally {
    if (!isPrefetch) isLoading.value = false
  }
}

// ✅ DEBOUNCED VERSION OF FETCH SCHEDULES
const debouncedFetchSchedules = (date, isPrefetch = false) => {
  // Clear previous timer
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
  
  // Set new timer
  debounceTimer = setTimeout(() => {
    fetchSchedules(date, isPrefetch)
  }, DEBOUNCE_DELAY)
}

const preloadNextDays = async (date, daysAhead = 2) => {
  const baseDate = new Date(date)
  for (let i = 1; i <= daysAhead; i++) {
    const nextDate = new Date(baseDate)
    nextDate.setDate(baseDate.getDate() + i)
    
    // Don't prefetch beyond max date
    if (nextDate > new Date(maxDateStr)) break;
    
    const formatted = nextDate.toISOString().split("T")[0]
    if (!scheduleCache.value[formatted]) {
      // Use debounced version for prefetch too
      debouncedFetchSchedules(formatted, true)
    }
  }
}

// ✅ FIXED WATCHERS - Use debounced version
watch(() => props.selectedDate, (newDate) => {
  if (newDate && newDate !== localSelectedDate.value) {
    localSelectedDate.value = newDate
    debouncedFetchSchedules(newDate)
  }
})

watch(() => props.selectedScheduleId, (newId) => {
  if (newId) localSelectedScheduleId.value = newId
})

onMounted(() => {
  // Set default date to tomorrow if no date is selected
  if (!props.selectedDate) {
    localSelectedDate.value = minDate
    emits("update:selectedDate", minDate)
  }
  
  // ✅ ONLY FETCH ONCE ON MOUNT - don't double fetch
  if (props.selectedDate && props.selectedDate !== minDate) {
    debouncedFetchSchedules(props.selectedDate)
  } else {
    // Auto-select tomorrow and fetch its schedules (ONCE)
    debouncedFetchSchedules(minDate)
  }
})

// ✅ FIXED: Use debounced version for local date changes
watch(localSelectedDate, (newDate) => {
  if (newDate) {
    debouncedFetchSchedules(newDate)
  }
})

const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())

const timeSlots = computed(() =>
  schedules.value.map(slot => ({
    label: slot.display_time,
    value: slot.start_time || slot.time_slot,
    scheduleId: slot.schedule_id
  }))
)

const monthNames = [
  "January","February","March","April","May","June",
  "July","August","September","October","November","December"
]
const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]

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

const getDateStatus = (day) => {
  if (!day) return { isPast: false, isClosed: false, isToday: false, isFuture: false }
  
  const dateObj = new Date(currentYear.value, currentMonth.value, day)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  
  const isToday = dateObj.getTime() === today.getTime()
  const isPast = dateObj < today
  const isFuture = dateObj > today
  const isClosed = dateObj.getDay() === 0 || dateObj.getDay() === 1 // Sunday or Monday
  const isBeyondMax = dateObj > new Date(maxDateStr)
  
  return { isPast, isClosed, isToday, isFuture, isBeyondMax }
}

const selectDate = (day) => {
  const { isPast, isClosed, isToday, isBeyondMax } = getDateStatus(day)
  if (!day || isPast || isClosed || isToday || isBeyondMax) return
  
  const formatted = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  localSelectedDate.value = formatted
  emits("update:selectedDate", formatted)
  // ✅ Don't call fetchSchedules here - the watcher will handle it debounced
}

const nextMonth = () => {
  const nextMonthDate = new Date(currentYear.value, currentMonth.value + 1, 1)
  if (nextMonthDate <= new Date(maxDateStr)) {
    if (currentMonth.value === 11) {
      currentMonth.value = 0
      currentYear.value++
    } else {
      currentMonth.value++
    }
  }
}

const prevMonth = () => {
  const prevMonthDate = new Date(currentYear.value, currentMonth.value - 1, 1)
  const minDateObj = new Date(minDate)
  if (prevMonthDate >= minDateObj) {
    if (currentMonth.value === 0) {
      currentMonth.value = 11
      currentYear.value--
    } else {
      currentMonth.value--
    }
  }
}

const handleTimeSelect = (slot) => {
  localSelectedTime.value = slot.value
  localSelectedScheduleId.value = slot.scheduleId

  emits("update:selectedTime", slot.value)
  emits("update:selectedTimeLabel", slot.label)
  emits("update:selectedScheduleId", slot.scheduleId)
}

const confirmSelection = () => {
  if (!localSelectedDate.value) {
    errorMessage.value = "Please select a date."
    return
  }
  
  // Double-check date is valid
  const selectedDate = new Date(localSelectedDate.value)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  
  if (selectedDate <= today) {
    errorMessage.value = "Please select a future date (tomorrow or later)."
    return
  }
  
  if (!localSelectedTime.value) {
    errorMessage.value = "Please select a time."
    return
  }

  errorMessage.value = ""

  if (props.isReschedule) {
    showConfirmModal.value = true
  } else {
    emits("datetime-selected", {
      date: localSelectedDate.value,
      time: localSelectedTime.value,
      timeLabel: timeSlots.value.find(slot => slot.value === localSelectedTime.value)?.label || "",
      scheduleId: localSelectedScheduleId.value
    })
    emits("update:modelValue", false)
  }
}

const confirmReschedule = () => {
  showConfirmModal.value = false
  emits("datetime-selected", {
    date: localSelectedDate.value,
    time: localSelectedTime.value,
    timeLabel: timeSlots.value.find(slot => slot.value === localSelectedTime.value)?.label || "",
    scheduleId: localSelectedScheduleId.value
  })

  emits("update:modelValue", false)

  router.reload({ only: ['appointments'] })
}

const cancelReschedule = () => {
  showConfirmModal.value = false
}

// Helper to check if a date is selectable
const isDateSelectable = (day) => {
  const { isPast, isClosed, isToday, isBeyondMax } = getDateStatus(day)
  return !isPast && !isClosed && !isToday && !isBeyondMax
}

const canGoNext = computed(() => {
  const nextMonthDate = new Date(currentYear.value, currentMonth.value + 1, 1)
  return nextMonthDate <= new Date(maxDateStr)
})

const canGoPrev = computed(() => {
  const prevMonthDate = new Date(currentYear.value, currentMonth.value - 1, 1)
  const minDateObj = new Date(minDate)
  return prevMonthDate >= minDateObj
})

// ✅ CLEANUP DEBOUNCE TIMER WHEN COMPONENT UNMOUNTS
import { onUnmounted } from 'vue'
onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
})
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl shadow-lg w-[90%] md:w-[700px] relative max-h-[90vh] overflow-y-auto">
      <button @click="$emit('update:modelValue', false)" class="absolute top-3 right-5 text-2xl text-gray-600 hover:text-black">✕</button>

      <h2 class="text-xl font-semibold mb-4 text-[#0E5C5C]">Choose Available Slots</h2>   
         
      <div class="flex flex-col md:flex-row gap-10">
        <!-- Calendar -->
        <div class="flex-1 p-3 border-2 rounded-xl border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <span class="font-semibold">{{ monthNames[currentMonth] }} {{ currentYear }}</span>
            <div class="flex items-center">
              <button @click="prevMonth" class="p-1 hover:scale-105 transition-transform" :disabled="!canGoPrev">
                <img src="/icons/arrow_back.png" alt="Previous" class="w-4 h-4" :class="{ 'opacity-50': !canGoPrev }" />
              </button>
              <button @click="nextMonth" class="p-1 hover:scale-105 transition-transform" :disabled="!canGoNext">
                <img src="/icons/arrow_forward.png" alt="Next" class="w-4 h-4" :class="{ 'opacity-50': !canGoNext }" />
              </button>
            </div>
          </div>

          <div class="grid grid-cols-7 gap-2 mb-2 text-sm font-semibold">
            <div v-for="d in dayNames" :key="d" class="text-center">{{ d }}</div>
          </div>

          <div class="grid grid-cols-7 gap-2">
            <div
              v-for="(day, index) in calendarDays"
              :key="index"
              class="p-2 text-center rounded cursor-pointer transition font-medium text-sm"
              :class="{
                'bg-gray-300 text-gray-500 cursor-not-allowed': !isDateSelectable(day),
                'bg-red-400 text-white cursor-not-allowed': getDateStatus(day).isClosed,
                'bg-gray-300 text-gray-500  cursor-not-allowed': getDateStatus(day).isToday,
                'bg-[#0E5C5C] text-white': localSelectedDate && localSelectedDate.endsWith(String(day).padStart(2, '0')) && isDateSelectable(day),
                'hover:bg-[#0E5C5C] hover:text-white': isDateSelectable(day)
              }"
              @click="selectDate(day)">
              {{ day }}
            </div>
          </div>
        </div>

        <!-- Time Slots -->
        <div class="flex-1">
          <h2 class="text-lg font-semibold mb-2">Available Time Slots</h2>

          <!-- Loading State -->
          <div v-if="isLoading" class="flex flex-col space-y-2 py-2">
            <div v-for="n in 4" :key="n" class="h-5 bg-gray-200 rounded animate-pulse"></div>
          </div>

          <!-- Date not selected or invalid -->
          <div v-else-if="!localSelectedDate || !isDateSelectable(localSelectedDate)" class="text-gray-400 text-sm mt-4 p-4 bg-gray-50 rounded text-center">
            Please select a valid future date to see available time slots.
          </div>

          <!-- Available Slots -->
          <div v-else-if="timeSlots.length" class="flex flex-col space-y-4">
            <label
              v-for="slot in timeSlots"
              :key="slot.scheduleId"
              class="flex items-center font-semibold space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
              <input
                type="radio"
                class="w-5 h-5 accent-[#0E5C5C] focus:ring-0 cursor-pointer"
                :value="slot.scheduleId"
                :checked="Number(localSelectedScheduleId) === Number(slot.scheduleId)"
                @change="handleTimeSelect(slot)"
              />
              <span>{{ slot.label }}</span>
            </label>
          </div>

          <!-- No Slots Available -->
          <div v-else class="text-gray-400 text-sm mt-4 p-4 bg-gray-50 rounded text-center">
            {{ errorMessage || 'No available time slots for selected date.' }}
          </div>
        </div>
      </div>

      <!-- Error Message -->
      <p v-if="errorMessage && timeSlots.length === 0" class="text-red-600 font-medium mt-4 text-center">{{ errorMessage }}</p>

      <div class="mt-6 flex justify-end">
        <button
          @click="confirmSelection"
          :disabled="!localSelectedTime || !localSelectedDate || !isDateSelectable(localSelectedDate)"
          class="bg-[#0E5C5C] text-white px-6 py-2 rounded-full hover:bg-[#084646] transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed">
          Confirm Selection
        </button>
      </div>
    </div>
  </div>

  <AppModal :show="showConfirmModal" @close="cancelReschedule">
    <template #default>
      <div class="p-4 text-center">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Confirm Reschedule</h3>
        <p class="text-gray-600">Are you sure you want to reschedule this appointment?</p>
      </div>
    </template>

    <template #actions>
      <div class="flex justify-center items-center w-full pb-4">
        <div class="flex gap-4">
          <button
            @click="cancelReschedule"
            class="px-5 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium"
          >
            Cancel
          </button>
          <button
            @click="confirmReschedule"
            class="px-5 py-2 rounded-lg bg-[#0E5C5C] text-white hover:bg-[#084646] font-medium"
          >
            Confirm
          </button>
        </div>
      </div>
    </template>
  </AppModal>
</template>
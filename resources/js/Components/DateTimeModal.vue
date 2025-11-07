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

// NOTE: You might need to adjust this API_BASE depending on your environment
const API_BASE = 'https://districtsmiles.online' 

// Get tomorrow's date for minimum selection
const tomorrow = new Date()
tomorrow.setDate(tomorrow.getDate() + 1)
const minDate = tomorrow.toISOString().split('T')[0]

// Get max date (3 months from now)
const maxDate = new Date()
maxDate.setMonth(maxDate.getMonth() + 3)
const maxDateStr = maxDate.toISOString().split('T')[0]

// Philippine Holidays 2025-2026
const philippineHolidays = {
    // 2025 Regular Holidays
    "2025-01-01": "New Year's Day",
    "2025-04-17": "Maundy Thursday",
    "2025-04-18": "Good Friday",
    "2025-04-19": "Black Saturday",
    "2025-04-20": "Easter Sunday",
    "2025-04-09": "Araw ng Kagitingan",
    "2025-05-01": "Labor Day",
    "2025-06-12": "Independence Day",
    "2025-08-21": "Ninoy Aquino Day",
    "2025-08-25": "National Heroes Day",
    "2025-11-01": "All Saints' Day",
    "2025-11-30": "Bonifacio Day",
    "2025-12-25": "Christmas Day",
    "2025-12-30": "Rizal Day",
    "2025-12-31": "New Year's Eve",

    // 2025 Special Non-Working Holidays
    "2025-01-29": "Chinese New Year",
    "2025-02-25": "EDSA Revolution Anniversary",
    "2025-03-01": "Ramadan Start",
    "2025-03-31": "Eid'l Fitr",
    "2025-11-02": "All Souls' Day",
    "2025-12-08": "Feast of the Immaculate Conception",
    "2025-12-24": "Christmas Eve",

    // 2026 Regular Holidays
    "2026-01-01": "New Year's Day",
    "2026-04-02": "Maundy Thursday",
    "2026-04-03": "Good Friday",
    "2026-04-04": "Black Saturday",
    "2026-04-05": "Easter Sunday",
    "2026-04-09": "Araw ng Kagitingan",
    "2026-05-01": "Labor Day",
    "2026-06-12": "Independence Day",
    "2026-08-21": "Ninoy Aquino Day",
    "2026-08-31": "National Heroes Day",
    "2026-11-01": "All Saints' Day",
    "2026-11-30": "Bonifacio Day",
    "2026-12-25": "Christmas Day",
    "2026-12-30": "Rizal Day",
    "2026-12-31": "New Year's Eve",

    // 2026 Special Non-Working Holidays
    "2026-02-17": "Chinese New Year",
    "2026-02-25": "EDSA Revolution Anniversary",
    "2026-02-18": "Ramadan Start",
    "2026-03-20": "Eid'l Fitr",
    "2026-11-02": "All Souls' Day",
    "2026-12-08": "Feast of the Immaculate Conception",
    "2026-12-24": "Christmas Eve"
}

// Function to check if a date is a holiday
const isHoliday = (year, month, day) => {
    const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    return philippineHolidays[dateString] || null
}

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
        // The backend controller for this endpoint already handles filtering out taken slots
        const res = await axios.get(`${API_BASE}/available-slots`, {
          params: { date }
        })

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

// DEBOUNCED VERSION OF FETCH SCHEDULES
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

// Watcher for prop changes
watch(() => props.selectedDate, (newDate) => {
    if (newDate && newDate !== localSelectedDate.value) {
        localSelectedDate.value = newDate
        // No need to clear time here, the localSelectedDate watcher handles it
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

    // ONLY FETCH ONCE ON MOUNT
    if (props.selectedDate) {
        debouncedFetchSchedules(props.selectedDate)
    } else {
        // Auto-select tomorrow and fetch its schedules (ONCE)
        debouncedFetchSchedules(minDate)
    }
})

// *** FIX: Clear selected time/schedule when a new date is chosen ***
watch(localSelectedDate, (newDate, oldDate) => {
    if (newDate) {
        if (newDate !== oldDate) {
            // Clear selections when date changes to force new selection from fetched slots
            localSelectedTime.value = null
            localSelectedScheduleId.value = null
            emits("update:selectedTime", null)
            emits("update:selectedScheduleId", null)
        }
        debouncedFetchSchedules(newDate)
    }
})

const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())

const timeSlots = computed(() =>
    schedules.value.map(slot => ({
        label: slot.display_time,
        value: slot.start_time, // NOTE: Use start_time for compatibility, though scheduleId is primary key
        scheduleId: slot.schedule_id
    }))
)

const monthNames = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
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
    if (!day) return { isPast: false, isClosed: false, isToday: false, isFuture: false, isHoliday: false, isBeyondMax: false }

    const dateObj = new Date(currentYear.value, currentMonth.value, day)
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    const isToday = dateObj.getTime() === today.getTime()
    const isPast = dateObj < today
    const isFuture = dateObj > today
    const isClosed = dateObj.getDay() === 0 || dateObj.getDay() === 1 // Sunday or Monday
    const isBeyondMax = dateObj > new Date(maxDateStr)
    const isHolidayDate = isHoliday(currentYear.value, currentMonth.value, day)

    return { isPast, isClosed, isToday, isFuture, isBeyondMax, isHoliday: !!isHolidayDate }
}

const selectDate = (day) => {
    const { isPast, isClosed, isToday, isBeyondMax, isHoliday } = getDateStatus(day)
    if (!day || isPast || isClosed || isToday || isBeyondMax || isHoliday) return

    const formatted = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    localSelectedDate.value = formatted
    emits("update:selectedDate", formatted)
    // The watcher on localSelectedDate will handle fetching and clearing time slots
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
    // Compare only the date part (year, month)
    const currentCalendarDate = new Date(currentYear.value, currentMonth.value, 1)
    
    // Check if going to previous month would be before the allowed minimum date
    if (prevMonthDate.getFullYear() > minDateObj.getFullYear() || 
        (prevMonthDate.getFullYear() === minDateObj.getFullYear() && prevMonthDate.getMonth() >= minDateObj.getMonth())) 
    {
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
    
    // Check if the selected slot is actually in the timeSlots list (to prevent submitting a now-taken slot)
    const selectedSlot = timeSlots.value.find(slot => Number(slot.scheduleId) === Number(localSelectedScheduleId.value))
    if (!selectedSlot) {
        errorMessage.value = "Selected time slot is no longer available. Please select a new slot."
        // Force re-fetch just in case the cache is stale
        fetchSchedules(localSelectedDate.value)
        return
    }

    errorMessage.value = ""

    if (props.isReschedule) {
        showConfirmModal.value = true
    } else {
        emits("datetime-selected", {
            date: localSelectedDate.value,
            time: localSelectedTime.value,
            timeLabel: selectedSlot.label, // Use the confirmed slot label
            scheduleId: localSelectedScheduleId.value
        })
        emits("update:modelValue", false)
    }
}

const confirmReschedule = () => {
    showConfirmModal.value = false
    const selectedSlot = timeSlots.value.find(slot => Number(slot.scheduleId) === Number(localSelectedScheduleId.value))
    
    emits("datetime-selected", {
        date: localSelectedDate.value,
        time: localSelectedTime.value,
        timeLabel: selectedSlot.label,
        scheduleId: localSelectedScheduleId.value
    })

    emits("update:modelValue", false)

    // Only reload the appointments list after a successful reschedule action in the parent component
    // router.reload({ only: ['appointments'] })
}

const cancelReschedule = () => {
    showConfirmModal.value = false
}

// Helper to check if a date is selectable
const isDateSelectable = (day) => {
    if (!day) return false;
    const { isPast, isClosed, isToday, isBeyondMax, isHoliday } = getDateStatus(day)
    return !isPast && !isClosed && !isToday && !isBeyondMax && !isHoliday
}

const canGoNext = computed(() => {
    const nextMonthDate = new Date(currentYear.value, currentMonth.value + 1, 1)
    return nextMonthDate <= new Date(maxDateStr)
})

const canGoPrev = computed(() => {
    const prevMonthDate = new Date(currentYear.value, currentMonth.value - 1, 1)
    const minDateObj = new Date(minDate)
    
    return prevMonthDate.getFullYear() > minDateObj.getFullYear() || 
           (prevMonthDate.getFullYear() === minDateObj.getFullYear() && prevMonthDate.getMonth() >= minDateObj.getMonth())
})



// CLEANUP DEBOUNCE TIMER WHEN COMPONENT UNMOUNTS
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
                            class="p-2 text-center rounded cursor-pointer transition font-medium text-sm relative"
                            :class="{
                                'bg-gray-300 text-gray-500 cursor-not-allowed': !isDateSelectable(day),
                                'bg-red-400 text-white cursor-not-allowed': getDateStatus(day).isClosed || getDateStatus(day).isHoliday,
                                'bg-gray-300 text-gray-500 cursor-not-allowed': getDateStatus(day).isToday,
                                'bg-[#0E5C5C] text-white': localSelectedDate && localSelectedDate.endsWith(String(day).padStart(2, '0')) && isDateSelectable(day) && currentMonth === new Date(localSelectedDate).getMonth(),
                                'hover:bg-[#0E5C5C] hover:text-white': isDateSelectable(day)
                            }"
                            @click="selectDate(day)">
                            {{ day }}
                            <div v-if="getDateStatus(day).isHoliday" class="absolute top-1 right-1 w-1 h-1 bg-white rounded-full"></div>
                        </div>
                    </div>

                    <div class="mt-4 text-xs flex flex-wrap gap-2 justify-center">
                        <div class="flex items-center gap-1">
                            <div class="w-3 h-3 bg-[#0E5C5C] rounded"></div>
                            <span>Selected</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="w-3 h-3 bg-red-400 rounded"></div>
                            <span>Holiday/Closed</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="w-3 h-3 bg-gray-300 rounded"></div>
                            <span>Unavailable</span>
                        </div>
                    </div>
                </div>

                <div class="flex-1">
                    <h2 class="text-lg font-semibold mb-2">Available Time Slots</h2>

                    <div v-if="isLoading" class="flex flex-col space-y-2 py-2">
                        <div v-for="n in 4" :key="n" class="h-5 bg-gray-200 rounded animate-pulse"></div>
                    </div>

                    <div v-else-if="!localSelectedDate || !isDateSelectable(new Date(localSelectedDate).getDate())" class="text-gray-400 text-sm mt-4 p-4 bg-gray-50 rounded text-center">
                         <div v-if="localSelectedDate">
                            <div v-if="isHoliday(new Date(localSelectedDate).getFullYear(), new Date(localSelectedDate).getMonth(), new Date(localSelectedDate).getDate())" class="text-red-600 font-medium">
                                {{ isHoliday(new Date(localSelectedDate).getFullYear(), new Date(localSelectedDate).getMonth(), new Date(localSelectedDate).getDate()) }} - Clinic Closed
                            </div>
                            <div v-else>
                                Please select a valid future date to see available time slots.
                            </div>
                        </div>
                        <div v-else>
                             Please select a valid future date to see available time slots.
                        </div>
                    </div>

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

                    <div v-else class="text-gray-400 text-sm mt-4 p-4 bg-gray-50 rounded text-center">
                        {{ errorMessage || 'No available time slots for selected date.' }}
                    </div>
                </div>
            </div>

            <p v-if="errorMessage && timeSlots.length === 0" class="text-red-600 font-medium mt-4 text-center">{{ errorMessage }}</p>

            <div class="mt-6 flex justify-end">
                <button
                    @click="confirmSelection"
                    :disabled="!localSelectedTime || !localSelectedDate || !isDateSelectable(new Date(localSelectedDate).getDate())"
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
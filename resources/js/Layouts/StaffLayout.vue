<script setup>
import { ref, onMounted } from 'vue'
import StaffSidebar from '@/Components/StaffSidebar.vue'

const currentDate = ref('')

// Format the date once the component is mounted
onMounted(() => {
  const today = new Date()
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }
  currentDate.value = today.toLocaleDateString('en-US', options)
})
</script>

<template>
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <StaffSidebar />

    <!-- Main content -->
    <main class="flex-1 overflow-y-auto font-rem relative p-6 bg-light/30">
      <!-- Top bar with date -->
      <div class="absolute top-14 right-16 text-base font-semibold">
        {{ currentDate }}
      </div>

      <!-- Page content -->
      <slot />
    </main>
  </div>
</template>

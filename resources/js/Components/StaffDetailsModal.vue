<template>
  <div
    v-if="visible"
    class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
  >
    <div
      class="bg-white rounded-2xl shadow-lg w-full max-w-md p-6 relative"
    >
      <!-- Close Button -->
      <button
        @click="$emit('close')"
        class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-xl"
      >
        &times;
      </button>

      <!-- Title -->
      <h2 class="text-xl font-bold text-center mb-4 text-dark">
        Staff Details
      </h2>

      <!-- Staff Information -->
      <div class="space-y-2">
        <p><strong>Staff ID:</strong> {{ staff?.user_id }}</p>
        <p><strong>Last Name:</strong> {{ staff?.last_name }}</p>
        <p><strong>First Name:</strong> {{ staff?.first_name }}</p>
        <p><strong>Email:</strong> {{ staff?.email }}</p>
        <p><strong>Contact Number:</strong> {{ staff?.contact_no }}</p>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-6 mt-6">
        <button
          class="text-teal-700 font-medium hover:underline"
          @click="$emit('activate', staff)">
          Activate
        </button>
        <button
          class="text-red-600 font-medium hover:underline"
          @click="$emit('deactivate', staff)">
          Deactivate
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  visible: Boolean,
  staff: Object,
});
defineEmits(['close', 'activate', 'deactivate']);
</script>

<style scoped>
/* Optional: smooth fade-in */
div[ v-if="visible" ] {
  animation: fadeIn 0.2s ease;
}
@keyframes fadeIn {
  from { opacity: 0 }
  to { opacity: 1 }
}
</style>

<script setup>
import AdminSideBar from '@/Components/AdminSideBar.vue';
</script>

<template>
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <AdminSideBar />

    <!-- Main content -->
    <main class="flex-1 p-8 overflow-y-auto font-rem">
      <slot />
    </main>
  </div>
</template>

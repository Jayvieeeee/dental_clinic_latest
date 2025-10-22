<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <!-- Patients content goes here -->
                        <h3 class="text-lg font-medium text-gray-900">Dashboard</h3>
                        <!-- Add your patients management UI here -->
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
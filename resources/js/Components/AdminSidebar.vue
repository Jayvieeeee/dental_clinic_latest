<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import Modal from '@/Components/Modal.vue' // ✅ Make sure this exists

const page = usePage()
const isExpanded = ref(true)
const showLogoutConfirm = ref(false) // ✅ Added

const menuItems = [
  { id: 'dashboard', label: 'HOME', href: '/admin/dashboard', icon: 'home' },
  { id: 'appointments', label: 'APPOINTMENTS', href: '/admin/appointments', icon: 'appointments' },
  { id: 'patients', label: 'PATIENTS', href: '/admin/patients', icon: 'patients' },
  { id: 'payments', label: 'PAYMENTS', href: '/admin/payments', icon: 'payments' },
  { id: 'feedback', label: 'FEEDBACK', href: '/admin/feedback', icon: 'feedback_admin' },
  { id: 'staff', label: 'MANAGE STAFF ACCOUNT', href: '/admin/manageStaffAccount', icon: 'manage_staff_acc' },
  { id: 'profile', label: 'PROFILE', href: '/admin/profile', icon: 'profile_admin' }
]

const isActive = (href) => {
  return page.url === href || page.url.startsWith(href + '/')
}

// ✅ SHOW CONFIRMATION FIRST
const handleLogout = () => {
  showLogoutConfirm.value = true
}

// ✅ PERFORM LOGOUT AFTER CONFIRMING
const confirmLogout = () => {
  router.post('/logout', {}, {
    onFinish: () => showLogoutConfirm.value = false
  })
}

const toggleSidebar = () => {
  isExpanded.value = !isExpanded.value
}
</script>

<template>
  <!-- Sidebar -->
  <aside
    :class="[
      'h-screen sticky top-0 left-0 bg-light/50 flex flex-col border-r transition-all duration-300 ease-in-out overflow-hidden',
      isExpanded ? 'w-64' : 'w-20'
    ]"
  >
    <!-- Logo -->
    <div class="flex items-center justify-center p-6 flex-shrink-0">
      <div class="flex items-center gap-3">
        <img 
          v-if="isExpanded"
          src="/icons/logo.png"
          class="h-13 lg:h-16 my-4 object-contain"
        >
        <img 
          v-else
          src="/images/tab_icon.svg"
          class="h-10 w-10 object-contain"
        >
      </div>
    </div>

    <!-- Menu -->
    <nav class="flex-1 py-4 px-2 space-y-2 overflow-y-auto">
      <Link 
        v-for="item in menuItems"
        :key="item.id"
        :href="item.href"
        :class="[
          'w-full flex items-center gap-4 px-4 py-3 rounded-full transition-colors relative group',
          isActive(item.href)
            ? 'bg-neutral font-semibold text-white'
            : 'text-black hover:bg-neutral hover:text-white'
        ]"
      >
        <img :src="`/icons/${item.icon}.png`" class="w-5 h-5 object-contain" />

        <Transition name="fade">
          <span v-if="isExpanded" class="text-sm font-medium whitespace-nowrap">
            {{ item.label }}
          </span>
        </Transition>

        <div
          v-if="!isExpanded"
          class="absolute left-16 bg-neutral text-white px-2 py-1 rounded text-xs opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50"
        >
          {{ item.label }}
        </div>
      </Link>
    </nav>

    <!-- Logout -->
    <div class="border-t border-cyan-200 p-3 flex-shrink-0">
      <button
        @click="handleLogout"
        class="w-full flex items-center gap-4 px-4 py-3 rounded-lg text-black hover:bg-neutral transition-colors relative group"
      >
        <img src="/icons/logout.png" class="w-5 h-5 object-contain" />

        <Transition name="fade">
          <span v-if="isExpanded" class="text-sm font-medium">LOGOUT</span>
        </Transition>

        <div
          v-if="!isExpanded"
          class="absolute left-16 bg-neutral text-white px-2 py-1 rounded text-xs opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50"
        >
          LOGOUT
        </div>
      </button>
    </div>

    <!-- Collapse -->
    <div class="border-t border-cyan-200 p-3 flex-shrink-0">
      <button @click="toggleSidebar" class="w-full flex items-center justify-center px-4 py-3 rounded-lg text-black hover:bg-neutral transition-colors">
        <span v-if="isExpanded" class="flex items-center gap-2">
          <img src="/icons/expand_icon.png" class="w-5 h-5 rotate-180" />
          <span class="text-sm font-medium">COLLAPSE</span>
        </span>
        <span v-else>
          <img src="/icons/expand_icon.png" class="w-5 h-5" />
        </span>
      </button>
    </div>
  </aside>

  <!-- ✅ Logout Confirmation Modal -->
  <Modal :show="showLogoutConfirm" @close="showLogoutConfirm = false" max-width="md">
    <div class="p-6 text-center">
      <h2 class="text-lg font-semibold mb-3">Confirm Logout</h2>
      <p class="text-gray-600 mb-6">Are you sure you want to log out?</p>

      <div class="flex justify-center gap-3">
        <button @click="showLogoutConfirm = false" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg">
          Cancel
        </button>
        <button @click="confirmLogout" class="px-5 py-2 bg-neutral hover:bg-dark text-white rounded-lg">
          Log Out
        </button>
      </div>
    </div>
  </Modal>
</template>

<style scoped>
aside {
  transition: width 0.3s ease-in-out;
}
</style>

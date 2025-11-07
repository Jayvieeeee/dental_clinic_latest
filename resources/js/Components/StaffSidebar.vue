<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const page = usePage()
const isExpanded = ref(true)

const menuItems = [
  {
    id: 'dashboard',
    label: 'HOME',
    href: '/staff/dashboard',
    icon: 'home'
  },
  {
    id: 'appointments',
    label: 'APPOINTMENTS',
    href: '/staff/appointments',
    icon: 'appointments'
  },
  {
    id: 'patients',
    label: 'PATIENTS',
    href: '/staff/patients',
    icon: 'patients'
  },
    {
    id: 'payments',
    label: 'PAYMENTS',
    href: '/staff/payments',
    icon: 'payments'
  },
  {
    id: 'feedback',
    label: 'FEEDBACK',
    href: '/staff/feedback',
    icon: 'feedback_admin'
  },
  {
    id: 'staff',
    label: 'MANAGE STAFF ACCOUNT',
    href: '/staff/manageStaffAccount',
    icon: 'manage_staff_acc'
  },
  {
    id: 'profile',
    label: 'PROFILE',
    href: '/staff/profile',
    icon: 'profile_admin'
  }
]

const isActive = (href) => {
  return page.url === href || page.url.startsWith(href + '/')
}

const handleLogout = () => {
  router.post('/logout')
}

const toggleSidebar = () => {
  isExpanded.value = !isExpanded.value
}
</script>

<template>
  <!-- Sidebar -->
  <aside
    :class="[
      'h-screen sticky top-0 left-0 bg-light/50 flex flex-col  border-r transition-all duration-300 ease-in-out overflow-hidden',
      isExpanded ? 'w-64' : 'w-20'
    ]"
  >
    <!-- Logo Section -->
    <div class="flex items-center justify-center p-6 flex-shrink-0">
      <div class="flex items-center gap-3 overflow-hidden">
        <div class="flex items-center justify-center flex-shrink-0">
          <img 
            v-if="isExpanded"
            src="/icons/logo.png" 
            alt="District Smiles Dental Center" 
            class="h-13 lg:h-16 my-4 object-contain"
          >
          <img 
            v-else
            src="/images/tab_icon.svg" 
            alt="District Smiles" 
            class="h-10 w-10 object-contain"
          >
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 py-4 px-2 space-y-2 overflow-y-auto overflow-x-hidden">
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
        <img
          :src="`/icons/${item.icon}.png`"
          :alt="item.label"
          class="w-5 h-5 object-contain flex-shrink-0"
        />
        <Transition
          enter-active-class="transition-opacity duration-300"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="transition-opacity duration-300"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <span v-if="isExpanded" class="text-sm font-medium text-left whitespace-nowrap overflow-hidden text-ellipsis">
            {{ item.label }}
          </span>
        </Transition>
        <div
          v-if="!isExpanded"
          class="absolute left-16 bg-cyan-900 text-white px-2 py-1 rounded text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50"
        >
          {{ item.label }}
        </div>
      </Link>
    </nav>

    <!-- Logout Button -->
    <div class="border-t border-cyan-200 p-3 flex-shrink-0">
      <button
        @click="handleLogout"
        :class="[
          'w-full flex items-center gap-4 px-4 py-3 rounded-lg text-black hover:bg-neutral transition-colors relative group'
        ]"
      >
        <img
          src="/icons/logout.png"
          alt="Logout"
          class="w-5 h-5 object-contain flex-shrink-0"
        />
        <Transition
          enter-active-class="transition-opacity duration-300"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="transition-opacity duration-300"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <span v-if="isExpanded" class="text-sm font-medium whitespace-nowrap">LOGOUT</span>
        </Transition>
        <div
          v-if="!isExpanded"
          class="absolute left-16 bg-cyan-900 text-black px-2 py-1 rounded text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50"
        >
          LOGOUT
        </div>
      </button>
    </div>

    <!-- Collapse/Expand Toggle -->
    <div class="border-t border-cyan-200 p-3 flex-shrink-0">
      <button
        @click="toggleSidebar"
        class="w-full flex items-center justify-center gap-4 px-4 py-3 rounded-lg text-black hover:bg-neutral transition-colors"
      >
      <span v-if="isExpanded" class="flex items-center gap-2">
          <img 
            src="/icons/expand_icon.png" 
            alt="Collapse" 
            class="w-5 h-5 flex-shrink-0 transform rotate-180 transition-transform duration-300" 
          />
          <span class="text-sm font-medium whitespace-nowrap">COLLAPSE</span>
        </span>
        <span v-else>
          <img 
            src="/icons/expand_icon.png" 
            alt="Expand" 
            class="w-5 h-5 flex-shrink-0 transition-transform duration-300" 
          />
        </span>
      </button>
    </div>
  </aside>
</template>

<style scoped>
/* Smooth width transition */
aside {
  transition: width 0.3s ease-in-out;
}
</style>
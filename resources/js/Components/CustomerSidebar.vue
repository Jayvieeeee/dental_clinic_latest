<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'

const page = usePage()
const isSidebarOpen = ref(false)
const isMobile = ref(false)

const menuItems = [
  {
    id: 'home',
    label: 'HOME',
    href: '/dashboard',
    icon: '/icons/home.png'
  },
  {
    id: 'schedule',
    label: 'SCHEDULE APPOINTMENT',
    href: '/appointments/schedule',
    icon: '/icons/sched.png'
  },
  {
    id: 'appointments',
    label: 'APPOINTMENTS',
    href: '/appointments',
    icon: '/icons/view_appointment.png'
  },
  {
    id: 'history',
    label: 'APPOINTMENT HISTORY',
    href: '/appointments/history',
    icon: '/icons/history.png'
  },
  {
    id: 'feedback',
    label: 'FEEDBACK',
    href: '/feedback',
    icon: '/icons/feedback.png'
  },
  {
    id: 'profile',
    label: 'PROFILE',
    href: '/profile',
    icon: '/icons/profile.png'
  }
]

const checkScreenSize = () => {
  isMobile.value = window.innerWidth < 1024
  if (!isMobile.value) {
    isSidebarOpen.value = false
  }
}

onMounted(() => {
  checkScreenSize()
  window.addEventListener('resize', checkScreenSize)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkScreenSize)
})

const isActive = (href) => {
  // Special handling for home/dashboard route
  if (href === '/dashboard') {
    return page.url === href || page.url === '/' || page.url === '/home'
  }
  return page.url === href || page.url.startsWith(href + '/')
}

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
}

const closeSidebar = () => {
  isSidebarOpen.value = false
}

const handleLogout = () => {
  router.post('/logout')
}
</script>

<template>
  <!-- Mobile Menu Button - Only show on mobile -->
  <div v-if="isMobile" class="fixed top-4 left-4 z-50">
    <button 
      @click="toggleSidebar"
      class="flex flex-col justify-center items-center w-10 h-10 bg-white rounded-lg shadow-lg p-2 focus:outline-none"
      aria-label="Toggle menu"
    >
      <span 
        :class="isSidebarOpen ? 'rotate-45 translate-y-2' : ''"
        class="block w-6 h-0.5 bg-gray-800 transition-transform duration-300"
      ></span>
      <span 
        :class="isSidebarOpen ? 'opacity-0' : 'opacity-100'"
        class="block w-6 h-0.5 bg-gray-800 mt-1.5 transition-opacity duration-300"
      ></span>
      <span 
        :class="isSidebarOpen ? '-rotate-45 -translate-y-2' : ''"
        class="block w-6 h-0.5 bg-gray-800 mt-1.5 transition-transform duration-300"
      ></span>
    </button>
  </div>

  <!-- Overlay for mobile - Only show when sidebar is open on mobile -->
  <Transition
    enter-active-class="transition-opacity duration-300"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity duration-300"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div 
      v-if="isSidebarOpen && isMobile"
      @click="closeSidebar"
      class="fixed inset-0 bg-black bg-opacity-60 z-40"
    ></div>
  </Transition>

  <!-- Sidebar -->
  <aside 
    :class="[
      'h-screen w-80 font-rem bg-gradient-to-b from-white to-neutral flex flex-col shadow-2xl border rounded-r-lg',
      isMobile 
        ? `fixed top-0 left-0 z-50 transition-transform duration-300 ${isSidebarOpen ? 'translate-x-0' : '-translate-x-full'}`
        : 'sticky top-0 left-0'
    ]"
  >
    <!-- Logo Section -->
    <div class="p-6">
      <div class="flex items-center justify-between lg:justify-center">
        <img src="/icons/logo.png" alt="District Smiles Dental Center" class="h-16 lg:h-20 my-4">
        <!-- Close button for mobile only -->
        <button 
          v-if="isMobile"
          @click="closeSidebar"
          class="text-gray-800 hover:text-gray-600 focus:outline-none"
          aria-label="Close menu"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Menu Items -->
    <nav class="flex-1 py-3 px-2 overflow-y-auto">
      <Link
        v-for="item in menuItems"
        :key="item.id"
        :href="item.href"
        @click="isMobile && closeSidebar()"
        :class="[
          'w-full flex items-center gap-2 lg:gap-3 px-4 lg:px-6 py-3 lg:py-3 mb-2 text-left relative',
          isActive(item.href)
            ? 'text-dark before:absolute before:left-0 before:top-0 before:bottom-0 before:w-1 before:bg-dark before:rounded-r'
            : 'text-black'
        ]"
      >
        <img 
          :src="item.icon" 
          :alt="item.label"
          :class="[
            'w-5 h-5 lg:w-6 lg:h-6 object-contain flex-shrink-0',
            isActive(item.href) ? 'brightness-0' : 'brightness-100'
          ]"
        >
        <span 
          :class="[
            'font-medium text-xs lg:text-sm',
            isActive(item.href) ? 'font-bold' : ''
          ]"
        >
          {{ item.label }}
        </span>
      </Link>
    </nav>

    <!-- Logout Button -->
    <div class="p-2 pb-6">
      <button
        @click="handleLogout"
        class="w-full flex items-center gap-3 lg:gap-4 px-4 lg:px-6 py-3 lg:py-4 text-left text-black"
      >
        <img 
          src="/icons/logout.png" 
          alt="Logout"
          class="w-5 h-5 lg:w-6 lg:h-6 object-contain flex-shrink-0"
        >
        <span class="font-medium text-xs lg:text-sm">
          LOGOUT
        </span>
      </button>
    </div>
  </aside>
</template>
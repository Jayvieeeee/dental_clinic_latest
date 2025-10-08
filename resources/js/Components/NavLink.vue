<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

const page = usePage()
const isSidebarOpen = ref(false)

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
}

const closeSidebar = () => {
  isSidebarOpen.value = false
}
</script>

<template>
  <header class="bg-white shadow-sm font-rem">

    <div class="hidden md:flex container mx-auto justify-between items-center py-2 px-4">
      <div class="flex items-center space-x-2">
        <img src="/icons/logo.svg" alt="District Smiles Dental Center" class="h-[80px]">
      </div>

      <nav class="flex space-x-10 text-sm font-semibold uppercase">
        <Link href="/" :class="page.url === '/' ? 'text-dark' : 'hover:text-dark'">
          HOME
        </Link>
        <Link href="/services" :class="page.url.startsWith('/services') ? 'text-dark' : 'hover:text-dark'">
          OUR SERVICES
        </Link>
        <Link href="/faqs" :class="page.url.startsWith('/faqs') ? 'text-dark' : 'hover:text-dark'">
          FAQs
        </Link>
        <Link href="/testimonials" :class="page.url.includes('/testimonials') ? 'text-dark' : 'hover:text-dark'">
          TESTIMONIALS
        </Link>
        <Link href="/contactUs" :class="page.url.includes('/contactUs') ? 'text-dark' : 'hover:text-dark'">
          CONTACT US
        </Link>
      </nav>

      <Link href="/login" class="bg-dark text-white text-medium text-center px-10 py-2 rounded-full font-semibold uppercase hover:bg-light transition">
        Login
      </Link>
    </div>

    <div class="md:hidden flex justify-start items-center py-4 px-4 bg-white">

      <button 
        @click="toggleSidebar"
        class="flex flex-col justify-center items-center w-10 h-10 space-y-2 focus:outline-none rounded-lg p-2"
        aria-label="Toggle menu"
      >
        <span 
          :class="isSidebarOpen ? 'rotate-45 translate-y-3' : ''"
          class="block w-8 h-1 bg-gray-800 transition-transform duration-300"
        ></span>
        <span 
          :class="isSidebarOpen ? 'opacity-0' : 'opacity-100'"
          class="block w-8 h-1 bg-gray-800 transition-opacity duration-300"
        ></span>
        <span 
          :class="isSidebarOpen ? '-rotate-45 -translate-y-3' : ''"
          class="block w-8 h-1 bg-gray-800 transition-transform duration-300"
        ></span>
      </button>
    </div>
    
    <div class="border border-black my-0"></div>

    <Transition
      enter-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-300"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="isSidebarOpen"
        @click="closeSidebar"
        class="fixed inset-0 bg-black bg-opacity-60 z-40 md:hidden"
      ></div>
    </Transition>

    <Transition
      enter-active-class="transition-transform duration-300"
      enter-from-class="-translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-300"
      leave-from-class="translate-x-0"
      leave-to-class="-translate-x-full"
    >
      <div 
        v-if="isSidebarOpen"
        class="fixed top-0 left-0 h-full w-72 bg-white shadow-2xl z-50 md:hidden"
      >
        <div class="flex justify-between items-center p-6 border-b border-black">
          <img src="/icons/logo.png" alt="District Smiles Dental Center" class="h-16 lg:h-20">
          <button 
            @click="closeSidebar"
            class="text-dark hover:text-gray-600 focus:outline-none"
            aria-label="Close menu"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Mobile Navigation -->
        <nav class="flex flex-col px-6 py-6 text-sm font-semibold uppercase">
          <Link 
            href="/" 
            @click="closeSidebar"
            :class="page.url === '/' ? 'text-dark bg-gray-100' : 'text-gray-700 hover:text-dark hover:bg-gray-50'"
            class="py-3 px-4 rounded-lg transition-colors duration-200 mb-2"
          >
            HOME
          </Link>
          <Link 
            href="/services" 
            @click="closeSidebar"
            :class="page.url.startsWith('/services') ? 'text-dark bg-gray-100' : 'text-gray-700 hover:text-dark hover:bg-gray-50'"
            class="py-3 px-4 rounded-lg transition-colors duration-200 mb-2"
          >
            OUR SERVICES
          </Link>
          <Link 
            href="/faqs" 
            @click="closeSidebar"
            :class="page.url.startsWith('/faqs') ? 'text-dark bg-gray-100' : 'text-gray-700 hover:text-dark hover:bg-gray-50'"
            class="py-3 px-4 rounded-lg transition-colors duration-200 mb-2"
          >
            FAQs
          </Link>
          <Link 
            href="/testimonials" 
            @click="closeSidebar"
            :class="page.url.includes('/testimonials') ? 'text-dark bg-gray-100' : 'text-gray-700 hover:text-dark hover:bg-gray-50'"
            class="py-3 px-4 rounded-lg transition-colors duration-200 mb-2"
          >
            TESTIMONIALS
          </Link>
          <Link 
            href="/contactUs" 
            @click="closeSidebar"
            :class="page.url.includes('/contactUs') ? 'text-dark bg-gray-100' : 'text-gray-700 hover:text-dark hover:bg-gray-50'"
            class="py-3 px-4 rounded-lg transition-colors duration-200 mb-2"
          >
            CONTACT US
          </Link>
          <Link 
            href="/login" 
            @click="closeSidebar"
            class="bg-dark text-white text-center px-6 py-3 rounded-full hover:bg-opacity-90 transition mt-6"
          >
            Login
          </Link>
        </nav>
      </div>
    </Transition>
  </header>
</template>
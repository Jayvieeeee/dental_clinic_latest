<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue"
import { Head } from "@inertiajs/vue3"
import GuestLayout from "@/Layouts/GuestLayout.vue"
import { testimonials } from "@/data/testimonials.js"

// ---- Testimonials logic (your existing code) ----
const currentIndex = ref(0)
const visibleCards = 3

function nextSlide() {
  if (currentIndex.value < testimonials.length - visibleCards) {
    currentIndex.value++
  }
}
function prevSlide() {
  if (currentIndex.value > 0) {
    currentIndex.value--
  }
}
function goToSlide(index) {
  currentIndex.value = index
}
const visibleTestimonials = computed(() =>
  testimonials.slice(currentIndex.value, currentIndex.value + visibleCards)
)

const imageIndex = ref(0)

const images = [
  "/images/photo1.jpg",
  "/images/photo2.jpg",
  "/images/photo3.jpg",
  "/images/photo4.jpg",
  "/images/photo5.jpg"
]

let intervalId = null
onMounted(() => {
  intervalId = setInterval(() => {
    if (imageIndex.value < images.length - 3) {
      imageIndex.value++
    } else {
      imageIndex.value = 0
    }
  }, 3000) // slide every 3s
})
onUnmounted(() => clearInterval(intervalId))

</script>

<template>
  <Head title="Testimonials" />
  <GuestLayout>
    <section class="py-12 bg-white font-rem">
      <div class="container mx-auto text-center">
        <!-- Title -->
        <h2 class="text-3xl font-bold text-dark uppercase">Success Stories</h2>
        <p class="text-dark text-xl font-semibold mb-10">We appreciate your support!</p>

        <!-- Testimonials Carousel -->
        <div class="relative">
          <div class="grid md:grid-cols-3 gap-8 items-stretch">
            <div
              v-for="(testimonial, idx) in visibleTestimonials"
              :key="testimonial.id"
              class="rounded-2xl shadow-lg p-6 flex flex-col items-center justify-center text-center transition-colors duration-300 h-[260px]"
              :class="[ idx === 1 ? 'bg-light text-black' : 'bg-neutral text-white']"
            >
              <p class="italic mb-4 text-sm leading-snug line-clamp-6">
                “ {{ testimonial.text }} ”
              </p>
              <div class="border-t w-full my-4"
                   :class="idx === 1 ? 'border-black opacity-50' : 'border-white opacity-50'"></div>
              <span class="block font-semibold text-sm leading-tight">
                — {{ testimonial.author }}
              </span>
            </div>
          </div>

          <!-- navigation arrows -->
          <button @click="prevSlide"
            class="absolute -left-6 top-1/2 -translate-y-1/2 z-20 bg-white text-dark w-12 h-12 flex items-center justify-center text-4xl border border-black rounded-full shadow hover:bg-light"
            :disabled="currentIndex === 0">‹</button>
          <button @click="nextSlide"
            class="absolute -right-6 top-1/2 -translate-y-1/2 z-20 bg-white text-dark w-12 h-12 flex items-center justify-center text-4xl border border-black rounded-full shadow hover:bg-light"
            :disabled="currentIndex >= testimonials.length - visibleCards">›</button>
        </div>

        <!-- dots -->
        <div class="flex justify-center mt-6 space-x-2">
          <span
            v-for="(t, index) in testimonials.length - visibleCards + 1"
            :key="index"
            class="w-3 h-3 rounded-full cursor-pointer"
            :class="currentIndex === index ? 'bg-teal-600' : 'bg-gray-300'"
            @click="goToSlide(index)"
          ></span>
        </div>
      </div>
    </section>

    <!-- ===== Image Carousel Section ===== -->
    <section class="py-12 bg-gradient-to-b from-white to-light font-rem">
      <div class="container mx-auto text-center">
        <h2 class="text-3xl font-bold text-dark uppercase mb-6">District Smiles</h2>

    <div class="relative w-full max-w-5xl mx-auto overflow-hidden">
      <!-- Track -->
      <div
        class="flex transition-transform duration-700"
        :style="{ transform: `translateX(-${imageIndex * (100/3)}%)` }"
      >
        <div
          v-for="(img, idx) in images"
          :key="idx"
          class="w-1/3 flex-shrink-0 px-2"
        >
          <img
            v-lazy="img"
            alt="Clinic Image"
            class="w-full h-3/4 object-cover rounded-2xl shadow-lg overflow-hidden"
          />
        </div>
      </div>
    </div>
    <h3 class="text-3xl m-12 font-extrabold text-dark mt-6">Thank you for trusting Distric Smile Dental Center</h3>
  </div>
</section>

  </GuestLayout>
</template>

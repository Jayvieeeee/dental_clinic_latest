<script setup>
import { ref } from "vue";
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { faqs as faqData } from "@/data/faqs.js";
import { Head } from '@inertiajs/vue3'

const faqs = ref(faqData);

// Helper: chunk array into groups of 4
const chunkArray = (arr, size) => {
  const result = [];
  for (let i = 0; i < arr.length; i += size) {
    result.push(arr.slice(i, i + size));
  }
  return result;
};

const toggleFaq = (category, index) => {
  faqs.value[category][index].open = !faqs.value[category][index].open;
};
</script>

<template>
  <Head title="FAQs" />
  <GuestLayout>
    <section class="py-12 px-6 md:px-16">
      <h1 class="text-1xl md:text-3xl font-black text-center text-dark mb-4">
        FREQUENTLY ASKED QUESTIONS
      </h1>
      <p class="text-center text-lg font-semibold mb-10">
        Got questions? Don’t worry, we’ve got you! <br />
        Here is our dental clinic frequently asked questions.
      </p>

      <!-- General Information -->
      <div class="grid md:grid-cols-2 gap-10 mb-16 items-start grid-flow-row-dense">
        <div>
          <h2 class="text-3xl text-dark font-semibold mb-6">General Information</h2>
          <div
            v-for="(faq, index) in faqs['General Information']"
            :key="index"
            class="border-2 shadow-md border-dark rounded-xl mb-4 p-4 cursor-pointer transition-all"
            @click="toggleFaq('General Information', index)"
          >
            <div class="flex justify-between items-center">
              <h3 class="font-semibold text-xl">{{ faq.question }}</h3>
              <div
                class="transition-transform duration-300"
                :class="{ 'rotate-180': faq.open }"
              >
                <img src="/icons/arrow.svg" alt="Arrow" class="w-8 h-8" />
              </div>
            </div>
            <p v-show="faq.open" class="mt-3 border-t-2 border-black pt-3">
              {{ faq.answer }}
            </p>
          </div>
        </div>

        <!-- Appointment -->
        <div>
          <h2 class="text-3xl text-dark font-semibold mb-6">Appointment</h2>
          <div
            v-for="(faq, index) in faqs['Appointment']"
            :key="index"
            class="border-2 shadow-md border-dark rounded-xl mb-4 p-4 cursor-pointer transition-all"
            @click="toggleFaq('Appointment', index)"
          >
            <div class="flex justify-between items-center">
              <h3 class="font-semibold text-xl">{{ faq.question }}</h3>
              <div
                class="transition-transform duration-300"
                :class="{ 'rotate-180': faq.open }"
              >
                <img src="/icons/arrow.svg" alt="Arrow" class="w-8 h-8" />
              </div>
            </div>
            <p v-show="faq.open" class="mt-3 border-t-2 border-black pt-3">
              {{ faq.answer }}
            </p>
          </div>
        </div>
      </div>


      <!-- Dental Services -->
      <div>
        <h2 class="text-3xl text-dark font-semibold mb-6">Dental Services</h2>

        <div class="grid md:grid-cols-2 gap-6 items-start grid-flow-row-dense">
  <div
    v-for="(faq, index) in faqs['Dental Services']"
    :key="index"
    class="border-2 shadow-md border-dark rounded-xl p-4 cursor-pointer transition-all"
    @click="toggleFaq('Dental Services', index)"
  >
    <div class="flex justify-between items-center">
      <h3 class="font-semibold text-xl">{{ faq.question }}</h3>
      <div
        class="transition-transform duration-300"
        :class="{ 'rotate-180': faq.open }"
      >
        <img src="/icons/arrow.svg" alt="Arrow" class="w-8 h-8" />
      </div>
    </div>
    <p v-show="faq.open" class="mt-3 border-t-2 border-black pt-3">
      {{ faq.answer }}
    </p>
  </div>
</div>

      </div>
    </section>
  </GuestLayout>
</template>

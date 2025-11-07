<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { Link } from '@inertiajs/vue3';
import { Head } from "@inertiajs/vue3"
</script>

<template>
    <Head title="Home" />
  <CustomerLayout>
    <div class="min-h-screen  flex items-center justify-center py-10 px-6 font-rem">
      <div class="shadow-xl bg-[#EFEFEF]/20 rounded-2xl p-10 w-full max-w-5xl flex flex-col justify-center">

        <h1 class="text-3xl font-extrabold text-dark mb-10 uppercase ">
          HOME
        </h1>

        <div class="flex flex-col lg:flex-row items-center justify-center gap-10 w-full">
          <!-- Image -->
          <div class="flex justify-center w-full lg:w-1/2">
            <img
              v-lazy="'/images/customer_homepage.jpg'"
              alt="Dental Clinic"
              class="rounded-2xl shadow-lg w-full max-w-md object-cover"
            />
          </div>

          <div class="flex flex-col items-center text-center w-full lg:w-1/2 mb-12 mx-6">
            <h2 class="text-3xl font-semibold text-dark mb-12">
              District Smiles Dental Center welcomes you!
            </h2>
            <p class="text-lg text-gray-700 mb-8 leading-relaxed max-w-md">
              Let’s create a healthy, confident smile together. Schedule your appointment today
              your comfort and well-being are our top priority.
            </p>
            <Link
              href="/schedule-appointment"
              class="bg-dark hover:bg-light text-white text-lg font-semibold w-44 py-3 rounded-full transition-all duration-300 shadow-md">
              BOOK NOW
            </Link>
          </div>
        </div>
        <div class="my-20 items-center text-center justify-center">  
          <h2 class="font-bold text-2xl text-dark">See your past visit here</h2>
        </div>
            <Link
            href="/customer/appointments"
            class=" bg-dark hover:bg-light block mx-auto text-white text-center text-lg font-semibold w-64 py-3.5 rounded-full transition-all duration-300 shadow-md">
            Appointment History
          </Link>
      </div>
    </div>
  </CustomerLayout>
</template>

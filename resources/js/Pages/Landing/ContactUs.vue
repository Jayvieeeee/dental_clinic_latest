<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head } from '@inertiajs/vue3'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { ref } from 'vue'

// form state
const form = ref({
  name: '',
  email: '',
  message: ''
})

const submit = () => {
  // Later you can send with axios or Inertia post
  console.log('Form submitted:', form.value)
}
</script>

<template>
  <Head title="Contact Us" />

  <GuestLayout>
   <section class="py-12 px-6 md:px-12 flex justify-center font-rem">
    <div class="max-w-5xl w-full grid md:grid-cols-2 gap-20 items-start mt-12">
    <!-- Contact Info -->
    <div class="space-y-8 ">
      <h2 class="font-bold text-5xl text-dark mb-12">Contact Us</h2>
      <div class="flex items-start space-x-4">
        <div class="bg-neutral p-5 rounded-2xl text-white">
          <img src="/icons/location.svg" alt="Location" class="w-8 h-6" />
        </div>
        <div>
          <h2 class="font-bold text-lg text-dark">Location</h2>
          <p class="font-medium text-sm">
            391 Odonas bldg F. Roxas street corner 10th Avenue Gracepark West,
            Caloocan, Philippines, 1406
          </p>
        </div>
      </div>

      <div class="flex items-start space-x-4">
        <div class="bg-neutral p-5 rounded-2xl text-white">
          <img src="/icons/mail.svg" alt="Email" class="w-8 h-6" />
        </div>
        <div>
          <h2 class="font-bold text-lg text-dark">Email Address</h2>
          <p class="font-medium text-sm">districtsmilesdentalcenter@gmail.com</p>
        </div>
      </div>

      <div class="flex items-start space-x-4">
        <div class="bg-neutral p-5 rounded-2xl text-white">
          <img src="/icons/phone.svg" alt="Phone" class="w-8 h-6" />
        </div>
        <div>
          <h2 class="font-bold text-lg text-dark">Phone Number</h2>
          <p class="font-medium text-sm">0915 533 5086</p>
        </div>
      </div>
    </div>

    <!-- Contact Form -->
     <div class="bg-neutral p-8 rounded-2xl text-white max-w-md w-full mx-auto">
      <h2 class="text-xl font-bold mb-2">Contact Form</h2>
      <p class="mb-6">Do you have inquiries? You can send them to us.</p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <TextInput
            id="name"
            v-model="form.name"
            type="text"
            class="mt-1 block w-full"
            placeholder="Your Name"
            required
          />
        </div>

        <div>
          <TextInput
            id="email"
            v-model="form.email"
            type="email"
            class="mt-1 block w-full"
            placeholder="Your Email"
            required
          />
        </div>

        <div>
          <textarea
            id="message"
            v-model="form.message"
            rows="4"
            class="mt-1 block w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-black"
            placeholder="Your Message"
            required
          ></textarea>
        </div>

      <div class="flex items-center justify-center h-full">
      <PrimaryButton class="font-medium text-xs bg-light text-black hover:bg-light-gray">
        Send Message
      </PrimaryButton>
      </div>

      </form>
    </div>
  </div>
</section>

   <div class="w-2/3 h-96 shadow-lg my-12 mx-auto relative border border-black">
  <iframe
    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3860.072142324708!2d120.97891037457389!3d14.651846175821024!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b5120fa4abef%3A0x33ce9828c9f64001!2sDistrict%20Smiles%20Dental%20Center!5e0!3m2!1sen!2sph!4v1758281032117!5m2!1sen!2sph"
    class="w-full h-full relative z-10"
    style="border:0;"
    allowfullscreen=""
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade">
  </iframe>
</div>



  </GuestLayout>
</template>

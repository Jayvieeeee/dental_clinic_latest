<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head } from '@inertiajs/vue3'
</script>

<template >
    <Head title="Welcome" />
    <GuestLayout>
<!--landing-page-->
<section class="bg-gradient-to-b from-neutral to-light py-16 text-center h-screen font-rem font-bold overflow-hidden">
  <div class="container mx-auto text-white font-bold">
    <h1 class="text-5xl md:text-6xl ">Better Teeth, Better Health</h1>
    <p class="mt-4 font-semibold text-xl">
      Experience dental care with modern <br>equipment, quality, and a gentle touch.
    </p>
    <PrimaryButton class="mt-8 bg-white">
         BOOK NOW
    </PrimaryButton>

    <div class="mt-10 overflow-hidden rounded-t-3xl shadow-lg mx-auto  w-[1200px]">
        <img v-lazy="'/images/clinic.jpg'" alt="Dental Clinic" class="w-full max-h-[430px] object-cover">
    </div>
  </div>
</section>

<!--about-us-->
<section class="bg-white text-center">
    <div class="mt-[120px] text-6xl font-bold text-dark">ABOUT US</div>
    <p class="my-20 text-xl">
        Welcome to Distric Smiles Dental Center in Southc Caloocan!<br>
        Creating Confident, and healthy smiles for our community is a goal that our team is truly passionate<br>
        about. When you visit out clinic, you will come across a welcoming and accomodating environment<br>
        where we put your comfort first. To make sure you feel properly taken care of, we genuinely believe<br>
        in paying attention to your particular needs and adjusting our appoarch accordingly.
    </p>
     <div class="my-10 border-t-2 border-dark w-1/2 mx-auto"></div>
     <p class="mt-20 text-xl">
        The dental clinic was founded in April 2021 by two qualified dentist who own and currently operate<br>
        the practice. Through their combined skills and common goals, they aimed to provide the local<br>
        community with a reasonably priced, quality dental care. This clinic with a patient-centered<br>
        approach, priotizing preventive, oral health, and affordable care.
     </p>
     <p class="mt-10 text-xl mb-[100px]">
        Despite the fact that organization is relatively new, it is steadily growing as it strives to become<br>
        an accredited and well-known provider of dental services in the region. Its goal is to deliver<br>
        exceptional dental care while maintaining the safety, comfort, and satisfaction of patients.
     </p>
</section>

<!--services-->
<section class="bg-light py-20">
  <div class="text-6xl font-bold text-dark text-center mb-16">
    OUR SERVICES
  </div>

  <div class="container mx-auto grid gap-10 px-6 md:grid-cols-2 lg:grid-cols-3">

    <div class="bg-neutral text-white rounded-xl shadow-xl shadow-black/30 p-8 text-center">
      <h3 class="text-3xl font-bold mb-4">General Dentistry</h3>
      <p class="mb-4">
        Routine services meant to maintain oral<br> health and prevent issues.
      </p>
      <ul class="text-left space-y-1 pl-8 text-base leading-tight">
        <li><span class="font-semibold">Mouth examinations</span></li>
        <li><span class="font-semibold">Oral prophylaxis (cleaning)</span></li>
      </ul>
    </div>

    <div class="bg-neutral text-white rounded-xl shadow-xl shadow-black/30 p-8 text-center">
      <h3 class="text-3xl font-bold mb-4">Resotorative Care</h3>
      <p class="mb-4">
        Procedures that repair minor<br> damage or decay.
      </p>
      <ul class="text-left space-y-1 pl-8 text-base leading-tight">
        <li><span class="font-semibold">Tooth Restoration (pasta)</span></li>
        <li><span class="font-semibold">Tooth Extraction</span></li>
      </ul>
    </div>
    
    <div class="bg-neutral text-white rounded-xl shadow-xl shadow-black/30 p-8 text-center">
      <h3 class="text-3xl font-bold mb-2">Replacing & Repairing</h3>
      <p class="mb-2">
        More complex repairs or replacement<br>
        targeting structural or functional restoration.
      </p>
      <ul class="text-left space-y-1 pl-8 text-base leading-tight">
        <li><span class="font-semibold">Crowns and Bridges</span></li>
        <li><span class="font-semibold">Partial and Complete Dentures</span></li>
        <li><span class="font-semibold">Rpot Canal and treatment</span></li>
        <li><span class="font-semibold">Wisdom Tooth Removal</span></li>
      </ul>
    </div>

    <div class="bg-neutral text-white rounded-xl shadow-lg p-8 text-center">
      <h3 class="text-3xl font-bold mb-4">Cosmetic Dentistry</h3>
      <p class="mb-4">Procedures primarily aimed at enhancing appearance.</p>
      <ul class="text-left space-y-1 pl-8 text-base leading-tight">
        <li><span class="font-semibold">Dental Veeners</span></li>
        <li><span class="font-semibold">Teeth Whitening</span></li>
         <li><span class="font-semibold">Crowns and Bridges</span></li>
      </ul>
    </div>

    <div class="bg-neutral text-white rounded-xl shadow-lg p-8 text-center">
      <h3 class="text-3xl font-bold mb-4">Orthodontics</h3>
      <p class="mb-4">
        Correct alignment and bite issues<br> using appliances
      </p>
      <ul class="text-left space-y-1 pl-8 text-base leading-tight">
        <li><span class="font-semibold">Metal Braces</span></li>
        <li><span class="font-semibold">Ceramic Braces</span></li>
         <li><span class="font-semibold">SWLF Braces</span></li>
      </ul>
    </div>
    
    <div class="bg-neutral text-white rounded-xl shadow-lg p-8 text-center">
      <h3 class="text-3xl font-bold mb-2">Dental X-Ray</h3>
      <p class="mb-2">
        Diagnostic tools to visualize oral<br> structures and support diagnosis.
      </p>
      <ul class="text-left space-y-1 pl-8 text-base leading-tight">
        <li><span class="font-semibold">Digital panoramic x-ray</span></li>
        <li><span class="font-semibold">DIgital cephalometric x-ray</span></li>
        <li><span class="font-semibold">TMJ x-ray</span></li>
         <li><span class="font-semibold">Periapical x-ray</span></li>
      </ul>
    </div>
  </div>
</section>

<!--success-stories-->
<section class="bg-white min-h-screen flex items-center justify-center">
  <div class="bg-light w-full py-20">
    <div class="container mx-auto px-6 text-center">
      <h2 class="text-5xl md:text-6xl font-bold text-dark mb-16">SUCCESS STORIES</h2>

      <!-- Grid -->
      <div class="grid gap-12 md:grid-cols-3">
        
        <div class="flex flex-col items-center">
          <p class="italic text-lg leading-snug">
            “Highly Recommended, the staff and dentist are very kind.”
          </p>
          <div class="w-1/2 border-t-2 border-dark my-4"></div>
          <p class="font-medium">Rowel Tampico</p>
        </div>

        <div class="flex flex-col items-center">
          <p class="italic text-lg leading-snug">
            “Doc is very responsive and very accommodating and the reception is also very attentive. I avail Panoramic x-ray and consult.”
          </p>
          <div class="w-1/2 border-t-2 border-dark my-4"></div>
          <p class="font-medium">Rachelle Rebucas</p>
        </div>

        <div class="flex flex-col items-center">
          <p class="italic text-lg leading-snug">
            “Very accommodating and ensures that you are very comfortable and at ease. The dentist is also very informative.”
          </p>
          <div class="w-1/2 border-t-2 border-dark my-4"></div>
          <p class="font-medium">Clarizze Kabigting</p>
        </div>
      </div>

      <div class="mt-16">
        <PrimaryButton class="bg-white font-medium text-lg">
          See More
        </PrimaryButton>
      </div>
    </div>
  </div>
</section>
</GuestLayout>
</template>
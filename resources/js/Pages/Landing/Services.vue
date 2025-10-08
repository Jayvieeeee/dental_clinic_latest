<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
</script>

<style scoped>
html {
  scroll-behavior: smooth;
}
</style>

<template>
    <Head title="Our Services" />
    <GuestLayout>
  <section class=" font-rem bg-gradient-to-b from-light to-neutral py-16 px-6 md:px-12">
    <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-10 min-h-[400px]">
      <!-- Left content -->
      <div class="flex flex-col justify-center items-center font-normal text-center md:text-left space-y-6">
        <h2 class="text-1xl md:text-2xl font-extrabold text-dark pb-4">OUR SERVICES</h2>
        <p class=" text-lg font-medium text-center pb-6 leading-relaxed">
          Here at District Smiles Dental Center, we deliver <br>
           quality, gentle and expert dental care from <br>
            cleanings to crowns, in a welcoming and caring<br>
             environment.
        </p>
        <p class="text-lg font-medium">Schedule your visit today</p>
        <Link href="/login" class="bg-white text-black px-10 py-4 rounded-full font-semibold shadow outline-2 outline-offset-2 outline-dark hover:text-dark transition w-fit mx-auto md:mx-0">
          BOOK NOW
        </Link>
      </div>

      <!-- Right image -->
      <div class="flex justify-center items-center">
        <img v-lazy="'/images/service.jpg'" alt="Dentist" class="rounded-2xl shadow-lg max-w-full">
      </div>
    </div>

    <!-- Services grid -->
    <div class="max-w-6xl mx-auto mt-12 grid grid-cols-2 md:grid-cols-6 gap-4">
      <div class="bg-light rounded-xl p-8 text-center font-medium content-center text-gray-900 shadow transition-all duration-300 hover:-translate-y-2 hover:shadow-lg">
        <a href="#generalDentistry">GENERAL DENTISTRY</a>
      </div>
      <div class="bg-light rounded-xl p-8 text-center font-medium content-center text-gray-900 shadow transition-all duration-300 hover:-translate-y-2 hover:shadow-lg">
       <a href="#restorativeCare">RESTORATIVE CARE</a>
      </div>
      <div class="bg-light rounded-xl p-8 text-center font-medium content-center text-gray-900 shadow transition-all duration-300 hover:-translate-y-2 hover:shadow-lg">
        <a href="#replacingAndReparing">REPLACING AND REPAIRING</a>
      </div>
      <div class="bg-light rounded-xl p-8 text-center font-medium content-center text-gray-900 shadow transition-all duration-300 hover:-translate-y-2 hover:shadow-lg">
        <a href="#cosmeticDentistry">COSMETIC DENTISTRY</a>
      </div>
      <div class="bg-light rounded-xl p-8 text-center font-medium content-center text-gray-900 shadow transition-all duration-300 hover:-translate-y-2 hover:shadow-lg">
       <a href="#orthodontics">ORTHODONTICS</a>
      </div>
      <div class="bg-light rounded-xl p-8 text-center font-medium content-center text-gray-900 shadow transition-all duration-300 hover:-translate-y-2 hover:shadow-lg">
       <a href="#dentalXray">DENTAL X-RAY</a>
      </div>
    </div>
  </section>

<!-- General dentistry-->
<section id="generalDentistry" class="relative py-20 px-6 md:px-12 overflow-hidden border-b border-gray-300">
  <!-- Circles -->
  <div class="absolute top-[-6rem] right-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>
  <div class="absolute bottom-[-6rem] left-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>

  <!-- Content -->
  <div class="max-w-6xl mx-auto text-center mb-12 relative z-10">
    <h2 class="text-2xl md:text-3xl font-extrabold text-dark pb-10">
      GENERAL DENTISTRY
    </h2>
  </div>

  <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <!-- Image -->
      <img v-lazy="'/images/pic1.png'" alt="Mouth Examination" class="w-full h-80 object-cover"/>
      <!-- Title -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Mouth Examination</h3>
      </div>
      <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
          A mouth examination (also known as a dental or oral examination) is a
          thorough check-up of your teeth, gums, tongue, and mouth, helping spot
          early signs of issues and keep your oral health on track.
        </p>
      </div>
    </div>

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <!-- Image -->
      <img v-lazy="'/images/pic2.png'" alt="Oral Prophylaxis" class="w-full h-80 object-cover"/>
      <!-- Title -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Oral Prophylaxis</h3>
      </div>
      <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
          Oral prophylaxis, is a professional dental cleaning 
          that removes built-up plaque, tartar, and stains to 
          keep your teeth healthy and your breath fresh.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Restorative Care -->
<section id="restorativeCare" class="relative py-20 px-6 md:px-12 overflow-hidden border-b border-gray-300">
  <!-- Circles -->
  <div class="absolute top-[-6rem] right-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>
  <div class="absolute bottom-[-6rem] left-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>

  <!-- Content -->
  <div class="max-w-6xl mx-auto text-center mb-12 relative z-10">
    <h2 class="text-2xl md:text-3xl font-extrabold text-dark pb-10">
      RESTORATIVE CARE
    </h2>
  </div>

  <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <!-- Image -->
      <img v-lazy="'/images/pic3.png'" alt="Tooth Restoration" class="w-full h-80 object-cover"/>
      <!-- Title -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Tooth Restoration</h3>
      </div>
      <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
            Tooth restoration (pasta) means filling a cavity using
            a tooth-colored resin(composite), applied like paste
            and hardened to repair and protect your tooth.
        </p>
      </div>
    </div>

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <!-- Image -->
      <img v-lazy="'/images/pic4.png'" alt="Tooth Extraction" class="w-full h-80 object-cover"/>
      <!-- Title -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Tooth Extraction</h3>
      </div>
      <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
            Tooth extraction is the dental proceduere of removing
            a tooth from its socket in the jaw. It's often needed
            when a tooth is badly decayed, infected, or causing
            crowding.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Replacing and Repairing -->
<section id="replacingAndReparing" class="relative py-20 px-6 md:px-12 overflow-hidden border-b border-gray-300">
  <!-- Circles -->
  <div class="absolute top-[-6rem] right-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>
  <div class="absolute bottom-[-6rem] left-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>
 
  <!-- Content -->
  <div class="max-w-6xl mx-auto text-center mb-12 relative z-10">
    <h2 class="text-2xl md:text-3xl font-extrabold text-dark pb-10">
      REPLACING AND REPAIRING
    </h2>
  </div>

  <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8 relative z-10">

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <img v-lazy="'/images/pic5.png'" alt="Partial Dentures" class="w-full h-72 object-cover"/>
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Partial Dentures</h3>
            <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
            Partial dentures are custome-made, removable, 
            devices that replace missing teeth to help you chew
            better, speak clearly, and smile confidently, without
            needing surgery.
        </p>
      </div>
      </div>
    </div>

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <img v-lazy="'/images/pic6.png'" alt="Complete Dentures" class="w-full h-72 object-cover"/>
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Complete Dentures</h3>
         <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
            Complete dentures (also known as full dentures) are
            custome-made removable sets of artificial teeth and
            gum-like base, designed to replace all missing teeth,
            restore your smile's look and function.
        </p>
      </div>
      </div>
    </div>

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <img v-lazy="'/images/pic7.png'" alt="Root Canal Treatment" class="w-full h-72 object-cover"/>
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Root Canal Treatment</h3>
         <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
            Root canal treatment removes infected tissus inside 
            the tooth, cleans, and seals it, and restore it, saving
            your natural tooth and relieving pain, often with 
            long-lasting results.
        </p>
      </div>
      </div>
    </div>
  </div>

  <div class="max-w-4xl mx-auto mt-8 grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
   
    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <img v-lazy="'/images/pic8.png'" alt="Wisdom Tooth Removal" class="w-full h-72 object-cover"/>
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Wisdom Tooth Removal</h3>
            <!-- Description -->
         <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
            <p class="text-sm leading-relaxed">
               Wisdom tooth removal (also known as third molar
               extraction) is a common dental surgery to remove
               one or more wisdom teeth when they don't fit well,
               cause problems, or may cause future issues.
            </p>
         </div>
      </div>
    </div>

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <img v-lazy="'/images/pic9.png'" alt="Dental Crowns" class="w-full h-72 object-cover"/>
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Crowns and Bridges</h3>
            <!-- Description -->
        <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
         <p class="text-sm leading-relaxed">
            A crown protects and restore a damaged tooth,
            while a bridge replaces one, or more missing teeth
            and "bridges" the gap using crowns on adjacent
            teeth.
        </p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Cosmetic Dentistry -->
 <section id="cosmeticDentistry" class="relative py-20 px-6 md:px-12 overflow-hidden border-b border-gray-300">
  <!-- Circles -->
  <div class="absolute top-[-6rem] right-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>
  <div class="absolute bottom-[-6rem] left-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>

  <!-- Content -->
  <div class="max-w-6xl mx-auto text-center mb-12 relative z-10">
    <h2 class="text-2xl md:text-3xl font-extrabold text-dark pb-10">
      COSMETIC DENTISTRY
    </h2>
  </div>

  <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <!-- Image -->
      <img v-lazy="'/images/pic10.png'" alt="Dental Veeners" class="w-full h-80 object-cover"/>
      <!-- Title -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Dental Veeners</h3>
      </div>
      <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
            Dental Veeners are thins shells, typically porcelain or
            composite that are bonded to the front of your 
            teeth to cover cosmetic flaws like stains, chips, or
            gaps.
        </p>
      </div>
    </div>

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <!-- Image -->
      <img v-lazy="'/images/pic12.png'" alt="Oral Prophylaxis" class="w-full h-80 object-cover"/>

      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Teeth Whitening</h3>
      </div>
      <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
            Teeth Whitening is a dental procedure that safely
            brightens your natural teeth, removing stains and
            discoloration. It typically uses peroxide-based gels
            activated by light or customs trays.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Orthodontics -->
 <section id="orthodontics" class="relative py-20 px-6 md:px-12 overflow-hidden border-b border-gray-300">
  <!-- Circles -->
  <div class="absolute top-[-6rem] right-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>
  <div class="absolute bottom-[-6rem] left-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>

  <div class="max-w-6xl mx-auto text-center mb-12 relative z-10">
    <h2 class="text-2xl md:text-3xl font-extrabold text-dark">
      ORTHODONTICS
    </h2>
  </div>

  <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8 relative z-10">
    <div class="relative rounded-xl overflow-hidden shadow-lg group">
    <!-- Image -->
    <img v-lazy="'/images/pic13.png'" alt="Metal Braces" class="w-full h-64 object-cover" />

    <!-- Title -->
    <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-dark">Metal Braces</h3>
    </div>

    <!-- Description -->
    <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
        Metal braces are strong, reliable orthodontic devices
        with brackets and wires that gently move your teeth
        into alignment. They also work for most dental issues.
        </p>
    </div>
    </div>

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
    <!-- Image -->
    <img v-lazy="'/images/pic14.png'" alt="Ceramic Braces" class="w-full h-64 object-cover" />

    <!-- Title -->
    <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-dark">Ceramic Braces</h3>
    </div>

    <!-- Description -->
    <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
            Ceramic braces are made from clear or
            tooth-colored ceramic, making them much less
            noticeable while still guiding teeth into place using
            wires and brackets.
        </p>
    </div>
    </div>

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
        <!-- Image -->
        <img v-lazy="'/images/pic15.png'" alt="SWLF Braces" class="w-full h-64 object-cover" />

        <!-- Title -->
        <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
            <h3 class="font-bold text-dark">SWLF Braces</h3>
        </div>

        <!-- Description -->
        <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
            <p class="text-sm leading-relaxed">
            SWLF(Straight Wire Low Friction) braces combine
            special low-friction brackets with super-elastic wires
            to move teeth faster and more comfortably, giving
            you fewer dental visits and more control over 
            alignment.
            </p>
        </div>
        </div>
  </div>
</section>

<!-- Dental X-Ray -->
<section id="dentalXray" class="relative py-20 px-6 md:px-12 overflow-hidden">
  <!-- Circles -->
  <div class="absolute top-[-6rem] right-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>
  <div class="absolute bottom-[-6rem] left-[-6rem] w-64 h-64 bg-neutral rounded-full"></div>

  <!-- Heading -->
  <div class="max-w-6xl mx-auto text-center mb-12 relative z-10">
    <h2 class="text-2xl md:text-3xl font-extrabold text-dark">
      DENTAL X-RAY
    </h2>
  </div>

  <!-- Grid -->
  <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <img v-lazy="'/images/pic15.png'" alt="Digital Panoramic X-ray" class="w-full h-80 object-cover" />
      <!-- Title -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Digital Panoramic X-ray</h3>
      </div>
      <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
          It's advanced 2D imaging method that provides a 
          complete overview of your mouth, jawbone, sinuses, 
          and temporomandibular joints in one shot.
        </p>
      </div>
    </div>

    <!-- Card 2 -->
    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <img v-lazy="'/images/pic16.png'" alt="Digital Cephalometric X-ray" class="w-full h-80 object-cover" />
      <!-- Title -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Digital Cephalometric X-ray</h3>
      </div>
      <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
          A cephalometric X-ray captures your entire skull and
          side profile, commonly used in orthodontics for
          diagnosis and treatment planning.
        </p>
      </div>
    </div>

    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <img v-lazy="'/images/pic17.png'" alt="Periapical X-ray" class="w-full h-80 object-cover" />
      <!-- Title -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Periapical X-ray</h3>
      </div>
      <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
          A periapical X-ray shows the entire tooth from crown
          to root tip, helping diagnose infections and root
          problems.
        </p>
      </div>
    </div>

    <!-- Card 4 -->
    <div class="relative rounded-xl overflow-hidden shadow-lg group">
      <img v-lazy="'/images/pic18.png'" alt="Bitewing X-ray" class="w-full h-80 object-cover" />
      <!-- Title -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/80 text-dark p-4">
        <h3 class="font-bold text-lg">Bitewing X-ray</h3>
      </div>
      <!-- Description -->
      <div class="absolute bottom-0 left-0 right-0 bg-light/95 text-dark p-4 transform translate-y-full group-hover:translate-y-0 transition-all duration-500 ease-in-out">
        <p class="text-sm leading-relaxed">
          A bitewing X-ray focuses on the upper and lower teeth
          in a specific area to detect cavities and bone loss.
        </p>
      </div>
    </div>

  </div>
</section>
</GuestLayout>
</template>

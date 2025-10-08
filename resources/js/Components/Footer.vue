<template>
    <footer id="contact" class="bg-gradient-to-b from-neutral to-light text-white py-10">
  <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-8">
    
    <!-- Left Section -->
    <div>
      <h2 class="font-bold text-lg">District Smiles<br>Dental Center</h2>
      <p class="mt-2 text-sm">
        391 Odonas bldg F. Roxas street corner 10th Avenue<br>
        Gracepark West , Caloocan, Philippines, 1406
      </p>
      <div class="flex items-center mt-4 space-x-3">
        <!-- Email Icon -->
          <img :src="'/images/envelope.svg'" alt="Email" class="w-5 h-5">
        <a href="mailto:districtsmilesdentalcenter@gmail.com" class="text-sm">
          districtsmilesdentalcenter@gmail.com
        </a>
      </div>
      <div class="flex items-center mt-2 space-x-3">
        <!-- Phone Icon -->
        <svg xmlns="http://www.w3.org/2000/svg" 
             class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M3 5h2l3.6 7.59a1 1 0 01-.2 1.22l-2.6 2.6c1.14 2.18 2.98 4.02 5.16 5.16l2.6-2.6a1 1 0 011.22-.2L19 19v2a1 1 0 01-1 1A16 16 0 013 5z"/>
        </svg>
        <a href="tel:+639155335086" class="text-sm">09155335086</a>
      </div>
    </div>

    <!-- Center Section -->
    <div>
      <h2 class="font-bold text-lg">Clinic Hours</h2>
      <ul class="mt-2 text-sm space-y-1">
        <li><span class="font-semibold">Monday:</span> Closed</li>
        <li><span class="font-semibold">Tuesday:</span> 10:00 am – 6:00 pm</li>
        <li><span class="font-semibold">Wednesday:</span> 10:00 am – 6:00 pm</li>
        <li><span class="font-semibold">Thursday:</span> 10:00 am – 6:00 pm</li>
        <li><span class="font-semibold">Friday:</span> 10:00 am – 6:00 pm</li>
        <li><span class="font-semibold">Saturday:</span> 10:00 am – 6:00 pm</li>
        <li><span class="font-semibold">Sunday:</span> Closed</li>
      </ul>
    </div>

    <!-- Right Section -->
    <div>
      <h2 class="font-bold text-lg">Follow us on</h2>
      <div class="mt-4 space-y-3">
        <a href="#" class="flex items-center space-x-2">
          <!-- Facebook Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" 
               class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M22 12c0-5.522-4.478-10-10-10S2 6.478 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.507 1.493-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.891h-2.33v6.987C18.343 21.128 22 16.991 22 12z"/>
          </svg>
          <span>District Smiles Dental Center</span>
        </a>
        <a href="#" class="flex items-center space-x-2">
          <!-- Location Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" 
               class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M12 11c1.657 0 3-1.343 3-3S13.657 5 12 5 9 6.343 9 8s1.343 3 3 3z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M12 22s8-4.438 8-11a8 8 0 10-16 0c0 6.562 8 11 8 11z"/>
          </svg>
          <span>See location on map</span>
        </a>
      </div>
    </div>
  </div>

  <!-- Bottom Copyright -->
  <div class="mt-8 text-center text-sm text-black">
    District Smiles Dental Center © 2025
  </div>
</footer>


</template>
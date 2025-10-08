<script setup>
import Navbar from '@/Components/NavLink.vue'
import Footer from '@/Components/Footer.vue';
</script>

<template>
    <Navbar />
    <main>
      <slot />
    </main>
    <Footer />
</template>

<template>
  <div class="relative flex flex-col min-h-screen w-full overflow-hidden bg-gradient-to-b from-neutral to-light">
    <!-- Navbar -->
    <AuthNavbar class="z-20" />

    <!-- Floating spheres -->
    <div
      v-for="(sphere, index) in spheres"
      :key="index"
      class="absolute rounded-full sphere"
      :style="{
        width: sphere.size,
        height: sphere.size,
        top: sphere.top,
        left: sphere.left,
        opacity: sphere.opacity,
        animationDuration: `${sphere.speed}s`,
        animationDelay: `${sphere.delay}s`,
      }"
    ></div>

    <!-- Page title -->
    <h1
      v-if="title"
      class="z-10 text-white font-bold text-2xl sm:text-3xl md:text-4xl mt-12 text-center drop-shadow-lg"
    >
      {{ title }}
    </h1>

    <!-- Slot for main content -->
    <main class="relative z-10 flex-1 flex items-center justify-center px-4 sm:px-8">
      <slot />
    </main>
  </div>
</template>

<script setup>
import AuthNavbar from "@/Components/NavLink.vue"

defineProps({
  title: {
    type: String,
    default: "",
  },
})

const spheres = [
  { size: "120px", top: "10%", left: "8%", opacity: 0.6, speed: 8, delay: 0 },
  { size: "180px", top: "15%", left: "90%", opacity: 0.7, speed: 10, delay: 1 },
  { size: "100px", top: "55%", left: "5%", opacity: 0.5, speed: 9, delay: 0.5 },
  { size: "220px", top: "75%", left: "35%", opacity: 0.65, speed: 12, delay: 2 },
  { size: "280px", top: "70%", left: "85%", opacity: 0.6, speed: 11, delay: 1.5 },
  { size: "150px", top: "40%", left: "95%", opacity: 0.55, speed: 9.5, delay: 0.8 },
]
</script>

<style scoped>
.sphere {
  position: absolute;
  border-radius: 50%;
  background: radial-gradient(circle at 30% 30%, 
    rgba(255, 255, 255, 0.9) 0%,
    rgba(186, 230, 235, 0.8) 20%,
    rgba(115, 180, 190, 0.7) 45%,
    rgba(80, 140, 150, 0.6) 70%,
    rgba(60, 110, 120, 0.5) 100%
  );
  box-shadow:
    inset -15px -15px 40px rgba(0, 0, 0, 0.2),
    inset 15px 15px 40px rgba(255, 255, 255, 0.4),
    0 20px 50px rgba(0, 0, 0, 0.15);
  animation: float ease-in-out infinite alternate;
  transform: translate(-50%, -50%);
  backdrop-filter: blur(2px);
}

/* Add subtle glossy highlight */
.sphere::before {
  content: '';
  position: absolute;
  top: 10%;
  left: 15%;
  width: 40%;
  height: 40%;
  background: radial-gradient(circle, 
    rgba(255, 255, 255, 0.6) 0%,
    rgba(255, 255, 255, 0.2) 50%,
    transparent 70%
  );
  border-radius: 50%;
  filter: blur(8px);
}

@keyframes float {
  0% {
    transform: translate(-50%, -50%) translateY(0px) scale(1);
  }
  50% {
    transform: translate(-50%, -50%) translateY(-20px) scale(1.02);
  }
  100% {
    transform: translate(-50%, -50%) translateY(0px) scale(1);
  }
}

/* Add a subtle moving effect to some spheres */
.sphere:nth-child(2),
.sphere:nth-child(4) {
  animation-name: float-horizontal;
}

@keyframes float-horizontal {
  0% {
    transform: translate(-50%, -50%) translateX(0px);
  }
  50% {
    transform: translate(-50%, -50%) translateX(15px);
  }
  100% {
    transform: translate(-50%, -50%) translateX(0px);
  }
}
</style>
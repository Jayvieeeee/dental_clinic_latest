<script setup>
import { ref, computed } from 'vue'
import EditModal from '@/Components/EditModal.vue'
import { usePage } from '@inertiajs/vue3'

// Make user reactive using computed
const page = usePage()
const user = computed(() => page.props.auth.user ?? {})

const showModal = ref(false)
const modalTitle = ref('')
const modalFields = ref([])
const modalRoute = ref('profile.update')

// Open modal
const openModal = (fieldType) => {
  if (fieldType === 'name') {
    modalTitle.value = 'Update Name'
    modalFields.value = [
      { label: 'First Name', name: 'first_name', value: user.value.first_name },
      { label: 'Last Name', name: 'last_name', value: user.value.last_name },
    ]
  } else if (fieldType === 'email') {
    modalTitle.value = 'Update Email'
    modalFields.value = [
      { label: 'Email', name: 'email', value: user.value.email },
    ]
  } else if (fieldType === 'contact') {
    modalTitle.value = 'Update Contact Number'
    modalFields.value = [
      { label: 'Contact Number', name: 'contact_no', value: user.value.contact_no },
    ]
  }
  showModal.value = true
}

</script>

<template>
  <section class="max-w-2xl w-full pb-10 px-5">
    <!-- Title -->
    <h2 class="text-2xl font-semibold mb-8 text-left tracking-wide">
      Personal Information
    </h2>

    <form class="space-y-6">
      <!-- Name -->
      <div class="flex items-center justify-between">
        <label class="w-1/4 font-medium text-lg">Name</label>
        <div class="relative flex-1">
          <input
            type="text"
            :value="`${user.first_name} ${user.last_name}`"
            disabled
            class="w-full border-2 border-black rounded-xl py-3 px-4 pr-10 bg-gray-50 cursor-default"
          />
          <button
            type="button"
            @click="openModal('name')"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-cyan-700 hover:text-cyan-800 text-xl"
            title="Edit Name"
          >
            ✎
          </button>
        </div>
      </div>

      <!-- Email -->
      <div class="flex items-center justify-between">
        <label class="w-1/4 font-medium text-lg">Email</label>
        <div class="relative flex-1">
          <input
            type="email"
            :value="user.email"
            disabled
            class="w-full border-2 border-black rounded-xl py-3 px-4 pr-10 bg-gray-50 text-gray-800 cursor-default"
          />
          <button
            type="button"
            @click="openModal('email')"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-cyan-700 hover:text-cyan-800 text-xl"
            title="Edit Email"
          >
            ✎
          </button>
        </div>
      </div>

      <!-- Contact Number -->
      <div class="flex items-center justify-between">
        <label class="w-1/4 font-medium text-lg">Contact Number</label>
        <div class="relative flex-1">
          <input
            type="text"
            :value="user.contact_no"
            disabled
            class="w-full border-2 border-black rounded-xl py-3 px-4 pr-10 bg-gray-50 text-gray-800 cursor-default"
          />
          <button
            type="button"
            @click="openModal('contact')"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-cyan-700 hover:text-cyan-800 text-xl"
            title="Edit Contact Number"
          >
            ✎
          </button>
        </div>
      </div>
    </form>

    <!-- Modal -->
    <EditModal
      :show="showModal"
      :title="modalTitle"
      :fields="modalFields"
      :route-name="modalRoute"
      @close="showModal = false"
    />
  </section>
</template>

<style scoped>
input:disabled {
  opacity: 1;
}
</style>
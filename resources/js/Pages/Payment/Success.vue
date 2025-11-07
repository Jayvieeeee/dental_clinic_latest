<template>
  <CustomerLayout>
    <Head title="Payment Successful" />

    <PaymentSuccessModal 
      :show="true"
      :appointment="appointment"
      :payment="payment"
    />
  </CustomerLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import CustomerLayout from '@/Layouts/CustomerLayout.vue'
import PaymentSuccessModal from '@/Components/PaymentSuccessModal.vue'

const page = usePage()
const appointment = computed(() => page.props.appointment)
const payment = computed(() => page.props.payment)
</script>

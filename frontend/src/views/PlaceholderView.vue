<script setup>
defineProps({
  title: { type: String, required: true },
  message: { type: String, required: true },
})
</script>

<template>
  <div>
    <h1 class="h4 fw-bold text-dark mb-2">{{ title }}</h1>
    <p class="text-muted col-lg-8 mb-0">{{ message }}</p>
  </div>
</template>

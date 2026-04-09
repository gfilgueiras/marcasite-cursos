<script setup>
import { RouterLink } from 'vue-router'

defineProps({
  courseId: { type: [Number, String], required: true },
  title: { type: String, required: true },
  priceLabel: { type: String, required: true },
  imageUrl: { type: String, default: '' },
  /** Inscrição paga para o e-mail salvo em localStorage (mesma origem que “meus cursos”). */
  purchased: { type: Boolean, default: false },
})
</script>

<template>
  <div class="course-card card h-100 border shadow-sm overflow-hidden">
    <div class="course-card__media d-flex align-items-center justify-content-center">
      <img v-if="imageUrl" :src="imageUrl" :alt="title" class="course-card__img" />
      <i v-else class="bi bi-image course-card__placeholder-icon" aria-hidden="true" />
    </div>
    <div class="card-body d-flex flex-column pt-3 pb-3">
      <h2 class="h6 fw-bold mb-2 text-dark">{{ title }}</h2>
      <p class="course-card__price mb-3 flex-grow-1">{{ priceLabel }}</p>
      <div class="d-flex justify-content-end mt-auto">
        <button
          v-if="purchased"
          type="button"
          class="btn btn-success btn-sm px-3"
          disabled
        >
          Comprado
        </button>
        <RouterLink
          v-else
          :to="`/courses/${courseId}/enroll`"
          class="btn btn-dark btn-sm px-3"
        >
          Comprar
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.course-card__media {
  aspect-ratio: 16 / 10;
  background: #dee2e6;
  border-bottom: 1px solid #e9ecef;
}

.course-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.course-card__placeholder-icon {
  font-size: 3rem;
  color: #adb5bd;
}

.course-card__price {
  font-size: 0.9rem;
  color: #495057;
}
</style>

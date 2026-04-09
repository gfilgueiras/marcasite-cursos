<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import AppPagination from '../components/AppPagination.vue'
import CourseCard from '../components/CourseCard.vue'

const route = useRoute()
const courses = ref([])
const loading = ref(true)
const error = ref('')
const currentPage = ref(1)
const pageSize = 6
const selectedCourseId = computed(() => route.query.id || '')

/** IDs de cursos com inscrição paga (e-mail em localStorage, via /my-enrollments). */
const purchasedCourseIds = ref(new Set())

async function loadCourses() {
  try {
    const { data } = await api.get('/api/v1/courses')
    courses.value = data.data ?? data
  } catch {
    error.value =
      'Não foi possível carregar o catálogo. Verifique se a API está no ar (por exemplo, http://localhost:8080) e tente novamente.'
  }
}

async function loadPurchasedCourseIds() {
  const email = localStorage.getItem('student_email')?.trim()
  if (!email) {
    purchasedCourseIds.value = new Set()
    return
  }
  try {
    const { data } = await api.post('/api/v1/my-enrollments', { email })
    const list = data.data ?? data
    const ids = new Set()
    for (const row of list) {
      if (row.payment_status === 'paid' && row.course?.id != null) {
        ids.add(Number(row.course.id))
      }
    }
    purchasedCourseIds.value = ids
  } catch {
    purchasedCourseIds.value = new Set()
  }
}

onMounted(async () => {
  await loadCourses()
  await loadPurchasedCourseIds()
  loading.value = false
})

watch(
  () => route.fullPath,
  () => {
    if (route.name === 'courses') {
      loadPurchasedCourseIds()
    }
  },
)

watch([courses, selectedCourseId], () => {
  currentPage.value = 1
})

const filteredCourses = computed(() => {
  if (!selectedCourseId.value) return courses.value
  return courses.value.filter((c) => String(c.id) === String(selectedCourseId.value))
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredCourses.value.length / pageSize)))

const pagedCourses = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredCourses.value.slice(start, start + pageSize)
})

watch(totalPages, (t) => {
  if (currentPage.value > t) {
    currentPage.value = t
  }
})

function formatPrice(cents, currency) {
  const cur = (currency || 'BRL').toUpperCase()
  try {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: cur === 'BRL' ? 'BRL' : 'USD',
    }).format((cents || 0) / 100)
  } catch {
    const v = ((cents || 0) / 100).toFixed(2)
    return `R$ ${v}`
  }
}
</script>

<template>
  <div>
    <h1 class="h4 fw-bold text-dark mb-2">Vitrine de Cursos</h1>
    <p class="text-muted small mb-4 col-lg-10">
      Escolha um curso e conclua a compra com pagamento seguro (Stripe em modo de teste). Os valores são exibidos em real.
    </p>

    <p v-if="loading" class="mb-0 text-secondary">Carregando cursos…</p>
    <p v-else-if="error" class="text-danger mb-0">{{ error }}</p>
    <p v-else-if="filteredCourses.length === 0" class="text-muted mb-0">
      Nenhum curso disponível no momento. Volte em breve ou fale com o administrador.
    </p>
    <template v-else>
      <div class="row g-4">
        <div v-for="c in pagedCourses" :key="c.id" class="col-sm-6 col-lg-4">
          <CourseCard
            :course-id="c.id"
            :title="c.name"
            :price-label="formatPrice(c.price_cents, c.currency)"
            :image-url="c.banner_url || ''"
            :purchased="purchasedCourseIds.has(Number(c.id))"
          />
        </div>
      </div>
      <AppPagination v-model="currentPage" :total-pages="totalPages" />
    </template>
  </div>
</template>

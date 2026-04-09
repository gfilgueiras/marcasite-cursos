<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../api'
import AppPagination from '../components/AppPagination.vue'

const STORAGE_KEY = 'student_email'

const loading = ref(false)
const listError = ref('')
const enrollments = ref([])
const lookedUp = ref(false)
const missingEmail = ref(false)
const currentPage = ref(1)
const pageSize = 10

onMounted(() => {
  fetchEnrollments()
})

function formatDateOnly(iso) {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(new Date(iso))
  } catch {
    return String(iso)
  }
}

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

function statusLabel(status) {
  switch (status) {
    case 'paid':
      return 'Pago'
    case 'pending':
      return 'Pendente'
    case 'failed':
      return 'Cancelado'
    default:
      return status || '—'
  }
}

function statusBadgeClass(status) {
  switch (status) {
    case 'paid':
      return 'text-bg-success'
    case 'pending':
      return 'text-bg-warning'
    case 'failed':
      return 'text-bg-danger'
    default:
      return 'text-bg-secondary'
  }
}

const totalPages = computed(() => Math.max(1, Math.ceil(enrollments.value.length / pageSize)))

const pagedEnrollments = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return enrollments.value.slice(start, start + pageSize)
})

watch(enrollments, () => {
  currentPage.value = 1
}, { deep: true })

watch(totalPages, (t) => {
  if (currentPage.value > t) {
    currentPage.value = t
  }
})

async function fetchEnrollments() {
  const e = localStorage.getItem(STORAGE_KEY)?.trim()
  if (!e) {
    missingEmail.value = true
    lookedUp.value = true
    loading.value = false
    enrollments.value = []
    listError.value = ''
    return
  }

  missingEmail.value = false
  listError.value = ''
  loading.value = true
  lookedUp.value = true
  try {
    const { data } = await api.post('/api/v1/my-enrollments', { email: e })
    enrollments.value = Array.isArray(data?.data) ? data.data : []
  } catch (err) {
    const msg = err.response?.data?.message
    const errors = err.response?.data?.errors
    if (errors && typeof errors === 'object') {
      const first = Object.values(errors).flat()[0]
      listError.value = first || 'Verifique o e-mail e tente de novo.'
    } else {
      listError.value = msg || 'Não foi possível carregar suas inscrições. Tente novamente.'
    }
    enrollments.value = []
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="bg-white rounded shadow-sm border p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h1 class="h4 fw-bold text-dark mb-1">Meus Cursos</h1>
        <p class="text-muted small mb-0">Suas inscrições e pagamentos (e-mail da sessão, gravado na compra).</p>
      </div>
      <RouterLink :to="{ name: 'courses' }" class="btn btn-dark btn-sm">
        <i class="bi bi-grid me-1" aria-hidden="true" />
        Vitrine de cursos
      </RouterLink>
    </div>

    <div v-if="missingEmail" class="text-muted py-3">
      <p class="mb-2">
        Ainda não há um e-mail associado a esta sessão. Conclua uma compra na vitrine para ver seus cursos aqui.
      </p>
      <RouterLink :to="{ name: 'courses' }" class="btn btn-dark btn-sm">Ir para a vitrine</RouterLink>
    </div>

    <template v-else>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">Nome</th>
              <th scope="col">Data de inscrição</th>
              <th scope="col">Status</th>
              <th scope="col">Valor</th>
              <th scope="col" class="text-end" />
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="text-center text-muted py-3">Carregando inscrições…</td>
            </tr>
            <tr v-else-if="listError">
              <td colspan="5" class="text-center text-danger py-3">{{ listError }}</td>
            </tr>
            <tr v-else-if="lookedUp && enrollments.length === 0">
              <td colspan="5" class="text-center text-muted py-3">
                Nenhuma inscrição encontrada para o e-mail desta sessão.
              </td>
            </tr>
            <tr v-for="row in pagedEnrollments" v-else :key="row.id">
              <td>{{ row.course?.name || 'Curso' }}</td>
              <td>{{ formatDateOnly(row.enrolled_at) }}</td>
              <td>
                <span class="badge" :class="statusBadgeClass(row.payment_status)">
                  {{ statusLabel(row.payment_status) }}
                </span>
              </td>
              <td>{{ formatPrice(row.amount_cents, row.currency) }}</td>
              <td class="text-end">
                <RouterLink
                  v-if="row.course?.id"
                  :to="{ name: 'courses', query: { id: String(row.course.id) } }"
                  class="btn btn-link btn-sm text-primary p-1"
                  aria-label="Ver curso na vitrine"
                >
                  <i class="bi bi-eye" aria-hidden="true" />
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppPagination v-if="!loading && !listError && enrollments.length > 0" v-model="currentPage" :total-pages="totalPages" />
    </template>
  </div>
</template>

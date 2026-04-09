<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'

const route = useRoute()
const router = useRouter()
const rows = ref([])
const meta = ref(null)
const loading = ref(true)
const listError = ref('')
const page = ref(1)
const hasAdminToken = ref(false)

const editRow = ref(null)
const editForm = reactive({
  student: { name: '', email: '', phone: '', document: '' },
  payment_status: '',
})

const showDeleteEnrollmentModal = ref(false)
const enrollmentToDelete = ref(null)
const deleteEnrollmentSubmitting = ref(false)

/** Nome do curso quando a lista está filtrada por `course_id` na URL. */
const filteredCourseName = ref('')
const loadingCourseName = ref(false)

const hasCourseFilter = computed(() => {
  const cid = route.query.course_id
  return cid != null && String(cid).trim() !== ''
})

const pageTitleMain = computed(() => {
  if (!hasCourseFilter.value) {
    return 'Inscrições em cursos'
  }
  if (loadingCourseName.value) {
    return 'Inscrições do curso …'
  }
  if (filteredCourseName.value) {
    return `Inscrições do curso ${filteredCourseName.value}`
  }
  return `Inscrições do curso #${route.query.course_id}`
})

async function loadFilteredCourseName() {
  const cid = route.query.course_id
  if (cid == null || String(cid).trim() === '') {
    filteredCourseName.value = ''
    loadingCourseName.value = false
    return
  }
  loadingCourseName.value = true
  filteredCourseName.value = ''
  try {
    const { data } = await api.get(`/api/v1/admin/courses/${String(cid)}`)
    const c = data.data ?? data
    filteredCourseName.value = c?.name ? String(c.name) : ''
  } catch {
    filteredCourseName.value = ''
  } finally {
    loadingCourseName.value = false
  }
}

function labelPagamento(status) {
  const map = {
    pending: 'Pendente',
    paid: 'Pago',
    failed: 'Falhou',
  }
  return map[status] || status
}

async function load() {
  loading.value = true
  listError.value = ''
  try {
    const params = {
      page: page.value,
      per_page: 15,
    }
    const cid = route.query.course_id
    if (cid != null && cid !== '') {
      params.course_id = String(cid)
    }
    const { data } = await api.get('/api/v1/admin/enrollments', { params })
    rows.value = data.data
    meta.value = data.meta
  } catch {
    rows.value = []
    meta.value = null
    listError.value =
      'Não foi possível carregar as inscrições. Confira se você ainda está logado e se a API está disponível.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  hasAdminToken.value = !!localStorage.getItem('admin_token')
  loadFilteredCourseName()
  load()
})

watch(page, load)

watch(
  () => route.query.course_id,
  () => {
    page.value = 1
    loadFilteredCourseName()
    load()
  }
)

watch(
  pageTitleMain,
  (t) => {
    document.title = `${t} · Marcasite`
  },
  { immediate: true },
)

function openEdit(row) {
  editRow.value = row
  editForm.student = {
    name: row.student.name,
    email: row.student.email,
    phone: row.student.phone,
    document: row.student.document || '',
  }
  editForm.payment_status = row.payment_status
}

async function saveEdit() {
  if (!editRow.value) return
  try {
    await api.put(`/api/v1/admin/enrollments/${editRow.value.id}`, {
      student: editForm.student,
      payment_status: editForm.payment_status,
    })
    editRow.value = null
    await load()
  } catch {
    alert('Não foi possível salvar as alterações. Verifique os dados e tente novamente.')
  }
}

function openDeleteEnrollmentModal(row) {
  enrollmentToDelete.value = row
  showDeleteEnrollmentModal.value = true
}

function closeDeleteEnrollmentModal() {
  if (deleteEnrollmentSubmitting.value) return
  showDeleteEnrollmentModal.value = false
  enrollmentToDelete.value = null
}

async function confirmDeleteEnrollment() {
  if (!enrollmentToDelete.value?.id) return
  deleteEnrollmentSubmitting.value = true
  try {
    await api.delete(`/api/v1/admin/enrollments/${enrollmentToDelete.value.id}`)
    deleteEnrollmentSubmitting.value = false
    closeDeleteEnrollmentModal()
    await load()
  } catch {
    alert('Não foi possível excluir a inscrição.')
  } finally {
    deleteEnrollmentSubmitting.value = false
  }
}

function exportParams() {
  const p = new URLSearchParams()
  const cid = route.query.course_id
  if (cid != null && cid !== '') {
    p.set('course_id', String(cid))
  }
  return p.toString()
}

async function downloadExport(format) {
  try {
    const q = exportParams()
    const url = `/api/v1/admin/enrollments/export?format=${format}${q ? `&${q}` : ''}`
    const res = await api.get(url, { responseType: 'blob' })
    const blob = new Blob([res.data])
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = format === 'pdf' ? 'inscricoes-marcasite.pdf' : 'inscricoes-marcasite.xlsx'
    a.click()
    URL.revokeObjectURL(a.href)
  } catch {
    alert('Não foi possível gerar o arquivo. Tente fazer login novamente ou confira a API.')
  }
}

function logout() {
  localStorage.removeItem('admin_token')
  router.push('/admin/login')
}
</script>

<template>
  <div class="bg-white rounded shadow-sm border p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <div>
        <p class="small text-muted mb-0">Painel administrativo</p>
        <h1 class="h3 mb-0">{{ pageTitleMain }}</h1>
      </div>
      <button
        v-if="hasAdminToken"
        type="button"
        class="btn btn-outline-secondary btn-sm"
        @click="logout"
      >
        Sair da conta
      </button>
    </div>
    <p class="text-muted small mb-4">
      Lista de alunos inscritos. Ao abrir a partir de um curso, apenas as inscrições daquele curso são exibidas.
    </p>

    <div v-if="hasAdminToken" class="mb-3 d-flex flex-wrap gap-2 align-items-center">
      <span class="small text-muted me-1">Exportar lista atual:</span>
      <button type="button" class="btn btn-sm btn-outline-dark" @click="downloadExport('xlsx')">
        Planilha Excel (.xlsx)
      </button>
      <button type="button" class="btn btn-sm btn-outline-dark" @click="downloadExport('pdf')">Documento PDF</button>
    </div>

    <p v-if="loading" class="mb-0">Carregando inscrições…</p>
    <p v-else-if="listError" class="text-danger small">{{ listError }}</p>
    <p v-else-if="rows.length === 0" class="text-muted mb-0">Nenhuma inscrição encontrada.</p>
    <div v-else class="table-responsive">
      <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Aluno</th>
            <th scope="col">E-mail</th>
            <th scope="col">Telefone</th>
            <th scope="col">Curso</th>
            <th scope="col">Pagamento</th>
            <th scope="col">Data da inscrição</th>
            <th scope="col" class="text-center">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.id">
            <td>{{ r.student.name }}</td>
            <td>{{ r.student.email }}</td>
            <td>{{ r.student.phone }}</td>
            <td>{{ r.course.name }}</td>
            <td>{{ labelPagamento(r.payment_status) }}</td>
            <td>{{ r.enrolled_at?.slice(0, 16)?.replace('T', ' ') }}</td>
            <td class="text-center">
              <div class="d-flex flex-wrap align-items-center justify-content-center gap-2 enrollment-row-actions">
                <button
                  v-if="hasAdminToken"
                  type="button"
                  class="btn btn-sm btn-outline-secondary enrollment-action-edit"
                  @click="openEdit(r)"
                >
                  <i class="bi bi-pencil-square" aria-hidden="true" />
                  <span class="ms-1">Editar</span>
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-danger enrollment-action-remove"
                  title="Remover esta inscrição da lista"
                  :aria-label="`Remover inscrição de ${r.student.name}`"
                  @click="openDeleteEnrollmentModal(r)"
                >
                  <i class="bi bi-trash3" aria-hidden="true" />
                  <span class="ms-1">Remover</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="meta && meta.last_page > 1" class="mt-3 d-flex align-items-center flex-wrap gap-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="page <= 1" @click="page--">
        Página anterior
      </button>
      <span class="small text-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="page >= meta.last_page" @click="page++">
        Próxima página
      </button>
    </nav>

    <div
      v-if="editRow"
      class="modal d-block bg-dark bg-opacity-50"
      tabindex="-1"
      style="position: fixed; inset: 0; z-index: 1050; overflow-y: auto; -webkit-overflow-scrolling: touch"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="'modal-edit-title'"
    >
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h2 id="modal-edit-title" class="h5 modal-title">Editar inscrição nº {{ editRow.id }}</h2>
            <button type="button" class="btn-close" aria-label="Fechar janela" @click="editRow = null"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted">Altere apenas o que for necessário. O valor pago na época da matrícula permanece registrado no sistema.</p>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small">Nome completo</label>
                <input v-model="editForm.student.name" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label small">E-mail</label>
                <input v-model="editForm.student.email" type="email" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label small">Telefone</label>
                <input v-model="editForm.student.phone" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label small">Situação do pagamento</label>
                <select v-model="editForm.payment_status" class="form-select form-select-sm">
                  <option value="pending">Pendente</option>
                  <option value="paid">Pago</option>
                  <option value="failed">Falhou</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" @click="editRow = null">Cancelar</button>
            <button type="button" class="btn btn-dark btn-sm" @click="saveEdit">Salvar alterações</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div
    v-if="showDeleteEnrollmentModal"
    class="user-modal-backdrop"
    @click.self="closeDeleteEnrollmentModal"
  >
    <div
      class="delete-confirm-modal card border-0 shadow-lg"
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-enrollment-title"
    >
      <div class="card-body p-4 p-md-5 text-center">
        <div class="delete-confirm-icon mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle">
          <i class="bi bi-trash3 text-danger" style="font-size: 1.75rem" aria-hidden="true" />
        </div>
        <h2 id="delete-enrollment-title" class="h5 fw-bold text-dark mb-3">Remover inscrição?</h2>
        <p class="text-muted small mb-2 mb-md-3">Tem certeza de que deseja remover</p>
        <p class="fw-semibold text-dark mb-1 fs-5">{{ enrollmentToDelete?.student?.name }}</p>
        <p class="text-muted small mb-3">do curso {{ enrollmentToDelete?.course?.name }}</p>
        <p class="text-muted small mb-4">Esta ação não pode ser desfeita.</p>
        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
          <button
            type="button"
            class="btn btn-light border px-4"
            :disabled="deleteEnrollmentSubmitting"
            @click="closeDeleteEnrollmentModal"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="btn btn-danger px-4"
            :disabled="deleteEnrollmentSubmitting"
            @click="confirmDeleteEnrollment"
          >
            <span
              v-if="deleteEnrollmentSubmitting"
              class="spinner-border spinner-border-sm me-1"
              aria-hidden="true"
            />
            {{ deleteEnrollmentSubmitting ? 'Removendo…' : 'Remover' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.user-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  z-index: 1200;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 1rem;
  box-sizing: border-box;
}

.delete-confirm-modal {
  width: min(420px, 100%);
  border-radius: 0.75rem;
  margin: 1.5rem auto;
  max-height: calc(100vh - 3rem);
  max-height: calc(100dvh - 3rem);
  overflow-y: auto;
  flex-shrink: 0;
}

.delete-confirm-icon {
  width: 4rem;
  height: 4rem;
  background: rgba(220, 53, 69, 0.12);
}

.enrollment-row-actions {
  max-width: 100%;
}

.enrollment-action-remove {
  font-weight: 600;
  letter-spacing: 0.01em;
  border-radius: 9999px;
  padding-inline: 0.65rem 0.85rem;
  border-width: 1.5px;
  transition:
    background-color 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease,
    color 0.15s ease;
}

.enrollment-action-remove:hover {
  color: #fff;
  background-color: #dc3545;
  border-color: #dc3545;
  box-shadow: 0 2px 8px rgba(220, 53, 69, 0.35);
}

.enrollment-action-remove:focus-visible {
  box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.35);
}

.enrollment-action-edit {
  border-radius: 9999px;
  padding-inline: 0.65rem 0.85rem;
  font-weight: 500;
}
</style>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../api'
import AppPagination from '../components/AppPagination.vue'

const courses = ref([])
const loading = ref(true)
const listError = ref('')
const searchQuery = ref('')
const currentPage = ref(1)
const pageSize = 10

const showFormModal = ref(false)
const editingId = ref(null)
const formError = ref('')
const formSubmitting = ref(false)
const nameInput = ref(null)
const bannerInputRef = ref(null)
const bannerFile = ref(null)
const bannerDisplayUrl = ref('')
const savedBannerUrl = ref('')
const removeBanner = ref(false)

const materialsInputRef = ref(null)
/** @type {import('vue').Ref<Array<{ path: string, name: string, url?: string }>>} */
const existingMaterials = ref([])
/** @type {import('vue').Ref<File[]>} */
const pendingMaterialFiles = ref([])
const initialMaterialsJson = ref('[]')

const courseForm = reactive({
  name: '',
  description: '',
  price_reais: '',
  active: true,
  enrollment_starts_at: '',
  enrollment_ends_at: '',
  max_seats: '',
})

const fieldErrors = reactive({
  name: '',
  description: '',
  price_reais: '',
  enrollment_ends_at: '',
})

const showDeleteModal = ref(false)
const courseToDelete = ref(null)
const deleteSubmitting = ref(false)

const isEditMode = computed(() => editingId.value !== null)

const materialsDirty = computed(() => {
  if (pendingMaterialFiles.value.length > 0) {
    return true
  }
  if (!editingId.value) {
    return false
  }
  return (
    JSON.stringify(existingMaterials.value.map((m) => ({ path: m.path, name: m.name }))) !==
    initialMaterialsJson.value
  )
})

const filteredCourses = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return courses.value
  return courses.value.filter((c) => String(c.name || '').toLowerCase().includes(q))
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredCourses.value.length / pageSize)))

const pagedCourses = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredCourses.value.slice(start, start + pageSize)
})

watch(searchQuery, () => {
  currentPage.value = 1
})

watch(totalPages, (t) => {
  if (currentPage.value > t) {
    currentPage.value = Math.max(1, t)
  }
})

function formatDateYmd(ymd) {
  if (!ymd) return '—'
  try {
    const [y, m, d] = String(ymd).split('-').map(Number)
    if (!y || !m || !d) return '—'
    return new Intl.DateTimeFormat('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(new Date(y, m - 1, d))
  } catch {
    return '—'
  }
}

function formatEnrollmentPeriod(c) {
  const a = c.enrollment_starts_at
  const b = c.enrollment_ends_at
  if (a && b) return `${formatDateYmd(a)} a ${formatDateYmd(b)}`
  if (a) return `a partir de ${formatDateYmd(a)}`
  if (b) return `até ${formatDateYmd(b)}`
  return '—'
}

function formatRemainingSeats(c) {
  if (c.max_seats == null) return 'Ilimitado'
  const n = c.remaining_seats
  return typeof n === 'number' ? String(n) : '—'
}

function formatPrice(cents, currency) {
  const cur = (currency || 'BRL').toUpperCase()
  try {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: cur === 'BRL' ? 'BRL' : 'USD',
    }).format((cents || 0) / 100)
  } catch {
    return `R$ ${((cents || 0) / 100).toFixed(2)}`
  }
}

function statusLabel(active) {
  return active ? 'Ativo' : 'Inativo'
}

function statusClass(active) {
  return active ? 'text-success fw-semibold' : 'text-danger fw-semibold'
}

function parseReaisToCents(s) {
  let t = String(s).trim().replace(/\s/g, '')
  if (!t) return null
  if (t.includes(',')) {
    t = t.replace(/\./g, '').replace(',', '.')
  }
  const n = Number.parseFloat(t)
  if (Number.isNaN(n) || n < 0) return null
  return Math.round(n * 100)
}

function revokeBannerBlob() {
  if (bannerDisplayUrl.value && bannerDisplayUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(bannerDisplayUrl.value)
  }
}

function resetBannerFields() {
  revokeBannerBlob()
  bannerDisplayUrl.value = ''
  savedBannerUrl.value = ''
  bannerFile.value = null
  removeBanner.value = false
  nextTick(() => {
    if (bannerInputRef.value) {
      bannerInputRef.value.value = ''
    }
  })
}

function resetForm() {
  editingId.value = null
  resetBannerFields()
  existingMaterials.value = []
  pendingMaterialFiles.value = []
  initialMaterialsJson.value = '[]'
  nextTick(() => {
    if (materialsInputRef.value) {
      materialsInputRef.value.value = ''
    }
  })
  courseForm.name = ''
  courseForm.description = ''
  courseForm.price_reais = ''
  courseForm.active = true
  courseForm.enrollment_starts_at = ''
  courseForm.enrollment_ends_at = ''
  courseForm.max_seats = ''
  fieldErrors.name = ''
  fieldErrors.description = ''
  fieldErrors.price_reais = ''
  fieldErrors.enrollment_ends_at = ''
  formError.value = ''
}

function openCreateModal() {
  resetForm()
  showFormModal.value = true
  nextTick(() => nameInput.value?.focus())
}

function openEditModal(course) {
  resetBannerFields()
  const mats = Array.isArray(course.materials) ? course.materials : []
  existingMaterials.value = mats.map((m) => ({
    path: m.path,
    name: m.name || 'arquivo',
    url: m.url || '',
  }))
  initialMaterialsJson.value = JSON.stringify(
    mats.map((m) => ({ path: m.path, name: m.name || 'arquivo' })),
  )
  pendingMaterialFiles.value = []
  nextTick(() => {
    if (materialsInputRef.value) {
      materialsInputRef.value.value = ''
    }
  })
  editingId.value = course.id
  savedBannerUrl.value = course.banner_url || ''
  bannerDisplayUrl.value = course.banner_url || ''
  courseForm.name = course.name
  courseForm.description = course.description || ''
  courseForm.price_reais = ((course.price_cents || 0) / 100).toFixed(2).replace('.', ',')
  courseForm.active = !!course.active
  courseForm.enrollment_starts_at = course.enrollment_starts_at || ''
  courseForm.enrollment_ends_at = course.enrollment_ends_at || ''
  courseForm.max_seats = course.max_seats != null ? String(course.max_seats) : ''
  fieldErrors.name = ''
  fieldErrors.description = ''
  fieldErrors.price_reais = ''
  fieldErrors.enrollment_ends_at = ''
  formError.value = ''
  showFormModal.value = true
  nextTick(() => nameInput.value?.focus())
}

function onBannerFileChange(e) {
  const f = e.target.files?.[0] || null
  revokeBannerBlob()
  bannerFile.value = f
  removeBanner.value = false
  if (f) {
    bannerDisplayUrl.value = URL.createObjectURL(f)
  } else {
    bannerDisplayUrl.value = savedBannerUrl.value
  }
}

function onMaterialsFileChange(e) {
  const files = Array.from(e.target.files || [])
  if (files.length === 0) {
    return
  }
  pendingMaterialFiles.value = [...pendingMaterialFiles.value, ...files]
  e.target.value = ''
}

function removeExistingMaterial(index) {
  existingMaterials.value.splice(index, 1)
}

function removePendingMaterial(index) {
  pendingMaterialFiles.value.splice(index, 1)
}

function clearBanner() {
  revokeBannerBlob()
  bannerFile.value = null
  bannerDisplayUrl.value = ''
  if (editingId.value) {
    removeBanner.value = true
  }
  if (bannerInputRef.value) {
    bannerInputRef.value.value = ''
  }
}

function closeFormModal() {
  if (formSubmitting.value) return
  showFormModal.value = false
  resetForm()
}

function validateEnrollmentDates() {
  const es = String(courseForm.enrollment_starts_at || '').trim()
  const ee = String(courseForm.enrollment_ends_at || '').trim()
  if (!es || !ee) {
    fieldErrors.enrollment_ends_at = ''
    return
  }
  if (ee < es) {
    fieldErrors.enrollment_ends_at = 'A data fim não pode ser anterior à data de início.'
  } else {
    fieldErrors.enrollment_ends_at = ''
  }
}

function validateForm() {
  fieldErrors.name = courseForm.name.trim() ? '' : 'Informe o nome do curso.'
  fieldErrors.description = courseForm.description.trim()
    ? ''
    : 'Informe a descrição do curso.'
  const cents = parseReaisToCents(courseForm.price_reais)
  fieldErrors.price_reais =
    cents === null || cents < 0 ? 'Informe um valor válido (ex.: 99,90).' : ''
  validateEnrollmentDates()
  return (
    !fieldErrors.name &&
    !fieldErrors.description &&
    !fieldErrors.price_reais &&
    !fieldErrors.enrollment_ends_at
  )
}

async function submitCourseForm() {
  if (!validateForm()) {
    formError.value = 'Corrija os campos destacados.'
    return
  }
  const cents = parseReaisToCents(courseForm.price_reais)
  formSubmitting.value = true
  formError.value = ''
  try {
    const payload = {
      name: courseForm.name.trim(),
      price_cents: cents,
      currency: 'brl',
      active: Boolean(courseForm.active),
    }
    payload.description = courseForm.description.trim()
    const es = String(courseForm.enrollment_starts_at || '').trim()
    const ee = String(courseForm.enrollment_ends_at || '').trim()
    payload.enrollment_starts_at = es || null
    payload.enrollment_ends_at = ee || null
    const ms = String(courseForm.max_seats || '').trim()
    if (ms) {
      const n = Number.parseInt(ms, 10)
      payload.max_seats = Number.isFinite(n) && n >= 1 ? n : null
    } else {
      payload.max_seats = null
    }

    const useMultipart =
      bannerFile.value != null ||
      (editingId.value != null && removeBanner.value) ||
      pendingMaterialFiles.value.length > 0 ||
      (editingId.value != null && materialsDirty.value)

    if (useMultipart) {
      const fd = new FormData()
      fd.append('name', payload.name)
      fd.append('price_cents', String(payload.price_cents))
      fd.append('currency', payload.currency)
      fd.append('active', payload.active ? '1' : '0')
      fd.append('description', payload.description ?? '')
      fd.append('enrollment_starts_at', payload.enrollment_starts_at ?? '')
      fd.append('enrollment_ends_at', payload.enrollment_ends_at ?? '')
      fd.append('max_seats', payload.max_seats != null ? String(payload.max_seats) : '')
      if (bannerFile.value) {
        fd.append('banner', bannerFile.value)
      }
      if (editingId.value && removeBanner.value) {
        fd.append('remove_banner', '1')
      }
      if (editingId.value) {
        fd.append(
          'existing_materials',
          JSON.stringify(
            existingMaterials.value.map((m) => ({ path: m.path, name: m.name })),
          ),
        )
      }
      pendingMaterialFiles.value.forEach((f) => {
        fd.append('material_files[]', f)
      })
      if (editingId.value) {
        await api.put(`/api/v1/admin/courses/${editingId.value}`, fd)
      } else {
        await api.post('/api/v1/admin/courses', fd)
      }
    } else if (editingId.value) {
      await api.put(`/api/v1/admin/courses/${editingId.value}`, payload)
    } else {
      await api.post('/api/v1/admin/courses', payload)
    }
    showFormModal.value = false
    resetForm()
    await loadCourses()
  } catch (e) {
    const errors = e?.response?.data?.errors
    const first = errors ? Object.values(errors).flat()[0] : null
    formError.value = first || e?.response?.data?.message || 'Não foi possível salvar o curso.'
  } finally {
    formSubmitting.value = false
  }
}

function openDeleteModal(course) {
  courseToDelete.value = course
  showDeleteModal.value = true
}

function closeDeleteModal() {
  if (deleteSubmitting.value) return
  showDeleteModal.value = false
  courseToDelete.value = null
}

async function confirmDeleteCourse() {
  if (!courseToDelete.value?.id) return
  deleteSubmitting.value = true
  try {
    await api.delete(`/api/v1/admin/courses/${courseToDelete.value.id}`)
    deleteSubmitting.value = false
    closeDeleteModal()
    await loadCourses()
    if (currentPage.value > totalPages.value) {
      currentPage.value = Math.max(1, totalPages.value)
    }
  } catch {
    alert('Não foi possível excluir o curso.')
  } finally {
    deleteSubmitting.value = false
  }
}

async function loadCourses() {
  loading.value = true
  listError.value = ''
  try {
    const { data } = await api.get('/api/v1/admin/courses')
    courses.value = Array.isArray(data?.data) ? data.data : []
  } catch {
    courses.value = []
    listError.value =
      'Não foi possível carregar os cursos. Confira se você está logado como administrador e se a API está no ar.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadCourses()
})
</script>

<template>
  <div class="bg-white rounded shadow-sm border p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h1 class="h4 fw-bold text-dark mb-1">Gerenciar cursos</h1>
        <p class="text-muted small mb-0">Cadastro, valores e situação dos cursos na plataforma.</p>
      </div>
      <button type="button" class="btn btn-dark btn-sm" @click="openCreateModal">
        <i class="bi bi-plus-lg me-1" aria-hidden="true" />
        Novo curso
      </button>
    </div>

    <div class="manage-courses-search position-relative mb-3 col-lg-10 px-0">
      <label class="form-label visually-hidden" for="manage-courses-search">Buscar curso</label>
      <i class="bi bi-search manage-courses-search__icon" aria-hidden="true" />
      <input
        id="manage-courses-search"
        v-model="searchQuery"
        type="search"
        class="form-control manage-courses-search__input"
        placeholder="Buscar por nome do curso"
        autocomplete="off"
      />
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Nome</th>
            <th scope="col">Valor</th>
            <th scope="col">Ativo</th>
            <th scope="col">Período de inscrição</th>
            <th scope="col">Vagas restantes</th>
            <th scope="col" class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="text-center text-muted py-3">Carregando cursos…</td>
          </tr>
          <tr v-else-if="listError">
            <td colspan="6" class="text-center text-danger py-3">{{ listError }}</td>
          </tr>
          <tr v-else-if="filteredCourses.length === 0">
            <td colspan="6" class="text-center text-muted py-3">Nenhum curso encontrado.</td>
          </tr>
          <tr v-for="c in pagedCourses" v-else :key="c.id">
            <td>{{ c.name }}</td>
            <td class="text-nowrap">{{ formatPrice(c.price_cents, c.currency) }}</td>
            <td>
              <span :class="statusClass(c.active)">{{ statusLabel(c.active) }}</span>
            </td>
            <td class="text-nowrap small">{{ formatEnrollmentPeriod(c) }}</td>
            <td class="text-nowrap">{{ formatRemainingSeats(c) }}</td>
            <td class="text-end text-nowrap">
              <RouterLink
                :to="{ name: 'admin-enrollments', query: { course_id: String(c.id) } }"
                class="btn btn-link btn-sm text-primary p-1 me-1"
                title="Ver alunos inscritos"
                aria-label="Ver alunos inscritos"
              >
                <i class="bi bi-people" aria-hidden="true" />
              </RouterLink>
              <button
                type="button"
                class="btn btn-link btn-sm text-primary p-1 me-1"
                aria-label="Editar curso"
                @click="openEditModal(c)"
              >
                <i class="bi bi-pencil-square" aria-hidden="true" />
              </button>
              <button
                type="button"
                class="btn btn-link btn-sm text-danger p-1"
                aria-label="Excluir curso"
                @click="openDeleteModal(c)"
              >
                <i class="bi bi-trash3" aria-hidden="true" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination v-if="!loading && !listError && filteredCourses.length > 0" v-model="currentPage" :total-pages="totalPages" />
  </div>

  <div v-if="showFormModal" class="course-modal-backdrop" @click.self="closeFormModal">
    <div class="course-modal card border-0 shadow-lg">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 fw-bold mb-0">{{ isEditMode ? 'Editar curso' : 'Novo curso' }}</h2>
          <button type="button" class="btn btn-link text-dark p-0" aria-label="Fechar" @click="closeFormModal">
            <i class="bi bi-x-lg fs-5" />
          </button>
        </div>
        <form @submit.prevent="submitCourseForm">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Nome *</label>
            <input
              ref="nameInput"
              v-model="courseForm.name"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': fieldErrors.name }"
              @blur="validateForm"
            />
            <div v-if="fieldErrors.name" class="invalid-feedback d-block">{{ fieldErrors.name }}</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Descrição *</label>
            <textarea
              v-model="courseForm.description"
              class="form-control"
              :class="{ 'is-invalid': fieldErrors.description }"
              rows="3"
              placeholder="Resumo do curso"
              @blur="validateForm"
            />
            <div v-if="fieldErrors.description" class="invalid-feedback d-block">
              {{ fieldErrors.description }}
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Banner da vitrine</label>
            <p class="text-muted small mb-2 mb-md-3">
              Imagem do card na vitrine. JPG, PNG, WebP ou GIF — até 5&nbsp;MB. Proporção sugerida 16:10.
            </p>
            <div
              v-if="bannerDisplayUrl"
              class="course-form-banner-preview rounded border overflow-hidden mb-2"
            >
              <img :src="bannerDisplayUrl" alt="" class="w-100 course-form-banner-preview__img" />
            </div>
            <p v-else-if="isEditMode && removeBanner && savedBannerUrl" class="small text-warning mb-2">
              A imagem atual será removida ao salvar.
            </p>
            <input
              ref="bannerInputRef"
              type="file"
              class="form-control form-control-sm"
              accept="image/jpeg,image/png,image/webp,image/gif"
              @change="onBannerFileChange"
            />
            <button
              v-if="bannerDisplayUrl || (isEditMode && savedBannerUrl && !removeBanner)"
              type="button"
              class="btn btn-link btn-sm text-secondary p-0 mt-2"
              @click="clearBanner"
            >
              Remover imagem
            </button>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Materiais do curso</label>
            <p class="text-muted small mb-2">
              Arquivos para alunos (PDF, ZIP, planilhas, imagens, etc.). Até 20&nbsp;MB por arquivo e até 30 arquivos
              por curso.
            </p>
            <ul
              v-if="existingMaterials.length || pendingMaterialFiles.length"
              class="list-group list-group-flush border rounded mb-2 small"
            >
              <li
                v-for="(m, i) in existingMaterials"
                :key="'mat-' + m.path"
                class="list-group-item d-flex justify-content-between align-items-center gap-2 py-2"
              >
                <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                  <a
                    v-if="m.url"
                    :href="m.url"
                    class="text-truncate text-decoration-none"
                    target="_blank"
                    rel="noopener noreferrer"
                    :title="m.name"
                    >{{ m.name }}</a
                  >
                  <span v-else class="text-truncate">{{ m.name }}</span>
                  <span class="badge text-bg-light text-secondary border">salvo</span>
                </div>
                <button
                  type="button"
                  class="btn btn-link btn-sm text-danger p-0 text-nowrap flex-shrink-0"
                  @click="removeExistingMaterial(i)"
                >
                  Remover
                </button>
              </li>
              <li
                v-for="(f, i) in pendingMaterialFiles"
                :key="'new-' + i + '-' + f.name"
                class="list-group-item d-flex justify-content-between align-items-center gap-2 py-2"
              >
                <span class="text-truncate min-w-0"
                  >{{ f.name }} <span class="text-muted">(será enviado)</span></span
                >
                <button
                  type="button"
                  class="btn btn-link btn-sm text-danger p-0 text-nowrap flex-shrink-0"
                  @click="removePendingMaterial(i)"
                >
                  Remover
                </button>
              </li>
            </ul>
            <input
              ref="materialsInputRef"
              type="file"
              class="form-control form-control-sm"
              multiple
              @change="onMaterialsFileChange"
            />
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Valor (R$) *</label>
              <input
                v-model="courseForm.price_reais"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': fieldErrors.price_reais }"
                placeholder="ex.: 99,90"
                inputmode="decimal"
                @blur="validateForm"
              />
              <div v-if="fieldErrors.price_reais" class="invalid-feedback d-block">{{ fieldErrors.price_reais }}</div>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check mb-1">
                <input id="course-active" v-model="courseForm.active" class="form-check-input" type="checkbox" />
                <label class="form-check-label small" for="course-active">Curso ativo na vitrine</label>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Início das inscrições</label>
              <input
                v-model="courseForm.enrollment_starts_at"
                type="date"
                class="form-control"
                :max="courseForm.enrollment_ends_at || undefined"
                @change="validateEnrollmentDates"
              />
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Fim das inscrições</label>
              <input
                v-model="courseForm.enrollment_ends_at"
                type="date"
                class="form-control"
                :class="{ 'is-invalid': fieldErrors.enrollment_ends_at }"
                :min="courseForm.enrollment_starts_at || undefined"
                @change="validateEnrollmentDates"
              />
              <div v-if="fieldErrors.enrollment_ends_at" class="invalid-feedback d-block">
                {{ fieldErrors.enrollment_ends_at }}
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Vagas (máx.)</label>
              <input
                v-model="courseForm.max_seats"
                type="number"
                min="1"
                class="form-control"
                placeholder="Em branco = ilimitado"
                inputmode="numeric"
              />
            </div>
          </div>
          <p v-if="formError" class="text-danger small mt-3 mb-0">{{ formError }}</p>
          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-dark" :disabled="formSubmitting">
              {{ formSubmitting ? 'Salvando…' : isEditMode ? 'Salvar' : 'Cadastrar' }}
            </button>
            <button type="button" class="btn btn-light border" :disabled="formSubmitting" @click="closeFormModal">
              Cancelar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div v-if="showDeleteModal" class="course-modal-backdrop" @click.self="closeDeleteModal">
    <div class="course-delete-modal card border-0 shadow-lg">
      <div class="card-body p-4 text-center">
        <p class="text-muted small mb-2">Tem certeza de que deseja excluir</p>
        <p class="fw-semibold text-dark mb-3">{{ courseToDelete?.name }}?</p>
        <p class="text-muted small mb-4">Inscrições existentes podem ficar inconsistentes.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
          <button type="button" class="btn btn-light border" :disabled="deleteSubmitting" @click="closeDeleteModal">
            Cancelar
          </button>
          <button type="button" class="btn btn-danger" :disabled="deleteSubmitting" @click="confirmDeleteCourse">
            {{ deleteSubmitting ? 'Excluindo…' : 'Excluir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.manage-courses-search__icon {
  position: absolute;
  left: 0.85rem;
  top: 50%;
  transform: translateY(-50%);
  color: #868e96;
  font-size: 1rem;
  pointer-events: none;
  z-index: 1;
}

.manage-courses-search__input {
  padding-left: 2.5rem;
  border-radius: 0.5rem;
  border: 1px solid #dee2e6;
  background: #f8f9fa;
}

.manage-courses-search__input:focus {
  background: #fff;
}

.course-modal-backdrop {
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

.course-modal {
  width: min(560px, 100%);
  border-radius: 0.75rem;
  margin: 1.5rem auto;
  max-height: calc(100vh - 3rem);
  max-height: calc(100dvh - 3rem);
  overflow-y: auto;
  flex-shrink: 0;
}

.course-form-banner-preview {
  background: #f1f3f5;
}

.course-form-banner-preview__img {
  display: block;
  max-height: 180px;
  object-fit: cover;
}

.course-delete-modal {
  width: min(400px, 100%);
  border-radius: 0.75rem;
  margin: 1.5rem auto;
  max-height: calc(100vh - 3rem);
  max-height: calc(100dvh - 3rem);
  overflow-y: auto;
  flex-shrink: 0;
}
</style>

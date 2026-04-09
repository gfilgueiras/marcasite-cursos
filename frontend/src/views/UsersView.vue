<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppPagination from '../components/AppPagination.vue'
import api from '../api'

const route = useRoute()
const currentPage = ref(1)
const pageSize = 10
const showNewUserModal = ref(false)
const formError = ref('')
const nameInput = ref(null)
const loading = ref(false)
const listError = ref('')
const notice = reactive({
  show: false,
  type: 'success',
  message: '',
})
const avatarInput = ref(null)
const avatarPreview = ref('')
const users = ref([])
const lastPage = ref(1)
const selectedUserId = computed(() => route.query.id || '')

const showDeleteModal = ref(false)
const userToDelete = ref(null)
const deleteSubmitting = ref(false)

const editingUserId = ref(null)

const newUserForm = reactive({
  name: '',
  email: '',
  type: '',
  password: '',
  password_confirmation: '',
  send_confirmation_email: true,
  active: true,
})

const userTypes = ['Administrador', 'Editor', 'Instrutor', 'Aluno']
const fieldErrors = reactive({
  name: '',
  type: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const roleToType = {
  admin: 'Administrador',
  editor: 'Editor',
  instrutor: 'Instrutor',
  aluno: 'Aluno',
  user: 'Aluno',
}
const typeToRole = {
  Administrador: 'admin',
  Editor: 'editor',
  Instrutor: 'instrutor',
  Aluno: 'aluno',
}

const totalPages = computed(() => Math.max(1, lastPage.value))

const isEditMode = computed(() => editingUserId.value !== null)

function resetForm() {
  editingUserId.value = null
  newUserForm.name = ''
  newUserForm.email = ''
  newUserForm.type = ''
  newUserForm.password = ''
  newUserForm.password_confirmation = ''
  newUserForm.send_confirmation_email = true
  newUserForm.active = true
  formError.value = ''
  avatarPreview.value = ''
  fieldErrors.name = ''
  fieldErrors.type = ''
  fieldErrors.email = ''
  fieldErrors.password = ''
  fieldErrors.password_confirmation = ''
}

function openNewUserModal() {
  resetForm()
  showNewUserModal.value = true
  nextTick(() => {
    nameInput.value?.focus()
  })
}

function closeNewUserModal() {
  showNewUserModal.value = false
  resetForm()
}

function openEditUserModal(user) {
  editingUserId.value = user.id
  newUserForm.name = user.name
  newUserForm.email = user.email
  newUserForm.type = user.type
  newUserForm.password = ''
  newUserForm.password_confirmation = ''
  newUserForm.send_confirmation_email = false
  newUserForm.active = user.active !== false
  formError.value = ''
  avatarPreview.value = ''
  fieldErrors.name = ''
  fieldErrors.type = ''
  fieldErrors.email = ''
  fieldErrors.password = ''
  fieldErrors.password_confirmation = ''
  showNewUserModal.value = true
  nextTick(() => {
    nameInput.value?.focus()
  })
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim())
}

async function loadUsers() {
  loading.value = true
  listError.value = ''
  try {
    const perPage = selectedUserId.value ? 500 : pageSize
    const page = selectedUserId.value ? 1 : currentPage.value
    const { data } = await api.get('/api/v1/admin/users', {
      params: { page, per_page: perPage },
    })
    let rows = data?.data || []
    if (selectedUserId.value) {
      rows = rows.filter((u) => String(u.id) === String(selectedUserId.value))
    }
    users.value = rows.map((u) => ({
      id: u.id,
      name: u.name,
      email: u.email,
      role: u.role,
      type: roleToType[u.role] || u.role,
      active: u.active ?? true,
    }))
    lastPage.value = selectedUserId.value ? 1 : data?.meta?.last_page || 1
  } catch (e) {
    users.value = []
    lastPage.value = 1
    listError.value = 'Não foi possível carregar os usuários. Tente novamente em instantes.'
    showNotice('error', e?.response?.data?.message || listError.value)
  } finally {
    loading.value = false
  }
}

function validateField(field) {
  if (field === 'name') {
    fieldErrors.name = newUserForm.name.trim() ? '' : 'Informe o nome do usuário.'
    return !fieldErrors.name
  }
  if (field === 'type') {
    fieldErrors.type = newUserForm.type ? '' : 'Selecione o tipo de usuário.'
    return !fieldErrors.type
  }
  if (field === 'email') {
    fieldErrors.email = isValidEmail(newUserForm.email) ? '' : 'Informe um e-mail válido.'
    return !fieldErrors.email
  }
  if (field === 'password') {
    if (editingUserId.value && !String(newUserForm.password).trim()) {
      fieldErrors.password = ''
      fieldErrors.password_confirmation = ''
      return true
    }
    fieldErrors.password =
      newUserForm.password.length >= 8 ? '' : 'A senha deve ter no mínimo 8 caracteres.'
    if (newUserForm.password_confirmation) {
      validateField('password_confirmation')
    }
    return !fieldErrors.password
  }
  if (field === 'password_confirmation') {
    if (editingUserId.value && !String(newUserForm.password).trim()) {
      fieldErrors.password_confirmation = ''
      return true
    }
    fieldErrors.password_confirmation =
      newUserForm.password === newUserForm.password_confirmation
        ? ''
        : 'A confirmação de senha não confere.'
    return !fieldErrors.password_confirmation
  }
  return true
}

function openAvatarPicker() {
  avatarInput.value?.click()
}

function onAvatarSelected(event) {
  const file = event.target.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/')) {
    formError.value = 'Selecione um arquivo de imagem válido.'
    return
  }
  avatarPreview.value = URL.createObjectURL(file)
  formError.value = ''
}

async function submitUserForm() {
  formError.value = ''
  const baseValid = validateField('name') && validateField('type') && validateField('email')
  let passwordValid = true
  if (editingUserId.value) {
    if (String(newUserForm.password).trim()) {
      passwordValid = validateField('password') && validateField('password_confirmation')
    } else {
      fieldErrors.password = ''
      fieldErrors.password_confirmation = ''
    }
  } else {
    passwordValid = validateField('password') && validateField('password_confirmation')
  }
  if (!baseValid || !passwordValid) {
    formError.value = 'Corrija os campos destacados para continuar.'
    showNotice('error', formError.value)
    return
  }

  try {
    if (editingUserId.value) {
      const payload = {
        name: newUserForm.name.trim(),
        email: newUserForm.email.trim(),
        role: typeToRole[newUserForm.type] || 'user',
        active: Boolean(newUserForm.active),
      }
      if (String(newUserForm.password).trim()) {
        payload.password = newUserForm.password
        payload.password_confirmation = newUserForm.password_confirmation
      }
      await api.put(`/api/v1/admin/users/${editingUserId.value}`, payload)
      await loadUsers()
      showNotice('success', 'Usuário atualizado com sucesso.')
    } else {
      await api.post('/api/v1/admin/users', {
        name: newUserForm.name.trim(),
        email: newUserForm.email.trim(),
        password: newUserForm.password,
        role: typeToRole[newUserForm.type] || 'user',
      })
      currentPage.value = 1
      await loadUsers()
      showNotice('success', 'Usuário cadastrado com sucesso.')
    }
    showNewUserModal.value = false
    resetForm()
  } catch (e) {
    const errors = e?.response?.data?.errors
    const firstError = errors ? Object.values(errors).flat()[0] : null
    showNotice(
      'error',
      firstError ||
        e?.response?.data?.message ||
        (editingUserId.value ? 'Não foi possível atualizar o usuário.' : 'Não foi possível cadastrar o usuário.'),
    )
  }
}

function showNotice(type, message) {
  notice.type = type
  notice.message = message
  notice.show = true
  window.setTimeout(() => {
    notice.show = false
  }, 2800)
}

function openDeleteModal(user) {
  userToDelete.value = user
  showDeleteModal.value = true
}

function closeDeleteModal() {
  if (deleteSubmitting.value) return
  showDeleteModal.value = false
  userToDelete.value = null
}

async function confirmDeleteUser() {
  if (!userToDelete.value?.id) return
  deleteSubmitting.value = true
  try {
    await api.delete(`/api/v1/admin/users/${userToDelete.value.id}`)
    deleteSubmitting.value = false
    closeDeleteModal()
    await loadUsers()
    if (currentPage.value > lastPage.value && lastPage.value >= 1) {
      currentPage.value = lastPage.value
      await loadUsers()
    }
    showNotice('success', 'Usuário excluído com sucesso.')
  } catch (e) {
    const msg = e?.response?.data?.message || 'Não foi possível excluir o usuário.'
    showNotice('error', msg)
  } finally {
    deleteSubmitting.value = false
  }
}

onMounted(() => {
  loadUsers()
})

watch(currentPage, () => {
  if (selectedUserId.value) return
  loadUsers()
})

watch(selectedUserId, () => {
  currentPage.value = 1
  loadUsers()
})
</script>

<template>
  <transition name="notice-fade">
    <div v-if="notice.show" class="floating-notice" :class="notice.type === 'success' ? 'is-success' : 'is-error'">
      <i class="bi" :class="notice.type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'" />
      <span>{{ notice.message }}</span>
      <button type="button" class="btn btn-link p-0 text-reset notice-close" @click="notice.show = false">
        <i class="bi bi-x-lg" />
      </button>
    </div>
  </transition>

  <div class="bg-white rounded shadow-sm border p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h1 class="h4 fw-bold text-dark mb-1">Usuarios</h1>
        <p class="text-muted small mb-0">Gerencie os acessos da plataforma.</p>
      </div>
      <button type="button" class="btn btn-dark btn-sm" @click="openNewUserModal">
        <i class="bi bi-plus-lg me-1" aria-hidden="true" />
        Novo usuario
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Nome</th>
            <th scope="col">tipo</th>
            <th scope="col">Ativo</th>
            <th scope="col" class="text-end" />
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="4" class="text-center text-muted py-3">Carregando usuários…</td>
          </tr>
          <tr v-else-if="listError">
            <td colspan="4" class="text-center text-danger py-3">{{ listError }}</td>
          </tr>
          <tr v-else-if="users.length === 0">
            <td colspan="4" class="text-center text-muted py-3">Nenhum usuário encontrado.</td>
          </tr>
          <tr v-for="user in users" v-else :key="user.id">
            <td>{{ user.name }}</td>
            <td>{{ user.type }}</td>
            <td>
              <span class="badge" :class="user.active ? 'text-bg-success' : 'text-bg-secondary'">
                {{ user.active ? 'Sim' : 'Nao' }}
              </span>
            </td>
            <td class="text-end">
              <button
                type="button"
                class="btn btn-link btn-sm text-primary p-1 me-1"
                aria-label="Editar usuario"
                @click="openEditUserModal(user)"
              >
                <i class="bi bi-pencil-square" aria-hidden="true" />
              </button>
              <button
                type="button"
                class="btn btn-link btn-sm text-danger p-1"
                aria-label="Remover usuario"
                @click.stop="openDeleteModal(user)"
              >
                <i class="bi bi-trash3" aria-hidden="true" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination v-model="currentPage" :total-pages="totalPages" />
  </div>

  <div v-if="showDeleteModal" class="user-modal-backdrop" @click.self="closeDeleteModal">
    <div class="delete-confirm-modal card border-0 shadow-lg" role="dialog" aria-modal="true" aria-labelledby="delete-user-title">
      <div class="card-body p-4 p-md-5 text-center">
        <div class="delete-confirm-icon mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle">
          <i class="bi bi-trash3 text-danger" style="font-size: 1.75rem" aria-hidden="true" />
        </div>
        <h2 id="delete-user-title" class="h5 fw-bold text-dark mb-3">Excluir usuário?</h2>
        <p class="text-muted small mb-2 mb-md-3">Tem certeza de que deseja remover</p>
        <p class="fw-semibold text-dark mb-3 fs-5">{{ userToDelete?.name }}?</p>
        <p class="text-muted small mb-4">Esta ação não pode ser desfeita.</p>
        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
          <button type="button" class="btn btn-light border px-4" :disabled="deleteSubmitting" @click="closeDeleteModal">
            Cancelar
          </button>
          <button type="button" class="btn btn-danger px-4" :disabled="deleteSubmitting" @click="confirmDeleteUser">
            <span v-if="deleteSubmitting" class="spinner-border spinner-border-sm me-1" aria-hidden="true" />
            {{ deleteSubmitting ? 'Excluindo…' : 'Excluir' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <div v-if="showNewUserModal" class="user-modal-backdrop" @click.self="closeNewUserModal">
    <div class="user-modal card border-0 shadow-lg">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="h4 fw-bold mb-0">{{ isEditMode ? 'Editar usuário' : 'Novo usuário' }}</h2>
          <button type="button" class="btn btn-link text-dark p-0" aria-label="Fechar" @click="closeNewUserModal">
            <i class="bi bi-x-lg fs-5" />
          </button>
        </div>

        <form @submit.prevent="submitUserForm">
          <div class="row g-4">
            <div class="col-lg-8">
              <h3 class="h6 fw-semibold mb-3">Detalhes de usuário</h3>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Nome *</label>
                  <input
                    ref="nameInput"
                    v-model="newUserForm.name"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.name }"
                    required
                    @blur="validateField('name')"
                  />
                  <div v-if="fieldErrors.name" class="invalid-feedback">{{ fieldErrors.name }}</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Tipo de usuário *</label>
                  <select
                    v-model="newUserForm.type"
                    class="form-select"
                    :class="{ 'is-invalid': fieldErrors.type }"
                    required
                    @blur="validateField('type')"
                  >
                    <option disabled value="">Selecione</option>
                    <option v-for="t in userTypes" :key="t" :value="t">{{ t }}</option>
                  </select>
                  <div v-if="fieldErrors.type" class="invalid-feedback">{{ fieldErrors.type }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label small fw-semibold">Email *</label>
                  <input
                    v-model="newUserForm.email"
                    type="email"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.email }"
                    placeholder="email@address.com"
                    required
                    @blur="validateField('email')"
                  />
                  <div v-if="fieldErrors.email" class="invalid-feedback">{{ fieldErrors.email }}</div>
                </div>

                <div v-if="isEditMode" class="col-12">
                  <label class="form-label small fw-semibold" for="user-active">Situação *</label>
                  <select
                    id="user-active"
                    v-model="newUserForm.active"
                    class="form-select user-form-situation-select"
                  >
                    <option :value="true">Ativo</option>
                    <option :value="false">Inativo</option>
                  </select>
                  <p class="form-text small text-muted mb-0 mt-1">
                    Define se o usuário pode acessar a plataforma.
                  </p>
                </div>

                <div class="col-12">
                  <div class="user-form-password-box border rounded-3 p-3 p-md-4 bg-light">
                    <p class="small fw-semibold text-dark mb-3 mb-md-4">Senha de acesso</p>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Senha</label>
                        <input
                          v-model="newUserForm.password"
                          type="password"
                          :minlength="isEditMode ? undefined : 8"
                          class="form-control"
                          :class="{ 'is-invalid': fieldErrors.password }"
                          :placeholder="isEditMode ? 'Deixe em branco para manter' : 'Mínimo 8 caracteres'"
                          :required="!isEditMode"
                          autocomplete="new-password"
                          @blur="validateField('password')"
                        />
                        <div v-if="fieldErrors.password" class="invalid-feedback d-block">
                          {{ fieldErrors.password }}
                        </div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Confirmar senha</label>
                        <input
                          v-model="newUserForm.password_confirmation"
                          type="password"
                          :minlength="isEditMode ? undefined : 8"
                          class="form-control"
                          :class="{ 'is-invalid': fieldErrors.password_confirmation }"
                          :placeholder="isEditMode ? 'Repita a nova senha' : 'Repita a senha'"
                          :required="!isEditMode"
                          autocomplete="new-password"
                          @blur="validateField('password_confirmation')"
                        />
                        <div v-if="fieldErrors.password_confirmation" class="invalid-feedback d-block">
                          {{ fieldErrors.password_confirmation }}
                        </div>
                      </div>
                    </div>
                    <p v-if="isEditMode" class="form-text small text-muted mb-0 mt-3">
                      Deixe os dois campos em branco para manter a senha atual.
                    </p>
                  </div>
                </div>

                <div v-if="!isEditMode" class="col-12">
                  <label class="d-flex align-items-center gap-2">
                    <input v-model="newUserForm.send_confirmation_email" class="form-check-input m-0" type="checkbox" />
                    <span>Enviar confirmação por e-mail</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="col-lg-4 border-start">
              <h3 class="h6 fw-semibold mb-3 ps-lg-3">Foto de perfil</h3>
              <div class="ps-lg-3">
                <div class="avatar-panel">
                  <div class="avatar-placeholder d-flex align-items-center justify-content-center mb-3">
                    <img v-if="avatarPreview" :src="avatarPreview" alt="Preview do avatar" class="avatar-preview" />
                    <i v-else class="bi bi-image text-secondary fs-1" />
                  </div>
                  <input
                    ref="avatarInput"
                    type="file"
                    class="d-none"
                    accept="image/*"
                    @change="onAvatarSelected"
                  />
                  <button type="button" class="btn btn-dark w-100" @click="openAvatarPicker">Upload</button>
                </div>
              </div>
            </div>
          </div>

          <p v-if="formError" class="text-danger small mt-3 mb-0">{{ formError }}</p>

          <div class="d-flex gap-3 mt-4">
            <button type="submit" class="btn btn-dark px-4">
              {{ isEditMode ? 'Salvar' : 'Adicionar' }}
            </button>
            <button type="button" class="btn btn-light border px-4" @click="closeNewUserModal">Cancelar</button>
          </div>
        </form>
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
  display: grid;
  place-items: center;
  padding: 1rem;
}

.user-modal {
  width: min(980px, 100%);
  border-radius: 0.75rem;
}

.delete-confirm-modal {
  width: min(420px, 100%);
  border-radius: 0.75rem;
}

.delete-confirm-icon {
  width: 4rem;
  height: 4rem;
  background: rgba(220, 53, 69, 0.12);
}

.avatar-placeholder {
  width: 100%;
  aspect-ratio: 1 / 1;
  background: #f1f3f5;
  border-radius: 1rem;
  overflow: hidden;
}

.avatar-panel {
  width: 190px;
  max-width: 100%;
}

.avatar-preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.floating-notice {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 1400;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 300px;
  max-width: min(92vw, 420px);
  border-radius: 0.65rem;
  padding: 0.7rem 0.85rem;
  box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.16);
  border: 1px solid transparent;
}

.floating-notice.is-success {
  background: #e8f7ee;
  border-color: #bce3ca;
  color: #166534;
}

.floating-notice.is-error {
  background: #fdecec;
  border-color: #f6bfc1;
  color: #9f1239;
}

.notice-close {
  margin-left: auto;
  opacity: 0.7;
}

.notice-close:hover {
  opacity: 1;
}

.notice-fade-enter-active,
.notice-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.notice-fade-enter-from,
.notice-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

.user-form-situation-select {
  max-width: 20rem;
}

.user-form-password-box {
  border-color: #e9ecef !important;
}
</style>

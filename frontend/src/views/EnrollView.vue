<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '../api'

const route = useRoute()
const courseId = route.params.id

const courseName = ref('')
const loadingCourse = ref(true)

const form = ref({
  name: '',
  email: '',
  phone: '',
  document: '',
})

const fieldErrors = ref({
  name: '',
  email: '',
  phone: '',
  document: '',
})

const submitting = ref(false)
const error = ref('')

const EMAIL_RE =
  /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/

/** Apenas dígitos; remove código do país 55 quando presente. */
function normalizeBrazilPhoneDigits(raw) {
  let d = String(raw ?? '').replace(/\D/g, '')
  if (d.startsWith('55') && d.length >= 12) {
    d = d.slice(2)
  }
  return d
}

/**
 * Máscara (xx) xxxxx-xxxx (celular, 9 na primeira posição local) ou (xx) xxxx-xxxx (fixo).
 */
function formatBrazilPhoneMask(raw) {
  let digits = String(raw ?? '').replace(/\D/g, '')
  if (digits.startsWith('55') && digits.length >= 12) {
    digits = digits.slice(2)
  }
  digits = digits.slice(0, 11)
  if (digits.length === 0) {
    return ''
  }
  if (digits.length <= 2) {
    return `(${digits}`
  }
  const dd = digits.slice(0, 2)
  const local = digits.slice(2)
  const isMobile = local.length > 0 && local[0] === '9'
  const maxLocal = isMobile ? 9 : 8
  const loc = local.slice(0, maxLocal)
  if (loc.length === 0) {
    return `(${dd}) `
  }
  if (isMobile) {
    if (loc.length <= 5) {
      return `(${dd}) ${loc}`
    }
    return `(${dd}) ${loc.slice(0, 5)}-${loc.slice(5)}`
  }
  if (loc.length <= 4) {
    return `(${dd}) ${loc}`
  }
  return `(${dd}) ${loc.slice(0, 4)}-${loc.slice(4)}`
}

/** Máscara xxx.xxx.xxx-xx */
function formatCpfMask(raw) {
  const d = String(raw ?? '')
    .replace(/\D/g, '')
    .slice(0, 11)
  if (d.length <= 3) {
    return d
  }
  if (d.length <= 6) {
    return `${d.slice(0, 3)}.${d.slice(3)}`
  }
  if (d.length <= 9) {
    return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`
  }
  return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9, 11)}`
}

function onPhoneInput(event) {
  form.value.phone = formatBrazilPhoneMask(event.target.value)
  clearFieldError('phone')
}

function onCpfInput(event) {
  form.value.document = formatCpfMask(event.target.value)
  clearFieldError('document')
}

function isValidCpfDigits(digits) {
  if (digits.length !== 11) return false
  if (/^(\d)\1{10}$/.test(digits)) return false
  let sum = 0
  for (let i = 0; i < 9; i++) {
    sum += parseInt(digits[i], 10) * (10 - i)
  }
  let rest = (sum * 10) % 11
  const d1 = rest === 10 ? 0 : rest
  if (d1 !== parseInt(digits[9], 10)) return false
  sum = 0
  for (let i = 0; i < 10; i++) {
    sum += parseInt(digits[i], 10) * (11 - i)
  }
  rest = (sum * 10) % 11
  const d2 = rest === 10 ? 0 : rest
  return d2 === parseInt(digits[10], 10)
}

function validateName() {
  const v = form.value.name?.trim() ?? ''
  if (!v) return 'Informe o nome completo.'
  if (v.length < 2) return 'Informe pelo menos 2 caracteres.'
  if (v.length > 255) return 'O nome pode ter no máximo 255 caracteres.'
  return ''
}

function validateEmail() {
  const v = form.value.email?.trim() ?? ''
  if (!v) return 'Informe o e-mail.'
  if (v.length > 255) return 'O e-mail pode ter no máximo 255 caracteres.'
  if (!EMAIL_RE.test(v)) return 'Informe um e-mail válido.'
  return ''
}

function validatePhone() {
  const raw = form.value.phone?.trim() ?? ''
  if (!raw) return 'Informe o telefone ou WhatsApp.'
  const digits = normalizeBrazilPhoneDigits(raw)
  if (digits.length < 10 || digits.length > 11) {
    return 'Informe DDD + número (10 ou 11 dígitos), com ou sem código 55.'
  }
  if (!/^[1-9]\d{9,10}$/.test(digits)) {
    return 'Informe um telefone válido com DDD brasileiro.'
  }
  return ''
}

function validateDocument() {
  const raw = form.value.document?.trim() ?? ''
  if (!raw) return 'Informe o CPF.'
  const digits = raw.replace(/\D/g, '')
  if (digits.length !== 11) return 'O CPF deve ter 11 dígitos.'
  if (!isValidCpfDigits(digits)) return 'CPF inválido.'
  return ''
}

const validators = {
  name: validateName,
  email: validateEmail,
  phone: validatePhone,
  document: validateDocument,
}

function validateField(field) {
  const fn = validators[field]
  if (!fn) return
  fieldErrors.value = { ...fieldErrors.value, [field]: fn() }
}

function clearFieldError(field) {
  if (fieldErrors.value[field]) {
    fieldErrors.value = { ...fieldErrors.value, [field]: '' }
  }
}

function validateAll() {
  let ok = true
  const next = { ...fieldErrors.value }
  for (const key of Object.keys(validators)) {
    const msg = validators[key]()
    next[key] = msg
    if (msg) ok = false
  }
  fieldErrors.value = next
  return ok
}

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v1/courses')
    const list = data.data ?? data
    const found = list.find((c) => String(c.id) === String(courseId))
    courseName.value = found?.name || `Curso #${courseId}`
  } catch {
    courseName.value = `Curso #${courseId}`
  } finally {
    loadingCourse.value = false
  }
})

async function submit() {
  error.value = ''
  if (!validateAll()) {
    return
  }
  submitting.value = true
  try {
    const { data } = await api.post('/api/v1/enrollments', {
      course_id: Number(courseId),
      ...form.value,
    })
    if (data.checkout_url) {
      const e = form.value.email?.trim()
      if (e) {
        localStorage.setItem('student_email', e.toLowerCase())
      }
      window.location.href = data.checkout_url
    }
  } catch (e) {
    const msg = e.response?.data?.message
    const errors = e.response?.data?.errors
    if (errors && typeof errors === 'object') {
      const map = {
        name: errors.name?.[0],
        email: errors.email?.[0],
        phone: errors.phone?.[0],
        document: errors.document?.[0],
      }
      fieldErrors.value = {
        name: map.name || '',
        email: map.email || '',
        phone: map.phone || '',
        document: map.document || '',
      }
      const first = Object.values(errors).flat()[0]
      error.value = first || 'Confira os dados e tente novamente.'
    } else {
      error.value = msg || 'Não foi possível concluir a inscrição. Tente de novo em alguns instantes.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="bg-white rounded shadow-sm border p-4 col-lg-10">
    <p class="small text-muted mb-1">Inscrição</p>
    <h1 class="h3 mb-2">
      <span v-if="loadingCourse">Carregando…</span>
      <span v-else>{{ courseName }}</span>
    </h1>
    <p class="text-muted small mb-4">
      Preencha seus dados de contato. Em seguida você será redirecionado para finalizar o pagamento com cartão de teste (sandbox).
    </p>

    <form class="col-md-8 col-lg-6" novalidate @submit.prevent="submit">
      <div class="mb-3">
        <label class="form-label" for="enroll-name">Nome completo</label>
        <input
          id="enroll-name"
          v-model="form.name"
          type="text"
          class="form-control"
          maxlength="255"
          :class="{ 'is-invalid': fieldErrors.name }"
          placeholder="Como no documento"
          autocomplete="name"
          @blur="validateField('name')"
          @input="clearFieldError('name')"
        />
        <div v-if="fieldErrors.name" class="invalid-feedback d-block">
          {{ fieldErrors.name }}
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="enroll-email">E-mail</label>
        <input
          id="enroll-email"
          v-model="form.email"
          type="email"
          class="form-control"
          maxlength="255"
          :class="{ 'is-invalid': fieldErrors.email }"
          placeholder="seu@email.com"
          autocomplete="email"
          inputmode="email"
          @blur="validateField('email')"
          @input="clearFieldError('email')"
        />
        <div v-if="fieldErrors.email" class="invalid-feedback d-block">
          {{ fieldErrors.email }}
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="enroll-phone">Telefone / WhatsApp</label>
        <input
          id="enroll-phone"
          :value="form.phone"
          type="tel"
          class="form-control"
          maxlength="16"
          :class="{ 'is-invalid': fieldErrors.phone }"
          placeholder="(11) 98765-4321"
          autocomplete="tel"
          inputmode="numeric"
          @blur="validateField('phone')"
          @input="onPhoneInput"
        />
        <div v-if="fieldErrors.phone" class="invalid-feedback d-block">
          {{ fieldErrors.phone }}
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="enroll-doc">CPF</label>
        <input
          id="enroll-doc"
          :value="form.document"
          type="text"
          class="form-control"
          maxlength="14"
          :class="{ 'is-invalid': fieldErrors.document }"
          placeholder="000.000.000-00"
          autocomplete="off"
          inputmode="numeric"
          @blur="validateField('document')"
          @input="onCpfInput"
        />
        <div v-if="fieldErrors.document" class="invalid-feedback d-block">
          {{ fieldErrors.document }}
        </div>
      </div>
      <p v-if="error" class="text-danger small">{{ error }}</p>
      <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-dark" :disabled="submitting || loadingCourse">
          {{ submitting ? 'Abrindo pagamento…' : 'Continuar para o pagamento' }}
        </button>
        <RouterLink to="/courses" class="btn btn-outline-secondary">Voltar ao catálogo</RouterLink>
      </div>
    </form>
  </div>
</template>

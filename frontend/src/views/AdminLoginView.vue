<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'

const route = useRoute()
const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function login() {
  error.value = ''
  loading.value = true
  try {
    const { data } = await api.post('/api/v1/auth/login', {
      email: email.value,
      password: password.value,
    })
    localStorage.setItem('admin_token', data.token)
    const next = typeof route.query.redirect === 'string' ? route.query.redirect : ''
    await router.push(next && next.startsWith('/') ? next : '/admin/inscricoes')
  } catch {
    error.value =
      'Não foi possível entrar. Confira e-mail e senha. Somente usuários com perfil de administrador podem acessar este painel.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="admin-login-shell min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="bg-white rounded shadow-sm border p-4 w-100" style="max-width: 440px">
    <p class="small text-muted mb-1">Área restrita</p>
    <h1 class="h3 mb-2">Acesso ao painel</h1>
    <p class="text-muted small mb-4">
      Use as credenciais definidas no ambiente (por exemplo, o usuário criado pelo comando de seed do Laravel).
    </p>
    <form @submit.prevent="login">
      <div class="mb-3">
        <label class="form-label" for="admin-email">E-mail institucional</label>
        <input id="admin-email" v-model="email" type="email" class="form-control" required autocomplete="username" />
      </div>
      <div class="mb-3">
        <label class="form-label" for="admin-password">Senha</label>
        <input
          id="admin-password"
          v-model="password"
          type="password"
          class="form-control"
          required
          autocomplete="current-password"
        />
      </div>
      <p v-if="error" class="text-danger small">{{ error }}</p>
      <button type="submit" class="btn btn-dark" :disabled="loading">
        {{ loading ? 'Entrando…' : 'Entrar no painel' }}
      </button>
    </form>
    <p class="small text-muted mt-4 mb-0">
      Dica para desenvolvimento: após <code class="small">php artisan db:seed</code>, o padrão costuma ser
      <strong>admin@marcasite.local</strong> com senha <strong>password</strong> (altere em produção).
    </p>
    </div>
  </div>
</template>

<style scoped>
.admin-login-shell {
  background: #f1f3f5;
}
</style>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import api from '../api'
import { mockPlatformUsers } from '../data/mockUsers'

const router = useRouter()
const route = useRoute()
const sidebarOpen = ref(false)

const searchRoot = ref(null)
const notifRoot = ref(null)
const searchQuery = ref('')
const dropdownOpen = ref(false)
const loadingCourses = ref(false)
const loadingUsers = ref(false)
const coursesCache = ref([])
const usersCache = ref([])
const courseHits = ref([])
const userHits = ref([])
const notificationsOpen = ref(false)
const notifications = ref([
  { id: 1, kind: 'Curso', text: 'Novo curso publicado: Docker para Desenvolvedores.', read: false },
  { id: 2, kind: 'Usuário', text: 'Lucas Ferreira atualizou o perfil.', read: false },
  { id: 3, kind: 'Inscrição', text: 'Pagamento aprovado para Vue 3 do Zero ao Deploy.', read: false },
  { id: 4, kind: 'Curso', text: 'Aula extra adicionada em Laravel Profissional.', read: true },
  { id: 5, kind: 'Usuário', text: 'Renata Moura foi promovida para Administrador.', read: false },
])

const MIN_CHARS = 3
const MAX_RESULTS = 8
const DEBOUNCE_MS = 280

let debounceId = null

watch(
  () => route.fullPath,
  () => {
    sidebarOpen.value = false
  },
)

function matchesQuery(haystack, needle) {
  if (haystack === undefined || haystack === null) return false
  const normalize = (v) =>
    String(v)
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
  return normalize(haystack).includes(normalize(needle))
}

function applyFilter(q) {
  const needle = q.trim().toLowerCase()
  courseHits.value = coursesCache.value
    .filter(
      (c) => matchesQuery(c.name, needle) || matchesQuery(c.description, needle),
    )
    .slice(0, MAX_RESULTS)
  userHits.value = usersCache.value
    .filter(
      (u) =>
        matchesQuery(u.name, needle) ||
        matchesQuery(u.type, needle) ||
        matchesQuery(u.email, needle),
    )
    .slice(0, MAX_RESULTS)
}

async function ensureCoursesLoaded() {
  if (coursesCache.value.length > 0) return
  loadingCourses.value = true
  try {
    const { data } = await api.get('/api/v1/courses')
    coursesCache.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
  } catch {
    coursesCache.value = []
  } finally {
    loadingCourses.value = false
  }
}

async function ensureUsersLoaded() {
  loadingUsers.value = true
  try {
    const { data } = await api.get('/api/v1/admin/users', {
      params: { page: 1, per_page: 200 },
    })
    const rows = Array.isArray(data?.data) ? data.data : []
    usersCache.value = rows.map((u) => ({
      id: u.id,
      name: u.name,
      email: u.email,
      type:
        u.role === 'admin'
          ? 'Administrador'
          : u.role === 'editor'
            ? 'Editor'
            : u.role === 'instrutor'
              ? 'Instrutor'
              : 'Aluno',
    }))
  } catch {
    usersCache.value = mockPlatformUsers
  } finally {
    loadingUsers.value = false
  }
}

async function runSearch() {
  const q = searchQuery.value.trim()
  if (q.length < MIN_CHARS) {
    dropdownOpen.value = false
    return
  }
  await Promise.all([ensureCoursesLoaded(), ensureUsersLoaded()])
  applyFilter(q)
  dropdownOpen.value = true
}

watch(searchQuery, () => {
  clearTimeout(debounceId)
  const q = searchQuery.value.trim()
  if (q.length < MIN_CHARS) {
    dropdownOpen.value = false
    return
  }
  debounceId = setTimeout(() => {
    runSearch()
  }, DEBOUNCE_MS)
})

function onSearchFocus() {
  const q = searchQuery.value.trim()
  if (q.length >= MIN_CHARS) {
    runSearch()
  }
}

function closeDropdown() {
  dropdownOpen.value = false
}

function toggleNotifications() {
  notificationsOpen.value = !notificationsOpen.value
}

function closeNotifications() {
  notificationsOpen.value = false
}

function markAllAsRead() {
  notifications.value = notifications.value.map((item) => ({ ...item, read: true }))
}

function removeNotification(id) {
  notifications.value = notifications.value.filter((item) => item.id !== id)
}

function goCourse(id) {
  closeDropdown()
  router.push({ name: 'courses', query: { id: String(id) } })
}

function goUser(userId) {
  closeDropdown()
  router.push(userId ? { name: 'users', query: { id: String(userId) } } : { name: 'users' })
}

function shortText(text, max = 44) {
  if (!text) return ''
  const t = String(text).trim()
  return t.length > max ? `${t.slice(0, max)}…` : t
}

function onDocPointerDown(e) {
  if (searchRoot.value && !searchRoot.value.contains(e.target)) {
    closeDropdown()
  }
  if (notifRoot.value && !notifRoot.value.contains(e.target)) {
    closeNotifications()
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', onDocPointerDown, true)
})

onUnmounted(() => {
  document.removeEventListener('pointerdown', onDocPointerDown, true)
  clearTimeout(debounceId)
})

const dropdownVisible = computed(() => {
  const q = searchQuery.value.trim()
  return dropdownOpen.value && q.length >= MIN_CHARS
})
const unreadCount = computed(() => notifications.value.filter((item) => !item.read).length)

const isActive = (names) => {
  const n = Array.isArray(names) ? names : [names]
  return n.includes(route.name)
}

function closeSidebar() {
  sidebarOpen.value = false
}

function openSidebar() {
  sidebarOpen.value = true
}
</script>

<template>
  <div class="app-shell d-flex min-vh-100">
    <div
      v-if="sidebarOpen"
      class="sidebar-backdrop d-md-none"
      aria-hidden="true"
      @click="closeSidebar"
    />

    <aside
      class="app-sidebar d-flex flex-column border-end"
      :class="{ 'is-open': sidebarOpen }"
    >
      <div class="d-flex align-items-center justify-content-between px-3 pt-3 pb-2 gap-2">
        <button
          type="button"
          class="btn btn-link text-dark p-1 lh-1 sidebar-close flex-shrink-0"
          aria-label="Fechar menu"
          @click="closeSidebar"
        >
          <i class="bi bi-x-lg" />
        </button>
        <RouterLink
          to="/"
          class="brand text-decoration-none text-dark text-end"
          @click="closeSidebar"
        >
          @ Marcasite
        </RouterLink>
      </div>

      <nav class="nav flex-column gap-1 px-2 pb-3 flex-grow-1" aria-label="Menu principal">
        <RouterLink
          to="/"
          class="nav-item-link"
          :class="{ active: isActive('dashboard') }"
          @click="closeSidebar"
        >
          <i class="bi bi-house-door me-2" aria-hidden="true" />
          Dashboard
        </RouterLink>
        <RouterLink
          :to="{ name: 'courses' }"
          class="nav-item-link"
          :class="{ active: isActive('courses') }"
          @click="closeSidebar"
        >
          <i class="bi bi-book me-2" aria-hidden="true" />
          Vitrine de Cursos
        </RouterLink>
        <RouterLink
          :to="{ name: 'my-courses' }"
          class="nav-item-link"
          :class="{ active: isActive('my-courses') }"
          @click="closeSidebar"
        >
          <i class="bi bi-collection-play me-2" aria-hidden="true" />
          Meus Cursos
        </RouterLink>
        <RouterLink
          :to="{ name: 'manage-courses' }"
          class="nav-item-link"
          :class="{ active: isActive('manage-courses') }"
          @click="closeSidebar"
        >
          <i class="bi bi-journal-text me-2" aria-hidden="true" />
          Gerenciar cursos
        </RouterLink>
        <RouterLink
          :to="{ name: 'users' }"
          class="nav-item-link"
          :class="{ active: isActive('users') }"
          @click="closeSidebar"
        >
          <i class="bi bi-people me-2" aria-hidden="true" />
          Usuarios
        </RouterLink>
        <RouterLink
          to="/configuracoes"
          class="nav-item-link"
          :class="{ active: isActive('settings') }"
          @click="closeSidebar"
        >
          <i class="bi bi-gear me-2" aria-hidden="true" />
          Configurações
        </RouterLink>
      </nav>
    </aside>

    <div class="app-main flex-grow-1 d-flex flex-column min-vh-100">
      <header class="app-topbar border-bottom bg-white px-3 px-md-4 py-3">
        <div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap">
          <button
            type="button"
            class="btn btn-outline-secondary d-md-none btn-icon"
            aria-label="Abrir menu"
            @click="openSidebar"
          >
            <i class="bi bi-list" />
          </button>
          <div
            ref="searchRoot"
            class="search-wrap flex-grow-1"
            style="min-width: 12rem; max-width: 28rem"
          >
            <i class="bi bi-search search-wrap__icon" aria-hidden="true" />
            <input
              v-model="searchQuery"
              type="search"
              class="form-control search-wrap__input"
              placeholder="Buscar cursos e/ou usuários"
              autocomplete="off"
              aria-autocomplete="list"
              :aria-expanded="dropdownVisible"
              aria-controls="global-search-results"
              @focus="onSearchFocus"
              @keydown.escape.prevent="closeDropdown"
            />
            <div
              v-if="dropdownVisible"
              id="global-search-results"
              class="search-dropdown"
              role="listbox"
              aria-label="Resultados da busca"
            >
              <div v-if="loadingCourses || loadingUsers" class="search-dropdown__status px-2 py-2">
                Carregando resultados…
              </div>
              <template v-else>
                <template v-if="courseHits.length">
                  <div class="search-dropdown__label">Vitrine de Cursos</div>
                  <button
                    v-for="c in courseHits"
                    :key="'c-' + c.id"
                    type="button"
                    class="search-dropdown__row search-dropdown__row--course"
                    role="option"
                    @mousedown.prevent="goCourse(c.id)"
                  >
                    <span class="search-dropdown__title">{{ c.name }}</span>
                    <span v-if="c.description" class="search-dropdown__hint">{{ shortText(c.description, 52) }}</span>
                  </button>
                </template>
                <template v-if="userHits.length">
                  <div class="search-dropdown__label">Usuários</div>
                  <button
                    v-for="u in userHits"
                    :key="'u-' + u.id"
                    type="button"
                    class="search-dropdown__row search-dropdown__row--user"
                    role="option"
                    @mousedown.prevent="goUser(u.id)"
                  >
                    <span class="search-dropdown__title">{{ u.name }}</span>
                    <span class="search-dropdown__hint">{{ u.type }} - {{ u.email }}</span>
                  </button>
                </template>
                <div
                  v-if="!courseHits.length && !userHits.length"
                  class="search-dropdown__status px-2 py-2 text-muted"
                >
                  Nenhum curso ou usuário encontrado.
                </div>
              </template>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3 ms-md-auto">
            <div ref="notifRoot" class="notif-wrap position-relative">
              <button
                type="button"
                class="btn btn-link text-dark position-relative p-2 btn-icon"
                aria-label="Notificações"
                :aria-expanded="notificationsOpen"
                aria-controls="topbar-notifications"
                @click="toggleNotifications"
              >
              <i class="bi bi-bell fs-5" />
              <span v-if="unreadCount > 0" class="position-absolute badge rounded-pill bg-dark notif-badge">
                {{ unreadCount }}
              </span>
              </button>
              <div
                v-if="notificationsOpen"
                id="topbar-notifications"
                class="notif-dropdown"
                role="dialog"
                aria-label="Notificações"
              >
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                  <p class="mb-0 fw-semibold small">Notificações</p>
                  <button
                    type="button"
                    class="btn btn-link btn-sm p-0 text-decoration-none"
                    @click="markAllAsRead"
                  >
                    Marcar tudo como lido
                  </button>
                </div>
                <div v-if="notifications.length === 0" class="px-3 py-3 text-muted small">
                  Nenhuma notificação no momento.
                </div>
                <div v-else class="py-1">
                  <div v-for="item in notifications" :key="item.id" class="notif-item">
                    <div class="notif-item__left">
                      <span class="notif-item__kind">{{ item.kind }}</span>
                      <p class="notif-item__text mb-0" :class="{ 'notif-item__text--read': item.read }">
                        {{ item.text }}
                      </p>
                    </div>
                    <button
                      type="button"
                      class="btn btn-link btn-sm text-muted p-1 notif-item__remove"
                      aria-label="Remover notificação"
                      @click="removeNotification(item.id)"
                    >
                      <i class="bi bi-x-lg" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="small text-secondary d-none d-sm-inline user-name">João da Silva</span>
              <div class="user-avatar rounded-circle bg-secondary" role="img" aria-label="Avatar do usuário" />
            </div>
          </div>
        </div>
      </header>

      <div class="app-content flex-grow-1 p-3 p-md-4">
        <RouterView />
      </div>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  --sidebar-bg: #e8eaed;
  --page-bg: #f1f3f5;
  --sidebar-width: 260px;
}

.app-sidebar {
  width: var(--sidebar-width);
  min-width: var(--sidebar-width);
  background: var(--sidebar-bg);
  z-index: 1050;
}

.brand {
  font-weight: 700;
  font-size: 1.1rem;
  letter-spacing: -0.02em;
}

.nav-item-link {
  display: flex;
  align-items: center;
  padding: 0.65rem 1rem;
  border-radius: 0.65rem;
  color: #343a40;
  text-decoration: none;
  font-size: 0.95rem;
  transition: background 0.15s ease, color 0.15s ease;
}

.nav-item-link:hover {
  background: rgba(255, 255, 255, 0.45);
  color: #212529;
}

.nav-item-link.active {
  background: #fff;
  color: #212529;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.sidebar-close {
  opacity: 0.7;
}

.sidebar-close:hover {
  opacity: 1;
}

.app-main {
  background: var(--page-bg);
}

.app-topbar {
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.04);
}

.search-wrap {
  position: relative;
  z-index: 1080;
}

.search-wrap__icon {
  position: absolute;
  left: 0.85rem;
  top: 50%;
  transform: translateY(-50%);
  color: #868e96;
  font-size: 1rem;
  pointer-events: none;
  z-index: 1;
}

.search-wrap__input {
  padding-left: 2.5rem;
  border-radius: 0.5rem;
  border: 1px solid #dee2e6;
  background: #f8f9fa;
}

.search-wrap__input:focus {
  background: #fff;
}

.search-dropdown {
  position: absolute;
  top: calc(100% + 0.35rem);
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 0.5rem;
  box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.1);
  max-height: min(18rem, 70vh);
  overflow-y: auto;
}

.search-dropdown__label {
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: #868e96;
  padding: 0.4rem 0.65rem 0.15rem;
}

.search-dropdown__row {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.05rem;
  width: 100%;
  text-align: left;
  border: 0;
  background: transparent;
  padding: 0.28rem 0.5rem 0.28rem 0.55rem;
  border-left: 3px solid transparent;
  cursor: pointer;
  line-height: 1.2;
}

.search-dropdown__row:hover {
  background: #f8f9fa;
}

.search-dropdown__row--course {
  border-left-color: #0d6efd;
}

.search-dropdown__row--user {
  border-left-color: #6f42c1;
}

.search-dropdown__title {
  font-size: 0.78rem;
  font-weight: 600;
  color: #212529;
}

.search-dropdown__hint {
  font-size: 0.68rem;
  color: #868e96;
}

.search-dropdown__status {
  font-size: 0.75rem;
}

.btn-icon {
  line-height: 1;
  border: none;
}

.notif-badge {
  font-size: 0.65rem;
  min-width: 1.1rem;
  padding: 0.2em 0.45em;
  top: 0.2rem;
  right: 0.15rem;
  transform: translate(40%, -35%);
}

.notif-wrap {
  z-index: 1085;
}

.notif-dropdown {
  position: absolute;
  top: calc(100% + 0.3rem);
  right: 0;
  width: min(22rem, 90vw);
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 0.5rem;
  box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.notif-item {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.45rem 0.75rem;
  border-bottom: 1px solid #f1f3f5;
  transition: background-color 0.15s ease;
}

.notif-item:last-child {
  border-bottom: none;
}

.notif-item:hover {
  background: #f8f9fa;
}

.notif-item__left {
  min-width: 0;
}

.notif-item__kind {
  display: inline-block;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #6c757d;
  margin-bottom: 0.1rem;
}

.notif-item__text {
  font-size: 0.78rem;
  line-height: 1.25;
  color: #212529;
}

.notif-item__text--read {
  color: #6c757d;
}

.notif-item__remove {
  opacity: 0.7;
}

.notif-item__remove:hover {
  opacity: 1;
}

.user-avatar {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
}

.user-name {
  max-width: 10rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  z-index: 1040;
}

@media (max-width: 767.98px) {
  .app-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    transform: translateX(-100%);
    transition: transform 0.2s ease;
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.08);
  }

  .app-sidebar.is-open {
    transform: translateX(0);
  }
}
</style>

import { createRouter, createWebHistory } from 'vue-router'
import MainLayout from '../layouts/MainLayout.vue'
import DashboardView from '../views/DashboardView.vue'
import CoursesView from '../views/CoursesView.vue'
import MyCoursesView from '../views/MyCoursesView.vue'
import UsersView from '../views/UsersView.vue'
import EnrollView from '../views/EnrollView.vue'
import SuccessView from '../views/SuccessView.vue'
import PlaceholderView from '../views/PlaceholderView.vue'
import AdminLoginView from '../views/AdminLoginView.vue'
import AdminEnrollmentsView from '../views/AdminEnrollmentsView.vue'
import ManageCoursesView from '../views/ManageCoursesView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/admin/login',
      name: 'admin-login',
      component: AdminLoginView,
    },
    {
      path: '/',
      component: MainLayout,
      children: [
        { path: '', name: 'dashboard', component: DashboardView },
        { path: 'courses', name: 'courses', component: CoursesView },
        { path: 'meus-cursos', name: 'my-courses', component: MyCoursesView },
        { path: 'gerenciar-cursos', name: 'manage-courses', component: ManageCoursesView },
        { path: 'usuarios', name: 'users', component: UsersView },
        { path: 'courses/:id/enroll', name: 'enroll', component: EnrollView, props: true },
        { path: 'enrollment/success', name: 'success', component: SuccessView },
        {
          path: 'configuracoes',
          name: 'settings',
          component: PlaceholderView,
          props: {
            title: 'Configurações',
            message:
              'Preferências da conta e notificações serão configuradas aqui em uma versão futura. Entre em contato com o suporte se precisar alterar dados cadastrais.',
          },
        },
        { path: 'admin/inscricoes', name: 'admin-enrollments', component: AdminEnrollmentsView },
      ],
    },
  ],
})

export default router

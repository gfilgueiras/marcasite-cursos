<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div>
    <h1 class="h4 fw-bold text-dark mb-2">Dashboard</h1>
    <p class="text-muted mb-4">
      Visão geral da sua conta na Marcasite. Acesse a área de cursos para conhecer as trilhas disponíveis.
    </p>
    <div class="row g-3">
      <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h2 class="h6 fw-semibold mb-2">Vitrine de Cursos</h2>
            <p class="small text-muted mb-3">Navegue pelo catálogo e inscreva-se nos treinamentos abertos.</p>
            <RouterLink to="/courses" class="btn btn-dark btn-sm">Abrir cursos</RouterLink>
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 bg-light">
          <div class="card-body">
            <h2 class="h6 fw-semibold mb-2">Meus cursos</h2>
            <p class="small text-muted mb-3">Em breve você acompanhará aqui o progresso das suas matrículas.</p>
            <RouterLink to="/meus-cursos" class="btn btn-outline-secondary btn-sm">Ver área</RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

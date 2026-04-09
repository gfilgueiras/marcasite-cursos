<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="bg-white rounded shadow-sm border p-4 col-lg-8">
    <h1 class="h3 mb-3">Pagamento enviado</h1>
    <p class="mb-3">
      Obrigado pela sua inscrição. Se o pagamento for aprovado no gateway, o status da inscrição será atualizado
      automaticamente para <strong>pago</strong> e você poderá acompanhar isso na área administrativa.
    </p>
    <p class="text-muted small mb-4">
      Em ambiente de testes, use os cartões indicados na documentação do Stripe. Se fechar a página antes de concluir,
      a inscrição pode permanecer como pendente até nova tentativa.
    </p>
    <RouterLink to="/courses" class="btn btn-dark me-2">Ver outros cursos</RouterLink>
    <RouterLink to="/" class="btn btn-outline-secondary">Ir ao dashboard</RouterLink>
  </div>
</template>

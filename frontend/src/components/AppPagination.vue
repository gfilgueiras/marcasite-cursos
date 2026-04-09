<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Number, required: true },
  totalPages: { type: Number, required: true },
})

const emit = defineEmits(['update:modelValue'])

const items = computed(() => {
  const t = props.totalPages
  const c = props.modelValue
  if (t <= 1) return []

  if (t <= 7) {
    return Array.from({ length: t }, (_, i) => i + 1)
  }

  const set = new Set([1, t, c, c - 1, c + 1])
  const nums = [...set].filter((n) => n >= 1 && n <= t).sort((a, b) => a - b)

  const out = []
  let prev = 0
  for (const n of nums) {
    if (prev && n - prev > 1) {
      out.push('gap')
    }
    out.push(n)
    prev = n
  }
  return out
})

function go(page) {
  if (page < 1 || page > props.totalPages || page === props.modelValue) return
  emit('update:modelValue', page)
}
</script>

<template>
  <nav v-if="totalPages > 1" class="d-flex justify-content-center mt-4 pt-2" aria-label="Paginação">
    <div class="d-flex align-items-center gap-1 flex-wrap justify-content-center">
      <template v-for="(item, idx) in items" :key="idx">
        <span v-if="item === 'gap'" class="px-1 text-muted small user-select-none">…</span>
        <button
          v-else
          type="button"
          class="page-btn btn btn-sm"
          :class="item === modelValue ? 'btn-dark' : 'btn-light border'"
          @click="go(item)"
        >
          {{ item }}
        </button>
      </template>
    </div>
  </nav>
</template>

<style scoped>
.page-btn {
  min-width: 2.25rem;
  border-radius: 0.4rem;
  font-size: 0.85rem;
}
</style>

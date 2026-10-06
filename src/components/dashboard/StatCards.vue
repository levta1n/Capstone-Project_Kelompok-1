<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'StatCards',
  props: {
    items: {
      type: Array,
      required: true,
    },
  },
})
</script>

<template>
  <section class="row g-3" aria-label="Ringkasan statistik">
    <article v-for="item in items" :key="item.label" class="col-12 col-sm-6 col-lg-4">
      <div class="card h-100 shadow-sm border-0 rounded-3 p-3 d-flex align-items-center gap-3">
        <span :class="`badge rounded-3 fs-4 ${item.type}`">{{ item.icon }}</span>
        <div class="d-flex flex-column"><span class="text-muted small fw-semibold">{{ item.label }}</span><strong class="display-6 fw-black text-dark">{{ item.value }}</strong><small class="text-muted">{{ item.description }}</small></div>
      </div>
    </article>
  </section>
</template>

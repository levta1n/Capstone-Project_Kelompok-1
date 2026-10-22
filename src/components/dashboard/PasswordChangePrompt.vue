<script>
import { defineComponent } from 'vue'
import { LockKeyhole } from '@lucide/vue'

export default defineComponent({
  name: 'PasswordChangePrompt',
  components: { LockKeyhole },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['change-now', 'later'],
})
</script>

<template>
  <div v-if="open" class="modal fade show d-block" role="presentation">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <section
        class="modal-content border-0 rounded-4 shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="password-prompt-title"
        aria-describedby="password-prompt-description"
      >
        <div class="modal-body p-4 p-md-5">
          <span class="badge bg-warning-subtle text-warning rounded-2 p-2 mb-3">
            <LockKeyhole :size="20" aria-hidden="true" />
          </span>
          <p class="text-primary small fw-bold text-uppercase mb-1">Keamanan akun</p>
          <h2 id="password-prompt-title" class="h4 fw-bold mb-2">Ganti password awal</h2>
          <p id="password-prompt-description" class="text-muted mb-4">
            Akun ini masih menggunakan password awal. Sebaiknya ganti sekarang agar akun lebih aman.
            Pengingat ini akan muncul lagi saat login berikutnya jika password belum diganti.
          </p>
          <div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2">
            <button class="btn btn-outline-secondary" type="button" @click="$emit('later')">Nanti</button>
            <button class="btn btn-primary" type="button" @click="$emit('change-now')">Ganti password sekarang</button>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

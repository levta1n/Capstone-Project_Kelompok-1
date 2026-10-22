<script>
import { defineComponent } from 'vue'
import { Download } from '@lucide/vue'

export default defineComponent({
  name: 'ReportExportModal',
  components: { Download },
  props: {
    open: { type: Boolean, default: false },
    from: { type: String, required: true },
    to: { type: String, required: true },
    isExporting: { type: Boolean, default: false },
    error: { type: String, default: '' },
  },
  emits: ['close', 'export', 'update:from', 'update:to'],
})
</script>

<template>
  <div v-if="open" class="modal fade show d-block" role="presentation">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <section
        class="modal-content border-0 rounded-4 shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="report-export-title"
      >
        <header class="modal-header border-0">
          <div>
            <p class="text-primary small fw-bold text-uppercase mb-1">Ekspor laporan</p>
            <h2 id="report-export-title" class="modal-title h5 fw-bold">Pilih periode laporan</h2>
          </div>
          <button class="btn-close" type="button" aria-label="Tutup" :disabled="isExporting" @click="$emit('close')"></button>
        </header>
        <form @submit.prevent="$emit('export')">
          <div class="modal-body">
            <p class="text-muted small">ZIP yang diunduh berisi ringkasan PDF dan workbook Excel dengan detail peminjaman serta statistik ruangan.</p>
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label" for="report-from">Dari tanggal</label>
                <input
                  id="report-from"
                  class="form-control"
                  type="date"
                  required
                  :value="from"
                  :disabled="isExporting"
                  @input="$emit('update:from', $event.target.value)"
                />
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="report-to">Sampai tanggal</label>
                <input
                  id="report-to"
                  class="form-control"
                  type="date"
                  required
                  :min="from"
                  :value="to"
                  :disabled="isExporting"
                  @input="$emit('update:to', $event.target.value)"
                />
              </div>
            </div>
            <p v-if="error" class="alert alert-danger mt-3 mb-0" role="alert">{{ error }}</p>
          </div>
          <footer class="modal-footer border-0">
            <button class="btn btn-outline-secondary" type="button" :disabled="isExporting" @click="$emit('close')">Batal</button>
            <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit" :disabled="isExporting">
              <Download :size="16" aria-hidden="true" />
              {{ isExporting ? 'Membuat ZIP...' : 'Unduh laporan ZIP' }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </div>
</template>

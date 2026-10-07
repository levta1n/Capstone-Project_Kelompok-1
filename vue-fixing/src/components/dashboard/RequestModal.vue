<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'RequestModal',
  props: {
    request: {
      type: Object,
      default: null,
    },
    createOpen: {
      type: Boolean,
      default: false,
    },
    rooms: {
      type: Array,
      default: () => [],
    },
    isSubmitting: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: '',
    },
    canReview: {
      type: Boolean,
      default: false,
    },
    isReviewing: {
      type: Boolean,
      default: false,
    },
    reviewError: {
      type: String,
      default: '',
    },
    initialRoomId: {
      type: String,
      default: '',
    },
    initialStartsAt: {
      type: String,
      default: '',
    },
    initialEndsAt: {
      type: String,
      default: '',
    },
    scheduleReadOnly: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['close', 'create', 'approve', 'reject'],
  data() {
    return {
      form: {
        roomId: '',
        requestType: 'room_booking',
        purpose: '',
        startsAt: '',
        endsAt: '',
        participantCount: 1,
      },
      isRejecting: false,
      rejectionCategory: 'Jadwal bertabrakan',
      rejectionNote: '',
    }
  },
  watch: {
    createOpen(isOpen) {
      if (isOpen) this.resetForm()
    },
    request() {
      this.isRejecting = false
      this.rejectionCategory = 'Jadwal bertabrakan'
      this.rejectionNote = ''
    },
  },
  computed: {
    canDecide() {
      return this.canReview && ['Menunggu', 'Proses'].includes(this.request?.status)
    },
  },
  methods: {
    resetForm() {
      this.form = {
        roomId: this.initialRoomId || this.rooms[0]?.id || '',
        requestType: 'room_booking',
        purpose: '',
        startsAt: this.initialStartsAt,
        endsAt: this.initialEndsAt,
        participantCount: 1,
      }
    },
    submitRequest() {
      this.$emit('create', { ...this.form })
    },
    submitRejection() {
      const note = this.rejectionNote.trim()
      this.$emit('reject', {
        request: this.request,
        reason: note ? `${this.rejectionCategory}: ${note}` : this.rejectionCategory,
      })
    },
  },
})
</script>

<template>
  <div v-if="request || createOpen" class="modal fade show d-block" @click="$emit('close')">
    <div class="modal-dialog modal-dialog-centered" @click.stop>
      <div class="modal-content shadow-lg border-0 rounded-4">
        <div v-if="createOpen" class="modal-header border-0">
          <div><span class="text-primary small fw-bold text-uppercase">Permintaan ruangan</span><h2 class="modal-title h5 fw-bold">Buat permintaan</h2></div>
          <button type="button" class="btn-close" aria-label="Tutup form" @click="$emit('close')"></button>
        </div>
        <div v-else class="modal-header border-0">
          <div><span class="text-primary small fw-bold text-uppercase">Detail permintaan</span><h2 class="modal-title h5 fw-bold">{{ request.id }}</h2></div>
          <button type="button" class="btn-close" aria-label="Tutup detail" @click="$emit('close')"></button>
        </div>
        <form v-if="createOpen" id="create-request-form" class="modal-body" @submit.prevent="submitRequest">
          <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
          <div class="mb-3">
            <label class="form-label" for="request-room">Ruangan</label>
            <select id="request-room" v-model="form.roomId" class="form-select" required>
              <option value="" disabled>Pilih ruangan</option>
              <option v-for="room in rooms" :key="room.id" :value="room.id">{{ room.name }} · {{ room.floor }}</option>
            </select>
            <small v-if="rooms.length === 0" class="text-danger">Belum ada ruangan aktif yang tersedia.</small>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-7">
              <label class="form-label" for="request-type">Jenis permintaan</label>
              <select id="request-type" v-model="form.requestType" class="form-select" required>
                <option value="room_booking">Peminjaman</option>
                <option value="registration">Pendaftaran</option>
              </select>
            </div>
            <div class="col-sm-5">
              <label class="form-label" for="request-participants">Jumlah peserta</label>
              <input id="request-participants" v-model.number="form.participantCount" class="form-control" type="number" min="1" required />
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label" for="request-starts">Waktu mulai</label>
              <input id="request-starts" v-model="form.startsAt" class="form-control" type="datetime-local" :readonly="scheduleReadOnly" required />
            </div>
            <div class="col-sm-6">
              <label class="form-label" for="request-ends">Waktu selesai</label>
              <input id="request-ends" v-model="form.endsAt" class="form-control" type="datetime-local" :readonly="scheduleReadOnly" required />
            </div>
          </div>
          <div>
            <label class="form-label" for="request-purpose">Tujuan penggunaan</label>
            <textarea id="request-purpose" v-model="form.purpose" class="form-control" rows="3" maxlength="1000" required></textarea>
          </div>
        </form>
        <div v-else class="modal-body">
          <div v-if="reviewError && canDecide && !isRejecting" class="alert alert-danger" role="alert">{{ reviewError }}</div>
          <div class="row g-3">
            <div class="col-6"><span class="text-muted small d-block">Pengguna</span><strong class="fs-6">{{ request.requester }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Unit</span><strong class="fs-6">{{ request.unit }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Ruangan</span><strong class="fs-6">{{ request.room }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Pemilik</span><strong class="fs-6">{{ request.owner }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Jenis</span><strong class="fs-6">{{ request.type }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Status</span><strong class="fs-6">{{ request.status }}</strong></div>
            <div v-if="request.rejectionReason" class="col-12"><span class="text-muted small d-block">Alasan penolakan</span><strong class="fs-6">{{ request.rejectionReason }}</strong></div>
          </div>
          <form v-if="isRejecting && canDecide" id="reject-request-form" class="mt-4" @submit.prevent="submitRejection">
            <div v-if="reviewError" class="alert alert-danger" role="alert">{{ reviewError }}</div>
            <div class="mb-3">
              <label class="form-label" for="rejection-category">Alasan penolakan</label>
              <select id="rejection-category" v-model="rejectionCategory" class="form-select" required>
                <option>Jadwal bertabrakan</option>
                <option>Kapasitas ruangan tidak mencukupi</option>
                <option>Ruangan tidak tersedia</option>
                <option>Data pengajuan belum lengkap</option>
                <option>Tidak sesuai ketentuan</option>
                <option>Lainnya</option>
              </select>
            </div>
            <div>
              <label class="form-label" for="rejection-note">Catatan tambahan <span class="text-muted">(opsional)</span></label>
              <textarea id="rejection-note" v-model="rejectionNote" class="form-control" rows="2" maxlength="500" placeholder="Tulis keterangan untuk pemohon"></textarea>
            </div>
          </form>
        </div>
        <div v-if="createOpen" class="modal-footer border-0">
          <button class="btn btn-outline-secondary rounded-2" type="button" @click="$emit('close')">Batal</button>
          <button class="btn btn-primary rounded-2" type="submit" form="create-request-form" :disabled="isSubmitting || rooms.length === 0">{{ isSubmitting ? 'Menyimpan...' : 'Kirim permintaan' }}</button>
        </div>
        <div v-else-if="canDecide" class="modal-footer border-0">
          <template v-if="isRejecting">
            <button class="btn btn-outline-secondary rounded-2" type="button" :disabled="isReviewing" @click="isRejecting = false">Kembali</button>
            <button class="btn btn-danger rounded-2" type="submit" form="reject-request-form" :disabled="isReviewing">{{ isReviewing ? 'Menyimpan...' : 'Konfirmasi penolakan' }}</button>
          </template>
          <template v-else>
            <button class="btn btn-outline-secondary rounded-2" type="button" @click="$emit('close')">Tutup detail</button>
            <button class="btn btn-outline-danger rounded-2" type="button" :disabled="isReviewing" @click="isRejecting = true">Tolak</button>
            <button class="btn btn-success rounded-2" type="button" :disabled="isReviewing" @click="$emit('approve', request)">{{ isReviewing ? 'Menyimpan...' : 'Setujui' }}</button>
          </template>
        </div>
        <div v-else class="modal-footer border-0"><button class="btn btn-primary rounded-2" @click="$emit('close')">Tutup detail</button></div>
      </div>
    </div>
  </div>
</template>

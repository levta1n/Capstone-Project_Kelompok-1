<script>
import { defineComponent } from 'vue'
import { X } from '@lucide/vue'
import { APPLICATION_TIME_ZONE, formatAppDateTime, parseAppDateTime } from '../../utils/dateTime'

export default defineComponent({
  name: 'RequestModal',
  components: { X },
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
    canCancel: {
      type: Boolean,
      default: false,
    },
    isCancelling: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['close', 'create', 'cancel', 'approve', 'reject'],
  data() {
    return {
      form: {
        roomId: '',
        purpose: '',
        startsAt: '',
        endsAt: '',
        participantCount: 1,
      },
      minimumStartsAt: '',
      durationHours: 1,
      isRejecting: false,
      rejectionReason: '',
    }
  },
  watch: {
    createOpen(isOpen) {
      if (isOpen) this.resetForm()
    },
    request() {
      this.isRejecting = false
      this.rejectionReason = ''
    },
  },
  computed: {
    canDecide() {
      return this.canReview && this.request?.status === 'Menunggu'
    },
  },
  methods: {
    resetForm() {
      this.minimumStartsAt = formatAppDateTime(new Date())
      this.form = {
        roomId: this.initialRoomId || this.rooms[0]?.id || '',
        purpose: '',
        startsAt: this.initialStartsAt,
        endsAt: this.initialEndsAt,
        participantCount: 1,
      }
      this.durationHours = 1
    },
    toLocalDateTimeInput(date) {
      return formatAppDateTime(date)
    },
    setDuration(hours) {
      const startsAt = parseAppDateTime(this.form.startsAt)
      if (!Number.isFinite(startsAt.getTime())) return

      this.durationHours = hours
      this.form.endsAt = this.toLocalDateTimeInput(new Date(startsAt.getTime() + hours * 60 * 60 * 1000))
    },
    keepEndAfterStart() {
      const startsAt = parseAppDateTime(this.form.startsAt)
      const endsAt = parseAppDateTime(this.form.endsAt)
      if (Number.isFinite(startsAt.getTime()) && (!Number.isFinite(endsAt.getTime()) || endsAt <= startsAt)) {
        this.setDuration(this.durationHours)
      }
    },
    submitRequest() {
      this.$emit('create', { ...this.form })
    },
    submitRejection() {
      this.$emit('reject', {
        request: this.request,
        reason: this.rejectionReason.trim(),
      })
    },
    formatDateTime(value) {
      if (!value) return '-'
      return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'full',
        timeStyle: 'short',
        timeZone: APPLICATION_TIME_ZONE,
      }).format(new Date(value))
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
          <div><span class="text-primary small fw-bold text-uppercase">Detail peminjaman</span><h2 class="modal-title h5 fw-bold">{{ request.id }}</h2></div>
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
          <div class="mb-3">
            <label class="form-label" for="request-participants">Jumlah peserta</label>
            <input id="request-participants" v-model.number="form.participantCount" class="form-control" type="number" min="1" required />
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label" for="request-starts">Waktu mulai (WIB)</label>
              <input id="request-starts" v-model="form.startsAt" class="form-control" type="datetime-local" :min="minimumStartsAt" :readonly="scheduleReadOnly" required @change="keepEndAfterStart" />
            </div>
            <div class="col-sm-6">
              <label class="form-label" for="request-ends">Waktu selesai (WIB)</label>
              <input id="request-ends" v-model="form.endsAt" class="form-control" type="datetime-local" :readonly="scheduleReadOnly" required />
            </div>
          </div>
          <fieldset class="mb-3">
            <legend class="form-label">Pengajuan cepat</legend>
            <div class="btn-group btn-group-sm" role="group" aria-label="Pilih durasi peminjaman">
              <button v-for="hours in [1, 2, 3, 4]" :key="hours" class="btn" :class="durationHours === hours ? 'btn-primary' : 'btn-outline-primary'" type="button" :disabled="!form.startsAt" :aria-pressed="durationHours === hours" @click="setDuration(hours)">{{ hours }} jam</button>
            </div>
          </fieldset>
          <div>
            <label class="form-label" for="request-purpose">Tujuan penggunaan</label>
            <textarea id="request-purpose" v-model="form.purpose" class="form-control" rows="3" maxlength="1000" required></textarea>
          </div>
        </form>
        <div v-else class="modal-body">
          <p v-if="reviewError" class="alert alert-danger" role="alert">{{ reviewError }}</p>
          <div class="row g-3">
            <div class="col-6"><span class="text-muted small d-block">Pengguna</span><strong class="fs-6">{{ request.requester }}</strong></div>
            <div v-if="request.unit" class="col-6"><span class="text-muted small d-block">Unit</span><strong class="fs-6">{{ request.unit }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Ruangan</span><strong class="fs-6">{{ request.room }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Jenis</span><strong class="fs-6">{{ request.type }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Status</span><strong class="fs-6">{{ request.status }}</strong></div>
            <div class="col-12"><span class="text-muted small d-block">Mulai</span><strong class="fs-6">{{ formatDateTime(request.startsAt) }}</strong></div>
            <div class="col-12"><span class="text-muted small d-block">Selesai</span><strong class="fs-6">{{ formatDateTime(request.endsAt) }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Jumlah peserta</span><strong class="fs-6">{{ request.participantCount }}</strong></div>
            <div v-if="request.purpose" class="col-12"><span class="text-muted small d-block">Tujuan penggunaan</span><strong class="fs-6 text-break">{{ request.purpose }}</strong></div>
            <div v-if="request.cancellationReason" class="col-12"><span class="text-muted small d-block">Alasan pembatalan</span><strong class="fs-6">{{ request.cancellationReason }}</strong></div>
            <div v-if="request.rejectionReason" class="col-12"><span class="text-muted small d-block">Alasan penolakan</span><strong class="fs-6">{{ request.rejectionReason }}</strong></div>
          </div>
          <form v-if="isRejecting && canDecide" id="reject-request-form" class="mt-4" @submit.prevent="submitRejection">
            <label class="form-label" for="request-rejection-reason">Alasan penolakan</label>
            <textarea
              id="request-rejection-reason"
              v-model.trim="rejectionReason"
              class="form-control"
              rows="3"
              maxlength="500"
              required
            ></textarea>
          </form>
        </div>
        <div v-if="createOpen" class="modal-footer border-0">
          <button class="btn btn-outline-secondary rounded-2" type="button" @click="$emit('close')">Batal</button>
          <button class="btn btn-primary rounded-2" type="submit" form="create-request-form" :disabled="isSubmitting || rooms.length === 0">{{ isSubmitting ? 'Menyimpan...' : 'Kirim permintaan' }}</button>
        </div>
        <div v-else-if="canDecide" class="modal-footer border-0">
          <template v-if="isRejecting">
            <button class="btn btn-outline-secondary" type="button" :disabled="isReviewing" @click="isRejecting = false">Kembali</button>
            <button class="btn btn-danger" type="submit" form="reject-request-form" :disabled="isReviewing">
              {{ isReviewing ? 'Menyimpan...' : 'Konfirmasi penolakan' }}
            </button>
          </template>
          <template v-else>
            <button class="btn btn-outline-secondary" type="button" @click="$emit('close')">Tutup detail</button>
            <button class="btn btn-outline-danger" type="button" :disabled="isReviewing" @click="isRejecting = true">Tolak</button>
            <button class="btn btn-success" type="button" :disabled="isReviewing" @click="$emit('approve', request)">
              {{ isReviewing ? 'Menyimpan...' : 'Terima permintaan' }}
            </button>
          </template>
        </div>
        <div v-else-if="canCancel" class="modal-footer border-0">
          <button class="btn btn-outline-secondary rounded-2" type="button" @click="$emit('close')">Tutup detail</button>
          <button class="btn btn-outline-danger rounded-2 d-inline-flex align-items-center gap-2" type="button" :disabled="isCancelling" @click="$emit('cancel', request)"><X :size="16" aria-hidden="true" />{{ isCancelling ? 'Membatalkan...' : 'Batalkan pengajuan' }}</button>
        </div>
        <div v-else class="modal-footer border-0"><button class="btn btn-primary rounded-2" @click="$emit('close')">Tutup detail</button></div>
      </div>
    </div>
  </div>
</template>

<script>
import { defineComponent } from 'vue'
import { Pencil, Plus, Power, X } from '@lucide/vue'

export default defineComponent({
  name: 'RoomManagerModal',
  components: {
    Pencil,
    Plus,
    Power,
    X,
  },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    rooms: {
      type: Array,
      default: () => [],
    },
    isSaving: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: '',
    },
  },
  emits: ['close', 'save', 'toggle-active'],
  data() {
    return {
      form: this.emptyForm(),
    }
  },
  watch: {
    open(isOpen) {
      if (isOpen) this.resetForm()
    },
  },
  methods: {
    emptyForm() {
      return { id: null, name: '', building: 'Gedung Utama', floor: '', capacity: 1 }
    },
    resetForm() {
      this.form = this.emptyForm()
    },
    editRoom(room) {
      this.form = {
        id: room.id,
        name: room.name,
        building: room.building,
        floor: room.floor,
        capacity: room.capacity,
      }
    },
    submit() {
      this.$emit('save', { ...this.form })
    },
  },
})
</script>

<template>
  <div v-if="open" class="modal fade show d-block" role="dialog" aria-modal="true" aria-labelledby="room-manager-title" @click="$emit('close')">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable" @click.stop>
      <div class="modal-content shadow-lg border-0 rounded-3">
        <div class="modal-header">
          <div>
            <p class="text-primary small fw-bold text-uppercase mb-1">Inventaris</p>
            <h2 id="room-manager-title" class="modal-title h5 fw-bold mb-0">Kelola ruangan</h2>
          </div>
          <button class="btn btn-light border rounded-2" type="button" aria-label="Tutup" @click="$emit('close')"><X :size="18" /></button>
        </div>

        <form class="modal-body" @submit.prevent="submit">
          <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
          <div class="border rounded-3 p-3 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <h3 class="h6 fw-bold mb-0">{{ form.id ? 'Ubah ruangan' : 'Tambah ruangan' }}</h3>
              <button v-if="form.id" class="btn btn-sm btn-link" type="button" @click="resetForm">Batal edit</button>
            </div>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label" for="room-name">Nama ruangan</label><input id="room-name" v-model.trim="form.name" class="form-control" maxlength="80" required /></div>
              <div class="col-md-6"><label class="form-label" for="room-building">Gedung</label><input id="room-building" v-model.trim="form.building" class="form-control" maxlength="100" required /></div>
              <div class="col-md-6"><label class="form-label" for="room-floor">Lantai</label><input id="room-floor" v-model.trim="form.floor" class="form-control" maxlength="40" placeholder="Lantai 1" required /></div>
              <div class="col-md-6"><label class="form-label" for="room-capacity">Kapasitas</label><input id="room-capacity" v-model.number="form.capacity" class="form-control" type="number" min="1" required /></div>
            </div>
            <button class="btn btn-primary rounded-2 d-inline-flex align-items-center gap-2 mt-3" type="submit" :disabled="isSaving"><Pencil v-if="form.id" :size="16" /><Plus v-else :size="16" />{{ isSaving ? 'Menyimpan...' : form.id ? 'Simpan perubahan' : 'Tambah ruangan' }}</button>
          </div>

          <div class="d-flex align-items-center justify-content-between mb-2">
            <h3 class="h6 fw-bold mb-0">Daftar ruangan</h3>
            <small class="text-muted">{{ rooms.length }} ruangan</small>
          </div>
          <div v-if="rooms.length" class="list-group">
            <div v-for="room in rooms" :key="room.id" class="list-group-item d-flex flex-wrap align-items-center gap-3 py-3">
              <div class="room-manager-info">
                <strong class="d-block">{{ room.name }}</strong>
                <small class="text-muted">{{ room.building }} · {{ room.floor }} · {{ room.capacity }} tempat</small>
              </div>
              <span :class="room.isActive ? 'badge bg-success-subtle text-success' : 'badge bg-secondary-subtle text-secondary'">{{ room.isActive ? 'Aktif' : 'Nonaktif' }}</span>
              <button class="btn btn-sm btn-outline-secondary" type="button" :disabled="isSaving" :aria-label="`Edit ${room.name}`" @click="editRoom(room)"><Pencil :size="16" /></button>
              <button class="btn btn-sm" :class="room.isActive ? 'btn-outline-danger' : 'btn-outline-success'" type="button" :disabled="isSaving" @click="$emit('toggle-active', { roomId: room.id, isActive: !room.isActive })"><Power :size="16" />{{ room.isActive ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </div>
          </div>
          <p v-else class="text-muted text-center py-4 mb-0">Belum ada data ruangan.</p>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.room-manager-info {
  flex: 1 1 12rem;
}
</style>
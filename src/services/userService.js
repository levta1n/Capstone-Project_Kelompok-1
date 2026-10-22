import { supabase } from '../utils/supabase'

async function getFunctionErrorMessage(error, fallback) {
  const response = error.context
  if (response && typeof response.clone === 'function') {
    try {
      const body = await response.clone().json()
      if (typeof body?.error === 'string') return body.error
    } catch (parseError) {
      if (!(parseError instanceof SyntaxError)) throw parseError
    }
  }
  return error.message || fallback
}

export async function listProfiles() {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const { data, error } = await supabase
    .from('profiles')
    .select('id, full_name, email, nim, unit_name, role, is_active, deleted_at, created_at')
    .order('created_at', { ascending: false })

  if (error) throw error
  return data
}

export async function createManagedUser(form) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const { data, error } = await supabase.functions.invoke('admin-create-user', {
    body: {
      fullName: form.fullName.trim(),
      nim: form.nim.trim(),
      dateOfBirth: form.dateOfBirth,
      unitName: form.unitName.trim(),
    },
  })

  if (error) throw new Error(await getFunctionErrorMessage(error, 'Akun gagal dibuat.'))
  if (!data?.user?.id) throw new Error(data?.error || 'Akun tidak berhasil dibuat.')
  return data.user
}

export async function manageUser(userId, action) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')
  if (!['suspend', 'reactivate', 'delete'].includes(action)) {
    throw new Error('Aksi pengguna tidak valid.')
  }

  const { data, error } = await supabase.functions.invoke('admin-manage-user', {
    body: { userId, action },
  })

  if (error) throw new Error(await getFunctionErrorMessage(error, 'Status akun gagal diperbarui.'))
  if (!data?.success) throw new Error(data?.error || 'Status akun gagal diperbarui.')
}

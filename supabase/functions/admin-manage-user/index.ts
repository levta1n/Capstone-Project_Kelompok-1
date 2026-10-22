import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
}

function jsonResponse(body: Record<string, unknown>, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...corsHeaders, 'Content-Type': 'application/json' },
  })
}

Deno.serve(async (request) => {
  if (request.method === 'OPTIONS') return new Response('ok', { headers: corsHeaders })
  if (request.method !== 'POST') return jsonResponse({ error: 'Method not allowed.' }, 405)

  const supabaseUrl = Deno.env.get('SUPABASE_URL')
  const anonKey = Deno.env.get('SUPABASE_ANON_KEY')
  const serviceRoleKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')
  if (!supabaseUrl || !anonKey || !serviceRoleKey) {
    return jsonResponse({ error: 'Konfigurasi Supabase Function belum lengkap.' }, 500)
  }

  const authorization = request.headers.get('Authorization')
  if (!authorization?.startsWith('Bearer ')) {
    return jsonResponse({ error: 'Sesi admin diperlukan.' }, 401)
  }

  const token = authorization.slice('Bearer '.length)
  const authClient = createClient(supabaseUrl, anonKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  })
  const { data: authData, error: authError } = await authClient.auth.getUser(token)
  if (authError || !authData.user) return jsonResponse({ error: 'Sesi admin tidak valid.' }, 401)

  const adminClient = createClient(supabaseUrl, serviceRoleKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  })
  const { data: adminProfile, error: adminProfileError } = await adminClient
    .from('profiles')
    .select('role, is_active, deleted_at')
    .eq('id', authData.user.id)
    .maybeSingle()

  if (adminProfileError) {
    return jsonResponse({ error: `Profil admin gagal diverifikasi: ${adminProfileError.message}` }, 500)
  }
  if (adminProfile?.role !== 'admin' || !adminProfile.is_active || adminProfile.deleted_at) {
    return jsonResponse({ error: 'Hanya admin aktif yang dapat mengelola akun.' }, 403)
  }

  let body: Record<string, unknown>
  try {
    body = await request.json()
  } catch {
    return jsonResponse({ error: 'Format data permintaan tidak valid.' }, 400)
  }

  const userId = typeof body.userId === 'string' ? body.userId : ''
  const action = body.action
  if (!userId || !['suspend', 'reactivate', 'delete'].includes(String(action))) {
    return jsonResponse({ error: 'ID atau aksi pengguna tidak valid.' }, 400)
  }
  if (userId === authData.user.id) {
    return jsonResponse({ error: 'Admin tidak dapat menonaktifkan atau menghapus akunnya sendiri.' }, 400)
  }

  const { data: target, error: targetError } = await adminClient
    .from('profiles')
    .select('id, role, is_active, deleted_at')
    .eq('id', userId)
    .maybeSingle()

  if (targetError) return jsonResponse({ error: `Pengguna gagal dimuat: ${targetError.message}` }, 500)
  if (!target) return jsonResponse({ error: 'Pengguna tidak ditemukan.' }, 404)
  if (target.role !== 'requester') {
    return jsonResponse({ error: 'Aksi ini hanya tersedia untuk akun requester.' }, 400)
  }

  if (action === 'reactivate' && target.deleted_at) {
    return jsonResponse({ error: 'Akun yang sudah diarsipkan tidak dapat diaktifkan kembali.' }, 400)
  }
  if (action === 'suspend' && target.deleted_at) {
    return jsonResponse({ error: 'Akun yang sudah diarsipkan tidak dapat diubah.' }, 400)
  }
  if (action === 'suspend' && !target.is_active) {
    return jsonResponse({ error: 'Akun sudah dalam keadaan suspend.' }, 400)
  }
  if (action === 'reactivate' && target.is_active) {
    return jsonResponse({ error: 'Akun sudah aktif.' }, 400)
  }
  if (action === 'delete' && target.deleted_at) {
    return jsonResponse({ error: 'Akun sudah diarsipkan.' }, 400)
  }

  const shouldBan = action !== 'reactivate'
  const { error: authUpdateError } = await adminClient.auth.admin.updateUserById(userId, {
    ban_duration: shouldBan ? '876000h' : 'none',
  })
  if (authUpdateError) {
    return jsonResponse({ error: `Akses login gagal diperbarui: ${authUpdateError.message}` }, 500)
  }

  const profileUpdate = action === 'delete'
    ? { is_active: false, deleted_at: new Date().toISOString() }
    : { is_active: action === 'reactivate' }
  const { error: profileUpdateError } = await adminClient
    .from('profiles')
    .update(profileUpdate)
    .eq('id', userId)

  if (profileUpdateError) {
    const { error: rollbackError } = await adminClient.auth.admin.updateUserById(userId, {
      ban_duration: shouldBan ? 'none' : '876000h',
    })
    const rollbackMessage = rollbackError
      ? ` Pemulihan akses login juga gagal: ${rollbackError.message}`
      : ''
    return jsonResponse({
      error: `Profil gagal diperbarui: ${profileUpdateError.message}.${rollbackMessage}`,
    }, 500)
  }

  return jsonResponse({ success: true })
})

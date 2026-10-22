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
  const { data: profile, error: profileError } = await adminClient
    .from('profiles')
    .select('role, is_active')
    .eq('id', authData.user.id)
    .maybeSingle()

  if (profileError) return jsonResponse({ error: `Profil admin gagal diverifikasi: ${profileError.message}` }, 500)
  if (profile?.role !== 'admin' || !profile.is_active) {
    return jsonResponse({ error: 'Hanya admin aktif yang dapat membuat akun.' }, 403)
  }

  let body: Record<string, unknown>
  try {
    body = await request.json()
  } catch {
    return jsonResponse({ error: 'Format data permintaan tidak valid.' }, 400)
  }

  const fullName = typeof body.fullName === 'string' ? body.fullName.trim() : ''
  const nim = typeof body.nim === 'string' ? body.nim.trim() : ''
  const dateOfBirth = typeof body.dateOfBirth === 'string' ? body.dateOfBirth : ''
  const unitName = typeof body.unitName === 'string' ? body.unitName.trim() : ''
  if (!fullName || fullName.length > 120) {
    return jsonResponse({ error: 'Nama wajib diisi dan maksimal 120 karakter.' }, 400)
  }
  if (!/^\d{1,32}$/.test(nim)) {
    return jsonResponse({ error: 'NIM wajib berupa 1 sampai 32 digit angka.' }, 400)
  }
  if (unitName.length > 120) {
    return jsonResponse({ error: 'Nama unit maksimal 120 karakter.' }, 400)
  }
  if (!/^\d{4}-\d{2}-\d{2}$/.test(dateOfBirth)) {
    return jsonResponse({ error: 'Tanggal lahir tidak valid.' }, 400)
  }
  const parsedBirthDate = new Date(`${dateOfBirth}T00:00:00.000Z`)
  if (
    Number.isNaN(parsedBirthDate.getTime())
    || parsedBirthDate.toISOString().slice(0, 10) !== dateOfBirth
    || parsedBirthDate > new Date()
  ) {
    return jsonResponse({ error: 'Tanggal lahir tidak valid.' }, 400)
  }

  const email = `${nim}@ecampus.ut.ac.id`
  const defaultPassword = `${nim}${dateOfBirth.slice(8, 10)}${dateOfBirth.slice(5, 7)}${dateOfBirth.slice(0, 4)}`
  const { data: created, error: createError } = await adminClient.auth.admin.createUser({
    email,
    password: defaultPassword,
    email_confirm: true,
    user_metadata: { full_name: fullName, nim, unit_name: unitName || null },
  })

  if (createError) {
    const status = createError.message.toLowerCase().includes('already')
      ? 409
      : 400
    return jsonResponse({ error: createError.message }, status)
  }

  return jsonResponse({
    user: {
      id: created.user.id,
      email: created.user.email,
      initialPassword: defaultPassword,
    },
  }, 201)
})

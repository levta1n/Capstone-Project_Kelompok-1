import { supabase } from '../utils/supabase'

export async function getProfile(userId) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const { data, error } = await supabase
    .from('profiles')
    .select('id, full_name, email, unit_name, role')
    .eq('id', userId)
    .maybeSingle()

  if (error) throw error
  return data
}

export async function signOut() {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const { error } = await supabase.auth.signOut()
  if (error) throw error
}
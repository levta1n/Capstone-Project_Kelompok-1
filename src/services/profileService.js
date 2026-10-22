import { supabase } from '../utils/supabase'

const passwordPromptSessionKey = 'simperu-password-prompt-user'

export function shouldPromptPasswordChange(userId) {
  if (!userId || sessionStorage.getItem(passwordPromptSessionKey) === userId) return false
  sessionStorage.setItem(passwordPromptSessionKey, userId)
  return true
}

export async function getProfile(userId) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const { data, error } = await supabase
    .from('profiles')
    .select('id, full_name, email, nim, unit_name, role, is_active, deleted_at, must_change_password')
    .eq('id', userId)
    .maybeSingle()

  if (error) throw error
  return data
}

export async function signOut() {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const { error } = await supabase.auth.signOut()
  if (error) throw error
  sessionStorage.removeItem(passwordPromptSessionKey)
}
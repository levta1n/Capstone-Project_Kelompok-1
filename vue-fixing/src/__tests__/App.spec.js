import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

import App from '../App.vue'
import { routes } from '../router'

describe('App', () => {
  it('menampilkan landing SIMPERU/SIPERU dan mengirim login ke dashboard', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes,
    })

    await router.push('/')
    const wrapper = mount(App, {
      global: {
        plugins: [router],
      },
    })

    expect(wrapper.text()).toContain('SIMPERU')
    expect(wrapper.text()).toContain('SIPERU')

    await wrapper.find('#email').setValue('admin@campus.edu')
    await wrapper.find('#password').setValue('password123')
    await wrapper.find('#login-form').trigger('submit')
    await new Promise((resolve) => setTimeout(resolve, 450))
    await router.isReady()
    await wrapper.vm.$nextTick()

    expect(router.currentRoute.value.name).toBe('dashboard')
    expect(wrapper.text()).toContain('Dashboard Manajemen')
  })
})

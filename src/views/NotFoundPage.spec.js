import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import App from '../App.vue'
import { routes } from '../router'

describe('NotFoundPage', () => {
  it('menampilkan halaman 404 untuk URL yang tidak terdaftar', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes,
    })

    await router.push('/alamat-yang-tidak-ada')
    await router.isReady()
    const wrapper = mount(App, {
      global: {
        plugins: [router],
      },
    })

    expect(router.currentRoute.value.name).toBe('not-found')
    expect(wrapper.text()).toContain('404')
    expect(wrapper.text()).toContain('Halaman tidak ditemukan')
    expect(wrapper.get('a').attributes('href')).toBe('/')
  })
})

import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import UserManagementPanel from './UserManagementPanel.vue'

describe('UserManagementPanel account email', () => {
  it('shows the required email domain before and after entering a NIM', async () => {
    const wrapper = mount(UserManagementPanel)

    expect(wrapper.text()).toContain('NIM@campus.univ.ac.id')

    await wrapper.find('#new-user-nim').setValue('123456')
    expect(wrapper.text()).toContain('123456@campus.univ.ac.id')
    expect(wrapper.text()).not.toContain('@undefined')
  })
})

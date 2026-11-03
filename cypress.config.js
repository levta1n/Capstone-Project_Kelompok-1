import { defineConfig } from 'cypress'
import process from 'node:process'
import { loadEnv } from 'vite'
import { writeCypressReports } from './cypress/write-reports.js'

const dotenv = loadEnv('test', process.cwd(), '')
const getEnv = (key) => process.env[key] ?? dotenv[key] ?? ''

export default defineConfig({
  env: {
    adminEmail: getEnv('CYPRESS_ADMIN_EMAIL'),
    adminPassword: getEnv('CYPRESS_ADMIN_PASSWORD'),
    requesterEmail: getEnv('CYPRESS_REQUESTER_EMAIL'),
    requesterPassword: getEnv('CYPRESS_REQUESTER_PASSWORD'),
  },
  e2e: {
    specPattern: 'cypress/e2e/**/*.{cy,spec}.{js,jsx,ts,tsx}',
    baseUrl: 'http://localhost:5173',
    supportFile: 'cypress/support/e2e.js',
    screenshotsFolder: 'cypress/artifacts/media/screenshots',
    videosFolder: 'cypress/artifacts/media/videos',
    video: true,
    setupNodeEvents(on) {
      on('after:run', writeCypressReports)
    },
  },
})

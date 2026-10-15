import { defineConfig, devices } from '@playwright/test';
export default defineConfig({
 testDir: './tests', fullyParallel: false, workers: 1, reporter: 'list',
 use: { baseURL: process.env.BASE_URL ?? 'http://127.0.0.1', trace: 'retain-on-failure', launchOptions: { executablePath: process.env.CHROME_PATH } },
 projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});

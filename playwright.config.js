// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * JGGL CRM — Playwright E2E Configuration
 * 
 * Optimized for SPA testing (Livewire/Inertia)
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
    testDir: './e2e',
    
    /* Run tests sequentially for stability */
    fullyParallel: false,
    
    /* Fail the build on CI if you accidentally left test.only in the source code */
    forbidOnly: !!process.env.CI,
    
    /* Retry failed tests */
    retries: process.env.CI ? 2 : 1,
    
    /* Single worker for stability */
    workers: 1,
    
    /* Reporter to use */
    reporter: [
        ['html', { outputFolder: 'playwright-report' }],
        ['list'],
    ],
    
    /* Shared settings for all the projects below */
    use: {
        /* Base URL */
        baseURL: 'http://localhost:8080',
        
        /* Collect trace when retrying */
        trace: 'on-first-retry',
        
        /* Screenshot on failure */
        screenshot: 'only-on-failure',
        
        /* Video on first retry */
        video: 'on-first-retry',
        
        /* Increase action timeout for SPA */
        actionTimeout: 10000,
    },
    
    /* Configure projects for major browsers */
    projects: [
        {
            name: 'chromium',
            use: { 
                ...devices['Desktop Chrome'],
                headless: true,
            },
        },
    ],
    
    /* Output folder for test artifacts */
    outputDir: 'test-results/',
    
    /* Timeout for each test - increased for SPA navigation */
    timeout: 60000,
    
    /* Expect timeout */
    expect: {
        timeout: 15000,
    },
});

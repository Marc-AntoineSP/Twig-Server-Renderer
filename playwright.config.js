import { defineConfig } from '@playwright/test';
import {ownership} from './tests/BrowserHarness/ownership.js';

ownership();

export default defineConfig({
    testDir: './tests/Browser',
    workers: 1,
    maxFailures: 10,
    globalSetup: './tests/BrowserHarness/setup.js',
    snapshotPathTemplate: '{testDir}/baselines/{projectName}/{arg}{ext}',
    outputDir: (process.env.STUDIO_BROWSER_ARTIFACTS || 'test-results')+'/results',
    updateSnapshots: process.env.STUDIO_UPDATE_BASELINES === '1' && !process.env.CI ? 'all' : 'none',
    projects: [
        {name: 'chromium', use: {browserName: 'chromium'}},
        {name: 'firefox', testMatch: '**/critical.spec.js', use: {browserName: 'firefox'}},
        {name: 'webkit', testMatch: '**/critical.spec.js', use: {browserName: 'webkit'}},
    ],
    use: {
        baseURL: process.env.STUDIO_BASE_URL,
        locale: 'en-GB',
        timezoneId: 'UTC',
        viewport: {width: 1440, height: 1000},
        reducedMotion: 'no-preference',
        actionTimeout: 12000,
        navigationTimeout: 20000,
        launchOptions: {timeout: 30000},
        screenshot: 'only-on-failure',
        // Existing credential/profile flows may expose passwords or tokens in network traces.
        trace: 'off',
    },
});

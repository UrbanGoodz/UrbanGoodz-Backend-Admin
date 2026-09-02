module.exports = {
  testDir: './tests/playwright',
  testIgnore: ['**/.claude/**', '**/node_modules/**'],
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:8000',
    screenshot: 'off',
    trace: 'off',
    video: 'off',
    navigationTimeout: 30000,
  },
  timeout: 30000,
};

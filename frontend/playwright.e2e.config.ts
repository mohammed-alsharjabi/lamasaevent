import { defineConfig, devices } from "@playwright/test";
import path from "node:path";

export default defineConfig({
  testDir: path.resolve(import.meta.dirname, "tests/e2e"),
  fullyParallel: false,
  workers: 1,
  reporter: "list",
  use: {
    ...devices["Desktop Chrome"],
    baseURL: "http://127.0.0.1:4321",
    locale: "ar-SA",
    trace: "retain-on-failure",
  },
  webServer: [
    {
      command: "php artisan serve --host=127.0.0.1 --port=8000",
      cwd: path.resolve(import.meta.dirname, "../backend"),
      url: "http://127.0.0.1:8000/up",
      reuseExistingServer: true,
      timeout: 30_000,
    },
    {
      command:
        "STAGING_HOST=127.0.0.1 STAGING_PORT=4321 STAGING_BACKEND_URL=http://127.0.0.1:8000 node ../staging/server.mjs",
      cwd: import.meta.dirname,
      url: "http://127.0.0.1:4321/",
      reuseExistingServer: true,
      timeout: 30_000,
    },
  ],
});

import { defineConfig, devices } from "@playwright/test";
import path from "node:path";

export default defineConfig({
  testDir: path.resolve(import.meta.dirname, "tests/visual"),
  outputDir: path.resolve(import.meta.dirname, "tests/visual/results"),
  fullyParallel: false,
  workers: 1,
  reporter: [["list"], ["html", { open: "never" }]],
  use: {
    ...devices["Desktop Chrome"],
    viewport: { width: 1440, height: 900 },
    colorScheme: "light",
    locale: "ar-SA",
    screenshot: "only-on-failure",
  },
});

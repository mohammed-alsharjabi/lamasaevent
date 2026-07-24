// @ts-check
import { defineConfig } from "astro/config";

export default defineConfig({
  site: "https://lams-event.com",
  output: "static",
  trailingSlash: "ignore",
  compressHTML: false,
  devToolbar: {
    enabled: false,
  },
  build: {
    format: "directory",
  },
});

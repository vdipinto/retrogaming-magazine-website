// vite.config.js (in your plugin root)
import { defineConfig } from "vite";
import { resolve } from "path";

export default defineConfig({
  build: {
    outDir: "assets",
    emptyOutDir: false,
    manifest: "manifest.json",   // ← ensures assets/manifest.json
    rollupOptions: {
      input: { frontend: resolve(__dirname, "resources/js/frontend.js") },
      output: {
        entryFileNames: "js/[name]-[hash].js",
        chunkFileNames: "js/[name]-[hash].js",
        assetFileNames: ({ name }) =>
          name && name.endsWith(".css")
            ? "css/[name]-[hash][extname]"
            : "assets/[name]-[hash][extname]",
      },
    },
  },
});

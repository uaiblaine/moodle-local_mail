import { defineConfig } from 'vite';
import { resolve } from 'path';
import { svelte } from '@sveltejs/vite-plugin-svelte';

// https://vitejs.dev/config/
export default defineConfig({
    build: {
        manifest: 'manifest.json',
        outDir: 'build',
        rollupOptions: {
            input: [resolve(__dirname, 'src/view.ts')],
            output: {
                entryFileNames: '[name]-[hash].js',
                chunkFileNames: '[name]-[hash].js',
                assetFileNames: '[name]-[hash][extname]',
            },
        },
        // Source maps embed the full node_modules sources, which ships ~460 kB of
        // dev-only data with the plugin and trips the CI development-leftover
        // checker on marker words found inside those vendored sources.
        sourcemap: false,
    },
    plugins: [svelte()],
    server: {
        cors: true,
    },
});

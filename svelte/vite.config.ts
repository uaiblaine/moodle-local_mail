import { defineConfig } from 'vite';
import { resolve } from 'path';
import { svelte } from '@sveltejs/vite-plugin-svelte';

// https://vitejs.dev/config/
export default defineConfig({
    build: {
        manifest: true,
        outDir: 'build',
        rollupOptions: {
            input: [resolve(__dirname, 'src/view.ts'), resolve(__dirname, 'src/navbar.ts')],
            output: {
                entryFileNames: `[name]-[hash].js`,
                chunkFileNames: `[name]-[hash].js`,
                assetFileNames: `[name]-[hash][extname]`,
            },
        },
    },
    plugins: [svelte()],
});

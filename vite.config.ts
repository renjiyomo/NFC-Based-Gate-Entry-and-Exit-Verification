import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [laravel({ input: ['resources/js/app.tsx'], refresh: true }), react(), tailwindcss()],
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        cors: { origin: 'http://127.0.0.1:8000' },
    },
});

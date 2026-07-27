import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devServerHost = env.VITE_DEV_SERVER_HOST || '127.0.0.1';
    const devServerPort = Number(env.VITE_DEV_SERVER_PORT || 5173);
    const devServerUrl = env.VITE_DEV_SERVER_URL;

    return {
        server: {
            host: devServerHost,
            port: devServerPort,
            strictPort: false,
            hmr: {
                host: env.VITE_DEV_SERVER_HMR_HOST || devServerHost,
            },
            ...(devServerUrl ? { origin: devServerUrl } : {}),
        },
        plugins: [
            laravel({
                input: 'resources/js/app.js',
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
        build: {
            rollupOptions: {
                output: {
                    manualChunks(id) {
                        if (!id.includes('node_modules')) {
                            return undefined;
                        }

                        if (id.includes('vue') || id.includes('@inertiajs')) {
                            return 'vendor-vue';
                        }

                        if (id.includes('line-awesome')) {
                            return 'vendor-icons';
                        }

                        return 'vendor';
                    },
                },
            },
        },
    };
});

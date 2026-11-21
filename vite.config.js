import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { instrumentFormFeedback } from './resources/js/support/formFeedbackCompiler.js';
import { parse as parseSfc } from '@vue/compiler-sfc';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devServerHost = env.VITE_DEV_SERVER_HOST || '127.0.0.1';
    const devServerPort = Number(env.VITE_DEV_SERVER_PORT || 5173);
    const devServerUrl = env.VITE_DEV_SERVER_URL;

    return {
        // Locale loaders consume the default catalog, never per-key exports.
        json: { namedExports: false },
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
            {
                name: 'airmius-form-feedback',
                enforce: 'pre',
                transform(source, id) {
                    if (!id.endsWith('.vue') || id.includes('node_modules')) return;
                    const template = parseSfc(source).descriptor.template;
                    if (!template) return;
                    return source.slice(0, template.loc.start.offset)
                        + instrumentFormFeedback(template.content)
                        + source.slice(template.loc.end.offset);
                },
            },
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

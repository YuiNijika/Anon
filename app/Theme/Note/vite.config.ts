export default {
    base: '/',
    build: {
        outDir: 'assets',
        emptyOutDir: false,
        rollupOptions: {
            input: 'src/main.ts',
            output: {
                entryFileNames: 'main.js',
                format: 'iife',
            },
        },
    },
    server: {
        port: 5173,
        strictPort: true,
        cors: true,
        host: '0.0.0.0', // 允许外部访问
        origin: 'http://localhost:5173', // 强制使用绝对路径
        hmr: {
            protocol: 'ws',
            host: 'localhost',
            port: 5173,
        },
    },
};

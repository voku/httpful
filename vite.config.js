import { defineConfig } from 'vite';

export default defineConfig(({ command }) => ({
  base: command === 'serve' ? '/' : '/httpful/',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
}));

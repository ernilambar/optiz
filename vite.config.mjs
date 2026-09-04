import { defineConfig } from 'vite';
import browserslistToEsbuild from 'browserslist-to-esbuild';

export default defineConfig( {
	build: {
		target: browserslistToEsbuild(),
		lib: {
			entry: 'resources/js/index.js',
			formats: [ 'iife' ],
			name: 'optiz',
			fileName: () => 'optiz.js',
		},
		outDir: 'assets',
		emptyOutDir: false,
		rollupOptions: {
			output: {
				assetFileNames: 'optiz[extname]',
			},
		},
	},
} );

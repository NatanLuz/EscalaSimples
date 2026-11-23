import * as esbuild from 'esbuild';
import { spawn } from 'node:child_process';

const watch = process.argv.includes('--watch');
const options = {
  entryPoints: ['resources/js/app.jsx'],
  bundle: true,
  outfile: 'public/build/app.js',
  format: 'esm',
  jsx: 'automatic',
  minify: !watch,
  sourcemap: watch,
  define: { 'process.env.NODE_ENV': JSON.stringify(watch ? 'development' : 'production') },
};

const tailwind = spawn(process.execPath, [
  'node_modules/@tailwindcss/cli/dist/index.mjs', '-i', 'resources/css/app.css',
  '-o', 'public/build/app.css', ...(watch ? ['--watch=always'] : ['--minify']),
], { stdio: 'inherit' });
tailwind.on('exit', code => { if (code && !process.exitCode) process.exitCode = code; });
if (watch) {
  const context = await esbuild.context(options);
  await context.watch();
  console.log('esbuild: observando alterações');
  for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, async () => {
    tailwind.kill();
    await context.dispose();
    process.exit(0);
  });
} else {
  await esbuild.build(options);
}

import esbuild from 'esbuild';

const watch = process.argv.includes('--watch');

const options = {
    entryPoints: ['assets/js/app.js'],
    bundle: true,
    minify: true,
    sourcemap: true,
    target: ['es2020'],
    outfile: 'public/assets/js/app.js',
    logLevel: 'info',
};

if (watch) {
    const ctx = await esbuild.context(options);
    await ctx.watch();
} else {
    await esbuild.build(options);
}

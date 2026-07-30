import * as esbuild from "esbuild";

/**
 * Builds the self-contained assets/consent.{js,css} pair that the plugin ships.
 *
 * Shipping prebuilt assets is the whole point: a consuming site drops the
 * plugin in and gets a working banner regardless of whether it uses Vite,
 * webpack or no bundler at all.
 */
const options = {
    entryPoints: ["src/consent.js", "src/consent.css"],
    outdir: "assets",
    bundle: true,
    format: "iife",
    target: ["es2020"],
    minify: true,
    sourcemap: false,
    legalComments: "none",
    logLevel: "info",
};

if (process.argv.includes("--watch")) {
    const ctx = await esbuild.context(options);
    await ctx.watch();
} else {
    await esbuild.build(options);
}

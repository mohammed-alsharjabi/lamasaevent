#!/usr/bin/env node

import {
  copyFileSync,
  cpSync,
  existsSync,
  mkdirSync,
  realpathSync,
  rmSync,
} from "node:fs";
import { relative, resolve, sep } from "node:path";

const args = Object.fromEntries(
  process.argv.slice(2).map((argument) => {
    const [key, ...value] = argument.replace(/^--/, "").split("=");
    return [key, value.join("=")];
  }),
);

const legacyRoot = realpathSync(
  resolve(args.legacy || process.env.LEGACY_DIST || "../legacy-dist"),
);
const publicRoot = resolve(args.output || "frontend/public");
const outputRelativeToLegacy = relative(legacyRoot, publicRoot);

if (
  outputRelativeToLegacy === "" ||
  (!outputRelativeToLegacy.startsWith(`..${sep}`) &&
    outputRelativeToLegacy !== "..")
) {
  throw new Error("Refusing to write generated assets inside legacy-dist.");
}

mkdirSync(publicRoot, { recursive: true });

for (const directory of ["_astro", "assets", "icons"]) {
  const source = resolve(legacyRoot, directory);
  const destination = resolve(publicRoot, directory);
  if (!existsSync(source)) {
    throw new Error(`Missing required legacy asset directory: ${directory}`);
  }
  rmSync(destination, { recursive: true, force: true });
  cpSync(source, destination, {
    recursive: true,
    preserveTimestamps: true,
    filter: (sourcePath) => !sourcePath.endsWith(`${sep}.DS_Store`),
  });
}

for (const filename of [
  "404.html",
  "favicon.png",
  "google93ac6571a02c8d53.html",
  "lmsevt_idx_7n4k.txt",
  "robots.txt",
]) {
  const source = resolve(legacyRoot, filename);
  if (existsSync(source)) {
    copyFileSync(source, resolve(publicRoot, filename));
  }
}

console.log(`Synchronized immutable legacy assets to ${publicRoot}`);

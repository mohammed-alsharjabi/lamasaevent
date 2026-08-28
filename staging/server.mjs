#!/usr/bin/env node

import { timingSafeEqual } from "node:crypto";
import { createReadStream, existsSync, statSync } from "node:fs";
import http from "node:http";
import { dirname, extname, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const distRoot = resolve(projectRoot, "frontend", "dist");
const host = process.env.STAGING_HOST || "127.0.0.1";
const port = Number(process.env.STAGING_PORT || 8080);
const backend = new URL(
  process.env.STAGING_BACKEND_URL || "http://127.0.0.1:8081",
);
const basicUser = process.env.STAGING_BASIC_USER || "";
const basicPassword = process.env.STAGING_BASIC_PASSWORD || "";
const loopbackHosts = new Set(["127.0.0.1", "::1", "localhost"]);

if (!existsSync(resolve(distRoot, "index.html"))) {
  throw new Error("frontend/dist is missing. Run scripts/staging-prepare.sh first.");
}

if (!loopbackHosts.has(host) && (!basicUser || !basicPassword)) {
  throw new Error(
    "A non-loopback staging listener requires STAGING_BASIC_USER and STAGING_BASIC_PASSWORD.",
  );
}

if ((basicUser && !basicPassword) || (!basicUser && basicPassword)) {
  throw new Error("Set both staging basic-auth values or neither.");
}

const mimeTypes = new Map([
  [".css", "text/css; charset=utf-8"],
  [".gif", "image/gif"],
  [".html", "text/html; charset=utf-8"],
  [".ico", "image/x-icon"],
  [".jpeg", "image/jpeg"],
  [".jpg", "image/jpeg"],
  [".js", "text/javascript; charset=utf-8"],
  [".json", "application/json; charset=utf-8"],
  [".png", "image/png"],
  [".svg", "image/svg+xml"],
  [".txt", "text/plain; charset=utf-8"],
  [".webp", "image/webp"],
  [".xml", "application/xml; charset=utf-8"],
]);

const backendPaths = [
  "/admin",
  "/api",
  "/up",
  "/admin.css",
  "/storage",
];

function secureEqual(left, right) {
  const leftBuffer = Buffer.from(left);
  const rightBuffer = Buffer.from(right);
  return (
    leftBuffer.length === rightBuffer.length &&
    timingSafeEqual(leftBuffer, rightBuffer)
  );
}

function isAuthorized(request) {
  if (!basicUser) return true;

  const header = request.headers.authorization || "";
  if (!header.startsWith("Basic ")) return false;

  let credentials;
  try {
    credentials = Buffer.from(header.slice(6), "base64").toString("utf8");
  } catch {
    return false;
  }
  const separator = credentials.indexOf(":");
  if (separator < 0) return false;

  return (
    secureEqual(credentials.slice(0, separator), basicUser) &&
    secureEqual(credentials.slice(separator + 1), basicPassword)
  );
}

function stagingHeaders(extra = {}) {
  return {
    "Cache-Control": "no-store",
    "Referrer-Policy": "strict-origin-when-cross-origin",
    "X-Content-Type-Options": "nosniff",
    "X-Robots-Tag": "noindex, nofollow, noarchive",
    ...extra,
  };
}

function staticFileFor(pathname) {
  let decoded;
  try {
    decoded = decodeURIComponent(pathname);
  } catch {
    return null;
  }

  const relativePath = decoded === "/" ? "index.html" : decoded.replace(/^\/+/, "");
  const candidate = resolve(distRoot, relativePath);
  const escaped = relative(distRoot, candidate);
  if (escaped === ".." || escaped.startsWith(`..${sep}`)) return null;

  if (existsSync(candidate) && statSync(candidate).isFile()) return candidate;
  const indexFile = resolve(candidate, "index.html");
  if (existsSync(indexFile) && statSync(indexFile).isFile()) return indexFile;
  return null;
}

function proxyToLaravel(request, response) {
  const proxy = http.request(
    {
      protocol: backend.protocol,
      hostname: backend.hostname,
      port: backend.port,
      method: request.method,
      path: request.url,
      headers: {
        ...request.headers,
        host: backend.host,
        "x-forwarded-host": request.headers.host || "",
        "x-forwarded-proto": "http",
      },
    },
    (backendResponse) => {
      response.writeHead(
        backendResponse.statusCode || 502,
        stagingHeaders(backendResponse.headers),
      );
      backendResponse.pipe(response);
    },
  );

  proxy.on("error", (error) => {
    response.writeHead(
      502,
      stagingHeaders({ "Content-Type": "text/plain; charset=utf-8" }),
    );
    response.end(`Laravel staging backend is unavailable: ${error.message}\n`);
  });
  request.pipe(proxy);
}

const server = http.createServer((request, response) => {
  if (!isAuthorized(request)) {
    response.writeHead(
      401,
      stagingHeaders({
        "Content-Type": "text/plain; charset=utf-8",
        "WWW-Authenticate": 'Basic realm="Lams staging", charset="UTF-8"',
      }),
    );
    response.end("Authentication required.\n");
    return;
  }

  const url = new URL(request.url || "/", "http://staging.invalid");
  if (url.pathname === "/robots.txt") {
    const robots = "User-agent: *\nDisallow: /\n";
    response.writeHead(
      200,
      stagingHeaders({
        "Content-Length": Buffer.byteLength(robots),
        "Content-Type": "text/plain; charset=utf-8",
      }),
    );
    response.end(request.method === "HEAD" ? undefined : robots);
    return;
  }

  const backendRequest = backendPaths.some(
    (prefix) => url.pathname === prefix || url.pathname.startsWith(`${prefix}/`),
  );
  if (backendRequest) {
    proxyToLaravel(request, response);
    return;
  }

  if (request.method === "GET" || request.method === "HEAD") {
    const file = staticFileFor(url.pathname);
    if (file) {
      response.writeHead(
        200,
        stagingHeaders({
          "Content-Length": statSync(file).size,
          "Content-Type": mimeTypes.get(extname(file).toLowerCase()) || "application/octet-stream",
        }),
      );
      if (request.method === "HEAD") {
        response.end();
      } else {
        createReadStream(file).pipe(response);
      }
      return;
    }
  }

  const notFound = resolve(distRoot, "404.html");
  response.writeHead(
    404,
    stagingHeaders({ "Content-Type": "text/html; charset=utf-8" }),
  );
  createReadStream(notFound).pipe(response);
});

server.listen(port, host, () => {
  console.log(`Lams staging gateway: http://${host}:${port}`);
  console.log(`Laravel backend: ${backend.href}`);
  console.log("Robots policy: noindex, nofollow, noarchive");
});

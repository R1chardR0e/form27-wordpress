import fs from "node:fs";
import path from "node:path";
import http from "node:http";
import {
  outputPathForUrl,
  resolveInsideRepository,
} from "./lib/project-paths.mjs";

const mimeTypes = new Map([
  [".avif", "image/avif"],
  [".css", "text/css; charset=utf-8"],
  [".gif", "image/gif"],
  [".html", "text/html; charset=utf-8"],
  [".ico", "image/x-icon"],
  [".jpeg", "image/jpeg"],
  [".jpg", "image/jpeg"],
  [".js", "text/javascript; charset=utf-8"],
  [".json", "application/json; charset=utf-8"],
  [".mp4", "video/mp4"],
  [".png", "image/png"],
  [".svg", "image/svg+xml"],
  [".webm", "video/webm"],
  [".webmanifest", "application/manifest+json"],
  [".webp", "image/webp"],
  [".woff", "font/woff"],
  [".woff2", "font/woff2"],
]);

function argumentValue(name, fallback) {
  const index = process.argv.indexOf(name);
  if (index >= 0 && process.argv[index + 1]) {
    return process.argv[index + 1];
  }

  const combined = process.argv.find((argument) =>
    argument.startsWith(`${name}=`),
  );
  return combined ? combined.slice(name.length + 1) : fallback;
}

const root = resolveInsideRepository(argumentValue("--root", "dist"));
const port = Number(argumentValue("--port", "8799"));

if (!Number.isInteger(port) || port < 1 || port > 65_535) {
  throw new Error(`Invalid port: ${port}`);
}

function send(response, status, body, headers = {}) {
  response.writeHead(status, {
    "Cache-Control": "no-store",
    "X-Content-Type-Options": "nosniff",
    ...headers,
  });
  response.end(body);
}

const server = http.createServer((request, response) => {
  if (!request.url || !["GET", "HEAD"].includes(request.method || "")) {
    send(response, 405, "Method Not Allowed\n", {
      Allow: "GET, HEAD",
      "Content-Type": "text/plain; charset=utf-8",
    });
    return;
  }

  let filePath;
  try {
    const pathname = new URL(request.url, "http://127.0.0.1").pathname;
    filePath = outputPathForUrl(root, pathname);
    const relative = path.relative(root, filePath);
    if (relative.startsWith(`..${path.sep}`) || path.isAbsolute(relative)) {
      throw new Error("Path escaped the static root");
    }
  } catch {
    send(response, 400, "Bad Request\n", {
      "Content-Type": "text/plain; charset=utf-8",
    });
    return;
  }

  fs.stat(filePath, (statError, stats) => {
    if (statError || !stats.isFile()) {
      send(response, 404, "Not Found\n", {
        "Content-Type": "text/plain; charset=utf-8",
      });
      return;
    }

    const headers = {
      "Content-Length": String(stats.size),
      "Content-Type":
        mimeTypes.get(path.extname(filePath).toLowerCase()) ||
        "application/octet-stream",
    };
    if (request.method === "HEAD") {
      send(response, 200, "", headers);
      return;
    }

    response.writeHead(200, {
      "Cache-Control": "no-store",
      "X-Content-Type-Options": "nosniff",
      ...headers,
    });
    fs.createReadStream(filePath).pipe(response);
  });
});

server.listen(port, "127.0.0.1", () => {
  console.log(`Static test server: http://127.0.0.1:${port}`);
});

function shutdown() {
  server.close(() => process.exit(0));
}

process.once("SIGINT", shutdown);
process.once("SIGTERM", shutdown);

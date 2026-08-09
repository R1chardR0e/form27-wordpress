import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { runCLI } from "@wp-playground/cli";
import { repositoryRoot } from "./lib/project-paths.mjs";

function readArgument(name, fallback) {
  const equals = process.argv.find((argument) =>
    argument.startsWith(`${name}=`),
  );
  if (equals) {
    return equals.slice(name.length + 1);
  }

  const index = process.argv.indexOf(name);
  return index >= 0 && process.argv[index + 1]
    ? process.argv[index + 1]
    : fallback;
}

export async function startForm27Playground({
  port = 9400,
  login = true,
} = {}) {
  const themePath = path.join(repositoryRoot, "wp-content/themes/form27");
  const pluginPath = path.join(
    repositoryRoot,
    "wp-content/plugins/form27-core",
  );
  const blueprintPath = path.join(repositoryRoot, "playground-local.json");

  await Promise.all(
    [themePath, pluginPath, blueprintPath].map(async (requiredPath) => {
      try {
        await fs.access(requiredPath);
      } catch {
        throw new Error(`Required Playground path is missing: ${requiredPath}`);
      }
    }),
  );

  const blueprint = JSON.parse(await fs.readFile(blueprintPath, "utf8"));

  return runCLI({
    command: "server",
    port: Number(port),
    php: process.env.FORM27_PHP_VERSION || "8.3",
    wp:
      process.env.FORM27_WP_SOURCE ||
      "https://wordpress.org/wordpress-7.0.3.zip",
    workers: Number(process.env.FORM27_PLAYGROUND_WORKERS || "6"),
    verbosity: process.env.FORM27_PLAYGROUND_VERBOSITY || "normal",
    login,
    mount: [
      {
        hostPath: themePath,
        vfsPath: "/wordpress/wp-content/themes/form27",
      },
      {
        hostPath: pluginPath,
        vfsPath: "/wordpress/wp-content/plugins/form27-core",
      },
    ],
    blueprint,
  });
}

export async function stopForm27Playground(instance) {
  if (!instance) {
    return;
  }

  if (typeof instance[Symbol.asyncDispose] === "function") {
    await instance[Symbol.asyncDispose]();
    return;
  }

  await instance.server?.close();
}

async function main() {
  const port = Number(readArgument("--port", "9400"));
  const isContinuousIntegration = process.argv.includes("--ci");
  const instance = await startForm27Playground({
    port,
    login: !isContinuousIntegration,
  });

  console.log(`FORM 27 Playground: ${instance.serverUrl}`);
  if (!isContinuousIntegration) {
    console.log(
      "Admin: /wp-admin/ (the local Blueprint logs in automatically)",
    );
    console.log("Stop the server with Ctrl+C.");
  }

  let stopping = false;
  const stop = async () => {
    if (stopping) {
      return;
    }
    stopping = true;
    await stopForm27Playground(instance);
    process.exit(0);
  };

  process.once("SIGINT", stop);
  process.once("SIGTERM", stop);
}

const isEntrypoint = process.argv[1]
  ? path.resolve(process.argv[1]) ===
    path.resolve(fileURLToPath(import.meta.url))
  : false;

if (isEntrypoint) {
  main().catch((error) => {
    console.error(error);
    if (error?.cause) {
      console.error("Caused by:", error.cause);
    }
    process.exitCode = 1;
  });
}

import { spawnSync } from "node:child_process";
import fs from "node:fs/promises";
import path from "node:path";
import { repositoryRoot } from "./lib/project-paths.mjs";

function parseEnvironment(contents) {
  const values = {};
  for (const line of contents.split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith("#")) {
      continue;
    }
    const separator = trimmed.indexOf("=");
    if (separator > 0) {
      values[trimmed.slice(0, separator)] = trimmed.slice(separator + 1);
    }
  }
  return values;
}

function dockerCompose(arguments_, options = {}) {
  const result = spawnSync("docker", ["compose", ...arguments_], {
    cwd: repositoryRoot,
    encoding: "utf8",
    stdio: options.capture ? "pipe" : "inherit",
    input: options.input,
  });

  if (result.error) {
    throw result.error;
  }
  return result;
}

async function waitForWordPress() {
  for (let attempt = 0; attempt < 30; attempt += 1) {
    const result = dockerCompose(["run", "--rm", "cli", "core", "version"], {
      capture: true,
    });
    if (result.status === 0) {
      return;
    }
    await new Promise((resolve) => setTimeout(resolve, 2_000));
  }
  throw new Error("WordPress did not become ready within 60 seconds");
}

async function main() {
  const envPath = path.join(repositoryRoot, ".env");
  let environment;
  try {
    environment = parseEnvironment(await fs.readFile(envPath, "utf8"));
  } catch {
    throw new Error("Create .env from .env.example before Docker bootstrap");
  }

  const required = [
    "FORM27_WP_ADMIN_USER",
    "FORM27_WP_ADMIN_PASSWORD",
    "FORM27_WP_ADMIN_EMAIL",
  ];
  for (const key of required) {
    if (!environment[key] || environment[key].startsWith("replace-with")) {
      throw new Error(`Set a non-placeholder ${key} value in .env`);
    }
  }

  dockerCompose(["up", "-d", "database", "wordpress", "mailpit"]);
  await waitForWordPress();

  const installed = dockerCompose(
    ["run", "--rm", "cli", "core", "is-installed"],
    { capture: true },
  );
  if (installed.status !== 0) {
    const port = environment.FORM27_HTTP_PORT || "8080";
    const installation = dockerCompose(
      [
        "run",
        "--rm",
        "cli",
        "core",
        "install",
        `--url=http://localhost:${port}`,
        "--title=FORM 27",
        `--admin_user=${environment.FORM27_WP_ADMIN_USER}`,
        `--admin_email=${environment.FORM27_WP_ADMIN_EMAIL}`,
        "--skip-email",
        "--prompt=admin_password",
      ],
      { capture: true, input: `${environment.FORM27_WP_ADMIN_PASSWORD}\n` },
    );
    if (installation.status !== 0) {
      throw new Error(installation.stderr || "WordPress installation failed");
    }
  }

  for (const command of [
    ["plugin", "activate", "form27-core"],
    ["theme", "activate", "form27"],
    ["rewrite", "structure", "/%postname%/", "--hard"],
    ["form27", "seed"],
  ]) {
    const result = dockerCompose(["run", "--rm", "cli", ...command]);
    if (result.status !== 0) {
      throw new Error(`WP-CLI failed: wp ${command.join(" ")}`);
    }
  }

  console.log("FORM 27 is ready. Mailpit is available on its configured port.");
}

main().catch((error) => {
  console.error(error.message);
  process.exitCode = 1;
});

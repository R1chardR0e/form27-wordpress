import fs from "node:fs/promises";
import path from "node:path";
import { validateBlueprintDeclaration } from "@wp-playground/blueprints";
import { repositoryRoot } from "./lib/project-paths.mjs";

const requiredFiles = [
  "package-lock.json",
  "playground-blueprint.json",
  "playground-local.json",
  "wp-content/themes/form27/style.css",
  "wp-content/themes/form27/theme.json",
  "wp-content/plugins/form27-core/form27-core.php",
  "docs/architecture.md",
  "docs/data-contract.md",
  "docs/bereg-audit.md",
];

const errors = [];

async function read(relativePath) {
  return fs.readFile(path.join(repositoryRoot, relativePath), "utf8");
}

for (const relativePath of requiredFiles) {
  try {
    await fs.access(path.join(repositoryRoot, relativePath));
  } catch {
    errors.push(`Missing required file: ${relativePath}`);
  }
}

for (const blueprintFile of [
  "playground-blueprint.json",
  "playground-local.json",
]) {
  try {
    const blueprint = JSON.parse(await read(blueprintFile));
    const schemaResult = await validateBlueprintDeclaration(blueprint);
    if (!schemaResult.valid) {
      errors.push(
        `${blueprintFile} does not satisfy the official Blueprint schema: ${JSON.stringify(schemaResult.errors)}`,
      );
    }
    if (
      blueprintFile === "playground-blueprint.json" &&
      blueprint.preferredVersions?.php !== "8.3"
    ) {
      errors.push(`${blueprintFile} must target PHP 8.3`);
    }
    if (
      blueprintFile === "playground-blueprint.json" &&
      blueprint.preferredVersions?.wp !== "7.0.3"
    ) {
      errors.push(`${blueprintFile} must target WordPress 7.0.3`);
    }
    if (
      !blueprint.steps?.some(
        (step) =>
          (step.step === "wp-cli" && step.command === "wp form27 seed") ||
          (step.step === "runPHP" && step.code?.includes("F27_Seeder::seed")),
      )
    ) {
      errors.push(`${blueprintFile} must run the idempotent FORM 27 seed`);
    }
  } catch (error) {
    errors.push(`${blueprintFile} is invalid: ${error.message}`);
  }
}

try {
  const publicBlueprint = await read("playground-blueprint.json");
  for (const asset of ["form27-theme.zip", "form27-core.zip"]) {
    if (!publicBlueprint.includes(`/releases/latest/download/${asset}`)) {
      errors.push(`Public Blueprint does not reference ${asset}`);
    }
  }
} catch {
  // The missing-file error above is more useful.
}

try {
  const plugin = await read("wp-content/plugins/form27-core/form27-core.php");
  if (!/Plugin Name:\s*FORM 27 Core/i.test(plugin)) {
    errors.push("The plugin entry file needs a FORM 27 Core plugin header");
  }
} catch {
  // The missing-file error above is more useful.
}

try {
  const theme = await read("wp-content/themes/form27/style.css");
  if (!/Theme Name:\s*FORM 27/i.test(theme)) {
    errors.push("The theme stylesheet needs a FORM 27 theme header");
  }
} catch {
  // The missing-file error above is more useful.
}

try {
  const homePattern = await read("wp-content/themes/form27/patterns/home.php");
  if (!homePattern.includes("wp_make_link_relative( $form27_theme_uri )")) {
    errors.push(
      "The home pattern must keep theme image URLs relative for HTTPS Playground and subdirectory installs",
    );
  }
} catch (error) {
  errors.push(`The FORM 27 home pattern is invalid: ${error.message}`);
}

if (errors.length) {
  console.error(errors.map((error) => `- ${error}`).join("\n"));
  process.exitCode = 1;
} else {
  console.log("Project structure and Playground contracts are valid.");
}

import { createHash } from "node:crypto";
import { createWriteStream } from "node:fs";
import fs from "node:fs/promises";
import path from "node:path";
import { pipeline } from "node:stream/promises";
import { ZipArchive } from "archiver";
import {
  repositoryRoot,
  resolveInsideRepository,
} from "./lib/project-paths.mjs";

const releaseDirectory = resolveInsideRepository("release");
const packages = [
  {
    source: path.join(repositoryRoot, "wp-content/themes/form27"),
    prefix: "form27",
    output: "form27-theme.zip",
  },
  {
    source: path.join(repositoryRoot, "wp-content/plugins/form27-core"),
    prefix: "form27-core",
    output: "form27-core.zip",
  },
];

async function zipDirectory({ source, prefix, output }) {
  await fs.access(source);
  const destination = path.join(releaseDirectory, output);
  const archive = new ZipArchive({ zlib: { level: 9 } });
  const outputStream = createWriteStream(destination);

  archive.glob(
    "**/*",
    {
      cwd: source,
      dot: true,
      ignore: [
        "**/.DS_Store",
        "**/*.log",
        "**/node_modules/**",
        "**/vendor/**",
      ],
    },
    { prefix },
  );

  const completed = pipeline(archive, outputStream);
  await archive.finalize();
  await completed;
  return destination;
}

async function checksum(filePath) {
  const contents = await fs.readFile(filePath);
  return createHash("sha256").update(contents).digest("hex");
}

async function main() {
  await fs.rm(releaseDirectory, { recursive: true, force: true });
  await fs.mkdir(releaseDirectory, { recursive: true });

  const outputs = [];
  for (const definition of packages) {
    outputs.push(await zipDirectory(definition));
  }

  const blueprintSource = path.join(
    repositoryRoot,
    "playground-blueprint.json",
  );
  const blueprintOutput = path.join(
    releaseDirectory,
    "playground-blueprint.json",
  );
  await fs.copyFile(blueprintSource, blueprintOutput);
  outputs.push(blueprintOutput);

  const manifest = [];
  for (const output of outputs) {
    const stats = await fs.stat(output);
    manifest.push({
      file: path.basename(output),
      bytes: stats.size,
      sha256: await checksum(output),
    });
  }

  await fs.writeFile(
    path.join(releaseDirectory, "manifest.json"),
    `${JSON.stringify({ schemaVersion: 1, files: manifest }, null, 2)}\n`,
  );
  await fs.writeFile(
    path.join(releaseDirectory, "SHA256SUMS.txt"),
    `${manifest.map(({ file, sha256 }) => `${sha256}  ${file}`).join("\n")}\n`,
  );

  console.log(`Release artifacts written to ${releaseDirectory}`);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { indexDirectory } from 'docmd-search';

const root = process.cwd();
const config = JSON.parse(await readFile(path.join(root, '.docmd-search', 'config.json'), 'utf8'));
const outputDir = path.join(root, '_site', '.docmd-search');

await mkdir(outputDir, { recursive: true });

await indexDirectory({
  rootDir: path.join(root, 'docs'),
  outDir: outputDir,
  model: config.model,
  include: config.include,
  exclude: config.exclude,
  chunkSize: config.chunkSize,
  chunkOverlap: config.chunkOverlap,
  incremental: config.incremental,
  topK: config.topK,
  config
});

import { readdir, readFile, stat } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const docsDir = path.join(root, 'docs');
const siteDir = path.join(root, '_site');
const rawHtml = /<\/?[a-z][\s\S]*?>/i;

async function walk(dir, predicate, files = []) {
  let entries;
  try {
    entries = await readdir(dir, { withFileTypes: true });
  } catch {
    return files;
  }

  for (const entry of entries) {
    const current = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      await walk(current, predicate, files);
    } else if (predicate(current)) {
      files.push(current);
    }
  }
  return files;
}

const markdownFiles = await walk(docsDir, (file) => file.endsWith('.md'));
const rawMarkdown = [];

for (const file of markdownFiles) {
  const content = await readFile(file, 'utf8');
  if (rawHtml.test(content)) {
    rawMarkdown.push(path.relative(root, file));
  }
}

if (rawMarkdown.length > 0) {
  console.error(`Raw HTML is not allowed in Markdown:\n${rawMarkdown.join('\n')}`);
  process.exit(1);
}

const siteExists = await stat(siteDir).then(() => true).catch(() => false);
if (siteExists) {
  const htmlFiles = await walk(siteDir, (file) => file.endsWith('.html'));
  const leakedContainers = [];

  for (const file of htmlFiles) {
    const content = await readFile(file, 'utf8');
    if (content.includes(':::')) {
      leakedContainers.push(path.relative(root, file));
    }
  }

  if (leakedContainers.length > 0) {
    console.error(`Unrendered docmd containers leaked into HTML:\n${leakedContainers.join('\n')}`);
    process.exit(1);
  }
}

console.log('Docs content guard passed.');

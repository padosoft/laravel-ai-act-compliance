# docmd-docs

Use this skill when creating or maintaining the `docs-site` docmd documentation for `padosoft/laravel-ai-act-compliance`.

## Rules

- Keep docs in Markdown only. Do not add MDX, JSX, or raw HTML to `docs-site/docs`.
- Use docmd containers for richer structure: `callout`, `tabs`, `steps`, `collapsible`, `grids`, `grid`, and `card`.
- Do not use `::: button`.
- Keep `docs-site/docmd.config.json` navigation in sync with every Markdown page.
- Keep semantic search configured in `docs-site/.docmd-search/config.json` with `Xenova/all-MiniLM-L6-v2`.
- Run `npm run check` and `npm run build` from `docs-site` before committing docs changes.

# Rule: docmd Docs Sync

When a change adds, removes, or renames a documentation page under `docs-site/docs`, update `docs-site/docmd.config.json` navigation in the same change.

Documentation pages must remain Markdown-only, avoid raw HTML, and must not use `::: button`. Run the docs guard before publishing.

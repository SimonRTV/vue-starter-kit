---
paths:
  - '{vite.config.ts,package.json,composer.json,eslint.config.js,.prettierrc}'
---

# General

## Frontend lint and format run through Vite+ (vp), not ESLint or Prettier
Since the September 2026 upstream sync, frontend linting and formatting are Vite+ (`npm run check` / `npm run check:fix`, `npx vp fmt <paths>`), configured in the `lint` and `fmt` blocks of vite.config.ts. Do not re-add eslint.config.js, .prettierrc or their packages; `composer ci:check` calls `npm run check`. Oxfmt sorts Tailwind classes differently from Prettier (custom theme classes first), so tests must not assert class-string order. Boost-managed AI assistant files (.agents, .ai, .claude, .codex, .cursor, .junie, .mcp.json, AGENTS.md, CLAUDE.md, boost.json) are excluded from fmt so Boost regeneration does not churn.

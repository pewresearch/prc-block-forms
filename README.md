# PRC Block Forms (`@prc/block-forms`)

Forms management for the PRC Platform:

- **`form` custom post type** — reusable, block-composed form definitions
- **Form blocks** — `prc-block/form`, `form-page`, `form-submit`, `form-message`, `form-captcha`, `form-message-bindings` (form-input-* blocks remain in `@prc/block-library`)
- **`prc-block/synced-form` block** — embed a saved form anywhere on the site
- **`prc-block-library/forms` data store** — form action registry consumed by the form block editor and downstream plugins
- **`Form_Renderer` PHP utility** — render a form CPT post by ID or slug from PHP (used by Content Gate, account pages, and other plugins)
- **Response logging** — custom `prc_form_responses` table with REST API, unread state, and DataViews admin UI (`src/response-admin/`)
- **Forms library** — **All Forms** runs on the shared `prc-wp-admin-dataview` shell; the provider lives in `src/admin-dataview/`
- **Abilities API** — Jetpack-shaped `prc-block-forms/*` tools for VIP MCP; Jetpack `jetpack-forms/*` abilities are suppressed. Full ability list and unread/spam semantics: [`docs/abilities.md`](../../docs/plugins/prc-block-forms/abilities.md).
- **REST submission handlers** — `sendToEmail` and `logResponse` actions consumed by `prc-block/form`
- **Sample forms** — optional Forms CPT seeds (e.g. **Speaker Request (Sample)** at slug `speaker-request`) provisioned idempotently on `init` via `Form_Sample_Provisioner`; markup lives in `patterns/`

## Form blocks

| Block | Doc |
| --- | --- |
| `prc-block/form` | [`docs/blocks/form.md`](../../docs/plugins/prc-block-forms/blocks/form.md) |
| `prc-block/synced-form` | [`docs/blocks/synced-form.md`](../../docs/plugins/prc-block-forms/blocks/synced-form.md) |
| `prc-block/form-page` | [`docs/blocks/form-page.md`](../../docs/plugins/prc-block-forms/blocks/form-page.md) |
| `prc-block/form-submit` | [`docs/blocks/form-submit.md`](../../docs/plugins/prc-block-forms/blocks/form-submit.md) |
| `prc-block/form-message` | [`docs/blocks/form-message.md`](../../docs/plugins/prc-block-forms/blocks/form-message.md) |
| `prc-block/form-captcha` | [`docs/blocks/form-captcha.md`](../../docs/plugins/prc-block-forms/blocks/form-captcha.md) |
| `prc-block/form-message-bindings` | Editor-only binding registration companion |

## Sample forms

On first run (and on self-heal when the seed version bumps), `@prc/block-forms` creates editable sample form posts editors can duplicate or embed with the Synced Form block:

| Slug | Title | Pattern file |
| --- | --- | --- |
| `speaker-request` | Speaker Request (Sample) | `patterns/speaker-request-form.php` |

Sample forms are deletable — they are onboarding starters, not runtime dependencies. Update the form's **Redirect Target** (recipient email for `sendToEmail`) before going live.

## Forms library (DataViews)

**Forms → All Forms** uses the shared `prc-wp-admin-dataview` shell (`prc-forms-library` page slug) instead of the classic `edit.php` list table. The domain provider in `src/admin-dataview/` adds action, method, response, and unread columns. The list reads mirrored post meta (`_prc_form_action`, `_prc_form_method`, `_prc_form_field_count`) synced on save because form configuration lives in `prc-block/form` block attributes inside `post_content`.

- Filter and sort by action, submission method, and field count; open a form or view its responses from row actions.
- Presence avatars and the Active editors filter work because the `form` CPT declares `presence` support. Working on / Watchers come from `prc-publish-workflows`.
- Escape hatch: append `?classic=1` to `edit.php?post_type=form` to use the classic list table (shell-owned).

Implementation: `includes/class-form-list.php` + `src/admin-dataview/` + `GET /prc-api/v3/form/library`.

**Forms → Responses** remains a plugin-owned DataViews screen for the `prc_form_responses` table (see [`docs/user-guide.md`](../../docs/plugins/prc-block-forms/user-guide.md) and [`docs/architecture.md`](../../docs/plugins/prc-block-forms/architecture.md)).

## Form_Renderer

Render a saved form from PHP without placing a synced-form block in content:

```php
use PRC\Platform\Block_Forms\Form_Renderer;

// By slug (post_name on the form CPT).
$html = Form_Renderer::render_by_slug( 'account-login' );

// By post ID.
$html = Form_Renderer::render_by_id( 123 );
```

Uses `parse_blocks()` + `WP_Block::render()` (not `do_blocks()`) so `prc-block/form/formPostId` context propagates to nested form blocks and submissions tie back to the form CPT.

`Synced_Form::render_block_callback()` delegates to `Form_Renderer::render_by_id()`.

## Build

```bash
npx turbo build --filter=@prc/block-forms
```

## Dependencies

- Requires `prc-scripts` and should load before `@prc/block-library` (see `client-mu-plugins/plugin-loader.php`). Consumer plugins that register form actions (`prc-user-accounts`, `prc-email-builder`, `prc-user-surveys`, `prc-quiz-builder`) declare `prc-block-forms` in `Requires Plugins`.

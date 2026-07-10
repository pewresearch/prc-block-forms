# PRC Block Forms (`@prc/block-forms`)

Forms management for the PRC Platform:

- **`form` custom post type** — reusable, block-composed form definitions
- **Form blocks** — `prc-block/form`, `form-page`, `form-submit`, `form-message`, `form-captcha`, `form-message-bindings` (form-input-* blocks remain in `@prc/block-library`)
- **`prc-block/synced-form` block** — embed a saved form anywhere on the site
- **`prc-block-library/forms` data store** — form action registry consumed by the form block editor and downstream plugins
- **`Form_Renderer` PHP utility** — render a form CPT post by ID or slug from PHP (used by Content Gate, account pages, and other plugins)
- **Response logging** — custom `prc_form_responses` table with REST API and DataViews admin UI (`src/response-admin/`)
- **REST submission handlers** — `sendToEmail` and `logResponse` actions consumed by `prc-block/form`
- **Sample forms** — optional Forms CPT seeds (e.g. **Speaker Request (Sample)** at slug `speaker-request`) provisioned idempotently on `init` via `Form_Sample_Provisioner`; markup lives in `patterns/`

## Form blocks

| Block | Doc |
| --- | --- |
| `prc-block/form` | [`docs/form.md`](docs/form.md) |
| `prc-block/form-page` | [`docs/form-page.md`](docs/form-page.md) |
| `prc-block/form-submit` | [`docs/form-submit.md`](docs/form-submit.md) |
| `prc-block/form-message` | [`docs/form-message.md`](docs/form-message.md) |
| `prc-block/form-captcha` | [`docs/form-captcha.md`](docs/form-captcha.md) |
| `prc-block/form-message-bindings` | Editor-only binding registration companion |

## Sample forms

On first run (and on self-heal when the seed version bumps), `@prc/block-forms` creates editable sample form posts editors can duplicate or embed with the Synced Form block:

| Slug | Title | Pattern file |
| --- | --- | --- |
| `speaker-request` | Speaker Request (Sample) | `patterns/speaker-request-form.php` |

Sample forms are deletable — they are onboarding starters, not runtime dependencies. Update the form's **Redirect Target** (recipient email for `sendToEmail`) before going live.

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

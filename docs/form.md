# Form

A container block that wraps form input blocks into a functional, submittable form. Manages form state, field registration, submission flow, captcha integration, error handling, form persistence via localStorage, and conditional field display. Serves as the central orchestrator for all `form-input-*` child blocks.

## Block inserter example

`block.json` defines an `example` with name and email `form-input-text` fields, `form-submit`, and `form-message` containing a thank-you `core/paragraph`. This drives the block inserter preview.

## Namespace

`prc-block/form`

## Category

`common`

## Supports

| Feature                 | Value                                |
| ----------------------- | ------------------------------------ |
| Interactivity           | `true`                               |
| Color (text)            | `true`                               |
| Color (background)      | `true`                               |
| Color (link)            | `true`                               |
| Layout (type)           | `constrained` (contentSize: `420px`) |
| Spacing (margin)        | `true`                               |
| Spacing (padding)       | `true`                               |
| Spacing (blockGap)      | `true`                               |
| Typography (fontSize)   | `true`                               |
| Typography (lineHeight) | `true`                               |
| HTML                    | `false`                              |

## Attributes

| Attribute     | Type     | Default | Description                                                                          |
| ------------- | -------- | ------- | ------------------------------------------------------------------------------------ |
| `formName`    | `string` | `""`    | Human-readable name for the form, used for localStorage persistence keys.            |
| `method`      | `string` | `"api"` | Submission method. `"api"` uses the internal PRC API; `"rest"` uses a REST endpoint. |
| `namespace`   | `string` | `""`    | The REST namespace for the form action endpoint (used when method is `"rest"`).      |
| `action`      | `string` | `""`    | The registered form action identifier. Maps to a server-side handler.                |
| `actionConfig`| `object` | `{}`    | Per-action configuration (e.g. `forwardTo` for Contact Form, Mailchimp segment for `subscribe`). |
| `redirectUrl` | `string` | `""`    | **Deprecated.** Use per-action `actionConfig` instead (e.g. `actionConfig.redirectUrl` when the action sets `supportsRedirect`). |

## Block Variations

| Variation | Description |
| --------- | ----------- |
| **Newsletter Signup (Mailchimp)** | Pre-configured form using the `subscribe` API action with email, submit, captcha, and thank-you message fields. |
| **Newsletter Selection (Mailchimp)** | Multi-segment signup: visitors choose newsletters via checkboxes; uses the `subscribeSelect` API action and the `mailchimp-select` Mailchimp credential. |

## Available Styles

None defined.

## Inner Blocks

The form block accepts the following inner blocks:

| Block                                | Description                                                                                  |
| ------------------------------------ | -------------------------------------------------------------------------------------------- |
| `prc-block/form-input-text`          | Text, email, password, textarea, number, date, URL, tel, time, search, datetime-local inputs |
| `prc-block/form-input-select`        | Dropdown select with search, single or multi-select                                          |
| `prc-block/form-input-select-range`  | Paired min/max select inputs for range selection                                             |
| `prc-block/form-input-checkbox`      | Checkbox and radio inputs                                                                    |
| `prc-block/form-input-radio-group`   | Grouped radio buttons with mutual exclusion                                                  |
| `prc-block/form-input-range`         | Slider/range input with formatted output                                                     |
| `prc-block/form-input-password`      | Password input with optional strength analyzer and confirmation                              |
| `prc-block/form-captcha`             | Cloudflare Turnstile captcha widget                                                          |
| `prc-block/form-input-submit-button` | Form submit button                                                                           |
| `prc-block/form-message`             | Success/failure message display area                                                         |
| `core/group`                         | Layout container                                                                             |
| `core/columns`                       | Multi-column layout                                                                          |
| `core/column`                        | Individual column                                                                            |
| `core/paragraph`                     | Static text                                                                                  |
| `core/heading`                       | Section headings                                                                             |
| `core/separator`                     | Visual divider                                                                               |
| `core/spacer`                        | Vertical spacing                                                                             |
| `core/image`                         | Inline images                                                                                |

### Default Template

The block ships with a default template containing name, email, and message fields plus a submit button and message area.

## Parent / Ancestor Requirements

None. The form block is a top-level container. Note: the block registration logic prevents nesting forms inside other forms.

**Provides Context:**

| Context Key           | Description                                    |
| --------------------- | ---------------------------------------------- |
| `form/displayMessage` | Controls visibility of the form message block. |

## Usage Instructions

### Creating a Form

1. Insert the Form block. It pre-populates with a default template (name, email, message, submit, form message).
2. Set the **Form Name** in the inspector sidebar -- this identifies the form for persistence and submission.
3. Choose the **Method** (`API` or `REST`) depending on your backend handler.
4. Select an **Action** from the dropdown of registered form actions.
5. Configure **Action Settings** when the selected action provides them (e.g. Forward To for Contact Form, Redirect URL when the action supports post-submit redirect).

### Form Templates

When you select an **Action** that was registered with a `template` in the `prc-block-library/forms` store, the editor opens a **Use Form Template?** modal with three choices:

| Button | Behavior |
| ------ | -------- |
| **Use template** | Replaces the form's **inner blocks only** with blocks from the registered action template. The Form block itself stays in place; inspector settings (`formName`, `method`, `namespace`, `action`, `actionConfig`) are preserved. |
| **Use existing blocks** | Closes the modal and keeps the current inner blocks unchanged. |
| **Start blank** | Replaces inner blocks with the minimal base template (`BASE_TEMPLATE`) — a fresh starting layout without the registered action's full field set. |

Templates are applied with `replaceInnerBlocks` on the Form block's `clientId`, not by replacing the Form block node. That keeps the form container, attributes, and interactivity context intact while swapping field structure.

Registered forms may omit a custom template; in that case the editor falls back to `DEFAULT_FORM_TEMPLATE` when resolving the selected action.

**Registering a template** (from a consuming plugin):

```js
dispatch('prc-block-library/forms').registerForm({
  label: 'My Form',
  namespace: 'my-plugin/namespace',
  action: 'myAction',
  method: 'api', // or 'rest'
  template: [
    ['prc-block/form-input-text', { /* … */ }],
    ['prc-block/form-input-submit-button', {}],
    ['prc-block/form-message', {}],
  ],
});
```

Selecting that action in the Form block inspector triggers the template modal when `template` is non-empty.

### Conditional Field Display

Form inputs support conditional display via `formDisplayMode` and `formDisplayCondition` attributes. Fields can be shown/hidden based on the values of other fields in the form. The form block processes these conditions during rendering via `handle_conditional_form_field_display`.

### Form Persistence

Form field values are automatically persisted to localStorage with a 24-hour expiry. This is keyed by `formName` and restored on page load. The `FormPersistence` utility handles serialization and cleanup.

### Public form security (edge-cached pages)

Public-facing forms (contact email, Mailchimp subscribe, etc.) are rendered on VIP edge-cached pages. Page-baked WordPress nonces expire before a visitor submits, so **do not** treat `nonceToken` or `?nonce=` query args as the primary auth gate.

| Layer | Mechanism |
| ----- | --------- |
| Bot protection | Cloudflare Turnstile via `prc-block/form-captcha` (verified once server-side per submission) |
| Abuse throttling | `PRC\Platform\rate_limit_hit()` per IP and/or per recipient (see `class-form-send-email.php`) |
| User-scoped actions | Firebase `X-PRC-User-Id` / `X-PRC-User-Token` headers (datasets, user-accounts) |

The form render callback sets `nonceToken` to an empty string for public forms. Handlers that still accept a `nonceToken` field should treat it as optional legacy data.

### Form Field Panel

The inspector sidebar includes a **Form Fields** panel that lists all detected form input blocks within the form, providing an overview of the form structure.

## Block Markup Example

```html
<!-- wp:prc-block/form {"formName":"contact-form","method":"api","action":"contact"} -->
<form class="wp-block-prc-block-form">
	<!-- wp:prc-block/form-input-text {"type":"text","metadata":{"name":"fullName"}} -->
	<!-- /wp:prc-block/form-input-text -->

	<!-- wp:prc-block/form-input-text {"type":"email","metadata":{"name":"emailAddress"}} -->
	<!-- /wp:prc-block/form-input-text -->

	<!-- wp:prc-block/form-input-text {"type":"textarea","metadata":{"name":"message"}} -->
	<!-- /wp:prc-block/form-input-text -->

	<!-- wp:prc-block/form-input-submit-button /-->

	<!-- wp:prc-block/form-message /-->
</form>
<!-- /wp:prc-block/form -->
```

## PHP Rendering

The block uses server-side rendering via `render_form_callback` in `class-form.php`.

### Render Pipeline

1. **Block Registration**: Registers with `render_callback` pointing to `render_form_callback`.
2. **Render Callback**: Wraps the form in `data-wp-interactive="prc-block/form"` with a comprehensive context object:
    - `formId` (unique per instance)
    - `formName`, `method`, `namespace`, `action`, `actionConfig`
    - `errors` (empty array), `hasErrors` (false)
    - `captchaHidden` (true), `captchaToken` (null), `captchaPassed` (false)
    - `nonceName` / `nonceToken` (empty for public forms; user-accounts may override)
    - `isSubmitting`, `isSubmitted`, `isProcessing` (all false)
    - `formFields` (empty object, populated by child blocks at render)
    - `formPages` (for multi-page forms)
3. **Conditional Fields**: `handle_conditional_form_field_display` processes inner blocks with display conditions, adding appropriate `data-wp-bind--hidden` directives.
4. **Error Template**: Injects an error overlay `<div>` with `data-wp-bind--hidden` that shows validation errors.
5. **Processing Spinner**: Adds a spinner overlay shown during form submission.
6. **Jetpack Compat**: Disables Jetpack contact form module to prevent conflicts.

### Server-Side State Registration

Each form instance calls `wp_interactivity_state('prc-block/form', [...])` to register its initial state, making it available to the Interactivity API on the client.

## Frontend Interactivity

**Store Namespace:** `prc-block/form`

The form block's view module (`view/index.js`) is the central interactivity hub for the entire form system.

### State

| Key             | Type      | Description                                                             |
| --------------- | --------- | ----------------------------------------------------------------------- | ----------------------------------------- |
| `formFields`    | `object`  | Map of field names to their current values, registered by child blocks. |
| `isSubmitting`  | `boolean` | True while form submission is in progress.                              |
| `isSubmitted`   | `boolean` | True after successful submission.                                       |
| `isProcessing`  | `boolean` | True during async processing.                                           |
| `hasErrors`     | `boolean` | True when validation errors exist.                                      |
| `errors`        | `array`   | List of error message strings.                                          |
| `captchaHidden` | `boolean` | Controls captcha visibility.                                            |
| `captchaToken`  | `string   | null`                                                                   | Turnstile token after captcha completion. |
| `captchaPassed` | `boolean` | True after captcha verification.                                        |

### Actions

| Action                 | Description                                                                                          |
| ---------------------- | ---------------------------------------------------------------------------------------------------- |
| `onSubmit`             | Handles form submission. Validates fields, triggers captcha if present, then calls `sendSubmission`. |
| `onReset`              | Resets all form fields and state to initial values.                                                  |
| `onInputChange`        | Generic handler for text-like input changes. Updates the field value in `formFields`.                |
| `onInputRangeChange`   | Handler for range slider input changes with live value updates.                                      |
| `onInputCheckboxClick` | Handler for checkbox/radio click events. Toggles or sets the checked value.                          |

### Callbacks

| Callback           | Description                                                                                                                         |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------- |
| `onFormMount`      | Runs on form initialization. Restores persisted field values from localStorage.                                                     |
| `sendSubmission`   | Generator function that performs the actual API/REST submission. Handles success (redirect or message display) and error responses. |
| `onCaptchaPassing` | Watches `captchaPassed` and triggers `sendSubmission` when captcha completes.                                                       |

### Form Persistence

The `FormPersistence` utility class manages localStorage-based field persistence:

-   **Key format**: `prc-form-{formName}`
-   **Expiry**: 24 hours from first save
-   **Behavior**: Field values are saved on every change and restored on form mount. Expired data is automatically cleaned up.

## Forms CPT, Synced Forms, and Response Logging

### Forms CPT (`form`)

Forms can be saved once in the `form` custom post type (registered in `plugins/prc-block-forms/includes/class-forms.php`, top-level **Forms** admin menu) and reused across the site. The post type is not publicly queryable — forms render only where embedded — and its editor template is locked to a single `prc-block/form` block. Forms are action-agnostic: a form post can use any registered action (`sendToEmail`, `sendSystemEmail`, Mailchimp subscribe, `logResponse`, etc.).

### Synced Form block (`prc-block/synced-form`)

Embeds a form post by `ref` (form post ID). The editor shows a read-only `BlockPreview` with an "Edit form in isolation" toolbar link; the placeholder offers `WPEntitySearch` over the `form` post type plus a "Create New Form" flow. Server-side, `plugins/prc-block-forms/src/synced-form/class-synced-form.php` parses the form post's blocks and renders each through `WP_Block` with `prc-block/form/formPostId` context (the form block declares this in `usesContext`), so `render_form_callback` bakes `formPostId` into the interactivity context and submissions are tied back to their form.

### `sendToEmail` recipient resolution

The `sendToEmail` REST handler (`plugins/prc-block-forms/includes/class-form-send-email.php`) resolves the recipient from server-side configuration — client-supplied `forwardTo` is never trusted on its own.

| Context | Resolution |
| --- | --- |
| **Synced form / Forms CPT** (`formPostId` > 0) | Reads `actionConfig.forwardTo` from the saved form post's `prc-block/form` block. Client `forwardTo` / `forwardToSig` in the POST body are **ignored**. |
| **Inline form** (no `formPostId`) | Requires `actionConfig.forwardTo` **and** a matching `forwardToSig` HMAC (`Form_Send_Email::sign_forward_to()`, keyed with `wp_salt('auth')`). Unsigned or mismatched signatures return `400`. |

Draft form posts resolve `forwardTo` only for users who can edit that form (`read_post`); anonymous visitors get `missing_forward_to` if the CPT is not published.

Abilities that expose form definitions (`prc-block-forms/get-form`) redact `forwardTo` to a presence-only token — agents see that a recipient is configured, not the address.

### Response logging

Submissions are logged to the custom `{$wpdb->prefix}prc_form_responses` table:

| Piece | Location |
| ----- | -------- |
| Schema (versioned dbDelta) | `plugins/prc-block-forms/includes/class-form-response-schema.php` |
| Repository (insert/query/delete) | `plugins/prc-block-forms/includes/class-form-response-repository.php` |
| Logger + `logResponse` REST action | `plugins/prc-block-forms/includes/class-form-response-log.php` |
| Admin REST controller | `plugins/prc-block-forms/includes/class-form-responses-rest-controller.php` |
| DataViews admin page (Forms → Responses) | `plugins/prc-block-forms/includes/class-form-responses-admin.php` + `src/response-admin/` |
| Retention (365-day daily purge) | `plugins/prc-block-forms/includes/class-form-response-retention.php` |

- `sendToEmail` logs every submission automatically (`status: sent` / `send_failed`).
- The `logResponse` action (**Save Response** in the editor) stores submissions without sending email; it verifies Turnstile captcha and applies per-IP rate limiting (`prc_block_form_log_response_throttle` filter).
- `sendSystemEmail` (prc-email-builder) logs real sends (`sent` / `send_failed`, never dry runs) with the Mailchimp opt-in outcome as a `newsletter_signup_result` field; the Mailchimp `subscribe` action (prc-mailchimp mu-plugin, used by `mailchimp-form` and `mailchimp-select`) logs `subscribed` / `subscribe_failed` keyed by form name. `sendSurvey`, `createGroup`, and `deleteUser` are deliberately excluded (own persistence / entity creation / PII erasure requests).
- System fields (`captchaToken`, `nonceToken`) are stripped before storage.
- `prc_block_form_response_logging_enabled` filter disables logging; `prc_platform_form_response_logged` action fires after each row is written.
- Spam foldering: responses are classified at log time (link flooding via `prc_form_response_spam_link_threshold`, term blocklist via `prc_form_response_spam_terms`, Akismet when active, final verdict via `prc_form_response_is_spam`) and land in the Responses screen's Spam tab instead of being dropped; "Mark as spam" / "Not spam" bulk actions move rows between folders.
- Read/unread: new responses default to unread (`is_unread` schema v3). Forms → Responses supports Mark as read / Mark as unread actions, an Unread filter, and `X-PRC-Unread-Total`. REST: `POST /prc-api/v3/form/responses/unread` with `{ ids, is_unread }`. Viewing a response does **not** auto-mark it read.
- The Responses screen and its REST routes require `manage_options` by default (`prc_form_responses_capability` filter).
- Responses are retained for 365 days, spam for 30: a recurring Action Scheduler job (`prc_form_responses_retention_purge`) runs every morning and batch-deletes older rows. Adjust via the `prc_form_responses_retention_days` / `prc_form_responses_spam_retention_days` filters (`0` disables); the `prc_form_responses_purged` action fires after each run.

### Abilities API (VIP MCP)

`@prc/block-forms` registers Jetpack Forms–shaped abilities under `prc-block-forms/*` for agents:

| Ability | Purpose |
| --- | --- |
| `prc-block-forms/list-forms` | List form CPT posts + response counts |
| `prc-block-forms/get-form` | Form details + parsed `form-input-*` fields (`forwardTo` redacted) |
| `prc-block-forms/create-form` | Create form (optional block `content`; empty shell if omitted) |
| `prc-block-forms/delete-form` | Trash form CPT |
| `prc-block-forms/get-responses` | List/filter responses (spam folder, unread, search, dates) |
| `prc-block-forms/update-response` | Spam/inbox, unread toggle, or permanent delete (`trash`) |
| `prc-block-forms/bulk-update-responses` | Bulk spam / read / unread |
| `prc-block-forms/get-status-counts` | Inbox, spam, and unread counts |

Jetpack's `jetpack-forms/*` abilities are unregistered (and kept off REST/MCP) so agents use the PRC surface — parallel to disabling the `jetpack/contact-form` block. Extend the disabled slug list with `prc_block_forms_disabled_jetpack_abilities`.

## Related Blocks

| Block                                | Relationship                                  |
| ------------------------------------ | --------------------------------------------- |
| `prc-block/synced-form`              | Embeds a `form` post anywhere on the site     |
| `prc-block/form-input-text`          | Child input block for text-type fields        |
| `prc-block/form-input-select`        | Child input block for dropdown selects        |
| `prc-block/form-input-select-range`  | Child input block for paired min/max selects  |
| `prc-block/form-input-checkbox`      | Child input block for checkboxes and radios   |
| `prc-block/form-input-radio-group`   | Child container for grouped radio buttons     |
| `prc-block/form-input-range`         | Child input block for range sliders           |
| `prc-block/form-input-password`      | Child input block for password fields         |
| `prc-block/form-captcha`             | Child block for Cloudflare Turnstile captcha  |
| `prc-block/form-input-submit-button` | Child block for the submit button             |
| `prc-block/form-message`             | Child block for success/error message display |

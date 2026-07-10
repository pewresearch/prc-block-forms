# Form Block

The form block is a primitive `<form/>` element that can be used to create interactive forms. The block comes complete with block validation, blocks as form fields, and a registry for forms site-wide.

## Form Submission Methods

The form block supports two different submission methods: **API** and **REST**. Each method has different implementation requirements and use cases.

### API Method

The API method uses WordPress Interactivity API stores to handle form submissions client-side. This method is ideal for complex interactive forms that need immediate feedback or client-side processing.

**How it works:**
- Form submission is handled by actions in an interactivity store
- The store namespace must match the form's namespace
- Actions are called directly from the client-side JavaScript
- Provides immediate feedback without page reloads

**Implementation Requirements:**

1. **Register an Interactivity Store** with actions:
```js
import { store } from '@wordpress/interactivity';

const { actions } = store('my-plugin/namespace', {
  actions: {
    async myAction(formFields) {
      // Handle form submission logic here
      // formFields is an array of field objects with id, name, type, value, required, checked properties
      
      try {
        // Process the form data
        const result = await processFormData(formFields);
        
        // Return success response
        return {
          status: 'success',
          message: 'Form submitted successfully!',
          data: result,
          actionUrl: null // Optional redirect URL
        };
      } catch (error) {
        // Return error response
        return {
          status: 'error',
          message: error.message,
          actionUrl: null
        };
      }
    }
  }
});
```

2. **Register the form** with the API method:
```js
import { dispatch } from '@wordpress/data';

dispatch('prc-block-library/forms').registerForm({
  label: 'My Custom Form',
  description: 'A description of my form',
  namespace: 'my-plugin/namespace',
  action: 'myAction',
  method: 'api',
  template: [
    // ...block template array
  ],
});
```

### REST Method

The REST method uses WordPress REST API endpoints to handle form submissions server-side. This method is ideal for traditional form processing, email sending, database operations, and server-side validation.

**How it works:**
- Form submission sends data to a REST API endpoint
- Server-side PHP handles the processing
- Can integrate with WordPress functions, databases, email systems
- Follows WordPress REST API patterns

**Implementation Requirements:**

1. **Register REST Endpoints** in PHP:
```php
class My_Form_Handler {
    public function __construct($loader) {
        $loader->add_action('rest_api_init', $this, 'register_rest_endpoints');
    }

    public function register_rest_endpoints() {
        register_rest_route(
            'prc-api/v3',
            'form/my-action', // Accessible at /wp-json/prc-api/v3/form/my-action
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle_form_submission'),
                'args'                => array(
                    'nonce' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return is_string($param);
                        },
                    ),
                ),
                'permission_callback' => function() {
                    return true; // Adjust permissions as needed
                },
            )
        );
    }

    public function handle_form_submission($request) {
        // Public forms: verify Turnstile + rate limits — not page-baked nonces.
        // See class-form-send-email.php for the platform sendToEmail pattern.

        // Get form data
        $form_data = json_decode($request->get_body(), true);
        $form_fields = $form_data['formFields'] ?? array();
        $form_name = $form_data['formName'] ?? '';
        $action_config = $form_data['actionConfig'] ?? array();

        // Process form fields
        foreach ($form_fields as $field) {
            // Validate and process each field
            // $field contains: id, name, type, value, required, checked
        }

        // Return success response
        return new \WP_REST_Response(array(
            'message' => 'Form submission processed successfully'
        ), 200);
    }
}
```

2. **Register the form** with the REST method:
```js
import { dispatch } from '@wordpress/data';

dispatch('prc-block-library/forms').registerForm({
  label: 'My REST Form',
  description: 'A form that submits via REST API',
  namespace: 'my-plugin/namespace',
  action: 'myAction', // Will be converted to 'my-action' for the REST endpoint
  method: 'rest',
  template: [
    // ...block template array
  ],
});
```

**Important Notes for REST Method:**
- Action names are automatically converted from camelCase to hyphenated form (e.g., `sendToEmail` becomes `send-to-email`)
- REST endpoints are accessible at `/wp-json/prc-api/v3/form/{action-name}`
- **Public forms on edge-cached pages** do not rely on page-baked `wp_rest` nonces — they expire under VIP cache. Use Turnstile captcha (`prc-block/form-captcha`) and server-side rate limiting instead. The client may still send a `nonceToken` field for backward compatibility; handlers should not treat it as authoritative on public routes.
- Form data structure includes `formName`, `formId`, `actionConfig`, and `formFields`

## Form Field Data Structure

Both methods receive form fields in the same standardized format:

```js
{
  id: "unique-field-id",
  name: "field-name", // From metadata.name attribute
  type: "email|text|textarea|select|checkbox|etc",
  value: "field-value",
  required: true|false,
  checked: true|false|null // Only for checkbox/radio fields
}
```

## Response Format

Both methods should return responses in this format:

```js
{
  status: 'success'|'error'|'processing',
  message: 'Human readable message',
  data: {}, // Optional additional data
  actionUrl: 'https://redirect-url.com' // Optional redirect URL
}
```

## Registering Forms with the Data Store

### Registering a Form

To register a form, dispatch the `registerForm` action to the `prc-block-library/forms` store:

```js
import { dispatch } from '@wordpress/data';

dispatch('prc-block-library/forms').registerForm({
  label: 'My Custom Form',
  description: 'A description of my form',
  namespace: 'my-plugin/namespace',
  action: 'my-action',
  method: 'api', // or 'rest'
  template: [
    // ...block template innerblocks array
  ],
  // Optional per-action configuration shown in the form block inspector:
  configDefaults: {
    segmentId: '',
  },
  ConfigComponent: MyActionConfigComponent, // ({ config, setConfig, configDefaults }) => ...
  hidden: false, // Set true to hide from the action picker (e.g. deprecated registrations)
});
```

You can call this registration function in your plugin's initialization code.

### Retrieving Registered Forms

To retrieve all registered forms, use the selector in your component:

```js
import { useSelect } from '@wordpress/data';

const forms = useSelect(
  (select) => select('prc-block-library/forms').getForms(),
  []
);
```

This will return an array of all forms registered with the store.

### Per-action configuration

Registry entries may declare action-specific settings rendered in the form block inspector:

| Key | Type | Description |
| --- | --- | --- |
| `configDefaults` | `object` | Default values stored on the form block's `actionConfig` attribute |
| `ConfigComponent` | `React component` | Custom inspector UI receiving `{ config, setConfig, configDefaults }`. Falls back to a generic `TextControl` per key when omitted |
| `supportsRedirect` | `boolean` | When `true`, renders a shared "Redirect URL" field (`actionConfig.redirectUrl`) for post-submit navigation on API actions |
| `hidden` | `boolean` | When `true`, excluded from the action picker unless it matches the form's current action (for deprecated registrations) |

Configured values are serialized into `actionConfig` on the block and passed to the frontend via `data-wp-context`. REST handlers receive `actionConfig` in the POST body; API actions read `actionConfig.redirectUrl` for optional post-submit redirects.

**Common config keys by action:**

| Action | Config key | Purpose |
| --- | --- | --- |
| `sendToEmail` | `forwardTo` | Recipient email address |
| `sendSystemEmail` | `originUrl` | Submission origin URL for Mailchimp merge fields |
| `subscribe` | `interest` | Single Mailchimp segment ID |
| `subscribeSelect` | `interests` | Offered Mailchimp segments (checkbox inner blocks) |
| Actions with `supportsRedirect` | `redirectUrl` | Post-submit redirect URL |

### Notes
- Ensure the store is imported/registered before any forms are registered (typically by importing `src/form/store.js` in your entry point).
- The old `addFilter('prc-block-forms', ...)` and `applyFilters('prc-block-forms', [])` methods are no longer supported.
- Each form should have a unique combination of `namespace` and `action`.
- The form block inspector shows a **unified action picker** grouped by submission type (server REST vs in-page Interactivity API). Selecting an action sets `method`, `namespace`, and `action` together.
- **API actions** are listed globally so they remain selectable in the Forms CPT editor and synced-form contexts without a parent controller block.
- **REST actions** registered under `prc-block/form` are globally listed; other namespaces follow parent-scoping rules.
- **Server-method actions** remain scoped to their parent controller block namespace.

### Mailchimp newsletter opt-in (`sendSystemEmail`)

Forms using the **Send System Email** action can include a **Newsletter Signup** checkbox (`metadata.name: mailchimp_signup`). The segment is chosen in the block inspector; on submit the interest ID travels in the field `value` and `checked` state. See `plugins/prc-email-builder/README.md` for the server-side filter and response shape.

## Forms CPT and Synced Forms

Forms can be saved once in the **`form` custom post type** (Forms admin menu, `@prc/block-forms`) and embedded anywhere with the **`prc-block/synced-form`** block (`plugins/prc-block-forms`). The synced block stores a `ref` (form post ID), renders the form post's blocks server-side, and passes the form post ID down via the `prc-block/form/formPostId` block context so every submission is tied back to its form. Forms are action-agnostic — a form post can use any registered action.

## Response Logging

Form submissions are logged server-side to a custom `{$wpdb->prefix}prc_form_responses` table (see `plugins/prc-block-forms/includes/class-form-response-schema.php` and `class-form-response-repository.php`), and surfaced in the **Forms → Responses** DataViews admin screen.

Actions that log:

| Action | Handler | Statuses |
| --- | --- | --- |
| `sendToEmail` | `plugins/prc-block-forms/includes/class-form-send-email.php` | `sent`, `send_failed` |
| `logResponse` | `plugins/prc-block-forms/includes/class-form-response-log.php` | `received` |
| `sendSystemEmail` | prc-email-builder `Form_Send_System_Email` | `sent`, `send_failed` (dry runs never log; Mailchimp opt-in outcome recorded as a `newsletter_signup_result` field) |
| `subscribe` | prc-mailchimp mu-plugin via `prc-block/form` (`subscribe` action), deprecated `mailchimp-form`, and deprecated `mailchimp-select` blocks | `subscribed`, `subscribe_failed` (flat envelope — always logs by form name, no form CPT ID) |

Deliberately excluded: `sendSurvey` (prc-user-surveys has its own persistence), `createGroup`/`createGroupFromResults` (quiz groups are first-class entities), and `deleteUser` (logging erasure requests would retain PII against the requester's intent).

- Other action handlers can opt in by calling `\PRC\Platform\Block_Forms\Form_Response_Log::log( array $args )` — see `plugins/prc-block-forms/includes/class-form-response-log.php`.
- System fields (`captchaToken`, `nonceToken`) are stripped before storage.
- Disable logging with the `prc_block_form_response_logging_enabled` filter (it receives the log args, so per-action opt-out is possible); the `prc_platform_form_response_logged` action fires after each row is written.
- The Responses screen capability defaults to `manage_options`, filterable via `prc_form_responses_capability`.

### Spam foldering

Responses are classified at log time and foldered — never dropped — so false positives are recoverable from the Responses screen's **Spam** tab via the "Not spam" action ("Mark as spam" works in the other direction, both bulk-capable).

Built-in detection, all advisory and filterable:

- **Link flooding** — 3+ URLs across field values marks spam (`prc_form_response_spam_link_threshold` filter; `0` disables).
- **Term blocklist** — `prc_form_response_spam_terms` filter, empty by default, so editors can react to spam campaigns without a deploy.
- **Akismet** — when the Akismet plugin is active and configured, its `comment-check` verdict is consulted. Skipped silently otherwise.
- The final verdict runs through the `prc_form_response_is_spam` filter.

Spam is purged on a shorter window than the general retention policy — see below.

### Data retention

Logged responses are retained for **365 days** by default. A recurring Action Scheduler job (`prc_form_responses_retention_purge`, group `prc-block-forms`, visible in **Tools → Scheduled Actions**) runs every morning around 4:00 AM site time and hard-deletes older rows in batches (`plugins/prc-block-forms/includes/class-form-response-retention.php`). There is no automatic archive — export responses to CSV from the Responses screen before they age out if you need them long-term.

- Change the window with the `prc_form_responses_retention_days` filter; return `0` to keep responses indefinitely (this also unschedules the job).
- Spam-foldered responses are purged after **30 days** (`prc_form_responses_spam_retention_days` filter; `0` keeps spam on the general window). The spam pass runs in the same job, so it is also inactive while general retention is disabled.
- The `prc_form_responses_purged` action fires after each run with the deleted counts and retention windows.

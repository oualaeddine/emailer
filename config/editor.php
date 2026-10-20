<?php

/*
|--------------------------------------------------------------------------
| Email editor (docs/46-editor-spike.md §4, docs/45 §45.6.1 — WP-60)
|--------------------------------------------------------------------------
| `trust_client_html`: when the server-side MJML compiler (spatie/mjml-php +
| npm `mjml`) cannot run (no `node` binary, `mjml` package missing), the
| validation endpoint may fall back to the HTML compiled in the browser by
| grapesjs-mjml — after sanitisation. Off in production: the server must
| store **its** HTML. On by default only in the `testing` environment.
|
| `node_path`: optional directory holding the `node` binary, exported as
| MJML_NODE_PATH for spatie/mjml-php when node is not on PATH.
*/

return [
    'trust_client_html' => (bool) env('EDITOR_TRUST_CLIENT_HTML', env('APP_ENV') === 'testing'),

    'node_path' => env('MJML_NODE_PATH'),

    'max_mjml_bytes' => 1024 * 1024,
];

---
paths:
  - 'resources/js/**'
---

# Js

## Use deterministic locale formatting during SSR
Inertia renders pages on the server in Vite development mode. The server and browser can use different default locales (observed 50.000 versus 50,000), causing React hydration failures. Specify an explicit locale for numbers and dates rendered on both sides; do not rely on the runtime default.

## Preserve rejected input on filter visits
GET filter forms that render useForm errors must preserve page state on validation failure (preserveState: 'errors'). Inertia swaps the page before invoking the submitting form's onError; remounting on errors loses both entered filters and field messages. Successful filter visits should still receive fresh server state.

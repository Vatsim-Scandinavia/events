---
paths:
  - 'resources/js/**'
---

# Js

## Use deterministic locale formatting during SSR
Inertia renders pages on the server in Vite development mode. The server and browser can use different default locales (observed 50.000 versus 50,000), causing React hydration failures. Specify an explicit locale for numbers and dates rendered on both sides; do not rely on the runtime default.

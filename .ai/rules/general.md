---
paths:
  - '.devcontainer/**'
---

# General

## Keep Codespaces development on one origin
Codespaces uses the Nginx gateway on port 8080 for both Laravel and Vite (/__vite/), including WebSocket upgrades. Keep Vite internal: separate private forwarded origins can fail asset requests at GitHub's authentication boundary. Container URL and Vite base changes apply only to development; production builds retain the normal asset paths.

## Preserve the tunnel's original host
Codespaces can forward requests with Host localhost while X-Forwarded-Host contains the public hostname. The Nginx gateway must preserve that forwarded hostname for both Host and X-Forwarded-Host, falling back to the incoming Host for direct local access. Overwriting it with localhost breaks Laravel redirects and Boost browser logging after successful writes.

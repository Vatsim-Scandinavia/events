---
paths:
  - '.devcontainer/**'
---

# General

## Keep Codespaces development on one origin
Codespaces uses the Nginx gateway on port 8080 for both Laravel and Vite (/__vite/), including WebSocket upgrades. Keep Vite internal: separate private forwarded origins can fail asset requests at GitHub's authentication boundary. Container URL and Vite base changes apply only to development; production builds retain the normal asset paths.

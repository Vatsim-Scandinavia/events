---
paths:
  - 'app/**'
---

# App

## VATSIM identity owns the user account
The verified VATSIM CID is the user's primary key across all explicitly configured SSO providers. Never match or merge accounts by email or accept a CID from callback query/form input. Name, email, rating and organisational membership come from the provider; local password authentication is intentionally disabled. OAuth tokens on the user belong to the most recent sign-in provider and must stay encrypted and hidden from serialization.

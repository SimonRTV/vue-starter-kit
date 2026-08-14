---
paths:
  - resources/js/pages/settings/SidebarFooterLinks.vue
---

# Js Pages Settings

## Allow relative paths in the destination input
Use a text Input with inputmode=url for sidebar link destinations. Do not use type=url because native browser validation rejects valid internal paths such as /pages before the Inertia form can submit.

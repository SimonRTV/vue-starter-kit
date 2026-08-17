---
paths:
  - '{app/Console/Commands/MakeAdmin.php,app/Actions/Users/CreateAdministrator.php,tests/Feature/Console/Commands/MakeAdminTest.php,README.md}'
---

# Commands

## Provision trusted administrators through make:admin
Keep direct administrator provisioning in the interactive make:admin command. Reuse the normal profile and password rules, normalize email, mark the trusted CLI-created account verified, synchronize policy-defined permissions, assign the protected Administrator role, and record the creation event. Web-created accounts continue to use password setup invitations.

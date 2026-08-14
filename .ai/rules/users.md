---
paths:
  - '{app/Policies/UserPolicy.php,app/Policies/RolePolicy.php,app/Http/Requests/*UserRequest.php,app/Actions/Users/**,resources/js/components/users/UserForm.vue}'
---

# Users

## Bound role assignment by operator authority
Role changes require users.assign_roles. Protected Administrator changes additionally require users.assign_administrator and an Administrator actor. Operators may change only roles whose permissions they possess; transactional user actions must preserve at least one verified Administrator. The UI mirrors can_assign, while Form Requests and policies remain authoritative.

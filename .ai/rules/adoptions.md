---
paths:
  - 'app/Http/Controllers/Adoptions/**'
---

# Adoptions

## Adoption ownership transfers only at verified pickup
Approval schedules pickup and leaves the pet in_process. Only an admin validating the one-time pickup code may transfer owner_id, mark adopted, and publish the adoption post. Lock the pet before the adoption inside a transaction to prevent competing schedules or duplicate releases. Never expose pickup_code in general adoption serialization; verification_code is only included in the adopter's authenticated receiver dashboard.

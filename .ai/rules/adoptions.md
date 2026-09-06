---
paths:
  - 'app/Http/Controllers/Adoptions/**'
---

# Adoptions

## Adoption ownership transfers only at verified pickup
Approval schedules pickup and leaves the pet in_process. Only an admin validating the one-time pickup code may transfer owner_id, mark adopted, and publish the adoption post. Lock the pet before the adoption inside a transaction to prevent competing schedules or duplicate releases. Never expose pickup_code in general adoption serialization; verification_code is only included in the adopter's authenticated receiver dashboard.

## Rescheduling and care keep adoption ownership private
A pickup is initially scheduled with POST and rescheduled by an admin with PATCH, rotating its code; an adopter's reschedule request does not change the scheduled date. Cancellation invalidates the code and reopens the pet only before release. Health and support notes are private; donor lists must not serialize applicant contact or pickup details. Reminder dispatch is idempotent and queued email rechecks current adoption state.

## Pet adoption kind is not an adoption status
ownership_kind=adoption includes pets registered for donation and does not mean adopted. UI status labels must use petStatusLabel/status. can_adopt excludes guardian, in_process, adopted and pets owned by the viewer (including accepted caretakers); enforce the same rule while locking the pet in interest creation. Primary owners may delete pets of either ownership kind, but must cancel any scheduled pickup first.

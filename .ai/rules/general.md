---
paths:
  - composer.json
---

# General

## Keep the delivery runnable with database queues
The delivery uses QUEUE_CONNECTION=database and queue:work for default, ably, ably-messages, ably-notifications-messages and ably-notifications. Horizon was explicitly removed to keep the standard installation compatible with native Windows; do not reintroduce Horizon or require Redis for the documented setup.

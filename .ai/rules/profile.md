---
paths:
  - '{resources/js/stores/admin/AdminStore.js,resources/js/pages/admin/profile/Profile.vue}'
---

# Profile

## Apply the saved shell preference after config refresh
After saving the personal school-color choice, apply the authoritative PUT response to adminStore.config.user after loadConfig completes. Config requests are deduplicated and may be stale or fail, so they must not overwrite the user's newly saved semantic-primary versus school-color selection.

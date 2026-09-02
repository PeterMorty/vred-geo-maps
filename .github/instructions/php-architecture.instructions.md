---
description: "Use when editing PHP in VRED Geo Maps."
name: "PHP Architecture"
applyTo: "**/*.php"
---
# PHP Architecture Guidelines

- Prefer native WordPress APIs and the existing VRED Geo Maps data flow.
- Keep logic close to the owning class and code path.
- Preserve working behavior unless the task explicitly changes it.
- Do not duplicate location queries, settings resolution, marker inheritance or renderer behavior in parallel helpers.
- Reuse `Data`, `Renderer`, `Shortcode`, `Admin`, `Updater` and `Plugin` responsibilities instead of introducing overlapping services.
- Add helpers only when they remove real duplication or clarify a repeated responsibility.
- Avoid broad refactors and framework-style abstraction layers.
- Sanitize external input, validate expected values and escape output appropriately.
- Keep visible strings translatable with the real `vred-geo-maps` text domain.
- Do not change updater or release behavior from unrelated PHP tasks.

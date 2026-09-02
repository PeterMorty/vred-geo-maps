---
description: "Use when editing the self-hosted updater or manifest contract in VRED Geo Maps."
name: "Updater"
applyTo: ["includes/class-updater.php", "scripts/build-update-manifest.php", "vred-geo-maps.php"]
---
# Updater Guidelines

- Preserve the current self-hosted VRED update architecture and WordPress native update integration.
- Keep the manifest contract compatible with `class-updater.php` and `build-update-manifest.php`.
- Validate remote URLs and keep trusted-host restrictions intact unless the task explicitly changes update hosting.
- Preserve transient caching, cache invalidation and `plugins_api` integration unless the task targets those behaviors.
- Keep `Update URI` and `VRED_GEO_MAPS_UPDATE_URL` aligned with the actual update endpoint.
- Do not weaken sanitization of remote metadata, icons, banners or package URLs.
- Do not add licensing or authenticated-download complexity unless explicitly requested.
- When updater behavior changes, validate the manifest fields and the WordPress payload paths that consume them.

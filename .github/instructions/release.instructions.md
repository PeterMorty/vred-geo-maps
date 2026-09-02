---
description: "Use when editing VRED Geo Maps release workflows and packaging."
name: "Release"
applyTo: [".github/workflows/release-updates.yml", "scripts/build-update-manifest.php"]
---
# Release Guidelines

- Keep the installable ZIP limited to files required by the runtime plugin.
- Build the ZIP from explicit runtime copies; do not package repository metadata, `.github/`, build directories or unrelated development files.
- Keep `vred-geo-maps.php` header `Version:` and `VRED_GEO_MAPS_VERSION` synchronized for releases.
- The release workflow must continue generating `vred-geo-maps-v{version}.zip` and `vred-geo-maps.json`.
- Keep the update manifest URLs aligned with the configured VRED updates base URL.
- Do not commit generated ZIPs, manifests or temporary release artifacts.
- Release/deploy changes are separate from normal feature work; do not alter this flow unless explicitly requested.

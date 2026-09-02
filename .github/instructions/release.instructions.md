---
description: "Use when editing VRED Geo Maps release workflows and packaging."
name: "Release"
applyTo: [".github/workflows/release-updates.yml", "scripts/build-update-manifest.php"]
---
# Release Guidelines

- Keep the installable ZIP limited to files required by the runtime plugin.
- Build the ZIP from explicit runtime copies; do not package repository metadata, `.github/`, build directories or unrelated development files.
- Keep `vred-geo-maps.php` header `Version:` and `VRED_GEO_MAPS_VERSION` synchronized for every version bump.
- Normal implementation work uses a fourth numeric development component: `A.B.C` → `A.B.C.1` → `A.B.C.2` and so on.
- Increment that fourth component exactly once per implementation task that ends in commit and push.
- Do not change the first three components during normal development work.
- Base versions such as `1.0.1`, `1.1.0` or `2.0.0` are explicit release decisions; after one of those releases, the next normal change starts again at `.1`.
- The release workflow must continue generating `vred-geo-maps-v{version}.zip` and `vred-geo-maps.json` using the exact current plugin version, including the fourth component when present.
- Keep the update manifest URLs aligned with the configured VRED updates base URL.
- Do not commit generated ZIPs, manifests or temporary release artifacts.
- Release/deploy changes are separate from normal feature work; do not alter this flow unless explicitly requested.

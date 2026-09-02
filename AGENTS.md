# Project Guidelines

## Scope
- This repository is the VRED Geo Maps WordPress plugin.
- This file is the mandatory project entry point for normal Codex work.
- Use the current user request and the real repository state as the source of truth.
- Read files in `.github/instructions/` only when they directly match the task scope.
- Do not continue previous work automatically and do not inspect unrelated documentation or historical notes unless explicitly requested.
- Start from the nearest concrete file, class, method, setting, shortcode, frontend behavior, error, or workflow involved in the task.
- Run `git status -sb` before editing or relying on Git state.

## Core Architecture
- Plugin entry: `vred-geo-maps.php`.
- Bootstrap: `includes/class-plugin.php`.
- Data registration, persistence and defaults: `includes/class-data.php`.
- Admin UI: `includes/class-admin.php`.
- Shared frontend renderer and assets: `includes/class-renderer.php`.
- Shortcode integration: `includes/class-shortcode.php`.
- Updater: `includes/class-updater.php`.
- Update manifest builder: `scripts/build-update-manifest.php`.
- Frontend and admin assets live under `assets/`; vendored third-party map libraries live under `assets/vendor/`.
- Translations live under `languages/` with text domain `vred-geo-maps`.

## Change Strategy
- Make the smallest safe change that solves the requested behavior.
- Fix the controlling cause when possible instead of layering a workaround over it.
- Preserve working behavior unless the request explicitly changes it.
- Do not touch unrelated PHP, JavaScript, CSS, translations, updater behavior, release flow, labels, settings or layout.
- Avoid broad refactors, framework-style abstractions and unnecessary helpers.
- Reuse the existing renderer, data flow, settings and WordPress APIs instead of creating parallel paths.
- Do not add dependencies unless there is a clear requirement that cannot be solved cleanly with the current stack.

## WordPress And Frontend Rules
- WordPress native APIs first.
- Keep JavaScript vanilla; do not introduce jQuery by default.
- Use `const` by default and `let` only when reassignment is required.
- Sanitize external input and escape output appropriately.
- Validate URLs and keep custom SVG handling strict.
- Avoid `innerHTML` with external or user-controlled data when safe DOM construction is practical.
- Keep assets conditional: do not enqueue map, clustering, admin or other runtime assets without a real consumer.
- Preserve the shortcode-first architecture and the shared renderer.
- Keep Leaflet and clustering behavior compatible with the vendored runtime already used by the plugin.

## Formatting
- Code, comments, identifiers and commit messages in English.
- User-facing UI remains translatable; Spanish translations must stay synchronized when visible strings change.
- Use real TAB characters for indentation where the existing project style uses tabs.
- CSS must follow the repository CSS instruction file when CSS is touched.

## Analysis-Only Tasks
When the user explicitly asks only for analysis:
- inspect the real code and applicable instructions;
- do not edit files;
- do not change version;
- do not commit or push;
- return the cause/current implementation, affected flow, risks and the smallest recommended solution.

## Implementation Workflow
For normal implementation tasks:
1. Inspect the controlling code and only the applicable instruction files.
2. Make the minimum safe change.
3. Run the narrowest useful validation for the touched area.
4. Review `git diff` and `git status`.
5. Stage only files related to the task; never use `git add .` blindly.
6. Commit with a short English message.
7. Push the commit to the current working branch, normally `main`.
8. Do not inspect GitHub Actions, FTP completion or the remote WordPress site unless the user explicitly asks.

The user does not use a local WordPress installation. Local checks validate code only; the user validates real behavior on the server after push.

After a successful push, report concisely that the push was completed and that deployment/server behavior was not reviewed.

## Git Safety
- Never stage or commit unrelated files.
- Do not overwrite unrelated existing working-tree changes.
- Do not leave temporary ZIPs, manifests, build directories or generated task artifacts in the workspace.
- Do not check for `.git/index.lock` preventively.
- If Git explicitly fails because of `index.lock`, wait 5 seconds and retry the same command once. If it fails again, stop and report the exact error. Never delete the lock without explicit user instruction.

## Versioning And Releases
- Do not bump the plugin version just because a normal implementation task is committed and pushed unless the task or current repository instructions require it.
- When a version change is requested, keep the `Version:` header and `VRED_GEO_MAPS_VERSION` synchronized.
- Do not alter updater or release behavior unless the task explicitly concerns those areas.
- Release/update tasks must follow the matching `.github/instructions/` files.

## Validation
Run only checks relevant to the change, for example:
- `php -l` for touched PHP files;
- `node --check` for touched JavaScript files;
- JSON validation for changed JSON;
- gettext/translation compilation checks when translations change;
- `git diff --check` before commit;
- targeted checks for shortcode rendering, asset gating, clustering, settings persistence or updater behavior when those areas are touched.

Do not present static checks as proof that the remote site works; server validation belongs to the user after push.

## Final Response
Keep the result short and practical. Use this order:
1. change made;
2. validation run;
3. commit/push result;
4. remaining server-side check or risk, if any.

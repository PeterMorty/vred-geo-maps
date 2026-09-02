---
description: "Use when editing CSS in VRED Geo Maps."
name: "CSS Formatting"
applyTo: "assets/css/**/*.css"
---
# CSS Formatting Guidelines

- Use real TAB characters for indentation.
- Put one property per line.
- Leave a space before `{` and after `:`.
- Prefer this property order when practical: layout, positioning, margin, padding, width/height, typography, borders, background, transforms/transitions, interaction.
- Keep related properties together and avoid unnecessary blank lines.
- Do not add a trailing semicolon on the last property of a rule.
- Avoid `!important` unless third-party or inline specificity makes it genuinely necessary.
- Keep selectors scoped to VRED Geo Maps and do not broaden styles into WordPress, Leaflet or theme globals unless explicitly required.

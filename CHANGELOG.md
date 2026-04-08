# Changelog

All notable changes to Minorda will be documented in this file.

## 1.0.2 - 2026-04-08

- Added a front-end rule explainer that shows `Minimum quantity: X` near the add to basket form.
- Prefilled the single-product quantity input with the strictest matching minimum quantity rule.
- Added variation-aware front-end updates so the explainer and default quantity follow the selected variation rule.
- Kept lower quantities manually editable while leaving non-compliant adds blocked by the existing notice flow.
- Updated the plugin author to `Webrankers`.

## 1.0.1 - 2026-04-08

- Renamed the plugin from its working title to `Minorda`.
- Updated the plugin header, admin labels, and text domain to match the Minorda name.
- Re-ran PHP linting and the rule engine test after the rename.

## 1.0.0 - 2026-04-08

- Added the initial Minorda plugin structure and WooCommerce dependency bootstrap.
- Added admin rule management for product and taxonomy minimum rules.
- Added storefront add-to-cart enforcement and strictest-rule selection logic.
- Added a lightweight rule engine test script.

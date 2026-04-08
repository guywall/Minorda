=== Minorda ===
Contributors: gwall
Tags: woocommerce, minimum order, minimum quantity, product rules, cart rules
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Minorda lets store owners enforce minimum quantity or minimum value rules for specific WooCommerce products, categories, and product taxonomies.

== Description ==

Minorda adds a WooCommerce admin screen for managing minimum-purchase rules without editing products one by one.

Each rule can target:

* One or more specific products
* Product categories
* Terms from any registered taxonomy attached to WooCommerce products

Each rule can require:

* A minimum quantity
* A minimum matched subtotal
* Either threshold to pass when both are set

Storefront enforcement happens during add to cart. When multiple rules match, Minorda applies the strictest matching rule using the current plugin logic.

== Features ==

* Dedicated WooCommerce admin screen for rule management
* Multiple active rules at the same time
* Product search with AJAX lookup in admin
* Taxonomy-based targeting for categories and custom product taxonomies
* Enable, disable, edit, and delete actions for rules
* Product-page minimum quantity explainer near the add to basket area
* Product-page default quantity prefilled from the relevant minimum quantity rule
* Add-to-cart validation with customer-facing error notices
* Matched subtotal calculation excludes tax, shipping, and fees

== Installation ==

1. Copy the plugin files into your WordPress `wp-content/plugins/minorda` directory.
2. Activate WooCommerce if it is not already active.
3. Activate Minorda from the WordPress Plugins screen.
4. Open `WooCommerce > Minorda` to create your rules.

== Frequently Asked Questions ==

= How are overlapping rules handled? =

Minorda selects a single strictest rule for the attempted product. Quantity-based rules take priority over value-only rules, and the highest quantity minimum wins among quantity rules.

= Does the minimum value use the whole cart total? =

No. Only the subtotal of products matched by the selected rule is used.

= Can I target custom product taxonomies? =

Yes. Minorda supports any registered taxonomy attached to WooCommerce products and shown in the admin UI.

== Changelog ==

= 1.0.2 =

* Added a front-end minimum quantity explainer near the add to basket form.
* Prefills the default product quantity with the relevant minimum quantity.
* Keeps lower quantities editable while still blocking non-compliant add-to-cart attempts.
* Updated plugin author to Webrankers.

= 1.0.1 =

* Renamed the plugin to Minorda.
* Updated plugin branding and text domain to `minorda`.
* Confirmed syntax and rule engine tests after the rename.

= 1.0.0 =

* Initial release with rule management screen, product and taxonomy targeting, and add-to-cart minimum enforcement.

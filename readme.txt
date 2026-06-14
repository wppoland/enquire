=== Enquire - Product Enquiry Form for WooCommerce ===
Contributors: wppoland
Tags: woocommerce, product enquiry, ask a question, contact form, product question
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add an Ask a Question form to products that emails the store owner.

== Description ==

Enquire adds an accessible "Ask a question" button to your WooCommerce single product pages. When a shopper clicks it, a focus-trapped dialog opens with a simple form (name, email, message). On submit, the enquiry is emailed straight to you with the product name and link — so you can answer pre-sale questions and close more sales.

No enquiry data is stored in your database: submissions are emailed only. The customer's address is set as the email Reply-To, so you can reply directly from your inbox.

= Features =

* "Ask a question" button on single product pages, with configurable label and placement (before/after add to cart, or end of the product summary).
* Accessible dialog form built on the native `<dialog>` element — keyboard friendly, focus-managed, screen-reader labelled, and motion-reduced aware.
* Sends enquiries via `wp_mail()` to a configurable recipient (falls back to your site admin email), with the product name and permalink included.
* Inline success and error states — no page reload.
* Spam protection: nonce verification, a honeypot field, and a per-visitor rate limit.
* Choose which fields are required (name, email, message) and customise every label and message.
* WooCommerce submenu settings page (WooCommerce → Enquire) with inline help on every option.
* Loads its small CSS/JS only on product pages. Declares WooCommerce HPOS and Cart/Checkout Blocks compatibility.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/enquire`, or install via Plugins → Add New.
2. Activate it. WooCommerce must be active.
3. Go to WooCommerce → Enquire to set the recipient email, button label and form options.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. Enquire adds its button to WooCommerce single product pages and uses WooCommerce product data.

= Where are enquiries stored? =

Nowhere. Enquiries are emailed only — nothing is written to your database.

= Where do enquiries get sent? =

To the recipient address you set on the settings page. If you leave it empty, your site's admin email is used.

= How is spam handled? =

Each submission is verified with a nonce, screened with a honeypot field, and limited to one per visitor per short window.

== Screenshots ==

1. The "Ask a question" form on a single product page.
2. The WooCommerce → Enquire settings page.

== Changelog ==

= 0.1.0 =
* Initial release.

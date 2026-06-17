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

Enquire adds an "Ask a question" button to your WooCommerce single product pages. When a shopper clicks it, a dialog opens with a short form (name, email, message). On submit, the question is emailed to you along with the product name and a link to it, so you can reply before the sale.

Nothing is stored in your database. Each enquiry is sent by email, and the shopper's address is used as the Reply-To header so you can answer straight from your inbox.

The code is on GitHub at https://github.com/wppoland/enquire if you want to read it, report a bug, or send a patch.

= Features =

* "Ask a question" button on single product pages, with a label you can change, placed after the add-to-cart button.
* The form opens in a native `<dialog>`: it traps focus while open, labels itself for screen readers, and skips its animation when the visitor prefers reduced motion.
* Enquiries go out through `wp_mail()` to whichever address you set (or your site admin email if you leave it blank), with the product name and its permalink in the body.
* Success and error messages appear inline, so there is no page reload.
* Spam handling: nonce check, a honeypot field, and a 30-second rate limit per visitor.
* Pick which of name, email and message are required, and edit the button, field labels, success/error text and email subject.
* Settings live under WooCommerce → Enquire.
* The small CSS and JS load only on product pages. The plugin declares WooCommerce HPOS and Cart &amp; Checkout Blocks compatibility.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/enquire`, or install via Plugins → Add New.
2. Activate it. WooCommerce must be active.
3. Go to WooCommerce → Enquire to set the recipient email, button label and form options.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. Enquire adds its button to WooCommerce single product pages and uses WooCommerce product data.

= Where are enquiries stored? =

They are not. Each enquiry is emailed and nothing is written to your database.

= Where do enquiries get sent? =

To the recipient address you set on the settings page. If you leave it empty, your site's admin email is used.

= How is spam handled? =

Each submission is checked against a nonce and a hidden honeypot field, and a visitor can send at most one enquiry every 30 seconds.

= Can I customise the button label? =

Yes. Set the button text and form heading under **WooCommerce → Enquire**.

== Screenshots ==

1. The "Ask a question" form on a single product page.
2. The WooCommerce → Enquire settings page.

== External Services ==

Enquire does not connect to any external service. Form submissions are sent to your own site over `admin-ajax.php` and never leave your server. Each enquiry is delivered with your site's own `wp_mail()` (WordPress core mail), using whatever mailer your site already has. The plugin stores only its own settings (the `enquire_settings` option) and a schema marker (`enquire_db_version`), plus a short-lived transient used for the per-visitor rate limit; enquiry content itself is not written to the database.

== Changelog ==

= 0.1.0 =
* Initial release.

# Enquire — Product Enquiry Form for WooCommerce

Adds an accessible **"Ask a question"** button to WooCommerce single product
pages. Clicking it opens a focus-trapped `<dialog>` with a simple form (name,
email, message); on submit, the enquiry is emailed to the configured recipient
with the product name and link. No enquiry data is stored — submissions are
emailed only.

## Features

- "Ask a question" trigger on single product pages (configurable label + placement).
- Accessible native `<dialog>` form: keyboard friendly, focus-managed, SR-labelled, motion-reduced aware.
- Emails enquiries via `wp_mail()` with product context; customer address set as Reply-To.
- Inline success/error states, no page reload.
- Spam protection: nonce + honeypot + per-visitor rate limit.
- Configurable required fields, labels, messages and email subject.
- WooCommerce → Enquire settings page with inline help on every option.
- Loads CSS/JS only on product pages. Declares WooCommerce HPOS + Blocks compatibility.

## Architecture

- `enquire.php` — bootstrap. Declares WC compatibility, then boots on `init:0`
  and fires `do_action('enquire/booted', Plugin::instance())` from
  `Plugin::boot()`. PRO companions hook that action.
- `src/Plugin.php` — singleton + minimal DI container; resolves the services in
  `config/hooks.php` and calls `registerHooks()` on each.
- `src/Service/EnquiryService.php` — renders the trigger/form, handles the AJAX
  submission, validates, rate-limits and emails.
- `src/Admin/Settings.php` — the WooCommerce submenu settings page.
- `templates/enquiry-form.php` — the trigger + dialog markup.
- `assets/` — front-end and admin CSS/JS (hand-written, no build step).

## Development

```sh
composer install
composer cs        # PHPCS (WordPress security sniffs)
composer analyse   # PHPStan level 6
```

Self-contained: no runtime Composer dependencies.

## License

GPL-2.0-or-later.

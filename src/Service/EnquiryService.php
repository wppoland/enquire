<?php

declare(strict_types=1);

namespace Enquire\Service;

defined('ABSPATH') || exit;

use Enquire\Contract\HasHooks;

/**
 * Front-end enquiry feature: renders an "Ask a question" trigger and an
 * accessible form on single product pages, and handles the AJAX submission by
 * emailing the configured recipient with the product context.
 *
 * Security: every submission is guarded by a nonce, a honeypot field and a
 * per-visitor rate limit. All input is sanitised; all output is escaped. No
 * enquiry data is stored — the message is emailed only.
 */
final class EnquiryService implements HasHooks
{
    private const OPTION       = 'enquire_settings';
    private const AJAX_ACTION  = 'enquire_submit';
    private const NONCE_ACTION = 'enquire_submit';
    private const HONEYPOT     = 'enquire_hp';

    /** Minimum seconds between submissions from the same visitor. */
    private const RATE_LIMIT_WINDOW = 30;

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_after_add_to_cart_form', [$this, 'renderTrigger'], 10);

        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'handleSubmit']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [$this, 'handleSubmit']);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->settings()['enabled'] ?? false);
    }

    /**
     * Load the front-end assets on single product pages only.
     */
    public function enqueueAssets(): void
    {
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        wp_enqueue_style(
            'enquire',
            \Enquire\Plugin::instance()->url('assets/css/enquire.css'),
            [],
            \Enquire\VERSION,
        );

        wp_enqueue_script(
            'enquire',
            \Enquire\Plugin::instance()->url('assets/js/enquire.js'),
            [],
            \Enquire\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );

        $settings = $this->settings();

        wp_localize_script('enquire', 'enquireData', [
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'action'         => self::AJAX_ACTION,
            'nonce'          => wp_create_nonce(self::NONCE_ACTION),
            'successMessage' => (string) ($settings['success_message'] ?? ''),
            'errorMessage'   => (string) ($settings['error_message'] ?? ''),
        ]);
    }

    /**
     * Render the trigger button + the (dialog) form on the single product page.
     */
    public function renderTrigger(): void
    {
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        $product = $this->currentProduct();
        if (! $product instanceof \WC_Product) {
            return;
        }

        $this->renderTemplate('enquiry-form', [
            'product'  => $product,
            'settings' => $this->settings(),
            'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            'honeypot' => self::HONEYPOT,
        ]);
    }

    /**
     * AJAX handler. Validates, rate-limits and emails the enquiry, returning a
     * JSON success/error payload.
     */
    public function handleSubmit(): void
    {
        $settings = $this->settings();

        // Honeypot: a filled hidden field means a bot. Pretend success to avoid
        // signalling the trap, but send nothing.
        $honeypot = isset($_POST[self::HONEYPOT]) ? sanitize_text_field(wp_unslash((string) $_POST[self::HONEYPOT])) : '';
        if ($honeypot !== '') {
            wp_send_json_success(['message' => (string) ($settings['success_message'] ?? '')]);
        }

        if (! check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Your session expired. Please reload the page and try again.', 'plogins-enquire'),
            ], 400);
        }

        if ($this->isRateLimited()) {
            wp_send_json_error([
                'message' => __('Please wait a moment before sending another enquiry.', 'plogins-enquire'),
            ], 429);
        }

        $productId = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
        $product   = $productId > 0 ? wc_get_product($productId) : null;
        if (! $product instanceof \WC_Product) {
            wp_send_json_error([
                'message' => (string) ($settings['error_message'] ?? __('Sorry, something went wrong. Please try again.', 'plogins-enquire')),
            ], 400);
        }

        $name    = isset($_POST['enquire_name']) ? sanitize_text_field(wp_unslash((string) $_POST['enquire_name'])) : '';
        $email   = isset($_POST['enquire_email']) ? sanitize_email(wp_unslash((string) $_POST['enquire_email'])) : '';
        $message = isset($_POST['enquire_message']) ? sanitize_textarea_field(wp_unslash((string) $_POST['enquire_message'])) : '';

        /** @var array<string, mixed> $enquiry */
        $enquiry = [
            'name'    => $name,
            'email'   => $email,
            'message' => $message,
        ];

        /**
         * Allows add-ons to add sanitised metadata to the enquiry payload.
         *
         * @param array<string, mixed> $enquiry Sanitised enquiry fields.
         * @param \WC_Product          $product Product the enquiry is about.
         * @param array<string, mixed> $settings Free plugin settings.
         */
        $enquiry = (array) apply_filters('enquire/enquiry_payload', $enquiry, $product, $settings);

        $errors = $this->validate($settings, $name, $email, $message);
        /**
         * Allows add-ons to validate fields they add to the enquiry form.
         *
         * @param list<string>         $errors   Human-readable validation errors.
         * @param \WC_Product          $product  Product the enquiry is about.
         * @param array<string, mixed> $enquiry  Sanitised enquiry payload.
         * @param array<string, mixed> $settings Free plugin settings.
         */
        $errors = (array) apply_filters('enquire/validation_errors', $errors, $product, $enquiry, $settings);
        if ($errors !== []) {
            wp_send_json_error([
                'message' => implode(' ', $errors),
            ], 422);
        }

        $sent = $this->sendEmail($settings, $product, $enquiry);
        if (! $sent) {
            wp_send_json_error([
                'message' => (string) ($settings['error_message'] ?? __('Sorry, something went wrong. Please try again.', 'plogins-enquire')),
            ], 500);
        }

        $this->markRateLimit();

        /**
         * Fires after a product enquiry has been successfully emailed to the
         * store owner.
         *
         * Add-ons (e.g. Enquire Pro auto-reply) hook this to react to a sent
         * enquiry — for example, emailing a confirmation back to the shopper.
         *
         * @param \WC_Product          $product The product the enquiry is about.
         * @param array<string, mixed> $enquiry The sanitised submission, with
         *                                       keys: name, email, message.
         */
        do_action('enquire/enquiry_sent', $product, $enquiry);

        wp_send_json_success([
            'message' => (string) ($settings['success_message'] ?? __('Thanks! Your question has been sent.', 'plogins-enquire')),
        ]);
    }

    /**
     * Validate the submitted fields against the configured required-field rules.
     *
     * @param array<string, mixed> $settings
     * @return list<string> Human-readable validation messages.
     */
    private function validate(array $settings, string $name, string $email, string $message): array
    {
        $errors = [];

        if (! empty($settings['require_name']) && $name === '') {
            $errors[] = __('Please enter your name.', 'plogins-enquire');
        }

        if (! empty($settings['require_email'])) {
            if ($email === '') {
                $errors[] = __('Please enter your email address.', 'plogins-enquire');
            } elseif (! is_email($email)) {
                $errors[] = __('Please enter a valid email address.', 'plogins-enquire');
            }
        } elseif ($email !== '' && ! is_email($email)) {
            $errors[] = __('Please enter a valid email address.', 'plogins-enquire');
        }

        if (! empty($settings['require_message']) && $message === '') {
            $errors[] = __('Please enter your question.', 'plogins-enquire');
        }

        return $errors;
    }

    /**
     * Send the enquiry email to the configured recipient.
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $enquiry
     */
    private function sendEmail(array $settings, \WC_Product $product, array $enquiry): bool
    {
        $recipient = sanitize_email((string) ($settings['recipient'] ?? ''));
        if ($recipient === '' || ! is_email($recipient)) {
            $recipient = (string) get_option('admin_email');
        }

        $productName = $product->get_name();
        $subjectTpl  = (string) ($settings['email_subject'] ?? 'Product enquiry: {product}');
        $subject     = str_replace('{product}', $productName, $subjectTpl);

        $notProvided = __('(not provided)', 'plogins-enquire');
        $name         = isset($enquiry['name']) ? (string) $enquiry['name'] : '';
        $email        = isset($enquiry['email']) ? (string) $enquiry['email'] : '';
        $message      = isset($enquiry['message']) ? (string) $enquiry['message'] : '';
        $nameValue   = $name !== '' ? $name : $notProvided;
        $emailValue  = $email !== '' ? $email : $notProvided;

        $lines = [
            /* translators: %s: product name. */
            sprintf(__('New product enquiry for: %s', 'plogins-enquire'), $productName),
            (string) $product->get_permalink(),
            '',
            /* translators: %s: customer name. */
            sprintf(__('Name: %s', 'plogins-enquire'), $nameValue),
            /* translators: %s: customer email. */
            sprintf(__('Email: %s', 'plogins-enquire'), $emailValue),
            '',
            __('Message:', 'plogins-enquire'),
            $message !== '' ? $message : __('(no message)', 'plogins-enquire'),
        ];

        $body = implode("\n", $lines);

        $headers = [];
        if ($email !== '' && is_email($email)) {
            $fromName  = $name !== '' ? $name : $email;
            $headers[] = 'Reply-To: ' . $fromName . ' <' . $email . '>';
        }

        /** @var array{to?: string, subject?: string, body?: string, headers?: list<string>, attachments?: list<string>} $mail */
        $mail = [
            'to'          => $recipient,
            'subject'     => $subject,
            'body'        => $body,
            'headers'     => $headers,
            'attachments' => [],
        ];

        /**
         * Allows add-ons to adjust the outgoing store-owner email.
         *
         * @param array<string, mixed> $mail     Mail arguments for wp_mail().
         * @param \WC_Product          $product  Product the enquiry is about.
         * @param array<string, mixed> $enquiry  Sanitised enquiry payload.
         * @param array<string, mixed> $settings Free plugin settings.
         */
        $mail = (array) apply_filters('enquire/mail_args', $mail, $product, $enquiry, $settings);

        $sent = (bool) wp_mail(
            (string) ($mail['to'] ?? $recipient),
            (string) ($mail['subject'] ?? $subject),
            (string) ($mail['body'] ?? $body),
            isset($mail['headers']) && is_array($mail['headers']) ? $mail['headers'] : [],
            isset($mail['attachments']) && is_array($mail['attachments']) ? $mail['attachments'] : [],
        );

        /**
         * Fires after the store-owner enquiry email send attempt.
         *
         * @param bool                 $sent     Whether wp_mail() reported success.
         * @param array<string, mixed> $mail     Mail arguments used for wp_mail().
         * @param \WC_Product          $product  Product the enquiry is about.
         * @param array<string, mixed> $enquiry  Sanitised enquiry payload.
         * @param array<string, mixed> $settings Free plugin settings.
         */
        do_action('enquire/mail_sent', $sent, $mail, $product, $enquiry, $settings);

        return $sent;
    }

    /**
     * Whether the current visitor is within the rate-limit window.
     */
    private function isRateLimited(): bool
    {
        return get_transient($this->rateLimitKey()) !== false;
    }

    /**
     * Record a successful submission for the rate-limit window.
     */
    private function markRateLimit(): void
    {
        set_transient($this->rateLimitKey(), time(), self::RATE_LIMIT_WINDOW);
    }

    private function rateLimitKey(): string
    {
        $userId = get_current_user_id();
        $token  = $userId > 0 ? 'u' . $userId : 'ip' . md5($this->clientIp());

        return 'enquire_rl_' . $token;
    }

    private function clientIp(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
            : '';

        return $ip !== '' ? $ip : 'unknown';
    }

    private function currentProduct(): ?\WC_Product
    {
        $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;

        return $product instanceof \WC_Product ? $product : null;
    }

    /**
     * Stored settings merged over packaged defaults.
     *
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $stored = get_option(self::OPTION, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require ENQUIRE_DIR . 'config/defaults.php';

        return array_merge($defaults, $stored);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $template, array $context): void
    {
        $file = ENQUIRE_DIR . 'templates/' . $template . '.php';

        if (! is_readable($file)) {
            return;
        }

        extract($context, EXTR_SKIP);
        require $file;
    }
}

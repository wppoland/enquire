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
        add_action($this->placementHook(), [$this, 'renderTrigger'], $this->placementPriority());

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
                'message' => __('Your session expired. Please reload the page and try again.', 'enquire'),
            ], 400);
        }

        if ($this->isRateLimited()) {
            wp_send_json_error([
                'message' => __('Please wait a moment before sending another enquiry.', 'enquire'),
            ], 429);
        }

        $productId = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
        $product   = $productId > 0 ? wc_get_product($productId) : null;
        if (! $product instanceof \WC_Product) {
            wp_send_json_error([
                'message' => (string) ($settings['error_message'] ?? __('Sorry, something went wrong. Please try again.', 'enquire')),
            ], 400);
        }

        $name    = isset($_POST['enquire_name']) ? sanitize_text_field(wp_unslash((string) $_POST['enquire_name'])) : '';
        $email   = isset($_POST['enquire_email']) ? sanitize_email(wp_unslash((string) $_POST['enquire_email'])) : '';
        $message = isset($_POST['enquire_message']) ? sanitize_textarea_field(wp_unslash((string) $_POST['enquire_message'])) : '';

        $errors = $this->validate($settings, $name, $email, $message);
        if ($errors !== []) {
            wp_send_json_error([
                'message' => implode(' ', $errors),
            ], 422);
        }

        $sent = $this->sendEmail($settings, $product, $name, $email, $message);
        if (! $sent) {
            wp_send_json_error([
                'message' => (string) ($settings['error_message'] ?? __('Sorry, something went wrong. Please try again.', 'enquire')),
            ], 500);
        }

        $this->markRateLimit();

        wp_send_json_success([
            'message' => (string) ($settings['success_message'] ?? __('Thanks! Your question has been sent.', 'enquire')),
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
            $errors[] = __('Please enter your name.', 'enquire');
        }

        if (! empty($settings['require_email'])) {
            if ($email === '') {
                $errors[] = __('Please enter your email address.', 'enquire');
            } elseif (! is_email($email)) {
                $errors[] = __('Please enter a valid email address.', 'enquire');
            }
        } elseif ($email !== '' && ! is_email($email)) {
            $errors[] = __('Please enter a valid email address.', 'enquire');
        }

        if (! empty($settings['require_message']) && $message === '') {
            $errors[] = __('Please enter your question.', 'enquire');
        }

        return $errors;
    }

    /**
     * Send the enquiry email to the configured recipient.
     *
     * @param array<string, mixed> $settings
     */
    private function sendEmail(array $settings, \WC_Product $product, string $name, string $email, string $message): bool
    {
        $recipient = sanitize_email((string) ($settings['recipient'] ?? ''));
        if ($recipient === '' || ! is_email($recipient)) {
            $recipient = (string) get_option('admin_email');
        }

        $productName = $product->get_name();
        $subjectTpl  = (string) ($settings['email_subject'] ?? 'Product enquiry: {product}');
        $subject     = str_replace('{product}', $productName, $subjectTpl);

        $notProvided = __('(not provided)', 'enquire');
        $nameValue   = $name !== '' ? $name : $notProvided;
        $emailValue  = $email !== '' ? $email : $notProvided;

        $lines = [
            /* translators: %s: product name. */
            sprintf(__('New product enquiry for: %s', 'enquire'), $productName),
            (string) $product->get_permalink(),
            '',
            /* translators: %s: customer name. */
            sprintf(__('Name: %s', 'enquire'), $nameValue),
            /* translators: %s: customer email. */
            sprintf(__('Email: %s', 'enquire'), $emailValue),
            '',
            __('Message:', 'enquire'),
            $message !== '' ? $message : __('(no message)', 'enquire'),
        ];

        $body = implode("\n", $lines);

        $headers = [];
        if ($email !== '' && is_email($email)) {
            $fromName  = $name !== '' ? $name : $email;
            $headers[] = 'Reply-To: ' . $fromName . ' <' . $email . '>';
        }

        /**
         * Filter the enquiry email arguments before sending.
         *
         * @param array{to:string,subject:string,body:string,headers:list<string>} $mail
         * @param \WC_Product $product
         */
        $mail = apply_filters('enquire_email_args', [
            'to'      => $recipient,
            'subject' => $subject,
            'body'    => $body,
            'headers' => $headers,
        ], $product);

        return (bool) wp_mail(
            (string) $mail['to'],
            (string) $mail['subject'],
            (string) $mail['body'],
            $mail['headers'],
        );
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
     * WooCommerce hook the trigger renders on, derived from the placement setting.
     */
    private function placementHook(): string
    {
        $placement = (string) ($this->settings()['button_placement'] ?? 'after_add_to_cart');

        return match ($placement) {
            'before_add_to_cart' => 'woocommerce_before_add_to_cart_form',
            'after_summary'      => 'woocommerce_single_product_summary',
            default              => 'woocommerce_after_add_to_cart_form',
        };
    }

    private function placementPriority(): int
    {
        // For the summary placement, render near the bottom of the summary block.
        return (string) ($this->settings()['button_placement'] ?? '') === 'after_summary' ? 45 : 10;
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

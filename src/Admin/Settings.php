<?php

declare(strict_types=1);

namespace Enquire\Admin;

defined('ABSPATH') || exit;

use Enquire\Contract\HasHooks;

/**
 * Admin settings page registered as a WooCommerce submenu ("WooCommerce →
 * Enquire").
 *
 * Stores settings in the `enquire_settings` option (array): the master toggle,
 * recipient address, trigger button label/placement, required-field rules and
 * messaging. All output is escaped; all input is sanitised on save.
 */
final class Settings implements HasHooks
{
    private const OPTION = 'enquire_settings';
    private const PAGE   = 'enquire-settings';

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Load the settings-page CSS/JS only on the Enquire screen.
     */
    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'woocommerce_page_' . self::PAGE) {
            return;
        }

        wp_enqueue_style(
            'enquire-admin',
            \Enquire\Plugin::instance()->url('assets/css/admin.css'),
            [],
            \Enquire\VERSION,
        );

        wp_enqueue_script(
            'enquire-admin',
            \Enquire\Plugin::instance()->url('assets/js/admin.js'),
            [],
            \Enquire\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Enquire Settings', 'enquire'),
            __('Enquire', 'enquire'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::PAGE,
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
            ],
        );

        // The menu uses manage_woocommerce; align the options.php save capability
        // so shop managers (not just admins with manage_options) can save.
        add_filter(
            'option_page_capability_' . self::PAGE,
            static fn (): string => 'manage_woocommerce',
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = $this->settings();
        ?>
        <div class="wrap enquire-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="enquire-admin__intro">
                <span class="enquire-admin__intro-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" focusable="false">
                        <path fill="currentColor" d="M12 3C7 3 3 6.58 3 11c0 2.4 1.2 4.55 3.1 6.02L5 21l4.27-2.13c.86.21 1.78.32 2.73.32 5 0 9-3.58 9-8s-4-8-9-8Zm-1 11H9v-2h2v2Zm2.07-4.75-.9.92c-.5.5-.67.87-.67 1.58h-2v-.25c0-.7.28-1.34.78-1.84l1.24-1.26c.2-.2.31-.47.31-.77 0-.6-.49-1.08-1.1-1.08-.6 0-1.1.48-1.1 1.08H8.63C8.63 7.4 9.8 6.3 11.23 6.3c1.43 0 2.6 1.1 2.6 2.45 0 .54-.22 1.03-.76 1.5Z"/>
                    </svg>
                </span>
                <div class="enquire-admin__intro-text">
                    <h2><?php esc_html_e('Let shoppers ask about a product before they buy', 'enquire'); ?></h2>
                    <p><?php esc_html_e('Enquire adds an “Ask a question” button to your single product pages. Clicking it opens an accessible form (name, email, message) that emails you the question with the product details — no data is stored. Tune everything below; hover a “?” for a quick explanation.', 'enquire'); ?></p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <div class="enquire-admin__section">
                    <h2><?php esc_html_e('General', 'enquire'); ?></h2>
                    <p class="enquire-admin__section-intro"><?php esc_html_e('The master switch and where enquiries are delivered.', 'enquire'); ?></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Enable enquiries', 'enquire'); ?>
                                    <?php $this->helpTip('enabled', __('Master switch. When off, no button or form is shown and no front-end assets are loaded.', 'enquire')); ?>
                                </th>
                                <td>
                                    <label for="enquire_enabled">
                                        <input
                                            type="checkbox"
                                            id="enquire_enabled"
                                            name="<?php echo esc_attr(self::OPTION); ?>[enabled]"
                                            value="1"
                                            aria-describedby="enquire-tip-enabled"
                                            <?php checked((bool) ($settings['enabled'] ?? false), true); ?>
                                        />
                                        <?php esc_html_e('Show the “Ask a question” button on single product pages.', 'enquire'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="enquire_recipient"><?php esc_html_e('Recipient email', 'enquire'); ?></label>
                                    <?php $this->helpTip('recipient', __('Where enquiries are sent. Leave empty to use your site’s admin email address.', 'enquire')); ?>
                                </th>
                                <td>
                                    <input
                                        type="email"
                                        id="enquire_recipient"
                                        name="<?php echo esc_attr(self::OPTION); ?>[recipient]"
                                        value="<?php echo esc_attr((string) ($settings['recipient'] ?? '')); ?>"
                                        class="regular-text"
                                        placeholder="<?php echo esc_attr((string) get_option('admin_email')); ?>"
                                        aria-describedby="enquire-tip-recipient"
                                    />
                                    <p class="description"><?php esc_html_e('Enquiries are emailed here. The customer’s address is set as Reply-To so you can reply directly.', 'enquire'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="enquire-admin__section">
                    <h2><?php esc_html_e('Trigger button', 'enquire'); ?></h2>
                    <p class="enquire-admin__section-intro"><?php esc_html_e('How and where the enquiry button appears on the product page.', 'enquire'); ?></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php
                            $this->textRow('button_text', __('Button label', 'enquire'), __('Text on the trigger button.', 'enquire'), $settings, __('The clickable label, e.g. “Ask a question” or “Enquire now”.', 'enquire'));
                            ?>
                            <tr>
                                <th scope="row">
                                    <label for="enquire_button_placement"><?php esc_html_e('Button placement', 'enquire'); ?></label>
                                    <?php $this->helpTip('button_placement', __('Where the button is injected on the single product page. “After add to cart” suits most themes.', 'enquire')); ?>
                                </th>
                                <td>
                                    <?php $enquire_placement = (string) ($settings['button_placement'] ?? 'after_add_to_cart'); ?>
                                    <select id="enquire_button_placement" name="<?php echo esc_attr(self::OPTION); ?>[button_placement]" aria-describedby="enquire-tip-button_placement">
                                        <option value="after_add_to_cart" <?php selected($enquire_placement, 'after_add_to_cart'); ?>><?php esc_html_e('After the add-to-cart button', 'enquire'); ?></option>
                                        <option value="before_add_to_cart" <?php selected($enquire_placement, 'before_add_to_cart'); ?>><?php esc_html_e('Before the add-to-cart button', 'enquire'); ?></option>
                                        <option value="after_summary" <?php selected($enquire_placement, 'after_summary'); ?>><?php esc_html_e('At the end of the product summary', 'enquire'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Pick the spot that best fits your theme’s product layout.', 'enquire'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="enquire-admin__section">
                    <h2><?php esc_html_e('Form fields', 'enquire'); ?></h2>
                    <p class="enquire-admin__section-intro"><?php esc_html_e('Field labels and which fields a shopper must complete. Empty labels fall back to the built-in defaults.', 'enquire'); ?></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php
                            $this->textRow('form_title', __('Form title', 'enquire'), __('Heading shown at the top of the dialog.', 'enquire'), $settings, __('Announced to screen-reader users when the dialog opens, and shown as its heading.', 'enquire'));
                            $this->textRow('name_label', __('Name field label', 'enquire'), __('Label for the name input.', 'enquire'), $settings);
                            $this->textRow('email_label', __('Email field label', 'enquire'), __('Label for the email input.', 'enquire'), $settings);
                            $this->textRow('message_label', __('Message field label', 'enquire'), __('Label for the message textarea.', 'enquire'), $settings);
                            $this->textRow('submit_text', __('Submit button label', 'enquire'), __('Label for the send button.', 'enquire'), $settings);
                            $this->checkboxRow('require_name', __('Require name', 'enquire'), __('Make the name field required.', 'enquire'), $settings, __('When on, the form cannot be submitted without a name.', 'enquire'));
                            $this->checkboxRow('require_email', __('Require email', 'enquire'), __('Make the email field required.', 'enquire'), $settings, __('Recommended on so you can reply. A valid email format is always enforced when an address is entered.', 'enquire'));
                            $this->checkboxRow('require_message', __('Require message', 'enquire'), __('Make the message field required.', 'enquire'), $settings, __('When on, the form cannot be submitted with an empty message.', 'enquire'));
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="enquire-admin__section">
                    <h2><?php esc_html_e('Messaging', 'enquire'); ?></h2>
                    <p class="enquire-admin__section-intro"><?php esc_html_e('What shoppers see after submitting, and the email subject line.', 'enquire'); ?></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php
                            $this->textRow('success_message', __('Success message', 'enquire'), __('Shown after a successful submission.', 'enquire'), $settings, __('A friendly confirmation shown inline once the enquiry is sent.', 'enquire'));
                            $this->textRow('error_message', __('Error message', 'enquire'), __('Shown if the submission fails.', 'enquire'), $settings, __('Shown if sending fails (e.g. a mail error). Reassure the shopper they can try again.', 'enquire'));
                            $this->textRow('email_subject', __('Email subject', 'enquire'), __('Subject line of the enquiry email. Use {product} for the product name.', 'enquire'), $settings, __('The subject of the email you receive. {product} is replaced with the product name.', 'enquire'));
                            ?>
                        </tbody>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render an accessible "?" help affordance with a tooltip.
     */
    private function helpTip(string $key, string $text): void
    {
        $tipId = 'enquire-tip-' . $key;
        ?>
        <button
            type="button"
            class="enquire-help"
            data-enquire-tip="<?php echo esc_attr($tipId); ?>"
            aria-label="<?php esc_attr_e('More information', 'enquire'); ?>"
            aria-describedby="<?php echo esc_attr($tipId); ?>"
            title="<?php echo esc_attr($text); ?>"
        >?</button>
        <span class="enquire-help-tip" id="<?php echo esc_attr($tipId); ?>" role="tooltip" hidden><?php echo esc_html($text); ?></span>
        <?php
    }

    /**
     * Render a single checkbox row in the form-table.
     *
     * @param array<string, mixed> $settings
     */
    private function checkboxRow(string $key, string $label, string $help, array $settings, string $tip = ''): void
    {
        $id = 'enquire_' . $key;
        ?>
        <tr>
            <th scope="row">
                <?php echo esc_html($label); ?>
                <?php if ($tip !== '') {
                    $this->helpTip($key, $tip);
                } ?>
            </th>
            <td>
                <label for="<?php echo esc_attr($id); ?>">
                    <input
                        type="checkbox"
                        id="<?php echo esc_attr($id); ?>"
                        name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($key); ?>]"
                        value="1"
                        <?php if ($tip !== '') : ?>aria-describedby="<?php echo esc_attr('enquire-tip-' . $key); ?>"<?php endif; ?>
                        <?php checked((bool) ($settings[$key] ?? false), true); ?>
                    />
                    <?php echo esc_html($help); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    /**
     * Render a single text-input row in the form-table.
     *
     * @param array<string, mixed> $settings
     */
    private function textRow(string $key, string $label, string $help, array $settings, string $tip = ''): void
    {
        $id = 'enquire_' . $key;
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
                <?php if ($tip !== '') {
                    $this->helpTip($key, $tip);
                } ?>
            </th>
            <td>
                <input
                    type="text"
                    id="<?php echo esc_attr($id); ?>"
                    name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($key); ?>]"
                    value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>"
                    class="regular-text"
                    <?php if ($tip !== '') : ?>aria-describedby="<?php echo esc_attr('enquire-tip-' . $key); ?>"<?php endif; ?>
                />
                <p class="description"><?php echo esc_html($help); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Sanitises the submitted settings before save, preserving defaults for any
     * field not on the form.
     *
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }

        $defaults = $this->settings();

        $placement = isset($raw['button_placement']) ? sanitize_key((string) $raw['button_placement']) : 'after_add_to_cart';
        if (! in_array($placement, ['after_add_to_cart', 'before_add_to_cart', 'after_summary'], true)) {
            $placement = 'after_add_to_cart';
        }

        $recipient = isset($raw['recipient']) ? sanitize_email((string) $raw['recipient']) : '';

        $sanitized = array_merge($defaults, [
            'enabled'          => ! empty($raw['enabled']),
            'recipient'        => $recipient,
            'button_text'      => $this->sanitizeText($raw, 'button_text', $defaults),
            'button_placement' => $placement,
            'form_title'       => $this->sanitizeText($raw, 'form_title', $defaults),
            'name_label'       => $this->sanitizeText($raw, 'name_label', $defaults),
            'email_label'      => $this->sanitizeText($raw, 'email_label', $defaults),
            'message_label'    => $this->sanitizeText($raw, 'message_label', $defaults),
            'submit_text'      => $this->sanitizeText($raw, 'submit_text', $defaults),
            'require_name'     => ! empty($raw['require_name']),
            'require_email'    => ! empty($raw['require_email']),
            'require_message'  => ! empty($raw['require_message']),
            'success_message'  => $this->sanitizeText($raw, 'success_message', $defaults),
            'error_message'    => $this->sanitizeText($raw, 'error_message', $defaults),
            'email_subject'    => $this->sanitizeText($raw, 'email_subject', $defaults),
        ]);

        return (array) apply_filters('enquire_sanitize_settings', $sanitized, $raw);
    }

    /**
     * Sanitise a single text field, falling back to the packaged default when
     * the submitted value is empty.
     *
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $defaults
     */
    private function sanitizeText(array $raw, string $key, array $defaults): string
    {
        $value = isset($raw[$key]) ? sanitize_text_field((string) $raw[$key]) : '';

        return $value !== '' ? $value : (string) ($defaults[$key] ?? '');
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
}

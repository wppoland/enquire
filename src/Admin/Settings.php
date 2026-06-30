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
 * recipient address, trigger button label, required-field rules and messaging.
 * All output is escaped; all input is sanitised on save.
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
     * Load the settings-page CSS only on the Enquire screen.
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
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Enquire Settings', 'plogins-enquire'),
            __('Enquire', 'plogins-enquire'),
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
        $defaults = $this->defaults();
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
                    <h2><?php esc_html_e('Let shoppers ask about a product before they buy', 'plogins-enquire'); ?></h2>
                    <p><?php esc_html_e('Enquire adds an “Ask a question” button to your single product pages. Clicking it opens an accessible form (name, email, message) that emails you the question with the product details, no data is stored.', 'plogins-enquire'); ?></p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <div class="enquire-admin__section">
                    <h2><?php esc_html_e('General', 'plogins-enquire'); ?></h2>
                    <p class="enquire-admin__section-intro"><?php esc_html_e('The master switch and where enquiries are delivered.', 'plogins-enquire'); ?></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e('Enable enquiries', 'plogins-enquire'); ?></th>
                                <td>
                                    <label for="enquire_enabled">
                                        <input
                                            type="checkbox"
                                            id="enquire_enabled"
                                            name="<?php echo esc_attr(self::OPTION); ?>[enabled]"
                                            value="1"
                                            <?php checked((bool) ($settings['enabled'] ?? false), true); ?>
                                        />
                                        <?php esc_html_e('Show the “Ask a question” button on single product pages.', 'plogins-enquire'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="enquire_recipient"><?php esc_html_e('Recipient email', 'plogins-enquire'); ?></label>
                                </th>
                                <td>
                                    <input
                                        type="email"
                                        id="enquire_recipient"
                                        name="<?php echo esc_attr(self::OPTION); ?>[recipient]"
                                        value="<?php echo esc_attr((string) ($settings['recipient'] ?? '')); ?>"
                                        class="regular-text"
                                        placeholder="<?php echo esc_attr((string) get_option('admin_email')); ?>"
                                    />
                                    <p class="description"><?php esc_html_e('Enquiries are emailed here. Leave empty to use your site’s admin email. The customer’s address is set as Reply-To so you can reply directly.', 'plogins-enquire'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="enquire-admin__section">
                    <h2><?php esc_html_e('Trigger button', 'plogins-enquire'); ?></h2>
                    <p class="enquire-admin__section-intro"><?php esc_html_e('The enquiry button shown after the add-to-cart button on the product page. Clear any text field below to restore its packaged default (shown as the greyed hint).', 'plogins-enquire'); ?></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php
                            $this->textRow('button_text', __('Button label', 'plogins-enquire'), __('The wording shoppers click to open the enquiry form, e.g. “Ask a question” or “Enquire now”.', 'plogins-enquire'), $settings, $defaults);
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="enquire-admin__section">
                    <h2><?php esc_html_e('Form fields', 'plogins-enquire'); ?></h2>
                    <p class="enquire-admin__section-intro"><?php esc_html_e('The labels inside the enquiry dialog and which fields a shopper must complete before they can send. Clear any label to restore its packaged default.', 'plogins-enquire'); ?></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php
                            $this->textRow('form_title', __('Form title', 'plogins-enquire'), __('The heading at the top of the dialog, set the shopper’s expectation, e.g. “Ask a question about this product”.', 'plogins-enquire'), $settings, $defaults);
                            $this->textRow('name_label', __('Name field label', 'plogins-enquire'), __('Sits above the name input. Customers see this wording, not “name”.', 'plogins-enquire'), $settings, $defaults);
                            $this->textRow('email_label', __('Email field label', 'plogins-enquire'), __('Sits above the email input. This address becomes the Reply-To, so you can answer the shopper directly.', 'plogins-enquire'), $settings, $defaults);
                            $this->textRow('message_label', __('Message field label', 'plogins-enquire'), __('Sits above the message box, prompt the kind of question you want, e.g. “Your question”.', 'plogins-enquire'), $settings, $defaults);
                            $this->textRow('submit_text', __('Submit button label', 'plogins-enquire'), __('The wording on the send button inside the dialog.', 'plogins-enquire'), $settings, $defaults);
                            $this->checkboxRow('require_name', __('Require name', 'plogins-enquire'), __('Shoppers cannot send until they fill in the name field.', 'plogins-enquire'), $settings);
                            $this->checkboxRow('require_email', __('Require email', 'plogins-enquire'), __('Shoppers cannot send without an email. A valid format is always enforced when an address is entered, even if this is off, but leaving it off means you may not be able to reply.', 'plogins-enquire'), $settings);
                            $this->checkboxRow('require_message', __('Require message', 'plogins-enquire'), __('Shoppers cannot send an empty enquiry.', 'plogins-enquire'), $settings);
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="enquire-admin__section">
                    <h2><?php esc_html_e('Messaging', 'plogins-enquire'); ?></h2>
                    <p class="enquire-admin__section-intro"><?php esc_html_e('What shoppers see after submitting, and the subject line of the email you receive. Clear any field to restore its packaged default.', 'plogins-enquire'); ?></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php
                            $this->textRow('success_message', __('Success message', 'plogins-enquire'), __('Shown inline the moment an enquiry sends, reassure the shopper and set a reply expectation.', 'plogins-enquire'), $settings, $defaults);
                            $this->textRow('error_message', __('Error message', 'plogins-enquire'), __('Shown if the email cannot be sent (e.g. a server mail error). Keep it calm and ask them to retry.', 'plogins-enquire'), $settings, $defaults);
                            $this->textRow('email_subject', __('Email subject', 'plogins-enquire'), __('The subject line of the enquiry email you receive. {product} is replaced with the product name, so enquiries are easy to scan in your inbox.', 'plogins-enquire'), $settings, $defaults);
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
     * Render a single checkbox row in the form-table.
     *
     * @param array<string, mixed> $settings
     */
    private function checkboxRow(string $key, string $label, string $help, array $settings): void
    {
        $id = 'enquire_' . $key;
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <label for="<?php echo esc_attr($id); ?>">
                    <input
                        type="checkbox"
                        id="<?php echo esc_attr($id); ?>"
                        name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($key); ?>]"
                        value="1"
                        <?php checked((bool) ($settings[$key] ?? false), true); ?>
                    />
                    <?php echo esc_html($help); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    /**
     * Render a single text-input row in the form-table. The packaged default is
     * shown as the placeholder, so a merchant who clears a field can still see
     * what value will be used on save.
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $defaults
     */
    private function textRow(string $key, string $label, string $help, array $settings, array $defaults = []): void
    {
        $id      = 'enquire_' . $key;
        $default = (string) ($defaults[$key] ?? '');
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            </th>
            <td>
                <input
                    type="text"
                    id="<?php echo esc_attr($id); ?>"
                    name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($key); ?>]"
                    value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>"
                    <?php if ($default !== '') : ?>placeholder="<?php echo esc_attr($default); ?>"<?php endif; ?>
                    class="regular-text"
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

        $recipient = isset($raw['recipient']) ? sanitize_email((string) $raw['recipient']) : '';

        return array_merge($defaults, [
            'enabled'         => ! empty($raw['enabled']),
            'recipient'       => $recipient,
            'button_text'     => $this->sanitizeText($raw, 'button_text', $defaults),
            'form_title'      => $this->sanitizeText($raw, 'form_title', $defaults),
            'name_label'      => $this->sanitizeText($raw, 'name_label', $defaults),
            'email_label'     => $this->sanitizeText($raw, 'email_label', $defaults),
            'message_label'   => $this->sanitizeText($raw, 'message_label', $defaults),
            'submit_text'     => $this->sanitizeText($raw, 'submit_text', $defaults),
            'require_name'    => ! empty($raw['require_name']),
            'require_email'   => ! empty($raw['require_email']),
            'require_message' => ! empty($raw['require_message']),
            'success_message' => $this->sanitizeText($raw, 'success_message', $defaults),
            'error_message'   => $this->sanitizeText($raw, 'error_message', $defaults),
            'email_subject'   => $this->sanitizeText($raw, 'email_subject', $defaults),
        ]);
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

        return array_merge($this->defaults(), $stored);
    }

    /**
     * The packaged default settings.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require ENQUIRE_DIR . 'config/defaults.php';

        return $defaults;
    }
}

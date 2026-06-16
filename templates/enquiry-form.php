<?php
/**
 * Enquiry trigger button + dialog form for a single product.
 *
 * @package Enquire
 *
 * @var \WC_Product          $product  Current product.
 * @var array<string, mixed> $settings Resolved plugin settings.
 * @var string               $nonce    Submission nonce.
 * @var string               $honeypot Honeypot field name.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$enquire_button_text = (string) ($settings['button_text'] ?? __('Ask a question', 'enquire'));
$enquire_form_title  = (string) ($settings['form_title'] ?? __('Ask a question about this product', 'enquire'));
$enquire_name_label  = (string) ($settings['name_label'] ?? __('Your name', 'enquire'));
$enquire_email_label = (string) ($settings['email_label'] ?? __('Your email', 'enquire'));
$enquire_msg_label   = (string) ($settings['message_label'] ?? __('Your question', 'enquire'));
$enquire_submit_text = (string) ($settings['submit_text'] ?? __('Send enquiry', 'enquire'));

$enquire_req_name  = ! empty($settings['require_name']);
$enquire_req_email = ! empty($settings['require_email']);
$enquire_req_msg   = ! empty($settings['require_message']);

$enquire_dialog_id = 'enquire-dialog-' . (int) $product->get_id();
$enquire_title_id  = $enquire_dialog_id . '-title';
?>
<div class="enquire" data-enquire>
    <button
        type="button"
        class="enquire__trigger button"
        data-enquire-open
        aria-haspopup="dialog"
        aria-controls="<?php echo esc_attr($enquire_dialog_id); ?>"
    >
        <svg class="enquire__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M12 3C7 3 3 6.58 3 11c0 2.4 1.2 4.55 3.1 6.02L5 21l4.27-2.13c.86.21 1.78.32 2.73.32 5 0 9-3.58 9-8s-4-8-9-8Zm-1 11H9v-2h2v2Zm2.07-4.75-.9.92c-.5.5-.67.87-.67 1.58h-2v-.25c0-.7.28-1.34.78-1.84l1.24-1.26c.2-.2.31-.47.31-.77 0-.6-.49-1.08-1.1-1.08-.6 0-1.1.48-1.1 1.08H8.63C8.63 7.4 9.8 6.3 11.23 6.3c1.43 0 2.6 1.1 2.6 2.45 0 .54-.22 1.03-.76 1.5Z"/>
        </svg>
        <span class="enquire__trigger-text"><?php echo esc_html($enquire_button_text); ?></span>
    </button>

    <dialog
        id="<?php echo esc_attr($enquire_dialog_id); ?>"
        class="enquire__dialog"
        aria-labelledby="<?php echo esc_attr($enquire_title_id); ?>"
        data-enquire-dialog
    >
        <div class="enquire__panel" data-enquire-panel>
            <div class="enquire__header">
                <h2 class="enquire__title" id="<?php echo esc_attr($enquire_title_id); ?>"><?php echo esc_html($enquire_form_title); ?></h2>
                <button type="button" class="enquire__close" data-enquire-close aria-label="<?php esc_attr_e('Close', 'enquire'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <p class="enquire__product">
                <?php
                printf(
                    /* translators: %s: product name. */
                    esc_html__('About: %s', 'enquire'),
                    '<strong>' . esc_html($product->get_name()) . '</strong>'
                );
                ?>
            </p>

            <form class="enquire__form" data-enquire-form novalidate>
                <input type="hidden" name="action" value="enquire_submit" />
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />
                <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product->get_id()); ?>" />

                <?php // Honeypot: visually hidden, hidden from assistive tech. ?>
                <div class="enquire__hp" aria-hidden="true">
                    <label>
                        <?php esc_html_e('Leave this field empty', 'enquire'); ?>
                        <input type="text" name="<?php echo esc_attr($honeypot); ?>" tabindex="-1" autocomplete="off" />
                    </label>
                </div>

                <p class="enquire__field">
                    <label for="<?php echo esc_attr($enquire_dialog_id); ?>-name">
                        <?php echo esc_html($enquire_name_label); ?>
                        <?php if ($enquire_req_name) : ?><span class="enquire__required" aria-hidden="true">*</span><?php endif; ?>
                    </label>
                    <input
                        type="text"
                        id="<?php echo esc_attr($enquire_dialog_id); ?>-name"
                        name="enquire_name"
                        autocomplete="name"
                        <?php echo $enquire_req_name ? 'required' : ''; ?>
                    />
                </p>

                <p class="enquire__field">
                    <label for="<?php echo esc_attr($enquire_dialog_id); ?>-email">
                        <?php echo esc_html($enquire_email_label); ?>
                        <?php if ($enquire_req_email) : ?><span class="enquire__required" aria-hidden="true">*</span><?php endif; ?>
                    </label>
                    <input
                        type="email"
                        id="<?php echo esc_attr($enquire_dialog_id); ?>-email"
                        name="enquire_email"
                        autocomplete="email"
                        <?php echo $enquire_req_email ? 'required' : ''; ?>
                    />
                </p>

                <p class="enquire__field">
                    <label for="<?php echo esc_attr($enquire_dialog_id); ?>-message">
                        <?php echo esc_html($enquire_msg_label); ?>
                        <?php if ($enquire_req_msg) : ?><span class="enquire__required" aria-hidden="true">*</span><?php endif; ?>
                    </label>
                    <textarea
                        id="<?php echo esc_attr($enquire_dialog_id); ?>-message"
                        name="enquire_message"
                        rows="5"
                        <?php echo $enquire_req_msg ? 'required' : ''; ?>
                    ></textarea>
                </p>

                <div class="enquire__status" data-enquire-status role="status" aria-live="polite" hidden></div>

                <div class="enquire__actions">
                    <button type="submit" class="enquire__submit button alt" data-enquire-submit>
                        <?php echo esc_html($enquire_submit_text); ?>
                    </button>
                </div>
            </form>

            <?php // Dispatch seal: revealed by JS once the enquiry has been sent. ?>
            <div class="enquire__seal" data-enquire-seal aria-hidden="true">
                <span class="enquire__seal-mark">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M12 2.6c-2.5 0-4.5 1.7-4.7 4.1h2.5c.15-1.1 1.05-1.9 2.2-1.9 1.25 0 2.2.95 2.2 2.1 0 .9-.45 1.45-1.5 2.3-1.1.9-1.6 1.7-1.6 3.1v.5h2.4v-.4c0-.85.3-1.3 1.35-2.15C15.85 9.3 16.6 8.3 16.6 6.9 16.6 4.5 14.55 2.6 12 2.6Zm-1.45 14.1v2.7h2.9v-2.7h-2.9Z"/>
                    </svg>
                </span>
                <p class="enquire__seal-text" data-enquire-seal-text></p>
            </div>
        </div>
    </dialog>
</div>

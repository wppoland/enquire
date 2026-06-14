<?php
/**
 * Default settings, merged under the option key `enquire_settings`.
 *
 * The feature ships enabled. The merchant configures the recipient address, the
 * trigger button label/placement, which fields are required, and the success
 * message from the Enquire admin screen. No enquiry data is stored — submissions
 * are emailed only.
 *
 * @package Enquire
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'enabled' => true,

    // Where enquiries are sent. Empty falls back to the WordPress admin email.
    'recipient' => '',

    // Trigger button.
    'button_text'      => 'Ask a question',
    'button_placement' => 'after_add_to_cart',

    // Form chrome.
    'form_title'    => 'Ask a question about this product',
    'name_label'    => 'Your name',
    'email_label'   => 'Your email',
    'message_label' => 'Your question',
    'submit_text'   => 'Send enquiry',

    // Required fields. Each can be toggled independently.
    'require_name'    => true,
    'require_email'   => true,
    'require_message' => true,

    // Messaging.
    'success_message' => 'Thanks! Your question has been sent. We will get back to you shortly.',
    'error_message'   => 'Sorry, something went wrong. Please try again.',

    // Email subject line. {product} is replaced with the product name.
    'email_subject' => 'Product enquiry: {product}',
];

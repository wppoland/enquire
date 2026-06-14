<?php
/**
 * Uninstall cleanup for Enquire.
 *
 * Removes the plugin's own options when it is deleted from wp-admin. Only the
 * options Enquire creates are deleted; WooCommerce data is never touched. No
 * enquiry data is stored, so there is nothing else to clean up.
 *
 * @package Enquire
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('enquire_settings');
delete_option('enquire_db_version');

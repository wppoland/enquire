<?php
/**
 * Boot order: services listed here are resolved from the container and have
 * their registerHooks() called during Plugin::boot(). Each must implement
 * Enquire\Contract\HasHooks.
 *
 * @package Enquire
 *
 * @return array<class-string>
 */

declare(strict_types=1);

use Enquire\Admin\Settings;
use Enquire\Service\EnquiryService;

defined('ABSPATH') || exit;

return [
    EnquiryService::class,
    ...(is_admin() ? [Settings::class] : []),
];

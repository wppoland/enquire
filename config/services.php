<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container.
 *
 * @package Enquire
 */

declare(strict_types=1);

use Enquire\Admin\Settings;
use Enquire\Container;
use Enquire\Migrator;
use Enquire\Service\EnquiryService;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());

    // Front-end enquiry feature: trigger button, dialog form, AJAX submit + email.
    $c->singleton(EnquiryService::class, static fn (): EnquiryService => new EnquiryService());

    // Admin (only needed in wp-admin context).
    if (is_admin()) {
        $c->singleton(Settings::class, static fn (): Settings => new Settings());
    }
};

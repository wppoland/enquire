<?php
/**
 * Constants needed by PHPStan to analyse the plugin without bootstrapping WordPress.
 *
 * @package Enquire
 */

declare(strict_types=1);

namespace {
    if (! defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wordpress/');
    }
    if (! defined('ENQUIRE_DIR')) {
        define('ENQUIRE_DIR', '/tmp/enquire/');
    }
    if (! defined('ENQUIRE_URL')) {
        define('ENQUIRE_URL', 'https://example.test/wp-content/plugins/enquire/');
    }
}

namespace Enquire {
    if (! defined('Enquire\\VERSION')) {
        define('Enquire\\VERSION', '0.1.0');
    }
    if (! defined('Enquire\\PLUGIN_FILE')) {
        define('Enquire\\PLUGIN_FILE', '/tmp/enquire/enquire.php');
    }
}

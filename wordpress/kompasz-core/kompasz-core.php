<?php
/**
 * Plugin Name: Kompasz – ügynökség-toplisták
 * Description: Ügynökség bejegyzéstípus, toplista- és szolgáltatás-taxonómia, admin mezők, beállítások és adatimport a Kompasz oldalhoz.
 * Version: 1.0.0
 * Author: 360 Marketing
 * Text Domain: kompasz
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('KOMPASZ_CORE_FILE', __FILE__);
define('KOMPASZ_CORE_DIR', plugin_dir_path(__FILE__));
define('KOMPASZ_CORE_URL', plugin_dir_url(__FILE__));

require_once KOMPASZ_CORE_DIR . 'inc/types.php';
require_once KOMPASZ_CORE_DIR . 'inc/fields.php';
require_once KOMPASZ_CORE_DIR . 'inc/data.php';
require_once KOMPASZ_CORE_DIR . 'inc/settings.php';
require_once KOMPASZ_CORE_DIR . 'inc/import.php';

register_activation_hook(__FILE__, function () {
    kompasz_register_types();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

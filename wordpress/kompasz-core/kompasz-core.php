<?php
/**
 * Plugin Name: Kompasz – ügynökség-toplisták
 * Description: Ügynökség bejegyzéstípus, szolgáltatások (toplistákkal), adatbázis-listák, térkép, admin mezők, beállítások és adatimport a Kompasz oldalhoz.
 * Version: 1.5.0
 * Author: 360 Marketing
 * Text Domain: kompasz
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('KOMPASZ_CORE_VERSION', '1.5.0');
define('KOMPASZ_CORE_FILE', __FILE__);
define('KOMPASZ_CORE_DIR', plugin_dir_path(__FILE__));
define('KOMPASZ_CORE_URL', plugin_dir_url(__FILE__));

require_once KOMPASZ_CORE_DIR . 'inc/i18n.php';
require_once KOMPASZ_CORE_DIR . 'inc/types.php';
require_once KOMPASZ_CORE_DIR . 'inc/fields.php';
require_once KOMPASZ_CORE_DIR . 'inc/phone.php';
require_once KOMPASZ_CORE_DIR . 'inc/terms.php';
require_once KOMPASZ_CORE_DIR . 'inc/agency-edit.php';
require_once KOMPASZ_CORE_DIR . 'inc/google.php';
require_once KOMPASZ_CORE_DIR . 'inc/cf7.php';
require_once KOMPASZ_CORE_DIR . 'inc/migrate.php';
require_once KOMPASZ_CORE_DIR . 'inc/merge.php';
require_once KOMPASZ_CORE_DIR . 'inc/map.php';
require_once KOMPASZ_CORE_DIR . 'inc/ops.php';
require_once KOMPASZ_CORE_DIR . 'inc/cli.php';
require_once KOMPASZ_CORE_DIR . 'inc/data.php';
require_once KOMPASZ_CORE_DIR . 'inc/settings.php';
require_once KOMPASZ_CORE_DIR . 'inc/import.php';
require_once KOMPASZ_CORE_DIR . 'inc/accounts.php';
require_once KOMPASZ_CORE_DIR . 'inc/pages-content.php';
require_once KOMPASZ_CORE_DIR . 'inc/webp.php';
require_once KOMPASZ_CORE_DIR . 'inc/subscription.php';

register_activation_hook(__FILE__, function () {
    kompasz_register_types();
    kompasz_register_partner_role();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

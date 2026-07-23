<?php
/**
 * Plugin Name:       Alertas DT + SII Portal
 * Plugin URI:        https://github.com/wladimick/Plugin-WP-Alertas-DT-Tibox-
 * Description:       Suscripción, portal de clientes y API REST para sincronización con Alertas DT + SII.
 * Version:           0.3.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            External Group
 * License:           GPL-2.0-or-later
 * Text Domain:       alertas-dt-bridge
 */

defined( 'ABSPATH' ) || exit;

define( 'ADT_VERSION',                  '0.3.2' );
define( 'ADT_PLUGIN_FILE',              __FILE__ );
define( 'ADT_PLUGIN_DIR',               plugin_dir_path( __FILE__ ) );
define( 'ADT_PLUGIN_URL',               plugin_dir_url( __FILE__ ) );
define( 'ADT_TABLE',                    'alertas_dt_subscribers' );
define( 'ADT_SUBSCRIPTIONS_TABLE',      'alertas_dt_subscriptions' );
define( 'ADT_PAYMENTS_TABLE',           'alertas_dt_payments' );
define( 'ADT_EVENTS_TABLE',             'alertas_dt_events' );
define( 'ADT_ACTIVATION_TOKENS_TABLE',  'alertas_dt_activation_tokens' );

require_once ADT_PLUGIN_DIR . 'includes/class-adt-database.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-settings.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-subscriptions.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-payments.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-portal.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-account-activation.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-page-manager.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-roles.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-activator.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-shortcode.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-rest.php';
require_once ADT_PLUGIN_DIR . 'includes/class-adt-admin.php';

register_activation_hook( __FILE__, [ 'ADT_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'ADT_Activator', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    // Mantiene el esquema y las páginas del portal actualizadas incluso cuando
    // el plugin se reemplaza por archivos sin pasar por una reactivación.
    ADT_Activator::maybe_upgrade();

    ADT_Roles::register();
    ADT_Shortcode::register();
    ADT_Portal::register();
    ADT_Account_Activation::register();
    ADT_Page_Manager::register();
    ADT_REST::register();

    if ( is_admin() ) {
        ADT_Admin::register();
    }
} );

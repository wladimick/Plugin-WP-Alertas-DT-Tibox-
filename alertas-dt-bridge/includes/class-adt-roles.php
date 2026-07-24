<?php
defined( 'ABSPATH' ) || exit;

class ADT_Roles {

    public const CUSTOMER_ROLE = 'alertas_dt_customer';

    public static function register(): void {
        add_action( 'init', [ __CLASS__, 'ensure_role' ] );
        add_action( 'admin_init', [ __CLASS__, 'redirect_customer_admin' ] );
        add_filter( 'show_admin_bar', [ __CLASS__, 'hide_admin_bar_for_customers' ] );
    }

    public static function ensure_role(): void {
        if ( ! get_role( self::CUSTOMER_ROLE ) ) {
            add_role(
                self::CUSTOMER_ROLE,
                'Cliente Alertas DT',
                [
                    'read' => true,
                ]
            );
        }
    }

    public static function is_customer( ?WP_User $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        return $user && in_array( self::CUSTOMER_ROLE, (array) $user->roles, true );
    }

    public static function hide_admin_bar_for_customers( bool $show ): bool {
        return self::is_customer() ? false : $show;
    }

    public static function redirect_customer_admin(): void {
        if ( ! is_user_logged_in() || ! self::is_customer() ) {
            return;
        }

        if ( wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        global $pagenow;
        if ( in_array( $pagenow, [ 'admin-post.php', 'async-upload.php' ], true ) ) {
            return;
        }

        wp_safe_redirect( ADT_Portal::account_url() );
        exit;
    }
}

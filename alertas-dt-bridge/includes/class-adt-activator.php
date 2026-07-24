<?php
defined( 'ABSPATH' ) || exit;

class ADT_Activator {

    public static function activate(): void {
        ADT_Database::create_table();
        ADT_Account_Activation::create_table();
        ADT_Roles::ensure_role();
        ADT_Portal::ensure_pages();
        ADT_Account_Activation::ensure_page();
        ADT_Page_Manager::ensure_pages();

        if ( ! get_option( 'adt_api_token' ) ) {
            update_option( 'adt_api_token', ADT_Settings::generate_token(), false );
        }

        update_option( 'adt_plugin_version', ADT_VERSION );
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * dbDelta() es seguro de re-ejecutar: agrega tablas, columnas e índices
     * faltantes sin eliminar los datos existentes.
     */
    public static function maybe_upgrade(): void {
        $version_ok = ( get_option( 'adt_plugin_version' ) === ADT_VERSION );
        $schema_ok  = self::schema_is_current();

        if ( ! $version_ok || ! $schema_ok ) {
            ADT_Database::create_table();
            ADT_Account_Activation::create_table();
            ADT_Roles::ensure_role();
            ADT_Portal::ensure_pages();
            ADT_Account_Activation::ensure_page();
            ADT_Page_Manager::ensure_pages();
            update_option( 'adt_plugin_version', ADT_VERSION );
            flush_rewrite_rules( false );
        }
    }

    private static function schema_is_current(): bool {
        global $wpdb;

        $subscriber_table = ADT_Database::get_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$subscriber_table}" );
        if ( empty( $cols ) ) {
            return false;
        }

        $required_columns = [
            'email',
            'status',
            'subscriber_name',
            'phone',
            'whatsapp_consent',
            'wp_user_id',
            'notification_status',
        ];
        foreach ( $required_columns as $column ) {
            if ( ! in_array( $column, $cols, true ) ) {
                return false;
            }
        }

        foreach ( [
            ADT_Database::get_subscriptions_table(),
            ADT_Database::get_payments_table(),
            ADT_Database::get_events_table(),
            ADT_Account_Activation::table(),
        ] as $table ) {
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $found !== $table ) {
                return false;
            }
        }

        return true;
    }
}

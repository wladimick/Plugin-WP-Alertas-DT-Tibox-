<?php
defined( 'ABSPATH' ) || exit;

class ADT_Portal_QA {

    public static function register(): void {
        add_filter( 'pre_do_shortcode_tag', [ __CLASS__, 'protect_account_shortcode' ], 10, 4 );
        add_filter( 'do_shortcode_tag', [ __CLASS__, 'enhance_account_shortcode' ], 10, 4 );
        add_filter( 'rest_request_after_callbacks', [ __CLASS__, 'protect_rest_eligibility' ], 10, 3 );

        add_action( 'template_redirect', [ __CLASS__, 'route_authenticated_users' ], 5 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_brand_overrides' ], 30 );

        foreach ( [
            'adt_customer_profile',
            'adt_customer_notifications',
            'adt_customer_cancel',
            'adt_customer_undo_cancel',
            'adt_customer_simulate_payment',
        ] as $action ) {
            add_action( 'admin_post_' . $action, [ __CLASS__, 'guard_customer_post' ], 0 );
        }
    }

    /**
     * Evita que administradores u otros usuarios WordPress obtengan una prueba
     * solo por visitar el shortcode de Mi cuenta.
     */
    public static function protect_account_shortcode( $return, string $tag, array $attr, array $m ) {
        if ( 'alertas_dt_account' !== $tag || ! is_user_logged_in() || ADT_Roles::is_customer() ) {
            return $return;
        }

        $title   = current_user_can( 'manage_options' ) ? 'Cuenta administrativa' : 'Acceso no disponible';
        $message = current_user_can( 'manage_options' )
            ? 'Esta cuenta pertenece a la administración de WordPress y no participa en pruebas ni suscripciones de Alertas DT + SII. Usa un usuario de prueba con rol Cliente Alertas DT para validar el portal.'
            : 'Tu usuario de WordPress no está asociado a una cuenta de cliente de Alertas DT + SII.';

        return '<div class="adt-portal adt-portal--narrow">'
            . '<section class="adt-portal__card">'
            . '<p class="adt-portal__eyebrow">Portal de clientes</p>'
            . '<h2>' . esc_html( $title ) . '</h2>'
            . '<p>' . esc_html( $message ) . '</p>'
            . '<div class="adt-portal__actions">'
            . ( current_user_can( 'manage_options' )
                ? '<a class="adt-portal__button" href="' . esc_url( admin_url() ) . '">Volver al escritorio</a>'
                : '' )
            . '<a class="adt-portal__button adt-portal__button--secondary" href="' . esc_url( wp_logout_url( ADT_Portal::login_url() ) ) . '">Cerrar sesión</a>'
            . '</div></section></div>';
    }

    /**
     * Agrega el CTA comercial que faltaba en la tarjeta de suscripción.
     */
    public static function enhance_account_shortcode( string $output, string $tag, array $attr, array $m ): string {
        if ( 'alertas_dt_account' !== $tag || ! is_user_logged_in() || ! ADT_Roles::is_customer() ) {
            return $output;
        }

        $subscription = ADT_Subscriptions::get_for_user( get_current_user_id() );
        $status       = (string) ( $subscription['status'] ?? ADT_Subscriptions::STATUS_TRIALING );

        if ( in_array( $status, [ ADT_Subscriptions::STATUS_ACTIVE, ADT_Subscriptions::STATUS_CANCEL_AT_PERIOD_END ], true ) ) {
            return $output;
        }

        $cta = '<a class="adt-portal__button adt-portal__button--block adt-portal__button--subscribe" href="'
            . esc_url( ADT_Page_Manager::checkout_url() )
            . '">Contratar plan anual</a>';

        return preg_replace(
            '/<div class="adt-portal__actions">/',
            '<div class="adt-portal__actions">' . $cta,
            $output,
            1
        ) ?: $output;
    }

    /**
     * Un usuario autenticado no necesita volver a ver la portada o el login.
     */
    public static function route_authenticated_users(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $root_id     = absint( get_option( 'adt_portal_root_page_id' ) );
        $login_id    = absint( get_option( 'adt_login_page_id' ) );
        $register_id = absint( get_option( 'adt_register_page_id' ) );

        $route_ids = array_filter( [ $root_id, $login_id, $register_id ] );
        if ( $route_ids && is_page( $route_ids ) ) {
            wp_safe_redirect( ADT_Portal::account_url() );
            exit;
        }
    }

    public static function guard_customer_post(): void {
        if ( ! is_user_logged_in() || ! ADT_Roles::is_customer() ) {
            wp_die( esc_html__( 'Esta acción está disponible únicamente para clientes de Alertas DT + SII.', 'alertas-dt-bridge' ), '', [ 'response' => 403 ] );
        }
    }

    /**
     * Un usuario WordPress vinculado con un rol distinto de cliente nunca debe
     * quedar marcado como elegible para envíos desde la API.
     */
    public static function protect_rest_eligibility( $response, array $handler, WP_REST_Request $request ) {
        if ( '/alertas-dt/v1/subscribers' !== $request->get_route() || is_wp_error( $response ) ) {
            return $response;
        }

        $response = rest_ensure_response( $response );
        $data     = $response->get_data();
        if ( empty( $data['subscribers'] ) || ! is_array( $data['subscribers'] ) ) {
            return $response;
        }

        $eligible_only = rest_sanitize_boolean( $request->get_param( 'eligible_only' ) );
        $filtered      = [];

        foreach ( $data['subscribers'] as $subscriber ) {
            $wp_user_id = absint( $subscriber['wp_user_id'] ?? 0 );
            if ( $wp_user_id ) {
                $user        = get_userdata( $wp_user_id );
                $is_customer = $user instanceof WP_User
                    && in_array( ADT_Roles::CUSTOMER_ROLE, (array) $user->roles, true );

                if ( ! $is_customer ) {
                    $subscriber['eligible_for_alerts'] = false;
                    $subscriber['excluded_reason']      = 'invalid_wordpress_role';
                }
            }

            if ( ! $eligible_only || ! empty( $subscriber['eligible_for_alerts'] ) ) {
                $filtered[] = $subscriber;
            }
        }

        $data['subscribers'] = array_values( $filtered );
        $response->set_data( $data );
        return $response;
    }

    public static function enqueue_brand_overrides(): void {
        if ( is_admin() || ! is_singular() ) {
            return;
        }

        $page_ids = array_filter( [
            absint( get_option( 'adt_portal_root_page_id' ) ),
            absint( get_option( 'adt_register_page_id' ) ),
            absint( get_option( 'adt_activation_page_id' ) ),
            absint( get_option( 'adt_login_page_id' ) ),
            absint( get_option( 'adt_account_page_id' ) ),
            absint( get_option( 'adt_checkout_page_id' ) ),
            absint( get_option( 'adt_payment_result_page_id' ) ),
            absint( get_option( 'adt_recovery_page_id' ) ),
        ] );

        if ( $page_ids && is_page( $page_ids ) ) {
            wp_enqueue_style(
                'alertas-dt-portal-qa',
                ADT_PLUGIN_URL . 'assets/css/portal-qa.css',
                [ 'alertas-dt-portal' ],
                ADT_VERSION
            );
        }
    }
}

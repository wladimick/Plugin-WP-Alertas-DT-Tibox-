<?php
defined( 'ABSPATH' ) || exit;

class ADT_Page_Manager {

    private const OPTION_ROOT_PAGE           = 'adt_portal_root_page_id';
    private const OPTION_CHECKOUT_PAGE       = 'adt_checkout_page_id';
    private const OPTION_PAYMENT_RESULT_PAGE = 'adt_payment_result_page_id';
    private const OPTION_RECOVERY_PAGE       = 'adt_recovery_page_id';

    public static function register(): void {
        add_shortcode( 'alertas_dt_checkout', [ __CLASS__, 'render_checkout' ] );
        add_shortcode( 'alertas_dt_payment_result', [ __CLASS__, 'render_payment_result' ] );
        add_shortcode( 'alertas_dt_recovery', [ __CLASS__, 'render_recovery' ] );

        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_init', [ __CLASS__, 'ensure_pages' ] );
        add_action( 'template_redirect', [ __CLASS__, 'redirect_legacy_slugs' ] );
    }

    /**
     * Crea las páginas faltantes y migra las páginas existentes al árbol
     * /alertas-dt/... sin cambiar sus IDs ni borrar contenido personalizado.
     */
    public static function ensure_pages(): void {
        $root_id = self::ensure_page(
            self::OPTION_ROOT_PAGE,
            'Alertas DT + SII',
            'alertas-dt',
            '[alertas_dt_login]',
            0
        );

        if ( ! $root_id ) {
            return;
        }

        self::ensure_page( 'adt_register_page_id', 'Crear cuenta', 'crear-cuenta', '[alertas_dt_register]', $root_id );
        self::ensure_page( 'adt_activation_page_id', 'Activar cuenta', 'activar-cuenta', '[alertas_dt_activate_account]', $root_id );
        self::ensure_page( 'adt_login_page_id', 'Ingresar', 'ingresar', '[alertas_dt_login]', $root_id );
        self::ensure_page( 'adt_account_page_id', 'Mi cuenta', 'mi-cuenta', '[alertas_dt_account]', $root_id );
        self::ensure_page( self::OPTION_CHECKOUT_PAGE, 'Contratar plan', 'contratar', '[alertas_dt_checkout]', $root_id );
        self::ensure_page( self::OPTION_PAYMENT_RESULT_PAGE, 'Resultado del pago', 'resultado-pago', '[alertas_dt_payment_result]', $root_id );
        self::ensure_page( self::OPTION_RECOVERY_PAGE, 'Recuperar contraseña', 'recuperar-contrasena', '[alertas_dt_recovery]', $root_id );
    }

    public static function root_url(): string {
        return self::option_url( self::OPTION_ROOT_PAGE, '/alertas-dt/' );
    }

    public static function checkout_url(): string {
        return self::option_url( self::OPTION_CHECKOUT_PAGE, '/alertas-dt/contratar/' );
    }

    public static function payment_result_url(): string {
        return self::option_url( self::OPTION_PAYMENT_RESULT_PAGE, '/alertas-dt/resultado-pago/' );
    }

    public static function recovery_url(): string {
        return self::option_url( self::OPTION_RECOVERY_PAGE, '/alertas-dt/recuperar-contrasena/' );
    }

    public static function enqueue_assets(): void {
        if ( is_admin() || ! is_singular() ) {
            return;
        }

        global $post;
        if ( ! $post instanceof WP_Post ) {
            return;
        }

        foreach ( [ 'alertas_dt_checkout', 'alertas_dt_payment_result', 'alertas_dt_recovery' ] as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) ) {
                wp_enqueue_style(
                    'alertas-dt-portal',
                    ADT_PLUGIN_URL . 'assets/css/portal.css',
                    [],
                    ADT_VERSION
                );
                break;
            }
        }
    }

    public static function render_checkout(): string {
        if ( ! is_user_logged_in() ) {
            return self::card(
                'Contratar plan anual',
                'Debes iniciar sesión para contratar o revisar tu suscripción.',
                ADT_Portal::login_url(),
                'Iniciar sesión'
            );
        }

        $user_id      = get_current_user_id();
        $subscription = ADT_Subscriptions::get_for_user( $user_id );
        $status       = $subscription['status'] ?? ADT_Subscriptions::STATUS_TRIALING;
        $message      = 'La contratación mediante Webpay Plus se habilitará en la siguiente etapa. No se realizan cobros desde esta versión.';

        if ( in_array( $status, [ ADT_Subscriptions::STATUS_ACTIVE, ADT_Subscriptions::STATUS_CANCEL_AT_PERIOD_END ], true ) ) {
            $message = 'Ya tienes una cobertura vigente. Puedes revisar sus fechas y preferencias desde Mi cuenta.';
        }

        return self::card( 'Plan anual Alertas DT + SII', $message, ADT_Portal::account_url(), 'Volver a Mi cuenta' );
    }

    public static function render_payment_result(): string {
        return self::card(
            'Resultado del pago',
            'No existe una transacción para verificar. Esta página quedará conectada a la confirmación segura de Webpay Plus.',
            ADT_Portal::account_url(),
            'Ir a Mi cuenta'
        );
    }

    public static function render_recovery(): string {
        if ( is_user_logged_in() ) {
            return self::card(
                'Recuperar contraseña',
                'Tu sesión ya está iniciada. Puedes continuar en Mi cuenta.',
                ADT_Portal::account_url(),
                'Ir a Mi cuenta'
            );
        }

        $native_url = wp_lostpassword_url( ADT_Portal::login_url() );
        return self::card(
            'Recuperar contraseña',
            'WordPress enviará un enlace seguro al correo asociado a tu cuenta. Nunca enviaremos contraseñas por correo.',
            $native_url,
            'Solicitar enlace de recuperación'
        );
    }

    public static function redirect_legacy_slugs(): void {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        $path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
        $map  = [
            'registro-alertas-dt'       => ADT_Portal::register_url(),
            'activar-cuenta-alertas-dt' => ADT_Account_Activation::activation_url(),
            'ingresar-alertas-dt'       => ADT_Portal::login_url(),
            'mi-cuenta-alertas-dt'      => ADT_Portal::account_url(),
        ];

        if ( isset( $map[ $path ] ) ) {
            wp_safe_redirect( $map[ $path ], 301 );
            exit;
        }
    }

    private static function ensure_page( string $option, string $title, string $slug, string $shortcode, int $parent_id ): int {
        $page_id = absint( get_option( $option ) );
        $page    = $page_id ? get_post( $page_id ) : null;

        if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
            $path     = $parent_id ? 'alertas-dt/' . $slug : $slug;
            $existing = get_page_by_path( $path, OBJECT, 'page' );
            if ( $existing instanceof WP_Post ) {
                $page    = $existing;
                $page_id = (int) $existing->ID;
            }
        }

        if ( $page instanceof WP_Post ) {
            $update = [
                'ID'          => (int) $page->ID,
                'post_title'  => $title,
                'post_name'   => $slug,
                'post_parent' => $parent_id,
            ];

            $content = trim( (string) $page->post_content );
            if ( '' === $content || self::is_plugin_only_content( $content ) ) {
                $update['post_content'] = $shortcode;
            }

            $updated = wp_update_post( wp_slash( $update ), true );
            if ( is_wp_error( $updated ) ) {
                return 0;
            }

            update_option( $option, (int) $page->ID, false );
            return (int) $page->ID;
        }

        $page_id = wp_insert_post( wp_slash( [
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $shortcode,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_parent'  => $parent_id,
        ] ), true );

        if ( is_wp_error( $page_id ) ) {
            return 0;
        }

        update_option( $option, (int) $page_id, false );
        return (int) $page_id;
    }

    private static function is_plugin_only_content( string $content ): bool {
        return 1 === preg_match( '/^\[alertas_dt_[a-z_]+\]$/', trim( $content ) );
    }

    private static function option_url( string $option, string $fallback ): string {
        $page_id = absint( get_option( $option ) );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) {
                return $url;
            }
        }
        return home_url( $fallback );
    }

    private static function card( string $title, string $message, string $url, string $label ): string {
        return '<div class="adt-portal adt-portal--narrow">'
            . '<section class="adt-portal__card">'
            . '<p class="adt-portal__eyebrow">Portal de clientes</p>'
            . '<h2>' . esc_html( $title ) . '</h2>'
            . '<p>' . esc_html( $message ) . '</p>'
            . '<p><a class="adt-portal__button adt-portal__button--block" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p>'
            . '</section></div>';
    }
}

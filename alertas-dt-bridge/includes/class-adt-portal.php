<?php
defined( 'ABSPATH' ) || exit;

class ADT_Portal {

    private const OPTION_ACCOUNT_PAGE  = 'adt_account_page_id';
    private const OPTION_REGISTER_PAGE = 'adt_register_page_id';
    private const OPTION_LOGIN_PAGE    = 'adt_login_page_id';

    public static function register(): void {
        add_shortcode( 'alertas_dt_account', [ __CLASS__, 'render_account' ] );
        add_shortcode( 'alertas_dt_register', [ __CLASS__, 'render_register' ] );
        add_shortcode( 'alertas_dt_login', [ __CLASS__, 'render_login' ] );

        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_post_nopriv_adt_customer_register', [ __CLASS__, 'handle_register' ] );
        add_action( 'admin_post_adt_customer_profile', [ __CLASS__, 'handle_profile' ] );
        add_action( 'admin_post_adt_customer_notifications', [ __CLASS__, 'handle_notifications' ] );
        add_action( 'admin_post_adt_customer_cancel', [ __CLASS__, 'handle_cancel' ] );
        add_action( 'admin_post_adt_customer_undo_cancel', [ __CLASS__, 'handle_undo_cancel' ] );
        add_action( 'admin_post_adt_customer_simulate_payment', [ __CLASS__, 'handle_simulated_payment' ] );
    }

    public static function ensure_pages(): void {
        self::ensure_page(
            self::OPTION_ACCOUNT_PAGE,
            'Mi cuenta Alertas DT',
            'mi-cuenta-alertas-dt',
            '[alertas_dt_account]'
        );
        self::ensure_page(
            self::OPTION_REGISTER_PAGE,
            'Registro Alertas DT',
            'registro-alertas-dt',
            '[alertas_dt_register]'
        );
        self::ensure_page(
            self::OPTION_LOGIN_PAGE,
            'Ingresar a Alertas DT',
            'ingresar-alertas-dt',
            '[alertas_dt_login]'
        );
    }

    public static function account_url(): string {
        return self::page_url( self::OPTION_ACCOUNT_PAGE, '/mi-cuenta-alertas-dt/' );
    }

    public static function register_url(): string {
        return self::page_url( self::OPTION_REGISTER_PAGE, '/registro-alertas-dt/' );
    }

    public static function login_url(): string {
        return self::page_url( self::OPTION_LOGIN_PAGE, '/ingresar-alertas-dt/' );
    }

    public static function enqueue_assets(): void {
        if ( is_admin() || ! is_singular() ) {
            return;
        }
        global $post;
        if ( ! $post instanceof WP_Post ) {
            return;
        }
        $shortcodes = [ 'alertas_dt_account', 'alertas_dt_register', 'alertas_dt_login' ];
        foreach ( $shortcodes as $shortcode ) {
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

    public static function render_register(): string {
        if ( is_user_logged_in() ) {
            return self::notice( 'Ya tienes una sesión activa. Puedes administrar tu cuenta desde el portal.', 'success' )
                . '<p><a class="adt-portal__button" href="' . esc_url( self::account_url() ) . '">Ir a Mi cuenta</a></p>';
        }

        $notice = self::query_notice();
        ob_start();
        ?>
        <div class="adt-portal adt-portal--narrow">
            <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <section class="adt-portal__card">
                <p class="adt-portal__eyebrow">Prueba gratuita por <?php echo esc_html( ADT_Subscriptions::TRIAL_DAYS ); ?> días</p>
                <h2>Crea tu cuenta de Alertas DT + SII</h2>
                <p>Regístrate para administrar tu perfil, preferencias y futura suscripción anual.</p>

                <form class="adt-portal__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="adt_customer_register">
                    <?php wp_nonce_field( 'adt_customer_register' ); ?>

                    <label>
                        <span>Nombre</span>
                        <input type="text" name="display_name" required autocomplete="name">
                    </label>
                    <label>
                        <span>Correo electrónico</span>
                        <input type="email" name="email" required autocomplete="email">
                    </label>
                    <label>
                        <span>Teléfono</span>
                        <input type="tel" name="phone" autocomplete="tel" placeholder="+56 9 1234 5678">
                    </label>
                    <label>
                        <span>Contraseña</span>
                        <input type="password" name="password" required minlength="10" autocomplete="new-password">
                        <small>Usa al menos 10 caracteres.</small>
                    </label>
                    <label>
                        <span>Confirmar contraseña</span>
                        <input type="password" name="password_confirm" required minlength="10" autocomplete="new-password">
                    </label>
                    <label class="adt-portal__check">
                        <input type="checkbox" name="consent" value="1" required>
                        <span>Acepto recibir alertas informativas por email sobre publicaciones de la DT y del SII.</span>
                    </label>
                    <label class="adt-portal__check">
                        <input type="checkbox" name="whatsapp_consent" value="1">
                        <span>También acepto recibir alertas por WhatsApp al número indicado.</span>
                    </label>

                    <button class="adt-portal__button adt-portal__button--block" type="submit">Comenzar prueba gratuita</button>
                </form>
                <p class="adt-portal__fineprint">¿Ya tienes una cuenta? <a href="<?php echo esc_url( self::login_url() ); ?>">Inicia sesión</a>.</p>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_login(): string {
        if ( is_user_logged_in() ) {
            return '<div class="adt-portal adt-portal--narrow">'
                . self::notice( 'Sesión iniciada correctamente.', 'success' )
                . '<p><a class="adt-portal__button" href="' . esc_url( self::account_url() ) . '">Ir a Mi cuenta</a></p></div>';
        }

        ob_start();
        ?>
        <div class="adt-portal adt-portal--narrow">
            <?php echo self::query_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <section class="adt-portal__card">
                <p class="adt-portal__eyebrow">Acceso de clientes</p>
                <h2>Ingresa a Alertas DT + SII</h2>
                <?php
                wp_login_form( [
                    'redirect'       => self::account_url(),
                    'label_username' => 'Correo o usuario',
                    'label_password' => 'Contraseña',
                    'label_log_in'   => 'Ingresar',
                    'remember'       => true,
                ] );
                ?>
                <p class="adt-portal__links">
                    <a href="<?php echo esc_url( wp_lostpassword_url( self::login_url() ) ); ?>">Recuperar contraseña</a>
                    <span aria-hidden="true">·</span>
                    <a href="<?php echo esc_url( self::register_url() ); ?>">Crear cuenta</a>
                </p>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_account(): string {
        if ( ! is_user_logged_in() ) {
            return '<div class="adt-portal adt-portal--narrow">'
                . self::notice( 'Debes iniciar sesión para ver tu cuenta.', 'warning' )
                . '<p><a class="adt-portal__button" href="' . esc_url( self::login_url() ) . '">Iniciar sesión</a></p></div>';
        }

        $user         = wp_get_current_user();
        $subscriber   = ADT_Database::find_subscriber_by_user( (int) $user->ID );
        $subscription = ADT_Subscriptions::get_for_user( (int) $user->ID );

        if ( ! $subscription ) {
            $subscription = ADT_Subscriptions::ensure_trial( (int) $user->ID, (int) ( $subscriber['id'] ?? 0 ) );
        }

        $payments           = ADT_Database::list_payments( (int) $user->ID );
        $status             = (string) ( $subscription['status'] ?? ADT_Subscriptions::STATUS_TRIALING );
        $access_end         = ADT_Subscriptions::access_end_at( $subscription );
        $notification       = (string) ( $subscriber['notification_status'] ?? 'active' );
        $days_remaining     = self::days_remaining( $access_end );
        $simulated_enabled  = ADT_Payments::simulated_enabled();
        $is_cancel_pending  = ! empty( $subscription['cancel_at_period_end'] );

        ob_start();
        ?>
        <div class="adt-portal">
            <?php echo self::query_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <header class="adt-portal__header">
                <div>
                    <p class="adt-portal__eyebrow">Mi cuenta Alertas DT + SII</p>
                    <h1>Hola, <?php echo esc_html( $user->display_name ?: $user->user_email ); ?></h1>
                    <p><?php echo esc_html( $user->user_email ); ?></p>
                </div>
                <a class="adt-portal__button adt-portal__button--secondary" href="<?php echo esc_url( wp_logout_url( self::login_url() ) ); ?>">Cerrar sesión</a>
            </header>

            <div class="adt-portal__summary-grid">
                <section class="adt-portal__card">
                    <span class="adt-portal__label">Estado</span>
                    <strong class="adt-portal__status adt-portal__status--<?php echo esc_attr( $status ); ?>">
                        <?php echo esc_html( ADT_Subscriptions::status_label( $status ) ); ?>
                    </strong>
                </section>
                <section class="adt-portal__card">
                    <span class="adt-portal__label">Acceso hasta</span>
                    <strong><?php echo esc_html( self::format_date( $access_end ) ); ?></strong>
                    <?php if ( null !== $days_remaining ) : ?>
                        <small><?php echo esc_html( max( 0, $days_remaining ) ); ?> días restantes</small>
                    <?php endif; ?>
                </section>
                <section class="adt-portal__card">
                    <span class="adt-portal__label">Notificaciones</span>
                    <strong><?php echo esc_html( self::notification_label( $notification ) ); ?></strong>
                </section>
            </div>

            <div class="adt-portal__grid">
                <section class="adt-portal__card">
                    <h2>Perfil</h2>
                    <form class="adt-portal__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="adt_customer_profile">
                        <?php wp_nonce_field( 'adt_customer_profile' ); ?>
                        <label>
                            <span>Nombre</span>
                            <input type="text" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" required>
                        </label>
                        <label>
                            <span>Correo</span>
                            <input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" disabled>
                            <small>El cambio de correo se habilitará en una etapa posterior.</small>
                        </label>
                        <label>
                            <span>Teléfono</span>
                            <input type="tel" name="phone" value="<?php echo esc_attr( $subscriber['phone'] ?? get_user_meta( $user->ID, 'adt_phone', true ) ); ?>">
                        </label>
                        <button class="adt-portal__button" type="submit">Guardar perfil</button>
                    </form>
                </section>

                <section class="adt-portal__card">
                    <h2>Preferencias de notificación</h2>
                    <form class="adt-portal__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="adt_customer_notifications">
                        <?php wp_nonce_field( 'adt_customer_notifications' ); ?>
                        <label>
                            <span>Estado de correos</span>
                            <select name="notification_status">
                                <option value="active" <?php selected( $notification, 'active' ); ?>>Activas</option>
                                <option value="paused" <?php selected( $notification, 'paused' ); ?>>Pausadas temporalmente</option>
                                <option value="opted_out" <?php selected( $notification, 'opted_out' ); ?>>No deseo recibir notificaciones</option>
                            </select>
                        </label>
                        <label class="adt-portal__check">
                            <input type="checkbox" name="whatsapp_consent" value="1" <?php checked( ! empty( $subscriber['whatsapp_consent'] ) ); ?>>
                            <span>Recibir también alertas por WhatsApp.</span>
                        </label>
                        <button class="adt-portal__button" type="submit">Guardar preferencias</button>
                    </form>
                </section>
            </div>

            <section class="adt-portal__card adt-portal__subscription">
                <div>
                    <h2>Suscripción</h2>
                    <p>La prueba gratuita dura <?php echo esc_html( ADT_Subscriptions::TRIAL_DAYS ); ?> días. El futuro pago anual manual habilitará 12 meses de acceso.</p>
                    <?php if ( $is_cancel_pending ) : ?>
                        <p class="adt-portal__alert">El término está programado para el final del período vigente.</p>
                    <?php endif; ?>
                </div>

                <div class="adt-portal__actions">
                    <?php if ( $simulated_enabled && ! in_array( $status, [ ADT_Subscriptions::STATUS_ACTIVE, ADT_Subscriptions::STATUS_CANCEL_AT_PERIOD_END ], true ) ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="adt_customer_simulate_payment">
                            <?php wp_nonce_field( 'adt_customer_simulate_payment' ); ?>
                            <button class="adt-portal__button" type="submit">Simular pago anual de $<?php echo esc_html( number_format_i18n( ADT_Payments::annual_price_clp() ) ); ?> CLP</button>
                            <small>Disponible solo en ambientes de prueba. No realiza cargos reales.</small>
                        </form>
                    <?php endif; ?>

                    <?php if ( $is_cancel_pending ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="adt_customer_undo_cancel">
                            <?php wp_nonce_field( 'adt_customer_undo_cancel' ); ?>
                            <button class="adt-portal__button adt-portal__button--secondary" type="submit">Mantener mi suscripción</button>
                        </form>
                    <?php elseif ( ! in_array( $status, [ ADT_Subscriptions::STATUS_EXPIRED, ADT_Subscriptions::STATUS_BLOCKED ], true ) ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('¿Programar el término para el final del período vigente?');">
                            <input type="hidden" name="action" value="adt_customer_cancel">
                            <?php wp_nonce_field( 'adt_customer_cancel' ); ?>
                            <label class="adt-portal__check">
                                <input type="checkbox" name="opt_out_notifications" value="1">
                                <span>No deseo recibir más notificaciones del sistema desde ahora.</span>
                            </label>
                            <button class="adt-portal__button adt-portal__button--danger" type="submit">Solicitar término del servicio</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="adt-portal__card">
                <h2>Historial de pagos</h2>
                <?php if ( ! $payments ) : ?>
                    <p>Aún no existen pagos registrados.</p>
                <?php else : ?>
                    <div class="adt-portal__table-wrap">
                        <table class="adt-portal__table">
                            <thead><tr><th>Fecha</th><th>Proveedor</th><th>Estado</th><th>Monto</th><th>Cobertura</th></tr></thead>
                            <tbody>
                            <?php foreach ( $payments as $payment ) : ?>
                                <tr>
                                    <td><?php echo esc_html( self::format_date( $payment['authorized_at'] ?: $payment['created_at'] ) ); ?></td>
                                    <td><?php echo esc_html( ucfirst( $payment['provider'] ) ); ?></td>
                                    <td><?php echo esc_html( ucfirst( $payment['status'] ) ); ?></td>
                                    <td>$<?php echo esc_html( number_format_i18n( (int) $payment['amount_clp'] ) ); ?> CLP</td>
                                    <td><?php echo esc_html( self::format_date( $payment['coverage_end_at'] ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <p class="adt-portal__fineprint">Los documentos tributarios se incorporarán al integrar Lioren.</p>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function handle_register(): void {
        check_admin_referer( 'adt_customer_register' );

        $email            = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $display_name     = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
        $phone            = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $password         = (string) ( $_POST['password'] ?? '' );
        $password_confirm = (string) ( $_POST['password_confirm'] ?? '' );
        $consent          = ! empty( $_POST['consent'] );
        $whatsapp         = ! empty( $_POST['whatsapp_consent'] );

        if ( ! is_email( $email ) || ! $display_name || ! $consent ) {
            self::redirect( self::register_url(), 'invalid_registration' );
        }
        if ( strlen( $password ) < 10 || ! hash_equals( $password, $password_confirm ) ) {
            self::redirect( self::register_url(), 'invalid_password' );
        }
        if ( email_exists( $email ) ) {
            self::redirect( self::login_url(), 'account_exists' );
        }

        $existing_subscriber = ADT_Database::find_subscriber_by_email( $email );
        if ( $existing_subscriber && ! empty( $existing_subscriber['wp_user_id'] ) ) {
            self::redirect( self::login_url(), 'account_exists' );
        }

        $user_login = self::unique_login_from_email( $email );
        $user_id    = wp_insert_user( [
            'user_login'   => $user_login,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $display_name,
            'role'         => ADT_Roles::CUSTOMER_ROLE,
        ] );

        if ( is_wp_error( $user_id ) ) {
            self::redirect( self::register_url(), 'registration_failed' );
        }

        update_user_meta( $user_id, 'adt_phone', $phone );
        $result = ADT_Database::upsert( [
            'email'              => $email,
            'consent'            => true,
            'source_page'        => 'customer_portal',
            'source_url'         => self::register_url(),
            'ip'                 => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent'         => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'subscriber_name'    => $display_name,
            'phone'              => $phone,
            'whatsapp_consent'   => $whatsapp,
            'wp_user_id'         => $user_id,
            'notification_status'=> 'active',
        ] );

        ADT_Subscriptions::ensure_trial( (int) $user_id, (int) $result['id'] );
        ADT_Database::insert_event( 'customer_registered', [
            'user_id'    => (int) $user_id,
            'actor_type' => 'customer',
            'actor_id'   => (int) $user_id,
            'metadata'   => [ 'linked_existing_subscriber' => ! empty( $existing_subscriber ) ],
        ] );

        wp_set_current_user( (int) $user_id );
        wp_set_auth_cookie( (int) $user_id, true, is_ssl() );
        self::redirect( self::account_url(), 'registered' );
    }

    public static function handle_profile(): void {
        $user_id = self::require_customer_action( 'adt_customer_profile' );
        $name    = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
        $phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        if ( ! $name ) {
            self::redirect( self::account_url(), 'profile_invalid' );
        }

        wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
        update_user_meta( $user_id, 'adt_phone', $phone );
        $subscriber = ADT_Database::find_subscriber_by_user( $user_id );
        if ( $subscriber ) {
            ADT_Database::upsert( [
                'email'               => $subscriber['email'],
                'consent'             => true,
                'source_page'         => $subscriber['source_page'],
                'source_url'          => $subscriber['source_url'],
                'subscriber_name'     => $name,
                'phone'               => $phone,
                'whatsapp_consent'    => ! empty( $subscriber['whatsapp_consent'] ),
                'wp_user_id'          => $user_id,
                'notification_status' => $subscriber['notification_status'] ?: 'active',
            ] );
        }
        ADT_Database::insert_event( 'profile_updated', [
            'user_id'    => $user_id,
            'actor_type' => 'customer',
            'actor_id'   => $user_id,
        ] );
        self::redirect( self::account_url(), 'profile_saved' );
    }

    public static function handle_notifications(): void {
        $user_id = self::require_customer_action( 'adt_customer_notifications' );
        $status  = sanitize_key( wp_unslash( $_POST['notification_status'] ?? 'active' ) );
        if ( ! in_array( $status, [ 'active', 'paused', 'opted_out' ], true ) ) {
            $status = 'active';
        }

        $subscriber = ADT_Database::find_subscriber_by_user( $user_id );
        if ( $subscriber ) {
            ADT_Subscriptions::update_notification_status( $user_id, $status );
            global $wpdb;
            $wpdb->update(
                ADT_Database::get_table(),
                [
                    'whatsapp_consent' => ! empty( $_POST['whatsapp_consent'] ) ? 1 : 0,
                    'updated_at'       => current_time( 'mysql', true ),
                ],
                [ 'id' => (int) $subscriber['id'] ],
                [ '%d', '%s' ],
                [ '%d' ]
            );
        }
        self::redirect( self::account_url(), 'notifications_saved' );
    }

    public static function handle_cancel(): void {
        $user_id = self::require_customer_action( 'adt_customer_cancel' );
        ADT_Subscriptions::request_cancel_at_period_end( $user_id );
        if ( ! empty( $_POST['opt_out_notifications'] ) ) {
            ADT_Subscriptions::update_notification_status( $user_id, 'opted_out' );
        }
        self::redirect( self::account_url(), 'cancel_requested' );
    }

    public static function handle_undo_cancel(): void {
        $user_id = self::require_customer_action( 'adt_customer_undo_cancel' );
        ADT_Subscriptions::undo_cancel( $user_id );
        self::redirect( self::account_url(), 'cancel_reverted' );
    }

    public static function handle_simulated_payment(): void {
        $user_id = self::require_customer_action( 'adt_customer_simulate_payment' );
        try {
            ADT_Payments::simulate_annual_payment( $user_id );
            self::redirect( self::account_url(), 'payment_simulated' );
        } catch ( Throwable $e ) {
            self::redirect( self::account_url(), 'payment_unavailable' );
        }
    }

    private static function ensure_page( string $option, string $title, string $slug, string $content ): void {
        $page_id = absint( get_option( $option ) );
        if ( $page_id && 'trash' !== get_post_status( $page_id ) ) {
            return;
        }

        $existing = get_page_by_path( $slug );
        if ( $existing instanceof WP_Post ) {
            update_option( $option, (int) $existing->ID, false );
            return;
        }

        $page_id = wp_insert_post( [
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );
        if ( ! is_wp_error( $page_id ) ) {
            update_option( $option, (int) $page_id, false );
        }
    }

    private static function page_url( string $option, string $fallback_path ): string {
        $page_id = absint( get_option( $option ) );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) {
                return $url;
            }
        }
        return home_url( $fallback_path );
    }

    private static function require_customer_action( string $nonce_action ): int {
        if ( ! is_user_logged_in() ) {
            auth_redirect();
        }
        check_admin_referer( $nonce_action );
        return get_current_user_id();
    }

    private static function redirect( string $url, string $notice ): void {
        wp_safe_redirect( add_query_arg( 'adt_notice', rawurlencode( $notice ), $url ) );
        exit;
    }

    private static function query_notice(): string {
        $key = sanitize_key( wp_unslash( $_GET['adt_notice'] ?? '' ) );
        if ( ! $key ) {
            return '';
        }
        $messages = [
            'registered'           => [ 'Tu cuenta fue creada y comenzó la prueba gratuita de 15 días.', 'success' ],
            'account_exists'       => [ 'Ya existe una cuenta con ese correo. Inicia sesión o recupera tu contraseña.', 'warning' ],
            'invalid_registration' => [ 'Completa los campos obligatorios y acepta el consentimiento.', 'error' ],
            'invalid_password'     => [ 'Las contraseñas deben coincidir y tener al menos 10 caracteres.', 'error' ],
            'registration_failed'  => [ 'No pudimos crear la cuenta. Intenta nuevamente.', 'error' ],
            'profile_saved'        => [ 'Perfil actualizado correctamente.', 'success' ],
            'profile_invalid'      => [ 'Ingresa un nombre válido.', 'error' ],
            'notifications_saved'  => [ 'Preferencias de notificación actualizadas.', 'success' ],
            'cancel_requested'     => [ 'El término quedó programado para el final del período vigente.', 'warning' ],
            'cancel_reverted'      => [ 'La solicitud de término fue revertida.', 'success' ],
            'payment_simulated'    => [ 'Pago simulado aprobado. El acceso quedó activo por 12 meses.', 'success' ],
            'payment_unavailable'  => [ 'El pago simulado no está disponible en este ambiente.', 'error' ],
        ];
        if ( empty( $messages[ $key ] ) ) {
            return '';
        }
        return self::notice( $messages[ $key ][0], $messages[ $key ][1] );
    }

    private static function notice( string $message, string $type ): string {
        return '<div class="adt-portal__notice adt-portal__notice--' . esc_attr( $type ) . '" role="status">' . esc_html( $message ) . '</div>';
    }

    private static function unique_login_from_email( string $email ): string {
        $local = strstr( $email, '@', true );
        $base  = sanitize_user( $local ?: 'cliente', true );
        $base  = $base ?: 'cliente';
        $login = substr( $base, 0, 50 );
        $i     = 1;
        while ( username_exists( $login ) ) {
            $suffix = '-' . $i;
            $login  = substr( $base, 0, 50 - strlen( $suffix ) ) . $suffix;
            $i++;
        }
        return $login;
    }

    private static function format_date( ?string $value ): string {
        if ( ! $value ) {
            return '—';
        }
        $timestamp = strtotime( $value . ' UTC' );
        return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp, wp_timezone() ) : '—';
    }

    private static function days_remaining( ?string $value ): ?int {
        if ( ! $value ) {
            return null;
        }
        $timestamp = strtotime( $value . ' UTC' );
        if ( ! $timestamp ) {
            return null;
        }
        return (int) ceil( ( $timestamp - time() ) / DAY_IN_SECONDS );
    }

    private static function notification_label( string $status ): string {
        $labels = [
            'active'    => 'Activas',
            'paused'    => 'Pausadas',
            'opted_out' => 'Desactivadas por solicitud',
        ];
        return $labels[ $status ] ?? 'Activas';
    }
}

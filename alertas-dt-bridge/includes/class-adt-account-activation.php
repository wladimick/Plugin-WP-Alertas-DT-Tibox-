<?php
defined( 'ABSPATH' ) || exit;

class ADT_Account_Activation {

    private const OPTION_PAGE_ID       = 'adt_activation_page_id';
    private const TOKEN_TTL_SECONDS    = HOUR_IN_SECONDS;
    private const REQUEST_COOLDOWN     = 5 * MINUTE_IN_SECONDS;
    private const TOKEN_RETENTION_DAYS = 7;

    public static function register(): void {
        add_shortcode( 'alertas_dt_activate_account', [ __CLASS__, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        add_action( 'admin_post_nopriv_adt_request_account_activation', [ __CLASS__, 'handle_request' ] );
        add_action( 'admin_post_nopriv_adt_complete_account_activation', [ __CLASS__, 'handle_complete' ] );

        // Evita que un tercero se apropie de un suscriptor histórico usando el
        // formulario de registro normal. La propiedad del correo debe probarse
        // primero mediante el enlace de activación de un solo uso.
        add_action( 'admin_post_nopriv_adt_customer_register', [ __CLASS__, 'intercept_historical_registration' ], 1 );

        add_filter( 'login_form_bottom', [ __CLASS__, 'append_login_link' ], 20, 2 );
    }

    public static function create_table(): void {
        global $wpdb;

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id     BIGINT UNSIGNED NOT NULL,
            token_hash        CHAR(64)        NOT NULL,
            expires_at        DATETIME        NOT NULL,
            used_at           DATETIME        NULL,
            requested_ip_hash CHAR(64)        NULL,
            created_at        DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash_unique (token_hash),
            KEY subscriber_idx (subscriber_id),
            KEY expires_idx (expires_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . ADT_ACTIVATION_TOKENS_TABLE;
    }

    public static function ensure_page(): void {
        $page_id = absint( get_option( self::OPTION_PAGE_ID ) );
        if ( $page_id && 'trash' !== get_post_status( $page_id ) ) {
            return;
        }

        $existing = get_page_by_path( 'activar-cuenta-alertas-dt' );
        if ( $existing instanceof WP_Post ) {
            update_option( self::OPTION_PAGE_ID, (int) $existing->ID, false );
            return;
        }

        $page_id = wp_insert_post( [
            'post_title'   => 'Activar cuenta Alertas DT',
            'post_name'    => 'activar-cuenta-alertas-dt',
            'post_content' => '[alertas_dt_activate_account]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );

        if ( ! is_wp_error( $page_id ) ) {
            update_option( self::OPTION_PAGE_ID, (int) $page_id, false );
        }
    }

    public static function activation_url(): string {
        $page_id = absint( get_option( self::OPTION_PAGE_ID ) );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) {
                return $url;
            }
        }
        return home_url( '/activar-cuenta-alertas-dt/' );
    }

    public static function pending_count(): int {
        global $wpdb;
        $table = self::table();
        $now   = current_time( 'mysql', true );
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE used_at IS NULL AND expires_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $now
            )
        );
    }

    public static function enqueue_assets(): void {
        if ( is_admin() || ! is_singular() ) {
            return;
        }

        global $post;
        if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'alertas_dt_activate_account' ) ) {
            wp_enqueue_style(
                'alertas-dt-portal',
                ADT_PLUGIN_URL . 'assets/css/portal.css',
                [],
                ADT_VERSION
            );
        }
    }

    public static function render(): string {
        if ( is_user_logged_in() ) {
            return '<div class="adt-portal adt-portal--narrow">'
                . self::notice( 'Tu sesión ya está iniciada.', 'success' )
                . '<p><a class="adt-portal__button" href="' . esc_url( ADT_Portal::account_url() ) . '">Ir a Mi cuenta</a></p></div>';
        }

        $token = self::normalize_token( wp_unslash( $_GET['token'] ?? '' ) );
        if ( $token ) {
            return self::render_password_form( $token );
        }

        ob_start();
        ?>
        <div class="adt-portal adt-portal--narrow">
            <?php echo self::query_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <section class="adt-portal__card">
                <p class="adt-portal__eyebrow">Suscriptores existentes</p>
                <h2>Activa tu cuenta</h2>
                <p>Si ya recibías Alertas DT + SII, solicita un enlace seguro para crear tu contraseña y vincular tu suscripción histórica.</p>

                <form class="adt-portal__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="adt_request_account_activation">
                    <?php wp_nonce_field( 'adt_request_account_activation' ); ?>
                    <label>
                        <span>Correo electrónico</span>
                        <input type="email" name="email" required autocomplete="email" placeholder="nombre@empresa.cl">
                    </label>
                    <button class="adt-portal__button adt-portal__button--block" type="submit">Enviar enlace de activación</button>
                </form>

                <p class="adt-portal__fineprint">Por seguridad, siempre mostraremos la misma confirmación, exista o no una suscripción asociada al correo.</p>
                <p class="adt-portal__links"><a href="<?php echo esc_url( ADT_Portal::login_url() ); ?>">Volver al inicio de sesión</a></p>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function handle_request(): void {
        check_admin_referer( 'adt_request_account_activation' );

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $ip    = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

        if ( is_email( $email ) && self::allow_request( $email, $ip ) ) {
            self::send_activation_if_applicable( $email, $ip );
        }

        self::redirect( 'activation_requested' );
    }

    public static function handle_complete(): void {
        check_admin_referer( 'adt_complete_account_activation' );

        $token            = self::normalize_token( wp_unslash( $_POST['token'] ?? '' ) );
        $password         = (string) ( $_POST['password'] ?? '' );
        $password_confirm = (string) ( $_POST['password_confirm'] ?? '' );

        if ( ! $token || strlen( $password ) < 10 || ! hash_equals( $password, $password_confirm ) ) {
            self::redirect( 'invalid_activation_password', $token );
        }

        $record = self::find_valid_token( $token );
        if ( ! $record ) {
            self::redirect( 'invalid_or_expired_token' );
        }

        $subscriber = ADT_Database::find_subscriber_by_email( (string) $record['email'] );
        if ( ! $subscriber || (int) $subscriber['id'] !== (int) $record['subscriber_id'] ) {
            self::mark_token_used( (int) $record['id'] );
            self::redirect( 'invalid_or_expired_token' );
        }

        if ( ! empty( $subscriber['wp_user_id'] ) || email_exists( $subscriber['email'] ) ) {
            self::mark_all_used( (int) $subscriber['id'] );
            self::redirect( 'account_already_exists' );
        }

        $display_name = trim( (string) ( $subscriber['subscriber_name'] ?? '' ) );
        if ( '' === $display_name ) {
            $display_name = strstr( $subscriber['email'], '@', true ) ?: 'Cliente Alertas DT';
        }

        $user_id = wp_insert_user( [
            'user_login'   => self::unique_login_from_email( $subscriber['email'] ),
            'user_email'   => $subscriber['email'],
            'user_pass'    => $password,
            'display_name' => $display_name,
            'role'         => ADT_Roles::CUSTOMER_ROLE,
        ] );

        if ( is_wp_error( $user_id ) ) {
            self::redirect( 'activation_failed', $token );
        }

        update_user_meta( (int) $user_id, 'adt_phone', (string) ( $subscriber['phone'] ?? '' ) );
        self::link_subscriber_to_user( (int) $subscriber['id'], (int) $user_id );
        $subscription = ADT_Subscriptions::ensure_trial( (int) $user_id, (int) $subscriber['id'] );

        self::mark_all_used( (int) $subscriber['id'] );
        ADT_Database::insert_event( 'historical_account_activated', [
            'user_id'         => (int) $user_id,
            'subscription_id' => (int) ( $subscription['id'] ?? 0 ),
            'actor_type'      => 'customer',
            'actor_id'        => (int) $user_id,
            'metadata'        => [ 'subscriber_id' => (int) $subscriber['id'] ],
        ] );

        wp_set_current_user( (int) $user_id );
        wp_set_auth_cookie( (int) $user_id, true, is_ssl() );
        self::send_confirmation_email( $subscriber['email'], $display_name );

        wp_safe_redirect( add_query_arg( 'adt_notice', 'registered', ADT_Portal::account_url() ) );
        exit;
    }

    public static function intercept_historical_registration(): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'adt_customer_register' ) ) {
            return;
        }

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( ! is_email( $email ) ) {
            return;
        }

        $subscriber = ADT_Database::find_subscriber_by_email( $email );
        if ( $subscriber && empty( $subscriber['wp_user_id'] ) ) {
            wp_safe_redirect( add_query_arg( 'adt_notice', 'activation_required', self::activation_url() ) );
            exit;
        }
    }

    public static function append_login_link( string $content, array $args ): string {
        $link = '<p class="adt-portal__fineprint">¿Ya recibías nuestras alertas? <a href="'
            . esc_url( self::activation_url() ) . '">Activa tu cuenta existente</a>.</p>';
        return $content . $link;
    }

    private static function render_password_form( string $token ): string {
        $record = self::find_valid_token( $token );
        if ( ! $record ) {
            return '<div class="adt-portal adt-portal--narrow">'
                . self::notice( 'El enlace de activación no es válido o ya venció.', 'error' )
                . '<p><a class="adt-portal__button" href="' . esc_url( self::activation_url() ) . '">Solicitar otro enlace</a></p></div>';
        }

        ob_start();
        ?>
        <div class="adt-portal adt-portal--narrow">
            <?php echo self::query_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <section class="adt-portal__card">
                <p class="adt-portal__eyebrow">Enlace verificado</p>
                <h2>Crea tu contraseña</h2>
                <p>Activarás la cuenta asociada a <strong><?php echo esc_html( self::mask_email( (string) $record['email'] ) ); ?></strong>.</p>

                <form class="adt-portal__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="adt_complete_account_activation">
                    <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
                    <?php wp_nonce_field( 'adt_complete_account_activation' ); ?>
                    <label>
                        <span>Contraseña</span>
                        <input type="password" name="password" required minlength="10" autocomplete="new-password">
                        <small>Usa al menos 10 caracteres.</small>
                    </label>
                    <label>
                        <span>Confirmar contraseña</span>
                        <input type="password" name="password_confirm" required minlength="10" autocomplete="new-password">
                    </label>
                    <button class="adt-portal__button adt-portal__button--block" type="submit">Activar mi cuenta</button>
                </form>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function send_activation_if_applicable( string $email, string $ip ): void {
        $subscriber = ADT_Database::find_subscriber_by_email( $email );
        if ( ! $subscriber || ! empty( $subscriber['wp_user_id'] ) || email_exists( $email ) ) {
            return;
        }

        try {
            $token = bin2hex( random_bytes( 32 ) );
        } catch ( Throwable $e ) {
            return;
        }

        self::cleanup_tokens();
        self::mark_all_used( (int) $subscriber['id'] );

        $inserted = self::insert_token( (int) $subscriber['id'], $token, $ip );
        if ( ! $inserted ) {
            return;
        }

        $url     = add_query_arg( 'token', rawurlencode( $token ), self::activation_url() );
        $name    = trim( (string) ( $subscriber['subscriber_name'] ?? '' ) );
        $subject = apply_filters( 'adt_activation_email_subject', 'Activa tu cuenta de Alertas DT + SII' );
        $body    = "Hola" . ( $name ? ' ' . $name : '' ) . ",\n\n"
            . "Solicitaste activar tu cuenta de Alertas DT + SII.\n\n"
            . "Crea tu contraseña usando este enlace seguro:\n{$url}\n\n"
            . "El enlace vence en 60 minutos y solo puede utilizarse una vez.\n\n"
            . "Si no solicitaste esta activación, ignora este correo.";

        if ( ! wp_mail( $email, $subject, $body ) ) {
            self::mark_all_used( (int) $subscriber['id'] );
            ADT_Database::insert_event( 'activation_email_failed', [
                'actor_type' => 'system',
                'metadata'   => [ 'subscriber_id' => (int) $subscriber['id'] ],
            ] );
            return;
        }

        ADT_Database::insert_event( 'activation_email_sent', [
            'actor_type' => 'system',
            'metadata'   => [ 'subscriber_id' => (int) $subscriber['id'] ],
        ] );
    }

    private static function send_confirmation_email( string $email, string $name ): void {
        $subject = apply_filters( 'adt_activation_confirmation_subject', 'Tu cuenta de Alertas DT + SII está activa' );
        $body    = "Hola {$name},\n\nTu cuenta fue activada correctamente.\n\n"
            . 'Puedes ingresar y administrar tu perfil en: ' . ADT_Portal::account_url() . "\n\n"
            . 'Si no reconoces esta acción, contacta al administrador del servicio.';
        wp_mail( $email, $subject, $body );
    }

    private static function insert_token( int $subscriber_id, string $token, string $ip ): bool {
        global $wpdb;
        $now     = current_time( 'mysql', true );
        $expires = gmdate( 'Y-m-d H:i:s', strtotime( '+' . self::TOKEN_TTL_SECONDS . ' seconds', strtotime( $now ) ) );

        return false !== $wpdb->insert(
            self::table(),
            [
                'subscriber_id'     => $subscriber_id,
                'token_hash'        => hash( 'sha256', $token ),
                'expires_at'        => $expires,
                'used_at'           => null,
                'requested_ip_hash' => $ip ? hash( 'sha256', $ip ) : null,
                'created_at'        => $now,
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    private static function find_valid_token( string $token ): ?array {
        global $wpdb;
        if ( ! self::is_token_format_valid( $token ) ) {
            return null;
        }

        $tokens      = self::table();
        $subscribers = ADT_Database::get_table();
        $now         = current_time( 'mysql', true );
        $row         = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT t.id, t.subscriber_id, t.expires_at, s.email
                 FROM {$tokens} t
                 INNER JOIN {$subscribers} s ON s.id = t.subscriber_id
                 WHERE t.token_hash = %s AND t.used_at IS NULL AND t.expires_at >= %s
                 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                hash( 'sha256', $token ),
                $now
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    private static function mark_token_used( int $token_id ): void {
        global $wpdb;
        $wpdb->update(
            self::table(),
            [ 'used_at' => current_time( 'mysql', true ) ],
            [ 'id' => $token_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    private static function mark_all_used( int $subscriber_id ): void {
        global $wpdb;
        $table = self::table();
        $now   = current_time( 'mysql', true );
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET used_at = %s WHERE subscriber_id = %d AND used_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $now,
                $subscriber_id
            )
        );
    }

    private static function cleanup_tokens(): void {
        global $wpdb;
        $table  = self::table();
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::TOKEN_RETENTION_DAYS . ' days', time() ) );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE (used_at IS NOT NULL AND used_at < %s) OR expires_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $cutoff,
                $cutoff
            )
        );
    }

    private static function allow_request( string $email, string $ip ): bool {
        $key = 'adt_activation_rl_' . substr( hash( 'sha256', strtolower( $email ) . '|' . $ip ), 0, 40 );
        if ( get_transient( $key ) ) {
            return false;
        }
        set_transient( $key, 1, self::REQUEST_COOLDOWN );
        return true;
    }

    private static function link_subscriber_to_user( int $subscriber_id, int $user_id ): void {
        global $wpdb;
        $wpdb->update(
            ADT_Database::get_table(),
            [
                'wp_user_id' => $user_id,
                'updated_at' => current_time( 'mysql', true ),
            ],
            [ 'id' => $subscriber_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );
    }

    private static function unique_login_from_email( string $email ): string {
        $local = strstr( $email, '@', true );
        $base  = sanitize_user( $local ?: 'cliente', true ) ?: 'cliente';
        $login = substr( $base, 0, 50 );
        $i     = 1;
        while ( username_exists( $login ) ) {
            $suffix = '-' . $i;
            $login  = substr( $base, 0, 50 - strlen( $suffix ) ) . $suffix;
            $i++;
        }
        return $login;
    }

    private static function redirect( string $notice, string $token = '' ): void {
        $args = [ 'adt_notice' => $notice ];
        if ( $token && self::is_token_format_valid( $token ) ) {
            $args['token'] = $token;
        }
        wp_safe_redirect( add_query_arg( $args, self::activation_url() ) );
        exit;
    }

    private static function query_notice(): string {
        $key = sanitize_key( wp_unslash( $_GET['adt_notice'] ?? '' ) );
        $messages = [
            'activation_requested'       => [ 'Si el correo está registrado y aún no tiene cuenta, enviaremos un enlace de activación.', 'success' ],
            'activation_required'        => [ 'Ese correo ya estaba suscrito. Activa tu cuenta mediante un enlace seguro.', 'warning' ],
            'invalid_activation_password'=> [ 'Las contraseñas deben coincidir y tener al menos 10 caracteres.', 'error' ],
            'invalid_or_expired_token'   => [ 'El enlace de activación no es válido o ya venció. Solicita uno nuevo.', 'error' ],
            'account_already_exists'     => [ 'Ya existe una cuenta con ese correo. Inicia sesión o recupera tu contraseña.', 'warning' ],
            'activation_failed'          => [ 'No pudimos activar la cuenta. Intenta nuevamente o solicita otro enlace.', 'error' ],
        ];
        if ( ! $key || empty( $messages[ $key ] ) ) {
            return '';
        }
        return self::notice( $messages[ $key ][0], $messages[ $key ][1] );
    }

    private static function notice( string $message, string $type ): string {
        return '<div class="adt-portal__notice adt-portal__notice--' . esc_attr( $type ) . '" role="status">' . esc_html( $message ) . '</div>';
    }

    private static function normalize_token( string $token ): string {
        $token = strtolower( trim( $token ) );
        return self::is_token_format_valid( $token ) ? $token : '';
    }

    private static function is_token_format_valid( string $token ): bool {
        return 64 === strlen( $token ) && ctype_xdigit( $token );
    }

    private static function mask_email( string $email ): string {
        $parts = explode( '@', $email, 2 );
        if ( 2 !== count( $parts ) ) {
            return 'correo registrado';
        }
        $local = $parts[0];
        $shown = substr( $local, 0, min( 2, strlen( $local ) ) );
        return $shown . str_repeat( '•', max( 3, strlen( $local ) - strlen( $shown ) ) ) . '@' . $parts[1];
    }
}

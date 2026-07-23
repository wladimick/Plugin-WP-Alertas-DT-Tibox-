<?php
defined( 'ABSPATH' ) || exit;

class ADT_Admin {

    public static function register(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_adt_regenerate_token', [ __CLASS__, 'handle_regenerate' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, 'alertas-dt' ) ) {
            return;
        }
        wp_enqueue_style( 'alertas-dt-admin', ADT_PLUGIN_URL . 'assets/css/admin.css', [], ADT_VERSION );
    }

    public static function add_menu(): void {
        add_menu_page(
            'Alertas DT + SII',
            'Alertas DT + SII',
            'manage_options',
            'alertas-dt',
            [ __CLASS__, 'render_page' ],
            'dashicons-email-alt',
            80
        );
    }

    private static function reveal_key(): string {
        return 'adt_token_reveal_' . get_current_user_id();
    }

    public static function handle_regenerate(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permisos.' );
        }
        check_admin_referer( 'adt_regenerate_token' );
        $new_token = ADT_Settings::regenerate_token();
        set_transient( self::reveal_key(), $new_token, 60 );
        wp_safe_redirect( add_query_arg( 'adt_notice', 'token_regenerated', admin_url( 'admin.php?page=alertas-dt' ) ) );
        exit;
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permisos.' );
        }

        $notice       = sanitize_text_field( wp_unslash( $_GET['adt_notice'] ?? '' ) );
        $token        = ADT_Settings::get_token();
        $masked       = $token ? substr( $token, 0, 8 ) . str_repeat( '•', 20 ) : '(sin token)';
        $total        = ADT_Database::count();
        $active       = ADT_Database::count( 'active' );
        $eligible     = ADT_Database::count_eligible();
        $last_sync    = ADT_Settings::get_last_sync() ?: '—';
        $base_url     = rest_url( ADT_REST::NAMESPACE );
        $account_url  = ADT_Portal::account_url();
        $register_url = ADT_Portal::register_url();
        $login_url    = ADT_Portal::login_url();

        $reveal_token = get_transient( self::reveal_key() );
        if ( $reveal_token ) {
            delete_transient( self::reveal_key() );
        }
        ?>
        <div class="wrap adt-admin">
            <h1>Alertas DT + SII <span class="adt-version">v<?php echo esc_html( ADT_VERSION ); ?></span></h1>

            <?php if ( $reveal_token ) : ?>
                <div class="notice notice-warning adt-token-reveal" style="border-left-color:#d97706;">
                    <h3 style="margin:.5em 0 .25em;">Token nuevo generado — cópialo ahora</h3>
                    <p>El token completo se muestra una sola vez. Al recargar quedará enmascarado.</p>
                    <div class="adt-token-reveal__box">
                        <code id="adt-token-full" class="adt-token-full"><?php echo esc_html( $reveal_token ); ?></code>
                        <button type="button" class="button button-primary" id="adt-copy-btn" onclick="adtCopyToken()">Copiar token</button>
                    </div>
                    <p class="adt-reveal-note">Pégalo en <code>WORDPRESS_API_TOKEN</code>. No lo guardes en Git.</p>
                </div>
                <script>
                function adtCopyToken() {
                    var val = document.getElementById('adt-token-full').textContent;
                    var btn = document.getElementById('adt-copy-btn');
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(val).then(function () {
                            btn.textContent = '¡Copiado!'; btn.disabled = true;
                            setTimeout(function () { btn.textContent = 'Copiar token'; btn.disabled = false; }, 3000);
                        });
                    }
                }
                </script>
            <?php elseif ( 'token_regenerated' === $notice ) : ?>
                <div class="notice notice-error is-dismissible"><p><strong>El token ya no está disponible.</strong> Genera otro si no alcanzaste a copiarlo.</p></div>
            <?php endif; ?>

            <div class="adt-cards">
                <div class="adt-card">
                    <h2>Estado</h2>
                    <p><span class="adt-badge adt-badge--ok">Activo</span></p>
                    <p>Versión <strong><?php echo esc_html( ADT_VERSION ); ?></strong></p>
                </div>
                <div class="adt-card">
                    <h2>Suscriptores</h2>
                    <p class="adt-stat"><?php echo esc_html( $total ); ?></p>
                    <p class="adt-muted"><?php echo esc_html( $active ); ?> activos</p>
                    <p class="adt-muted"><?php echo esc_html( $eligible ); ?> elegibles para alertas</p>
                </div>
                <div class="adt-card">
                    <h2>Portal de clientes</h2>
                    <p><span class="adt-badge adt-badge--ok">Preparado</span></p>
                    <p class="adt-muted">Prueba gratuita: <?php echo esc_html( ADT_Subscriptions::TRIAL_DAYS ); ?> días</p>
                    <p class="adt-muted">Pago anual: simulado en esta etapa</p>
                </div>
                <div class="adt-card">
                    <h2>Sincronización</h2>
                    <p class="adt-muted">Última sincronización:</p>
                    <p><strong><?php echo esc_html( $last_sync ); ?></strong></p>
                </div>
            </div>

            <div class="adt-section">
                <h2>Páginas del portal</h2>
                <table class="form-table">
                    <tr><th>Registro</th><td><a href="<?php echo esc_url( $register_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $register_url ); ?></a><br><code>[alertas_dt_register]</code></td></tr>
                    <tr><th>Inicio de sesión</th><td><a href="<?php echo esc_url( $login_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $login_url ); ?></a><br><code>[alertas_dt_login]</code></td></tr>
                    <tr><th>Mi cuenta</th><td><a href="<?php echo esc_url( $account_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $account_url ); ?></a><br><code>[alertas_dt_account]</code></td></tr>
                    <tr><th>Formulario heredado</th><td><code>[alertas_dt_form]</code></td></tr>
                </table>
            </div>

            <div class="adt-section">
                <h2>API para Alertas DT Python</h2>
                <table class="form-table">
                    <tr><th>Endpoint base</th><td><code><?php echo esc_html( $base_url ); ?></code></td></tr>
                    <tr><th>Suscriptores</th><td><code><?php echo esc_html( $base_url . '/subscribers' ); ?></code></td></tr>
                    <tr><th>Solo elegibles</th><td><code><?php echo esc_html( $base_url . '/subscribers?eligible_only=true' ); ?></code></td></tr>
                    <tr><th>Healthcheck</th><td><code><?php echo esc_html( $base_url . '/health' ); ?></code></td></tr>
                    <tr>
                        <th>Token API</th>
                        <td>
                            <code class="adt-token-masked"><?php echo esc_html( $masked ); ?></code>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:12px;">
                                <?php wp_nonce_field( 'adt_regenerate_token' ); ?>
                                <input type="hidden" name="action" value="adt_regenerate_token">
                                <button type="submit" class="button button-secondary" onclick="return confirm('¿Regenerar el token? La app dejará de sincronizar hasta actualizar WORDPRESS_API_TOKEN.');">Regenerar token</button>
                            </form>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="adt-section">
                <h2>Configurar aplicación Python</h2>
                <pre class="adt-pre">WORDPRESS_SYNC_ENABLED=true
WORDPRESS_API_URL=<?php echo esc_html( rtrim( $base_url, '/' ) ); ?>
WORDPRESS_API_TOKEN=<em>token privado</em>
WORDPRESS_SYNC_INTERVAL_MINUTES=15
WORDPRESS_SYNC_LIMIT=100</pre>
                <p class="adt-muted">La API mantiene compatibilidad con el flujo actual y ahora expone el estado de suscripción y elegibilidad.</p>
            </div>
        </div>
        <?php
    }
}

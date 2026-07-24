<?php
defined( 'ABSPATH' ) || exit;

class ADT_Database {

    public static function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . ADT_TABLE;
    }

    public static function get_subscriptions_table(): string {
        global $wpdb;
        return $wpdb->prefix . ADT_SUBSCRIPTIONS_TABLE;
    }

    public static function get_payments_table(): string {
        global $wpdb;
        return $wpdb->prefix . ADT_PAYMENTS_TABLE;
    }

    public static function get_events_table(): string {
        global $wpdb;
        return $wpdb->prefix . ADT_EVENTS_TABLE;
    }

    public static function create_table(): void {
        global $wpdb;

        $subscribers   = self::get_table();
        $subscriptions = self::get_subscriptions_table();
        $payments      = self::get_payments_table();
        $events        = self::get_events_table();
        $charset       = $wpdb->get_charset_collate();

        $subscriber_sql = "CREATE TABLE {$subscribers} (
            id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email                   VARCHAR(190)    NOT NULL,
            status                  VARCHAR(30)     NOT NULL DEFAULT 'active',
            consent                 TINYINT(1)      NOT NULL DEFAULT 0,
            consent_at              DATETIME        NULL,
            source_page             TEXT            NULL,
            source_url              TEXT            NULL,
            ip_hash                 VARCHAR(128)    NULL,
            user_agent              TEXT            NULL,
            created_at              DATETIME        NOT NULL,
            updated_at              DATETIME        NOT NULL,
            synced_at               DATETIME        NULL,
            last_error              TEXT            NULL,
            subscriber_name         VARCHAR(255)    NULL,
            phone                   VARCHAR(30)     NULL,
            whatsapp_consent        TINYINT(1)      NOT NULL DEFAULT 0,
            wp_user_id              BIGINT UNSIGNED NULL,
            notification_status     VARCHAR(30)     NOT NULL DEFAULT 'active',
            notification_updated_at DATETIME        NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email_unique (email),
            KEY status_idx (status),
            KEY wp_user_id_idx (wp_user_id)
        ) {$charset};";

        $subscription_sql = "CREATE TABLE {$subscriptions} (
            id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id               BIGINT UNSIGNED NOT NULL,
            subscriber_id         BIGINT UNSIGNED NULL,
            status                VARCHAR(30)     NOT NULL DEFAULT 'trialing',
            trial_start_at        DATETIME        NULL,
            trial_end_at          DATETIME        NULL,
            subscription_start_at DATETIME        NULL,
            subscription_end_at   DATETIME        NULL,
            cancel_at_period_end  TINYINT(1)      NOT NULL DEFAULT 0,
            canceled_at           DATETIME        NULL,
            created_at            DATETIME        NOT NULL,
            updated_at            DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_unique (user_id),
            KEY subscriber_idx (subscriber_id),
            KEY status_idx (status)
        ) {$charset};";

        $payment_sql = "CREATE TABLE {$payments} (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id           BIGINT UNSIGNED NOT NULL,
            subscription_id   BIGINT UNSIGNED NULL,
            provider          VARCHAR(40)     NOT NULL,
            external_id       VARCHAR(190)    NULL,
            amount_clp        BIGINT UNSIGNED NOT NULL DEFAULT 0,
            currency          VARCHAR(3)      NOT NULL DEFAULT 'CLP',
            status            VARCHAR(30)     NOT NULL DEFAULT 'created',
            coverage_start_at DATETIME        NULL,
            coverage_end_at   DATETIME        NULL,
            authorized_at     DATETIME        NULL,
            created_at        DATETIME        NOT NULL,
            updated_at        DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY provider_external_unique (provider, external_id),
            KEY user_idx (user_id),
            KEY status_idx (status)
        ) {$charset};";

        $event_sql = "CREATE TABLE {$events} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT UNSIGNED NULL,
            subscription_id BIGINT UNSIGNED NULL,
            event_type      VARCHAR(80)     NOT NULL,
            actor_type      VARCHAR(30)     NOT NULL DEFAULT 'system',
            actor_id        BIGINT UNSIGNED NULL,
            metadata_json   LONGTEXT        NULL,
            created_at      DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY user_idx (user_id),
            KEY subscription_idx (subscription_id),
            KEY event_type_idx (event_type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $subscriber_sql );
        dbDelta( $subscription_sql );
        dbDelta( $payment_sql );
        dbDelta( $event_sql );
    }

    public static function drop_table(): void {
        global $wpdb;
        foreach ( [ self::get_events_table(), self::get_payments_table(), self::get_subscriptions_table(), self::get_table() ] as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
    }

    /**
     * Upsert subscriber. Returns [ 'id' => int, 'created' => bool ].
     */
    public static function upsert( array $data ): array {
        global $wpdb;
        $table = self::get_table();
        $now   = current_time( 'mysql', true );

        $email = sanitize_email( strtolower( trim( $data['email'] ?? '' ) ) );
        if ( ! is_email( $email ) ) {
            throw new InvalidArgumentException( 'Ingresa un correo electrónico válido.' );
        }
        if ( empty( $data['consent'] ) ) {
            throw new InvalidArgumentException( 'Debes aceptar recibir alertas para continuar.' );
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, wp_user_id FROM {$table} WHERE email = %s", $email ), // phpcs:ignore
            ARRAY_A
        );

        $subscriber_name     = isset( $data['subscriber_name'] ) ? sanitize_text_field( $data['subscriber_name'] ) : null;
        $phone               = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : null;
        $whatsapp_consent    = ! empty( $data['whatsapp_consent'] ) ? 1 : 0;
        $wp_user_id          = isset( $data['wp_user_id'] ) ? absint( $data['wp_user_id'] ) : 0;
        $notification_status = isset( $data['notification_status'] )
            ? sanitize_key( $data['notification_status'] )
            : 'active';

        if ( ! in_array( $notification_status, [ 'active', 'paused', 'opted_out' ], true ) ) {
            $notification_status = 'active';
        }

        if ( $existing ) {
            $update = [
                'status'                  => 'active',
                'consent'                 => 1,
                'consent_at'              => $now,
                'source_page'             => $data['source_page'] ?? null,
                'source_url'              => $data['source_url'] ?? null,
                'updated_at'              => $now,
                'subscriber_name'         => $subscriber_name,
                'phone'                   => $phone,
                'whatsapp_consent'        => $whatsapp_consent,
                'notification_status'     => $notification_status,
                'notification_updated_at' => $now,
            ];
            $formats = [ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ];

            if ( $wp_user_id > 0 ) {
                $update['wp_user_id'] = $wp_user_id;
                $formats[]            = '%d';
            }

            $wpdb->update( $table, $update, [ 'id' => $existing['id'] ], $formats, [ '%d' ] );
            return [ 'id' => (int) $existing['id'], 'created' => false ];
        }

        $ip_hash = ! empty( $data['ip'] ) ? hash( 'sha256', $data['ip'] ) : null;

        $wpdb->insert(
            $table,
            [
                'email'                   => $email,
                'status'                  => 'active',
                'consent'                 => 1,
                'consent_at'              => $now,
                'source_page'             => $data['source_page'] ?? null,
                'source_url'              => $data['source_url'] ?? null,
                'ip_hash'                 => $ip_hash,
                'user_agent'              => isset( $data['user_agent'] ) ? substr( $data['user_agent'], 0, 512 ) : null,
                'created_at'              => $now,
                'updated_at'              => $now,
                'subscriber_name'         => $subscriber_name,
                'phone'                   => $phone,
                'whatsapp_consent'        => $whatsapp_consent,
                'wp_user_id'              => $wp_user_id ?: null,
                'notification_status'     => $notification_status,
                'notification_updated_at' => $now,
            ],
            [ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
        );

        return [ 'id' => (int) $wpdb->insert_id, 'created' => true ];
    }

    public static function find_subscriber_by_email( string $email ): ?array {
        global $wpdb;
        $table = self::get_table();
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", sanitize_email( strtolower( $email ) ) ), // phpcs:ignore
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function find_subscriber_by_user( int $user_id ): ?array {
        global $wpdb;
        $table = self::get_table();
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE wp_user_id = %d", $user_id ), // phpcs:ignore
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function update_notification_status( int $subscriber_id, string $status ): bool {
        global $wpdb;
        if ( ! in_array( $status, [ 'active', 'paused', 'opted_out' ], true ) ) {
            return false;
        }
        return false !== $wpdb->update(
            self::get_table(),
            [
                'notification_status'     => $status,
                'notification_updated_at' => current_time( 'mysql', true ),
                'updated_at'              => current_time( 'mysql', true ),
            ],
            [ 'id' => $subscriber_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    public static function count( string $status = '' ): int {
        global $wpdb;
        $table = self::get_table();
        if ( $status ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) // phpcs:ignore
            );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
    }

    public static function count_eligible(): int {
        global $wpdb;
        $subscribers   = self::get_table();
        $subscriptions = self::get_subscriptions_table();
        $now           = current_time( 'mysql', true );
        $sql           = "SELECT COUNT(*)
            FROM {$subscribers} s
            LEFT JOIN {$subscriptions} sub ON sub.user_id = s.wp_user_id
            WHERE s.status = 'active'
              AND s.consent = 1
              AND COALESCE(s.notification_status, 'active') = 'active'
              AND (
                    s.wp_user_id IS NULL
                    OR (sub.status = 'trialing' AND sub.trial_end_at >= %s)
                    OR (sub.status IN ('active', 'cancel_at_period_end') AND sub.subscription_end_at >= %s)
              )";
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $now, $now ) ); // phpcs:ignore
    }

    public static function list( array $args = [] ): array {
        global $wpdb;
        $subscribers   = self::get_table();
        $subscriptions = self::get_subscriptions_table();
        $where         = [];
        $params        = [];

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 's.status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['updated_after'] ) ) {
            $where[]  = 's.updated_at >= %s';
            $params[] = $args['updated_after'];
        }
        if ( ! empty( $args['eligible_only'] ) ) {
            $now      = current_time( 'mysql', true );
            $where[]  = "s.consent = 1
                AND COALESCE(s.notification_status, 'active') = 'active'
                AND (
                    s.wp_user_id IS NULL
                    OR (sub.status = 'trialing' AND sub.trial_end_at >= %s)
                    OR (sub.status IN ('active', 'cancel_at_period_end') AND sub.subscription_end_at >= %s)
                )";
            $params[] = $now;
            $params[] = $now;
        }

        $limit  = min( (int) ( $args['limit'] ?? 100 ), 500 );
        $offset = ( max( 1, (int) ( $args['page'] ?? 1 ) ) - 1 ) * $limit;

        $sql = "SELECT
                    s.id, s.email, s.status, s.consent, s.consent_at, s.source_page, s.source_url,
                    s.created_at, s.updated_at, s.synced_at, s.subscriber_name, s.phone,
                    s.whatsapp_consent, s.wp_user_id, s.notification_status,
                    sub.id AS subscription_id, sub.status AS subscription_status,
                    sub.trial_start_at, sub.trial_end_at, sub.subscription_start_at,
                    sub.subscription_end_at, sub.cancel_at_period_end, sub.canceled_at
                FROM {$subscribers} s
                LEFT JOIN {$subscriptions} sub ON sub.user_id = s.wp_user_id";

        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql     .= ' ORDER BY s.id ASC LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
        return $rows ?: [];
    }

    public static function get_subscription_by_user( int $user_id ): ?array {
        global $wpdb;
        $table = self::get_subscriptions_table();
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ), // phpcs:ignore
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function insert_subscription( array $data ): int {
        global $wpdb;
        $now = current_time( 'mysql', true );
        $wpdb->insert(
            self::get_subscriptions_table(),
            [
                'user_id'               => absint( $data['user_id'] ),
                'subscriber_id'         => ! empty( $data['subscriber_id'] ) ? absint( $data['subscriber_id'] ) : null,
                'status'                => sanitize_key( $data['status'] ?? 'trialing' ),
                'trial_start_at'        => $data['trial_start_at'] ?? null,
                'trial_end_at'          => $data['trial_end_at'] ?? null,
                'subscription_start_at' => $data['subscription_start_at'] ?? null,
                'subscription_end_at'   => $data['subscription_end_at'] ?? null,
                'cancel_at_period_end'  => ! empty( $data['cancel_at_period_end'] ) ? 1 : 0,
                'canceled_at'           => $data['canceled_at'] ?? null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );
        return (int) $wpdb->insert_id;
    }

    public static function update_subscription( int $id, array $data ): bool {
        global $wpdb;
        $allowed = [
            'status'                => '%s',
            'trial_start_at'        => '%s',
            'trial_end_at'          => '%s',
            'subscription_start_at' => '%s',
            'subscription_end_at'   => '%s',
            'cancel_at_period_end'  => '%d',
            'canceled_at'           => '%s',
            'subscriber_id'         => '%d',
        ];
        $update  = [];
        $formats = [];
        foreach ( $allowed as $key => $format ) {
            if ( array_key_exists( $key, $data ) ) {
                $update[ $key ] = $data[ $key ];
                $formats[]      = $format;
            }
        }
        $update['updated_at'] = current_time( 'mysql', true );
        $formats[]            = '%s';

        return false !== $wpdb->update(
            self::get_subscriptions_table(),
            $update,
            [ 'id' => $id ],
            $formats,
            [ '%d' ]
        );
    }

    public static function insert_payment( array $data ): int {
        global $wpdb;
        $now = current_time( 'mysql', true );
        $wpdb->insert(
            self::get_payments_table(),
            [
                'user_id'           => absint( $data['user_id'] ),
                'subscription_id'   => ! empty( $data['subscription_id'] ) ? absint( $data['subscription_id'] ) : null,
                'provider'          => sanitize_key( $data['provider'] ?? 'simulated' ),
                'external_id'       => sanitize_text_field( $data['external_id'] ?? '' ),
                'amount_clp'        => absint( $data['amount_clp'] ?? 0 ),
                'currency'          => sanitize_text_field( $data['currency'] ?? 'CLP' ),
                'status'            => sanitize_key( $data['status'] ?? 'created' ),
                'coverage_start_at' => $data['coverage_start_at'] ?? null,
                'coverage_end_at'   => $data['coverage_end_at'] ?? null,
                'authorized_at'     => $data['authorized_at'] ?? null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [ '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
        return (int) $wpdb->insert_id;
    }

    public static function list_payments( int $user_id, int $limit = 20 ): array {
        global $wpdb;
        $table = self::get_payments_table();
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d", // phpcs:ignore
                $user_id,
                min( max( 1, $limit ), 100 )
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    public static function insert_event( string $event_type, array $context = [] ): int {
        global $wpdb;
        $metadata = $context['metadata'] ?? [];
        unset( $metadata['api_key'], $metadata['token'], $metadata['password'] );

        $wpdb->insert(
            self::get_events_table(),
            [
                'user_id'         => ! empty( $context['user_id'] ) ? absint( $context['user_id'] ) : null,
                'subscription_id' => ! empty( $context['subscription_id'] ) ? absint( $context['subscription_id'] ) : null,
                'event_type'      => sanitize_key( $event_type ),
                'actor_type'      => sanitize_key( $context['actor_type'] ?? 'system' ),
                'actor_id'        => ! empty( $context['actor_id'] ) ? absint( $context['actor_id'] ) : null,
                'metadata_json'   => $metadata ? wp_json_encode( $metadata ) : null,
                'created_at'      => current_time( 'mysql', true ),
            ],
            [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ]
        );
        return (int) $wpdb->insert_id;
    }

    public static function mark_synced( array $ids, string $synced_at ): int {
        global $wpdb;
        if ( empty( $ids ) ) {
            return 0;
        }
        $table        = self::get_table();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET synced_at = %s WHERE id IN ({$placeholders})", // phpcs:ignore
                array_merge( [ $synced_at ], $ids )
            )
        );
    }
}

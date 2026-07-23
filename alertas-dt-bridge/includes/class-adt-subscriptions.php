<?php
defined( 'ABSPATH' ) || exit;

class ADT_Subscriptions {

    public const TRIAL_DAYS = 15;

    public const STATUS_TRIALING             = 'trialing';
    public const STATUS_AWAITING_PAYMENT     = 'awaiting_payment';
    public const STATUS_ACTIVE               = 'active';
    public const STATUS_PAST_DUE             = 'past_due';
    public const STATUS_CANCEL_AT_PERIOD_END = 'cancel_at_period_end';
    public const STATUS_EXPIRED              = 'expired';
    public const STATUS_PAUSED               = 'paused';
    public const STATUS_BLOCKED              = 'blocked';

    public static function valid_statuses(): array {
        return [
            self::STATUS_TRIALING,
            self::STATUS_AWAITING_PAYMENT,
            self::STATUS_ACTIVE,
            self::STATUS_PAST_DUE,
            self::STATUS_CANCEL_AT_PERIOD_END,
            self::STATUS_EXPIRED,
            self::STATUS_PAUSED,
            self::STATUS_BLOCKED,
        ];
    }

    public static function get_for_user( int $user_id ): ?array {
        $subscription = ADT_Database::get_subscription_by_user( $user_id );
        if ( ! $subscription ) {
            return null;
        }
        return self::refresh_expiration( $subscription );
    }

    public static function ensure_trial( int $user_id, int $subscriber_id = 0 ): array {
        $existing = self::get_for_user( $user_id );
        if ( $existing ) {
            if ( $subscriber_id && empty( $existing['subscriber_id'] ) ) {
                ADT_Database::update_subscription( (int) $existing['id'], [ 'subscriber_id' => $subscriber_id ] );
                $existing['subscriber_id'] = $subscriber_id;
            }
            return $existing;
        }

        $start = current_time( 'mysql', true );
        $end   = gmdate( 'Y-m-d H:i:s', strtotime( '+' . self::TRIAL_DAYS . ' days', strtotime( $start ) ) );
        $id    = ADT_Database::insert_subscription( [
            'user_id'        => $user_id,
            'subscriber_id'  => $subscriber_id ?: null,
            'status'         => self::STATUS_TRIALING,
            'trial_start_at' => $start,
            'trial_end_at'   => $end,
        ] );

        ADT_Database::insert_event( 'trial_started', [
            'user_id'         => $user_id,
            'subscription_id' => $id,
            'actor_type'      => 'customer',
            'actor_id'        => $user_id,
            'metadata'        => [ 'trial_days' => self::TRIAL_DAYS ],
        ] );

        return ADT_Database::get_subscription_by_user( $user_id ) ?: [];
    }

    public static function activate_annual( int $user_id, int $payment_id = 0 ): array {
        $subscription = self::get_for_user( $user_id );
        if ( ! $subscription ) {
            $subscriber   = ADT_Database::find_subscriber_by_user( $user_id );
            $subscription = self::ensure_trial( $user_id, (int) ( $subscriber['id'] ?? 0 ) );
        }

        $start = current_time( 'mysql', true );
        $end   = gmdate( 'Y-m-d H:i:s', strtotime( '+12 months', strtotime( $start ) ) );

        ADT_Database::update_subscription( (int) $subscription['id'], [
            'status'                => self::STATUS_ACTIVE,
            'subscription_start_at' => $start,
            'subscription_end_at'   => $end,
            'cancel_at_period_end'  => 0,
            'canceled_at'           => null,
        ] );

        ADT_Database::insert_event( 'subscription_activated', [
            'user_id'         => $user_id,
            'subscription_id' => (int) $subscription['id'],
            'actor_type'      => 'system',
            'metadata'        => [
                'payment_id'   => $payment_id,
                'coverage_end' => $end,
            ],
        ] );

        return self::get_for_user( $user_id ) ?: [];
    }

    public static function request_cancel_at_period_end( int $user_id ): bool {
        $subscription = self::get_for_user( $user_id );
        if ( ! $subscription ) {
            return false;
        }

        // En prueba se conserva el estado trialing para mantener el acceso hasta
        // trial_end_at. En una suscripción pagada se usa el estado explícito de
        // término programado hasta subscription_end_at.
        $status = self::STATUS_TRIALING === $subscription['status']
            ? self::STATUS_TRIALING
            : self::STATUS_CANCEL_AT_PERIOD_END;

        $updated = ADT_Database::update_subscription( (int) $subscription['id'], [
            'status'               => $status,
            'cancel_at_period_end' => 1,
            'canceled_at'          => current_time( 'mysql', true ),
        ] );

        if ( $updated ) {
            ADT_Database::insert_event( 'cancellation_requested', [
                'user_id'         => $user_id,
                'subscription_id' => (int) $subscription['id'],
                'actor_type'      => 'customer',
                'actor_id'        => $user_id,
            ] );
        }

        return $updated;
    }

    public static function undo_cancel( int $user_id ): bool {
        $subscription = self::get_for_user( $user_id );
        if ( ! $subscription || empty( $subscription['cancel_at_period_end'] ) ) {
            return false;
        }

        $status = ! empty( $subscription['subscription_end_at'] )
            ? self::STATUS_ACTIVE
            : self::STATUS_TRIALING;

        $updated = ADT_Database::update_subscription( (int) $subscription['id'], [
            'status'               => $status,
            'cancel_at_period_end' => 0,
            'canceled_at'          => null,
        ] );

        if ( $updated ) {
            ADT_Database::insert_event( 'cancellation_reverted', [
                'user_id'         => $user_id,
                'subscription_id' => (int) $subscription['id'],
                'actor_type'      => 'customer',
                'actor_id'        => $user_id,
            ] );
        }
        return $updated;
    }

    public static function update_notification_status( int $user_id, string $status ): bool {
        $subscriber = ADT_Database::find_subscriber_by_user( $user_id );
        if ( ! $subscriber ) {
            return false;
        }

        $updated = ADT_Database::update_notification_status( (int) $subscriber['id'], $status );
        if ( $updated ) {
            $subscription = self::get_for_user( $user_id );
            ADT_Database::insert_event( 'notification_status_changed', [
                'user_id'         => $user_id,
                'subscription_id' => (int) ( $subscription['id'] ?? 0 ),
                'actor_type'      => 'customer',
                'actor_id'        => $user_id,
                'metadata'        => [ 'notification_status' => $status ],
            ] );
        }
        return $updated;
    }

    public static function is_eligible( ?array $subscriber, ?array $subscription ): bool {
        if ( ! $subscriber || 'active' !== ( $subscriber['status'] ?? '' ) || empty( $subscriber['consent'] ) ) {
            return false;
        }
        if ( 'active' !== ( $subscriber['notification_status'] ?? 'active' ) ) {
            return false;
        }

        // Compatibilidad de transición: suscriptores antiguos sin cuenta siguen activos.
        if ( empty( $subscriber['wp_user_id'] ) ) {
            return true;
        }
        if ( ! $subscription ) {
            return false;
        }

        $now    = time();
        $status = (string) ( $subscription['status'] ?? '' );
        if ( self::STATUS_TRIALING === $status ) {
            return ! empty( $subscription['trial_end_at'] ) && strtotime( $subscription['trial_end_at'] . ' UTC' ) >= $now;
        }
        if ( in_array( $status, [ self::STATUS_ACTIVE, self::STATUS_CANCEL_AT_PERIOD_END ], true ) ) {
            return ! empty( $subscription['subscription_end_at'] ) && strtotime( $subscription['subscription_end_at'] . ' UTC' ) >= $now;
        }
        return false;
    }

    public static function is_row_eligible( array $row ): bool {
        $subscriber = [
            'status'              => $row['status'] ?? '',
            'consent'             => $row['consent'] ?? 0,
            'notification_status' => $row['notification_status'] ?? 'active',
            'wp_user_id'          => $row['wp_user_id'] ?? null,
        ];
        $subscription = ! empty( $row['subscription_id'] ) ? [
            'status'              => $row['subscription_status'] ?? '',
            'trial_end_at'        => $row['trial_end_at'] ?? null,
            'subscription_end_at' => $row['subscription_end_at'] ?? null,
        ] : null;
        return self::is_eligible( $subscriber, $subscription );
    }

    public static function status_label( string $status ): string {
        $labels = [
            self::STATUS_TRIALING             => 'Prueba gratuita',
            self::STATUS_AWAITING_PAYMENT     => 'Pago pendiente',
            self::STATUS_ACTIVE               => 'Plan anual activo',
            self::STATUS_PAST_DUE             => 'Pago vencido',
            self::STATUS_CANCEL_AT_PERIOD_END => 'Término programado',
            self::STATUS_EXPIRED              => 'Vencida',
            self::STATUS_PAUSED               => 'Pausada',
            self::STATUS_BLOCKED              => 'Bloqueada',
        ];
        return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
    }

    public static function access_end_at( array $subscription ): ?string {
        if ( ! empty( $subscription['subscription_end_at'] ) ) {
            return $subscription['subscription_end_at'];
        }
        return $subscription['trial_end_at'] ?? null;
    }

    private static function refresh_expiration( array $subscription ): array {
        $now = time();
        if ( self::STATUS_TRIALING === $subscription['status'] && ! empty( $subscription['trial_end_at'] ) ) {
            if ( strtotime( $subscription['trial_end_at'] . ' UTC' ) < $now ) {
                $next_status = ! empty( $subscription['cancel_at_period_end'] )
                    ? self::STATUS_EXPIRED
                    : self::STATUS_AWAITING_PAYMENT;
                ADT_Database::update_subscription( (int) $subscription['id'], [ 'status' => $next_status ] );
                $subscription['status'] = $next_status;
            }
        }

        if ( in_array( $subscription['status'], [ self::STATUS_ACTIVE, self::STATUS_CANCEL_AT_PERIOD_END ], true ) && ! empty( $subscription['subscription_end_at'] ) ) {
            if ( strtotime( $subscription['subscription_end_at'] . ' UTC' ) < $now ) {
                ADT_Database::update_subscription( (int) $subscription['id'], [
                    'status' => self::STATUS_EXPIRED,
                ] );
                $subscription['status'] = self::STATUS_EXPIRED;
            }
        }
        return $subscription;
    }
}

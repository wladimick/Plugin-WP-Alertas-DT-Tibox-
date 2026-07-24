<?php
defined( 'ABSPATH' ) || exit;

interface ADT_Payment_Provider {
    public function get_name(): string;
    public function create_order( int $user_id, int $amount_clp ): array;
    public function confirm_order( array $order ): array;
}

class ADT_Simulated_Payment_Provider implements ADT_Payment_Provider {

    public function get_name(): string {
        return 'simulated';
    }

    public function create_order( int $user_id, int $amount_clp ): array {
        return [
            'provider'    => $this->get_name(),
            'external_id' => 'sim_' . wp_generate_uuid4(),
            'user_id'     => $user_id,
            'amount_clp'  => $amount_clp,
            'currency'    => 'CLP',
            'status'      => 'created',
        ];
    }

    public function confirm_order( array $order ): array {
        $order['status']        = 'authorized';
        $order['authorized_at'] = current_time( 'mysql', true );
        return $order;
    }
}

class ADT_Payments {

    public static function annual_price_clp(): int {
        // $100 CLP es solo un valor referencial para esta etapa sin cobros reales.
        return max( 1, (int) apply_filters( 'adt_annual_price_clp', 100 ) );
    }

    public static function simulated_enabled(): bool {
        $default = function_exists( 'wp_get_environment_type' )
            ? 'production' !== wp_get_environment_type()
            : ( defined( 'WP_DEBUG' ) && WP_DEBUG );
        return (bool) apply_filters( 'adt_enable_simulated_payments', $default );
    }

    public static function provider(): ADT_Payment_Provider {
        return new ADT_Simulated_Payment_Provider();
    }

    public static function simulate_annual_payment( int $user_id ): array {
        if ( ! self::simulated_enabled() ) {
            throw new RuntimeException( 'El proveedor de pago simulado no está disponible en producción.' );
        }

        $subscription = ADT_Subscriptions::get_for_user( $user_id );
        if ( ! $subscription ) {
            $subscriber   = ADT_Database::find_subscriber_by_user( $user_id );
            $subscription = ADT_Subscriptions::ensure_trial( $user_id, (int) ( $subscriber['id'] ?? 0 ) );
        }

        $provider = self::provider();
        $order    = $provider->create_order( $user_id, self::annual_price_clp() );
        $order    = $provider->confirm_order( $order );
        $start    = current_time( 'mysql', true );
        $end      = gmdate( 'Y-m-d H:i:s', strtotime( '+12 months', strtotime( $start ) ) );

        $payment_id = ADT_Database::insert_payment( [
            'user_id'           => $user_id,
            'subscription_id'   => (int) ( $subscription['id'] ?? 0 ),
            'provider'          => $order['provider'],
            'external_id'       => $order['external_id'],
            'amount_clp'        => $order['amount_clp'],
            'currency'          => $order['currency'],
            'status'            => $order['status'],
            'coverage_start_at' => $start,
            'coverage_end_at'   => $end,
            'authorized_at'     => $order['authorized_at'],
        ] );

        $updated_subscription = ADT_Subscriptions::activate_annual( $user_id, $payment_id );

        ADT_Database::insert_event( 'payment_authorized', [
            'user_id'         => $user_id,
            'subscription_id' => (int) ( $updated_subscription['id'] ?? 0 ),
            'actor_type'      => 'simulator',
            'metadata'        => [
                'payment_id' => $payment_id,
                'provider'   => $provider->get_name(),
                'amount_clp' => self::annual_price_clp(),
            ],
        ] );

        return [
            'payment_id'  => $payment_id,
            'subscription' => $updated_subscription,
        ];
    }
}

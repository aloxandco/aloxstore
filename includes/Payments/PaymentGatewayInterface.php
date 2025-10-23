<?php
namespace AloxStore\Payments;

if ( ! defined( 'ABSPATH' ) ) exit;

interface PaymentGatewayInterface {
    public function id(): string;
    public function label(): string;
    public function is_enabled(): bool;

    /**
     * Create a checkout session/flow from a cart structure.
     * @param array $cart Enriched cart array
     * @return array ['id' => string|null, 'url' => string] or throws \WP_Error via caller
     */
    public function create_checkout( array $cart ): array;

    /**
     * Handle gateway webhook and return a WP_REST_Response (200 if ok).
     */
    public function handle_webhook( \WP_REST_Request $request ): \WP_REST_Response;
}

<?php
// /aloxstore/templates/checkout-success.php
get_header();
?>

    <div class="container py-5">
        <h1 class="mb-4">Thank you for your order!</h1>
        <p>Your payment has been received and your order is now being processed.</p>

        <?php if ( isset( $_GET['session_id'] ) ) : ?>
            <p><strong>Session ID:</strong> <?php echo esc_html( $_GET['session_id'] ); ?></p>
        <?php endif; ?>

        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary mt-4">Return to Shop</a>
    </div>

<?php
get_footer();

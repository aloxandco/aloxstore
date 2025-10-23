<?php
namespace AloxStore\CPT;

if (!defined('ABSPATH')) exit;

class Coupons {
    public static function register() {
        register_post_type('alox_coupon', [
            'labels' => [
                'name' => __('Alox Coupons', 'aloxstore'),
                'singular_name' => __('Alox Coupon', 'aloxstore'),
            ],
            'public' => false,
            'show_ui' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-tickets',
        ]);

        self::meta('code','string','');
        self::meta('discount_type','string','percent'); // percent|fixed
        self::meta('amount','integer',0);
        self::meta('applies_to','string','all'); // all|products|categories
        self::meta('product_ids','string',''); // CSV of IDs
        self::meta('category_ids','string',''); // CSV of term IDs
        self::meta('usage_limit_total','integer',0);
        self::meta('usage_limit_per_user','integer',0);
        self::meta('min_subtotal_cents','integer',0);
        self::meta('free_shipping','boolean',false);
        self::meta('valid_from','string','');
        self::meta('valid_until','string','');
        self::meta('stackable','boolean',false);
    }

    protected static function meta($key,$type,$default){
        register_post_meta('alox_coupon',$key,[
            'type'=>$type,'single'=>true,'default'=>$default,'show_in_rest'=>true,
            'auth_callback'=>fn()=> current_user_can('edit_posts')
        ]);
    }
}

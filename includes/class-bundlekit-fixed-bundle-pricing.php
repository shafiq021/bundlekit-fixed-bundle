<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cart behaviour for Fixed Bundle.
 * The bundle box is a checkbox inside the product's own add-to-cart form
 * (v1.3.0 — there is no separate bundle form or button any more).
 *
 * v1.4.0 model (user request, 28 Oct 2026): ONE cart line, not one line
 * per bundle product. When the main Add to cart button is clicked:
 *  - checkbox ticked   -> the whole bundle is added as ONE line: the main
 *    product, priced at the WHOLE bundle total, with the bundle name and
 *    the list of included products shown on the line. The bundle products
 *    are NOT added as separate cart lines.
 *  - checkbox unticked -> only the single product is added, at its normal
 *    price, with no bundle meta.
 * Removing the one bundle line removes the whole bundle, automatically.
 *
 * How the ticked state reaches the server — two paths, because themes do
 * not all submit Add to cart the same way:
 *  1. Normal form POST: the checkbox sits inside form.cart, so the browser
 *     sends `bundlekit_bundle=1` with the form. (Classic themes, and the
 *     block Add to Cart Form, whose view script only handles the quantity
 *     stepper — the submit itself is a normal POST.)
 *  2. Session flag: when the checkbox is ticked/unticked, frontend.js
 *     posts the state to the `bundlekit_set_bundle` wc-ajax endpoint,
 *     which stores it in the WooCommerce session for that product. The
 *     handler below reads it as a fallback. This covers themes/plugins
 *     whose Add to cart / Buy now buttons submit via AJAX and only send
 *     product id + quantity (so the checkbox field never arrives) — that
 *     is why ticked bundles were silently added as single products on
 *     the user's theme. The flag is consumed (cleared) when the bundle
 *     is added, and the JS also re-sends the current state on page load,
 *     so a stale flag from an earlier visit can never bundle a later,
 *     unticked add.
 */
class BundleKit_Fixed_Bundle_Pricing {

	private static $bundling = false;

	public static function init() {
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'add_bundle' ), 21, 6 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_prices' ), 9999 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'get_cart_item_from_session' ), 10, 2 );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'cart_item_removed' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 3 );
		// Session-flag endpoint for the checkbox state (guests included).
		add_action( 'wc_ajax_bundlekit_set_bundle', array( __CLASS__, 'set_bundle_flag' ) );
	}

	/**
	 * Remember the checkbox state in the WooCommerce session, for the
	 * product the box belongs to. Called by frontend.js on every tick /
	 * untick, and once on page load (to clear a stale flag).
	 * Only ever stores a 0/1 for the user's own cart; nothing else.
	 */
	public static function set_bundle_flag() {
		// Global kill-switch: when Fixed Bundle is off, no flag is
		// stored (the box does not render, so this is only defense).
		if ( ! BundleKit_Fixed_Bundle::enabled() ) {
			wp_send_json_success();
		}
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce      = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $product_id && wp_verify_nonce( $nonce, 'bundlekit_bundle' ) && WC()->session ) {
			$checked = ! empty( $_POST['checked'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			WC()->session->set( 'bundlekit_bundle_' . $product_id, $checked ? 1 : 0 );
			if ( $checked && method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
				/*
				 * Make the session real. A guest who has only viewed the
				 * product page has NO session cookie yet, and WooCommerce
				 * refuses to save session data without one (has_session()
				 * is false), so the flag would be lost with the request
				 * and the later Add to cart would see nothing. Setting
				 * the cookie here makes the flag persist to the next
				 * request. (Unticking needs no cookie: 0 is the default.)
				 */
				WC()->session->set_customer_session_cookie( true );
			}
			WC()->session->save_data();
		}
		wp_send_json_success();
	}

	/** Session key for the checkbox flag of one product. */
	private static function flag_key( $product_id ) {
		return 'bundlekit_bundle_' . (int) $product_id;
	}

	public static function add_bundle( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( self::$bundling ) {
			return;
		}
		// Global kill-switch (BundleKit > Fixed Bundle > Settings):
		// when Fixed Bundle is off site-wide, never bundle — and
		// clear any session flag for this product, so it cannot
		// bundle a later add after the feature is turned back on.
		if ( ! BundleKit_Fixed_Bundle::enabled() ) {
			if ( WC()->session ) {
				WC()->session->set( self::flag_key( $product_id ), 0 );
			}
			return;
		}
		// Path 1: the checkbox field arrived with the form POST.
		$checked = ! empty( $_REQUEST['bundlekit_bundle'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// Path 2: the session flag, set by frontend.js when the box was ticked.
		if ( ! $checked && WC()->session ) {
			$checked = (bool) WC()->session->get( self::flag_key( $product_id ) );
		}
		if ( ! $checked ) {
			return;
		}
		$cfg = BundleKit_Fixed_Bundle::config( $product_id );
		if ( ! $cfg['on'] ) {
			return;
		}
		$all_ids = array_merge( array( (int) $product_id ), $cfg['items'] );
		$calc    = BundleKit_Fixed_Bundle::totals( $all_ids, $cfg['type'], $cfg['value'] );
		if ( $calc['total'] <= 0 ) {
			return;
		}

		self::$bundling = true;
		// ONE line only: the main product, priced at the whole bundle total.
		if ( isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
			WC()->cart->cart_contents[ $cart_item_key ]['bundlekit_bundle_price'] = $calc['total'];
			WC()->cart->cart_contents[ $cart_item_key ]['bundlekit_bundle_name']  = $cfg['name'];
			WC()->cart->cart_contents[ $cart_item_key ]['bundlekit_bundle_items'] = $all_ids;
		}
		// Consume the session flag, so a later add of this product is not
		// bundled by mistake.
		if ( WC()->session ) {
			WC()->session->set( self::flag_key( $product_id ), 0 );
		}
		/*
		 * Save the session again now. WooCommerce saves the cart session
		 * at the end of calculate_totals(), which runs on
		 * woocommerce_add_to_cart at priority 20 — BEFORE this hook
		 * (priority 21) runs, and before the bundle meta above is set.
		 * Without this second save, the bundle price/meta never reaches
		 * the session and the next request loads the line at full price.
		 */
		WC()->cart->calculate_totals();
		self::$bundling = false;
		unset( $_REQUEST['bundlekit_bundle'] );
	}

	public static function apply_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item['bundlekit_bundle_price'] ) ) {
				$item['data']->set_price( (float) $item['bundlekit_bundle_price'] );
			}
		}
	}

	public static function get_cart_item_from_session( $cart_item, $values ) {
		foreach ( array( 'bundlekit_bundle_price', 'bundlekit_bundle_name', 'bundlekit_bundle_items', 'bundlekit_bundle_parent_key', 'bundlekit_bundle_children' ) as $k ) {
			if ( isset( $values[ $k ] ) ) {
				$cart_item[ $k ] = $values[ $k ];
			}
		}
		return $cart_item;
	}

	/**
	 * Safety net for carts built by v1.3.0 (one line per bundle product):
	 * if a removed line still has bundle children, remove them too.
	 * With the v1.4.0 single-line model there are no children to remove.
	 */
	public static function cart_item_removed( $cart_item_key, $cart ) {
		$removed = $cart->removed_cart_contents[ $cart_item_key ] ?? null;
		if ( ! $removed ) {
			return;
		}
		$children = (array) ( $removed['bundlekit_bundle_children'] ?? array() );
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['bundlekit_bundle_parent_key'] ) && $item['bundlekit_bundle_parent_key'] === $cart_item_key ) {
				$children[] = $key;
			}
		}
		foreach ( array_unique( $children ) as $child_key ) {
			$cart->remove_cart_item( $child_key );
		}
	}

	/** Names of the products in a bundle, for cart / order display. */
	private static function item_names( $cart_item ) {
		$names = array();
		foreach ( (array) ( $cart_item['bundlekit_bundle_items'] ?? array() ) as $id ) {
			$p = wc_get_product( (int) $id );
			if ( $p ) {
				$names[] = $p->get_name();
			}
		}
		return $names;
	}

	public static function item_data( $item_data, $cart_item ) {
		if ( ! isset( $cart_item['bundlekit_bundle_price'] ) ) {
			return $item_data;
		}
		$name = ! empty( $cart_item['bundlekit_bundle_name'] ) ? $cart_item['bundlekit_bundle_name'] : __( 'Bundle', 'bundlekit-fixed-bundle' );
		$item_data[] = array(
			'key'   => __( 'Bundle offer', 'bundlekit-fixed-bundle' ),
			'value' => $name,
		);
		$names = self::item_names( $cart_item );
		if ( $names ) {
			$item_data[] = array(
				'key'   => __( 'Includes', 'bundlekit-fixed-bundle' ),
				'value' => implode( ', ', $names ),
			);
		}
		return $item_data;
	}

	public static function order_item_meta( $item, $cart_item_key, $values ) {
		if ( isset( $values['bundlekit_bundle_name'] ) ) {
			$item->add_meta_data( __( 'Bundle', 'bundlekit-fixed-bundle' ), $values['bundlekit_bundle_name'], true );
		}
		$names = self::item_names( $values );
		if ( $names ) {
			$item->add_meta_data( __( 'Includes', 'bundlekit-fixed-bundle' ), implode( ', ', $names ), true );
		}
	}
}

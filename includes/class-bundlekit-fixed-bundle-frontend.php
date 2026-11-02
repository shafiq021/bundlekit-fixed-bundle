<?php
defined( 'ABSPATH' ) || exit;

/**
 * Frontend: show the Fixed Bundle box on a simple product page.
 * Own CSS + JS files, enqueued ONLY when a bundle box is actually shown.
 *
 * v1.4.0: the bundle is added as ONE cart line (the main product at the
 * whole bundle total), not one line per bundle product. The ticked state
 * reaches the server two ways: the checkbox inside form.cart for normal
 * POSTs, and a session flag set by frontend.js (wc-ajax) for themes whose
 * Add to cart / Buy now buttons submit via AJAX with only id + quantity.
 * See class-bundlekit-fixed-bundle-pricing.php for the full explanation.
 *
 * Layout — v1.4.1: when a bundle box is present, the whole form.cart
 * stacks vertically (box, then quantity, then buttons, each full
 * width). v1.4.0 only made the form wrap, which the user's theme row
 * rules beat, so quantity / Add to cart were pushed off the
 * container's right edge and cut off. The CSS now forces column
 * direction (with !important, scoped to forms containing the box)
 * and caps every row at 100% width, so nothing overflows.
 *
 * Style 2 (v1.5.0): a card layout picked per product in the BundleKit
 * tab (meta _bundlekit_bundle_layout). Same outer div class and data
 * attributes as style 1, so the same frontend.js binds to it — only
 * the inner markup differs: header with the main product image,
 * "What's included" list with x1 badges, footer with the bundle
 * total and a Breakdown popup. The heading shows the bundle name
 * (cfg['name']), like the reference design, not the style-1 title.
 *
 * Why the old button did not work on the user's site:
 * v1.2.x rendered the box as its own <form> with its own submit button.
 * On block themes that box ended up inside / around the main add-to-cart
 * form's rendering, and a nested or intercepted form submit does nothing
 * useful in a browser. With the checkbox design there is only ONE form
 * (the product's own add-to-cart form) and ONE submit button, so there is
 * nothing to nest or intercept.
 *
 * Placement — the checkbox MUST be inside the main add-to-cart <form>,
 * otherwise the browser will not send it when Add to cart is clicked:
 *  - Primary hook: `woocommerce_before_add_to_cart_quantity` — fires
 *    INSIDE <form class="cart"> in simple.php, which both the classic
 *    template and WooCommerce's block Add to Cart Form call. The box
 *    lands inside the form, just before the quantity / Add to cart button.
 *  - Fallbacks: `woocommerce_after_add_to_cart_form` (after </form>) and
 *    `woocommerce_after_single_product_summary` (classic, after the tabs).
 *    If the box lands outside the form, frontend.js moves it inside
 *    form.cart on load.
 *  - Deliberately NOT hooked to `woocommerce_single_product_summary`:
 *    on block themes WooCommerce's template-compatibility layer runs
 *    that hook BEFORE the excerpt block — before the add-to-cart form
 *    even exists in the page — so the box would land outside the form
 *    and the once-only guard would then block the in-form hook.
 * A static flag guarantees the box is printed exactly once per page load.
 * Deliberately NOT using `the_content`: on block themes that filter runs
 * while the product summary/excerpt block is generated, and the box HTML
 * gets stripped down to plain text inside the excerpt.
 */
class BundleKit_Fixed_Bundle_Frontend {

	/** Product IDs the box has already been printed for, this request. */
	private static $rendered = array();

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		// Primary: inside the main add-to-cart form, before quantity/button.
		add_action( 'woocommerce_before_add_to_cart_quantity', array( __CLASS__, 'bundle_box' ) );
		// Fallbacks: after the form closes, and after the summary tabs.
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'bundle_box' ) );
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'bundle_box' ), 5 );
	}

	public static function register_assets() {
		wp_register_style( 'bundlekit-fixed-bundle', BUNDLEKIT_FIXED_BUNDLE_URL . 'assets/css/frontend.css', array(), BUNDLEKIT_FIXED_BUNDLE_VERSION );
		wp_register_script( 'bundlekit-fixed-bundle', BUNDLEKIT_FIXED_BUNDLE_URL . 'assets/js/frontend.js', array(), BUNDLEKIT_FIXED_BUNDLE_VERSION, true );
	}

	/**
	 * Prints the box once, if it applies. Called from the hooks above.
	 */
	public static function bundle_box() {
		global $product;
		echo self::box_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build the bundle box HTML for a product, or '' if it must not show.
	 * Returns '' (and prints nothing) when:
	 *  - not a simple, purchasable product,
	 *  - the bundle is off, or every bundle item was filtered out
	 *    (items must be purchasable AND in stock — see config()),
	 *  - the bundle total is 0 or less (e.g. all prices are 0),
	 *  - the box was already printed for this product on this page.
	 *
	 * The box is a plain <div> with a checkbox named `bundlekit_bundle`.
	 * It is NOT a form and has NO submit button — the product's own
	 * Add to cart button submits it. Unchecked, the browser sends no
	 * `bundlekit_bundle` field at all, so the cart handler adds only the
	 * single product.
	 *
	 * @param WC_Product|null $product Product to render for.
	 * @return string
	 */
	private static function box_html( $product ) {
		if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_purchasable() ) {
			return '';
		}
		// Global kill-switch (BundleKit > Fixed Bundle > Settings):
		// when Fixed Bundle is off site-wide, no box renders anywhere.
		if ( ! BundleKit_Fixed_Bundle::enabled() ) {
			return '';
		}
		$product_id = $product->get_id();
		if ( isset( self::$rendered[ $product_id ] ) ) {
			return '';
		}
		$cfg = BundleKit_Fixed_Bundle::config( $product_id );
		if ( ! $cfg['on'] ) {
			return '';
		}
		$all_ids = array_merge( array( $product_id ), $cfg['items'] );
		$calc    = BundleKit_Fixed_Bundle::totals( $all_ids, $cfg['type'], $cfg['value'] );
		if ( $calc['total'] <= 0 ) {
			return '';
		}
		self::$rendered[ $product_id ] = true;
		wp_enqueue_style( 'bundlekit-fixed-bundle' );
		wp_enqueue_script( 'bundlekit-fixed-bundle' );

		$style_attr = self::style_attr( $cfg['style'] );
		// Display toggles (BundleKit > Fixed Bundle > Display):
		// one extra class per hidden element, e.g. bk-no-badge.
		$display_classes = BundleKit_Fixed_Bundle::display_classes();
		$display_attr    = $display_classes ? ' ' . implode( ' ', $display_classes ) : '';
		if ( 'style2' === $cfg['layout'] ) {
			return self::box_html_style2( $product_id, $cfg, $calc, $all_ids, $style_attr, $display_attr );
		}
		return self::box_html_style1( $product_id, $cfg, $calc, $all_ids, $style_attr, $display_attr );
	}

	/**
	 * Build the box's style attribute from the saved styling (1.6.0):
	 * one CSS variable per set control, e.g.
	 * --bk-box-radius:16px;--bk-title-color:#111827. Empty controls
	 * are left out, so the CSS fallbacks (the default look) apply.
	 * Only ever outputs values sanitized by global_style_values(),
	 * and font stacks only from the font_stacks() allow-list.
	 *
	 * @param array<string,string> $style Sanitized styling values.
	 */
	public static function style_attr( $style ) {
		$vars = self::var_map();
		$stacks = BundleKit_Fixed_Bundle::font_stacks();
		$parts  = array();
		foreach ( $vars as $var => $spec ) {
			list( $field, $format ) = $spec;
			$v = isset( $style[ $field ] ) ? $style[ $field ] : '';
			if ( '' === $v ) {
				continue;
			}
			if ( 'px' === $format ) {
				$v .= 'px';
			} elseif ( 'font' === $format ) {
				$v = isset( $stacks[ $v ] ) ? $stacks[ $v ] : '';
				if ( '' === $v ) {
					continue;
				}
			}
			$parts[] = $var . ':' . $v;
		}
		return $parts ? ' style="' . esc_attr( implode( ';', $parts ) ) . '"' : '';
	}

	/**
	 * The field → CSS variable map, shared by style_attr() (frontend)
	 * and the styling-page live preview (admin, via wp_localize_script).
	 * Keys are style field keys; values are [variable, format] where
	 * format is 'raw' (colors, weights), 'px' (numbers) or 'font'
	 * (font-stack key).
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function var_map() {
		return array(
			'--bk-box-bg'           => array( 'box_bg', 'raw' ),
			'--bk-box-border'       => array( 'box_border', 'raw' ),
			'--bk-box-border-width' => array( 'box_border_width', 'px' ),
			'--bk-box-radius'       => array( 'box_radius', 'px' ),
			'--bk-box-padding'      => array( 'box_padding', 'px' ),
			'--bk-title-font'       => array( 'title_font', 'font' ),
			'--bk-title-size'       => array( 'title_size', 'px' ),
			'--bk-title-weight'     => array( 'title_weight', 'raw' ),
			'--bk-title-color'      => array( 'title_color', 'raw' ),
			'--bk-item-font'        => array( 'item_font', 'font' ),
			'--bk-item-size'        => array( 'item_size', 'px' ),
			'--bk-item-weight'      => array( 'item_weight', 'raw' ),
			'--bk-item-color'       => array( 'item_color', 'raw' ),
			'--bk-price-size'       => array( 'price_size', 'px' ),
			'--bk-price-weight'     => array( 'price_weight', 'raw' ),
			'--bk-price-color'      => array( 'price_color', 'raw' ),
			'--bk-total-size'       => array( 'total_size', 'px' ),
			'--bk-total-weight'     => array( 'total_weight', 'raw' ),
			'--bk-total-color'      => array( 'total_color', 'raw' ),
			'--bk-badge-bg'         => array( 'badge_bg', 'raw' ),
			'--bk-badge-color'      => array( 'badge_color', 'raw' ),
			'--bk-save-color'       => array( 'save_color', 'raw' ),
			'--bk-checked-border'   => array( 'checked_border', 'raw' ),
			'--bk-checked-bg'       => array( 'checked_bg', 'raw' ),
			'--bk-checkbox-color'   => array( 'checkbox_color', 'raw' ),
			'--bk-hint-size'        => array( 'hint_size', 'px' ),
			'--bk-hint-color'       => array( 'hint_color', 'raw' ),
			'--bk-check-bg'         => array( 'check_bg', 'raw' ),
			'--bk-eyebrow-color'    => array( 'eyebrow_color', 'raw' ),
			'--bk-eyebrow-size'     => array( 'eyebrow_size', 'px' ),
			'--bk-included-color'   => array( 'included_color', 'raw' ),
			'--bk-included-size'    => array( 'included_size', 'px' ),
			'--bk-qty-bg'           => array( 'qty_bg', 'raw' ),
			'--bk-qty-color'        => array( 'qty_color', 'raw' ),
			'--bk-popup-bg'         => array( 'popup_bg', 'raw' ),
			'--bk-popup-border'     => array( 'popup_border', 'raw' ),
			'--bk-popup-radius'     => array( 'popup_radius', 'px' ),
		);
	}

	/**
	 * Style 1 — the classic list box (the only layout before 1.5.0).
	 */
	private static function box_html_style1( $product_id, $cfg, $calc, $all_ids, $style_attr, $display_attr = '' ) {
		$title = '' !== $cfg['title'] ? $cfg['title'] : __( 'Complete the bundle', 'bundlekit-fixed-bundle' );
		ob_start();
		?>
		<div class="bundlekit-bundle-box<?php echo esc_attr( $display_attr ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-bundlekit-box data-product-id="<?php echo esc_attr( $product_id ); ?>" data-ajax-url="<?php echo esc_url( WC_AJAX::get_endpoint( 'bundlekit_set_bundle' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'bundlekit_bundle' ) ); ?>">
			<label class="bundlekit-bundle-head" for="bundlekit_bundle_<?php echo esc_attr( $product_id ); ?>">
				<input type="checkbox" id="bundlekit_bundle_<?php echo esc_attr( $product_id ); ?>" name="bundlekit_bundle" value="1" class="bundlekit-bundle-checkbox" />
				<span class="bundlekit-bundle-title"><?php echo esc_html( $title ); ?></span>
				<?php if ( '' !== $cfg['badge'] ) : ?>
					<span class="bundlekit-bundle-badge"><?php echo esc_html( $cfg['badge'] ); ?></span>
				<?php endif; ?>
			</label>
			<ul class="bundlekit-bundle-items">
				<?php foreach ( $all_ids as $id ) : $p = wc_get_product( $id ); ?>
					<?php if ( ! $p ) { continue; } ?>
					<li>
						<span class="bundlekit-bundle-thumb"><?php echo $p->get_image( array( 48, 48 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="bundlekit-bundle-name"><?php echo esc_html( $p->get_name() ); ?></span>
						<span class="bundlekit-bundle-price"><?php echo wp_kses_post( wc_price( $p->get_price() ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="bundlekit-bundle-total">
				<?php if ( $calc['save'] > 0 ) : ?>
					<del><?php echo wp_kses_post( wc_price( $calc['regular'] ) ); ?></del>
				<?php endif; ?>
				<strong><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></strong>
				<?php if ( $calc['save'] > 0 ) : ?>
					<span class="bundlekit-bundle-save"><?php
						/* translators: %s: formatted amount the customer saves, e.g. "$10.00". */
						printf( esc_html__( 'You save %s', 'bundlekit-fixed-bundle' ), wp_kses_post( wc_price( $calc['save'] ) ) );
					?></span>
				<?php endif; ?>
			</p>
			<p class="bundlekit-bundle-hint"><?php esc_html_e( 'Tick the box to add the whole bundle as one item at the bundle price, or leave it unticked to add only this product.', 'bundlekit-fixed-bundle' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Style 2 — card layout (new in 1.5.0): header with the main
	 * product image, "What's included" list with x1 badges, footer
	 * with the bundle total and a Breakdown popup.
	 *
	 * The tick-box logic is identical to style 1: the outer div keeps
	 * the bundlekit-bundle-box class and data attributes, so the same
	 * frontend.js binds to it — clicking anywhere on the card (except
	 * the Breakdown button / popup) ticks/unticks the checkbox, the
	 * checkbox inside form.cart covers normal POSTs, and the session
	 * flag covers AJAX Add to cart. The checkbox itself is visually
	 * hidden in this style (the reference design has none); the
	 * green border + check circle show the selected state.
	 * The heading shows the bundle name (cfg['name']), like the
	 * reference design. "What's included" lists the main product
	 * first, then the picked items, so the listed prices sum to the
	 * "Total value" in the popup. Qty is always 1 per item in v1.
	 */
	private static function box_html_style2( $product_id, $cfg, $calc, $all_ids, $style_attr, $display_attr = '' ) {
		$main  = wc_get_product( $product_id );
		$count = count( $all_ids );
		ob_start();
		?>
		<div class="bundlekit-bundle-box bundlekit-bundle-box--style2<?php echo esc_attr( $display_attr ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-bundlekit-box data-product-id="<?php echo esc_attr( $product_id ); ?>" data-ajax-url="<?php echo esc_url( WC_AJAX::get_endpoint( 'bundlekit_set_bundle' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'bundlekit_bundle' ) ); ?>">
			<input type="checkbox" id="bundlekit_bundle_<?php echo esc_attr( $product_id ); ?>" name="bundlekit_bundle" value="1" class="bundlekit-bundle-checkbox" />
			<div class="bundlekit-bundle2-head">
				<span class="bundlekit-bundle2-head-thumb"><?php echo $main ? $main->get_image( array( 56, 56 ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="bundlekit-bundle2-head-text">
					<span class="bundlekit-bundle2-eyebrow"><?php esc_html_e( 'Your bundle', 'bundlekit-fixed-bundle' ); ?></span>
					<span class="bundlekit-bundle2-title"><?php echo esc_html( $cfg['name'] ); ?></span>
					<span class="bundlekit-bundle2-head-price"><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></span>
				</span>
				<?php if ( '' !== $cfg['badge'] ) : ?>
					<span class="bundlekit-bundle-badge"><?php echo esc_html( $cfg['badge'] ); ?></span>
				<?php endif; ?>
				<span class="bundlekit-bundle2-check" aria-hidden="true">✓</span>
			</div>
			<p class="bundlekit-bundle2-included"><?php esc_html_e( "What's included", 'bundlekit-fixed-bundle' ); ?></p>
			<p class="bundlekit-bundle2-count"><?php
				/* translators: %d: number of products inside the bundle. */
				echo esc_html( sprintf( _n( '%d item inside', '%d items inside', $count, 'bundlekit-fixed-bundle' ), $count ) );
			?></p>
			<ul class="bundlekit-bundle2-items">
				<?php foreach ( $all_ids as $id ) : $p = wc_get_product( $id ); ?>
					<?php if ( ! $p ) { continue; } ?>
					<li>
						<span class="bundlekit-bundle2-thumb"><?php echo $p->get_image( array( 40, 40 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="bundlekit-bundle2-item-name"><?php echo esc_html( $p->get_name() ); ?></span>
						<span class="bundlekit-bundle2-item-price"><?php echo wp_kses_post( wc_price( $p->get_price() ) ); ?></span>
						<span class="bundlekit-bundle2-qty">×1</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="bundlekit-bundle2-foot">
				<span class="bundlekit-bundle2-total"><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></span>
				<?php if ( $calc['save'] > 0 ) : ?>
					<del class="bundlekit-bundle2-regular"><?php echo wp_kses_post( wc_price( $calc['regular'] ) ); ?></del>
				<?php endif; ?>
				<button type="button" class="bundlekit-bundle2-breakdown" aria-expanded="false"><?php esc_html_e( 'Breakdown', 'bundlekit-fixed-bundle' ); ?> <span class="bundlekit-bundle2-chevron" aria-hidden="true">∨</span></button>
				<div class="bundlekit-bundle2-popup">
					<p class="bundlekit-bundle2-popup-head"><?php esc_html_e( 'Price breakdown', 'bundlekit-fixed-bundle' ); ?></p>
					<div class="bundlekit-bundle2-popup-row bundlekit-bundle2-popup-bundle">
						<span><?php echo esc_html( $cfg['name'] ); ?></span>
						<strong><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></strong>
					</div>
					<div class="bundlekit-bundle2-popup-items">
						<?php foreach ( $all_ids as $id ) : $p = wc_get_product( $id ); ?>
							<?php if ( ! $p ) { continue; } ?>
							<div class="bundlekit-bundle2-popup-row">
								<span><?php echo esc_html( $p->get_name() ); ?></span>
								<span><?php echo wp_kses_post( wc_price( $p->get_price() ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="bundlekit-bundle2-popup-row bundlekit-bundle2-popup-total">
						<span><?php esc_html_e( 'Total value', 'bundlekit-fixed-bundle' ); ?></span>
						<span><?php echo wp_kses_post( wc_price( $calc['regular'] ) ); ?></span>
					</div>
					<div class="bundlekit-bundle2-popup-row bundlekit-bundle2-popup-pay">
						<span><?php esc_html_e( 'You pay', 'bundlekit-fixed-bundle' ); ?></span>
						<strong><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></strong>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

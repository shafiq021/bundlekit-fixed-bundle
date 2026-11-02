<?php
defined( 'ABSPATH' ) || exit;

/**
 * Recommended Plugins tab.
 *
 * Rule (per spec): only show plugins the client has NOT installed.
 * Installed/active plugins are filtered out here — they are listed
 * on the About tab instead, under "Installed BundleKit add-ons".
 *
 * Each entry:
 *  slug        — unique key
 *  name        — display name
 *  description — one line
 *  plugin      — plugin file path, e.g. bundlekit-fixed-bundle/bundlekit-fixed-bundle.php
 *  url         — info link for the plugin (repo / wordpress.org page);
 *                empty string = "coming soon", no link button
 *  type        — bundlekit-addon | standalone
 *
 * NOTE (wordpress.org): the plugin must never download or install plugin
 * code from servers other than WordPress.org, so this tab only links out —
 * there is no one-click installer here by design.
 */
class BundleKit_Recommended {

	public static function all() {
		$items = array(
			array(
				'slug'         => 'bundlekit-fixed-bundle',
				'name'         => __( 'BundleKit – Fixed Bundle', 'bundlekit-fixed-bundle' ),
				'description'  => __( 'Sell a fixed group of products as one kit, at a fixed price or with a % discount.', 'bundlekit-fixed-bundle' ),
				'plugin'       => 'bundlekit-fixed-bundle/bundlekit-fixed-bundle.php',
				'url'          => 'https://github.com/shafiq021/BundleKit-Fixed-Bundle',
				'type'         => 'bundlekit-addon',
			),
			array(
				'slug'         => 'bundlekit-buy-x-get-y',
				'name'         => __( 'BundleKit – Buy X Get Y / BOGO', 'bundlekit-fixed-bundle' ),
				'description'  => __( 'Buy X, get Y free (or at a discount). Cheapest item free, cart rules, and per-product rules.', 'bundlekit-fixed-bundle' ),
				'plugin'       => 'bundlekit-buy-x-get-y/bundlekit-buy-x-get-y.php',
				'url'          => '',
				'type'         => 'bundlekit-addon',
			),
			array(
				'slug'         => 'bundlekit-multipack',
				'name'         => __( 'BundleKit – Multipack', 'bundlekit-fixed-bundle' ),
				'description'  => __( 'Quantity packs for one product: 2-pack, 3-pack, 5-pack with tiered pricing.', 'bundlekit-fixed-bundle' ),
				'plugin'       => 'bundlekit-multipack/bundlekit-multipack.php',
				'url'          => '',
				'type'         => 'bundlekit-addon',
			),
			array(
				'slug'         => 'bundlekit-mix-match',
				'name'         => __( 'BundleKit – Mix & Match / BYOB', 'bundlekit-fixed-bundle' ),
				'description'  => __( 'Let customers build their own bundle: pick any X products from a category for one price.', 'bundlekit-fixed-bundle' ),
				'plugin'       => 'bundlekit-mix-match/bundlekit-mix-match.php',
				'url'          => '',
				'type'         => 'bundlekit-addon',
			),
			array(
				'slug'         => 'bundlekit-build-a-box',
				'name'         => __( 'BundleKit – Build-a-Box', 'bundlekit-fixed-bundle' ),
				'description'  => __( 'Fill a box: choose products until the box is full, with a progress bar and a fixed box price.', 'bundlekit-fixed-bundle' ),
				'plugin'       => 'bundlekit-build-a-box/bundlekit-build-a-box.php',
				'url'          => '',
				'type'         => 'bundlekit-addon',
			),
			array(
				'slug'         => 'woocommerce-stock-inquiry',
				'name'         => __( 'WooCommerce Stock Inquiry', 'bundlekit-fixed-bundle' ),
				'description'  => __( 'Let customers ask about stock on out-of-stock products. A standalone plugin by the same developer.', 'bundlekit-fixed-bundle' ),
				'plugin'       => 'woocommerce-stock-inquiry/woocommerce-stock-inquiry.php',
				'url'          => '',
				'type'         => 'standalone',
			),
		);
		return apply_filters( 'bundlekit_recommended_plugins', $items );
	}

	public static function is_installed( $item ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		return isset( $plugins[ $item['plugin'] ] );
	}

	public static function is_active( $item ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $item['plugin'] );
	}

	/** Only plugins the client has NOT installed. */
	public static function not_installed() {
		return array_values(
			array_filter(
				self::all(),
				function ( $item ) {
					return ! self::is_installed( $item );
				}
			)
		);
	}

	public static function render() {
		$items = self::not_installed();
		?>
		<div class="bundlekit-card">
			<h2><?php esc_html_e( 'Recommended Plugins', 'bundlekit-fixed-bundle' ); ?></h2>
			<p><?php esc_html_e( 'These are Shafiq Khan’s other plugins. Only plugins you have not installed are shown here — installed ones appear on the About tab.', 'bundlekit-fixed-bundle' ); ?></p>
			<?php if ( empty( $items ) ) : ?>
				<p><strong><?php esc_html_e( 'All recommended plugins are already installed. Nice — you have the full set.', 'bundlekit-fixed-bundle' ); ?></strong></p>
			<?php else : ?>
				<div class="bundlekit-grid">
					<?php foreach ( $items as $item ) : ?>
						<div class="bundlekit-plugin-card" data-slug="<?php echo esc_attr( $item['slug'] ); ?>">
							<h3><?php echo esc_html( $item['name'] ); ?></h3>
							<p><?php echo esc_html( $item['description'] ); ?></p>
							<p><span class="bundlekit-badge"><?php echo 'bundlekit-addon' === $item['type'] ? esc_html__( 'BundleKit add-on', 'bundlekit-fixed-bundle' ) : esc_html__( 'Standalone plugin', 'bundlekit-fixed-bundle' ); ?></span></p>
							<?php if ( ! empty( $item['url'] ) ) : ?>
								<a href="<?php echo esc_url( $item['url'] ); ?>" class="button button-primary" target="_blank" rel="noopener"><?php esc_html_e( 'More info', 'bundlekit-fixed-bundle' ); ?></a>
							<?php else : ?>
								<button type="button" class="button" disabled><?php esc_html_e( 'Coming soon', 'bundlekit-fixed-bundle' ); ?></button>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

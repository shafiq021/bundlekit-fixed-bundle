<?php
/**
 * Plugin Name: BundleKit – Fixed Bundle
 * Plugin URI: https://github.com/shafiq021/BundleKit-Fixed-Bundle
 * Description: Fixed Bundle add-on for BundleKit. Sell a fixed group of products as one kit, at a fixed total price or with a % discount. Install just this plugin — the BundleKit framework is built in, there is no separate BundleKit plugin to install. The bundle box is a tick-box on the product page: tick it and the normal Add to cart button adds the whole bundle as ONE cart line at the bundle price, leave it unticked and only the single product is added. Requires WooCommerce.
 * Version: 1.9.3
 * Author: Shafiq Khan
 * Author URI: https://www.linkedin.com/in/shafiqkhan21/
 * Text Domain: bundlekit-fixed-bundle
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 10.0
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * NOTE — no separate framework plugin (bundled-framework model):
 * The BundleKit framework lives inside this plugin at
 * includes/framework/ and is loaded by the line below. It is NOT a
 * separate plugin, it never appears in the Plugins list, and the user
 * never installs it. Every BundleKit add-on ships the same framework
 * library; only the first loaded copy runs, the rest skip theirs.
 * The old 1.1.0 auto-install of a separate BundleKit plugin is gone.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'BUNDLEKIT_FIXED_BUNDLE_VERSION' ) ) {
	return;
}

define( 'BUNDLEKIT_FIXED_BUNDLE_VERSION', '1.9.3' );
define( 'BUNDLEKIT_FIXED_BUNDLE_FILE', __FILE__ );
define( 'BUNDLEKIT_FIXED_BUNDLE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUNDLEKIT_FIXED_BUNDLE_URL', plugin_dir_url( __FILE__ ) );

// Load the embedded BundleKit framework (shared admin: About Developer /
// Recommended Plugins). If another BundleKit add-on already loaded its
// copy, this file returns immediately and that copy is used instead.
require_once BUNDLEKIT_FIXED_BUNDLE_DIR . 'includes/framework/bundlekit-framework.php';

/**
 * If the old separate "BundleKit" plugin (from v1.1.0) is still installed
 * and active, tell the admin it can now be safely deleted — the framework
 * is built into this add-on. This is a one-time clean-up notice, not an error.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( 'bundlekit/bundlekit.php' ) ) {
			return;
		}
		echo '<div class="notice notice-info is-dismissible"><p><strong>BundleKit – Fixed Bundle:</strong> the BundleKit framework is now built into this plugin, so the separate <strong>BundleKit</strong> plugin is no longer needed. You can safely deactivate and delete it from Plugins — this add-on will keep working on its built-in copy.</p></div>';
	}
);

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BUNDLEKIT_FIXED_BUNDLE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BUNDLEKIT_FIXED_BUNDLE_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p><strong>BundleKit – Fixed Bundle</strong> needs WooCommerce to be active.</p></div>';
				}
			);
			return;
		}

		require_once BUNDLEKIT_FIXED_BUNDLE_DIR . 'includes/class-bundlekit-fixed-bundle.php';
		require_once BUNDLEKIT_FIXED_BUNDLE_DIR . 'includes/class-bundlekit-fixed-bundle-admin.php';
		require_once BUNDLEKIT_FIXED_BUNDLE_DIR . 'includes/class-bundlekit-fixed-bundle-pricing.php';
		require_once BUNDLEKIT_FIXED_BUNDLE_DIR . 'includes/class-bundlekit-fixed-bundle-frontend.php';

		BundleKit_Fixed_Bundle::init();
		BundleKit_Fixed_Bundle_Admin::init();
		BundleKit_Fixed_Bundle_Pricing::init();
		BundleKit_Fixed_Bundle_Frontend::init();
	},
	25
);

// Register with the BundleKit framework (shows on About tab as an installed add-on).
add_filter(
	'bundlekit_addons',
	function ( $addons ) {
		$addons[] = array(
			'slug'    => 'bundlekit-fixed-bundle',
			'name'    => 'BundleKit – Fixed Bundle',
			'version' => BUNDLEKIT_FIXED_BUNDLE_VERSION,
			'file'    => BUNDLEKIT_FIXED_BUNDLE_FILE,
		);
		return $addons;
	}
);

// Register the Fixed Bundle global page (BundleKit > Fixed Bundle):
// Settings (global on/off, default layout) and Styling Controls
// (global styling defaults; per-product styling overrides them).
// The framework only adds the submenu if this class is loaded, so
// the page never appears without the add-on itself.
add_filter(
	'bundlekit_addon_pages',
	function ( $pages ) {
		$pages[] = array(
			'slug'   => 'bundlekit-fixed-bundle',
			'title'  => __( 'Fixed Bundle', 'bundlekit-fixed-bundle' ),
			'render' => array( 'BundleKit_Fixed_Bundle_Admin', 'global_page' ),
		);
		return $pages;
	}
);

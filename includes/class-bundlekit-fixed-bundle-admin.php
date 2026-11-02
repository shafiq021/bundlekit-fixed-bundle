<?php
defined( 'ABSPATH' ) || exit;

/**
 * Product editor tab: "BundleKit" — Fixed Bundle settings for one product.
 * Own file, own fields. No shared settings file.
 */
class BundleKit_Fixed_Bundle_Admin {

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		add_action( 'woocommerce_process_product_meta_simple', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'global_save' ) );
	}

	public static function tab( $tabs ) {
		// Product editor, 1.8.0: bundle CONTENT only (on/off, title,
		// name, badge text, discount, products). Layout and styling
		// are global now — layout is picked on the Styling Controls
		// page, styling lives there too — so the per-product
		// BundleKit Styling tab and the layout selector are gone.
		$tabs['bundlekit_fixed_bundle'] = array(
			'label'    => __( 'BundleKit', 'bundlekit-fixed-bundle' ),
			'target'   => 'bundlekit_fixed_bundle_data',
			'class'    => array( 'show_if_simple' ),
			'priority' => 60,
		);
		return $tabs;
	}

	public static function admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		// Global Fixed Bundle page only (the modern styling page):
		// the frontend CSS so the live preview looks like the real
		// box, the WP color picker for the color controls, and the
		// preview script. The frontend script is enqueued too, so
		// the preview's checkbox and Breakdown popup work live.
		if ( 'bundlekit_page_bundlekit-fixed-bundle' !== $screen->id ) {
			return;
		}
		// Shared BundleKit admin UI (framework 1.0.4): the Settings /
		// Styling Controls / Display design system. Each add-on loads
		// its own bundled copy — no cross-plugin dependency.
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'bundlekit-admin-ui', BUNDLEKIT_FIXED_BUNDLE_URL . 'includes/framework/assets/css/bundlekit-admin-ui.css', array(), BUNDLEKIT_FRAMEWORK_VERSION );
		wp_enqueue_script( 'bundlekit-admin-ui', BUNDLEKIT_FIXED_BUNDLE_URL . 'includes/framework/assets/js/bundlekit-admin-ui.js', array(), BUNDLEKIT_FRAMEWORK_VERSION, true );
		wp_enqueue_style( 'bundlekit-fixed-bundle-frontend', BUNDLEKIT_FIXED_BUNDLE_URL . 'assets/css/frontend.css', array(), BUNDLEKIT_FIXED_BUNDLE_VERSION );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'bundlekit-fixed-bundle-frontend', BUNDLEKIT_FIXED_BUNDLE_URL . 'assets/js/frontend.js', array(), BUNDLEKIT_FIXED_BUNDLE_VERSION, true );
		wp_enqueue_script( 'bundlekit-fixed-bundle-global-styling', BUNDLEKIT_FIXED_BUNDLE_URL . 'assets/js/global-styling.js', array( 'jquery', 'wp-color-picker' ), BUNDLEKIT_FIXED_BUNDLE_VERSION, true );
		wp_localize_script(
			'bundlekit-fixed-bundle-global-styling',
			'bundlekitStyling',
			array(
				'varMap' => BundleKit_Fixed_Bundle_Frontend::var_map(),
				'fonts'  => BundleKit_Fixed_Bundle::font_stacks(),
			)
		);
	}

	public static function panel() {
		global $post;
		$cfg   = BundleKit_Fixed_Bundle::config( $post->ID );
		$on    = 'yes' === get_post_meta( $post->ID, '_bundlekit_bundle_on', true );
		$title = get_post_meta( $post->ID, '_bundlekit_bundle_title', true );
		$name  = get_post_meta( $post->ID, '_bundlekit_bundle_name', true );
		$badge = get_post_meta( $post->ID, '_bundlekit_bundle_badge', true );
		$type  = get_post_meta( $post->ID, '_bundlekit_bundle_type', true );
		$value = get_post_meta( $post->ID, '_bundlekit_bundle_value', true );
		?>
		<div id="bundlekit_fixed_bundle_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field">
					<label><input type="checkbox" name="bundlekit_bundle_on" value="yes" <?php checked( $on ); ?> /> <?php esc_html_e( 'Show a Fixed Bundle offer on this product', 'bundlekit-fixed-bundle' ); ?></label>
				</p>
				<p class="form-field">
					<label for="bundlekit_bundle_title"><?php esc_html_e( 'Bundle title', 'bundlekit-fixed-bundle' ); ?></label>
					<input type="text" id="bundlekit_bundle_title" name="bundlekit_bundle_title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Frequently bought together', 'bundlekit-fixed-bundle' ); ?>" />
				</p>
				<p class="form-field">
					<label for="bundlekit_bundle_name"><?php esc_html_e( 'Bundle name (shown in cart)', 'bundlekit-fixed-bundle' ); ?></label>
					<input type="text" id="bundlekit_bundle_name" name="bundlekit_bundle_name" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'Bundle offer', 'bundlekit-fixed-bundle' ); ?>" />
				</p>
				<p class="form-field">
					<label for="bundlekit_bundle_badge"><?php esc_html_e( 'Badge (optional)', 'bundlekit-fixed-bundle' ); ?></label>
					<input type="text" id="bundlekit_bundle_badge" name="bundlekit_bundle_badge" value="<?php echo esc_attr( $badge ); ?>" placeholder="<?php esc_attr_e( 'e.g. Save 20%', 'bundlekit-fixed-bundle' ); ?>" />
				</p>
				<p class="form-field">
					<label for="bundlekit_bundle_type"><?php esc_html_e( 'Discount type', 'bundlekit-fixed-bundle' ); ?></label>
					<select id="bundlekit_bundle_type" name="bundlekit_bundle_type">
						<option value="percent" <?php selected( $type, 'percent' ); ?>><?php esc_html_e( 'Percent off the combined price', 'bundlekit-fixed-bundle' ); ?></option>
						<option value="fixed" <?php selected( $type, 'fixed' ); ?>><?php esc_html_e( 'Fixed total price for the whole bundle', 'bundlekit-fixed-bundle' ); ?></option>
					</select>
				</p>
				<p class="form-field">
					<label for="bundlekit_bundle_value"><?php esc_html_e( 'Value', 'bundlekit-fixed-bundle' ); ?></label>
					<input type="number" min="0" step="any" id="bundlekit_bundle_value" name="bundlekit_bundle_value" value="<?php echo esc_attr( $value ); ?>" class="small-text" />
					<span class="description"><?php esc_html_e( '% off, or the fixed total price — depending on the type above.', 'bundlekit-fixed-bundle' ); ?></span>
				</p>
				<p class="form-field">
					<label for="bundlekit_bundle_items"><?php esc_html_e( 'Bundle products', 'bundlekit-fixed-bundle' ); ?></label>
					<select id="bundlekit_bundle_items" name="bundlekit_bundle_items[]" class="wc-product-search" multiple="multiple" style="width: 50%;" data-placeholder="<?php esc_attr_e( 'Search for products…', 'bundlekit-fixed-bundle' ); ?>" data-action="woocommerce_json_search_products_and_variations">
						<?php foreach ( $cfg['items'] as $id ) : $p = wc_get_product( $id ); ?>
							<?php if ( $p ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" selected><?php echo esc_html( $p->get_formatted_name() ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
					<span class="description"><?php esc_html_e( 'These products are added with the main product when the customer adds the bundle. Simple products only in v1.', 'bundlekit-fixed-bundle' ); ?></span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Section cards for the Styling Controls page, in the design's
	 * order, with an icon and subtitle per section. Layout-specific
	 * sections carry data-bk-group so the shared admin script dims
	 * them when the other layout is picked.
	 *
	 * @param array<string,string> $values Sanitized values by field key.
	 */
	public static function render_style_fields( $values ) {
		$meta  = self::style_section_meta();
		$order = array( 'Title', 'Box', 'Prices', 'Items', 'Badge & saving', 'Total', 'Style 1 only', 'Selected state', 'Style 2 only' );
		$by_section = array();
		foreach ( BundleKit_Fixed_Bundle::style_fields() as $spec ) {
			$by_section[ $spec['section'] ][] = $spec;
		}
		$fonts = BundleKit_Fixed_Bundle::font_stacks();
		foreach ( $order as $section ) {
			if ( empty( $by_section[ $section ] ) || ! isset( $meta[ $section ] ) ) {
				continue;
			}
			$m     = $meta[ $section ];
			$group = $by_section[ $section ][0]['group'];
			$attrs = 'shared' === $group ? '' : 'data-bk-group="' . esc_attr( $group ) . '"';
			bundlekit_ui_card_open( $m['icon'], $m['title'], $m['subtitle'], '', $attrs );
			foreach ( $by_section[ $section ] as $spec ) {
				$id = 'bundlekit_style_' . $spec['key'];
				bundlekit_ui_style_field( $id, $id, $spec, isset( $values[ $spec['key'] ] ) ? $values[ $spec['key'] ] : '', $fonts, 'bundlekit-fixed-bundle' );
			}
			if ( 'shared' !== $group ) {
				/* translators: %s: style group label, either "Style 1" or "Style 2". */
				bundlekit_ui_lock_note( sprintf( __( 'Enable %s to customize these options', 'bundlekit-fixed-bundle' ), 'style1' === $group ? 'Style 1' : 'Style 2' ) );
			}
			bundlekit_ui_card_close();
		}
	}

	/**
	 * Icon + subtitle per styling section, matching the design.
	 */
	private static function style_section_meta() {
		return array(
			'Title'          => array( 'title' => __( 'Title', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-text', 'subtitle' => __( 'Customize the bundle title text style.', 'bundlekit-fixed-bundle' ) ),
			'Box'            => array( 'title' => __( 'Box', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-admin-appearance', 'subtitle' => __( 'Customize the bundle box container style.', 'bundlekit-fixed-bundle' ) ),
			'Prices'         => array( 'title' => __( 'Prices', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-tag', 'subtitle' => __( 'Customize the price text style.', 'bundlekit-fixed-bundle' ) ),
			'Items'          => array( 'title' => __( 'Items', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-list-view', 'subtitle' => __( 'Customize the bundle items text style.', 'bundlekit-fixed-bundle' ) ),
			'Badge & saving' => array( 'title' => __( 'Badge & Savings', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-award', 'subtitle' => __( 'Customize badge and savings text style.', 'bundlekit-fixed-bundle' ) ),
			'Total'          => array( 'title' => __( 'Total', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-calculator', 'subtitle' => __( 'Customize the total price text style.', 'bundlekit-fixed-bundle' ) ),
			'Style 1 only'   => array( 'title' => __( 'Style 1 Only', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-admin-page', 'subtitle' => __( 'Style 1 specific styling options.', 'bundlekit-fixed-bundle' ) ),
			'Selected state' => array( 'title' => __( 'Selected State', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-yes-alt', 'subtitle' => __( 'Customize the selected box state style.', 'bundlekit-fixed-bundle' ) ),
			'Style 2 only'   => array( 'title' => __( 'Style 2 Only', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-admin-page', 'subtitle' => __( 'Style 2 specific styling options.', 'bundlekit-fixed-bundle' ) ),
		);
	}

	/**
	 * Render the Display tab's show/hide toggles as cards. On the
	 * Styling Controls page only the layout-specific cards are shown
	 * (matching the design); the Display page shows all three.
	 *
	 * @param string[]|null $only_groups Null for all groups.
	 */
	public static function render_display_cards( $only_groups = null ) {
		$values = BundleKit_Fixed_Bundle::display_values();
		$groups = array(
			'shared' => array( 'title' => __( 'All Layouts', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-grid-view' ),
			'style1' => array( 'title' => __( 'Style 1 Only', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-text' ),
			'style2' => array( 'title' => __( 'Style 2 Only', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-admin-page' ),
		);
		$by_group = array( 'shared' => array(), 'style1' => array(), 'style2' => array() );
		foreach ( BundleKit_Fixed_Bundle::display_fields() as $spec ) {
			$by_group[ $spec['group'] ][] = $spec;
		}
		foreach ( $groups as $group => $m ) {
			if ( null !== $only_groups && ! in_array( $group, $only_groups, true ) ) {
				continue;
			}
			$attrs = 'shared' === $group ? '' : 'data-bk-group="' . esc_attr( $group ) . '"';
			bundlekit_ui_card_open( $m['icon'], $m['title'], '', '', $attrs );
			foreach ( $by_group[ $group ] as $spec ) {
				bundlekit_ui_toggle( 'bundlekit_show_' . $spec['key'], $spec['label'], ! empty( $values[ $spec['key'] ] ) );
			}
			if ( 'shared' !== $group ) {
				/* translators: %s: style group label, either "Style 1" or "Style 2". */
				bundlekit_ui_lock_note( sprintf( __( 'Enable %s to customize these options', 'bundlekit-fixed-bundle' ), 'style1' === $group ? 'Style 1' : 'Style 2' ) );
			}
			bundlekit_ui_card_close();
		}
	}

	/**
	 * Global Fixed Bundle page (BundleKit > Fixed Bundle): Settings,
	 * Styling Controls and Display tabs, in the shared BundleKit admin
	 * design (framework 1.0.4). Layout and styling are picked here and
	 * apply to every bundle box on the site.
	 */
	public static function global_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bundlekit-fixed-bundle' ) );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = in_array( $tab, array( 'settings', 'styling', 'display' ), true ) ? $tab : 'settings';
		$layout = BundleKit_Fixed_Bundle::default_layout();
		$tabs   = array(
			'settings' => array( 'label' => __( 'Settings', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-admin-generic' ),
			'styling'  => array( 'label' => __( 'Styling Controls', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-art' ),
			'display'  => array( 'label' => __( 'Display', 'bundlekit-fixed-bundle' ), 'icon' => 'dashicons-desktop' ),
		);
		?>
		<div class="wrap">
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'bundlekit-fixed-bundle' ); ?></p></div>
			<?php endif; ?>
			<div class="bk-ui" data-bk-ui data-bk-layout="<?php echo esc_attr( $layout ); ?>">
				<?php
				bundlekit_ui_head(
					'dashicons-archive',
					__( 'Fixed Bundle', 'bundlekit-fixed-bundle' ),
					__( 'Global Fixed Bundle settings. Layout and styling are picked here and apply to every bundle box on the site; the per-product BundleKit tab only holds the bundle itself (products, discount, title). Turning Fixed Bundle off here hides every bundle box and stops bundle pricing on the whole site.', 'bundlekit-fixed-bundle' )
				);
				bundlekit_ui_tabs( 'bundlekit-fixed-bundle', $tabs, $tab );
				?>
			<?php if ( 'settings' === $tab ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit-fixed-bundle&tab=settings' ) ); ?>">
					<?php wp_nonce_field( 'bundlekit_fixed_bundle_global', 'bundlekit_fixed_bundle_global_nonce' ); ?>
					<div class="bk-cards bk-cards--narrow">
						<?php bundlekit_ui_card_open( 'dashicons-admin-generic', __( 'General', 'bundlekit-fixed-bundle' ), __( 'Master switch for Fixed Bundle.', 'bundlekit-fixed-bundle' ) ); ?>
							<?php bundlekit_ui_toggle( 'bundlekit_fixed_bundle_enabled', __( 'Show bundle boxes and apply bundle pricing', 'bundlekit-fixed-bundle' ), BundleKit_Fixed_Bundle::enabled() ); ?>
							<p class="bk-field-desc"><?php esc_html_e( 'Untick to turn Fixed Bundle off for the whole site. No bundle box will show and no bundle will be added, even on products with a bundle switched on. Product settings are kept, so ticking this again restores everything.', 'bundlekit-fixed-bundle' ); ?></p>
						<?php bundlekit_ui_card_close(); ?>
					</div>
					<?php bundlekit_ui_save_button( __( 'Save settings', 'bundlekit-fixed-bundle' ) ); ?>
				</form>
			<?php elseif ( 'display' === $tab ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit-fixed-bundle&tab=display' ) ); ?>">
					<?php wp_nonce_field( 'bundlekit_fixed_bundle_global', 'bundlekit_fixed_bundle_global_nonce' ); ?>
					<?php bundlekit_ui_section_title( __( 'Display Settings', 'bundlekit-fixed-bundle' ), __( 'Choose which parts of the bundle box show on the frontend. Unticked parts are hidden on both layouts (layout-only parts only affect their layout). These are global: they apply to every bundle box.', 'bundlekit-fixed-bundle' ) ); ?>
					<div class="bk-cards-3">
						<?php self::render_display_cards(); ?>
					</div>
					<?php bundlekit_ui_save_button( __( 'Save display settings', 'bundlekit-fixed-bundle' ) ); ?>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit-fixed-bundle&tab=styling' ) ); ?>">
					<?php wp_nonce_field( 'bundlekit_fixed_bundle_global', 'bundlekit_fixed_bundle_global_nonce' ); ?>
					<div class="bk-bento">
						<div class="bk-bento-main">
							<div class="bk-cards bundlekit-styling-controls">
								<?php bundlekit_ui_card_open( 'dashicons-grid-view', __( 'Layout', 'bundlekit-fixed-bundle' ), __( 'Choose the bundle box layout style.', 'bundlekit-fixed-bundle' ), 'bk-card--wide' ); ?>
									<div class="bk-field">
										<label class="bk-field-label" for="bundlekit_fixed_bundle_default_layout"><?php esc_html_e( 'Bundle box layout', 'bundlekit-fixed-bundle' ); ?></label>
										<select id="bundlekit_fixed_bundle_default_layout" name="bundlekit_fixed_bundle_default_layout" class="bk-select" data-bk-layout-select>
											<option value="style1" <?php selected( $layout, 'style1' ); ?>><?php esc_html_e( 'Style 1 – Classic list', 'bundlekit-fixed-bundle' ); ?></option>
											<option value="style2" <?php selected( $layout, 'style2' ); ?>><?php esc_html_e( 'Style 2 – Card with price breakdown', 'bundlekit-fixed-bundle' ); ?></option>
										</select>
										<p class="bk-field-desc"><?php esc_html_e( 'This one layout applies to every bundle box on the site. The preview on the right switches with it, so you can style both layouts before saving.', 'bundlekit-fixed-bundle' ); ?></p>
									</div>
								<?php bundlekit_ui_card_close(); ?>
								<?php self::render_style_fields( BundleKit_Fixed_Bundle::global_style_values() ); ?>
								<?php self::render_display_cards( array( 'style1', 'style2' ) ); ?>
							</div>
							<p class="bk-field-desc"><?php esc_html_e( 'Style every bundle box. Colors use the color picker; leave a field empty to use the built-in look. Shared controls apply to both layouts; layout-only controls are dimmed when the other layout is picked.', 'bundlekit-fixed-bundle' ); ?></p>
							<?php bundlekit_ui_save_button( __( 'Save styling', 'bundlekit-fixed-bundle' ) ); ?>
						</div>
						<div class="bk-preview">
							<div class="bk-preview-frame">
								<div class="bk-preview-head">
									<span class="dashicons dashicons-visibility"></span>
									<div>
										<h3><?php esc_html_e( 'Live preview', 'bundlekit-fixed-bundle' ); ?></h3>
										<p><?php esc_html_e( 'This is how your bundle will look with the current styling.', 'bundlekit-fixed-bundle' ); ?></p>
									</div>
								</div>
								<div class="bundlekit-preview-box">
									<?php self::render_preview(); ?>
								</div>
								<p class="bk-field-desc"><?php esc_html_e( 'A sample bundle. Change any control on the left and this preview updates instantly; click the card to tick/untick it, and Breakdown to open the popup (Style 2). The preview reflects the saved Display settings.', 'bundlekit-fixed-bundle' ); ?></p>
							</div>
						</div>
					</div>
				</form>
			<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render both preview boxes for the styling page: a sample bundle
	 * (3 items, regular $40, bundle $35) in each layout. Only the one
	 * matching the picked layout is visible; the preview script
	 * switches between them. The preview checkbox has no name, so it
	 * never submits with the form, and no ajax URL is set, so the
	 * frontend script's session-flag fetch never runs here — but
	 * tick styling and the Breakdown popup work live.
	 */
	public static function render_preview() {
		$style_attr = BundleKit_Fixed_Bundle_Frontend::style_attr( BundleKit_Fixed_Bundle::global_style_values() );
		$display    = BundleKit_Fixed_Bundle::display_classes();
		$display    = $display ? ' ' . implode( ' ', $display ) : '';
		$layout     = BundleKit_Fixed_Bundle::default_layout();
		$thumb      = 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect width="48" height="48" rx="8" fill="#e5e7eb"/></svg>' );
		$items      = array(
			array( 'Premium Mixed Nuts Bowl', '$15' ),
			array( 'Crunchy Granola Pouch', '$13' ),
			array( 'Golden Citrus Juice Bottle', '$12' ),
		);
		?>
		<div id="bundlekit-preview-style1" class="bundlekit-preview<?php echo 'style1' === $layout ? '' : ' is-hidden'; ?>">
			<div class="bundlekit-bundle-box<?php echo esc_attr( $display ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-bundlekit-box data-product-id="0">
				<label class="bundlekit-bundle-head">
					<input type="checkbox" value="1" class="bundlekit-bundle-checkbox" />
					<span class="bundlekit-bundle-title"><?php esc_html_e( 'Complete the bundle', 'bundlekit-fixed-bundle' ); ?></span>
					<span class="bundlekit-bundle-badge"><?php esc_html_e( 'Save $5', 'bundlekit-fixed-bundle' ); ?></span>
				</label>
				<ul class="bundlekit-bundle-items">
					<?php foreach ( $items as $item ) : ?>
						<li>
							<span class="bundlekit-bundle-thumb"><img src="<?php echo esc_url( $thumb ); ?>" alt="" /></span>
							<span class="bundlekit-bundle-name"><?php echo esc_html( $item[0] ); ?></span>
							<span class="bundlekit-bundle-price"><?php echo esc_html( $item[1] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="bundlekit-bundle-total"><del>$40</del><strong>$35</strong><span class="bundlekit-bundle-save"><?php esc_html_e( 'You save $5', 'bundlekit-fixed-bundle' ); ?></span></p>
				<p class="bundlekit-bundle-hint"><?php esc_html_e( 'Tick the box to add the whole bundle as one item at the bundle price, or leave it unticked to add only this product.', 'bundlekit-fixed-bundle' ); ?></p>
			</div>
		</div>
		<div id="bundlekit-preview-style2" class="bundlekit-preview<?php echo 'style2' === $layout ? '' : ' is-hidden'; ?>">
			<div class="bundlekit-bundle-box bundlekit-bundle-box--style2<?php echo esc_attr( $display ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-bundlekit-box data-product-id="0">
				<input type="checkbox" value="1" class="bundlekit-bundle-checkbox" />
				<div class="bundlekit-bundle2-head">
					<span class="bundlekit-bundle2-head-thumb"><img src="<?php echo esc_url( $thumb ); ?>" alt="" /></span>
					<span class="bundlekit-bundle2-head-text">
						<span class="bundlekit-bundle2-eyebrow"><?php esc_html_e( 'Your bundle', 'bundlekit-fixed-bundle' ); ?></span>
						<span class="bundlekit-bundle2-title"><?php esc_html_e( 'Healthy Breakfast Combo Bundle', 'bundlekit-fixed-bundle' ); ?></span>
						<span class="bundlekit-bundle2-head-price">$35</span>
					</span>
					<span class="bundlekit-bundle-badge"><?php esc_html_e( 'Save $5', 'bundlekit-fixed-bundle' ); ?></span>
					<span class="bundlekit-bundle2-check" aria-hidden="true">✓</span>
				</div>
				<p class="bundlekit-bundle2-included"><?php esc_html_e( "What's included", 'bundlekit-fixed-bundle' ); ?></p>
				<p class="bundlekit-bundle2-count"><?php esc_html_e( '3 items inside', 'bundlekit-fixed-bundle' ); ?></p>
				<ul class="bundlekit-bundle2-items">
					<?php foreach ( $items as $item ) : ?>
						<li>
							<span class="bundlekit-bundle2-thumb"><img src="<?php echo esc_url( $thumb ); ?>" alt="" /></span>
							<span class="bundlekit-bundle2-item-name"><?php echo esc_html( $item[0] ); ?></span>
							<span class="bundlekit-bundle2-item-price"><?php echo esc_html( $item[1] ); ?></span>
							<span class="bundlekit-bundle2-qty">×1</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<div class="bundlekit-bundle2-foot">
					<span class="bundlekit-bundle2-total">$35</span>
					<del class="bundlekit-bundle2-regular">$40</del>
					<button type="button" class="bundlekit-bundle2-breakdown" aria-expanded="false"><?php esc_html_e( 'Breakdown', 'bundlekit-fixed-bundle' ); ?> <span class="bundlekit-bundle2-chevron" aria-hidden="true">∨</span></button>
					<div class="bundlekit-bundle2-popup">
						<p class="bundlekit-bundle2-popup-head"><?php esc_html_e( 'Price breakdown', 'bundlekit-fixed-bundle' ); ?></p>
						<div class="bundlekit-bundle2-popup-row bundlekit-bundle2-popup-bundle"><span><?php esc_html_e( 'Healthy Breakfast Combo Bundle', 'bundlekit-fixed-bundle' ); ?></span><strong>$35</strong></div>
						<div class="bundlekit-bundle2-popup-items">
							<?php foreach ( $items as $item ) : ?>
								<div class="bundlekit-bundle2-popup-row"><span><?php echo esc_html( $item[0] ); ?></span><span><?php echo esc_html( $item[1] ); ?></span></div>
							<?php endforeach; ?>
						</div>
						<div class="bundlekit-bundle2-popup-row bundlekit-bundle2-popup-total"><span><?php esc_html_e( 'Total value', 'bundlekit-fixed-bundle' ); ?></span><span>$40</span></div>
						<div class="bundlekit-bundle2-popup-row bundlekit-bundle2-popup-pay"><span><?php esc_html_e( 'You pay', 'bundlekit-fixed-bundle' ); ?></span><strong>$35</strong></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save the global Fixed Bundle page. Runs on admin_init, only for
	 * posts to this page with a valid nonce; then redirects back
	 * (Post/Redirect/Get) so a refresh never re-saves.
	 */
	public static function global_save() {
		if ( ! isset( $_GET['page'] ) || 'bundlekit-fixed-bundle' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! isset( $_POST['bundlekit_fixed_bundle_global_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['bundlekit_fixed_bundle_global_nonce'] ) ), 'bundlekit_fixed_bundle_global' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = in_array( $tab, array( 'settings', 'styling', 'display' ), true ) ? $tab : 'settings';
		if ( 'settings' === $tab ) {
			update_option( 'bundlekit_fixed_bundle_enabled', isset( $_POST['bundlekit_fixed_bundle_enabled'] ) ? 'yes' : 'no' );
		} elseif ( 'display' === $tab ) {
			foreach ( BundleKit_Fixed_Bundle::display_fields() as $spec ) {
				update_option( 'bundlekit_fixed_bundle_show_' . $spec['key'], isset( $_POST[ 'bundlekit_show_' . $spec['key'] ] ) ? 'yes' : 'no' );
			}
		} else {
			// Styling tab: the layout pick lives here, together with
			// the styling controls and the layout-specific show/hide
			// toggles (Style 1 Only / Style 2 Only cards) — so display
			// toggles are saved here as well.
			update_option( 'bundlekit_fixed_bundle_default_layout', ( isset( $_POST['bundlekit_fixed_bundle_default_layout'] ) && 'style2' === $_POST['bundlekit_fixed_bundle_default_layout'] ) ? 'style2' : 'style1' );
			foreach ( BundleKit_Fixed_Bundle::style_fields() as $spec ) {
				$key = 'bundlekit_style_' . $spec['key'];
				$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				update_option( 'bundlekit_fixed_bundle_style_' . $spec['key'], BundleKit_Fixed_Bundle::sanitize_style_value( $spec, is_string( $raw ) ? $raw : '' ) );
			}
			foreach ( BundleKit_Fixed_Bundle::display_fields() as $spec ) {
				// The styling page only renders the layout-specific
				// toggle cards (Style 1 / Style 2); the shared "All
				// Layouts" toggles live on the Display page only, so
				// they must NOT be overwritten from here.
				if ( 'shared' === $spec['group'] ) {
					continue;
				}
				update_option( 'bundlekit_fixed_bundle_show_' . $spec['key'], isset( $_POST[ 'bundlekit_show_' . $spec['key'] ] ) ? 'yes' : 'no' );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bundlekit-fixed-bundle&tab=' . $tab . '&saved=1' ) );
		exit;
	}

	public static function save( $post_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- save() only runs on
		// woocommerce_process_product_meta_simple, which WooCommerce fires from
		// WC_Meta_Box_Product_Data::save() after verifying its own product-data nonce.
		update_post_meta( $post_id, '_bundlekit_bundle_on', isset( $_POST['bundlekit_bundle_on'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_bundlekit_bundle_title', isset( $_POST['bundlekit_bundle_title'] ) ? sanitize_text_field( wp_unslash( $_POST['bundlekit_bundle_title'] ) ) : '' );
		update_post_meta( $post_id, '_bundlekit_bundle_name', isset( $_POST['bundlekit_bundle_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bundlekit_bundle_name'] ) ) : '' );
		update_post_meta( $post_id, '_bundlekit_bundle_badge', isset( $_POST['bundlekit_bundle_badge'] ) ? sanitize_text_field( wp_unslash( $_POST['bundlekit_bundle_badge'] ) ) : '' );
		update_post_meta( $post_id, '_bundlekit_bundle_type', ( isset( $_POST['bundlekit_bundle_type'] ) && 'fixed' === $_POST['bundlekit_bundle_type'] ) ? 'fixed' : 'percent' );
		// 1.8.0: no per-product layout or styling any more — layout
		// and styling are global (Styling Controls page). Old
		// per-product meta stays in the database but is not read.
		update_post_meta( $post_id, '_bundlekit_bundle_value', isset( $_POST['bundlekit_bundle_value'] ) ? max( 0, (float) $_POST['bundlekit_bundle_value'] ) : 0 );

		$items = array();
		if ( isset( $_POST['bundlekit_bundle_items'] ) && is_array( $_POST['bundlekit_bundle_items'] ) ) {
			foreach ( wp_unslash( $_POST['bundlekit_bundle_items'] ) as $id ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$id = absint( $id );
				if ( $id && $id !== (int) $post_id && ! in_array( $id, $items, true ) ) {
					$items[] = $id;
				}
			}
		}
		update_post_meta( $post_id, '_bundlekit_bundle_items', $items );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}

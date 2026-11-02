<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixed Bundle config + pricing maths.
 * Meta keys are _bundlekit_bundle_*. Rule: one fixed group, one combined
 * total (fixed price or % off), spread proportionally across every line
 * so each line still shows a price.
 *
 * v1 scope: simple products only, qty 1 per bundle item.
 */
class BundleKit_Fixed_Bundle {

	public static function init() {
		// Nothing to hook here — config/totals are used by Admin / Pricing / Frontend.
	}

	/**
	 * Global on/off (BundleKit > Fixed Bundle > Settings). Default
	 * on: turning it off hides every bundle box and stops bundle
	 * pricing site-wide, even on products with a bundle switched on.
	 */
	public static function enabled() {
		return 'no' !== get_option( 'bundlekit_fixed_bundle_enabled', 'yes' );
	}

	/**
	 * The box layout, picked on the Styling Controls page (1.8.0:
	 * layout is global only — per-product layout settings from
	 * earlier versions are no longer read, so every bundle follows
	 * this one pick).
	 */
	public static function default_layout() {
		return 'style2' === get_option( 'bundlekit_fixed_bundle_default_layout', 'style1' ) ? 'style2' : 'style1';
	}

	/**
	 * Sanitized global styling values (BundleKit > Fixed Bundle >
	 * Styling Controls). 1.8.0: styling is global only — these are
	 * the values every bundle box uses; per-product styling from
	 * earlier versions is no longer read.
	 *
	 * @return array<string,string>
	 */
	public static function global_style_values() {
		$values = array();
		foreach ( self::style_fields() as $spec ) {
			$raw = get_option( 'bundlekit_fixed_bundle_style_' . $spec['key'], '' );
			$values[ $spec['key'] ] = self::sanitize_style_value( $spec, is_string( $raw ) ? $raw : '' );
		}
		return $values;
	}

	/**
	 * Show/hide controls for frontend elements (BundleKit > Fixed
	 * Bundle > Display). One spec list drives the Display tab, the
	 * save handler and the frontend classes, like style_fields().
	 *
	 * @return array<int,array{key:string,label:string,group:string}>
	 */
	public static function display_fields() {
		$fields = array();
		$add    = function ( $key, $label, $group = 'shared' ) use ( &$fields ) {
			$fields[] = array(
				'key'   => $key,
				'label' => $label,
				'group' => $group,
			);
		};
		$add( 'badge', 'Show badge' );
		$add( 'images', 'Show product images' );
		$add( 'item_prices', 'Show item prices' );
		$add( 'total', 'Show bundle total' );
		$add( 'regular', 'Show struck regular price' );
		$add( 'save', 'Show "You save" text' );
		$add( 'hint', 'Show hint text', 'style1' );
		$add( 'checkbox', 'Show checkbox', 'style1' );
		$add( 'eyebrow', 'Show "Your bundle" heading', 'style2' );
		$add( 'included', 'Show "What\'s included" heading', 'style2' );
		$add( 'qty', 'Show quantity badges', 'style2' );
		$add( 'breakdown', 'Show Breakdown button and popup', 'style2' );
		return $fields;
	}

	/**
	 * Whether each frontend element is shown. Default: everything
	 * shows. Stored as bundlekit_fixed_bundle_show_<key> = yes/no.
	 *
	 * @return array<string,bool>
	 */
	public static function display_values() {
		$values = array();
		foreach ( self::display_fields() as $spec ) {
			$values[ $spec['key'] ] = 'no' !== get_option( 'bundlekit_fixed_bundle_show_' . $spec['key'], 'yes' );
		}
		return $values;
	}

	/**
	 * CSS classes for hidden frontend elements, e.g. "bk-no-badge".
	 * Added to the bundle box; frontend.css hides the matching part.
	 * The live preview uses the same classes.
	 */
	public static function display_classes() {
		$classes = array();
		foreach ( self::display_values() as $key => $show ) {
			if ( ! $show ) {
				$classes[] = 'bk-no-' . str_replace( '_', '-', $key );
			}
		}
		return $classes;
	}

	/**
	 * @return array{on:bool, items:int[], title:string, name:string, badge:string, type:string, value:float, layout:string, style:array<string,string>}
	 */
	public static function config( $product_id ) {
		$product_id = absint( $product_id );
		$meta       = function ( $key, $default = '' ) use ( $product_id ) {
			$v = get_post_meta( $product_id, '_bundlekit_bundle_' . $key, true );
			return ( '' === $v || null === $v ) ? $default : $v;
		};

		$items = array();
		foreach ( (array) get_post_meta( $product_id, '_bundlekit_bundle_items', true ) as $id ) {
			$id = absint( $id );
			if ( ! $id || $id === $product_id || in_array( $id, $items, true ) ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				continue;
			}
			$items[] = $id;
		}

		return array(
			'on'    => 'yes' === $meta( 'on', 'no' ) && ! empty( $items ),
			'items' => $items,
			'title' => (string) $meta( 'title', '' ),
			'name'  => '' !== (string) $meta( 'name', '' ) ? (string) $meta( 'name', '' ) : __( 'Bundle offer', 'bundlekit-fixed-bundle' ),
			'badge' => (string) $meta( 'badge', '' ),
			'type'  => 'fixed' === $meta( 'type', 'percent' ) ? 'fixed' : 'percent',
			'value' => max( 0, (float) $meta( 'value', 0 ) ),
			// Bundle box layout: 'style1' (classic list) or 'style2'
			// (card with "What's included" + Breakdown popup).
			// 1.8.0: the layout is picked once on the Styling
			// Controls page and applies to every bundle; a
			// per-product layout saved by an earlier version is
			// no longer read.
			'layout' => self::default_layout(),
			// Box styling, 1.8.0: the global styling values only.
			// An empty value means the built-in look.
			'style'  => self::global_style_values(),
		);
	}

	/**
	 * Font stacks offered for the title / item text. Keys are stored
	 * in post meta; the stack itself is only ever output from this
	 * allow-list, never from raw user input.
	 *
	 * @return array<string,string>
	 */
	public static function font_stacks() {
		return array(
			''          => '',
			'system'    => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
			'arial'     => 'Arial, Helvetica, sans-serif',
			'verdana'   => 'Verdana, Geneva, sans-serif',
			'trebuchet' => '"Trebuchet MS", Helvetica, sans-serif',
			'georgia'   => 'Georgia, serif',
			'times'     => '"Times New Roman", Times, serif',
			'mono'      => '"Courier New", Courier, monospace',
		);
	}

	/**
	 * The styling controls (BundleKit Styling tab, 1.6.0). One spec
	 * list drives the admin panel, the save handler and the frontend
	 * output, so a control cannot exist in one place and be missing
	 * in another.
	 *
	 * Fields: key (meta suffix after _bundlekit_style_), type
	 * (color|number|font|weight), label, section heading, group
	 * (shared = both layouts, style1 / style2 = only shown when that
	 * layout is selected in the BundleKit tab).
	 *
	 * @return array<int,array{key:string,type:string,label:string,section:string,group:string}>
	 */
	public static function style_fields() {
		$fields = array();
		$add    = function ( $key, $type, $label, $section, $group = 'shared' ) use ( &$fields ) {
			$fields[] = array(
				'key'     => $key,
				'type'    => $type,
				'label'   => $label,
				'section' => $section,
				'group'   => $group,
			);
		};
		// Box.
		$add( 'box_bg', 'color', 'Box background', 'Box' );
		$add( 'box_border', 'color', 'Box border color', 'Box' );
		$add( 'box_border_width', 'number', 'Border width', 'Box' );
		$add( 'box_radius', 'number', 'Corner radius', 'Box' );
		$add( 'box_padding', 'number', 'Box padding', 'Box' );
		// Title.
		$add( 'title_font', 'font', 'Title font', 'Title' );
		$add( 'title_size', 'number', 'Title size', 'Title' );
		$add( 'title_weight', 'weight', 'Title weight', 'Title' );
		$add( 'title_color', 'color', 'Title color', 'Title' );
		// Items.
		$add( 'item_font', 'font', 'Item font', 'Items' );
		$add( 'item_size', 'number', 'Item size', 'Items' );
		$add( 'item_weight', 'weight', 'Item weight', 'Items' );
		$add( 'item_color', 'color', 'Item color', 'Items' );
		// Prices.
		$add( 'price_size', 'number', 'Price size', 'Prices' );
		$add( 'price_weight', 'weight', 'Price weight', 'Prices' );
		$add( 'price_color', 'color', 'Price color', 'Prices' );
		// Total.
		$add( 'total_size', 'number', 'Total size', 'Total' );
		$add( 'total_weight', 'weight', 'Total weight', 'Total' );
		$add( 'total_color', 'color', 'Total color', 'Total' );
		// Badge & saving.
		$add( 'badge_bg', 'color', 'Badge background', 'Badge & saving' );
		$add( 'badge_color', 'color', 'Badge text', 'Badge & saving' );
		$add( 'save_color', 'color', '"You save" text', 'Badge & saving' );
		// Selected state.
		$add( 'checked_border', 'color', 'Selected border', 'Selected state' );
		$add( 'checked_bg', 'color', 'Selected background', 'Selected state' );
		// Style 1 only.
		$add( 'checkbox_color', 'color', 'Checkbox color', 'Style 1 only', 'style1' );
		$add( 'hint_size', 'number', 'Hint text size', 'Style 1 only', 'style1' );
		$add( 'hint_color', 'color', 'Hint text color', 'Style 1 only', 'style1' );
		// Style 2 only.
		$add( 'check_bg', 'color', 'Check circle background', 'Style 2 only', 'style2' );
		$add( 'eyebrow_color', 'color', '"Your bundle" text', 'Style 2 only', 'style2' );
		$add( 'eyebrow_size', 'number', '"Your bundle" size', 'Style 2 only', 'style2' );
		$add( 'included_color', 'color', '"What\'s included" text', 'Style 2 only', 'style2' );
		$add( 'included_size', 'number', '"What\'s included" size', 'Style 2 only', 'style2' );
		$add( 'qty_bg', 'color', 'Quantity badge background', 'Style 2 only', 'style2' );
		$add( 'qty_color', 'color', 'Quantity badge text', 'Style 2 only', 'style2' );
		$add( 'popup_bg', 'color', 'Breakdown popup background', 'Style 2 only', 'style2' );
		$add( 'popup_border', 'color', 'Breakdown popup border', 'Style 2 only', 'style2' );
		$add( 'popup_radius', 'number', 'Breakdown popup corner radius', 'Style 2 only', 'style2' );
		return $fields;
	}

	/**
	 * Sanitize one styling value. '' always means "use the default".
	 */
	public static function sanitize_style_value( $spec, $raw ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( '' === $raw ) {
			return '';
		}
		switch ( $spec['type'] ) {
			case 'color':
				$v = sanitize_hex_color( $raw );
				return $v ? $v : '';
			case 'number':
				$v = max( 0, (float) $raw );
				// Keep "18" as "18", not "18.0".
				return (string) ( (float) $v === (float) (int) $v ? (int) $v : $v );
			case 'font':
				$stacks = self::font_stacks();
				return isset( $stacks[ $raw ] ) && '' !== $stacks[ $raw ] ? $raw : '';
			case 'weight':
				return in_array( $raw, array( '400', '500', '600', '700', '800' ), true ) ? $raw : '';
			default:
				return '';
		}
	}

	/**
	 * NOTE (1.8.0): style_values() and resolve_layout() were removed
	 * with the per-product styling tab — styling and layout are
	 * global only now (global_style_values() / default_layout()).
	 * Per-product meta from earlier versions stays in the database
	 * but is no longer read.
	 */

	/**
	 * Totals for a bundle.
	 *
	 * @param int[] $all_ids Main product id first, then bundle item ids.
	 * @return array{regular:float, total:float, save:float, units:array<int,float>} units keyed by product id.
	 */
	public static function totals( $all_ids, $type, $value ) {
		$prices  = array();
		$regular = 0.0;
		foreach ( $all_ids as $id ) {
			$product = wc_get_product( $id );
			$price   = $product ? (float) $product->get_price() : 0.0;
			$prices[ $id ] = $price;
			$regular      += $price;
		}
		if ( $regular <= 0 ) {
			return array( 'regular' => 0, 'total' => 0, 'save' => 0, 'units' => $prices );
		}
		$total = 'fixed' === $type ? (float) $value : $regular * ( 100 - min( 100, $value ) ) / 100;
		$total = max( 0, min( $total, $regular ) );
		$save  = $regular - $total;

		// Spread proportionally, fix rounding on the last line.
		$units     = array();
		$allocated = 0.0;
		$count     = count( $all_ids );
		foreach ( $all_ids as $i => $id ) {
			if ( $i === $count - 1 ) {
				$units[ $id ] = round( $total - $allocated, 2 );
			} else {
				$units[ $id ] = round( $total * $prices[ $id ] / $regular, 2 );
				$allocated   += $units[ $id ];
			}
		}
		return array(
			'regular' => round( $regular, 2 ),
			'total'   => round( $total, 2 ),
			'save'    => round( $save, 2 ),
			'units'   => $units,
		);
	}
}

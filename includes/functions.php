<?php
/**
 * Helpers: settings, finding low stock products, scheduling.
 *
 * Thresholds come from WooCommerce itself: the per-product "Low stock threshold"
 * if set, otherwise WooCommerce → Settings → Products → Inventory.
 *
 * @package LSA
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default settings (options named lsa_{key}).
 *
 * @return array<string, string>
 */
function lsa_defaults() {
	return array(
		'recipients'    => get_option( 'admin_email' ),
		'frequency'     => 'daily',
		'hour'          => '9',
		'include_out'   => 'yes',
		'admin_bar'     => 'yes',
		'skip_if_empty' => 'yes',
	);
}

/**
 * Get a setting. Filter: lsa_option_{key}.
 *
 * @param string $key Key without the lsa_ prefix.
 * @return string
 */
function lsa_option( $key ) {
	$defaults = lsa_defaults();
	$value    = get_option( 'lsa_' . $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );

	return (string) apply_filters( 'lsa_option_' . $key, $value );
}

/**
 * Whether a yes/no setting is on.
 *
 * @param string $key Key.
 * @return bool
 */
function lsa_is_on( $key ) {
	return 'yes' === lsa_option( $key );
}

/**
 * Highest low stock threshold in use (global or any product).
 *
 * @return int
 */
function lsa_max_threshold() {
	global $wpdb;

	$max    = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
	$values = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_low_stock_amount' AND meta_value <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	foreach ( $values as $value ) {
		$max = max( $max, (int) $value );
	}

	return $max;
}

/**
 * Whether a product (or variation) is live in the store.
 *
 * @param WC_Product $product Product.
 * @return bool
 */
function lsa_is_live( $product ) {
	if ( 'publish' !== $product->get_status() ) {
		return false;
	}

	if ( $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );
		return $parent && 'publish' === $parent->get_status();
	}

	return true;
}

/**
 * All low stock and out-of-stock products and variations.
 *
 * Each item: product (WC_Product), qty (int|null), threshold (int|null), type ('out'|'low').
 * Out-of-stock items come first, then the lowest stock.
 *
 * @param bool|null $include_out Include out-of-stock items (default: setting).
 * @return array<int, array>
 */
function lsa_get_items( $include_out = null ) {
	global $wpdb;

	$include_out = null === $include_out ? lsa_is_on( 'include_out' ) : (bool) $include_out;
	$table       = $wpdb->prefix . 'wc_product_meta_lookup';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT product_id, stock_quantity, stock_status FROM {$table} WHERE ( stock_quantity IS NOT NULL AND stock_quantity <= %d ) OR stock_status = 'outofstock' LIMIT 2000", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			lsa_max_threshold()
		)
	);

	$items = array();

	foreach ( (array) $rows as $row ) {
		$product = wc_get_product( (int) $row->product_id );

		if ( ! $product || $product->is_type( 'variable' ) || ! lsa_is_live( $product ) ) {
			continue;
		}

		$qty = $product->managing_stock() ? (int) $product->get_stock_quantity() : null;

		if ( 'outofstock' === $product->get_stock_status() ) {
			if ( ! $include_out ) {
				continue;
			}
			$type      = 'out';
			$threshold = null;
		} elseif ( null !== $qty ) {
			$threshold = (int) wc_get_low_stock_amount( $product );

			if ( $qty > $threshold ) {
				continue;
			}
			$type = 'low';
		} else {
			continue;
		}

		$items[] = array(
			'product'   => $product,
			'qty'       => $qty,
			'threshold' => $threshold,
			'type'      => $type,
		);
	}

	usort(
		$items,
		function ( $a, $b ) {
			if ( $a['type'] !== $b['type'] ) {
				return 'out' === $a['type'] ? -1 : 1;
			}
			return (int) $a['qty'] - (int) $b['qty'];
		}
	);

	return apply_filters( 'lsa_items', $items );
}

/**
 * Low / out counts, cached for 15 minutes.
 *
 * @return array{low:int, out:int, total:int}
 */
function lsa_counts() {
	$counts = get_transient( 'lsa_counts' );

	if ( is_array( $counts ) ) {
		return $counts;
	}

	$counts = array(
		'low'   => 0,
		'out'   => 0,
		'total' => 0,
	);

	foreach ( lsa_get_items() as $item ) {
		++$counts[ $item['type'] ];
		++$counts['total'];
	}

	set_transient( 'lsa_counts', $counts, 15 * MINUTE_IN_SECONDS );

	return $counts;
}

/**
 * Clear the cached counts whenever stock changes.
 */
foreach ( array( 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status', 'woocommerce_update_product', 'woocommerce_update_product_variation' ) as $lsa_hook ) {
	add_action(
		$lsa_hook,
		function () {
			delete_transient( 'lsa_counts' );
		}
	);
}
unset( $lsa_hook );

/**
 * Link to the "Low stock" view of the products list.
 *
 * @return string
 */
function lsa_products_url() {
	return admin_url( 'edit.php?post_type=product&lsa_low_stock=1' );
}

/**
 * (Re)schedule the digest at the chosen hour (site time zone).
 *
 * @return void
 */
function lsa_schedule() {
	wp_clear_scheduled_hook( 'lsa_send_digest' );

	$frequency = lsa_option( 'frequency' );

	if ( ! in_array( $frequency, array( 'daily', 'weekly' ), true ) ) {
		return;
	}

	$hour = min( 23, max( 0, (int) lsa_option( 'hour' ) ) );
	$next = new DateTime( 'today', wp_timezone() );
	$next->setTime( $hour, 0 );

	if ( $next->getTimestamp() <= time() ) {
		$next->modify( '+1 day' );
	}

	wp_schedule_event( $next->getTimestamp(), $frequency, 'lsa_send_digest' );
}

/**
 * Next scheduled digest as a timestamp, or false.
 *
 * @return int|false
 */
function lsa_next_digest() {
	return wp_next_scheduled( 'lsa_send_digest' );
}

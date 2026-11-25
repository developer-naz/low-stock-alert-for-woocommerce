<?php
/**
 * Admin: settings tab, dashboard widget, admin bar count, products list view.
 *
 * @package LSA
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Settings: WooCommerce → Settings → Stock Alerts
 * ---------------------------------------------------------------------- */

/**
 * Settings fields.
 *
 * @return array
 */
function lsa_settings_fields() {
	$d     = lsa_defaults();
	$hours = array();

	for ( $h = 0; $h < 24; $h++ ) {
		$hours[ (string) $h ] = sprintf( '%02d:00', $h );
	}

	$next = lsa_next_digest();

	return array(
		array(
			'title' => __( 'Stock report email', 'lsa' ),
			'type'  => 'title',
			'desc'  => sprintf(
				/* translators: %s: link to inventory settings. */
				__( 'One email with every low and out-of-stock product. Low stock thresholds come from each product or from %s.', 'lsa' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory' ) ) . '">' . esc_html__( 'Inventory settings', 'lsa' ) . '</a>'
			) . ( $next ? '<br><strong>' . esc_html(
				sprintf(
					/* translators: %s: date and time. */
					__( 'Next report: %s', 'lsa' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next )
				)
			) . '</strong>' : '' ),
			'id'    => 'lsa_email',
		),
		array(
			'title'       => __( 'Send to', 'lsa' ),
			'desc'        => __( 'One or more email addresses, separated by commas.', 'lsa' ),
			'id'          => 'lsa_recipients',
			'type'        => 'text',
			'default'     => $d['recipients'],
			'css'         => 'width:420px;',
		),
		array(
			'title'   => __( 'How often', 'lsa' ),
			'id'      => 'lsa_frequency',
			'type'    => 'select',
			'default' => $d['frequency'],
			'options' => array(
				'daily'  => __( 'Every day', 'lsa' ),
				'weekly' => __( 'Every week', 'lsa' ),
				'off'    => __( 'Off', 'lsa' ),
			),
		),
		array(
			'title'   => __( 'Send at', 'lsa' ),
			'desc'    => __( 'Your site time zone.', 'lsa' ),
			'id'      => 'lsa_hour',
			'type'    => 'select',
			'default' => $d['hour'],
			'options' => $hours,
		),
		array(
			'title'   => __( 'Out of stock', 'lsa' ),
			'desc'    => __( 'Include out-of-stock products', 'lsa' ),
			'id'      => 'lsa_include_out',
			'type'    => 'checkbox',
			'default' => $d['include_out'],
		),
		array(
			'title'   => __( 'Nothing to report', 'lsa' ),
			'desc'    => __( 'Skip the email when no product is low', 'lsa' ),
			'id'      => 'lsa_skip_if_empty',
			'type'    => 'checkbox',
			'default' => $d['skip_if_empty'],
		),
		array(
			'title'   => __( 'Admin bar', 'lsa' ),
			'desc'    => __( 'Show the low stock count in the admin bar', 'lsa' ),
			'id'      => 'lsa_admin_bar',
			'type'    => 'checkbox',
			'default' => $d['admin_bar'],
		),
		array(
			'type' => 'sectionend',
			'id'   => 'lsa_email',
		),
	);
}

add_filter( 'woocommerce_settings_tabs_array', function ( $tabs ) {
	$tabs['lsa'] = __( 'Stock Alerts', 'lsa' );
	return $tabs;
}, 70 );

add_action( 'woocommerce_settings_tabs_lsa', function () {
	woocommerce_admin_fields( lsa_settings_fields() );
	// Named "save" so WooCommerce saves the form; the value tells us to also send.
	echo '<p><button type="submit" name="save" value="lsa_send_now" class="button">' . esc_html__( 'Save and send a report now', 'lsa' ) . '</button></p>';
} );

add_action( 'woocommerce_update_options_lsa', function () {
	woocommerce_update_options( lsa_settings_fields() );

	// The settings form is nonce-checked by WooCommerce before this action runs.
	if ( isset( $_POST['save'] ) && 'lsa_send_now' === $_POST['save'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( lsa_send_digest( true ) ) {
			WC_Admin_Settings::add_message( __( 'Stock report sent.', 'lsa' ) );
		} else {
			WC_Admin_Settings::add_error( __( 'The stock report could not be sent. Check the email addresses and that your site can send email.', 'lsa' ) );
		}
	}
} );

add_filter( 'plugin_action_links_' . plugin_basename( LSA_FILE ), function ( $links ) {
	array_unshift(
		$links,
		'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=lsa' ) ) . '">' . esc_html__( 'Settings', 'lsa' ) . '</a>'
	);
	return $links;
} );

/* -------------------------------------------------------------------------
 * Dashboard widget
 * ---------------------------------------------------------------------- */

add_action( 'wp_dashboard_setup', function () {
	if ( current_user_can( 'manage_woocommerce' ) ) {
		wp_add_dashboard_widget( 'lsa_dashboard', __( 'Low stock', 'lsa' ), 'lsa_render_dashboard_widget' );
	}
} );

/**
 * Dashboard widget content: counts and the 10 most urgent items.
 *
 * @return void
 */
function lsa_render_dashboard_widget() {
	$items  = lsa_get_items();
	$counts = array(
		'out' => 0,
		'low' => 0,
	);

	foreach ( $items as $item ) {
		++$counts[ $item['type'] ];
	}

	if ( empty( $items ) ) {
		echo '<p>' . esc_html__( 'All products are well stocked.', 'lsa' ) . '</p>';
		return;
	}

	printf(
		'<p><strong style="color:#b32d2e">%1$s</strong> &nbsp;·&nbsp; <strong style="color:#996800">%2$s</strong></p>',
		/* translators: %d: count. */
		esc_html( sprintf( __( '%d out of stock', 'lsa' ), $counts['out'] ) ),
		/* translators: %d: count. */
		esc_html( sprintf( __( '%d low', 'lsa' ), $counts['low'] ) )
	);

	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Product', 'lsa' ) . '</th><th style="width:70px">' . esc_html__( 'Stock', 'lsa' ) . '</th></tr></thead><tbody>';

	foreach ( array_slice( $items, 0, 10 ) as $item ) {
		$product = $item['product'];
		$edit_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$stock   = 'out' === $item['type'] ? '<span style="color:#b32d2e">' . esc_html__( 'Out', 'lsa' ) . '</span>' : esc_html( (string) $item['qty'] );

		echo '<tr><td><a href="' . esc_url( get_edit_post_link( $edit_id ) ) . '">' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</a></td><td>' . $stock . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $stock is escaped above.
	}

	echo '</tbody></table>';

	if ( count( $items ) > 10 ) {
		/* translators: %d: total count. */
		echo '<p><a href="' . esc_url( lsa_products_url() ) . '">' . esc_html( sprintf( __( 'View all %d', 'lsa' ), count( $items ) ) ) . '</a></p>';
	} else {
		echo '<p><a href="' . esc_url( lsa_products_url() ) . '">' . esc_html__( 'Open in the products list', 'lsa' ) . '</a></p>';
	}
}

/* -------------------------------------------------------------------------
 * Admin bar
 * ---------------------------------------------------------------------- */

add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! lsa_is_on( 'admin_bar' ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$counts = lsa_counts();

	if ( $counts['total'] < 1 ) {
		return;
	}

	$bar->add_node(
		array(
			'id'    => 'lsa-low-stock',
			/* translators: %d: count. */
			'title' => '<span style="display:inline-block;padding:0 7px;border-radius:9px;background:#d63638;color:#fff;line-height:18px;">' . esc_html( sprintf( __( 'Low stock: %d', 'lsa' ), $counts['total'] ) ) . '</span>',
			'href'  => lsa_products_url(),
		)
	);
}, 100 );

/* -------------------------------------------------------------------------
 * Products list: "Low stock" view
 * ---------------------------------------------------------------------- */

add_filter( 'views_edit-product', function ( $views ) {
	$counts  = lsa_counts();
	$current = ! empty( $_GET['lsa_low_stock'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$views['lsa_low_stock'] = sprintf(
		'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
		esc_url( lsa_products_url() ),
		$current ? ' class="current" aria-current="page"' : '',
		esc_html__( 'Low stock', 'lsa' ),
		$counts['total']
	);

	return $views;
} );

add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || empty( $_GET['lsa_low_stock'] ) || 'product' !== $query->get( 'post_type' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$ids = array();

	foreach ( lsa_get_items() as $item ) {
		$product = $item['product'];
		$ids[]   = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
	}

	$query->set( 'post__in', $ids ? array_values( array_unique( $ids ) ) : array( 0 ) );
} );

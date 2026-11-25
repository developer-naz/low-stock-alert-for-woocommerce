<?php
/**
 * The digest email and its schedule.
 *
 * @package LSA
 */

defined( 'ABSPATH' ) || exit;

// Make sure the digest is scheduled (covers activation and updates).
add_action( 'init', function () {
	$frequency = lsa_option( 'frequency' );

	if ( in_array( $frequency, array( 'daily', 'weekly' ), true ) && ! lsa_next_digest() ) {
		lsa_schedule();
	}
} );

// Reschedule when the frequency or hour changes.
add_action( 'update_option_lsa_frequency', 'lsa_schedule' );
add_action( 'update_option_lsa_hour', 'lsa_schedule' );
add_action( 'add_option_lsa_frequency', 'lsa_schedule' );
add_action( 'add_option_lsa_hour', 'lsa_schedule' );

// Keep the report at the same local hour when the site time zone changes.
add_action( 'update_option_timezone_string', 'lsa_schedule' );
add_action( 'update_option_gmt_offset', 'lsa_schedule' );

add_action( 'lsa_send_digest', function () {
	lsa_send_digest();
} );

/**
 * Valid recipient addresses.
 *
 * @return string[]
 */
function lsa_recipients() {
	$list = preg_split( '/[\s,;]+/', lsa_option( 'recipients' ) );
	$list = array_filter( array_map( 'sanitize_email', (array) $list ), 'is_email' );

	return array_values( array_unique( apply_filters( 'lsa_recipients', $list ) ) );
}

/**
 * Build the email body (HTML table).
 *
 * @param array $items Items from lsa_get_items().
 * @return string
 */
function lsa_digest_html( $items ) {
	$out = 0;
	$low = 0;

	foreach ( $items as $item ) {
		'out' === $item['type'] ? ++$out : ++$low;
	}

	if ( empty( $items ) ) {
		return '<p>' . esc_html__( 'Good news: no products are low on stock or out of stock right now.', 'lsa' ) . '</p>';
	}

	$html  = '<p>' . esc_html(
		sprintf(
			/* translators: 1: out of stock count, 2: low stock count. */
			__( '%1$d out of stock and %2$d low on stock.', 'lsa' ),
			$out,
			$low
		)
	) . '</p>';
	$html .= '<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;border:1px solid #e5e5e5;">';
	$html .= '<thead><tr>';

	foreach ( array( __( 'Product', 'lsa' ), __( 'SKU', 'lsa' ), __( 'Stock', 'lsa' ), __( 'Status', 'lsa' ) ) as $head ) {
		$html .= '<th style="text-align:left;border:1px solid #e5e5e5;">' . esc_html( $head ) . '</th>';
	}

	$html .= '</tr></thead><tbody>';

	foreach ( $items as $item ) {
		$product = $item['product'];
		$edit_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$status  = 'out' === $item['type']
			? '<strong style="color:#b32d2e;">' . esc_html__( 'Out of stock', 'lsa' ) . '</strong>'
			/* translators: %d: low stock threshold. */
			: '<span style="color:#996800;">' . esc_html( sprintf( __( 'Low (threshold %d)', 'lsa' ), $item['threshold'] ) ) . '</span>';

		$html .= '<tr>';
		$html .= '<td style="border:1px solid #e5e5e5;"><a href="' . esc_url( admin_url( 'post.php?post=' . $edit_id . '&action=edit' ) ) . '">' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</a></td>';
		$html .= '<td style="border:1px solid #e5e5e5;">' . esc_html( $product->get_sku() ) . '</td>';
		$html .= '<td style="border:1px solid #e5e5e5;">' . ( null === $item['qty'] ? '&ndash;' : esc_html( (string) $item['qty'] ) ) . '</td>';
		$html .= '<td style="border:1px solid #e5e5e5;">' . $status . '</td>';
		$html .= '</tr>';
	}

	$html .= '</tbody></table>';
	$html .= '<p><a href="' . esc_url( lsa_products_url() ) . '">' . esc_html__( 'Open the low stock list in your store', 'lsa' ) . '</a></p>';

	return $html;
}

/**
 * Send the digest.
 *
 * @param bool $force Send even if nothing is low (used for the test email).
 * @return bool Whether an email was sent.
 */
function lsa_send_digest( $force = false ) {
	$items      = lsa_get_items();
	$recipients = lsa_recipients();

	if ( empty( $recipients ) || ( empty( $items ) && lsa_is_on( 'skip_if_empty' ) && ! $force ) ) {
		return false;
	}

	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject = empty( $items )
		/* translators: %s: site name. */
		? sprintf( __( '[%s] Stock report: all good', 'lsa' ), $site )
		/* translators: 1: site name, 2: number of products. */
		: sprintf( _n( '[%1$s] Stock report: %2$d product needs attention', '[%1$s] Stock report: %2$d products need attention', count( $items ), 'lsa' ), $site, count( $items ) );

	$heading = __( 'Stock report', 'lsa' );
	$mailer  = WC()->mailer();
	$message = $mailer->wrap_message( $heading, lsa_digest_html( $items ) );

	$sent = $mailer->send( implode( ',', $recipients ), apply_filters( 'lsa_digest_subject', $subject, $items ), $message );

	if ( $sent ) {
		update_option( 'lsa_last_sent', time(), false );
	}

	return (bool) $sent;
}

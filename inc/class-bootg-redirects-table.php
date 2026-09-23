<?php
/**
 * List table for the Redirects tab — same native look as WordPress's own
 * Posts screen (it's built on the same core WP_List_Table class Rank Math
 * and every other list screen in wp-admin uses).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Bootg_Redirects_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'redirect',
			'plural'   => 'redirects',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox">',
			'from'          => 'From',
			'to'            => 'To',
			'type'          => 'Type',
			'hits'          => 'Hits',
			'created'       => 'Created',
			'last_accessed' => 'Last Accessed',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'hits'    => array( 'hits', false ),
			'created' => array( 'created', false ),
		);
	}

	protected function get_views() {
		$counts = wp_count_posts( 'bootg_redirect' );
		$status = isset( $_GET['redirect_status'] ) ? sanitize_key( $_GET['redirect_status'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base   = remove_query_arg( 'redirect_status' );

		$all_count = (int) $counts->publish + (int) $counts->draft;

		$views = array(
			'all'      => sprintf( '<a href="%s" class="%s">All <span class="count">(%d)</span></a>', esc_url( $base ), 'all' === $status ? 'current' : '', $all_count ),
			'active'   => sprintf( '<a href="%s" class="%s">Active <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'redirect_status', 'active', $base ) ), 'active' === $status ? 'current' : '', (int) $counts->publish ),
			'inactive' => sprintf( '<a href="%s" class="%s">Inactive <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'redirect_status', 'inactive', $base ) ), 'inactive' === $status ? 'current' : '', (int) $counts->draft ),
		);
		if ( ! empty( $counts->trash ) ) {
			$views['trash'] = sprintf( '<a href="%s" class="%s">Trash <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'redirect_status', 'trash', $base ) ), 'trash' === $status ? 'current' : '', (int) $counts->trash );
		}
		return $views;
	}

	protected function get_bulk_actions() {
		$status = isset( $_GET['redirect_status'] ) ? sanitize_key( $_GET['redirect_status'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'trash' === $status ) {
			return array( 'restore' => 'Restore', 'delete' => 'Delete Permanently' );
		}
		return array( 'activate' => 'Activate', 'deactivate' => 'Deactivate', 'trash' => 'Move to Trash' );
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="redirect_ids[]" value="%d">', $item['id'] );
	}

	public function column_from( $item ) {
		$edit_url = add_query_arg( array( 'view' => 'edit', 'redirect_id' => $item['id'] ) );
		$sources  = wp_list_pluck( $item['sources'], 'url' );
		$label    = $sources ? implode( ', ', $sources ) : '(no source URL)';

		$status = isset( $_GET['redirect_status'] ) ? sanitize_key( $_GET['redirect_status'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$actions = array();
		if ( 'trash' === $status ) {
			$actions['restore'] = sprintf( '<a href="%s">Restore</a>', esc_url( $this->row_action_url( 'restore', $item['id'] ) ) );
			$actions['delete']  = sprintf( '<a href="%s" onclick="return confirm(\'Delete this redirect permanently?\');">Delete Permanently</a>', esc_url( $this->row_action_url( 'delete', $item['id'] ) ) );
		} else {
			$actions['edit']   = sprintf( '<a href="%s">Edit</a>', esc_url( $edit_url ) );
			$actions['toggle'] = 'publish' === $item['status']
				? sprintf( '<a href="%s">Deactivate</a>', esc_url( $this->row_action_url( 'deactivate', $item['id'] ) ) )
				: sprintf( '<a href="%s">Activate</a>', esc_url( $this->row_action_url( 'activate', $item['id'] ) ) );
			$actions['trash']  = sprintf( '<a href="%s">Trash</a>', esc_url( $this->row_action_url( 'trash', $item['id'] ) ) );
		}

		return sprintf( '<strong><a href="%s">%s</a></strong>', esc_url( $edit_url ), esc_html( $label ) ) . $this->row_actions( $actions );
	}

	private function row_action_url( $action, $id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=bootg_redirect_row_action&row_action=' . $action . '&redirect_id=' . $id ),
			'bootg_redirect_row_action_' . $id
		);
	}

	public function column_to( $item ) {
		if ( in_array( $item['type'], array( 410, 451 ), true ) ) {
			return '<em>&mdash;</em>';
		}
		$dest = $item['destination'];
		$url  = str_starts_with( $dest, 'http' ) ? $dest : home_url( $dest );
		return sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $url ), esc_html( $dest ) );
	}

	public function column_type( $item ) {
		return esc_html( $item['type'] );
	}

	public function column_hits( $item ) {
		return esc_html( number_format_i18n( $item['hits'] ) );
	}

	public function column_created( $item ) {
		return esc_html( mysql2date( 'M j, Y', $item['created'] ) );
	}

	public function column_last_accessed( $item ) {
		return $item['last_accessed'] ? esc_html( mysql2date( 'M j, Y, g:ia', $item['last_accessed'] ) ) : '&mdash;';
	}

	public function no_items() {
		echo 'No redirects yet.';
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$status  = isset( $_GET['redirect_status'] ) ? sanitize_key( $_GET['redirect_status'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_status = array( 'publish', 'draft' );
		if ( 'active' === $status ) {
			$post_status = array( 'publish' );
		} elseif ( 'inactive' === $status ) {
			$post_status = array( 'draft' );
		} elseif ( 'trash' === $status ) {
			$post_status = array( 'trash' );
		}

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wp_orderby = 'hits' === $orderby ? 'meta_value_num' : 'date';

		$args = array(
			'post_type'      => 'bootg_redirect',
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => $wp_orderby,
			'order'          => $order,
			's'              => $search,
		);
		if ( 'hits' === $orderby ) {
			$args['meta_key'] = '_bootg_hits'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}

		$q = new WP_Query( $args );

		$this->items = array_map( function ( $post ) {
			return bootg_get_redirect( $post->ID );
		}, $q->posts );

		$this->set_pagination_args( array(
			'total_items' => $q->found_posts,
			'per_page'    => $per_page,
			'total_pages' => $q->max_num_pages,
		) );
	}
}

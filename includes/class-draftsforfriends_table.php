<?php
// secure the file from direct access
defined( 'ABSPATH' ) or die( 'Script kiddies make kitty sad.' );

// fixes for fatal error that occurs when trying to include WP_List_table
// for details, see https://wordpress.org/support/topic/function-convert_to_screen-no-longer-exists-in-templatephp
require_once( ABSPATH . 'wp-admin/includes/template.php' );
if( ! class_exists( 'WP_Screen' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/screen.php' );
} // end fixes

if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

// allows redirect while page still rendering
add_action('init', 'start_buffer_output');
function start_buffer_output() {
	ob_start();
}

class DraftsForFriends_Table extends WP_List_Table {

	private $data;

	function __construct(){
		global $status, $page;

		// set parent defaults
		parent::__construct( array(
			'singular'  => 'draft',         // singular name of the listed records
			'plural'    => 'drafts',        // plural name of the listed records
			'ajax'      => false,           // does this table support ajax?
			'screen'    => 'interval-list'  // fix for hook warning (see comments in header)
		) );
	}

	function set_data( $input ) {
		$this->data = $input;
	}

	function fetch_data() {
		return $this->data;
	}

	// render columns
	function single_row_columns( $item ) {
		list( $columns, $hidden ) = $this->get_column_info();      
		foreach ( $columns as $column_name => $column_display_name ) {
			$delete_nonce = wp_create_nonce( 'draftsforfriends-delete-' . $item['key'] );
			$extend_nonce = wp_create_nonce( 'draftsforfriends-extend-' . $item['key'] );
			if (in_array($column_name, $hidden)) {
				$style = ' style="display:none;"';
			} else {
				$style = '';
			}
			if ( 'cb' === $column_name ) {
				echo '<th scope="row" class="check-column"' . esc_attr( $style ) . '>';
				echo sprintf( '<input type="checkbox" name="%1$s[]" value="%2$s" />',
						$this->_args['singular'], // $1%s
						esc_attr( $item['ID'] ) // $2%s
					);
				echo '</th>';
			} elseif ( 'title' === $column_name ) {
				echo '<td class="title column-title has-row-actions column-primary" data-colname="Title"' . esc_attr( $style ) . '>';
				echo esc_html( $item['title'] ) . ' <span style="color:silver;">(id:' . esc_html( $item['ID'] ) . ')</span>';
				// div to be hidden when 'Extend' is clicked:
				echo '<div class="row-actions" id="draftsforfriends-extend-link-'. esc_attr( $item['key'] ) . '">';
				echo '<span class="extend">';
				echo '<a href="javascript:draftsforfriends.toggle_extend(\'' . esc_js( $item['key'] ) . '\')">' . __( 'Extend', 'drafts-for-friends' ) . '</a>';
				echo '</span> | <span class="trash">';
				echo sprintf( '<a href="?page=%s&action=%s&key=%s&_wpnonce=%s">%s</a>', esc_attr( $_REQUEST['page'] ), 'delete', esc_attr( $item['key'] ), $delete_nonce, __( 'Delete', 'drafts-for-friends' ) );
				echo '</span></div>';
				// end div
				// div to be displayed when 'Extend' is clicked:
				echo '<div class="draftsforfriends-extend" id="draftsforfriends-extend-div-' . esc_attr( $item['key'] ) . '" style="margin-top: 5px; display:none;">';
				echo '<input type="hidden" value="' . $extend_nonce . '" id="wpnonce-' . esc_attr( $item['key'] ) . '"/>';
				echo '<input type="hidden" value="extend" id="action-' . esc_attr( $item['key'] ) . '"/>';
				echo '<input type="hidden" value="' . esc_attr( $item['key'] ) . '" id="key-' . esc_attr( $item['key'] ) . '"/>';
				echo '<a class="draftsforfriends-extend" href="javascript:draftsforfriends.extend(\'' . esc_js( $item['key'] ) . '\')">' . __( 'Extend', 'drafts-for-friends' ) . '</a>';
				echo __( 'by', 'drafts-for-friends' );
				echo DraftsForFriends::tmpl_measure_select( $item['key'] );
				echo '&nbsp;' . __( ' or ', 'drafts-for-friends' ) . '<a class="draftsforfriends-extend-cancel-button" href="javascript:draftsforfriends.cancel_extend(\'' . esc_js( $item['key'] ) . '\')">' . __( 'Cancel', 'drafts-for-friends' ) . '</a>';
				echo '</div>';
				// end div
				echo '</td>';
			} elseif ( 'author' === $column_name ) {
				echo '<td class="author column-author"' . esc_attr( $style ) . '>';
				echo esc_html( $item['author'] ); 
				echo '</td>';
			} elseif ( 'status' === $column_name ) {
				echo '<td class="status column-status"' . esc_attr( $style ) . '>';
				echo '<span class="' . esc_attr( $item['status_class'] ) . '">' . esc_html( $item['status'] ). '</span>';
				echo '</td>';
			} elseif ( 'link' === $column_name ) {
				echo '<td class="link column-link"' . esc_attr( $style ) . '>';
				echo sprintf( '<a href="%s">%s</a>', esc_url( $item['link'] ), esc_url( $item['link'] ) ); 
				echo '</td>';
			} elseif ( 'expires' === $column_name ) {
				echo '<td class="expires column-expires"' . esc_attr( $style ) . '>';
				echo '<span class="' . esc_attr( $item['expires_class'] ) . '">' . esc_html( $item['expires'] ) . '</span>';
				echo '</td>';                
			} else {
				echo '<td class=""' . esc_attr( $style ) . '>';
				echo esc_html( $this->column_default( $item, $column_name ) );
				echo "</td>";
			}
		}
	}

	// returns array of table columns
	function get_columns(){
		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'title'     => __( 'Title', 'drafts-for-friends' ),
			'author'    => __( 'Author', 'drafts-for-friends' ),
			'status'    => __( 'Status', 'drafts-for-friends' ),
			'link'      => __( 'Link', 'drafts-for-friends' ),
			'expires'   => __( 'Expires in', 'drafts-for-friends' )
		);
		return $columns;
	}

	// returns array of sortable columns
	function get_sortable_columns() {
		$sortable_columns = array(
			'title'     => array( 'title', false ),
			'author'    => array( 'author', false ),
			'status'    => array( 'status', false ),
			'link'      => array( 'link', false ),
			'expires'   => array( 'expires', false )
		);
		return $sortable_columns;
	}

	// defines bulk actions for the table
	function get_bulk_actions() {
		$actions = array(
			'bulk-delete'           => __( 'Delete', 'drafts-for-friends' ),
			'bulk-delete-expired'   => __( 'Delete all expired', 'drafts-for-friends' )
		);
		return $actions;
	}

	function no_items() {
		_e( 'No shared drafts yet! Use the form above to start sharing!', 'drafts-for-friends' );
	}

	function prepare_items() {
		$data = $this->fetch_data();
		$per_page = 10;
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();        
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// process bulk actions
		$this->process_bulk_action();

		// sorting
		function usort_reorder($a,$b){
			$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'title'; // if no sort, default to title
			$order = ( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'asc'; // if no order, default to asc
			$result = strcmp( $a[ $orderby ], $b[ $orderby ] ); // determine sort order
			return ( $order === 'asc' ) ? $result : -$result; // send final sort direction to usort
		}
		usort( $data, 'usort_reorder' );

		// pagination
		$current_page = $this->get_pagenum();
		$total_items = count( $data );
		$data = array_slice( $data, ( ($current_page-1) * $per_page ), $per_page );

		// pass prepared $data to the class
		$this->items = $data;

		// pagination calculations
		$this->set_pagination_args( array(
			'total_items' => $total_items,                  // we have to calculate the total number of items
			'per_page'    => $per_page,                     // we have to determine how many items to show on a page
			'total_pages' => ceil($total_items/$per_page)   // we have to calculate the total number of pages
		) );
	}
}
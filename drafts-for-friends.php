<?php
/*
Plugin Name: Drafts for Friends
Plugin URI: http://automattic.com/
Description: Now you don't need to add friends as users to the blog in order to let them preview your drafts
Author: Ivan Pekarik
Version: 2.5.9
Author URI: mailto:ivan.pekarik@gmail.com
*/

// secure the file from direct access
defined( 'ABSPATH' ) or die( 'Script kiddies make kitty sad.' );

// version information
if ( !defined('DRAFTSFORFRIENDS_VERSION') ) {
	define( 'DRAFTSFORFRIENDS_VERSION', '2.6.0' );
}

// check plugin version and run update script if needed
add_action('plugins_loaded', 'update_draftsforfriends' );
function update_draftsforfriends() {
	$old_version = get_option('draftsforfriends_version');
	if ( empty ( $old_version ) ) { // new install
		add_option('draftsforfriends_version', DRAFTSFORFRIENDS_VERSION);
		return;
	}
	if ( $old_version < DRAFTSFORFRIENDS_VERSION ) {
		// execute update script here, if needed
	}
	update_option('draftsforfriends_version', DRAFTSFORFRIENDS_VERSION);
}

// include class for rendering plugin data in default WordPress style table
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-draftsforfriends_table.php' );

// define main plugin class
if( ! class_exists( 'DraftsForFriends' ) ) {

	class DraftsForFriends	{

		function __construct() {
			add_action( 'init', array( $this, 'init') );
		}

		// plugin initialisation
		function init() {
			// load translation
			load_plugin_textdomain( 'drafts-for-friends', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

			// instance output table 
			$this->table = new DraftsForFriends_Table;
			
			// load admin and user options from database
			global $current_user;
			$this->admin_options = $this->get_admin_options();
			$this->user_options = ( $current_user->ID > 0 && isset( $this->admin_options[$current_user->ID] ) ) ? $this->admin_options[$current_user->ID] : array( 'shared' => array() );
			$this->save_admin_options();

			// setup hooks
			add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_page_scripts' ) );
			add_filter( 'the_posts', array( $this, 'the_posts_intercept' ) );
			add_filter( 'posts_results', array( $this, 'posts_results_intercept' ) );
		}

		// renders plugin JS and CSS, but only on plugin admin page
		function admin_page_scripts( $hook_suffix ) {
			if( 'posts_page_drafts-for-friends/drafts-for-friends' === strtolower( $hook_suffix ) ) {
				wp_enqueue_style( 'draftsforfriends', plugins_url( 'css/draftsforfriends.css', __FILE__ ) );
				wp_enqueue_script( 'draftsforfriends', plugins_url( 'js/draftsforfriends.js', __FILE__ ), array( 'jquery' ) );
			}
		}

		// adds submenu page to the Posts menu in Admin
		function add_admin_pages(){
			add_submenu_page( "edit.php", __('Drafts for Friends', 'drafts-for-friends'), __('Drafts for Friends', 'drafts-for-friends'),
				'edit_posts', __FILE__, array( $this, 'output_existing_menu_sub_admin_page' ) );
		}

		// posts_results filter
		function posts_results_intercept( $posts ) {
			if ( 1 != count( $posts ) ) return $posts;
			$post = $posts[0];
			$status = get_post_status( $post );
			if ( 'publish' != $status && 'trash' != $status && 'private' != $status && $this->can_view( $post->ID ) ) {
				$this->shared_post = $post;
			} else {
				$this->shared_post = null;
			}
			return $posts;
		}

		// the_posts filter
		function the_posts_intercept( $posts ) {
			if ( empty( $posts ) && ! empty( $this->shared_post ) ) {
				return array( $this->shared_post );
			} else {
				$this->shared_post = null;
				return $posts;
			}
		}

		// checks whether the current post is available for viewing
		function can_view( $pid ) {
			if ( isset( $_GET['key'] ) ) {
				foreach ( $this->admin_options as $user ) {
					if ( isset( $user['shared'] ) ) {
						foreach( $user['shared'] as $share ) {
							if ( $share['key'] === $_GET['key'] && $share['ID'] === $pid && time() < $share['expires'] ) {
								return true;
							}
						}
					}
				}
			}
			return false;
		}

		// loads array of saved WP database options
		function get_admin_options() {
			$saved_options = get_option('draftsforfriends_shared_posts');
			return is_array( $saved_options ) ? $saved_options : array();
		}

		// updates list of shared drafts in the database
		function save_admin_options(){
			global $current_user;
			if ( $current_user->ID > 0 ) {
				$this->admin_options[$current_user->ID] = $this->user_options;
			}
			update_option( 'draftsforfriends_shared_posts', $this->admin_options );
			$this->table->set_data( $this->prepare_data() ); // refresh table display data
		}

		// returns/renders HTML code of the dropdown menu with selectable units of time
		static function tmpl_measure_select( $id ) {
			if ( ! isset( $id ) ) {
				$id = '';
				$id_expires = '';
				$id_measure = '';
			} else {
				$id_expires = 'expires-' . esc_attr( $id );
				$id_measure = 'measure-' . esc_attr( $id );
			}
			$secs = __( 'seconds', 'drafts-for-friends' );
			$mins = __( 'minutes', 'drafts-for-friends' );
			$hours = __( 'hours', 'drafts-for-friends' );
			$days = __( 'days', 'drafts-for-friends' );

			// IMPORTANT NOTE: If you change the values in this select input, modify following functions:
			// - DraftsForFriends::validate_measure_select()
			// - DraftsForFriends::calc()
			return <<<SELECT
				<input name="expires" type="text" value="2" size="4" id="$id_expires"/>
				<select name="measure" id="$id_measure">
					<option value="s">$secs</option>
					<option value="m">$mins</option>
					<option value="h" selected="selected">$hours</option>
					<option value="d">$days</option>
				</select>
SELECT;
		}

		// checks if 'measure' param in the _POST request is valid.
		// if not, someone is probably trying to put a malicious string in our input field
		function validate_measure_select( $params ) {
			if ( ! in_array( $params['measure'], array( 's', 'm', 'h', 'd' ) ) ) {
				die( 'Playing dirty, are we?' );
			}
		}

		// returns total time in seconds calculated from $_POST parameters as integer
		function calc( $params ) {
			$exp = 60;
			$multiply = 60;
			$mults = array( 's' => 1, 'm' => 60, 'h' => 3600, 'd' => 24*3600 );
			if ( isset( $params['expires'] ) && ( $e = intval( $params['expires'] ) ) ) {
				$exp = $e;
			}
			if ( $params['measure'] && $mults[ $params['measure'] ] ) {
				$multiply = $mults[ $params['measure'] ];
			}
			return $exp * $multiply;
		}

		// returns remaining time to link expiry in human readable format as string
		function expires_in( $timestamp ) {
			if ( isset( $timestamp ) && ( $check = intval( $timestamp ) ) ) {
				$timestamp = $check;
			}
			else {
				/* translators: timestamp corrupt or unavailable */
				return array( 'red', sprintf( __( 'n/a', 'drafts-for-friends' ) ) );
			}
			$now = time();
			if ( $timestamp < $now ) {
				return array( 'red', __( 'Link expired', 'drafts-for-friends' ) );
			} else {
				$diff = (int) abs( $timestamp - $now );
				if ( $diff < MINUTE_IN_SECONDS ) {
					return array( 'red', sprintf( _n( '%s second', '%s seconds', $diff, 'drafts-for-friends' ), $diff ) );
				} elseif ( $diff < HOUR_IN_SECONDS ) {
					$minutes = round ( $diff / MINUTE_IN_SECONDS );
					return array( 'orange', sprintf( _n( '%s minute', '%s minutes', $minutes, 'drafts-for-friends' ), $minutes ) );
				} elseif ( $diff < DAY_IN_SECONDS ) {
					$hours = floor ( $diff / HOUR_IN_SECONDS );
					$minutes = round ( ( $diff - $hours*HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
					if ( 60 == $minutes ) {
						$minutes = 0;
						$hours += 1;
					}
					if ( $minutes > 0 ) {
						return array( 'green', sprintf( _n( '%s hour and ', '%s hours and ', $hours, 'drafts-for-friends' ), $hours ) . sprintf( _n( '%s minute', '%s minutes', $minutes, 'drafts-for-friends' ), $minutes ) );
					} else {
						return array( 'green', sprintf( _n( '%s hour', '%s hours', $hours, 'drafts-for-friends' ), $hours ) );
					}
				} else {
					$days = floor ( $diff / DAY_IN_SECONDS );
					$hours = round ( ( $diff - $days*DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
					if ( 24 == $hours ) {
						$hours = 0;
						$days += 1;
					}
					if ( $hours > 0 ) {
						return array( 'green', sprintf( _n( '%s day and ', '%s days and ', $days, 'drafts-for-friends' ), $days ) . sprintf( _n( '%s hour', '%s hours', $hours, 'drafts-for-friends' ), $hours ) );
					} else {
						return array( 'green', sprintf( _n( '%s day', '%s days', $days, 'drafts-for-friends' ), $days ) );
					}
				}
			}
		}

		// checks if a post has alredy been shared
		function already_shared( $pid ) {
			$shared = array();
			foreach ( $this->user_options['shared'] as $share ) {
				if ( $share['ID'] == $pid ) {
					return true;
				}
			}
			return false;
		}

		// adds new shared link to the WP database option array
		function process_post_options( $params ) {
			$this->validate_measure_select( $params );
			if ( ! ctype_digit( $params['expires'] ) ) {
				return array( 'error', __( 'Input value must be positive and a whole number!', 'drafts-for-friends' ) );
			}
			global $current_user;
			if ( ctype_digit( $params['post_id'] ) ) {
				$p = get_post( $params['post_id'] );
				if ( ! $p ) {
					return array( 'error', __( 'There is no such post!', 'drafts-for-friends' ) );
				}
				if ( ! current_user_can ( 'edit_others_posts' ) ) {
					if ( ! $p->post_author === $current_user->ID ) {
						return array( 'error', __( 'You\'re not allowed to share this post!', 'drafts-for-friends' ) );
					}
				}
				if ( 'publish' === get_post_status( $p ) ) {
					return array( 'error', __( 'The post is published!', 'drafts-for-friends' ) );
				}
				if ( 'private' === get_post_status( $p ) ) {
					return array( 'error', __( 'This post is private!', 'drafts-for-friends' ) );
				}				
				if ( $this->already_shared( $p->ID ) ) {
					return array( 'error', __( 'This post has already been shared! Consider extending the validity of the link if it has expired.', 'drafts-for-friends' ) );	
				}
				$this->user_options['shared'][] = array( 
						'ID' => $p->ID,
						'expires' => time() + $this->calc( $params ),
						'key' => wp_generate_password( 16, false ) 
					);
				$this->save_admin_options();
				return array( 'updated', __( 'New share link created!', 'drafts-for-friends' ) );
			}			
			return array( 'error', __( 'Please select a post!', 'drafts-for-friends' ) );
		}

		// deletes a shared link from WP database option array
		function process_delete( $params ) {
			if ( isset ( $params ) ) {
				$shared = array();
				$now = time();	
				foreach ( $this->user_options['shared'] as $share ) {
					if ( 'expired' === $params ) { // bulk delete expired
						if ( $share['expires'] < $now ) {
							continue;
						}
					} else { // regular delete
						if ( $share['key'] == $params['key'] ) {
							continue;
						}
					}	
					$shared[] = $share;
				}
				if ( $this->user_options['shared'] == $shared ) {
					return array( 'error', __( 'No links deleted!', 'drafts-for-friends' ) );
				}
				$this->user_options['shared'] = $shared;
				$this->save_admin_options();
				if ( 'expired' === $params ){
					return array( 'updated', __( 'All expired links deleted!', 'drafts-for-friends' ) );
				} else {
					return array( 'updated', __( 'Link deleted!', 'drafts-for-friends' ) );	
				}
			}
		}

		// process bulk delete request
		function process_bulk_delete ( $params ) {
			if ( isset( $params['draft'] ) ) {
				foreach( $params['draft'] as $entry_ID ) {
					$shared = array();
					foreach ( $this->user_options['shared'] as $share ) {
						if ( $share['ID'] == $entry_ID ) {
							continue;
						}
						$shared[] = $share;
					}
					$this->user_options['shared'] = $shared;
				}
				$this->save_admin_options();
				return array( 'updated', __( 'Bulk delete complete!', 'drafts-for-friends' ) );
			} else {
				return array( 'error', __( 'No links selected to delete!', 'drafts-for-friends' ) );
			}
		}

		// extends the validy of links by specified length of time in $params
		function process_extend( $params ) {
			$this->validate_measure_select( $params );
			if ( ! ctype_digit( $params['expires'] ) ) {
				return array( 'error', __( 'Input value must be positive and a whole number!', 'drafts-for-friends' ) );
			}
			$shared = array();
			foreach ( $this->user_options['shared'] as $share ) {
				if ( $share['key'] == $params['key'] ) {
					if ( $share['expires'] < time() ) {
						$share['expires'] = time() + $this->calc( $params );
					} else {
						$share['expires'] += $this->calc( $params );
					}
				}
				$shared[] = $share;
			}
			$this->user_options['shared'] = $shared;
			$this->save_admin_options();
			return array( 'updated', __( 'Link extended!', 'drafts-for-friends' ) );
		}

		// returns all applicable posts for sharing (drafts, scheduled, or pending review)
		function get_drafts() {
			global $current_user;
			current_user_can ( 'edit_others_posts' ) ? $author = null : $author = $current_user->ID;

			$my_drafts = $this->fetch_users_drafts( $author, 'draft' );
			$my_scheduled = $this->fetch_users_drafts( $author, 'future' );
			$my_pending = $this->fetch_users_drafts( $author, 'pending' );

			if ( empty( $my_drafts ) && empty( $my_scheduled ) && empty ( $my_pending ) ) {
				return null;
			}
			$drafts = array(
				array(
					__( 'Drafts:', 'drafts-for-friends' ),
					count($my_drafts),
					$my_drafts,
				),
				array(
					__( 'Scheduled Posts:', 'drafts-for-friends' ),
					count($my_scheduled),
					$my_scheduled,
				),
				array(
					__( 'Pending Review:', 'drafts-for-friends' ),
					count($my_pending),
					$my_pending,
				),
			);
			return $drafts; 
		}

		// returns all drafts by current user
		function fetch_users_drafts( $author, $post_status ) {
			global $wpdb;
			$post_type = 'post';
			isset ( $author ) ? $and = 'AND post_author = ' : $and = '';
			return $wpdb->get_results( $wpdb->prepare ( "SELECT ID, post_title, post_author FROM $wpdb->posts WHERE post_type = %s AND post_status = %s $and %s  ORDER BY post_modified DESC", $post_type, $post_status, $author ) );
		}
		
		// returns all currently shared links by current user
		function get_shared() {
			if ( isset( $this->user_options['shared'] ) ) {
				return $this->user_options['shared'];
			}
		}

		// prepares data array for WP_List_Table output
		function prepare_data() {
			$data = array();
			$housekeeping = array( 'draft' => array() );
			$s = $this->get_shared();
			if ( isset( $s ) ) {
				foreach ( $s as $share ) {
					$p = get_post( $share['ID'] );
					if ( ! $p ) {                                    // this post was permanently deleted, so
						$housekeeping['draft'][] = $share['ID'];     // mark it for deletion from the array
						continue;                                    // and don't try to display it in this run
					}

					switch ( get_post_status( $p ) ) {
						case 'publish':
							$status = sprintf( __( 'Published', 'drafts-for-friends' ) );
							$status_class = 'status-ok';
							break;
						case 'pending':
							$status = sprintf( __( 'Pending review', 'drafts-for-friends' ) );
							$status_class = 'status-ok';
							break;
						case 'draft':
							$status = sprintf( __( 'Draft', 'drafts-for-friends' ) );
							$status_class = 'status-ok';
							break;
						case 'future':
							$status = sprintf( __( 'Scheduled', 'drafts-for-friends' ) );
							$status_class = 'status-ok';
							break;
						case 'private':
							$status = sprintf( __( 'Private', 'drafts-for-friends' ) );
							$status_class = 'status-error';
							break;
						case 'trash':
							$status = sprintf( __( 'Trashed', 'drafts-for-friends' ) );
							$status_class = 'status-error';
							break;
						default:
							$status = get_post_status( $p );
							$status_class = '';
					}					
					$time = $this->expires_in( $share['expires'] );
					$user = get_userdata( $p->post_author );
					$entry = array( 
						'ID'              => $share['ID'],
						'title'           => $p->post_title,
						'author'          => $user->user_login,
						'status_class'    => $status_class,
						'status'          => $status,
						'link'            => get_bloginfo('url') . '/?p=' . $p->ID . '&key=' . $share['key'],
						'expires_class'   => $time[0],
						'expires'         => $time[1],
						'key'             => $share['key']
						);
					array_push( $data, $entry );
				}
				if ( ! empty( $housekeeping['draft'] ) ) {
					$this->process_bulk_delete( $housekeeping ); // delete links to posts that no longer exist
				}
			}
			return $data;
		}

		// strip _wp_http_referer from URL before handling the bulk request to avoid 
		// fattening up the URL after multiple bulk requests
		function strip_http_referer() {
			if ( ! empty( $_GET['_wp_http_referer'] ) ) {
				wp_safe_redirect( remove_query_arg( array( '_wp_http_referer' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
				exit;
			}
		}

		// processes incoming HTTP request, runs security nonce checks and calls required actions
		// returns (or rather relays) the result message from called action
		function process_HTTP_request() {
			$result = null;
			$bulk_nonce_check = 'bulk-' . $this->table->_args['plural']; // bulk action nonces check for plural arg in class DraftsForFriends_Table
			if ( isset( $_POST['draftsforfriends_submit'] ) && check_admin_referer( 'draftsforfriends-share' ) ) {
				$result = $this->process_post_options( $_POST );
			} elseif ( isset( $_POST['action'] ) && 'extend' === $_POST['action'] && check_admin_referer( 'draftsforfriends-extend-' . $_POST['key'] ) ) {
				$result = $this->process_extend( $_POST );
			} elseif ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && check_admin_referer( 'draftsforfriends-delete-' . $_GET['key'] ) ) {
				$result = $this->process_delete( $_GET );
			} elseif ( isset ( $_GET['action'] ) && 'bulk-delete' === $_GET['action'] && check_admin_referer( $bulk_nonce_check ) ) {
				$this->strip_http_referer();
				$result = $this->process_bulk_delete ( $_GET );
			} elseif ( isset ( $_GET['action'] ) && 'bulk-delete-expired' === $_GET['action'] && check_admin_referer( $bulk_nonce_check ) ) {
				$this->strip_http_referer();
				$result = $this->process_delete('expired');
			}
			return $result;
		}

		// outputs HTML of the admin page
		function output_existing_menu_sub_admin_page(){
			$message = $this->process_HTTP_request();
?>
			<div class="wrap">
				<h2 style="display:inline;"><?php _e( 'Drafts for Friends', 'drafts-for-friends' ); ?></h2><span style="display:inline;"><?php echo 'v' . DRAFTSFORFRIENDS_VERSION; ?></span>
<?php 			if ( isset( $message ) ): ?>
					<div id="message" style="margin-bottom:-43px;" class="<?php echo esc_attr( $message[0] ); ?> fade"><?php echo esc_html( $message[1] ); ?></div>
<?php 			endif; ?>	
				</br></br></br>
				<h3><?php _e( 'Share a new draft', 'drafts-for-friends' ); ?></h3>
				<form id="draftsforfriends-share" action="" method="post">
<?php
					wp_nonce_field( 'draftsforfriends-share' );
?>
					<p>
						<select id="draftsforfriends-postid" name="post_id">
							<option value=""><?php _e( 'Choose a post', 'drafts-for-friends' ); ?></option>
<?php
							$ds = $this->get_drafts();
							if ( empty ( $ds ) ) {
?>
								<option value="" disabled="disabled"></option>
								<option value="" disabled="disabled"><?php _e( 'You don\'t have any drafts to share!', 'drafts-for-friends' ); ?></option>
<?php								
							} else {
								foreach ( $ds as $dt ):
									if ( $dt[1] ):
?>
										<option value="" disabled="disabled"></option>
										<option value="" disabled="disabled"><?php echo $dt[0]; ?></option>
<?php
										foreach ( $dt[2] as $d ):
											if ( empty( $d->post_title ) ) {
												continue;
											}
											$user = get_userdata( $d->post_author );
											$title = mb_strimwidth( $d->post_title, 0, 60, ' ...' );
?>
											<option value="<?php echo esc_attr( $d->ID ) ?>"><?php echo esc_html( $title ) . '&nbsp;&nbsp;&nbsp;&nbsp;(' . __( 'by ', 'drafts-for-friends' ) . esc_html( $user->user_login ) . ')'; ?></option>
<?php
										endforeach;
									endif;
								endforeach;
							}
?>
						</select>
<?php						
						echo __( 'and', 'drafts-for-friends' );
?>						
						<input type="submit" class="button" name="draftsforfriends_submit"
							value="<?php _e( 'Share it', 'drafts-for-friends' ); ?>" />
						<?php echo __( 'for', 'drafts-for-friends' ); ?>
						<?php echo $this->tmpl_measure_select(null); ?>
					</p>
				</form> <!-- end new post share selection form -->
				<h3 style="margin-bottom: 0px;"><?php _e('Drafts you\'re currently sharing', 'drafts-for-friends'); ?></h3>
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-1">
						<div id="post-body-content">
							<div class="meta-box-sortables ui-sortable">
								<form method="GET"> <!-- wrapper form for bulk actions -->
									<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ) ?>">
<?php
									$this->table->prepare_items();
									$this->table->display(); 
?>
								</form> <!-- end wrapper form -->
							</div>
						</div>
					</div>
					<br class="clear">
				</div>
				<!-- submit form for individual actions from table row entries, populated and submitted by JS -->
				<form class="draftsforfriends-extend-main" id="draftsforfriends-extend-main" action="" method="POST">
					<input type="hidden" id="main-wpnonce" name="_wpnonce" value="" />
					<input type="hidden" id="main-action" name="action" value="" />
					<input type="hidden" id="main-key" name="key" value=""/>
					<input type="hidden" id="main-expires" name="expires" value=""/>
					<input type="hidden" id="main-measure" name="measure" value="" />
				</form> <!-- end JS form -->
			</div>
<?php
		}
	}
}

new DraftsForFriends();
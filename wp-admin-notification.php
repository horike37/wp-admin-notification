<?php 
/*
Plugin Name: WP Admin Notification
Plugin URI: http://digitalcube.jp
Description: Show message from administrator
Author: Digitalcube
Version: 1.0
Author URI: http://digitalcube.jp


Copyright 2014 Digitalcube (email : info@digitalcube.jp)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'WPAN_DOMAIN' ) )
	define( 'WPAN_DOMAIN', '' );
	
if ( ! defined( 'WPAN_PLUGIN_URL' ) )
	define( 'WPAN_PLUGIN_URL', plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ));

if ( ! defined( 'WPAN_PLUGIN_DIR' ) )
	define( 'WPAN_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ));
	
class WP_Admin_Notification {
	private static $instance;

	private function __construct() {}
	
	public static function get_instance() {
		if( !isset( self::$instance ) ) {
			$c = __CLASS__;
			self::$instance = new $c();
		}

		return self::$instance;
	}
	
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'register_post_type' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	public function admin_notices() {
		$messages = get_posts(array('post_type' => 'notification'));
		
		if ( empty($messages) )
			return;
		
		foreach ( $messages as $message ) {
			$user = wp_get_current_user();
			$target_role = get_post_meta( $message->ID, 'wp_admin_notification_display_role', true );
		
			if ( is_object($user) ) {
				$target_role = array_map('strtolower', $target_role);
				$compare = array_intersect($target_role, $user->roles);
			}
		
			if ( empty($compare) )
				continue;
				
			$term_start = get_post_meta( $message->ID, 'wp_admin_notification_display_term_start', true );
			$term_end = get_post_meta( $message->ID, 'wp_admin_notification_display_term_end', true );
			
			if ( strtotime(date_i18n('Y-m-d')) >= strtotime($term_start) && strtotime(date_i18n('Y-m-d')) <= strtotime($term_end) ) {

?>
<div class="update-nag">
    <?php echo apply_filters('the_content', $message->post_content); ?>
</div>
<?php
			}
		}
	}

	public function save_meta_box( $post_id ) {

		if ( wp_is_post_revision( $post_id ) )
			return;
			
		if ( get_post_type() != 'notification' )
			return;
			
		if ( !current_user_can( 'edit_wp_admin_notification', $post_id ) )
			return;
		
		if ( !isset($_POST['wp_admin_notification_meta_box_nonce']) || !wp_verify_nonce( $_POST['wp_admin_notification_meta_box_nonce'], 'wp_admin_notification_meta_box' ))
			return;
			
		if ( isset($_POST['term_start']) )
			update_post_meta( $post_id, 'wp_admin_notification_display_term_start', $_POST['term_start'] );

		if ( isset($_POST['term_end']) )
			update_post_meta( $post_id, 'wp_admin_notification_display_term_end', $_POST['term_end'] );
			
		if ( isset($_POST['taget_role']) )
			update_post_meta( $post_id, 'wp_admin_notification_display_role', $_POST['taget_role'] );
	}
	
	public function admin_enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_script( 'jquery-datepicker' );
		wp_enqueue_style( 'jquery-datepicker-css', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.11/themes/smoothness/jquery-ui.css');
		wp_enqueue_script( 'wpan_common', plugins_url('js/common.js', __FILE__) );
	}

	public function register_post_type() {
		$capabilities = array(
			'read_wp_admin_notification',
			'edit_wp_admin_notification',
			'delete_wp_admin_notification',
			'edit_wp_admin_notifications',
			'edit_others_wp_admin_notifications',
			'publish_wp_admin_notifications',
			'read_private_wp_admin_notifications',
			'delete_wp_admin_notifications',
			'delete_private_wp_admin_notifications',
			'delete_published_wp_admin_notifications',
			'delete_others_wp_admin_notifications',
			'edit_private_wp_admin_notifications',
			'edit_published_wp_admin_notifications'
		);
		$role = get_role( 'administrator' );
		foreach ( $capabilities as $cap ) {
    		$role->add_cap( $cap );
		}
		register_post_type( 'notification', 
	    							array( 
	    								'labels' => array( 'name' => __( 'Notification', WPAN_DOMAIN ) ),
	    								'public' => true,
	    								'publicly_queryable' => false,
										'has_archive' => false,
	    								'hierarchical' => false,
	    								'supports' => array( 'title', 'editor' ),
	    								'rewrite' => false,
	    								'can_export' => true,
	    								'capability_type' => 'wp_admin_notification',
    									'capabilities'    => $capabilities,
    									'map_meta_cap' => true,
    									'register_meta_box_cb' => array( $this, 'add_meta_box' )
	    							));
	}
	
	public function add_meta_box() {
		add_meta_box( 'wp_admin_notification_meta_box', __( 'configuration', WPAN_DOMAIN ), array( $this, 'meta_box' ), 'notification');
	}
	
	public function meta_box($post) {
		echo wp_nonce_field('wp_admin_notification_meta_box', 'wp_admin_notification_meta_box_nonce');
		$term_start = get_post_meta( get_the_ID(), 'wp_admin_notification_display_term_start', true );
		$term_end = get_post_meta( get_the_ID(), 'wp_admin_notification_display_term_end', true );
		$display_role = get_post_meta( get_the_ID(), 'wp_admin_notification_display_role', true );
		if (empty($display_role))
			$display_role = array();
		
		$editable_roles = get_editable_roles();

?>
<table class="form-table">
<tr><th><?php _e( 'Display Term', WPAN_DOMAIN ) ?></th><td><input id="wpan_term_start" type="text" name="term_start" size="20" value="<?php echo esc_attr($term_start); ?>" /> - <input id="wpan_term_end" type="text" name="term_end" size="20" value="<?php echo esc_attr($term_end); ?>" /></td></tr>
<tr><th><?php _e( 'Target Role', WPAN_DOMAIN ) ?></th>
<td>
<?php foreach ($editable_roles as $role_info): ?>
	<label><input type="checkbox" name="taget_role[]" <?php echo in_array($role_info['name'], $display_role) ? 'checked="checked"' : ''; ?> value="<?php echo esc_attr($role_info['name']); ?>" /> <?php echo translate_user_role( esc_html($role_info['name']) ); ?></label>
<?php endforeach; ?>
</td></tr>
</table>
<?php
	}
}

$wpan_instance = WP_Admin_Notification::get_instance();
$wpan_instance->init();
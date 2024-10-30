<?php

namespace Buddyboss\BpGdpr;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( '\BuddyBoss\BpGdpr\FriendshipExport' ) ) :

	final class FriendshipExport extends Export {

		/**
		 * Get the instance of this class.
		 *
		 * @return Controller|null
		 */
		public static function instance() {
			static $instance = null;

			if ( null === $instance ) {
				
				$instance = new FriendshipExport();
				$instance->setup( "bp_friendship", __( "Friendship", 'buddyboss-bp-gdpr' ) );
			}

			return $instance;
		}


		function process_data( $user, $page, $email_address = false ) {

			if(!$user || is_wp_error($user)) {
				return $this->response(array(),true);
			}

			$export_items = array();

			$data_items = $this->get_data($user,$page);
			
			foreach($data_items["items"] as $item) {
				
				$group_id    = "bp_friends";
				$group_label = __("Friends",'buddyboss-bp-gdpr');
				$item_id     = "{$this->exporter_name}-{$group_id}-{$item->id}";

				if ( $item->initiator_user_id == $user->ID ) {
					$friend_user_id = $item->friend_user_id;
					$is_initiator   = true;
				} else {
					$friend_user_id = $item->initiator_user_id;
					$is_initiator   = false;
				}
				
				if($item->is_confirmed  == "0" && $is_initiator) {
					$group_id .= "_pending_sent";
					$group_label =  __("Pending Sent Friend Requests",'buddyboss-bp-gdpr');
				}
				
				if($item->is_confirmed  == "0" && !$is_initiator) {
					$group_id .= "_pending_received";
					$group_label =  __("Pending Received Friend Requests",'buddyboss-bp-gdpr');
				}

				$friend_user = get_userdata($friend_user_id);

				$data = array(
					array("name"=>__('Friend Name','buddyboss-bp-gdpr'), "value" => $friend_user->display_name),
					array("name"=>__('Sent Created (GMT)','buddyboss-bp-gdpr'), "value" => $item->date_created),
				);
				
				if($item->is_confirmed == "1") {
					$is_initiator_value = (($is_initiator)?__("Yes","buddyboss-bp-gdpr"):__("No","buddyboss-bp-gdpr"));
					$data[] = array("name"=>__('Request Initiator','buddyboss-bp-gdpr'), "value" => $is_initiator_value);
				}
				
				$data = apply_filters("buddyboss_bp_gdpr_friendship_after_data_prepare",$data,$item,$data_items);

				$export_items[] = array(
					'group_id'    => $group_id,
					'group_label' => $group_label,
					'item_id'     => $item_id,
					'data'        => $data,
				);

			}

			$done = $data_items["total"] < $data_items["offset"];

			return $this->response($export_items,$done);
			
		}

		/**
		 * Remove friendship relations data from database..
		 * @param      $user
		 * @param      $page
		 * @param bool $email_address
		 *
		 * @return array
		 */
		function process_erase($user, $page, $email_address) {
			
			if(!$user || is_wp_error($user)) {
				return $this->response_erase(array(),true);
			}

			$items_removed  = true;
			$items_retained = false;
			
			friends_remove_data($user->ID);

			$done = true;
			
			return $this->response_erase($items_removed,$done,array(),$items_retained);
		
		}
		
		/**
		 * Returns the data & count of messages by page and user.
		 * @param $user
		 * @param $page
		 *
		 * @return array
		 */
		function get_data($user,$page) {
			global $wpdb,$bp;

			$wpdb->show_errors(false);
			$friends_table = $bp->friends->table_name;

			$table = "{$friends_table} item";

			$query_select = "item.*";
			$query_select_count = "COUNT(item.id)";
			$query_where = "item.initiator_user_id=%d OR item.friend_user_id=%d";

			$offset = ( $page - 1 ) * $this->items_per_batch;
			$limit  = "LIMIT {$this->items_per_batch} OFFSET {$offset}";

			$query = "SELECT {$query_select} FROM {$table} WHERE {$query_where} {$limit}";
			$query = $wpdb->prepare($query,$user->ID,$user->ID);
			$query_count = "SELECT {$query_select_count} FROM {$table} WHERE {$query_where}";
			$query_count = $wpdb->prepare($query_count,$user->ID,$user->ID);
			
			$count = (int) $wpdb->get_var($query_count);
			$items = $wpdb->get_results($query);

			return array(
				"total" => $count,
				"offset" => $offset,
				"items" => $items
			);

		}

		

	}

endif;

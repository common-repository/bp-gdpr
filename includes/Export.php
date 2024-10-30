<?php

namespace Buddyboss\BpGdpr;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( '\BuddyBoss\BpGdpr\Example' ) ) :

	abstract class Export {

		protected $exporter_name = "";
		protected $exporter_label = "";
		protected $items_per_batch = 50;

		function setup( $name, $label ) {
			
			$this->exporter_name  = $name;
			$this->exporter_label = $label;
			
			// Global Filter.
			$this->items_per_batch = apply_filters("buddyboss_bp_gdpr_item_per_batch",$this->items_per_batch);
			// Specific Filter.
			$this->items_per_batch = apply_filters("buddyboss_bp_gdpr_item_per_batch_{$name}",$this->items_per_batch);
			
			$this->hooks();
			
		}

		function hooks() {
			add_filter(
				'wp_privacy_personal_data_exporters',
				array( $this, "register_exporter" ),
				10
			);
			add_filter(
				'wp_privacy_personal_data_erasers',
				array( $this, "register_eraser" ),
				10
			);
		}

		/**
		 * @param $exporters
		 *
		 * @return mixed
		 */
		function register_exporter( $exporters ) {

			$exporters[ $this->exporter_name ] = array(
				'exporter_friendly_name' => $this->exporter_label,
				'callback'               => array( $this, 'process_export_callback' ),
			);

			return $exporters;
		}

		/**
		 * @param $erasers
		 *
		 * @return mixed
		 */
		function register_eraser( $erasers ) {
			
			$erasers[$this->exporter_name] = array(
				'eraser_friendly_name' => $this->exporter_label,
				'callback'             => array($this,"process_eraser_callback"),
			);

			return $erasers;
		}

		/**
		 * @param     $email_address
		 * @param int $page
		 *
		 * @return array
		 */
		function process_export_callback( $email_address, $page = 1 ) {
			
			$user = get_user_by( 'email', $email_address );

			do_action("buddyboss_bp_gdpr_pre_exporter_callback",$email_address,$page,$user);

			return $this->process_data( $user, $page, $email_address );
		}

		/**
		 * @param     $email_address
		 * @param int $page
		 *
		 * @return mixed
		 */
		function process_eraser_callback($email_address, $page = 1) {

			$user = get_user_by( 'email', $email_address );
			
			do_action("buddyboss_bp_gdpr_pre_eraser_callback",$email_address,$page,$user);
			
			return $this->process_erase($user, $page, $email_address);

		}
		
		/**
		 * Function should be override.
		 *
		 * @param      $user
		 * @param      $page
		 * @param bool $email_address
		 *
		 * @return array
		 */
		function process_data( $user, $page, $email_address ) {
			return $this->response(array(), true);
		}

		/**
		 * Function should be overiide.
		 * @param      $user
		 * @param      $page
		 * @param bool $email_address
		 *
		 * @return array
		 */
		function process_erase( $user, $page, $email_address ) {
			return $this->response_erase(false,true);
		}

		/**
		 * @param array $export_data
		 * @param bool  $done
		 *
		 * @return array
		 */
		function response($export_data = array(),$done=false) {
			return array(
				'data' => $export_data,
				'done' => $done,	
			);
		}

		/**
		 * @param bool  $item_removed
		 * @param bool  $done
		 * @param array $messages
		 * @param bool  $items_retained
		 *
		 * @return array
		 */
		function response_erase($item_removed = true,$done=false,$messages=array(),$items_retained=false) {
			return array(
				'items_removed' => $item_removed,
				'items_retained' => $items_retained,
				'messages' => $messages,
				'done' => $done,	
			);
		}

		/**
		 * @param $value
		 *
		 * @return string
		 */
		function easy_readable($value) {
			
			if(!is_serialized($value) && !is_array($value) && !is_object($value)) {
				return $value;
			}
			
			$value = maybe_serialize($value);
			
			return json_encode($value,JSON_PRETTY_PRINT);
			
		}
		
	}

endif; 
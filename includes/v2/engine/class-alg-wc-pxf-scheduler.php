<?php
/**
 * Product XML Feeds for WooCommerce - v2 schedules.
 *
 * WP-Cron event alg_wc_pxf_scheduled (args feed id) per feed. Free: daily or manual. Pro: also hourly, twicedaily and
 * every 15 minutes, plus "rebuild on change" (a run at most every 10 minutes per feed after product or stock changes).
 * A schedule the current edition does not offer runs daily; the stored value is kept, so adding Pro back restores it.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Scheduler' ) ) :

class Alg_WC_PXF_Scheduler {

	/**
	 * Hooks.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const HOOK         = 'alg_wc_pxf_scheduled';
	const REBUILD_HOOK = 'alg_wc_pxf_rebuild';
	const REBUILD_GAP  = 600;
	const META_CHANGE  = '_alg_pxf_last_change_run';
	const INTERVAL_15  = 'alg_pxf_15min';

	/**
	 * Feeds already queued for a rebuild in this request.
	 *
	 * @var array
	 */
	protected static $queued = array();

	/**
	 * Feeds with rebuild_on_change (per request).
	 *
	 * @var int[]|null
	 */
	protected static $rebuild_feeds = null;

	/**
	 * init.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( self::HOOK, array( __CLASS__, 'scheduled' ) );
		add_action( 'alg_wc_pxf_feed_saved', array( __CLASS__, 'sync' ) );
		add_action( 'alg_wc_pxf_feed_deleted', array( __CLASS__, 'clear' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_sync_all' ) );
		add_action( self::REBUILD_HOOK, array( __CLASS__, 'rebuild' ) );
		foreach ( array( 'woocommerce_update_product', 'woocommerce_update_product_variation', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'product_changed' ) );
		}
	}

	/**
	 * labels: every schedule the current edition offers.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array value => label.
	 */
	static function labels() {
		$labels = array(
			'daily'  => __( 'Once a day', 'product-xml-feeds-for-woocommerce' ),
			'manual' => __( 'Only when I click Update', 'product-xml-feeds-for-woocommerce' ),
		);
		if ( Alg_WC_PXF::is_pro() ) {
			$labels['twicedaily'] = __( 'Twice a day', 'product-xml-feeds-for-woocommerce' );
			$labels['hourly']     = __( 'Every hour', 'product-xml-feeds-for-woocommerce' );
			$labels['15min']      = __( 'Every 15 minutes', 'product-xml-feeds-for-woocommerce' );
		}
		return $labels;
	}

	/**
	 * effective: the schedule that really runs.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $schedule Stored schedule.
	 * @return  string
	 */
	static function effective( $schedule ) {
		$labels = self::labels();
		return isset( $labels[ $schedule ] ) ? $schedule : 'daily';
	}

	/**
	 * recurrence: WP-Cron recurrence name.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $schedule Schedule.
	 * @return  string
	 */
	static function recurrence( $schedule ) {
		return '15min' === $schedule ? self::INTERVAL_15 : $schedule;
	}

	/**
	 * cron_schedules: the 15 minute interval exists only in Pro.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $schedules Schedules.
	 * @return  array
	 */
	static function cron_schedules( $schedules ) {
		if ( Alg_WC_PXF::is_pro() && ! isset( $schedules[ self::INTERVAL_15 ] ) ) {
			$schedules[ self::INTERVAL_15 ] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes', 'product-xml-feeds-for-woocommerce' ),
			);
		}
		return $schedules;
	}

	/**
	 * sync: one event per feed with the right recurrence (kept when it is already right).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 */
	static function sync( $feed_id ) {
		$feed_id = absint( $feed_id );
		$args    = array( $feed_id );
		if ( ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			self::clear( $feed_id );
			return;
		}
		$config   = Alg_WC_PXF_Feeds::get_config( $feed_id );
		$schedule = self::effective( $config['schedule'] );
		if ( 'manual' === $schedule ) {
			self::clear( $feed_id );
			return;
		}
		$recurrence = self::recurrence( $schedule );
		$event      = wp_get_scheduled_event( self::HOOK, $args );
		if ( $event && isset( $event->schedule ) && $recurrence === $event->schedule ) {
			return;
		}
		self::clear( $feed_id );
		$all      = wp_get_schedules();
		$interval = isset( $all[ $recurrence ]['interval'] ) ? (int) $all[ $recurrence ]['interval'] : DAY_IN_SECONDS;
		wp_schedule_event( time() + $interval, $recurrence, self::HOOK, $args );
	}

	/**
	 * clear.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 */
	static function clear( $feed_id ) {
		wp_clear_scheduled_hook( self::HOOK, array( absint( $feed_id ) ) );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::REBUILD_HOOK, array( absint( $feed_id ) ), Alg_WC_PXF_Engine::GROUP );
		}
	}

	/**
	 * maybe_sync_all: twice a day, and right away when Pro was added or removed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function maybe_sync_all() {
		$edition = Alg_WC_PXF::is_pro() ? 'pro' : 'free';
		if ( get_transient( 'alg_pxf_schedules_checked' ) === $edition ) {
			return;
		}
		foreach ( Alg_WC_PXF_Feeds::all() as $feed_id ) {
			self::sync( $feed_id );
		}
		set_transient( 'alg_pxf_schedules_checked', $edition, 12 * HOUR_IN_SECONDS );
	}

	/**
	 * scheduled: WP-Cron callback.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 */
	static function scheduled( $feed_id ) {
		$feed_id = absint( $feed_id );
		if ( ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			self::clear( $feed_id );
			return;
		}
		$config = Alg_WC_PXF_Feeds::get_config( $feed_id );
		if ( 'manual' === self::effective( $config['schedule'] ) ) {
			self::clear( $feed_id );
			return;
		}
		self::sync( $feed_id );
		Alg_WC_PXF_Engine::run( $feed_id, 'schedule' );
	}

	/**
	 * product_changed: Pro, queue a rebuild for feeds with rebuild_on_change.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function product_changed() {
		if ( ! Alg_WC_PXF::is_pro() ) {
			return;
		}
		if ( null === self::$rebuild_feeds ) {
			self::$rebuild_feeds = array();
			foreach ( Alg_WC_PXF_Feeds::all() as $feed_id ) {
				$config = Alg_WC_PXF_Feeds::get_config( $feed_id );
				if ( $config['rebuild_on_change'] ) {
					self::$rebuild_feeds[] = $feed_id;
				}
			}
		}
		foreach ( self::$rebuild_feeds as $feed_id ) {
			self::queue_rebuild( $feed_id );
		}
	}

	/**
	 * queue_rebuild: at most one run every REBUILD_GAP seconds per feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @param   int $delay   Minimum delay in seconds.
	 */
	static function queue_rebuild( $feed_id, $delay = 60 ) {
		$feed_id = absint( $feed_id );
		if ( isset( self::$queued[ $feed_id ] ) || ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return;
		}
		self::$queued[ $feed_id ] = true;
		$pending = as_get_scheduled_actions( array(
			'hook'     => self::REBUILD_HOOK,
			'args'     => array( $feed_id ),
			'group'    => Alg_WC_PXF_Engine::GROUP,
			'status'   => ActionScheduler_Store::STATUS_PENDING,
			'per_page' => 1,
		), 'ids' );
		if ( ! empty( $pending ) ) {
			return;
		}
		$last = (int) get_post_meta( $feed_id, self::META_CHANGE, true );
		as_schedule_single_action( max( time() + (int) $delay, $last + self::REBUILD_GAP ), self::REBUILD_HOOK, array( $feed_id ), Alg_WC_PXF_Engine::GROUP );
	}

	/**
	 * rebuild: Action Scheduler callback. When a run is going, try again after the gap.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 */
	static function rebuild( $feed_id ) {
		$feed_id = absint( $feed_id );
		if ( ! Alg_WC_PXF::is_pro() || ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			return;
		}
		$config = Alg_WC_PXF_Feeds::get_config( $feed_id );
		if ( ! $config['rebuild_on_change'] ) {
			return;
		}
		$state = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
		if ( in_array( $state['status'], Alg_WC_PXF_Engine::ACTIVE, true ) && Alg_WC_PXF_Engine::is_locked( $feed_id ) ) {
			unset( self::$queued[ $feed_id ] );
			self::queue_rebuild( $feed_id, self::REBUILD_GAP );
			return;
		}
		update_post_meta( $feed_id, self::META_CHANGE, time() );
		Alg_WC_PXF_Engine::run( $feed_id, 'change' );
	}

}

endif;

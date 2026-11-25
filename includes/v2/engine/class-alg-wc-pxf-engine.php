<?php
/**
 * Product XML Feeds for WooCommerce - v2 engine: background runs in batches through Action Scheduler.
 *
 * run() queues a run with a new run id; every batch (hook alg_wc_pxf_batch, args feed id and run id) pages products by
 * (post_date, ID), expands variations, builds model, item and issues, appends to the temporary file and saves the cursor.
 * A batch whose run id is no longer the feed's run id stops. One run per feed (option lock with expiry), one batch at a
 * time per feed (short batch lock). tick() runs one batch in the current request for sites where WP-Cron is off.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Engine' ) ) :

class Alg_WC_PXF_Engine {

	/**
	 * Hooks, group, limits.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const HOOK           = 'alg_wc_pxf_batch';
	const GROUP          = 'alg-pxf';
	const LOCK_TTL       = 900;
	const BATCH_LOCK_TTL = 300;
	const BATCH_START    = 100;
	const BATCH_MIN      = 10;
	const BATCH_MAX      = 500;
	const TICK_AFTER     = 60;
	const MAX_ATTEMPTS   = 3;
	const MAX_STORED     = 500;

	/**
	 * Active statuses.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const ACTIVE = array( 'queued', 'running' );

	/**
	 * init.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function init() {
		add_action( self::HOOK, array( __CLASS__, 'batch' ), 10, 2 );
		add_action( 'alg_wc_pxf_feed_deleted', array( __CLASS__, 'forget' ) );
	}

	/**
	 * run: queues a new run. When a run is already going it is returned as it is, unless $restart is true.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int    $feed_id Feed id.
	 * @param   string $source  manual, schedule, change, rest, ...
	 * @param   bool   $restart Replace a run that is going (its batches then stop as stale).
	 * @return  array|WP_Error State.
	 */
	static function run( $feed_id, $source = 'manual', $restart = false ) {
		$feed_id = absint( $feed_id );
		if ( ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			return new WP_Error( 'alg_pxf_no_feed', __( 'This feed does not exist.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 404 ) );
		}
		$old = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
		if ( ! self::lock_acquire( self::lock_name( $feed_id ), self::LOCK_TTL, $restart ) ) {
			return $old;
		}
		$config  = Alg_WC_PXF_Feeds::get_config( $feed_id );
		$channel = Alg_WC_PXF_Feeds::channel( $config['channel'] );
		if ( ! $channel ) {
			self::lock_release( self::lock_name( $feed_id ) );
			return new WP_Error( 'alg_pxf_no_channel', __( 'The channel of this feed is not available.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 400 ) );
		}
		if ( ! empty( $old['run_id'] ) ) {
			self::unschedule( $feed_id, $old['run_id'] );
		}
		$run_id = wp_generate_uuid4();
		$state  = array_merge( Alg_WC_PXF_Feeds::default_state(), array(
			'status'     => 'queued',
			'run_id'     => $run_id,
			'total'      => self::count_products( $feed_id, $config ),
			'started'    => time(),
			'last_good'  => (int) $old['last_good'],
			'source'     => sanitize_key( $source ),
			'batch_size' => self::start_size(),
		) );
		Alg_WC_PXF_Feeds::save_state( $feed_id, $state );
		delete_transient( 'alg_pxf_issues_run_' . $feed_id );
		self::enqueue( $feed_id, $run_id );
		do_action( 'alg_wc_pxf_run_queued', $feed_id, $state );
		return $state;
	}

	/**
	 * start_size: first batch size of a run, from "Products per batch" on the Settings screen (option
	 * alg_pxf_settings), else BATCH_START. The adaptive sizing works from there.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  int
	 */
	static function start_size() {
		$settings = get_option( 'alg_pxf_settings', array() );
		$size     = ( is_array( $settings ) && ! empty( $settings['batch_size'] ) ) ? absint( $settings['batch_size'] ) : self::BATCH_START;
		return max( self::BATCH_MIN, min( self::BATCH_MAX, $size ) );
	}

	/**
	 * tick: runs one batch of the current run in this request.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int   $feed_id Feed id.
	 * @param   float $budget  Seconds (0 = default).
	 * @return  array State after the batch.
	 */
	static function tick( $feed_id, $budget = 0 ) {
		$feed_id = absint( $feed_id );
		$state   = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
		if ( in_array( $state['status'], self::ACTIVE, true ) ) {
			self::batch( $feed_id, $state['run_id'], $budget );
			$state = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
		}
		return $state;
	}

	/**
	 * needs_tick: a run is going but no batch ran for TICK_AFTER seconds (WP-Cron off or stuck).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $state State.
	 * @return  bool
	 */
	static function needs_tick( array $state ) {
		if ( ! in_array( $state['status'], self::ACTIVE, true ) ) {
			return false;
		}
		$last = max( (int) $state['last_batch'], (int) $state['started'] );
		return ( time() - $last ) > (int) apply_filters( 'alg_wc_pxf_tick_after', self::TICK_AFTER );
	}

	/**
	 * batch: Action Scheduler callback; also used by tick().
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int    $feed_id Feed id.
	 * @param   string $run_id  Run id.
	 * @param   float  $budget  Seconds (0 = default).
	 * @return  string done, next, stale, busy, failed.
	 */
	static function batch( $feed_id, $run_id, $budget = 0 ) {
		$feed_id = absint( $feed_id );
		$run_id  = (string) $run_id;
		$state   = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
		if ( ! self::is_current( $state, $run_id ) ) {
			return 'stale';
		}
		$lock = self::batch_lock_name( $feed_id );
		if ( ! self::lock_acquire( $lock, self::BATCH_LOCK_TTL ) ) {
			return 'busy';
		}
		$user          = get_current_user_id();
		$guard         = new stdClass();
		$guard->active = true;
		register_shutdown_function( array( __CLASS__, 'shutdown_guard' ), $guard, $feed_id, $run_id );
		$result = 'failed';
		try {
			$state = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
			if ( ! self::is_current( $state, $run_id ) ) {
				$result = 'stale';
			} elseif ( (int) $state['attempts'] >= self::MAX_ATTEMPTS ) {
				self::fail( $feed_id, $run_id, __( 'The run stopped three times at the same place, so it was ended. The last good file was kept. Check the debug log for the product that causes it.', 'product-xml-feeds-for-woocommerce' ) );
			} else {
				// Write-ahead marker: if this request dies, the next attempt knows.
				$state['attempts'] = (int) $state['attempts'] + 1;
				Alg_WC_PXF_Feeds::save_state( $feed_id, $state );
				wp_set_current_user( 0 ); // Generate as a guest: no member or role prices.
				$result = self::process( $feed_id, $state, (float) $budget );
			}
		} catch ( Throwable $e ) {
			self::fail( $feed_id, $run_id, $e->getMessage() );
			$result = 'failed';
		} finally {
			$guard->active = false;
			wp_set_current_user( $user );
			self::lock_release( $lock );
		}
		return $result;
	}

	/**
	 * process: one batch.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int   $feed_id Feed id.
	 * @param   array $state   State (fresh).
	 * @param   float $budget  Seconds (0 = default).
	 * @return  string done, next, stale.
	 * @throws  RuntimeException On anything that ends the run.
	 */
	protected static function process( $feed_id, array $state, $budget ) {
		$run_id  = $state['run_id'];
		$config  = Alg_WC_PXF_Feeds::get_config( $feed_id );
		$channel = Alg_WC_PXF_Feeds::channel( $config['channel'] );
		if ( ! $channel ) {
			throw new RuntimeException( __( 'The channel of this feed is not available.', 'product-xml-feeds-for-woocommerce' ) );
		}
		if ( ! class_exists( 'Alg_WC_PXF_Product_Data' ) || ! class_exists( 'Alg_WC_PXF_Mapper' ) ) {
			throw new RuntimeException( __( 'The product data part of the plugin is missing. Reinstall the plugin.', 'product-xml-feeds-for-woocommerce' ) );
		}
		if ( ! Alg_WC_PXF_Feeds::ensure_dir() ) {
			throw new RuntimeException( __( 'The folder uploads/product-feeds could not be created or is not writable.', 'product-xml-feeds-for-woocommerce' ) );
		}
		$path   = Alg_WC_PXF_Feeds::file_path( $feed_id );
		$writer = Alg_WC_PXF_Writer::open( Alg_WC_PXF_Feeds::format( $config, $channel ), $channel, $path );
		if ( is_wp_error( $writer ) ) {
			throw new RuntimeException( $writer->get_error_message() );
		}
		$raw = ! empty( $channel['raw'] );
		if ( 0 === (int) $state['batches'] ) {
			if ( $raw && is_callable( array( 'Alg_WC_PXF_Mapper', 'custom_part' ) ) ) {
				$writer->set_parts( array( 'header' => Alg_WC_PXF_Mapper::custom_part( 'header', $config ) ) );
			}
			$writer->begin();
		} elseif ( ! $writer->resume( (int) $state['bytes'] ) ) {
			throw new RuntimeException( __( 'The temporary feed file was lost during the run. Start the run again.', 'product-xml-feeds-for-woocommerce' ) );
		}

		do_action( 'alg_wc_pxf_batch_start', $feed_id, $config, $state );
		if ( '' !== $config['language'] ) {
			do_action( 'wpml_switch_language', $config['language'] );
		}

		$started   = microtime( true );
		$budget    = $budget > 0 ? $budget : self::time_budget();
		$mem_limit = self::memory_limit();
		$size      = (int) $state['batch_size'] > 0 ? (int) $state['batch_size'] : self::BATCH_START;
		$size      = max( self::BATCH_MIN, min( self::BATCH_MAX, $size ) );
		$ids       = self::query_ids( $feed_id, $config, $state['cursor'], $size );
		$mode      = '' !== $config['variation_mode'] ? $config['variation_mode'] : ( ! empty( $channel['variation_mode'] ) ? $channel['variation_mode'] : 'all' );
		$stored    = get_transient( 'alg_pxf_issues_run_' . $feed_id );
		$stored    = is_array( $stored ) ? $stored : array();
		$processed = 0;
		$early     = false;

		if ( ! empty( $ids ) ) {
			_prime_post_caches( $ids, true, true );
		}
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post ) {
				$product = wc_get_product( $post );
				if ( $product ) {
					foreach ( self::expand( $product, $mode, $config ) as $one ) {
						self::add_item( $writer, $feed_id, $one, $channel, $config, $state, $stored );
					}
				}
				$state['cursor'] = array( 'last_date' => $post->post_date, 'last_id' => (int) $post->ID );
			}
			$processed++;
			$state['done'] = (int) $state['done'] + 1;
			if ( ( microtime( true ) - $started ) > $budget || ( $mem_limit > 0 && memory_get_usage( true ) > 0.8 * $mem_limit ) ) {
				$early = true;
				break;
			}
			if ( $mem_limit > 0 && memory_get_usage( true ) > 0.6 * $mem_limit && function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
		}

		if ( '' !== $config['language'] ) {
			do_action( 'wpml_switch_language', null );
		}
		do_action( 'alg_wc_pxf_batch_end', $feed_id, $config, $state );

		// Another run took over while this batch was working: leave its state alone.
		$fresh = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
		if ( ! self::is_current( $fresh, $run_id ) ) {
			$writer->close();
			return 'stale';
		}

		$elapsed             = microtime( true ) - $started;
		$state['batches']    = (int) $state['batches'] + 1;
		$state['bytes']      = $writer->close();
		$state['status']     = 'running';
		$state['last_batch'] = time();
		$state['attempts']   = 0;
		$state['error']      = '';
		if ( $early ) {
			$state['batch_size'] = max( self::BATCH_MIN, (int) floor( max( 1, $processed ) * 0.8 ) );
		} elseif ( count( $ids ) === $size && $elapsed < $budget / 3 ) {
			$state['batch_size'] = min( self::BATCH_MAX, (int) ceil( $size * 1.5 ) );
		}
		$finished = ! $early && count( $ids ) < $size;

		if ( ! $finished ) {
			Alg_WC_PXF_Feeds::save_state( $feed_id, $state );
			set_transient( 'alg_pxf_issues_run_' . $feed_id, $stored, WEEK_IN_SECONDS );
			self::lock_acquire( self::lock_name( $feed_id ), self::LOCK_TTL, true );
			self::enqueue( $feed_id, $run_id );
			return 'next';
		}

		if ( 0 === (int) $state['written'] && file_exists( $path ) && ! apply_filters( 'alg_wc_pxf_publish_empty', false, $feed_id ) ) {
			throw new RuntimeException( __( 'No products were written, so the last good file was kept. Check the feed filters.', 'product-xml-feeds-for-woocommerce' ) );
		}
		if ( $raw && is_callable( array( 'Alg_WC_PXF_Mapper', 'custom_part' ) ) ) {
			$user = get_current_user_id();
			wp_set_current_user( 0 );
			$writer->set_parts( array( 'footer' => Alg_WC_PXF_Mapper::custom_part( 'footer', $config ) ) );
			wp_set_current_user( $user );
		}
		if ( ! $writer->end() ) {
			throw new RuntimeException( __( 'The new file could not replace the old one, so the last good file was kept. Check the folder permissions.', 'product-xml-feeds-for-woocommerce' ) );
		}
		$state['status']    = 'done';
		$state['finished']  = time();
		$state['last_good'] = time();
		Alg_WC_PXF_Feeds::save_state( $feed_id, $state );
		set_transient( 'alg_pxf_issues_' . $feed_id, $stored, WEEK_IN_SECONDS );
		delete_transient( 'alg_pxf_issues_run_' . $feed_id );
		self::lock_release( self::lock_name( $feed_id ) );
		self::unschedule( $feed_id, $run_id );
		do_action( 'alg_wc_pxf_run_done', $feed_id, $state, $path );
		return 'done';
	}

	/**
	 * add_item: model -> items (Alg_WC_PXF_Mapper::items(), one or more) -> issues -> writer.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   Alg_WC_PXF_Writer $writer  Writer.
	 * @param   int               $feed_id Feed id.
	 * @param   WC_Product        $product Product or variation.
	 * @param   array             $channel Channel.
	 * @param   array             $config  Config.
	 * @param   array             $state   State (counts updated).
	 * @param   array             $stored  Per-product issues (first MAX_STORED).
	 */
	protected static function add_item( $writer, $feed_id, $product, array $channel, array $config, array &$state, array &$stored ) {
		try {
			$model = Alg_WC_PXF_Product_Data::model( $product, $config );
			$items = is_callable( array( 'Alg_WC_PXF_Mapper', 'items' ) ) ? Alg_WC_PXF_Mapper::items( $model, $channel, $config ) : array( Alg_WC_PXF_Mapper::item( $model, $channel, $config ) );
		} catch ( Throwable $e ) {
			self::count_issues( $product, array( self::error_issue( $e ) ), $state, $stored );
			$state['left_out'] = (int) $state['left_out'] + 1;
			return;
		}
		foreach ( (array) $items as $item ) {
			if ( empty( $item ) || ! is_array( $item ) ) {
				continue;
			}
			if ( ! empty( $item['_exclude'] ) ) {
				// Left out by a feed rule: counted in left_out, and in excluded on its own.
				$state['left_out'] = (int) $state['left_out'] + 1;
				$state['excluded'] = (int) $state['excluded'] + 1;
				continue;
			}
			$issues = array();
			if ( ! isset( $item['_raw'] ) && class_exists( 'Alg_WC_PXF_Validator' ) ) {
				try {
					$issues = (array) Alg_WC_PXF_Validator::check( $item, $model, $channel );
				} catch ( Throwable $e ) {
					$issues = array( self::error_issue( $e ) );
				}
			}
			$has_error = self::count_issues( $product, $issues, $state, $stored );
			if ( $has_error && $config['leave_out_errors'] && Alg_WC_PXF::is_pro() ) {
				$state['left_out'] = (int) $state['left_out'] + 1;
				continue;
			}
			$writer->item( $item );
			$state['written'] = (int) $state['written'] + 1;
		}
	}

	/**
	 * error_issue.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   Throwable $e Error.
	 * @return  array
	 */
	protected static function error_issue( $e ) {
		return array(
			'code'     => 'product_error',
			'field'    => '',
			'severity' => 'error',
			/* translators: %s: Error message. */
			'message'  => sprintf( __( 'This product could not be read: %s', 'product-xml-feeds-for-woocommerce' ), $e->getMessage() ),
			'fix'      => 'product',
		);
	}

	/**
	 * count_issues: counts by code in the state, keeps the first MAX_STORED per product.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product.
	 * @param   array      $issues  Issues.
	 * @param   array      $state   State.
	 * @param   array      $stored  Stored issues.
	 * @return  bool Whether an issue has severity error.
	 */
	protected static function count_issues( $product, array $issues, array &$state, array &$stored ) {
		$has_error = false;
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$code = isset( $issue['code'] ) ? sanitize_key( $issue['code'] ) : 'unknown';
			$state['issues'][ $code ] = ( isset( $state['issues'][ $code ] ) ? (int) $state['issues'][ $code ] : 0 ) + 1;
			if ( isset( $issue['severity'] ) && 'error' === $issue['severity'] ) {
				$has_error = true;
			}
			if ( count( $stored ) < self::MAX_STORED ) {
				$stored[] = array_merge( $issue, array(
					'code'       => $code,
					'product_id' => (int) $product->get_id(),
					'parent_id'  => (int) $product->get_parent_id(),
					'name'       => wp_strip_all_tags( $product->get_name() ),
				) );
			}
		}
		return $has_error;
	}

	/**
	 * expand: the products one product becomes in the feed, per variation mode.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product.
	 * @param   string     $mode    all, parent, default, cheapest.
	 * @param   array      $config  Config.
	 * @return  WC_Product[]
	 */
	static function expand( $product, $mode, array $config ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return self::passes( $product, $config ) ? array( $product ) : array();
		}
		if ( 'parent' === $mode ) {
			return array( $product );
		}
		$children = array();
		$ids      = array_map( 'intval', (array) $product->get_children() );
		if ( ! empty( $ids ) ) {
			_prime_post_caches( $ids, false, true );
		}
		foreach ( $ids as $child_id ) {
			if ( 'publish' !== get_post_status( $child_id ) ) {
				continue;
			}
			$child = wc_get_product( $child_id );
			if ( $child && self::passes( $child, $config ) ) {
				$children[] = $child;
			}
		}
		if ( empty( $children ) || 'all' === $mode ) {
			return $children;
		}
		if ( 'cheapest' === $mode ) {
			$best = null;
			foreach ( $children as $child ) {
				if ( '' === (string) $child->get_price() ) {
					continue;
				}
				if ( null === $best || (float) $child->get_price() < (float) $best->get_price() ) {
					$best = $child;
				}
			}
			return array( $best ? $best : $children[0] );
		}
		// 'default': the variation that matches the default attributes, else the first one.
		$defaults = (array) $product->get_default_attributes();
		if ( ! empty( $defaults ) ) {
			foreach ( $children as $child ) {
				$match = true;
				$own   = (array) $child->get_attributes();
				foreach ( $defaults as $name => $value ) {
					$raw = isset( $own[ $name ] ) ? (string) $own[ $name ] : '';
					// '' on a variation means "any value".
					if ( '' !== $raw && $raw !== (string) $value ) {
						$match = false;
						break;
					}
				}
				if ( $match ) {
					return array( $child );
				}
			}
		}
		return array( $children[0] );
	}

	/**
	 * passes: the filters that also apply to single variations (stock, price, sale, excluded ids).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product.
	 * @param   array      $config  Config.
	 * @return  bool
	 */
	static function passes( $product, array $config ) {
		$f = $config['filters'];
		if ( ! empty( $f['exclude_ids'] ) && in_array( (int) $product->get_id(), array_map( 'intval', $f['exclude_ids'] ), true ) ) {
			return false;
		}
		if ( ! empty( $f['stock'] ) && ! in_array( $product->get_stock_status(), $f['stock'], true ) ) {
			return false;
		}
		$price = $product->get_price();
		if ( '' !== $f['min_price'] && ( '' === (string) $price || (float) $price < (float) $f['min_price'] ) ) {
			return false;
		}
		if ( '' !== $f['max_price'] && ( '' === (string) $price || (float) $price > (float) $f['max_price'] ) ) {
			return false;
		}
		if ( 'yes' === $f['on_sale'] && ! $product->is_on_sale() ) {
			return false;
		}
		if ( 'no' === $f['on_sale'] && $product->is_on_sale() ) {
			return false;
		}
		return (bool) apply_filters( 'alg_wc_pxf_include_product', true, $product, $config );
	}

	/**
	 * query_args: published products that match the feed filters (parents; variations are checked one by one).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int   $feed_id Feed id.
	 * @param   array $config  Config.
	 * @return  array
	 */
	static function query_args( $feed_id, array $config ) {
		$f    = $config['filters'];
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'orderby'             => array( 'date' => 'ASC', 'ID' => 'ASC' ),
			'ignore_sticky_posts' => true,
			'suppress_filters'    => false,
			'cache_results'       => false,
			'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => (array) apply_filters( 'alg_wc_pxf_skip_types', array( 'grouped' ), $config ),
					'operator' => 'NOT IN',
				),
			),
		);
		foreach ( array( 'include_cats' => array( 'product_cat', 'IN' ), 'exclude_cats' => array( 'product_cat', 'NOT IN' ), 'include_tags' => array( 'product_tag', 'IN' ), 'exclude_tags' => array( 'product_tag', 'NOT IN' ) ) as $key => $rule ) {
			if ( ! empty( $f[ $key ] ) ) {
				$args['tax_query'][] = array(
					'taxonomy'         => $rule[0],
					'field'            => 'term_id',
					'terms'            => array_map( 'intval', $f[ $key ] ),
					'operator'         => $rule[1],
					'include_children' => true,
				);
			}
		}
		$meta = array();
		if ( ! empty( $f['stock'] ) ) {
			$meta[] = array( 'key' => '_stock_status', 'value' => $f['stock'], 'compare' => 'IN' );
		}
		if ( '' !== $f['min_price'] ) {
			$meta[] = array( 'key' => '_price', 'value' => (float) $f['min_price'], 'compare' => '>=', 'type' => 'DECIMAL(20,4)' );
		}
		if ( '' !== $f['max_price'] ) {
			$meta[] = array( 'key' => '_price', 'value' => (float) $f['max_price'], 'compare' => '<=', 'type' => 'DECIMAL(20,4)' );
		}
		if ( ! empty( $meta ) ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		$not_in = array_map( 'intval', $f['exclude_ids'] );
		if ( 'yes' === $f['on_sale'] ) {
			$on_sale          = array_map( 'intval', wc_get_product_ids_on_sale() );
			$args['post__in'] = ! empty( $on_sale ) ? $on_sale : array( 0 );
		} elseif ( 'no' === $f['on_sale'] ) {
			// Variable products with some variations on sale stay in; each variation is checked on its own.
			foreach ( wc_get_product_ids_on_sale() as $sale_id ) {
				if ( 'product' !== get_post_type( $sale_id ) ) {
					continue;
				}
				$sale_product = wc_get_product( $sale_id );
				if ( $sale_product && ! $sale_product->is_type( 'variable' ) ) {
					$not_in[] = (int) $sale_id;
				}
			}
		}
		if ( ! empty( $not_in ) ) {
			$args['post__not_in'] = array_values( array_unique( $not_in ) );
		}
		return apply_filters( 'alg_wc_pxf_query_args', $args, $config, $feed_id );
	}

	/**
	 * query_ids: next page after the cursor (keyset paging on post_date, ID; no OFFSET).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int   $feed_id Feed id.
	 * @param   array $config  Config.
	 * @param   array $cursor  last_date, last_id.
	 * @param   int   $size    Page size.
	 * @return  int[]
	 */
	static function query_ids( $feed_id, array $config, array $cursor, $size ) {
		$args                   = self::query_args( $feed_id, $config );
		$args['posts_per_page'] = (int) $size;
		$args['no_found_rows']  = true;
		$args['alg_pxf_cursor'] = $cursor;
		add_filter( 'posts_where', array( __CLASS__, 'keyset_where' ), 10, 2 );
		$query = new WP_Query( $args );
		remove_filter( 'posts_where', array( __CLASS__, 'keyset_where' ), 10 );
		return array_map( 'intval', $query->posts );
	}

	/**
	 * keyset_where.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string   $where Where.
	 * @param   WP_Query $query Query.
	 * @return  string
	 */
	static function keyset_where( $where, $query ) {
		$cursor = $query->get( 'alg_pxf_cursor' );
		if ( is_array( $cursor ) && ! empty( $cursor['last_date'] ) ) {
			global $wpdb;
			$where .= $wpdb->prepare(
				" AND ( {$wpdb->posts}.post_date > %s OR ( {$wpdb->posts}.post_date = %s AND {$wpdb->posts}.ID > %d ) )",
				$cursor['last_date'],
				$cursor['last_date'],
				(int) $cursor['last_id']
			);
		}
		return $where;
	}

	/**
	 * count_products: parents that match the filters (progress total).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int   $feed_id Feed id.
	 * @param   array $config  Config.
	 * @return  int
	 */
	static function count_products( $feed_id, array $config ) {
		$args                   = self::query_args( $feed_id, $config );
		$args['posts_per_page'] = 1;
		$args['no_found_rows']  = false;
		$args['orderby']        = 'ID';
		$query                  = new WP_Query( $args );
		return (int) $query->found_posts;
	}

	/**
	 * fail: ends the run, keeps the live file, removes the temporary files.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int    $feed_id Feed id.
	 * @param   string $run_id  Run id.
	 * @param   string $message Plain text.
	 */
	static function fail( $feed_id, $run_id, $message ) {
		$state = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
		if ( $state['run_id'] !== $run_id ) {
			return;
		}
		Alg_WC_PXF_Writer::discard( Alg_WC_PXF_Feeds::file_path( $feed_id ) );
		$state['status']   = 'failed';
		$state['error']    = wp_strip_all_tags( (string) $message );
		$state['finished'] = time();
		Alg_WC_PXF_Feeds::save_state( $feed_id, $state );
		self::lock_release( self::lock_name( $feed_id ) );
		self::unschedule( $feed_id, $run_id );
		do_action( 'alg_wc_pxf_run_failed', $feed_id, $state );
	}

	/**
	 * shutdown_guard: a batch that died (fatal error, memory) frees its lock, halves the batch size and queues a retry.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   stdClass $guard   Guard (active while the batch runs).
	 * @param   int      $feed_id Feed id.
	 * @param   string   $run_id  Run id.
	 */
	static function shutdown_guard( $guard, $feed_id, $run_id ) {
		if ( empty( $guard->active ) ) {
			return;
		}
		$guard->active = false;
		self::lock_release( self::batch_lock_name( $feed_id ) );
		$state = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
		if ( ! self::is_current( $state, $run_id ) ) {
			return;
		}
		$error               = error_get_last();
		$state['batch_size'] = max( self::BATCH_MIN, (int) floor( max( self::BATCH_MIN, (int) $state['batch_size'] ) / 2 ) );
		$state['error']      = $error && ! empty( $error['message'] ) ? wp_strip_all_tags( $error['message'] ) : __( 'A batch stopped before it finished.', 'product-xml-feeds-for-woocommerce' );
		Alg_WC_PXF_Feeds::save_state( $feed_id, $state );
		self::enqueue( $feed_id, $run_id );
	}

	/**
	 * forget: a deleted feed leaves no queued batches or locks behind.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 */
	static function forget( $feed_id ) {
		$state = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
		if ( ! empty( $state['run_id'] ) ) {
			self::unschedule( $feed_id, $state['run_id'] );
		}
		self::lock_release( self::lock_name( $feed_id ) );
		self::lock_release( self::batch_lock_name( $feed_id ) );
	}

	/**
	 * is_current.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array  $state  State.
	 * @param   string $run_id Run id.
	 * @return  bool
	 */
	static function is_current( array $state, $run_id ) {
		return '' !== (string) $run_id && (string) $state['run_id'] === (string) $run_id && in_array( $state['status'], self::ACTIVE, true );
	}

	/**
	 * enqueue: next batch through Action Scheduler (once per run), WP-Cron when it is missing.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int    $feed_id Feed id.
	 * @param   string $run_id  Run id.
	 */
	static function enqueue( $feed_id, $run_id ) {
		$args = array( (int) $feed_id, (string) $run_id );
		if ( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_get_scheduled_actions' ) && class_exists( 'ActionScheduler_Store' ) ) {
			$pending = as_get_scheduled_actions( array(
				'hook'     => self::HOOK,
				'args'     => $args,
				'group'    => self::GROUP,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 1,
			), 'ids' );
			if ( empty( $pending ) ) {
				as_enqueue_async_action( self::HOOK, $args, self::GROUP );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK, $args ) ) {
			wp_schedule_single_event( time(), self::HOOK, $args );
		}
	}

	/**
	 * unschedule: drops the pending batches of a run.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int    $feed_id Feed id.
	 * @param   string $run_id  Run id.
	 */
	static function unschedule( $feed_id, $run_id ) {
		$args = array( (int) $feed_id, (string) $run_id );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, $args, self::GROUP );
		}
		wp_clear_scheduled_hook( self::HOOK, $args );
	}

	/**
	 * time_budget: seconds per batch, from max_execution_time and the Action Scheduler time limit.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  float
	 */
	static function time_budget() {
		$as_limit = (int) apply_filters( 'action_scheduler_queue_runner_time_limit', 30 );
		$budget   = min( 20, max( 5, $as_limit ) * 0.5 );
		$max      = (int) ini_get( 'max_execution_time' );
		if ( $max > 0 ) {
			$budget = min( $budget, $max * 0.5 );
		}
		return max( 2, (float) apply_filters( 'alg_wc_pxf_batch_time_budget', $budget ) );
	}

	/**
	 * memory_limit: bytes, 0 = no limit.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  int
	 */
	static function memory_limit() {
		$limit = ini_get( 'memory_limit' );
		if ( false === $limit || '' === $limit || '-1' === trim( $limit ) ) {
			return 0;
		}
		return (int) wp_convert_hr_to_bytes( $limit );
	}

	/**
	 * lock_name.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @return  string
	 */
	static function lock_name( $feed_id ) {
		return 'alg_pxf_lock_' . absint( $feed_id );
	}

	/**
	 * batch_lock_name.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @return  string
	 */
	static function batch_lock_name( $feed_id ) {
		return 'alg_pxf_batch_lock_' . absint( $feed_id );
	}

	/**
	 * is_locked: a run lock that has not expired.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @return  bool
	 */
	static function is_locked( $feed_id ) {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::lock_name( $feed_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return null !== $value && (int) $value > time();
	}

	/**
	 * lock_acquire: atomic (INSERT IGNORE, then compare-and-swap on an expired value). Value "expiry|token".
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $name  Option name.
	 * @param   int    $ttl   Seconds.
	 * @param   bool   $force Take it even when it has not expired.
	 * @return  bool
	 */
	static function lock_acquire( $name, $ttl, $force = false ) {
		global $wpdb;
		$value = ( time() + (int) $ttl ) . '|' . wp_generate_password( 12, false );
		for ( $i = 0; $i < 2; $i++ ) {
			$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )", $name, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $inserted ) {
				wp_cache_delete( $name, 'options' );
				return true;
			}
			$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( null === $current ) {
				continue; // Released in between: try the insert again.
			}
			if ( ! $force && (int) $current > time() ) {
				return false;
			}
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $name, $current ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_cache_delete( $name, 'options' );
			return (bool) $updated;
		}
		return false;
	}

	/**
	 * lock_release.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $name Option name.
	 */
	static function lock_release( $name ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( $name, 'options' );
	}

}

endif;

<?php
/**
 * Product XML Feeds for WooCommerce - v2 AI jobs.
 *
 * Jobs: titles (10 products per call), descriptions (5), attributes (10, optionally with the main photo), categories
 * (10 WooCommerce categories per call, each with a local shortlist of Google categories). Each call is an Anthropic
 * Messages body with one tool and a forced tool_choice, so the answer is JSON. Answers are checked (length, promo
 * words, prices, HTML, language kept) and saved per product as feed-only suggestions; nothing reaches a feed until it
 * is approved. Failed batches are refunded. The filter `alg_wc_pxf_ai_complete` answers instead of WPFactory AI (tests,
 * own-key setups).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_AI' ) ) :

class Alg_WC_PXF_AI {

	/**
	 * Product id on the WPFactory AI server.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const PRODUCT = 'product-feeds';

	/**
	 * Model: fast and cheap.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const MODEL = 'claude-haiku-5-5';

	/**
	 * Action Scheduler hook for one batch of a run.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const HOOK = 'alg_wc_pxf_ai_batch';

	/**
	 * Option with the current (or last) run and its progress.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const OPTION_RUN = 'alg_pxf_ai_run';

	/**
	 * Post meta keys of the feed-only values (the status is the key plus `_status`).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const META = array(
		'titles'       => '_alg_pxf_ai_title',
		'descriptions' => '_alg_pxf_ai_description',
		'attributes'   => '_alg_pxf_ai_attrs',
		'categories'   => '_alg_pxf_ai_google_category',
	);

	/**
	 * Term meta of the confirmed Google category (shared with the category map).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const TERM_CATEGORY = '_alg_pxf_google_category';

	/**
	 * Attributes AI may fill.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const ATTRIBUTES = array( 'color', 'material', 'pattern', 'gender', 'age_group', 'size', 'brand' );

	/**
	 * Request ids charged in the current batch (refunded when the batch fails).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @var array
	 */
	protected static $last = array();

	/**
	 * init.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function init() {
		add_action( 'init', array( __CLASS__, 'register_product' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( self::HOOK, array( __CLASS__, 'batch' ) );
	}

	/**
	 * The WPFactory AI library, when bundled and loaded.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  object|null
	 */
	static function library() {
		return function_exists( 'wpfactory_ai' ) ? wpfactory_ai() : null;
	}

	/**
	 * Registers Product Feeds with the library (shows on the AI Credits screen).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function register_product() {
		$ai = self::library();
		if ( $ai ) {
			$ai->register_product( self::PRODUCT, array( 'name' => __( 'Product Feeds', 'product-xml-feeds-for-woocommerce' ) ) );
		}
	}

	/**
	 * Whether AI can answer: the site is connected to WPFactory AI, or another service answers through the filter.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  bool
	 */
	static function is_ready() {
		$ready = has_filter( 'alg_wc_pxf_ai_complete' ) || ( self::library() && self::library()->is_connected() );
		/**
		 * Whether AI is ready to answer (own-key setups say true).
		 *
		 * @param bool $ready Ready.
		 */
		return (bool) apply_filters( 'alg_wc_pxf_ai_ready', $ready );
	}

	/**
	 * The AI Credits screen (connect, balance, buy).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	static function screen_url() {
		return self::library() ? self::library()->admin_url() : admin_url();
	}

	/**
	 * Jobs: batch size, credits per item (estimate), most output tokens, plain names.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function jobs() {
		$jobs = array(
			'titles'       => array(
				'size'       => 10,
				'rate'       => 0.1,
				'max_tokens' => 1000,
				'tool'       => 'save_titles',
				'task'       => 'feed-titles',
				'label'      => __( 'Feed titles', 'product-xml-feeds-for-woocommerce' ),
			),
			'descriptions' => array(
				'size'       => 5,
				'rate'       => 0.2,
				'max_tokens' => 2000,
				'tool'       => 'save_descriptions',
				'task'       => 'feed-descriptions',
				'label'      => __( 'Feed descriptions', 'product-xml-feeds-for-woocommerce' ),
			),
			'attributes'   => array(
				'size'       => 10,
				'rate'       => 0.1,
				'rate_photo' => 0.3,
				'max_tokens' => 1000,
				'tool'       => 'save_attributes',
				'task'       => 'feed-attributes',
				'label'      => __( 'Missing attributes', 'product-xml-feeds-for-woocommerce' ),
			),
			'categories'   => array(
				'size'       => 10,
				'rate'       => 0.25,
				'max_tokens' => 600,
				'tool'       => 'save_categories',
				'task'       => 'feed-categories',
				'label'      => __( 'Google categories', 'product-xml-feeds-for-woocommerce' ),
			),
		);
		/**
		 * AI jobs (batch size, credits per item, most output tokens).
		 *
		 * @param array $jobs Jobs.
		 */
		return (array) apply_filters( 'alg_wc_pxf_ai_jobs', $jobs );
	}

	/**
	 * One job, or null.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job Job id.
	 * @return  array|null
	 */
	static function job( $job ) {
		$jobs = self::jobs();
		return isset( $jobs[ $job ] ) ? $jobs[ $job ] : null;
	}

	/* ------------------------------------------------------------------ estimate */

	/**
	 * Credits a run would cost, before it starts.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string    $job   Job.
	 * @param   int|array $items Count, or ids.
	 * @param   bool      $photo Attributes: also look at the main photo.
	 * @return  array|WP_Error job, items, batches, credits, balance (null when unknown), connected, enough (null when unknown), message.
	 */
	static function estimate( $job, $items, $photo = false ) {
		$spec = self::job( $job );
		if ( ! $spec ) {
			return new WP_Error( 'alg_pxf_ai_job', __( 'Unknown AI job.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 400 ) );
		}
		$count   = is_array( $items ) ? count( array_unique( array_map( 'absint', $items ) ) ) : max( 0, (int) $items );
		$rate    = ( 'attributes' === $job && $photo && isset( $spec['rate_photo'] ) ) ? (float) $spec['rate_photo'] : (float) $spec['rate'];
		$size    = max( 1, (int) $spec['size'] );
		$batches = (int) ceil( $count / $size );
		$credits = 0;
		for ( $b = 0; $b < $batches; $b++ ) {
			$in       = min( $size, $count - $b * $size );
			$credits += max( 1, (int) ceil( round( $in * $rate, 4 ) ) );
		}
		$connected = self::library() && self::library()->is_connected();
		$balance   = null;
		if ( $connected ) {
			$b       = self::library()->balance( self::PRODUCT );
			$balance = ( is_array( $b ) && isset( $b['balance'] ) ) ? (int) $b['balance'] : null;
		}
		$enough = null === $balance ? null : $balance >= $credits;
		$what   = array(
			/* translators: %s: number of products. */
			'titles'       => _n( 'Improve %s feed title', 'Improve %s feed titles', $count, 'product-xml-feeds-for-woocommerce' ),
			/* translators: %s: number of products. */
			'descriptions' => _n( 'Write %s feed description', 'Write %s feed descriptions', $count, 'product-xml-feeds-for-woocommerce' ),
			/* translators: %s: number of products. */
			'attributes'   => _n( 'Fill missing attributes for %s product', 'Fill missing attributes for %s products', $count, 'product-xml-feeds-for-woocommerce' ),
			/* translators: %s: number of categories. */
			'categories'   => _n( 'Suggest a Google category for %s category', 'Suggest Google categories for %s categories', $count, 'product-xml-feeds-for-woocommerce' ),
		);
		$message = sprintf( isset( $what[ $job ] ) ? $what[ $job ] : '%s', number_format_i18n( $count ) );
		/* translators: %s: number of credits. */
		$message .= ': ' . sprintf( _n( 'about %s credit.', 'about %s credits.', $credits, 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $credits ) );
		if ( ! self::is_ready() ) {
			$message .= ' ' . __( 'Turn on AI first: connect this site on the AI Credits screen.', 'product-xml-feeds-for-woocommerce' );
		} elseif ( null !== $balance ) {
			/* translators: %s: number of credits. */
			$message .= ' ' . sprintf( _n( 'You have %s credit.', 'You have %s credits.', $balance, 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $balance ) );
			if ( ! $enough ) {
				$message .= ' ' . __( 'That is not enough: buy credits or run fewer.', 'product-xml-feeds-for-woocommerce' );
			}
		}
		return array(
			'job'       => $job,
			'items'     => $count,
			'batches'   => $batches,
			'credits'   => $credits,
			'balance'   => $balance,
			'connected' => (bool) $connected,
			'ready'     => self::is_ready(),
			'enough'    => $enough,
			'message'   => $message,
			'screen'    => self::screen_url(),
			'buy'       => self::library() ? self::library()->buy_url() : '',
		);
	}

	/* ------------------------------------------------------------------ runs */

	/**
	 * Starts a run. One batch or less runs at once; more run in the background (Action Scheduler).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job  Job.
	 * @param   array  $ids  Product ids (categories: product_cat term ids).
	 * @param   array  $opts photo (bool), sync (bool: run every batch now).
	 * @return  array|WP_Error The run.
	 */
	static function run( $job, array $ids, array $opts = array() ) {
		$spec = self::job( $job );
		if ( ! $spec ) {
			return new WP_Error( 'alg_pxf_ai_job', __( 'Unknown AI job.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 400 ) );
		}
		$ids = self::valid_ids( $job, $ids );
		if ( ! $ids ) {
			return new WP_Error( 'alg_pxf_ai_nothing', __( 'Choose at least one product first.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 400 ) );
		}
		if ( ! self::is_ready() ) {
			return new WP_Error( 'alg_pxf_ai_not_connected', __( 'Turn on AI first: connect this site on the AI Credits screen.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 400, 'link' => self::screen_url() ) );
		}
		if ( 'categories' === $job && ! self::taxonomy_available() ) {
			return new WP_Error( 'alg_pxf_ai_no_taxonomy', __( 'The Google category list is missing from this copy of the plugin, so AI cannot suggest categories.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 400 ) );
		}
		$current = self::progress();
		if ( $current && in_array( $current['status'], array( 'queued', 'running' ), true ) && ( time() - (int) $current['updated'] ) < 15 * MINUTE_IN_SECONDS ) {
			return new WP_Error( 'alg_pxf_ai_busy', __( 'An AI run is already going. Wait for it to finish, or stop it first.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 409 ) );
		}
		$run = array(
			'id'        => wp_generate_uuid4(),
			'job'       => $job,
			'photo'     => ! empty( $opts['photo'] ),
			'queue'     => $ids,
			'total'     => count( $ids ),
			'done'      => 0,
			'suggested' => 0,
			'dropped'   => array(),
			'failed'    => array(),
			'credits'   => 0,
			'refunded'  => 0,
			'status'    => 'queued',
			'error'     => '',
			'started'   => time(),
			'updated'   => time(),
			'finished'  => 0,
			'user'      => get_current_user_id(),
		);
		self::save_run( $run );
		$sync = ! empty( $opts['sync'] ) || count( $ids ) <= (int) $spec['size'] || ! function_exists( 'as_enqueue_async_action' );
		if ( $sync ) {
			$guard = 0;
			while ( $guard++ < 1000 ) {
				$run = self::batch( $run['id'], false );
				if ( ! $run || ! in_array( $run['status'], array( 'queued', 'running' ), true ) ) {
					break;
				}
			}
			return self::public_run( self::progress() );
		}
		as_enqueue_async_action( self::HOOK, array( $run['id'] ), 'alg-pxf' );
		return self::public_run( $run );
	}

	/**
	 * Runs the next batch of a run (Action Scheduler hook), then queues the one after.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $run_id  Run id.
	 * @param   bool   $enqueue Queue the next batch in the background.
	 * @return  array|null The run.
	 */
	static function batch( $run_id, $enqueue = true ) {
		$run = self::progress();
		if ( ! $run || $run['id'] !== (string) $run_id || ! in_array( $run['status'], array( 'queued', 'running' ), true ) ) {
			return $run; // Stale, stopped or finished.
		}
		$spec  = self::job( $run['job'] );
		$chunk = array_splice( $run['queue'], 0, (int) $spec['size'] );
		$user  = get_current_user_id();
		if ( ! $user && ! empty( $run['user'] ) ) {
			wp_set_current_user( (int) $run['user'] ); // Background: answer as the admin who started it (balance, refunds).
		}
		$run['status'] = 'running';
		$result        = self::process( $run['job'], $chunk, ! empty( $run['photo'] ) );
		if ( ! $user ) {
			wp_set_current_user( 0 );
		}
		$fresh = self::progress(); // Stopped meanwhile?
		if ( $fresh && $fresh['id'] === $run['id'] && 'stopped' === $fresh['status'] ) {
			$run['status'] = 'stopped';
		}
		$run['done']     += count( $chunk );
		$run['updated']   = time();
		$run['credits']  += (int) $result['credits'];
		$run['refunded'] += (int) $result['refunded'];
		$run['suggested'] += (int) $result['suggested'];
		foreach ( $result['dropped'] as $id => $why ) {
			if ( count( $run['dropped'] ) < 500 ) {
				$run['dropped'][ $id ] = $why;
			}
		}
		if ( is_wp_error( $result['error'] ) ) {
			foreach ( $chunk as $id ) {
				$run['failed'][] = (int) $id;
			}
			$run['error'] = $result['error']->get_error_message();
			if ( in_array( $result['error']->get_error_code(), array( 'wpfactory_ai_not_connected', 'wpfactory_ai_insufficient_credits', 'alg_pxf_ai_low_credits', 'alg_pxf_ai_not_connected', 'alg_pxf_ai_missing' ), true ) ) {
				// No point going on: keep the rest for Retry.
				$run['failed'] = array_merge( $run['failed'], array_map( 'intval', $run['queue'] ) );
				$run['queue']  = array();
				$run['status'] = 'failed';
			}
		}
		if ( 'running' === $run['status'] && ! $run['queue'] ) {
			$run['status'] = 'done';
		}
		if ( ! in_array( $run['status'], array( 'queued', 'running' ), true ) ) {
			$run['finished'] = time();
		}
		self::save_run( $run );
		if ( $enqueue && 'running' === $run['status'] && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array( $run['id'] ), 'alg-pxf' );
		}
		return $run;
	}

	/**
	 * Stops the current run after its running batch.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array|null
	 */
	static function stop() {
		$run = self::progress();
		if ( $run && in_array( $run['status'], array( 'queued', 'running' ), true ) ) {
			$run['status']   = 'stopped';
			$run['finished'] = time();
			self::save_run( $run );
		}
		return self::public_run( $run );
	}

	/**
	 * The current (or last) run.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array|null
	 */
	static function progress() {
		$run = get_option( self::OPTION_RUN, null );
		return is_array( $run ) && ! empty( $run['id'] ) ? $run : null;
	}

	/**
	 * A run for the admin: counts, percent, plain status line.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array|null $run Run.
	 * @return  array|null
	 */
	static function public_run( $run ) {
		if ( ! $run ) {
			return null;
		}
		$run['percent'] = $run['total'] ? (int) floor( 100 * $run['done'] / $run['total'] ) : 0;
		$run['left']    = count( $run['queue'] );
		unset( $run['queue'], $run['user'] );
		$statuses = array(
			'queued'  => __( 'Waiting to start', 'product-xml-feeds-for-woocommerce' ),
			/* translators: 1: done, 2: total. */
			'running' => sprintf( __( 'Working: %1$s of %2$s done', 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $run['done'] ), number_format_i18n( $run['total'] ) ),
			/* translators: %s: number of suggestions. */
			'done'    => sprintf( _n( 'Finished: %s suggestion to review', 'Finished: %s suggestions to review', $run['suggested'], 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $run['suggested'] ) ),
			'stopped' => __( 'Stopped', 'product-xml-feeds-for-woocommerce' ),
			'failed'  => __( 'Stopped because of an error', 'product-xml-feeds-for-woocommerce' ),
		);
		$run['status_text'] = isset( $statuses[ $run['status'] ] ) ? $statuses[ $run['status'] ] : $run['status'];
		return $run;
	}

	/**
	 * Saves the run option (not autoloaded).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $run Run.
	 */
	protected static function save_run( array $run ) {
		update_option( self::OPTION_RUN, $run, false );
	}

	/**
	 * Ids that exist for a job.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job Job.
	 * @param   array  $ids Ids.
	 * @return  int[]
	 */
	static function valid_ids( $job, array $ids ) {
		$out = array();
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
			if ( ! $id ) {
				continue;
			}
			if ( 'categories' === $job ) {
				$term = get_term( $id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$out[] = $id;
				}
			} elseif ( in_array( get_post_type( $id ), array( 'product', 'product_variation' ), true ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ one batch */

	/**
	 * Asks the AI for one batch, checks the answers and saves them as suggestions. A batch that gives nothing usable
	 * is refunded.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job   Job.
	 * @param   array  $ids   Ids (at most one batch).
	 * @param   bool   $photo Attributes: send the main photo.
	 * @return  array suggested (int), dropped (id => reason), credits, refunded, error (WP_Error|null), request_id.
	 */
	static function process( $job, array $ids, $photo = false ) {
		$out  = array(
			'suggested'  => 0,
			'dropped'    => array(),
			'credits'    => 0,
			'refunded'   => 0,
			'error'      => null,
			'request_id' => '',
		);
		$spec = self::job( $job );
		$data = array();
		foreach ( $ids as $id ) {
			$facts = 'categories' === $job ? self::category_facts( (int) $id ) : self::product_facts( (int) $id, $job );
			if ( $facts ) {
				$data[ (int) $id ] = $facts;
			}
		}
		if ( ! $spec || ! $data ) {
			$out['error'] = new WP_Error( 'alg_pxf_ai_nothing', __( 'None of these items could be read.', 'product-xml-feeds-for-woocommerce' ) );
			return $out;
		}
		$body = self::body( $job, $data, $photo );
		$r    = self::complete( $body, $spec['task'], count( $data ) );
		if ( is_wp_error( $r ) ) {
			$out['error'] = $r;
			return $out;
		}
		$out['credits']    = (int) $r['credits'];
		$out['request_id'] = (string) $r['request_id'];
		$answers           = self::tool_input( $r['message'], $spec['tool'] );
		if ( null === $answers ) {
			$out['error']    = new WP_Error( 'alg_pxf_ai_bad_answer', __( 'The AI sent an answer that could not be read. These products were not charged.', 'product-xml-feeds-for-woocommerce' ) );
			$out['refunded'] = self::refund( $out['request_id'], $out['credits'] );
			return $out;
		}
		$seen = array();
		foreach ( $answers as $answer ) {
			$id = isset( $answer['id'] ) ? absint( $answer['id'] ) : 0;
			if ( ! isset( $data[ $id ] ) || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$value       = self::check( $job, $answer, $data[ $id ] );
			if ( is_wp_error( $value ) ) {
				$out['dropped'][ $id ] = $value->get_error_message();
				continue;
			}
			self::save( $job, $id, $value, $out['request_id'] );
			++$out['suggested'];
		}
		foreach ( array_keys( $data ) as $id ) {
			if ( ! isset( $seen[ $id ] ) ) {
				$out['dropped'][ $id ] = __( 'AI could not improve this one.', 'product-xml-feeds-for-woocommerce' );
			}
		}
		foreach ( array_diff( array_map( 'intval', $ids ), array_keys( $data ) ) as $id ) {
			$out['dropped'][ $id ] = __( 'This item could not be read.', 'product-xml-feeds-for-woocommerce' );
		}
		if ( 0 === $out['suggested'] ) {
			$out['refunded'] = self::refund( $out['request_id'], $out['credits'] ); // Nothing usable: nothing charged.
		}
		return $out;
	}

	/**
	 * One AI call: the filter `alg_wc_pxf_ai_complete` first (tests, own key), else WPFactory AI credits.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array  $body  Anthropic Messages body.
	 * @param   string $task  Task (usage by task).
	 * @param   int    $items Items in the call (for the plain "not enough credits" message).
	 * @return  array|WP_Error message, request_id, credits, balance.
	 */
	static function complete( array $body, $task, $items = 0 ) {
		/**
		 * Answers an AI call instead of WPFactory AI (tests, own API key). Return null to use WPFactory AI credits,
		 * an Anthropic message array (with `content`), an array like the library's call() result (with `message`),
		 * or a WP_Error with a plain message.
		 *
		 * @param null|array|WP_Error $result Result.
		 * @param array               $body   Anthropic Messages body.
		 * @param string              $task   Task.
		 */
		$pre = apply_filters( 'alg_wc_pxf_ai_complete', null, $body, $task );
		if ( null !== $pre ) {
			if ( is_wp_error( $pre ) ) {
				return $pre;
			}
			$pre = (array) $pre;
			if ( isset( $pre['message'] ) && is_array( $pre['message'] ) ) {
				return array(
					'message'    => $pre['message'],
					'request_id' => isset( $pre['request_id'] ) ? (string) $pre['request_id'] : '',
					'credits'    => isset( $pre['credits'] ) ? (int) $pre['credits'] : 0,
					'balance'    => isset( $pre['balance'] ) ? (int) $pre['balance'] : null,
				);
			}
			return array(
				'message'    => $pre,
				'request_id' => '',
				'credits'    => 0,
				'balance'    => null,
			);
		}
		$ai = self::library();
		if ( ! $ai ) {
			return new WP_Error( 'alg_pxf_ai_missing', __( 'WPFactory AI is not available in this copy of the plugin.', 'product-xml-feeds-for-woocommerce' ) );
		}
		if ( ! $ai->is_connected() ) {
			return new WP_Error( 'alg_pxf_ai_not_connected', __( 'Turn on AI first: connect this site on the AI Credits screen.', 'product-xml-feeds-for-woocommerce' ), array( 'link' => self::screen_url() ) );
		}
		$r = $ai->call( self::PRODUCT, $body, array( 'task' => $task ) );
		if ( is_wp_error( $r ) ) {
			if ( 'wpfactory_ai_insufficient_credits' === $r->get_error_code() ) {
				$data = (array) $r->get_error_data();
				return new WP_Error(
					'alg_pxf_ai_low_credits',
					/* translators: 1: number of products, 2: credits left. */
					sprintf( _n( 'Not enough credits for the next %1$s product (%2$s left): buy credits or run fewer.', 'Not enough credits for the next %1$s products (%2$s left): buy credits or run fewer.', max( 1, (int) $items ), 'product-xml-feeds-for-woocommerce' ), number_format_i18n( max( 1, (int) $items ) ), number_format_i18n( (int) ( $data['balance'] ?? 0 ) ) ),
					array( 'link' => $ai->buy_url() ) + $data
				);
			}
			return $r; // Plain, ready-to-show message from the library. Nothing was charged.
		}
		return $r;
	}

	/**
	 * Gives back the credits of a failed batch (once, within 30 minutes).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $request_id Request id.
	 * @param   int    $credits    Credits charged.
	 * @return  int Credits refunded.
	 */
	static function refund( $request_id, $credits ) {
		if ( '' === (string) $request_id || $credits <= 0 || ! self::library() || ! self::library()->is_connected() ) {
			return 0;
		}
		$r = self::library()->refund( $request_id );
		return is_wp_error( $r ) ? 0 : (int) $credits;
	}

	/**
	 * The tool input list of an Anthropic message: `items` of the forced tool, or null.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array  $message Message.
	 * @param   string $tool    Tool name.
	 * @return  array|null
	 */
	static function tool_input( array $message, $tool ) {
		if ( isset( $message['stop_reason'] ) && 'refusal' === $message['stop_reason'] ) {
			return null;
		}
		foreach ( (array) ( $message['content'] ?? array() ) as $block ) {
			if ( is_array( $block ) && 'tool_use' === ( $block['type'] ?? '' ) && $tool === ( $block['name'] ?? '' ) ) {
				$input = $block['input'] ?? null;
				if ( is_string( $input ) ) {
					$input = json_decode( $input, true );
				}
				if ( is_array( $input ) && isset( $input['items'] ) && is_array( $input['items'] ) ) {
					return array_values( array_filter( $input['items'], 'is_array' ) );
				}
			}
		}
		return null;
	}

	/* ------------------------------------------------------------------ prompts */

	/**
	 * The Anthropic Messages body for a batch.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job   Job.
	 * @param   array  $data  id => facts.
	 * @param   bool   $photo Attributes: send the main photos.
	 * @return  array
	 */
	static function body( $job, array $data, $photo = false ) {
		$spec  = self::job( $job );
		$lang  = self::language();
		$store = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$facts = 'Only use facts in the data given. Never invent materials, sizes, certifications, claims, numbers, prices, shipping or return promises. Keep brand and model names exactly as written.';
		$items = array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'id' ),
			),
		);
		switch ( $job ) {
			case 'titles':
				$system = 'You write product titles for shopping feeds (Google Shopping and similar) for the online store "' . $store . '". ' . $facts
					. ' Pattern by product type: apparel: Brand + Gender + Product type + Material + Color + Size; electronics: Brand + Model + Product type + Key spec; other products: Brand + Product type + Key attribute. Leave out parts the data does not give.'
					. ' Put the most important words in the first 70 characters. At most 150 characters.'
					. ' Never use promotional words (sale, free shipping, best, cheap, discount, deal, percentages off), prices, the store name, emoji, HTML, exclamation marks or all capital letters.'
					. ' Write in ' . $lang . ' (the language of the product data). Answer with the ' . $spec['tool'] . ' tool, one title per product id.';
				$items['items']['properties']['title'] = array( 'type' => 'string', 'description' => 'The feed title, at most 150 characters.' );
				$items['items']['required'][]          = 'title';
				$desc                                  = 'Saves one feed title per product.';
				break;
			case 'descriptions':
				$system = 'You write product descriptions for shopping feeds (Google Shopping and similar) for the online store "' . $store . '". ' . $facts
					. ' 500 to 1,000 characters of plain text: what the product is, what it is made of and its key features, from the data. No HTML, no markdown, no links, no prices, no promotional words, no store name, no emoji.'
					. ' Write in ' . $lang . ' (the language of the product data). Answer with the ' . $spec['tool'] . ' tool, one description per product id.';
				$items['items']['properties']['description'] = array( 'type' => 'string', 'description' => 'Plain text, 500 to 1000 characters.' );
				$items['items']['required'][]                = 'description';
				$desc                                        = 'Saves one feed description per product.';
				break;
			case 'attributes':
				$system = 'You fill missing product attributes for shopping feeds. ' . $facts
					. ' Give a value only when the product data states it' . ( $photo ? ' or the photo clearly shows it (color, pattern)' : '' ) . '; leave the field out otherwise. Never guess a brand.'
					. ' gender is one of: male, female, unisex. age_group is one of: newborn, infant, toddler, kids, adult. Short values (a few words), in ' . $lang . ' except gender and age_group.'
					. ' Answer with the ' . $spec['tool'] . ' tool, one object per product id.';
				foreach ( self::ATTRIBUTES as $attr ) {
					$items['items']['properties'][ $attr ] = array( 'type' => 'string' );
				}
				$items['items']['properties']['gender']['enum']    = array( 'male', 'female', 'unisex' );
				$items['items']['properties']['age_group']['enum'] = array( 'newborn', 'infant', 'toddler', 'kids', 'adult' );
				$desc = 'Saves the attributes found per product.';
				break;
			default: // categories
				$system = 'You map WooCommerce product categories to the Google product taxonomy. For each category, pick the best Google category from its numbered candidates, judging by the category path and its sample products.'
					. ' Answer with the candidate id and a confidence from 0 to 1. If no candidate fits, answer 0 with confidence 0. Answer with the ' . $spec['tool'] . ' tool, one object per category id.';
				$items['items']['properties']['google_category_id'] = array( 'type' => 'integer' );
				$items['items']['properties']['confidence']         = array( 'type' => 'number' );
				$items['items']['required']                         = array( 'id', 'google_category_id', 'confidence' );
				$desc = 'Saves one Google category per WooCommerce category.';
		}
		$send = array();
		foreach ( $data as $one ) {
			$send[] = array_diff_key( $one, array( '_ids' => true ) ); // Local only.
		}
		$text    = ( 'categories' === $job ? 'Categories' : 'Products' ) . " (JSON):\n" . wp_json_encode( $send, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$content = $text;
		if ( 'attributes' === $job && $photo ) {
			$content = array();
			foreach ( $data as $id => $facts_one ) {
				$image = self::photo( $id );
				if ( $image ) {
					$content[] = array(
						'type' => 'text',
						'text' => 'Main photo of product ' . $id . ':',
					);
					$content[] = array(
						'type'   => 'image',
						'source' => array(
							'type'       => 'base64',
							'media_type' => 'image/jpeg',
							'data'       => $image,
						),
					);
				}
			}
			$content[] = array(
				'type' => 'text',
				'text' => $text,
			);
		}
		$body = array(
			'model'       => self::MODEL,
			'max_tokens'  => (int) $spec['max_tokens'],
			'system'      => $system,
			'tools'       => array(
				array(
					'name'         => $spec['tool'],
					'description'  => $desc,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array( 'items' => $items ),
						'required'   => array( 'items' ),
					),
				),
			),
			'tool_choice' => array(
				'type' => 'tool',
				'name' => $spec['tool'],
			),
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $content,
				),
			),
		);
		/**
		 * The Anthropic Messages body of an AI batch.
		 *
		 * @param array  $body Body.
		 * @param string $job  Job.
		 * @param array  $data id => facts.
		 */
		return (array) apply_filters( 'alg_wc_pxf_ai_body', $body, $job, $data );
	}

	/**
	 * Language name for prompts (the feed's or the store's).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	static function language() {
		$locale = get_locale();
		if ( function_exists( 'locale_get_display_language' ) ) {
			$name = locale_get_display_language( $locale, 'en' );
			if ( $name && $name !== $locale ) {
				return $name;
			}
		}
		return 'the language of the locale ' . $locale;
	}

	/**
	 * Product facts sent to the AI (no customer or order data).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int    $id  Product id.
	 * @param   string $job Job.
	 * @return  array|null
	 */
	static function product_facts( $id, $job ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( ! $product ) {
			return null;
		}
		$limits = array(
			'titles'       => 600,
			'descriptions' => 2500,
			'attributes'   => 1200,
		);
		$limit  = isset( $limits[ $job ] ) ? $limits[ $job ] : 800;
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : null;
		$base   = $parent ? $parent : $product;
		$facts  = array(
			'id'   => (int) $id,
			'name' => self::plain( $product->get_name(), 300 ),
		);
		$brand = self::brand( $base );
		if ( '' !== $brand ) {
			$facts['brand'] = $brand;
		}
		$cats = array();
		foreach ( (array) $base->get_category_ids() as $cat_id ) {
			$path = self::category_path( (int) $cat_id );
			if ( '' !== $path ) {
				$cats[] = $path;
			}
		}
		if ( $cats ) {
			$facts['categories'] = array_slice( $cats, 0, 5 );
		}
		$attrs = self::attributes( $product );
		if ( $parent ) {
			$attrs = array_merge( self::attributes( $parent ), $attrs );
		}
		if ( $attrs ) {
			$facts['attributes'] = $attrs;
		}
		$short = self::plain( $base->get_short_description(), 'titles' === $job ? 300 : 600 );
		if ( '' !== $short ) {
			$facts['short_description'] = $short;
		}
		$long = self::plain( $product->get_description() ? $product->get_description() : $base->get_description(), $limit );
		if ( '' !== $long ) {
			$facts['description'] = $long;
		}
		$facts['has_gtin'] = '' !== (string) ( method_exists( $product, 'get_global_unique_id' ) ? $product->get_global_unique_id() : '' ) || '' !== (string) get_post_meta( $id, '_alg_ean', true );
		if ( 'attributes' === $job ) {
			$current = self::current_attributes( $product );
			if ( $current ) {
				$facts['already_set'] = $current;
			}
		}
		/**
		 * Product facts sent to the AI.
		 *
		 * @param array      $facts   Facts.
		 * @param WC_Product $product Product.
		 * @param string     $job     Job.
		 */
		return (array) apply_filters( 'alg_wc_pxf_ai_product_facts', $facts, $product, $job );
	}

	/**
	 * Category facts with the Google shortlist.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $term_id product_cat term id.
	 * @return  array|null
	 */
	static function category_facts( $term_id ) {
		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		$path     = self::category_path( $term_id );
		$samples  = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		$names    = array_map(
			function ( $pid ) {
				return self::plain( get_the_title( $pid ), 100 );
			},
			$samples
		);
		$query    = trim( $path . ' ' . implode( ' ', $names ) );
		$shortlist = self::shortlist( $term->name . ' ' . $path, $query, 40 );
		$cands     = array();
		foreach ( $shortlist as $gid => $gpath ) {
			$cands[] = $gid . ': ' . $gpath;
		}
		return array(
			'id'              => (int) $term_id,
			'category'        => $path,
			'sample_products' => $names,
			'candidates'      => $cands,
			'_ids'            => array_map( 'intval', array_keys( $shortlist ) ),
		);
	}

	/**
	 * Local shortlist of Google categories: id => path.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $name  Category name and path.
	 * @param   string $text  More words (sample products).
	 * @param   int    $limit Most candidates.
	 * @return  array
	 */
	static function shortlist( $name, $text, $limit = 40 ) {
		if ( class_exists( 'Alg_WC_PXF_Google_Taxonomy' ) && method_exists( 'Alg_WC_PXF_Google_Taxonomy', 'shortlist' ) ) {
			$list = (array) Alg_WC_PXF_Google_Taxonomy::shortlist( $name . ' ' . $text, $limit );
			$out  = array();
			foreach ( $list as $k => $v ) {
				if ( is_array( $v ) ) {
					$gid  = (int) ( $v['id'] ?? $k );
					$path = (string) ( $v['path'] ?? ( $v['name'] ?? '' ) );
				} else {
					$gid  = (int) $k;
					$path = (string) $v;
				}
				if ( $gid > 0 ) {
					$out[ $gid ] = $path;
				}
			}
			return $out;
		}
		$rows = self::taxonomy_rows();
		if ( ! $rows ) {
			return array();
		}
		$words = array_unique( array_filter( preg_split( '/[^\p{L}\p{N}]+/u', strtolower( $name . ' ' . $text ) ), function ( $w ) {
			return strlen( $w ) > 2 && ! in_array( $w, array( 'and', 'for', 'the', 'with', 'uncategorized' ), true );
		} ) );
		$name_words = array_filter( preg_split( '/[^\p{L}\p{N}]+/u', strtolower( $name ) ) );
		$scores     = array();
		foreach ( $rows as $gid => $path ) {
			$lower = strtolower( $path );
			$leaf  = strtolower( (string) substr( strrchr( ' > ' . $path, '>' ), 1 ) );
			$score = 0;
			foreach ( $words as $w ) {
				$stem = rtrim( $w, 's' );
				if ( '' !== $stem && false !== strpos( $lower, $stem ) ) {
					$score += in_array( $w, $name_words, true ) ? 3 : 1;
					if ( false !== strpos( $leaf, $stem ) ) {
						$score += 2;
					}
				}
			}
			if ( $score > 0 ) {
				$scores[ $gid ] = $score - substr_count( $path, '>' ) * 0.1;
			}
		}
		arsort( $scores );
		$out = array();
		foreach ( array_slice( array_keys( $scores ), 0, $limit ) as $gid ) {
			$out[ $gid ] = $rows[ $gid ];
		}
		return $out;
	}

	/**
	 * Whether a Google category list is available.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  bool
	 */
	static function taxonomy_available() {
		return ( class_exists( 'Alg_WC_PXF_Google_Taxonomy' ) && method_exists( 'Alg_WC_PXF_Google_Taxonomy', 'shortlist' ) ) || (bool) self::taxonomy_rows();
	}

	/**
	 * The bundled Google taxonomy file as id => path.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function taxonomy_rows() {
		static $rows = null;
		if ( null !== $rows ) {
			return $rows;
		}
		$rows = array();
		$file = dirname( __DIR__, 3 ) . '/assets/v2/data/google-product-taxonomy-en-US.txt';
		/**
		 * Path of the Google taxonomy file ("id - path" per line).
		 *
		 * @param string $file Path.
		 */
		$file = (string) apply_filters( 'alg_wc_pxf_ai_taxonomy_file', $file );
		if ( ! is_readable( $file ) ) {
			return $rows;
		}
		foreach ( (array) file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
			if ( preg_match( '/^(\d+)\s+-\s+(.+)$/', trim( $line ), $m ) ) {
				$rows[ (int) $m[1] ] = $m[2];
			}
		}
		return $rows;
	}

	/* ------------------------------------------------------------------ checks */

	/**
	 * Checks one answer. Returns the value to save, or WP_Error with the reason it was dropped.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job    Job.
	 * @param   array  $answer Answer object.
	 * @param   array  $facts  Facts sent.
	 * @return  string|array|WP_Error
	 */
	static function check( $job, array $answer, array $facts ) {
		$drop = function ( $why ) {
			return new WP_Error( 'alg_pxf_ai_dropped', $why );
		};
		$source = trim( ( $facts['name'] ?? '' ) . ' ' . ( $facts['short_description'] ?? '' ) . ' ' . ( $facts['description'] ?? '' ) );
		if ( 'titles' === $job || 'descriptions' === $job ) {
			$key  = 'titles' === $job ? 'title' : 'description';
			$text = isset( $answer[ $key ] ) && is_string( $answer[ $key ] ) ? trim( $answer[ $key ] ) : '';
			if ( '' === $text ) {
				return $drop( __( 'AI could not improve this one.', 'product-xml-feeds-for-woocommerce' ) );
			}
			if ( wp_strip_all_tags( $text ) !== $text || preg_match( '/<[a-z\/!]|&lt;|\[\/?[a-z_]+[^\]]*\]/i', $text ) ) {
				return $drop( __( 'The AI answer had HTML or code in it.', 'product-xml-feeds-for-woocommerce' ) );
			}
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
			$max = 'titles' === $job ? 150 : 5000;
			$min = 'titles' === $job ? 5 : 80;
			if ( $len > $max || $len < $min ) {
				return $drop( 'titles' === $job ? __( 'The AI title was too long or too short.', 'product-xml-feeds-for-woocommerce' ) : __( 'The AI description was too long or too short.', 'product-xml-feeds-for-woocommerce' ) );
			}
			if ( self::has_price( $text ) ) {
				return $drop( __( 'The AI text had a price in it.', 'product-xml-feeds-for-woocommerce' ) );
			}
			if ( self::has_emoji( $text ) ) {
				return $drop( __( 'The AI text had emoji in it.', 'product-xml-feeds-for-woocommerce' ) );
			}
			if ( preg_match( '#https?://|www\.#i', $text ) ) {
				return $drop( __( 'The AI text had a link in it.', 'product-xml-feeds-for-woocommerce' ) );
			}
			if ( 'titles' === $job ) {
				if ( Alg_WC_PXF_Validator_Bridge::promo_words( $text ) || false !== strpos( $text, '!' ) ) {
					return $drop( __( 'The AI title had promotional words in it.', 'product-xml-feeds-for-woocommerce' ) );
				}
				if ( Alg_WC_PXF_Validator_Bridge::is_all_caps( $text ) ) {
					return $drop( __( 'The AI title was all capital letters.', 'product-xml-feeds-for-woocommerce' ) );
				}
				$store = trim( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
				if ( strlen( $store ) >= 4 && false !== stripos( $text, $store ) && false === stripos( (string) ( $facts['name'] ?? '' ), $store ) && false === stripos( (string) ( $facts['brand'] ?? '' ), $store ) ) {
					return $drop( __( 'The AI title had the store name in it.', 'product-xml-feeds-for-woocommerce' ) );
				}
				if ( ! empty( $facts['brand'] ) && false !== stripos( $text, $facts['brand'] ) && false === strpos( $text, $facts['brand'] ) ) {
					return $drop( __( 'The AI title changed how the brand is written.', 'product-xml-feeds-for-woocommerce' ) );
				}
			} else {
				foreach ( Alg_WC_PXF_Validator_Bridge::promo_words( $text ) as $word ) {
					if ( preg_match( '/free|%|discount|clearance|buy now|offer|lowest|cheap|deal|bargain|save/i', $word ) ) {
						return $drop( __( 'The AI description had promotional words in it.', 'product-xml-feeds-for-woocommerce' ) );
					}
				}
			}
			if ( ! self::same_script( $source, $text ) ) {
				return $drop( __( 'The AI answer was not in the language of the product.', 'product-xml-feeds-for-woocommerce' ) );
			}
			return 'titles' === $job ? sanitize_text_field( $text ) : sanitize_textarea_field( $text );
		}
		if ( 'attributes' === $job ) {
			$out = array();
			foreach ( self::ATTRIBUTES as $attr ) {
				if ( ! isset( $answer[ $attr ] ) || ! is_scalar( $answer[ $attr ] ) ) {
					continue;
				}
				$value = trim( sanitize_text_field( (string) $answer[ $attr ] ) );
				if ( '' === $value || strlen( $value ) > 100 || self::has_price( $value ) ) {
					continue;
				}
				if ( 'gender' === $attr && ! in_array( strtolower( $value ), array( 'male', 'female', 'unisex' ), true ) ) {
					continue;
				}
				if ( 'age_group' === $attr && ! in_array( strtolower( $value ), array( 'newborn', 'infant', 'toddler', 'kids', 'adult' ), true ) ) {
					continue;
				}
				if ( 'brand' === $attr && false === stripos( $source . ' ' . wp_json_encode( $facts['attributes'] ?? array() ), $value ) ) {
					continue; // A brand must be in the data, never a guess.
				}
				$out[ $attr ] = in_array( $attr, array( 'gender', 'age_group' ), true ) ? strtolower( $value ) : $value;
			}
			return $out ? $out : $drop( __( 'AI found no attributes in the product data.', 'product-xml-feeds-for-woocommerce' ) );
		}
		// categories
		$gid  = isset( $answer['google_category_id'] ) ? absint( $answer['google_category_id'] ) : 0;
		$conf = isset( $answer['confidence'] ) ? (float) $answer['confidence'] : 0;
		if ( $gid <= 0 || ( ! empty( $facts['_ids'] ) && ! in_array( $gid, $facts['_ids'], true ) ) ) {
			return $drop( __( 'AI found no Google category that fits.', 'product-xml-feeds-for-woocommerce' ) );
		}
		return array(
			'id'         => $gid,
			'confidence' => max( 0, min( 1, round( $conf, 2 ) ) ),
		);
	}

	/**
	 * Whether a text has a price (a number with a currency sign or code).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @return  bool
	 */
	static function has_price( $text ) {
		$symbols = array( '$', "\xE2\x82\xAC", "\xC2\xA3", "\xC2\xA5", "\xE2\x82\xB9", "\xE2\x82\xBD", "\xE2\x82\xBA", 'kr', 'z\x{0142}' );
		if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
			$symbols[] = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
		}
		$codes = 'USD|EUR|GBP|JPY|AUD|CAD|CHF|SEK|NOK|DKK|PLN|CZK|HUF|RON|BGN|TRY|INR|BRL|MXN|ZAR|NZD|SGD|HKD|AED|SAR|JOD';
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$codes .= '|' . preg_quote( get_woocommerce_currency(), '/' );
		}
		$sym = implode( '|', array_map( function ( $s ) {
			return 'z\x{0142}' === $s ? $s : preg_quote( $s, '/' );
		}, array_filter( array_unique( $symbols ) ) ) );
		return (bool) preg_match( '/(?:' . $sym . ')\s?\d|\d(?:[.,]\d{1,2})?\s?(?:' . $sym . ')(?![\p{L}])|\b\d+(?:[.,]\d{1,2})?\s?(?:' . $codes . ')\b|\b(?:' . $codes . ')\s?\d/u', $text );
	}

	/**
	 * Whether a text has emoji.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @return  bool
	 */
	static function has_emoji( $text ) {
		return (bool) preg_match( '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B50}\x{2B55}\x{FE0F}]/u', $text );
	}

	/**
	 * Whether two texts are written in the same script (a simple language check: Latin, Cyrillic, Greek, Arabic,
	 * Hebrew, CJK, Thai...).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $source Source text.
	 * @param   string $text   AI text.
	 * @return  bool
	 */
	static function same_script( $source, $text ) {
		$a = self::script( $source );
		$b = self::script( $text );
		return '' === $a || '' === $b || $a === $b;
	}

	/**
	 * Main script of a text ('' when it has too few letters).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @return  string
	 */
	static function script( $text ) {
		$scripts = array( 'Latin', 'Cyrillic', 'Greek', 'Arabic', 'Hebrew', 'Han', 'Hiragana', 'Katakana', 'Hangul', 'Thai', 'Devanagari', 'Georgian', 'Armenian' );
		$best    = '';
		$most    = 0;
		$all     = 0;
		foreach ( $scripts as $s ) {
			$n    = (int) preg_match_all( '/\p{' . $s . '}/u', (string) $text );
			$all += $n;
			if ( $n > $most ) {
				$most = $n;
				$best = $s;
			}
		}
		if ( $all < 5 ) {
			return '';
		}
		return in_array( $best, array( 'Han', 'Hiragana', 'Katakana' ), true ) ? 'CJK' : $best;
	}

	/* ------------------------------------------------------------------ storage */

	/**
	 * Saves a suggestion.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string       $job        Job.
	 * @param   int          $id         Product id (categories: term id).
	 * @param   string|array $value      Value.
	 * @param   string       $request_id Request id.
	 * @param   string       $status     suggested|approved.
	 */
	static function save( $job, $id, $value, $request_id = '', $status = 'suggested' ) {
		$key = self::META[ $job ];
		if ( 'categories' === $job ) {
			update_term_meta( $id, $key, (int) $value['id'] );
			update_term_meta( $id, $key . '_confidence', (float) $value['confidence'] );
			update_term_meta( $id, $key . '_status', $status );
			update_term_meta( $id, $key . '_date', time() );
			return;
		}
		$store = 'attributes' === $job ? wp_json_encode( $value, JSON_UNESCAPED_UNICODE ) : $value;
		update_post_meta( $id, $key, wp_slash( $store ) );
		update_post_meta( $id, $key . '_status', $status );
		update_post_meta( $id, $key . '_request', (string) $request_id );
		update_post_meta( $id, $key . '_date', time() );
	}

	/**
	 * A stored AI value: value, status, date ('' / null when none).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job Job.
	 * @param   int    $id  Product id (categories: term id).
	 * @return  array|null value, status, date (and confidence for categories).
	 */
	static function get( $job, $id ) {
		if ( ! isset( self::META[ $job ] ) ) {
			return null;
		}
		$key = self::META[ $job ];
		if ( 'categories' === $job ) {
			$value = (int) get_term_meta( $id, $key, true );
			if ( ! $value ) {
				return null;
			}
			return array(
				'value'      => $value,
				'status'     => (string) get_term_meta( $id, $key . '_status', true ),
				'date'       => (int) get_term_meta( $id, $key . '_date', true ),
				'confidence' => (float) get_term_meta( $id, $key . '_confidence', true ),
			);
		}
		$value = get_post_meta( $id, $key, true );
		if ( '' === $value || null === $value ) {
			return null;
		}
		if ( 'attributes' === $job ) {
			$value = json_decode( (string) $value, true );
			$value = is_array( $value ) ? $value : array();
		}
		return array(
			'value'  => $value,
			'status' => (string) get_post_meta( $id, $key . '_status', true ),
			'date'   => (int) get_post_meta( $id, $key . '_date', true ),
		);
	}

	/**
	 * The approved AI value for a product field ('' or array() when none); variations fall back to their parent.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int    $product_id Product id.
	 * @param   string $field      title, description or attributes.
	 * @return  string|array
	 */
	static function approved( $product_id, $field ) {
		$jobs = array(
			'title'       => 'titles',
			'description' => 'descriptions',
			'attributes'  => 'attributes',
		);
		$job  = isset( $jobs[ $field ] ) ? $jobs[ $field ] : $field;
		$one  = self::get( $job, $product_id );
		if ( ( ! $one || 'approved' !== $one['status'] ) && 'product_variation' === get_post_type( $product_id ) ) {
			$one = self::get( $job, (int) wp_get_post_parent_id( $product_id ) );
		}
		if ( $one && 'approved' === $one['status'] ) {
			return $one['value'];
		}
		return 'attributes' === $job ? array() : '';
	}

	/**
	 * Approves suggestions (optionally with edited values).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job    Job.
	 * @param   array  $ids    Ids; empty with $all approves every suggestion of the job.
	 * @param   array  $values id => edited value (optional).
	 * @param   bool   $all    Approve all suggestions.
	 * @return  int Approved.
	 */
	static function approve( $job, array $ids, array $values = array(), $all = false ) {
		if ( ! isset( self::META[ $job ] ) ) {
			return 0;
		}
		if ( $all ) {
			$ids = self::ids_with( $job, 'suggested' );
		}
		$count = 0;
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
			if ( isset( $values[ $id ] ) ) {
				$edit = self::sanitize_edit( $job, $values[ $id ] );
				if ( null !== $edit ) {
					$old = self::get( $job, $id );
					self::save( $job, $id, $edit, '', 'approved' );
					if ( 'categories' === $job ) {
						self::confirm_category( $id, (int) $edit['id'], $old );
					}
					++$count;
					continue;
				}
			}
			$one = self::get( $job, $id );
			if ( ! $one || 'suggested' !== $one['status'] ) {
				continue;
			}
			if ( 'categories' === $job ) {
				update_term_meta( $id, self::META[ $job ] . '_status', 'approved' );
				self::confirm_category( $id, (int) $one['value'], null );
			} else {
				update_post_meta( $id, self::META[ $job ] . '_status', 'approved' );
			}
			++$count;
		}
		return $count;
	}

	/**
	 * Approving a category suggestion maps the WooCommerce category (the previous mapping is kept for Undo).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int        $term_id Term id.
	 * @param   int        $gid     Google category id.
	 * @param   array|null $old     Unused.
	 */
	protected static function confirm_category( $term_id, $gid, $old ) {
		$prev = get_term_meta( $term_id, self::TERM_CATEGORY, true );
		if ( '' === (string) get_term_meta( $term_id, self::META['categories'] . '_prev', true ) ) {
			update_term_meta( $term_id, self::META['categories'] . '_prev', '' === (string) $prev ? 'none' : (string) $prev );
		}
		update_term_meta( $term_id, self::TERM_CATEGORY, (int) $gid );
	}

	/**
	 * Undo: removes the feed-only value (product data is never touched); a category goes back to its earlier mapping.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job Job.
	 * @param   array  $ids Ids.
	 * @return  int Removed.
	 */
	static function undo( $job, array $ids ) {
		if ( ! isset( self::META[ $job ] ) ) {
			return 0;
		}
		$key   = self::META[ $job ];
		$count = 0;
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
			if ( ! self::get( $job, $id ) ) {
				continue;
			}
			if ( 'categories' === $job ) {
				$prev = (string) get_term_meta( $id, $key . '_prev', true );
				if ( 'approved' === (string) get_term_meta( $id, $key . '_status', true ) && '' !== $prev ) {
					if ( 'none' === $prev ) {
						delete_term_meta( $id, self::TERM_CATEGORY );
					} else {
						update_term_meta( $id, self::TERM_CATEGORY, (int) $prev );
					}
				}
				foreach ( array( '', '_status', '_date', '_confidence', '_prev' ) as $suffix ) {
					delete_term_meta( $id, $key . $suffix );
				}
			} else {
				foreach ( array( '', '_status', '_date', '_request' ) as $suffix ) {
					delete_post_meta( $id, $key . $suffix );
				}
			}
			++$count;
		}
		return $count;
	}

	/**
	 * An edited value, cleaned (null when unusable).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job   Job.
	 * @param   mixed  $value Value.
	 * @return  mixed
	 */
	static function sanitize_edit( $job, $value ) {
		switch ( $job ) {
			case 'titles':
				$v = sanitize_text_field( (string) $value );
				$v = function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 150, 'UTF-8' ) : substr( $v, 0, 150 );
				return '' !== $v ? $v : null;
			case 'descriptions':
				$v = sanitize_textarea_field( (string) $value );
				return '' !== $v ? ( function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 5000, 'UTF-8' ) : substr( $v, 0, 5000 ) ) : null;
			case 'attributes':
				$out = array();
				foreach ( (array) $value as $k => $v ) {
					if ( in_array( $k, self::ATTRIBUTES, true ) && is_scalar( $v ) && '' !== trim( (string) $v ) ) {
						$out[ $k ] = sanitize_text_field( (string) $v );
					}
				}
				return $out ? $out : null;
			case 'categories':
				$gid = absint( is_array( $value ) ? ( $value['id'] ?? 0 ) : $value );
				return $gid ? array(
					'id'         => $gid,
					'confidence' => 1,
				) : null;
		}
		return null;
	}

	/**
	 * Ids with an AI value of a status.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job    Job.
	 * @param   string $status suggested, approved or '' for both.
	 * @param   int    $limit  Most ids.
	 * @param   int    $offset Offset.
	 * @return  int[]
	 */
	static function ids_with( $job, $status = '', $limit = -1, $offset = 0 ) {
		$key  = self::META[ $job ] . '_status';
		$meta = array( 'key' => $key );
		if ( '' !== $status ) {
			$meta['value'] = $status;
		}
		if ( 'categories' === $job ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'fields'     => 'ids',
					'number'     => $limit > 0 ? $limit : 0,
					'offset'     => $offset,
					'meta_query' => array( $meta ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				)
			);
			return is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
		}
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => array( 'product', 'product_variation' ),
					'post_status'    => 'any',
					'posts_per_page' => $limit,
					'offset'         => $offset,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'meta_query'     => array( $meta ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				)
			)
		);
	}

	/**
	 * Results for the review list: before and after, status, date.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $job      Job.
	 * @param   string $status   suggested, approved or ''.
	 * @param   int    $page     Page (from 1).
	 * @param   int    $per_page Per page.
	 * @return  array items, page, per_page, run.
	 */
	static function results( $job, $status = '', $page = 1, $per_page = 50 ) {
		$per_page = max( 1, min( 200, (int) $per_page ) );
		$page     = max( 1, (int) $page );
		$items    = array();
		foreach ( self::ids_with( $job, $status, $per_page, ( $page - 1 ) * $per_page ) as $id ) {
			$one = self::get( $job, $id );
			if ( ! $one ) {
				continue;
			}
			$row = array(
				'id'     => $id,
				'status' => $one['status'],
				'date'   => $one['date'],
				'after'  => $one['value'],
			);
			if ( 'categories' === $job ) {
				$row['name']       = self::category_path( $id );
				$row['before']     = (int) get_term_meta( $id, self::TERM_CATEGORY, true );
				$row['confidence'] = $one['confidence'];
				$rows              = self::taxonomy_rows();
				$row['after_path'] = isset( $rows[ (int) $one['value'] ] ) ? $rows[ (int) $one['value'] ] : '';
			} else {
				$product       = wc_get_product( $id );
				$row['name']   = $product ? $product->get_name() : '';
				$row['before'] = ! $product ? '' : ( 'titles' === $job ? $product->get_name() : ( 'descriptions' === $job ? self::plain( $product->get_description(), 1000 ) : self::current_attributes( $product ) ) );
			}
			$items[] = $row;
		}
		$run = self::progress();
		return array(
			'job'      => $job,
			'items'    => $items,
			'page'     => $page,
			'per_page' => $per_page,
			'run'      => $run && $run['job'] === $job ? self::public_run( $run ) : null,
		);
	}

	/* ------------------------------------------------------------------ shortcodes */

	/**
	 * Registers [alg_product_ai_title] and [alg_product_ai_description] (approved values only).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function register_shortcodes() {
		add_shortcode( 'alg_product_ai_title', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'alg_product_ai_description', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Shortcode output: the approved AI value, '' otherwise.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array|string $atts      product_id, before, after, cdata (yes|no).
	 * @param   string       $content   Content.
	 * @param   string       $shortcode Tag.
	 * @return  string
	 */
	static function shortcode( $atts, $content = '', $shortcode = '' ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'before'     => '',
				'after'      => '',
				'cdata'      => 'no',
			),
			(array) $atts,
			$shortcode
		);
		$id   = absint( $atts['product_id'] );
		if ( ! $id ) {
			global $alg_product_id_for_shortcode;
			$id = ! empty( $alg_product_id_for_shortcode ) ? (int) $alg_product_id_for_shortcode : (int) get_the_ID();
		}
		if ( ! $id || ! in_array( get_post_type( $id ), array( 'product', 'product_variation' ), true ) ) {
			return '';
		}
		// Outside feed builds: only published products, unless the viewer manages the shop.
		$generating = class_exists( 'Alg_WC_Product_XML_Feeds_Core' ) && ! empty( Alg_WC_Product_XML_Feeds_Core::$generating );
		$check      = 'product_variation' === get_post_type( $id ) ? (int) wp_get_post_parent_id( $id ) : $id;
		if ( ! $generating && ! current_user_can( 'manage_woocommerce' ) && ( 'publish' !== get_post_status( $check ) || post_password_required( $check ) ) ) {
			return '';
		}
		$value = (string) self::approved( $id, 'alg_product_ai_description' === $shortcode ? 'description' : 'title' );
		if ( '' === $value ) {
			return '';
		}
		$value = wp_strip_all_tags( $atts['before'] . $value . $atts['after'] );
		if ( 'yes' === $atts['cdata'] ) {
			return '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $value ) . ']]>';
		}
		return $value;
	}

	/* ------------------------------------------------------------------ helpers */

	/**
	 * Plain text, cut on a word.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text  Text.
	 * @param   int    $limit Most characters.
	 * @return  string
	 */
	static function plain( $text, $limit ) {
		$text = wp_strip_all_tags( strip_shortcodes( (string) $text ) );
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > $limit ) {
			$text = mb_substr( $text, 0, $limit, 'UTF-8' );
			$cut  = mb_strrpos( $text, ' ', 0, 'UTF-8' );
			$text = ( false !== $cut && $cut > $limit * 0.6 ? mb_substr( $text, 0, $cut, 'UTF-8' ) : $text ) . '...';
		}
		return $text;
	}

	/**
	 * Category path "A > B > C".
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $term_id Term id.
	 * @return  string
	 */
	static function category_path( $term_id ) {
		$names = array();
		$guard = 0;
		while ( $term_id && $guard++ < 20 ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				break;
			}
			array_unshift( $names, wp_specialchars_decode( $term->name, ENT_QUOTES ) );
			$term_id = (int) $term->parent;
		}
		return implode( ' > ', $names );
	}

	/**
	 * Brand: core Brands taxonomy, then an attribute named brand.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product.
	 * @return  string
	 */
	static function brand( $product ) {
		if ( taxonomy_exists( 'product_brand' ) ) {
			$terms = wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				return wp_specialchars_decode( (string) $terms[0], ENT_QUOTES );
			}
		}
		foreach ( array( 'brand', 'pa_brand' ) as $name ) {
			$v = (string) $product->get_attribute( $name );
			if ( '' !== $v ) {
				return $v;
			}
		}
		return '';
	}

	/**
	 * Product attributes as label => value.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product.
	 * @return  array
	 */
	static function attributes( $product ) {
		$out = array();
		foreach ( (array) $product->get_attributes() as $key => $attr ) {
			$name  = is_object( $attr ) && method_exists( $attr, 'get_name' ) ? $attr->get_name() : (string) $key;
			$value = is_object( $attr ) ? (string) $product->get_attribute( $name ) : (string) $attr;
			if ( '' === $value && is_string( $attr ) && taxonomy_exists( $key ) ) {
				$term  = get_term_by( 'slug', $attr, $key );
				$value = $term ? $term->name : $attr;
			}
			if ( '' !== trim( $value ) ) {
				$label         = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name, $product ) : $name;
				$out[ $label ] = self::plain( $value, 200 );
			}
		}
		return array_slice( $out, 0, 20, true );
	}

	/**
	 * The feed attributes a product already has (color, size...), from its attributes and approved AI values.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product.
	 * @return  array
	 */
	static function current_attributes( $product ) {
		$out = array();
		$all = self::attributes( $product );
		foreach ( self::ATTRIBUTES as $attr ) {
			foreach ( $all as $label => $value ) {
				if ( false !== stripos( str_replace( array( ' ', '-' ), '_', (string) $label ), $attr ) || ( 'color' === $attr && false !== stripos( (string) $label, 'colour' ) ) ) {
					$out[ $attr ] = $value;
					break;
				}
			}
		}
		if ( ! isset( $out['brand'] ) ) {
			$brand = self::brand( $product );
			if ( '' !== $brand ) {
				$out['brand'] = $brand;
			}
		}
		return $out;
	}

	/**
	 * The main product photo as base64 JPEG (max 1,024 px), or ''.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $id Product id.
	 * @return  string
	 */
	static function photo( $id ) {
		$product = wc_get_product( $id );
		$image   = $product ? (int) $product->get_image_id() : 0;
		if ( ! $image && $product && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			$image  = $parent ? (int) $parent->get_image_id() : 0;
		}
		$file = $image ? get_attached_file( $image ) : '';
		if ( ! $file || ! file_exists( $file ) ) {
			return '';
		}
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return '';
		}
		$editor->resize( 1024, 1024, false );
		$tmp   = wp_tempnam( 'pxf-ai' ) . '.jpg';
		$saved = $editor->save( $tmp, 'image/jpeg' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return '';
		}
		$bytes = (string) file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp file.
		wp_delete_file( $saved['path'] );
		wp_delete_file( substr( $tmp, 0, -4 ) );
		return strlen( $bytes ) > 0 && strlen( $bytes ) < 5 * MB_IN_BYTES ? base64_encode( $bytes ) : ''; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- image upload format.
	}

}

endif;

if ( ! class_exists( 'Alg_WC_PXF_Validator_Bridge' ) ) :

/**
 * Title rules shared with the validator (works when the validator module is not loaded).
 *
 * @version 4.0.0
 * @since   4.0.0
 */
class Alg_WC_PXF_Validator_Bridge {

	/**
	 * Promotional words.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @return  array
	 */
	static function promo_words( $text ) {
		if ( class_exists( 'Alg_WC_PXF_Validator' ) ) {
			return Alg_WC_PXF_Validator::promo_words( $text );
		}
		return preg_match( '/\b(?:sale|free\s+shipping|best|cheap|discount|deal|clearance)\b|\d+\s*%\s*off/i', $text, $m ) ? array( strtolower( $m[0] ) ) : array();
	}

	/**
	 * All capitals.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @return  bool
	 */
	static function is_all_caps( $text ) {
		if ( class_exists( 'Alg_WC_PXF_Validator' ) ) {
			return Alg_WC_PXF_Validator::is_all_caps( $text );
		}
		$letters = preg_replace( '/[^\p{L}]+/u', '', $text );
		return strlen( $letters ) >= 8 && mb_strtoupper( $letters ) === $letters && mb_strtolower( $letters ) !== $letters;
	}

}

endif;

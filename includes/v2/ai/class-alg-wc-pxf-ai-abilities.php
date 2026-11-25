<?php
/**
 * Product XML Feeds for WooCommerce - v2 abilities for Agent Control (AI Assistant & MCP Server).
 *
 * Read-only: list feeds, feed status, why a product is missing from a feed. Registered through the `acwc_abilities`
 * and `acwc_run_ability` filters, only when Agent Control is active. Answers use Agent Control's own credits.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_AI_Abilities' ) ) :

class Alg_WC_PXF_AI_Abilities {

	/**
	 * Ability ids.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const IDS = array( 'pxf_list_feeds', 'pxf_feed_status', 'pxf_why_missing' );

	/**
	 * init: waits until every plugin is loaded, then hooks in when Agent Control is there.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_hook' ), 20 );
	}

	/**
	 * Whether Agent Control is active.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  bool
	 */
	static function active() {
		return defined( 'ACWC_VERSION' ) || class_exists( '\WPFactory\AgentControl\Catalogue' );
	}

	/**
	 * Adds the filters when Agent Control is active.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function maybe_hook() {
		if ( ! self::active() ) {
			return;
		}
		add_filter( 'acwc_abilities', array( __CLASS__, 'abilities' ) );
		add_filter( 'acwc_run_ability', array( __CLASS__, 'run' ), 10, 4 );
	}

	/**
	 * The abilities (read only).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $a Abilities.
	 * @return  array
	 */
	static function abilities( $a ) {
		$a   = (array) $a;
		$a[] = array(
			'id'       => 'pxf_list_feeds',
			'area'     => 'reports',
			'effect'   => 'read',
			'label'    => __( 'Show my product feeds', 'product-xml-feeds-for-woocommerce' ),
			'example'  => __( 'which product feeds do I have?', 'product-xml-feeds-for-woocommerce' ),
			'patterns' => array( '/^(?!.*\b(?:why|missing|status)\b).*\b(?:list|show|which|what)\b.*\b(?:product\s+|xml\s+|shopping\s+)?feeds\b/i' ),
			'slots'    => array(),
		);
		$a[] = array(
			'id'       => 'pxf_feed_status',
			'area'     => 'reports',
			'effect'   => 'read',
			'label'    => __( 'How a product feed is doing', 'product-xml-feeds-for-woocommerce' ),
			'example'  => __( 'how is the Google Shopping feed doing?', 'product-xml-feeds-for-woocommerce' ),
			'patterns' => array(
				'/^(?!.*\b(?:why|missing)\b).*\b(?:status|state|health|problems?|issues?)\s+(?:of|with|in)\s+(?:the\s+|my\s+)?(?P<feed>.+?)\s+feed\??$/iu',
				'/^(?!.*\b(?:why|missing)\b)\bhow\s+is\s+(?:the\s+|my\s+)?(?P<feed>.+?)\s+feed\b(?:\s+doing|\s+working)?\??$/iu',
			),
			'slots'    => array( 'feed' => 'text' ),
		);
		$a[] = array(
			'id'       => 'pxf_why_missing',
			'area'     => 'reports',
			'effect'   => 'read',
			'label'    => __( 'Why a product is not in a feed', 'product-xml-feeds-for-woocommerce' ),
			'example'  => __( 'why is the Wool Beanie missing from the Google feed?', 'product-xml-feeds-for-woocommerce' ),
			'patterns' => array(
				'/\bwhy\s+(?:is|isn\'t|is\s+not)\s+(?:the\s+)?(?:product\s+)?(?P<product>.+?)\s+(?:missing\s+from|not\s+in|left\s+out\s+of)\s+(?:the\s+|my\s+)?(?P<feed>.+?)\s+feed\??$/iu',
			),
			'slots'    => array(
				'product' => 'product',
				'feed'    => 'text',
			),
		);
		return $a;
	}

	/**
	 * Runs one of these abilities.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed  $ext    Earlier answer (null).
	 * @param   string $id     Ability id.
	 * @param   array  $s      Slots.
	 * @param   string $source Source.
	 * @return  mixed
	 */
	static function run( $ext, $id, $s, $source = '' ) {
		if ( null !== $ext || ! in_array( $id, self::IDS, true ) ) {
			return $ext;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return new WP_Error( 'alg_pxf_forbidden', __( 'You are not allowed to see the product feeds.', 'product-xml-feeds-for-woocommerce' ) );
		}
		$s = (array) $s;
		switch ( $id ) {
			case 'pxf_list_feeds':
				return self::list_feeds();
			case 'pxf_feed_status':
				return self::feed_status( (string) ( is_array( $s['feed'] ?? null ) ? ( $s['feed']['value'] ?? '' ) : ( $s['feed'] ?? '' ) ) );
			default:
				$p   = $s['product'] ?? null;
				$pid = is_array( $p ) ? (int) ( $p['id'] ?? 0 ) : (int) $p;
				return self::why_missing( $pid, (string) ( is_array( $s['feed'] ?? null ) ? ( $s['feed']['value'] ?? '' ) : ( $s['feed'] ?? '' ) ) );
		}
	}

	/**
	 * Report in Agent Control's format.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $headline Headline.
	 * @param   array  $lines    Lines.
	 * @param   string $link     Link.
	 * @return  array
	 */
	static function report( $headline, array $lines, $link = '' ) {
		if ( class_exists( '\WPFactory\AgentControl\Catalogue' ) && method_exists( '\WPFactory\AgentControl\Catalogue', 'report' ) ) {
			return \WPFactory\AgentControl\Catalogue::report( $headline, $lines, $link );
		}
		return array(
			'kind'     => 'report',
			'headline' => $headline,
			'lines'    => array_values( $lines ),
			'link'     => $link,
		);
	}

	/**
	 * Channel feeds (v2): id => title.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function feeds() {
		if ( ! post_type_exists( 'alg_pxf_feed' ) ) {
			return array();
		}
		$out = array();
		foreach ( get_posts( array( 'post_type' => 'alg_pxf_feed', 'post_status' => 'any', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) ) as $post ) {
			$out[ (int) $post->ID ] = $post->post_title;
		}
		return $out;
	}

	/**
	 * Feed config (merged defaults when the engine is loaded).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @return  array
	 */
	static function config( $feed_id ) {
		if ( class_exists( 'Alg_WC_PXF_Feeds' ) && method_exists( 'Alg_WC_PXF_Feeds', 'get_config' ) ) {
			return (array) Alg_WC_PXF_Feeds::get_config( $feed_id );
		}
		return (array) get_post_meta( $feed_id, '_alg_pxf_config', true );
	}

	/**
	 * The feed whose name best matches some words.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $name Name said.
	 * @return  int 0 when none.
	 */
	static function find_feed( $name ) {
		$feeds = self::feeds();
		$name  = strtolower( trim( preg_replace( '/\s+feed$/i', '', $name ) ) );
		$best  = 0;
		$score = 0;
		foreach ( $feeds as $id => $title ) {
			$config = self::config( $id );
			$hay    = strtolower( $title . ' ' . ( $config['channel'] ?? '' ) );
			$s      = 0;
			if ( '' !== $name && false !== strpos( $hay, $name ) ) {
				$s = 3;
			} else {
				foreach ( array_filter( preg_split( '/\s+/', $name ) ) as $w ) {
					$s += strlen( $w ) > 2 && false !== strpos( $hay, $w ) ? 1 : 0;
				}
			}
			if ( $s > $score ) {
				$score = $s;
				$best  = $id;
			}
		}
		return ( ! $best && 1 === count( $feeds ) ) ? (int) key( $feeds ) : (int) $best;
	}

	/**
	 * Admin link of a feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id (0: the feeds list).
	 * @return  string
	 */
	static function link( $feed_id = 0 ) {
		return $feed_id ? admin_url( 'admin.php?page=alg-pxf&view=edit&feed=' . (int) $feed_id ) : admin_url( 'admin.php?page=alg-pxf' );
	}

	/**
	 * Ability: list feeds.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function list_feeds() {
		$lines = array();
		foreach ( self::feeds() as $id => $title ) {
			$state   = (array) get_post_meta( $id, '_alg_pxf_state', true );
			$config  = self::config( $id );
			$channel = (string) ( $config['channel'] ?? '' );
			if ( class_exists( 'Alg_WC_PXF_Channels' ) && $channel ) {
				$spec    = Alg_WC_PXF_Channels::get( $channel );
				$channel = $spec && ! empty( $spec['label'] ) ? $spec['label'] : $channel;
			}
			$when    = ! empty( $state['last_good'] ) ? sprintf( /* translators: %s: time ago, for example "2 hours". */ __( 'built %s ago', 'product-xml-feeds-for-woocommerce' ), human_time_diff( (int) $state['last_good'] ) ) : __( 'not built yet', 'product-xml-feeds-for-woocommerce' );
			/* translators: 1: feed name, 2: channel, 3: number of products, 4: when built. */
			$lines[] = sprintf( __( '%1$s (%2$s): %3$s products, %4$s', 'product-xml-feeds-for-woocommerce' ), $title, $channel, number_format_i18n( (int) ( $state['written'] ?? 0 ) ), $when );
		}
		$classic = max( 0, (int) get_option( 'alg_products_xml_total_files', 1 ) );
		for ( $i = 1; $i <= min( $classic, 50 ); $i++ ) {
			if ( 'yes' !== get_option( 'alg_products_xml_enabled_' . $i, 'yes' ) ) {
				continue;
			}
			$path = (string) get_option( 'alg_products_xml_file_path_' . $i, 1 === $i ? 'products.xml' : 'products_' . $i . '.xml' );
			/* translators: 1: classic feed number, 2: file name. */
			$lines[] = sprintf( __( 'Classic feed #%1$d: %2$s', 'product-xml-feeds-for-woocommerce' ), $i, $path );
		}
		$n = count( $lines );
		/* translators: %s: number of feeds. */
		return self::report( sprintf( _n( 'You have %s product feed.', 'You have %s product feeds.', $n, 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $n ) ), $lines, self::link() );
	}

	/**
	 * Ability: feed status.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $name Feed name said.
	 * @return  array
	 */
	static function feed_status( $name ) {
		$id = self::find_feed( $name );
		if ( ! $id ) {
			/* translators: %s: feed name. */
			return self::report( sprintf( __( 'I could not find a feed called "%s".', 'product-xml-feeds-for-woocommerce' ), $name ), array_values( self::feeds() ), self::link() );
		}
		$state  = (array) get_post_meta( $id, '_alg_pxf_state', true );
		$status = (string) ( $state['status'] ?? 'idle' );
		$names  = array(
			'idle'    => __( 'waiting for its next build', 'product-xml-feeds-for-woocommerce' ),
			'queued'  => __( 'about to build', 'product-xml-feeds-for-woocommerce' ),
			'running' => __( 'building now', 'product-xml-feeds-for-woocommerce' ),
			'done'    => __( 'up to date', 'product-xml-feeds-for-woocommerce' ),
			'failed'  => __( 'the last build failed', 'product-xml-feeds-for-woocommerce' ),
		);
		$lines   = array();
		/* translators: %s: number of products. */
		$lines[] = sprintf( __( 'Products in the file: %s', 'product-xml-feeds-for-woocommerce' ), number_format_i18n( (int) ( $state['written'] ?? 0 ) ) );
		if ( ! empty( $state['left_out'] ) ) {
			/* translators: %s: number of products. */
			$lines[] = sprintf( __( 'Left out because of errors: %s', 'product-xml-feeds-for-woocommerce' ), number_format_i18n( (int) $state['left_out'] ) );
		}
		$lines[] = ! empty( $state['last_good'] ) ? sprintf( /* translators: %s: time ago. */ __( 'Last complete file: %s ago', 'product-xml-feeds-for-woocommerce' ), human_time_diff( (int) $state['last_good'] ) ) : __( 'No complete file yet.', 'product-xml-feeds-for-woocommerce' );
		if ( ! empty( $state['error'] ) ) {
			$lines[] = (string) $state['error'];
		}
		if ( ! empty( $state['issues'] ) && is_array( $state['issues'] ) && class_exists( 'Alg_WC_PXF_Validator' ) ) {
			foreach ( Alg_WC_PXF_Validator::summary( $state['issues'], 5 )['top'] as $row ) {
				$lines[] = $row['reason'];
			}
		}
		/* translators: 1: feed name, 2: status. */
		return self::report( sprintf( __( '%1$s: %2$s.', 'product-xml-feeds-for-woocommerce' ), get_the_title( $id ), isset( $names[ $status ] ) ? $names[ $status ] : $status ), $lines, self::link( $id ) );
	}

	/**
	 * Ability: why a product is not in a feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int    $product_id Product id.
	 * @param   string $name       Feed name said.
	 * @return  array|WP_Error
	 */
	static function why_missing( $product_id, $name ) {
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return new WP_Error( 'alg_pxf_product', __( 'Which product?', 'product-xml-feeds-for-woocommerce' ) );
		}
		$id = self::find_feed( $name );
		if ( ! $id ) {
			/* translators: %s: feed name. */
			return self::report( sprintf( __( 'I could not find a feed called "%s".', 'product-xml-feeds-for-woocommerce' ), $name ), array_values( self::feeds() ), self::link() );
		}
		$reasons = self::reasons( $product, self::config( $id ) );
		$title   = $product->get_name();
		if ( ! $reasons ) {
			/* translators: 1: product name, 2: feed name. */
			return self::report( sprintf( __( 'Nothing in the settings of %2$s leaves out "%1$s".', 'product-xml-feeds-for-woocommerce' ), $title, get_the_title( $id ) ), array( __( 'It will be in the file after the next build.', 'product-xml-feeds-for-woocommerce' ) ), self::link( $id ) );
		}
		/* translators: 1: product name, 2: feed name. */
		return self::report( sprintf( __( 'Why "%1$s" is not in %2$s:', 'product-xml-feeds-for-woocommerce' ), $title, get_the_title( $id ) ), $reasons, admin_url( 'admin.php?page=alg-pxf&view=products&feed=' . (int) $id ) );
	}

	/**
	 * Plain reasons a product is left out of a feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product.
	 * @param   array      $config  Feed config.
	 * @return  string[]
	 */
	static function reasons( $product, array $config ) {
		$out    = array();
		$base   = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		$base   = $base ? $base : $product;
		$filter = (array) ( $config['filters'] ?? array() );
		if ( 'publish' !== $base->get_status() ) {
			$out[] = __( 'The product is not published.', 'product-xml-feeds-for-woocommerce' );
		}
		if ( '' !== (string) $base->get_post_password() ) {
			$out[] = __( 'The product is password protected.', 'product-xml-feeds-for-woocommerce' );
		}
		if ( 'hidden' === $base->get_catalog_visibility() ) {
			$out[] = __( 'The product is hidden from the shop (catalog visibility).', 'product-xml-feeds-for-woocommerce' );
		}
		if ( ! empty( $filter['stock'] ) && ! in_array( $product->get_stock_status(), (array) $filter['stock'], true ) ) {
			/* translators: %s: stock status. */
			$out[] = sprintf( __( 'The feed only takes some stock statuses, and this product is "%s".', 'product-xml-feeds-for-woocommerce' ), $product->get_stock_status() );
		}
		$cats = array_map( 'intval', (array) $base->get_category_ids() );
		if ( ! empty( $filter['include_cats'] ) && ! array_intersect( $cats, array_map( 'intval', (array) $filter['include_cats'] ) ) ) {
			$out[] = __( 'The feed only takes some categories, and this product is in none of them.', 'product-xml-feeds-for-woocommerce' );
		}
		if ( ! empty( $filter['exclude_cats'] ) && array_intersect( $cats, array_map( 'intval', (array) $filter['exclude_cats'] ) ) ) {
			$out[] = __( 'The product is in a category the feed leaves out.', 'product-xml-feeds-for-woocommerce' );
		}
		$tags = array_map( 'intval', (array) $base->get_tag_ids() );
		if ( ! empty( $filter['include_tags'] ) && ! array_intersect( $tags, array_map( 'intval', (array) $filter['include_tags'] ) ) ) {
			$out[] = __( 'The feed only takes some tags, and this product has none of them.', 'product-xml-feeds-for-woocommerce' );
		}
		if ( ! empty( $filter['exclude_tags'] ) && array_intersect( $tags, array_map( 'intval', (array) $filter['exclude_tags'] ) ) ) {
			$out[] = __( 'The product has a tag the feed leaves out.', 'product-xml-feeds-for-woocommerce' );
		}
		if ( ! empty( $filter['exclude_ids'] ) && array_intersect( array( $product->get_id(), $base->get_id() ), array_map( 'intval', (array) $filter['exclude_ids'] ) ) ) {
			$out[] = __( 'The product is on the feed\'s list of left out products.', 'product-xml-feeds-for-woocommerce' );
		}
		$price = (float) $product->get_price();
		if ( '' !== (string) ( $filter['min_price'] ?? '' ) && $price < (float) $filter['min_price'] ) {
			$out[] = __( 'The price is below the feed\'s lowest price.', 'product-xml-feeds-for-woocommerce' );
		}
		if ( '' !== (string) ( $filter['max_price'] ?? '' ) && $price > (float) $filter['max_price'] ) {
			$out[] = __( 'The price is above the feed\'s highest price.', 'product-xml-feeds-for-woocommerce' );
		}
		if ( 'yes' === ( $filter['on_sale'] ?? '' ) && ! $product->is_on_sale() ) {
			$out[] = __( 'The feed only takes products on sale.', 'product-xml-feeds-for-woocommerce' );
		} elseif ( 'no' === ( $filter['on_sale'] ?? '' ) && $product->is_on_sale() ) {
			$out[] = __( 'The feed leaves out products on sale.', 'product-xml-feeds-for-woocommerce' );
		}
		// Channel rules, when the engine modules are loaded.
		if ( class_exists( 'Alg_WC_PXF_Product_Data' ) && class_exists( 'Alg_WC_PXF_Mapper' ) && class_exists( 'Alg_WC_PXF_Channels' ) && class_exists( 'Alg_WC_PXF_Validator' ) ) {
			$channel = Alg_WC_PXF_Channels::get( (string) ( $config['channel'] ?? 'google' ) );
			if ( $channel ) {
				$model  = Alg_WC_PXF_Product_Data::model( $product, $config );
				$item   = Alg_WC_PXF_Mapper::item( $model, $channel, $config );
				$errors = array();
				foreach ( Alg_WC_PXF_Validator::check( $item, $model, $channel ) as $issue ) {
					if ( 'error' === $issue['severity'] ) {
						$errors[] = $issue['message'];
					}
				}
				if ( $errors ) {
					$out[] = ! empty( $config['leave_out_errors'] )
						? __( 'The feed leaves out products with errors, and this one has some:', 'product-xml-feeds-for-woocommerce' )
						: __( 'The product is in the file, but the channel will reject it because of these errors:', 'product-xml-feeds-for-woocommerce' );
					$out   = array_merge( $out, $errors );
				}
			}
		}
		return $out;
	}

}

endif;

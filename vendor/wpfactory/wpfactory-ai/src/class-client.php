<?php
/**
 * The WPFactory AI client: one site connection and one wallet for every WPFactory plugin on the site.
 *
 *   wpfactory_ai()->register_product( 'helpdesk', array( 'name' => 'Helpdesk' ) );   // on init
 *   $r = wpfactory_ai()->call( 'helpdesk', $messages_api_body, array( 'task' => 'reply-draft' ) );
 *   if ( ! is_wp_error( $r ) ) { $answer = $r['message']['content']; $r['credits']; $r['balance']; }
 *
 * Nothing is sent to WPFactory until a site administrator connects the site.
 *
 * @package WPFactory\AI
 */

namespace WPFactory\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Client.
 */
final class Client {

	const VERSION = '1.7.0';

	/**
	 * The one instance.
	 *
	 * @var Client|null
	 */
	private static $instance = null;

	/**
	 * Plugins that use credits on this site, by product id.
	 *
	 * @var array<string,array{name:string}>
	 */
	private $products = array();

	/**
	 * The client.
	 *
	 * @return Client
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks. Called once, by the newest bundled copy.
	 */
	public function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'rest_api_init', array( Rest::class, 'register' ) );
		if ( is_admin() ) {
			Admin::boot();
		}
	}

	/**
	 * Library version that loaded.
	 *
	 * @return string
	 */
	public function version() {
		return self::VERSION;
	}

	/**
	 * The library's own translations.
	 */
	public function load_textdomain() {
		$mo = dirname( __DIR__ ) . '/langs/wpfactory-ai-' . determine_locale() . '.mo';
		if ( is_readable( $mo ) ) {
			load_textdomain( 'wpfactory-ai', $mo );
		}
	}

	/**
	 * A plugin that spends credits registers once, on init. Its name shows on the WPFactory AI screen.
	 *
	 * @param string               $id   Product id the server knows (ai-assistant-mcp, helpdesk, image-generator).
	 * @param array<string,string> $args name: shown to the owner.
	 */
	public function register_product( $id, $args = array() ) {
		$id                    = sanitize_key( $id );
		$this->products[ $id ] = array( 'name' => isset( $args['name'] ) ? (string) $args['name'] : $id );
	}

	/**
	 * Registered plugins.
	 *
	 * @return array<string,array{name:string}>
	 */
	public function products() {
		return $this->products;
	}

	/**
	 * Server address (filter wpfactory_ai_endpoint, for local development).
	 *
	 * @return string
	 */
	public function endpoint() {
		return untrailingslashit( (string) apply_filters( 'wpfactory_ai_endpoint', 'https://ai.wpfactory.com' ) );
	}

	/**
	 * Where owners buy credits: the packs on the WPFactory AI screen.
	 *
	 * @return string
	 */
	public function buy_url() {
		return (string) apply_filters( 'wpfactory_ai_buy_url', $this->admin_url() . '#wpfactory-ai-buy' );
	}

	/**
	 * Credit packs, and whether buying is open. Cached for an hour.
	 *
	 * @return array{open:bool, packs:array<int,array{usd:int, credits:int}>}|\WP_Error
	 */
	public function packs() {
		$hit = get_transient( 'wpfactory_ai_packs' );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$data = $this->request( 'GET', '/v1/packs', null, array(), false );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array(
			'open'  => ! empty( $data['open'] ),
			'packs' => isset( $data['packs'] ) ? array_values( (array) $data['packs'] ) : array(),
		);
		set_transient( 'wpfactory_ai_packs', $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Opens a Stripe payment page for a credit pack. Send the owner's browser to the returned address; they
	 * come back to $return_url (default: the WPFactory AI screen). Credits arrive a few seconds after paying.
	 *
	 * @param int    $usd        Pack in US dollars: 5, 10, 25 or 50.
	 * @param string $return_url An address on this site.
	 * @return string|\WP_Error Stripe Checkout address.
	 */
	public function checkout( $usd, $return_url = '' ) {
		$data = $this->request(
			'POST',
			'/v1/checkout',
			array(
				'pack'       => (int) $usd,
				'return_url' => '' !== $return_url ? esc_url_raw( $return_url ) : $this->admin_url(),
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return isset( $data['url'] ) ? (string) $data['url'] : new \WP_Error( 'wpfactory_ai_unexpected', __( 'WPFactory AI sent an unexpected answer. Please try again.', 'wpfactory-ai' ), array( 'status' => 502 ) );
	}

	/**
	 * The WPFactory AI screen (WPFactory > WPFactory AI, or Settings > WPFactory AI without the WPFactory menu).
	 *
	 * @return string
	 */
	public function admin_url() {
		return Admin::url();
	}

	/**
	 * Is this site connected?
	 *
	 * @return bool
	 */
	public function is_connected() {
		return '' !== Connection::token();
	}

	/**
	 * Connection details for screens (never the token).
	 *
	 * @return array<string,mixed>
	 */
	public function connection() {
		return Connection::info();
	}

	/**
	 * Step 1 of connecting: email a 6-digit code to the owner.
	 *
	 * A plugin that is sold (no free version) passes its product id: the server then sends the code only
	 * when this email bought that plugin on wpfactory.com, and otherwise answers not_purchased.
	 *
	 * @param string $email   Owner's email.
	 * @param string $product Optional product id that must have been bought.
	 * @return true|\WP_Error
	 */
	public function start_connect( $email, $product = '' ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'wpfactory_ai_invalid_email', __( 'Please enter a valid email address.', 'wpfactory-ai' ), array( 'status' => 400 ) );
		}
		$res = $this->request(
			'POST',
			'/v1/connect/start',
			array(
				'email'    => $email,
				'site_url' => home_url(),
			) + ( '' !== (string) $product ? array( 'product' => (string) $product ) : array() ),
			array(),
			false
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		Connection::remember_email( $email );
		return true;
	}

	/**
	 * Step 2 of connecting: the code from the email. The server creates or finds the account.
	 *
	 * @param string $email Owner's email.
	 * @param string $code  6-digit code.
	 * @return array<string,mixed>|\WP_Error Connection details.
	 */
	public function finish_connect( $email, $code ) {
		$body = array(
			'email'    => sanitize_email( $email ),
			'code'     => preg_replace( '/\D/', '', (string) $code ),
			'site_url' => home_url(),
			'product'  => (string) key( $this->products ),
		);
		$res  = $this->request( 'POST', '/v1/connect/verify', $body, array(), false );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( empty( $res['token'] ) ) {
			return new \WP_Error( 'wpfactory_ai_unexpected', __( 'WPFactory AI sent an unexpected answer. Please try again.', 'wpfactory-ai' ), array( 'status' => 502 ) );
		}
		Connection::save( (string) $res['token'], (string) ( $res['account']['email'] ?? $body['email'] ), (int) ( $res['site_id'] ?? 0 ) );
		if ( ! empty( $res['balance']['product'] ) ) {
			$this->remember_balance( (string) $res['balance']['product'], (array) $res['balance'] );
		}
		do_action( 'wpfactory_ai_connected', Connection::info() );
		return Connection::info();
	}

	/**
	 * Disconnects this site (the server forgets its token).
	 */
	public function disconnect() {
		if ( $this->is_connected() ) {
			$this->request( 'POST', '/v1/disconnect', array() );
		}
		Connection::forget();
		foreach ( array_keys( $this->products ) as $product ) {
			delete_transient( 'wpfactory_ai_balance_' . $product );
		}
		do_action( 'wpfactory_ai_disconnected' );
	}

	/**
	 * One AI call, in the Anthropic Messages format, paid with credits. Use (object) array() for an empty
	 * JSON object in tool schemas.
	 *
	 * @param string              $product Product id.
	 * @param array<string,mixed> $request Messages API body (model, max_tokens, messages, system, tools...).
	 * @param array<string,mixed> $opts    task (for usage by task), request_id (UUID, keep it for refunds), timeout.
	 * @return array{message:array<string,mixed>, request_id:string, credits:int, balance:int}|\WP_Error
	 */
	public function call( $product, $request, $opts = array() ) {
		$product = sanitize_key( $product );
		$rid     = ! empty( $opts['request_id'] ) ? (string) $opts['request_id'] : wp_generate_uuid4();
		$headers = array(
			'X-WPFactory-Product'    => $product,
			'X-WPFactory-Task'       => sanitize_key( isset( $opts['task'] ) ? (string) $opts['task'] : 'ask' ),
			'X-WPFactory-Request-Id' => $rid,
		);
		$data    = $this->request( 'POST', '/v1/messages', (array) $request, $headers, true, isset( $opts['timeout'] ) ? (int) $opts['timeout'] : 120 );
		if ( is_wp_error( $data ) ) {
			$info = (array) $data->get_error_data();
			if ( isset( $info['balance'] ) ) {
				$this->remember_balance( $product, array( 'balance' => (int) $info['balance'] ) );
			}
			return $data;
		}
		$wpf = isset( $data['wpfactory'] ) ? (array) $data['wpfactory'] : array();
		unset( $data['wpfactory'] );
		$this->remember_balance( $product, $wpf );
		return array(
			'message'    => $data,
			'request_id' => isset( $wpf['request_id'] ) ? (string) $wpf['request_id'] : $rid,
			'credits'    => (int) ( $wpf['credits'] ?? 0 ),
			'balance'    => (int) ( $wpf['balance'] ?? 0 ),
		);
	}

	/**
	 * One picture from an image model (Gemini), paid with credits. Nothing is charged when no picture comes back;
	 * a picture the provider refuses for safety gives the code wpfactory_ai_image_blocked.
	 *
	 * @param string              $product Product id.
	 * @param array<string,mixed> $request model (optional: the plugin's default), prompt, images (1 to 4: array of
	 *                                     mime_type and base64 data; the product photo first), aspect_ratio, temperature.
	 * @param array<string,mixed> $opts    task (generate, wizard, bulk, automation), request_id (UUID, keep it for refunds), timeout.
	 * @return array{images:array<int,array{mime_type:string, data:string}>, text:string, model:string, request_id:string, credits:int, balance:int}|\WP_Error
	 */
	public function image( $product, $request, $opts = array() ) {
		$product = sanitize_key( $product );
		$rid     = ! empty( $opts['request_id'] ) ? (string) $opts['request_id'] : wp_generate_uuid4();
		$headers = array(
			'X-WPFactory-Product'    => $product,
			'X-WPFactory-Task'       => sanitize_key( isset( $opts['task'] ) ? (string) $opts['task'] : 'generate' ),
			'X-WPFactory-Request-Id' => $rid,
		);
		$data    = $this->request( 'POST', '/v1/images', (array) $request, $headers, true, isset( $opts['timeout'] ) ? (int) $opts['timeout'] : 120 );
		if ( is_wp_error( $data ) ) {
			$info = (array) $data->get_error_data();
			if ( isset( $info['balance'] ) ) {
				$this->remember_balance( $product, array( 'balance' => (int) $info['balance'] ) );
			}
			return $data;
		}
		$wpf = isset( $data['wpfactory'] ) ? (array) $data['wpfactory'] : array();
		$this->remember_balance( $product, $wpf );
		return array(
			'images'     => isset( $data['images'] ) ? array_values( (array) $data['images'] ) : array(),
			'text'       => isset( $data['text'] ) ? (string) $data['text'] : '',
			'model'      => isset( $data['model'] ) ? (string) $data['model'] : '',
			'request_id' => isset( $wpf['request_id'] ) ? (string) $wpf['request_id'] : $rid,
			'credits'    => (int) ( $wpf['credits'] ?? 0 ),
			'balance'    => (int) ( $wpf['balance'] ?? 0 ),
		);
	}

	/**
	 * The credits a paid plugin gives once for buying it on wpfactory.com (AI Image: 150 images). The server checks
	 * that the connected email has a completed order for the plugin, billed to that exact email.
	 *
	 * @param string $product Product id.
	 * @return array{result:string, credits:int, images:int, balance:int, paid:int, free:int}|\WP_Error result is
	 *         granted, already_granted or not_purchased; wpfactory_ai_claim_unavailable when wpfactory.com cannot be checked.
	 */
	public function claim( $product ) {
		$product = sanitize_key( $product );
		$data    = $this->request( 'POST', '/v1/claim', array( 'product' => $product ), array( 'X-WPFactory-Product' => $product ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$this->remember_balance( $product, $data );
		return array(
			'result'  => isset( $data['result'] ) ? (string) $data['result'] : '',
			'credits' => (int) ( $data['credits'] ?? 0 ),
			'images'  => (int) ( $data['images'] ?? 0 ),
			'balance' => (int) ( $data['balance'] ?? 0 ),
			'paid'    => (int) ( $data['paid'] ?? 0 ),
			'free'    => (int) ( $data['free'] ?? 0 ),
		);
	}

	/**
	 * Credits per image for each image model of a product, and the default model, so a plugin can say
	 * "17 credits per image" before the owner generates. Cached for 5 minutes.
	 *
	 * @param string $product Product id.
	 * @return array{images:array<string,int>, default:string, flat:bool, purchase_grant:array{images:int, credits:int}}|\WP_Error
	 */
	public function prices( $product ) {
		$product = sanitize_key( $product );
		$key     = 'wpfactory_ai_prices_' . $product;
		$hit     = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$data = $this->request( 'GET', '/v1/prices?product=' . rawurlencode( $product ), null, array(), false );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$grant = isset( $data['purchase_grant'] ) && is_array( $data['purchase_grant'] ) ? $data['purchase_grant'] : array();
		$out   = array(
			'images'         => array_map( 'intval', isset( $data['images'] ) ? (array) $data['images'] : array() ),
			'default'        => isset( $data['default'] ) ? (string) $data['default'] : '',
			'flat'           => ! empty( $data['flat'] ),
			'purchase_grant' => array(
				'images'  => (int) ( $grant['images'] ?? 0 ),
				'credits' => (int) ( $grant['credits'] ?? 0 ),
			),
		);
		set_transient( $key, $out, 5 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Credits this site can spend for a product: paid credits (shared by all plugins) plus this month's free
	 * credits for that product. Cached for 5 minutes and updated after every call.
	 *
	 * @param string $product Product id.
	 * @param bool   $fresh   Ask the server now.
	 * @return array{balance:int, paid:int, free:int, free_expires_at:string}|\WP_Error
	 */
	public function balance( $product, $fresh = false ) {
		$product = sanitize_key( $product );
		$cached  = get_transient( 'wpfactory_ai_balance_' . $product );
		if ( ! $fresh && is_array( $cached ) && isset( $cached['paid'], $cached['at'] ) && time() - (int) $cached['at'] < 300 ) {
			return $cached;
		}
		$data = $this->request( 'GET', '/v1/balance?product=' . rawurlencode( $product ) );
		if ( is_wp_error( $data ) ) {
			return is_array( $cached ) ? $cached + array( 'stale' => true ) : $data;
		}
		$this->remember_balance( $product, $data );
		return (array) get_transient( 'wpfactory_ai_balance_' . $product );
	}

	/**
	 * Gives back the credits of one request, once: a failed job, or a change the owner undid within minutes.
	 *
	 * @param string   $request_id From call().
	 * @param int|null $credits    Fewer than charged, or null for all of them.
	 * @return array<string,mixed>|\WP_Error refunded, balance, paid, free.
	 */
	public function refund( $request_id, $credits = null ) {
		$body = array( 'request_id' => (string) $request_id );
		if ( null !== $credits ) {
			$body['credits'] = (int) $credits;
		}
		$data = $this->request( 'POST', '/v1/refund', $body );
		if ( ! is_wp_error( $data ) && ! empty( $data['product'] ) ) {
			$this->remember_balance( (string) $data['product'], $data );
		}
		return $data;
	}

	/**
	 * Usage this month (or another), by plugin and task, in numbers only. Cached for 5 minutes.
	 *
	 * @param string $period YYYY-MM, or '' for this month.
	 * @param string $scope  account (all the account's sites) or site.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function usage( $period = '', $scope = 'account' ) {
		$query = array_filter(
			array(
				'period' => (string) $period,
				'scope'  => 'site' === $scope ? 'site' : 'account',
			)
		);
		$key   = 'wpfactory_ai_usage_' . md5( (string) wp_json_encode( $query ) );
		$hit   = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$data = $this->request( 'GET', '/v1/usage?' . http_build_query( $query ) );
		if ( ! is_wp_error( $data ) ) {
			set_transient( $key, $data, 5 * MINUTE_IN_SECONDS );
		}
		return $data;
	}

	/**
	 * A small "123 credits" link to the WPFactory AI screen, for a plugin's header. Uses the cached balance
	 * only, so it never slows a page down.
	 *
	 * @param string $product Product id.
	 * @return string HTML.
	 */
	public function pill( $product ) {
		Admin::enqueue_pill_style();
		$cached = get_transient( 'wpfactory_ai_balance_' . sanitize_key( $product ) );
		if ( ! $this->is_connected() ) {
			$label = __( 'Connect AI', 'wpfactory-ai' );
		} elseif ( is_array( $cached ) && isset( $cached['balance'] ) ) {
			$n = (int) $cached['balance'];
			/* translators: %s: number of credits */
			$label = sprintf( _n( '%s credit', '%s credits', $n, 'wpfactory-ai' ), number_format_i18n( $n ) );
		} else {
			$label = __( 'AI credits', 'wpfactory-ai' );
		}
		return sprintf( '<a class="wpfactory-ai-pill" href="%s">%s</a>', esc_url( $this->admin_url() ), esc_html( $label ) );
	}

	/**
	 * Keeps the latest balance numbers for a product.
	 *
	 * @param string              $product Product id.
	 * @param array<string,mixed> $numbers balance, paid, free, free_expires_at (any of them).
	 */
	private function remember_balance( $product, array $numbers ) {
		$key  = 'wpfactory_ai_balance_' . sanitize_key( $product );
		$old  = get_transient( $key );
		$keep = array_intersect_key( $numbers, array_flip( array( 'balance', 'paid', 'free', 'free_expires_at' ) ) );
		set_transient( $key, array_merge( is_array( $old ) ? $old : array(), $keep, array( 'at' => time() ) ), HOUR_IN_SECONDS );
	}

	/**
	 * One JSON request to the server.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $path    Path from the server root.
	 * @param array<string,mixed>|null $body JSON body.
	 * @param array<string,string> $headers More headers.
	 * @param bool                 $auth    Send the site token.
	 * @param int                  $timeout Seconds.
	 * @return array<string,mixed>|\WP_Error The decoded answer, or the server's error as WP_Error.
	 */
	private function request( $method, $path, $body = null, $headers = array(), $auth = true, $timeout = 30 ) {
		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => array_merge(
				array(
					'Accept'     => 'application/json',
					'User-Agent' => 'WPFactory-AI/' . self::VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				),
				$headers
			),
		);
		if ( $auth ) {
			$token = Connection::token();
			if ( '' === $token ) {
				return new \WP_Error( 'wpfactory_ai_not_connected', __( 'Connect this site to WPFactory AI first.', 'wpfactory-ai' ), array( 'status' => 401 ) );
			}
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		$res = wp_remote_request( $this->endpoint() . $path, $args );
		if ( is_wp_error( $res ) ) {
			return new \WP_Error(
				'wpfactory_ai_network',
				/* translators: %s: error message */
				sprintf( __( 'Could not reach WPFactory AI: %s. Nothing was charged.', 'wpfactory-ai' ), $res->get_error_message() ),
				array( 'status' => 502 )
			);
		}
		$status = (int) wp_remote_retrieve_response_code( $res );
		$data   = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 === $status && is_array( $data ) ) {
			return $data;
		}
		if ( $auth && 401 === $status ) {
			Connection::mark_broken();
		}
		$type    = is_array( $data ) && ! empty( $data['error']['type'] ) ? sanitize_key( (string) $data['error']['type'] ) : 'http_' . $status;
		$message = is_array( $data ) && ! empty( $data['error']['message'] )
			? (string) $data['error']['message']
			/* translators: %d: HTTP status */
			: sprintf( __( 'WPFactory AI answered with an error (%d). Nothing was charged.', 'wpfactory-ai' ), $status );
		$extra = is_array( $data ) && isset( $data['wpfactory'] ) ? (array) $data['wpfactory'] : array();
		return new \WP_Error( 'wpfactory_ai_' . $type, $message, array( 'status' => $status >= 400 ? $status : 502 ) + $extra );
	}
}

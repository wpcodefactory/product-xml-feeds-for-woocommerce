<?php
/**
 * WPFactory > AI Credits (Settings > AI Credits when no WPFactory plugin made that menu): connect the site (email, then a 6-digit code), see credits and usage per plugin,
 * buy credits, disconnect. Shown once at least one plugin registered a product.
 *
 * @package WPFactory\AI
 */

namespace WPFactory\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen and the credits pill style.
 */
final class Admin {

	const SLUG = 'wpfactory-ai';

	/**
	 * The WPFactory top-level menu (wpfactory/wpfactory-admin-menu).
	 */
	const WPFACTORY_MENU = 'wpfactory';

	/**
	 * The screen's page hook, once added.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * The screen's parent menu, once added.
	 *
	 * @var string
	 */
	private static $parent = '';

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_page_access_denied', array( __CLASS__, 'redirect_old_address' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Lets the screen take the whole content area, like the WPFactory plugins' own screens.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return ( $screen && '' !== self::$hook && self::$hook === $screen->id ) ? $classes . ' wpfai-body' : $classes;
	}

	/**
	 * The screen's address. admin.php?page= opens it under either parent menu.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Adds the screen: under the WPFactory menu when a WPFactory plugin made it, else under Settings.
	 */
	public static function menu() {
		global $admin_page_hooks;
		if ( ! wpfactory_ai()->products() ) {
			return;
		}
		$parent = empty( $admin_page_hooks[ self::WPFACTORY_MENU ] ) ? 'options-general.php' : self::WPFACTORY_MENU;

		self::$parent = (string) apply_filters( 'wpfactory_ai_menu_parent', $parent );

		$hook = add_submenu_page(
			self::$parent,
			__( 'AI Credits', 'wpfactory-ai' ),
			__( 'AI Credits', 'wpfactory-ai' ),
			(string) apply_filters( 'wpfactory_ai_capability', 'manage_options' ),
			self::SLUG,
			array( __CLASS__, 'render' )
		);

		self::$hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Sends the old Settings address (options-general.php?page=wpfactory-ai: bookmarks, older links) to the screen
	 * when it lives under another menu. WordPress refuses that address before admin_init, so this runs on
	 * admin_page_access_denied, just before its "not allowed" message. Users who cannot open the screen still get that message.
	 */
	public static function redirect_old_address() {
		global $pagenow;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a redirect to the same screen; nothing is saved.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'options-general.php' !== $pagenow || self::SLUG !== $page || '' === self::$hook || 'options-general.php' === self::$parent ) {
			return;
		}
		$args = array();
		foreach ( wp_unslash( $_GET ) as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$args[ sanitize_key( $key ) ] = rawurlencode( sanitize_text_field( (string) $value ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Styles and the connect script, on the screen only.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function assets( $hook ) {
		if ( '' === self::$hook || self::$hook !== $hook ) {
			return;
		}
		self::enqueue_pill_style();
		wp_add_inline_style( 'wpfactory-ai', self::css() );
		wp_register_script( 'wpfactory-ai-admin', false, array( 'wp-api-fetch' ), Client::VERSION, true );
		wp_enqueue_script( 'wpfactory-ai-admin' );
		wp_add_inline_script(
			'wpfactory-ai-admin',
			'window.wpfactoryAi = ' . wp_json_encode(
				array(
					/* translators: %s: email address */
					'sent'       => __( 'We sent a 6-digit code to %s. It works for 10 minutes.', 'wpfactory-ai' ),
					'error'      => __( 'Something went wrong. Please try again.', 'wpfactory-ai' ),
					'disconnect' => __( 'Disconnect this site from WPFactory AI? AI features that use credits stop until you connect again.', 'wpfactory-ai' ),
				)
			) . ';' . self::script(),
			'after'
		);
	}

	/**
	 * The small credits pill style, on any admin page that shows a pill.
	 */
	public static function enqueue_pill_style() {
		if ( wp_style_is( 'wpfactory-ai', 'enqueued' ) ) {
			return;
		}
		wp_register_style( 'wpfactory-ai', false, array(), Client::VERSION );
		wp_enqueue_style( 'wpfactory-ai' );
		wp_add_inline_style(
			'wpfactory-ai',
			'.wpfactory-ai-pill{display:inline-block;padding:2px 10px;border-radius:999px;background:#f0f6fc;color:#0a4b78;border:1px solid #c5d9ed;font-size:12px;line-height:20px;text-decoration:none;white-space:nowrap}'
			. '.wpfactory-ai-pill:hover,.wpfactory-ai-pill:focus{background:#e5f0fa;color:#0a4b78}'
		);
	}

	/**
	 * The WPFactory logo, inline so it shows wherever the library is bundled.
	 *
	 * @return string SVG, filtered by wp_kses().
	 */
	private static function logo() {
		$svg = (string) file_get_contents( dirname( __DIR__ ) . '/assets/wpfactory-logo.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A file of this library.
		return wp_kses(
			$svg,
			array(
				'svg'            => array(
					'xmlns'      => true,
					'viewbox'    => true,
					'fill'       => true,
					'role'       => true,
					'aria-label' => true,
				),
				'path'           => array(
					'd'         => true,
					'fill'      => true,
					'fill-rule' => true,
					'clip-rule' => true,
				),
				'defs'           => array(),
				'lineargradient' => array(
					'id'            => true,
					'x1'            => true,
					'y1'            => true,
					'x2'            => true,
					'y2'            => true,
					'gradientunits' => true,
				),
				'stop'           => array(
					'offset'     => true,
					'stop-color' => true,
				),
			)
		);
	}

	/**
	 * The screen's look: the WPFactory plugins' admin skin (paper, white cards, pills, one tool blue), scoped to .wpfai.
	 *
	 * @return string
	 */
	private static function css() {
		return <<<'CSS'
body.wpfai-body #wpcontent{padding-left:0}
body.wpfai-body #wpbody-content{padding-bottom:0}
body.wpfai-body #wpfooter{display:none}
body.wpfai-body .wrap.wpfai{margin:0}
.wpfai{--paper:#F7F4EA;--card:#FFF;--chip:#ECE7D6;--chip-hover:#E2DCC8;--ink:#171509;--ink-2:#5A5645;--ink-3:#6B6653;--tool:#6BBFFF;--tool-hover:#5AB3F5;--tool-tint:#E4F3FF;--tool-text:#046BD2;--pass-soft:rgba(44,156,87,.12);--pass-text:#1F7A43;--fail-soft:rgba(196,43,26,.10);--fail-text:#C42B1A;--hairline:#E8E3D2;font-family:'IBM Plex Sans Arabic',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:var(--ink);font-size:15px;line-height:1.55;background:var(--paper);min-height:calc(100vh - 32px);-webkit-font-smoothing:antialiased}
.wpfai *{box-sizing:border-box}
.wpfai h1,.wpfai h2,.wpfai p,.wpfai ul{margin:0;padding:0;color:inherit}
.wpfai ul{list-style:none}
.wpfai [hidden]{display:none !important}
.wpfai a{color:var(--ink)}
.wpfai a:hover{color:var(--tool-text)}
.wpfai :focus-visible{outline:3px solid var(--ink);outline-offset:3px;box-shadow:none}
.wpfai .wp-header-end{visibility:hidden;margin:0;border:0;height:0}
.wpfai>.notice,.wpfai>.updated,.wpfai>.error{margin:12px 32px 0}
.wpfai-bar{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:16px;padding:14px 24px;background:var(--card);box-shadow:inset 0 -1px 0 var(--hairline)}
.wpfai-brand{grid-column:2;display:flex;align-items:center;gap:12px}
.wpfai-brand svg{height:26px;width:auto;display:block;color:var(--ink)}
.wpfai-brand b{padding-left:12px;border-left:1px solid var(--hairline);font-size:15px;letter-spacing:-.01em;white-space:nowrap}
.wpfai-bar__end{grid-column:3;justify-self:end}
.wpfai-main{max-width:1180px;margin:0 auto;padding:28px 32px 56px;display:flex;flex-direction:column;gap:20px}
.wpfai-head{display:flex;flex-direction:column;gap:8px}
.wpfai .wpfai-kicker{display:block;font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--ink-3)}
.wpfai .wpfai-h1{font-size:36px;line-height:1.05;font-weight:700;letter-spacing:-.03em}
.wpfai .wpfai-h2{font-size:20px;line-height:1.2;font-weight:700;letter-spacing:-.02em}
.wpfai .wpfai-lede{font-size:17px;color:var(--ink-2);max-width:720px}
.wpfai-text{font-size:14px;color:var(--ink-2)}
.wpfai-small{font-size:13px;color:var(--ink-2)}
.wpfai-num{font-variant-numeric:tabular-nums;white-space:nowrap}
.wpfai-two{display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start}
.wpfai-col{flex:1.3 1 520px;min-width:0;display:flex;flex-direction:column;gap:16px}
.wpfai-side{flex:1 1 320px;min-width:0;display:flex;flex-direction:column;gap:16px}
.wpfai-card{background:var(--card);border-radius:22px;padding:24px;display:flex;flex-direction:column;gap:12px}
.wpfai-card--ink{background:var(--ink);color:#FFF}
.wpfai-card--ink .wpfai-kicker{color:var(--tool)}
.wpfai-card--ink .wpfai-text,.wpfai-card--ink .wpfai-small{color:rgba(255,255,255,.78)}
.wpfai-hero{display:flex;gap:28px;align-items:center;flex-wrap:wrap}
.wpfai-big{font-size:64px;font-weight:300;line-height:1;letter-spacing:-.03em}
.wpfai-hero__side{flex:1 1 240px;display:flex;flex-direction:column;gap:6px}
.wpfai-row{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:12px 0;border-top:1px solid var(--hairline)}
.wpfai-rows .wpfai-row:first-child{border-top:0;padding-top:4px}
.wpfai-row__title{font-weight:700}
.wpfai-packs{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
.wpfai-pack{background:var(--paper);border-radius:18px;padding:16px;display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center}
.wpfai-pack b{font-size:28px;font-weight:300;letter-spacing:-.02em;line-height:1}
.wpfai-pack .wpfai-btn{margin-top:6px}
.wpfai .wpfai-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;height:44px;min-height:0;padding:0 20px;border:0;border-radius:999px;background:var(--chip);color:var(--ink);font-size:14px;font-weight:700;line-height:1;text-decoration:none;white-space:nowrap;box-shadow:none;cursor:pointer;transition:background .15s}
.wpfai .wpfai-btn:hover{background:var(--chip-hover);color:var(--ink)}
.wpfai .wpfai-btn:active{transform:scale(.98)}
.wpfai .wpfai-btn[disabled]{opacity:.45;cursor:default;transform:none}
.wpfai .wpfai-btn--primary{background:var(--tool)}
.wpfai .wpfai-btn--primary:hover{background:var(--tool-hover)}
.wpfai .wpfai-btn--sm{height:36px;padding:0 16px;font-size:13px}
.wpfai .wpfai-btn--block{width:100%}
.wpfai .wpfai-btn--danger:hover{background:var(--fail-soft);color:var(--fail-text)}
.wpfai-tag{display:inline-flex;align-items:center;height:24px;padding:0 10px;border-radius:999px;font-size:12px;font-weight:700;background:var(--tool-tint);color:var(--tool-text);white-space:nowrap}
.wpfai-notice{border-radius:16px;padding:14px 18px;font-size:14px}
.wpfai-notice.is-ok{background:var(--pass-soft);color:var(--pass-text)}
.wpfai-notice.is-err{background:var(--fail-soft);color:var(--fail-text)}
.wpfai-inset{background:var(--paper);border-radius:16px;padding:14px 18px;font-size:13px;color:var(--ink-2)}
.wpfai label{display:block;font-size:13px;font-weight:700;margin:0 0 6px}
.wpfai .wpfai-input{width:100%;max-width:none;height:52px;border:2px solid var(--ink-3);background:var(--card);border-radius:14px;padding:0 14px;font-size:16px;color:var(--ink);box-shadow:none}
.wpfai .wpfai-input:focus{border-color:var(--tool);outline:none;box-shadow:none}
.wpfai .wpfai-input--code{font-size:24px;letter-spacing:.3em;text-align:center}
.wpfai-form{display:flex;flex-direction:column;gap:12px}
.wpfai-connect{max-width:480px;margin:24px auto 0;width:100%;display:flex;flex-direction:column;gap:16px}
.wpfai-main:has(.wpfai-connect)>.wpfai-notice{max-width:480px;width:100%;margin:0 auto}
.wpfai-connect .wpfai-card{padding:32px;gap:16px}
.wpfai-connect .wpfai-head{align-items:center;text-align:center}
.wpfai-links{display:flex;gap:12px;justify-content:center;font-size:13px}
.wpfai-links a{color:var(--ink-3)}
@media (max-width:782px){.wpfai-main{padding:20px 16px 40px}.wpfai-bar{grid-template-columns:1fr;justify-items:center;padding:12px 16px}.wpfai-brand,.wpfai-bar__end{grid-column:1;justify-self:center}.wpfai .wpfai-h1{font-size:28px}.wpfai-big{font-size:48px}.wpfai-card{padding:20px}}
CSS;
	}

	/**
	 * Renders the screen.
	 */
	public static function render() {
		if ( ! Rest::can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage WPFactory AI.', 'wpfactory-ai' ) );
		}
		$client      = wpfactory_ai();
		$info        = $client->connection();
		$account_url = $client->endpoint() . '/account';

		echo '<div class="wrap wpfactory-ai wpfai">';
		echo '<hr class="wp-header-end">';
		echo '<div class="wpfai-bar"><div class="wpfai-brand">';
		echo self::logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses() in logo().
		echo '<b>' . esc_html__( 'AI Credits', 'wpfactory-ai' ) . '</b></div>';
		if ( $info['connected'] ) {
			echo '<div class="wpfai-bar__end"><a class="wpfai-btn wpfai-btn--sm" href="' . esc_url( $account_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Your account', 'wpfactory-ai' ) . ' &#8599;</a></div>';
		}
		echo '</div><div class="wpfai-main">';
		echo '<div class="wpfactory-ai-message wpfai-notice" role="status" hidden><p></p></div>';
		// Back from the Stripe page: only changes which note is shown.
		$back = isset( $_GET['wpfactory_ai_checkout'] ) ? sanitize_key( wp_unslash( $_GET['wpfactory_ai_checkout'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, no action.
		if ( 'done' === $back ) {
			echo '<div class="wpfai-notice is-ok" role="status">' . esc_html__( 'Thank you! Your credits are added within a few seconds. Reload this page if you do not see them yet.', 'wpfactory-ai' ) . '</div>';
		} elseif ( 'cancelled' === $back ) {
			echo '<div class="wpfai-notice is-ok" role="status">' . esc_html__( 'The payment was cancelled. Nothing was charged.', 'wpfactory-ai' ) . '</div>';
		}
		if ( $info['connected'] ) {
			self::render_connected( $client, $info, $account_url );
		} else {
			self::render_connect( $info );
		}
		echo '</div></div>';
	}

	/**
	 * Connected: credits, usage, buy, disconnect.
	 *
	 * @param Client              $client      Client.
	 * @param array<string,mixed> $info        Connection.
	 * @param string              $account_url The account page on the server.
	 */
	private static function render_connected( $client, array $info, $account_url ) {
		$products = $client->products();

		// The big number is what this site can spend: paid credits plus each plugin's free credits.
		$paid  = null;
		$free  = 0;
		$frees = array();
		$until = '';
		foreach ( $products as $id => $product ) {
			$b = $client->balance( $id, true );
			if ( is_wp_error( $b ) ) {
				continue;
			}
			$paid = (int) $b['paid'];
			if ( (int) $b['free'] > 0 ) {
				$free        += (int) $b['free'];
				$frees[ $id ] = (int) $b['free'];
				$until        = date_i18n( get_option( 'date_format' ), (int) strtotime( (string) $b['free_expires_at'] ) - 1 );
			}
		}

		echo '<div class="wpfai-head"><span class="wpfai-kicker">' . esc_html__( 'AI Credits', 'wpfactory-ai' ) . '</span>';
		echo '<h1 class="wpfai-h1">' . esc_html__( 'Credits for AI in your WPFactory plugins.', 'wpfactory-ai' ) . '</h1>';
		/* translators: %s: email address */
		echo '<p class="wpfai-lede">' . wp_kses( sprintf( __( 'This site is connected to WPFactory AI as %s. Paid credits work in every WPFactory plugin on every site you connect with this email.', 'wpfactory-ai' ), '<b>' . esc_html( (string) $info['email'] ) . '</b>' ), array( 'b' => array() ) ) . '</p></div>';

		echo '<div class="wpfai-two"><div class="wpfai-col">';

		// What this site can spend.
		echo '<div class="wpfai-card wpfai-card--ink"><div class="wpfai-hero"><div><span class="wpfai-kicker">' . esc_html__( 'This site can spend', 'wpfactory-ai' ) . '</span>';
		echo '<p class="wpfai-big wpfai-num">' . esc_html( null === $paid ? '…' : number_format_i18n( $paid + $free ) ) . '</p></div><div class="wpfai-hero__side">';
		if ( null === $paid ) {
			echo '<p class="wpfai-text">' . esc_html__( 'WPFactory AI cannot be reached right now. Reload the page in a minute.', 'wpfactory-ai' ) . '</p>';
		} else {
			/* translators: %s: number of credits */
			echo '<p class="wpfai-text">' . esc_html( sprintf( _n( '%s paid credit, for every WPFactory plugin on every site of this account. Paid credits never expire.', '%s paid credits, for every WPFactory plugin on every site of this account. Paid credits never expire.', $paid, 'wpfactory-ai' ), number_format_i18n( $paid ) ) ) . '</p>';
			foreach ( $frees as $id => $n ) {
				/* translators: 1: number of credits, 2: plugin name, 3: date */
				echo '<p class="wpfai-small">' . esc_html( sprintf( __( '%1$s free credits for %2$s, until %3$s', 'wpfactory-ai' ), number_format_i18n( $n ), $products[ $id ]['name'], $until ) ) . '</p>';
			}
			echo '<p class="wpfai-small">' . esc_html__( '1 credit = 1 US cent. Free credits are used first.', 'wpfactory-ai' ) . '</p>';
		}
		echo '</div></div></div>';

		// Buy.
		echo '<div class="wpfai-card" id="wpfactory-ai-buy"><div class="wpfai-head"><span class="wpfai-kicker">' . esc_html__( 'Buy credits', 'wpfactory-ai' ) . '</span>';
		echo '<h2 class="wpfai-h2">' . esc_html__( 'Top up once, use them everywhere', 'wpfactory-ai' ) . '</h2></div>';
		$packs = $client->packs();
		if ( is_wp_error( $packs ) || empty( $packs['open'] ) ) {
			echo '<p class="wpfai-text">' . esc_html__( 'Buying credits opens soon.', 'wpfactory-ai' ) . '</p>';
		} else {
			echo '<div class="wpfai-packs">';
			foreach ( $packs['packs'] as $pack ) {
				$usd     = (int) $pack['usd'];
				$credits = (int) $pack['credits'];
				echo '<div class="wpfai-pack"><b>$' . esc_html( number_format_i18n( $usd ) ) . '</b>';
				/* translators: %s: number of credits */
				echo '<span class="wpfai-small">' . esc_html( sprintf( __( '%s credits', 'wpfactory-ai' ), number_format_i18n( $credits ) ) ) . '</span>';
				/* translators: 1: number of credits, 2: price in US dollars */
				echo '<button type="button" class="wpfai-btn wpfai-btn--primary wpfai-btn--sm wpfai-btn--block" data-wpfactory-ai-pack="' . esc_attr( (string) $usd ) . '" aria-label="' . esc_attr( sprintf( __( 'Buy %1$s credits for $%2$s', 'wpfactory-ai' ), number_format_i18n( $credits ), number_format_i18n( $usd ) ) ) . '">' . esc_html__( 'Buy', 'wpfactory-ai' ) . '</button></div>';
			}
			echo '</div>';
			echo '<p class="wpfai-small">' . esc_html__( 'Secure payment by Stripe. Paid credits never expire. Purchases are final.', 'wpfactory-ai' ) . '</p>';
		}
		echo '</div>';

		echo '</div><div class="wpfai-side">';

		// Usage this month, for the whole account.
		$usage = $client->usage();
		echo '<div class="wpfai-card"><div class="wpfai-head"><span class="wpfai-kicker">' . esc_html__( 'This month', 'wpfactory-ai' ) . '</span>';
		echo '<h2 class="wpfai-h2">' . esc_html__( 'AI use', 'wpfactory-ai' ) . '</h2></div>';
		if ( is_wp_error( $usage ) || empty( $usage['by_product'] ) ) {
			echo '<p class="wpfai-text">' . esc_html__( 'No AI use yet this month.', 'wpfactory-ai' ) . '</p>';
		} else {
			echo '<div class="wpfai-rows">';
			foreach ( (array) $usage['by_product'] as $row ) {
				// Plugins not active on this site still count for the account: the server sends their names.
				$name = isset( $products[ $row['product'] ] ) ? $products[ $row['product'] ]['name'] : ( isset( $row['name'] ) ? (string) $row['name'] : (string) $row['product'] );
				/* translators: %s: number of AI requests */
				$calls = sprintf( _n( '%s request', '%s requests', (int) $row['calls'], 'wpfactory-ai' ), number_format_i18n( (int) $row['calls'] ) );
				/* translators: %s: number of credits */
				$spent = sprintf( _n( '%s credit', '%s credits', (int) $row['credits'], 'wpfactory-ai' ), number_format_i18n( (int) $row['credits'] ) );
				echo '<div class="wpfai-row"><div><p class="wpfai-row__title">' . esc_html( $name ) . '</p><p class="wpfai-small">' . esc_html( $calls ) . '</p></div>';
				echo '<span class="wpfai-num">' . esc_html( $spent ) . '</span></div>';
			}
			echo '</div>';
		}
		echo '</div>';

		// The account: all sites, purchases, and this site's connection.
		echo '<div class="wpfai-card"><div class="wpfai-head"><span class="wpfai-kicker">' . esc_html__( 'Account', 'wpfactory-ai' ) . '</span>';
		echo '<h2 class="wpfai-h2">' . esc_html__( 'Sites and purchases', 'wpfactory-ai' ) . '</h2></div>';
		/* translators: %s: web address of the WPFactory AI account page, like ai.wpfactory.com */
		echo '<p class="wpfai-text">' . esc_html( sprintf( __( 'See every site of this account, usage by month and your purchases on %s. Sign in with the same email.', 'wpfactory-ai' ), (string) wp_parse_url( $account_url, PHP_URL_HOST ) ) ) . '</p>';
		echo '<div class="wpfai-rows"><div class="wpfai-row"><a class="wpfai-btn wpfai-btn--sm" href="' . esc_url( $account_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open your account', 'wpfactory-ai' ) . ' &#8599;</a>';
		echo '<button type="button" class="wpfai-btn wpfai-btn--sm wpfai-btn--danger" data-wpfactory-ai="disconnect">' . esc_html__( 'Disconnect this site', 'wpfactory-ai' ) . '</button></div></div>';
		echo '</div>';

		echo '</div></div>';
	}

	/**
	 * Not connected: email, then the code.
	 *
	 * @param array<string,mixed> $info Connection.
	 */
	private static function render_connect( array $info ) {
		echo '<div class="wpfai-connect">';
		if ( ! empty( $info['address_changed'] ) ) {
			echo '<div class="wpfai-notice is-err">' . esc_html__( 'This site\'s address changed since it was connected. Connect it again to use AI credits here.', 'wpfactory-ai' ) . '</div>';
		} elseif ( ! empty( $info['needs_reconnect'] ) ) {
			echo '<div class="wpfai-notice is-err">' . esc_html__( 'The connection to WPFactory AI ended. Connect again to keep using AI credits.', 'wpfactory-ai' ) . '</div>';
		}
		echo '<div class="wpfai-card"><div class="wpfai-head"><span class="wpfai-kicker">' . esc_html__( 'AI Credits', 'wpfactory-ai' ) . '</span>';
		echo '<h1 class="wpfai-h2">' . esc_html__( 'Connect this site to WPFactory AI', 'wpfactory-ai' ) . '</h1>';
		echo '<p class="wpfai-text">' . esc_html__( 'Use AI in your WPFactory plugins with credits, without an AI account or key of your own. Free and Pro plugins alike. 1 credit = 1 US cent, and every account gets free credits each month.', 'wpfactory-ai' ) . '</p></div>';
		$email = '' !== (string) $info['pending_email'] ? (string) $info['pending_email'] : (string) $info['email'];
		if ( '' === $email ) {
			$user  = wp_get_current_user();
			$email = (string) $user->user_email;
		}
		echo '<form data-wpfactory-ai="email" class="wpfai-form">';
		echo '<div><label for="wpfactory-ai-email">' . esc_html__( 'Email', 'wpfactory-ai' ) . '</label>';
		echo '<input type="email" id="wpfactory-ai-email" name="email" class="wpfai-input" required autocomplete="email" value="' . esc_attr( $email ) . '"></div>';
		echo '<button type="submit" class="wpfai-btn wpfai-btn--primary wpfai-btn--block">' . esc_html__( 'Email me a code', 'wpfactory-ai' ) . '</button>';
		echo '</form>';
		echo '<form data-wpfactory-ai="code" class="wpfai-form" hidden>';
		echo '<input type="hidden" name="email" value="">';
		echo '<div><label for="wpfactory-ai-code">' . esc_html__( '6-digit code', 'wpfactory-ai' ) . '</label>';
		echo '<input type="text" id="wpfactory-ai-code" name="code" class="wpfai-input wpfai-input--code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="000000"></div>';
		echo '<button type="submit" class="wpfai-btn wpfai-btn--primary wpfai-btn--block">' . esc_html__( 'Connect', 'wpfactory-ai' ) . '</button>';
		echo '</form></div>';
		echo '<div class="wpfai-inset">' . esc_html__( 'Nothing is sent to WPFactory until you connect. After that, only what a plugin asks the AI is sent, and WPFactory AI keeps numbers only (tokens, credits, time), never your content.', 'wpfactory-ai' ) . '</div>';
		$terms   = (string) apply_filters( 'wpfactory_ai_terms_url', 'https://wpfactory.com/terms-conditions/#wpfactory-ai' );
		$privacy = (string) apply_filters( 'wpfactory_ai_privacy_url', 'https://wpfactory.com/privacy-policy/#wpfactory-ai' );
		if ( '' !== $terms || '' !== $privacy ) {
			echo '<p class="wpfai-links">';
			if ( '' !== $terms ) {
				echo '<a href="' . esc_url( $terms ) . '" target="_blank" rel="noopener">' . esc_html__( 'Terms', 'wpfactory-ai' ) . '</a>';
			}
			if ( '' !== $privacy ) {
				echo '<a href="' . esc_url( $privacy ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy', 'wpfactory-ai' ) . '</a>';
			}
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * The connect script (vanilla JavaScript, no build step).
	 *
	 * @return string
	 */
	private static function script() {
		return <<<'JS'
(function () {
	var root = document.querySelector('.wpfactory-ai');
	if (!root || !window.wp || !wp.apiFetch) { return; }
	var box = root.querySelector('.wpfactory-ai-message');
	function say(text, bad) {
		box.querySelector('p').textContent = text;
		box.className = 'wpfactory-ai-message wpfai-notice ' + (bad ? 'is-err' : 'is-ok');
		box.hidden = false;
		box.scrollIntoView({ block: 'nearest' });
	}
	function post(path, data) {
		return wp.apiFetch({ path: '/wpfactory-ai/v1/' + path, method: 'POST', data: data || {} });
	}
	root.addEventListener('submit', function (e) {
		var form = e.target.closest('form[data-wpfactory-ai]');
		if (!form) { return; }
		e.preventDefault();
		var button = form.querySelector('button');
		button.disabled = true;
		var step = form.getAttribute('data-wpfactory-ai');
		var done = step === 'email'
			? post('connect/start', { email: form.email.value.trim() }).then(function () {
				var code = root.querySelector('form[data-wpfactory-ai="code"]');
				code.email.value = form.email.value.trim();
				code.hidden = false;
				code.code.focus();
				say(window.wpfactoryAi.sent.replace('%s', form.email.value.trim()));
			})
			: post('connect/verify', { email: form.email.value, code: form.code.value }).then(function () {
				window.location.reload();
			});
		done.catch(function (err) { say((err && err.message) || window.wpfactoryAi.error, true); })
			.then(function () { button.disabled = false; });
	});
	root.addEventListener('click', function (e) {
		var pack = e.target.closest('[data-wpfactory-ai-pack]');
		if (!pack) { return; }
		root.querySelectorAll('[data-wpfactory-ai-pack]').forEach(function (b) { b.disabled = true; });
		post('checkout', { pack: parseInt(pack.getAttribute('data-wpfactory-ai-pack'), 10) })
			.then(function (res) { window.location.href = res.url; })
			.catch(function (err) {
				say((err && err.message) || window.wpfactoryAi.error, true);
				root.querySelectorAll('[data-wpfactory-ai-pack]').forEach(function (b) { b.disabled = false; });
			});
	});
	root.addEventListener('click', function (e) {
		var button = e.target.closest('[data-wpfactory-ai="disconnect"]');
		if (!button || !window.confirm(window.wpfactoryAi.disconnect)) { return; }
		button.disabled = true;
		post('disconnect').then(function () { window.location.reload(); })
			.catch(function (err) { say((err && err.message) || window.wpfactoryAi.error, true); button.disabled = false; });
	});
})();
JS;
	}
}

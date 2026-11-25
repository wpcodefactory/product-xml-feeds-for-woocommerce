# wpfactory/wpfactory-ai

The WordPress side of WPFactory AI (https://ai.wpfactory.com): one site connection and one credits wallet for every WPFactory plugin that uses AI, free and Pro alike. Plugins bundle this library; when several bundle different versions, the newest copy loads, once.

Server: `MCP AI/wpfactory-ai-server` (API contract in its README).

## Add it to a plugin

```json
{
  "repositories": [ { "type": "vcs", "url": "https://github.com/wpcodefactory/wpfactory-ai" } ],
  "require": { "wpfactory/wpfactory-ai": "^1.0" }
}
```

Composer loads `wpfactory-ai.php` through its "files" autoload, so requiring the plugin's `vendor/autoload.php` is enough (on file load or on `plugins_loaded`). Without Composer, `require_once` the bundled `wpfactory-ai.php`. Then, on `init`:

```php
wpfactory_ai()->register_product( 'helpdesk', array( 'name' => 'Helpdesk' ) );
```

Product ids the server knows: `ai-assistant-mcp`, `helpdesk`, `image-generator`.

## Use it

```php
$ai = wpfactory_ai();

if ( ! $ai->is_connected() ) {
	// Send the owner to $ai->admin_url() (WPFactory > AI Credits), or build your own form on
	// $ai->start_connect( $email ) and $ai->finish_connect( $email, $code ).
}

$r = $ai->call(
	'helpdesk',
	array(                                    // Anthropic Messages API body, as is
		'model'      => 'claude-haiku-5-5',
		'max_tokens' => 1000,
		'system'     => 'You draft replies for a WooCommerce store.',
		'messages'   => array( array( 'role' => 'user', 'content' => $ticket_text ) ),
	),
	array( 'task' => 'reply-draft' )          // shows in usage by task
);
if ( is_wp_error( $r ) ) {
	// Plain, ready-to-show messages. Codes: wpfactory_ai_not_connected, wpfactory_ai_insufficient_credits
	// (data: estimate, balance), wpfactory_ai_rate_limit_error, wpfactory_ai_network, ... Nothing was charged.
	return $r;
}
$text       = $r['message']['content'][0]['text']; // the plain Anthropic message
$r['credits'];    // charged for this call
$r['balance'];    // left after it
$r['request_id']; // keep it to refund

$ai->refund( $r['request_id'] );           // once, within 30 minutes: a failed job or an undone change
$url = $ai->checkout( 10 );               // Stripe page for a $10 pack (1,000 credits); send the browser there
$ai->balance( 'helpdesk' );                // balance, paid, free, free_expires_at (cached 5 minutes)
$ai->usage();                              // this month, by plugin and task (numbers only)
echo $ai->pill( 'helpdesk' );              // "123 credits" link for a plugin header
```

Images (image models, Gemini):

```php
$r = $ai->image(
	'image-generator',
	array(
		'prompt'       => $prompt,                // 'model' is optional: the plugin's default
		'images'       => array( array( 'mime_type' => 'image/jpeg', 'data' => base64_encode( $bytes ) ) ), // 1 to 4, product photo first
		'aspect_ratio' => '4:5',                  // 1:1, 4:5, 3:2, 2:3, 16:9, 9:16, 4:3, 3:4, or leave out
	),
	array( 'task' => 'generate' )             // generate, wizard, bulk, automation
);
// WP_Error (the codes of call(), plus wpfactory_ai_image_blocked for a safety refusal; nothing charged), or
// array( images => array( array( mime_type, data ) ), text, model, request_id, credits, balance )
$ai->prices( 'image-generator' );          // images: model => credits per image, default, flat, purchase_grant (cached 5 minutes)
$ai->claim( 'image-generator' );           // once per email that bought the plugin on wpfactory.com: result granted, already_granted or not_purchased
```

AI Image is a paid plugin: no monthly free credits, a flat 18 credits per image (36 with the stronger model), and 150 images (2,700 credits) after `claim()` finds the purchase. Each input image is a JPEG, PNG or WebP of at most 7 MB, and the whole request at most 20 MB. One picture per call, 120 second timeout. Refunds by request id work as for text.

Notes:
- Use `(object) array()` where the API needs an empty JSON object (for example a tool schema with no properties).
- Models: `claude-haiku-5-5` (fast, cheap) and `claude-sonnet-5-5` (stronger). The server reserves the most a call can cost (`max_tokens` included), so smaller `max_tokens` lets low balances through.
- Hooks: `wpfactory_ai_connected`, `wpfactory_ai_disconnected`. Filters: `wpfactory_ai_endpoint` (local server), `wpfactory_ai_capability` (default `manage_options`), `wpfactory_ai_menu_parent`, `wpfactory_ai_buy_url`, `wpfactory_ai_terms_url`, `wpfactory_ai_privacy_url`.
- The connection is shared. A plugin's uninstall must not delete the `wpfactory_ai_connection` option while other WPFactory AI plugins remain.
- A database copied to another address (a staging clone) does not reuse the connection; that site connects on its own.

## What it adds to a site

- WPFactory > AI Credits, or Settings > AI Credits when no plugin made the WPFactory menu (once a plugin registered a product): connect with an email and a 6-digit code, credits, usage per plugin this month, Buy credits, Disconnect. Its address is `admin.php?page=wpfactory-ai` under either menu; the old `options-general.php?page=wpfactory-ai` redirects there.
- REST routes `wpfactory-ai/v1/*` for that screen (administrators only), and the public `wpfactory-ai/v1/site-check`, which answers the server's one-time challenge with an HMAC so the server knows the site really runs at its address before the free monthly credits. It reveals nothing else.

## wordpress.org rules

- Nothing is sent until an administrator connects the site. No tracking, no updater, no remote code.
- Every plugin that bundles the library lists the service in its readme. Paste this into `== External services ==`:

> This plugin can use WPFactory AI (https://ai.wpfactory.com), a service by WPFactory, for its AI features, paid with WPFactory credits. Nothing is sent until a site administrator connects the site on the AI Credits screen with an email address and a 6-digit code sent to it. When connecting, the site address and that email address are sent. After that, the text of each AI request the plugin makes is sent when a user starts it; WPFactory AI passes it to Anthropic, its AI provider, to produce the answer. WPFactory AI keeps only numbers about each request (tokens, credits, time), never its content. Terms: https://wpfactory.com/terms-conditions/#wpfactory-ai Privacy: https://wpfactory.com/privacy-policy/#wpfactory-ai

## Tests

```sh
php tests/versions-test.php                                   # newest copy wins (no WordPress needed)
wp eval-file tests/wp-integration.php connect <email>         # real site, local server, Mailpit
php bin/admin.php credits:add <email> 100                     # on the server
wp eval-file tests/wp-integration.php spend
```

Coding standards: WordPress-Extra and Plugin Check's rule sets pass with no errors.

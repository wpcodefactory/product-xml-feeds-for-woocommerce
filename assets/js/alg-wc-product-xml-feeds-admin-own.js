/**
 * alg-wc-product-xml-feeds-admin-own.js
 *
 * @version 3.1.0
 * @since   2.7.10
 *
 * @author  WPFactory
 */

/**
 * Ajax xml feed generation.
 *
 * @version 3.1.0
 */
jQuery( document ).ready( function ( $ ) {
	$( 'body' ).on( 'click', 'a.generate_feed_by_ajax', function ( e ) {
		e.preventDefault();

		$( '#alg-wc-xml-feed-overlay-id' ).show();
		$( '#alg-wc-xml-feed-bar-percentage' ).width( '0%' );
		$( '.alg-wc-xml-feed-file-download-per-text' ).html( '0%' );

		var href = $( this ).attr( 'href' );
		var withoutHash = href.substr( 1, href.length );
		var params = new URLSearchParams( withoutHash.split( '?' )[1] );

		alg_wc_product_xml_feeds_admin_ajax_object.file_num = params.get( 'alg_create_products_xml' );

		var requestdata = {
			'action': 'alg_wc_product_xml_feeds_admin_product_ajax_feed_generation_start',
			'file_num': alg_wc_product_xml_feeds_admin_ajax_object.file_num,
			'nonce': alg_wc_product_xml_feeds_admin_ajax_object.nonce
		};

		$.ajax( {
			type: "POST",
			url: woocommerce_admin.ajax_url,
			data: requestdata,
			success: function ( response ) {
				response = response.trim();
				response = $.parseJSON( response );
				if ( response.success ) {
					recursive_ajax_feed_generation( response.total_page, 1, response.file_path, response.file_url );
					$( '#alg-wc-xml-feed-bar-percentage' ).width( response.progress + '%' );
					$( '.alg-wc-xml-feed-file-download-per-text' ).html( parseInt( response.progress ) + '%' );
				}
			},
		} );

	} );

	/**
	 * recursive_ajax_feed_generation.
	 */
	function recursive_ajax_feed_generation( totalpage, currentpage, filepath, file_url ) {
		var filepath = filepath.replace( /\/\//g, "/" );
		var requestdata = {
			'action': 'alg_wc_product_xml_feeds_admin_product_ajax_feed_generation',
			'file_path': filepath,
			'file_url': file_url,
			'total_page': totalpage,
			'current_page': currentpage,
			'file_num': alg_wc_product_xml_feeds_admin_ajax_object.file_num,
			'nonce': alg_wc_product_xml_feeds_admin_ajax_object.nonce
		};
		$.ajax( {
			type: "POST",
			url: woocommerce_admin.ajax_url,
			data: requestdata,
			success: function ( response ) {
				response = response.trim();
				response = $.parseJSON( response );
				if ( response.is_end ) {
					location.reload();
					$( '#alg-wc-overlay-id' ).hide();
				} else {
					var crpage = parseInt( response.current_page ) + 1;
					recursive_ajax_feed_generation( response.total_page, crpage, response.file_path, response.file_url );
				}
				if ( response.success ) {
					$( '#alg-wc-xml-feed-bar-percentage' ).width( response.progress + '%' );
					$( '.alg-wc-xml-feed-file-download-per-text' ).html( parseInt( response.progress ) + '%' );
				}
			},
		} );
	}
} );

/**
 * Fields filter.
 *
 * @version 3.1.0
 * @since 2.7.10
 */
jQuery( document ).ready( function ( $ ) {
	$( '.alg-wc-product-xml-feeds-products' ).select2( {
		ajax: {
			type: 'POST',
			url: woocommerce_admin.ajax_url,
			dataType: 'json',
			data: function ( params ) {
				var query = {
					search: params.term,
					type: 'public',
					action: 'alg_wc_product_xml_feeds_get_products_response'
				}

				// Query parameters will be ?search=[term]&type=public
				return query;
			},
			processResults: function ( data ) {
				return {
					results: data
				};
			},
		},
		minimumInputLength: 3
	} );

	$( '.alg-wc-product-xml-feeds-cats' ).select2( {
		ajax: {
			type: 'POST',
			url: woocommerce_admin.ajax_url,
			dataType: 'json',
			data: function ( params ) {
				var query = {
					search: params.term,
					type: 'public',
					action: 'alg_wc_product_xml_feeds_get_cats_response'
				}

				// Query parameters will be ?search=[term]&type=public
				return query;
			},
			processResults: function ( data ) {
				return {
					results: data
				};
			},
		},
		minimumInputLength: 3
	} );

	$( '.alg-wc-product-xml-feeds-tags' ).select2( {
		ajax: {
			type: 'POST',
			url: woocommerce_admin.ajax_url,
			dataType: 'json',
			data: function ( params ) {
				var query = {
					search: params.term,
					type: 'public',
					action: 'alg_wc_product_xml_feeds_get_tags_response'
				}

				// Query parameters will be ?search=[term]&type=public
				return query;
			},
			processResults: function ( data ) {
				return {
					results: data
				};
			},
		},
		minimumInputLength: 3
	} );
} );
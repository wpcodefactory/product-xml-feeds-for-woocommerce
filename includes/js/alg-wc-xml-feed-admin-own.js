/**
 * alg-wc-xml-feed-admin-own.js
 *
 * @version 2.7.10
 * @since   2.7.10
 * @author  WPFactory
 */

jQuery( document ).ready(function() {
					
				
	jQuery('body').on('click', 'a.generate_feed_by_ajax', function(e) {
		e.preventDefault();
		jQuery('#alg-wc-xml-feed-overlay-id').show();
		jQuery('#alg-wc-xml-feed-bar-percentage').width('0%');
		jQuery('.alg-wc-xml-feed-file-download-per-text').html('0%');
		var href = jQuery(this).attr('href');
		var withoutHash = href.substr(1, href.length);
		
		var requestdata = {
			'action'	: 'alg_wc_xml_feed_admin_product_ajax_feed_generation_start',
			'file_num'	: alg_wc_xml_feed_admin_own_js.file_num,
			'nonce'		: alg_wc_xml_feed_admin_own_js.nonce
		};
		jQuery.ajax( {
			type: "POST",
			url: woocommerce_admin.ajax_url,
			data: requestdata,
			success: function( response ) {
				response = response.trim();
				response = jQuery.parseJSON( response );
				if ( response.success ) {
					
					recursive_ajax_feed_generation( response.total_page, 1 , response.file_path, response.file_url );
					jQuery('#alg-wc-xml-feed-bar-percentage').width(response.progress + '%');
					jQuery('.alg-wc-xml-feed-file-download-per-text').html(parseInt(response.progress) + '%');
				}
				
			},
		} );
	});

});


function recursive_ajax_feed_generation( totalpage, currentpage, filepath, file_url ) {
		var filepath = filepath.replace(/\/\//g, "/");
		var requestdata = {
			'action'		: 	'alg_wc_xml_feed_admin_product_ajax_feed_generation',
			'file_path'		: 	filepath,
			'file_url'		: 	file_url,
			'total_page'	: 	totalpage,
			'current_page'	: 	currentpage,
			'file_num'		: 	alg_wc_xml_feed_admin_own_js.file_num,
			'nonce'			: 	alg_wc_xml_feed_admin_own_js.nonce
		};
		jQuery.ajax( {
			type: "POST",
			url: woocommerce_admin.ajax_url,
			data: requestdata,
			success: function( response ) {
				response = response.trim();
				response = jQuery.parseJSON( response );
				if(response.is_end) {
					// location.href = response.file_url;
					// window.open(response.file_url, '_blank');
					location.reload();
					jQuery('#alg-wc-overlay-id').hide();
				} else {
					var crpage = parseInt(response.current_page) + 1;
					recursive_ajax_feed_generation(response.total_page, crpage, response.file_path, response.file_url);
				}
				if(response.success) {
					jQuery('#alg-wc-xml-feed-bar-percentage').width(response.progress + '%');
					jQuery('.alg-wc-xml-feed-file-download-per-text').html(parseInt(response.progress) + '%');
				}
				
			},
		} );
}
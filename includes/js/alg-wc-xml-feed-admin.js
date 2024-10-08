/**
 * alg-wc-xml-feed-admin.js
 *
 * @version 2.8.0
 * @since   2.8.0
 * @author  WPFactory
 */

jQuery( document ).ready( function ( $ ) {
	// Add button to each element with the class 'alg-wc-xml-feed-shortcode-field'.
	$( '.alg-wc-xml-feed-shortcode-field' ).each( function () {
		// Find the closest ancestor element and add a class to it
		const shortcode_closest_element = $( this ).parents().first().addClass( 'alg-wc-xml-feed-shortcode-wrap' );

		// Create the link element
		let link = $( '<a>', {
			href: '#',
			title: alg_wc_xml_feed_admin_js.shortcodes_text,
			class: 'alg-wc-xml-feed-shortcode-button button button-secondary',
			text: alg_wc_xml_feed_admin_js.shortcodes_text
		} );

		// Create the span element for the icon
		let icon = $( '<span>', {
			class: 'dashicons dashicons-arrow-down-alt2',
		} );

		// Append the icon to the link
		link.append( icon );

		// Append the link to the selected element
		shortcode_closest_element.append( link );
	} );

	// Define the content to append.
	let shortcodes_list = alg_wc_xml_feed_admin_js.shortcodes;

	const shortcode_list_class = '.alg-wc-xml-feed-shortcode-list';

	// Click event on shortcode button.
	$( document ).on( 'click', '.alg-wc-xml-feed-shortcode-button', function ( e ) {
		e.preventDefault();

		let container = $( this ).closest( '.alg-wc-xml-feed-shortcode-wrap' );

		$( shortcode_list_class ).not( container.find( shortcode_list_class ) ).hide();

		if ( container.find( shortcode_list_class ).length ) {
			container.find( shortcode_list_class ).toggle();
		} else {
			container.append( `${shortcodes_list}` );
			container.find( shortcode_list_class ).toggle();
		}

		e.stopPropagation();
	} );

	// Click event for hiding shortcodes list when clicking outside.
	$( document ).on( 'click', function ( e ) {
		const shortcode_lists = $( shortcode_list_class );

		if ( ! shortcode_lists.is( e.target ) && 0 === shortcode_lists.has( e.target ).length ) {
			shortcode_lists.hide();
		}
	} );

	// Click and append shortcodes to the field or TinyMCE editor.
	$( document ).on( 'click', '.alg-wc-xml-feed-shortcode-list li', function () {
		const shortcode = $( this ).data( 'shortcode' );
		const field_container = $( this ).closest( '.alg-wc-xml-feed-shortcode-wrap' );
		const field_id = field_container.find( '.alg-wc-xml-feed-shortcode-field' ).attr( 'id' );
		const field = $( `#${field_id}` );

		if ( ! field.length ) {
			return;
		} // Ensure the field exists

		field.focus();

		// Get current cursor position
		const cursor_pos = field.prop( 'selectionStart' );

		// Use execCommand to insert text
		try {
			document.execCommand( 'insertText', false, shortcode );
		} catch ( error ) {
			// Fallback method if execCommand fails
			const field_value = field.val();
			field.val( field_value.substring( 0, cursor_pos ) + shortcode + field_value.substring( cursor_pos ) );
		}

		// Update cursor position after inserting the shortcode
		field.prop( 'selectionStart', cursor_pos + shortcode.length );
		field.prop( 'selectionEnd', cursor_pos + shortcode.length );
	} );

	// Filter items in the dropdown shortcode list.
	$( document ).on( 'keyup', '.alg-wc-xml-feed-shortcode-search', function () {
		let filter = $( this ).val().toLowerCase();
		$( this ).closest( '.alg-wc-xml-feed-shortcode-list' ).find( 'li' ).filter( function () {
			$( this ).toggle( $( this ).text().toLowerCase().indexOf( filter ) > - 1 );
		} );
	} );
} );
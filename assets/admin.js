( function( $ ) {
	'use strict';

	function initProductSearch() {
		var $field = $( '.wcmr-product-search' );

		if ( ! $field.length || ! $.fn.selectWoo ) {
			return;
		}

		$field.selectWoo( {
			ajax: {
				url: wcmrAdmin.ajaxUrl,
				dataType: 'json',
				delay: 250,
				data: function( params ) {
					return {
						action: 'wcmr_search_products',
						nonce: wcmrAdmin.searchNonce,
						term: params.term || ''
					};
				},
				processResults: function( data ) {
					return {
						results: data || []
					};
				},
				cache: true
			},
			width: '100%',
			placeholder: $field.data( 'placeholder' ) || wcmrAdmin.searchProducts,
			minimumInputLength: 1
		} );
	}

	function initTermSelects() {
		var $fields = $( '.wcmr-term-select' );

		if ( ! $fields.length || ! $.fn.selectWoo ) {
			return;
		}

		$fields.selectWoo( {
			width: '100%'
		} );
	}

	$( function() {
		initProductSearch();
		initTermSelects();
	} );
}( jQuery ) );

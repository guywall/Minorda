( function( $ ) {
	'use strict';

	function updateExplainer( $form, html ) {
		var $explainer = $form.find( '.wcmr-rule-explainer' );

		if ( ! $explainer.length ) {
			return;
		}

		if ( html ) {
			$explainer.html( html ).show();
			return;
		}

		var defaultHtml = $explainer.data( 'default-html' );

		if ( defaultHtml ) {
			$explainer.html( defaultHtml ).show();
			return;
		}

		$explainer.hide();
	}

	function updateQuantity( $form, quantity ) {
		if ( ! quantity ) {
			return;
		}

		var $qty = $form.find( 'input.qty' );

		if ( ! $qty.length ) {
			return;
		}

		$qty.val( quantity ).trigger( 'change' );
	}

	$( function() {
		var $variationForms = $( '.variations_form' );

		if ( ! $variationForms.length ) {
			return;
		}

		$variationForms.on( 'found_variation', function( event, variation ) {
			var $form = $( event.currentTarget );
			var minimumQuantity = variation && variation.minorda_min_quantity ? parseInt( variation.minorda_min_quantity, 10 ) : 0;
			var explainerHtml = variation && variation.minorda_quantity_explainer ? variation.minorda_quantity_explainer : '';

			updateQuantity( $form, minimumQuantity );
			updateExplainer( $form, explainerHtml );
		} );

		$variationForms.on( 'reset_data hide_variation', function( event ) {
			var $form = $( event.currentTarget );
			var $explainer = $form.find( '.wcmr-rule-explainer' );
			var defaultMinimum = parseInt( $explainer.data( 'default-min-quantity' ), 10 ) || 0;

			updateQuantity( $form, defaultMinimum );
			updateExplainer( $form, '' );
		} );
	} );
}( jQuery ) );

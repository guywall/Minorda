<?php
define( 'WCMR_TESTING', true );

require_once dirname( __DIR__ ) . '/includes/class-wcmr-rule-engine.php';

function wcmr_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$rules = array(
	array(
		'id'           => 'value-only',
		'min_quantity' => null,
		'min_value'    => 150.00,
	),
	array(
		'id'           => 'quantity-five',
		'min_quantity' => 5,
		'min_value'    => null,
	),
	array(
		'id'           => 'quantity-three',
		'min_quantity' => 3,
		'min_value'    => 90.00,
	),
);

$strictest = WCMR_Rule_Engine::select_strictest_rule( $rules );
wcmr_assert( 'quantity-five' === $strictest['id'], 'Expected highest quantity rule to win.' );

$evaluation = WCMR_Rule_Engine::evaluate_rule(
	array(
		array(
			'quantity' => 2,
			'subtotal' => 50.00,
		),
		array(
			'quantity' => 1,
			'subtotal' => 60.00,
		),
	),
	array(
		'min_quantity' => 5,
		'min_value'    => 120.00,
	)
);

wcmr_assert( false === $evaluation['passes'], 'Expected rule to fail when neither threshold is met.' );
wcmr_assert( 3 === $evaluation['quantity'], 'Expected quantity aggregation to equal 3.' );
wcmr_assert( 110.00 === $evaluation['subtotal'], 'Expected subtotal aggregation to equal 110.' );

$passing = WCMR_Rule_Engine::evaluate_rule(
	array(
		array(
			'quantity' => 1,
			'subtotal' => 130.00,
		),
	),
	array(
		'min_quantity' => 5,
		'min_value'    => 120.00,
	)
);

wcmr_assert( true === $passing['passes'], 'Expected either threshold to satisfy the rule.' );

echo "WCMR rule engine tests passed.\n";

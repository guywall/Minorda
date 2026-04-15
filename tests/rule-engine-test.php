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
		'max_quantity' => null,
		'max_value'    => null,
		'quantity_scope' => 'combined',
	),
	array(
		'id'           => 'quantity-five',
		'min_quantity' => 5,
		'min_value'    => null,
		'max_quantity' => null,
		'max_value'    => null,
		'quantity_scope' => 'combined',
	),
	array(
		'id'           => 'quantity-three',
		'min_quantity' => 3,
		'min_value'    => 90.00,
		'max_quantity' => 10,
		'max_value'    => null,
		'quantity_scope' => 'combined',
	),
	array(
		'id'           => 'quantity-max-two',
		'min_quantity' => null,
		'min_value'    => null,
		'max_quantity' => 2,
		'max_value'    => null,
		'quantity_scope' => 'combined',
	),
);

$strictest = WCMR_Rule_Engine::select_strictest_rule( $rules );
wcmr_assert( 'quantity-max-two' === $strictest['id'], 'Expected smallest quantity ceiling to win when maximums exist.' );

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
		'max_quantity' => null,
		'max_value'    => null,
		'quantity_scope' => 'combined',
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
		'max_quantity' => null,
		'max_value'    => null,
		'quantity_scope' => 'combined',
	)
);

wcmr_assert( true === $passing['passes'], 'Expected either threshold to satisfy the rule.' );

$range_pass = WCMR_Rule_Engine::evaluate_rule(
	array(
		array(
			'quantity' => 4,
			'subtotal' => 90.00,
		),
	),
	array(
		'min_quantity' => 2,
		'max_quantity' => 5,
		'min_value'    => null,
		'max_value'    => null,
		'quantity_scope' => 'combined',
	)
);

wcmr_assert( true === $range_pass['passes'], 'Expected quantity within min and max to pass.' );

$range_fail = WCMR_Rule_Engine::evaluate_rule(
	array(
		array(
			'quantity' => 6,
			'subtotal' => 90.00,
		),
	),
	array(
		'min_quantity' => 2,
		'max_quantity' => 5,
		'min_value'    => null,
		'max_value'    => null,
		'quantity_scope' => 'combined',
	)
);

wcmr_assert( false === $range_fail['passes'], 'Expected quantity above maximum to fail.' );

$value_cap_fail = WCMR_Rule_Engine::evaluate_rule(
	array(
		array(
			'quantity' => 1,
			'subtotal' => 150.00,
		),
	),
	array(
		'min_quantity' => null,
		'max_quantity' => null,
		'min_value'    => null,
		'max_value'    => 120.00,
		'quantity_scope' => 'combined',
	)
);

wcmr_assert( false === $value_cap_fail['passes'], 'Expected subtotal above maximum value to fail.' );

$per_product_fail = WCMR_Rule_Engine::evaluate_rule(
	array(
		array(
			'item_key'  => 'product:10',
			'quantity' => 3,
			'subtotal' => 60.00,
		),
		array(
			'item_key'  => 'product:20',
			'quantity' => 1,
			'subtotal' => 20.00,
		),
	),
	array(
		'min_quantity'   => null,
		'max_quantity'   => 2,
		'min_value'      => null,
		'max_value'      => null,
		'quantity_scope' => 'per_product',
	)
);

wcmr_assert( false === $per_product_fail['passes'], 'Expected per-product quantity cap to fail when one matched product exceeds the cap.' );

$per_product_pass = WCMR_Rule_Engine::evaluate_rule(
	array(
		array(
			'item_key'  => 'product:10',
			'quantity' => 2,
			'subtotal' => 40.00,
		),
		array(
			'item_key'  => 'product:20',
			'quantity' => 2,
			'subtotal' => 40.00,
		),
	),
	array(
		'min_quantity'   => null,
		'max_quantity'   => 2,
		'min_value'      => null,
		'max_value'      => null,
		'quantity_scope' => 'per_product',
	)
);

wcmr_assert( true === $per_product_pass['passes'], 'Expected per-product quantity cap to pass when each matched product stays within the cap.' );

echo "WCMR rule engine tests passed.\n";

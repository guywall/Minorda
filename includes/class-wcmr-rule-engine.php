<?php
/**
 * Pure rule engine helpers.
 *
 * @package WooCommerceMinimumRules
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WCMR_TESTING' ) ) {
	exit;
}

class WCMR_Rule_Engine {
	public static function select_strictest_rule( array $rules ) {
		if ( empty( $rules ) ) {
			return null;
		}

		$quantity_rules = array_values(
			array_filter(
				$rules,
				static function( $rule ) {
					return isset( $rule['min_quantity'] ) && null !== $rule['min_quantity'];
				}
			)
		);

		if ( ! empty( $quantity_rules ) ) {
			usort(
				$quantity_rules,
				static function( $left, $right ) {
					$quantity_compare = (int) $right['min_quantity'] <=> (int) $left['min_quantity'];

					if ( 0 !== $quantity_compare ) {
						return $quantity_compare;
					}

					return (float) ( $right['min_value'] ?? 0 ) <=> (float) ( $left['min_value'] ?? 0 );
				}
			);

			return $quantity_rules[0];
		}

		$value_rules = array_values( $rules );

		usort(
			$value_rules,
			static function( $left, $right ) {
				return (float) ( $right['min_value'] ?? 0 ) <=> (float) ( $left['min_value'] ?? 0 );
			}
		);

		return $value_rules[0];
	}

	public static function evaluate_rule( array $matched_items, array $rule, $price_decimals = 2 ) {
		$total_quantity = 0;
		$total_subtotal = 0.0;

		foreach ( $matched_items as $item ) {
			$total_quantity += isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			$total_subtotal += isset( $item['subtotal'] ) ? (float) $item['subtotal'] : 0.0;
		}

		$total_subtotal = round( $total_subtotal, (int) $price_decimals );

		return array(
			'quantity' => $total_quantity,
			'subtotal' => $total_subtotal,
			'passes'   => self::passes_rule( $rule, $total_quantity, $total_subtotal ),
		);
	}

	public static function passes_rule( array $rule, $quantity, $subtotal ) {
		$checks = array();

		if ( isset( $rule['min_quantity'] ) && null !== $rule['min_quantity'] ) {
			$checks[] = (int) $quantity >= (int) $rule['min_quantity'];
		}

		if ( isset( $rule['min_value'] ) && null !== $rule['min_value'] ) {
			$checks[] = (float) $subtotal >= (float) $rule['min_value'];
		}

		if ( empty( $checks ) ) {
			return true;
		}

		return in_array( true, $checks, true );
	}
}

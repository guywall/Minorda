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
					return ( isset( $rule['min_quantity'] ) && null !== $rule['min_quantity'] ) || ( isset( $rule['max_quantity'] ) && null !== $rule['max_quantity'] );
				}
			)
		);

		if ( ! empty( $quantity_rules ) ) {
			usort(
				$quantity_rules,
				static function( $left, $right ) {
					$left_min_quantity   = (int) ( $left['min_quantity'] ?? 0 );
					$right_min_quantity  = (int) ( $right['min_quantity'] ?? 0 );
					$left_max_quantity   = null !== ( $left['max_quantity'] ?? null ) ? (int) $left['max_quantity'] : PHP_INT_MAX;
					$right_max_quantity  = null !== ( $right['max_quantity'] ?? null ) ? (int) $right['max_quantity'] : PHP_INT_MAX;
					$left_quantity_width = PHP_INT_MAX === $left_max_quantity ? PHP_INT_MAX : max( 0, $left_max_quantity - $left_min_quantity );
					$right_quantity_width = PHP_INT_MAX === $right_max_quantity ? PHP_INT_MAX : max( 0, $right_max_quantity - $right_min_quantity );
					$bound_compare       = $left_quantity_width <=> $right_quantity_width;

					if ( 0 !== $bound_compare ) {
						return $bound_compare;
					}

					$max_compare = $left_max_quantity <=> $right_max_quantity;

					if ( 0 !== $max_compare ) {
						return $max_compare;
					}

					$min_compare = $right_min_quantity <=> $left_min_quantity;

					if ( 0 !== $min_compare ) {
						return $min_compare;
					}

					$left_min_value   = (float) ( $left['min_value'] ?? 0 );
					$right_min_value  = (float) ( $right['min_value'] ?? 0 );
					$left_max_value   = null !== ( $left['max_value'] ?? null ) ? (float) $left['max_value'] : INF;
					$right_max_value  = null !== ( $right['max_value'] ?? null ) ? (float) $right['max_value'] : INF;
					$left_value_width = INF === $left_max_value ? INF : max( 0, $left_max_value - $left_min_value );
					$right_value_width = INF === $right_max_value ? INF : max( 0, $right_max_value - $right_min_value );
					$value_bound_compare = $left_value_width <=> $right_value_width;

					if ( 0 !== $value_bound_compare ) {
						return $value_bound_compare;
					}

					$value_max_compare = $left_max_value <=> $right_max_value;

					if ( 0 !== $value_max_compare ) {
						return $value_max_compare;
					}

					return $right_min_value <=> $left_min_value;
				}
			);

			return $quantity_rules[0];
		}

		$value_rules = array_values( $rules );

		usort(
			$value_rules,
			static function( $left, $right ) {
				$left_min_value   = (float) ( $left['min_value'] ?? 0 );
				$right_min_value  = (float) ( $right['min_value'] ?? 0 );
				$left_max_value   = null !== ( $left['max_value'] ?? null ) ? (float) $left['max_value'] : INF;
				$right_max_value  = null !== ( $right['max_value'] ?? null ) ? (float) $right['max_value'] : INF;
				$left_value_width = INF === $left_max_value ? INF : max( 0, $left_max_value - $left_min_value );
				$right_value_width = INF === $right_max_value ? INF : max( 0, $right_max_value - $right_min_value );
				$bound_compare    = $left_value_width <=> $right_value_width;

				if ( 0 !== $bound_compare ) {
					return $bound_compare;
				}

				$max_compare = $left_max_value <=> $right_max_value;

				if ( 0 !== $max_compare ) {
					return $max_compare;
				}

				return $right_min_value <=> $left_min_value;
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
		$quantity_checks = array();
		$value_checks    = array();

		if ( isset( $rule['min_quantity'] ) && null !== $rule['min_quantity'] ) {
			$quantity_checks[] = (int) $quantity >= (int) $rule['min_quantity'];
		}

		if ( isset( $rule['max_quantity'] ) && null !== $rule['max_quantity'] ) {
			$quantity_checks[] = (int) $quantity <= (int) $rule['max_quantity'];
		}

		if ( isset( $rule['min_value'] ) && null !== $rule['min_value'] ) {
			$value_checks[] = (float) $subtotal >= (float) $rule['min_value'];
		}

		if ( isset( $rule['max_value'] ) && null !== $rule['max_value'] ) {
			$value_checks[] = (float) $subtotal <= (float) $rule['max_value'];
		}

		$quantity_passes = empty( $quantity_checks ) ? null : ! in_array( false, $quantity_checks, true );
		$value_passes    = empty( $value_checks ) ? null : ! in_array( false, $value_checks, true );

		if ( null !== $quantity_passes && null !== $value_passes ) {
			return $quantity_passes || $value_passes;
		}

		if ( null !== $quantity_passes ) {
			return $quantity_passes;
		}

		if ( null !== $value_passes ) {
			return $value_passes;
		}

		if ( empty( $quantity_checks ) && empty( $value_checks ) ) {
			return true;
		}

		return false;
	}
}

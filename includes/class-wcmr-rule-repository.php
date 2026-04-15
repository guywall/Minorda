<?php
/**
 * Rule persistence.
 *
 * @package WooCommerceMinimumRules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCMR_Rule_Repository {
	const OPTION_NAME = 'wcmr_rules';

	public function all() {
		$stored = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$rules = array();

		foreach ( $stored as $rule_id => $rule ) {
			$normalized = $this->normalize_rule( is_array( $rule ) ? $rule : array(), (string) $rule_id );

			if ( empty( $normalized['id'] ) ) {
				continue;
			}

			$rules[ $normalized['id'] ] = $normalized;
		}

		return $rules;
	}

	public function active() {
		return array_filter(
			$this->all(),
			static function( $rule ) {
				return ! empty( $rule['enabled'] );
			}
		);
	}

	public function get( $id ) {
		$rules = $this->all();

		return isset( $rules[ $id ] ) ? $rules[ $id ] : null;
	}

	public function save( array $rule ) {
		$rules     = $this->all();
		$rule_id   = ! empty( $rule['id'] ) ? sanitize_key( $rule['id'] ) : wp_generate_uuid4();
		$sanitized = $this->normalize_rule( $rule, $rule_id );

		$rules[ $rule_id ] = $sanitized;
		update_option( self::OPTION_NAME, $rules, false );

		return $sanitized;
	}

	public function delete( $id ) {
		$rules = $this->all();

		if ( ! isset( $rules[ $id ] ) ) {
			return;
		}

		unset( $rules[ $id ] );
		update_option( self::OPTION_NAME, $rules, false );
	}

	public function set_enabled( $id, $enabled ) {
		$rules = $this->all();

		if ( ! isset( $rules[ $id ] ) ) {
			return;
		}

		$rules[ $id ]['enabled'] = (bool) $enabled;
		update_option( self::OPTION_NAME, $rules, false );
	}

	protected function normalize_rule( array $rule, $rule_id ) {
		$product_ids = array();

		if ( ! empty( $rule['product_ids'] ) && is_array( $rule['product_ids'] ) ) {
			$product_ids = array_values(
				array_unique(
					array_filter(
						array_map( 'absint', $rule['product_ids'] )
					)
				)
			);
		}

		$taxonomy_terms = array();

		if ( ! empty( $rule['taxonomy_terms'] ) && is_array( $rule['taxonomy_terms'] ) ) {
			foreach ( $rule['taxonomy_terms'] as $taxonomy => $term_ids ) {
				if ( ! is_array( $term_ids ) ) {
					continue;
				}

				$clean_ids = array_values(
					array_unique(
						array_filter(
							array_map( 'absint', $term_ids )
						)
					)
				);

				if ( empty( $clean_ids ) ) {
					continue;
				}

				$taxonomy_terms[ sanitize_key( $taxonomy ) ] = $clean_ids;
			}
		}

		$min_quantity = null;
		if ( isset( $rule['min_quantity'] ) && '' !== $rule['min_quantity'] && null !== $rule['min_quantity'] ) {
			$min_quantity = absint( $rule['min_quantity'] );
			if ( $min_quantity <= 0 ) {
				$min_quantity = null;
			}
		}

		$min_value = null;
		if ( isset( $rule['min_value'] ) && '' !== $rule['min_value'] && null !== $rule['min_value'] ) {
			$min_value = (float) wc_format_decimal( wp_unslash( (string) $rule['min_value'] ) );
			if ( $min_value <= 0 ) {
				$min_value = null;
			}
		}

		$max_quantity = null;
		if ( isset( $rule['max_quantity'] ) && '' !== $rule['max_quantity'] && null !== $rule['max_quantity'] ) {
			$max_quantity = absint( $rule['max_quantity'] );
			if ( $max_quantity <= 0 ) {
				$max_quantity = null;
			}
		}

		$max_value = null;
		if ( isset( $rule['max_value'] ) && '' !== $rule['max_value'] && null !== $rule['max_value'] ) {
			$max_value = (float) wc_format_decimal( wp_unslash( (string) $rule['max_value'] ) );
			if ( $max_value <= 0 ) {
				$max_value = null;
			}
		}

		$quantity_scope = 'combined';
		if ( isset( $rule['quantity_scope'] ) ) {
			$scope = sanitize_key( (string) $rule['quantity_scope'] );
			if ( in_array( $scope, array( 'combined', 'per_product' ), true ) ) {
				$quantity_scope = $scope;
			}
		}

		return array(
			'id'             => sanitize_key( $rule_id ),
			'name'           => sanitize_text_field( $rule['name'] ?? '' ),
			'enabled'        => ! empty( $rule['enabled'] ),
			'product_ids'    => $product_ids,
			'taxonomy_terms' => $taxonomy_terms,
			'min_quantity'   => $min_quantity,
			'min_value'      => $min_value,
			'max_quantity'   => $max_quantity,
			'max_value'      => $max_value,
			'quantity_scope' => $quantity_scope,
		);
	}
}

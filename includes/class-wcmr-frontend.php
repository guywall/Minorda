<?php
/**
 * Storefront enforcement.
 *
 * @package WooCommerceMinimumRules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCMR_Frontend {
	protected $repository;

	public function __construct( WCMR_Rule_Repository $repository ) {
		$this->repository = $repository;
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );
	}

	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		unset( $variations );

		if ( ! $passed || $quantity <= 0 ) {
			return $passed;
		}

		$target_product_id = $variation_id ? (int) $variation_id : (int) $product_id;
		$matching_rules    = $this->get_matching_rules_for_product( $target_product_id );

		if ( empty( $matching_rules ) ) {
			return $passed;
		}

		$rule = WCMR_Rule_Engine::select_strictest_rule( $matching_rules );

		if ( ! $rule ) {
			return $passed;
		}

		$matched_items = $this->build_projected_matched_items( $rule, $target_product_id, (int) $quantity );
		$evaluation    = WCMR_Rule_Engine::evaluate_rule( $matched_items, $rule, wc_get_price_decimals() );

		if ( $evaluation['passes'] ) {
			return $passed;
		}

		wc_add_notice( $this->build_failure_message( $rule ), 'error' );

		return false;
	}

	protected function get_matching_rules_for_product( $product_id ) {
		$matching_rules = array();

		foreach ( $this->repository->active() as $rule ) {
			if ( $this->product_matches_rule( $product_id, $rule ) ) {
				$matching_rules[] = $rule;
			}
		}

		return $matching_rules;
	}

	protected function product_matches_rule( $product_id, array $rule ) {
		$candidate_ids = $this->get_candidate_product_ids( $product_id );

		if ( ! empty( $rule['product_ids'] ) && array_intersect( $candidate_ids, $rule['product_ids'] ) ) {
			return true;
		}

		if ( empty( $rule['taxonomy_terms'] ) ) {
			return false;
		}

		foreach ( $rule['taxonomy_terms'] as $taxonomy => $required_term_ids ) {
			$product_term_ids = array();

			foreach ( $candidate_ids as $candidate_id ) {
				$term_ids = wp_get_post_terms(
					$candidate_id,
					$taxonomy,
					array(
						'fields' => 'ids',
					)
				);

				if ( is_wp_error( $term_ids ) ) {
					continue;
				}

				$product_term_ids = array_merge( $product_term_ids, $term_ids );
			}

			$product_term_ids = array_unique( array_map( 'absint', $product_term_ids ) );

			if ( array_intersect( $product_term_ids, $required_term_ids ) ) {
				return true;
			}
		}

		return false;
	}

	protected function build_projected_matched_items( array $rule, $target_product_id, $quantity ) {
		$matched_items = array();
		$cart          = WC()->cart;

		if ( $cart ) {
			foreach ( $cart->get_cart() as $cart_item ) {
				$cart_item_product_id = ! empty( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : (int) $cart_item['product_id'];

				if ( ! $this->product_matches_rule( $cart_item_product_id, $rule ) ) {
					continue;
				}

				$matched_items[] = array(
					'quantity' => isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0,
					'subtotal' => isset( $cart_item['line_subtotal'] ) ? (float) $cart_item['line_subtotal'] : 0.0,
				);
			}
		}

		$product = wc_get_product( $target_product_id );

		if ( $product && $this->product_matches_rule( $target_product_id, $rule ) ) {
			$matched_items[] = array(
				'quantity' => (int) $quantity,
				'subtotal' => (float) wc_get_price_excluding_tax(
					$product,
					array(
						'qty' => $quantity,
					)
				),
			);
		}

		return $matched_items;
	}

	protected function get_candidate_product_ids( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array( (int) $product_id );
		}

		$ids = array( (int) $product->get_id() );

		if ( $product->is_type( 'variation' ) ) {
			$ids[] = (int) $product->get_parent_id();
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	protected function build_failure_message( array $rule ) {
		$requirements = array();

		if ( null !== $rule['min_quantity'] ) {
			$requirements[] = sprintf(
				__( 'a minimum quantity of %d', 'minorda' ),
				(int) $rule['min_quantity']
			);
		}

		if ( null !== $rule['min_value'] ) {
			$requirements[] = sprintf(
				__( 'a minimum value of %s', 'minorda' ),
				wp_strip_all_tags( wc_price( $rule['min_value'] ) )
			);
		}

		$requirements_text = 1 === count( $requirements )
			? $requirements[0]
			: implode( ' ' . __( 'or', 'minorda' ) . ' ', $requirements );

		if ( ! empty( $rule['name'] ) ) {
			return sprintf(
				__( 'You cannot add this item yet because rule "%1$s" requires %2$s for the matched products.', 'minorda' ),
				$rule['name'],
				$requirements_text
			);
		}

		return sprintf(
			__( 'You cannot add this item yet because the matched products require %s.', 'minorda' ),
			$requirements_text
		);
	}
}

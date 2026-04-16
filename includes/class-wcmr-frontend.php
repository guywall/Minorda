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
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'set_default_quantity_input' ), 10, 2 );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_rule_explainer' ), 15 );
		add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_rule_data' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_script(
			'wcmr-frontend',
			WCMR_PLUGIN_URL . 'assets/frontend.js',
			array( 'jquery' ),
			WCMR_VERSION,
			true
		);

		wp_enqueue_style(
			'wcmr-frontend',
			WCMR_PLUGIN_URL . 'assets/frontend.css',
			array(),
			WCMR_VERSION
		);
	}

	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		unset( $variations );

		if ( ! $passed || $quantity <= 0 ) {
			return $passed;
		}

		$target_product_id = $variation_id ? (int) $variation_id : (int) $product_id;
		$rule              = $this->get_strictest_rule_for_product( $target_product_id );

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

	public function set_default_quantity_input( $args, $product ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! $product instanceof WC_Product ) {
			return $args;
		}

		$rule = $this->get_strictest_rule_for_product( $product->get_id() );

		if ( ! $rule || null === $rule['min_quantity'] ) {
			return $args;
		}

		$args['input_value'] = max( 1, (int) $rule['min_quantity'] );

		return $args;
	}

	public function render_rule_explainer() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$rule         = $this->get_strictest_rule_for_product( $product->get_id() );
		$minimum      = $rule && null !== ( $rule['min_quantity'] ?? null ) ? (int) $rule['min_quantity'] : 0;
		$default_html = $rule ? $this->get_quantity_explainer_html( $rule ) : '';
		$style        = '' === $default_html ? ' style="display:none;"' : '';

		?>
		<p
			class="wcmr-rule-explainer"
			data-default-html="<?php echo esc_attr( $default_html ); ?>"
			data-default-min-quantity="<?php echo esc_attr( $minimum ); ?>"<?php echo $style; ?>
		>
			<?php echo wp_kses( $default_html, array( 'br' => array() ) ); ?>
		</p>
		<?php
	}

	public function add_variation_rule_data( $data, $product, $variation ) {
		unset( $product );

		if ( ! $variation instanceof WC_Product_Variation ) {
			return $data;
		}

		$rule = $this->get_strictest_rule_for_product( $variation->get_id() );

		$data['minorda_min_quantity']        = $rule && null !== ( $rule['min_quantity'] ?? null ) ? (int) $rule['min_quantity'] : 0;
		$data['minorda_quantity_explainer']  = $rule ? $this->get_quantity_explainer_html( $rule ) : '';

		return $data;
	}

	public function get_strictest_rule_for_product( $product_id ) {
		$matching_rules = $this->get_matching_rules_for_product( $product_id );

		if ( empty( $matching_rules ) ) {
			return null;
		}

		return WCMR_Rule_Engine::select_strictest_rule( $matching_rules );
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
					'item_key' => $this->get_quantity_item_key( $cart_item_product_id, $rule ),
					'quantity' => isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0,
					'subtotal' => isset( $cart_item['line_subtotal'] ) ? (float) $cart_item['line_subtotal'] : 0.0,
				);
			}
		}

		$product = wc_get_product( $target_product_id );

		if ( $product && $this->product_matches_rule( $target_product_id, $rule ) ) {
			$matched_items[] = array(
				'item_key' => $this->get_quantity_item_key( $target_product_id, $rule ),
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
		$requirements_text = $this->build_requirements_text( $rule );

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

	protected function get_quantity_explainer_html( array $rule ) {
		$lines = array();
		$per_product_suffix = 'per_product' === ( $rule['quantity_scope'] ?? 'combined' )
			? __( ' per product', 'minorda' )
			: '';

		if ( null !== ( $rule['min_quantity'] ?? null ) ) {
			$lines[] = sprintf(
				__( 'Minimum quantity: %1$d%2$s', 'minorda' ),
				(int) $rule['min_quantity'],
				$per_product_suffix
			);
		}

		if ( null !== ( $rule['max_quantity'] ?? null ) ) {
			$lines[] = sprintf(
				__( 'Maximum quantity: %1$d%2$s', 'minorda' ),
				(int) $rule['max_quantity'],
				$per_product_suffix
			);
		}

		return implode( '<br>', array_map( 'esc_html', $lines ) );
	}

	protected function build_requirements_text( array $rule ) {
		$requirements = array();
		$quantity_requirement = $this->build_quantity_requirement_text( $rule );
		$value_requirement    = $this->build_value_requirement_text( $rule );

		if ( '' !== $quantity_requirement ) {
			$requirements[] = $quantity_requirement;
		}

		if ( '' !== $value_requirement ) {
			$requirements[] = $value_requirement;
		}

		if ( empty( $requirements ) ) {
			return '';
		}

		if ( 1 === count( $requirements ) ) {
			return $requirements[0];
		}

		return implode( ' ' . __( 'or', 'minorda' ) . ' ', $requirements );
	}

	protected function build_quantity_requirement_text( array $rule ) {
		$has_min = null !== ( $rule['min_quantity'] ?? null );
		$has_max = null !== ( $rule['max_quantity'] ?? null );
		$per_product_suffix = 'per_product' === ( $rule['quantity_scope'] ?? 'combined' )
			? __( ' for each matched product', 'minorda' )
			: '';

		if ( $has_min && $has_max ) {
			return sprintf(
				__( 'a quantity between %1$d and %2$d%3$s', 'minorda' ),
				(int) $rule['min_quantity'],
				(int) $rule['max_quantity'],
				$per_product_suffix
			);
		}

		if ( $has_min ) {
			return sprintf(
				__( 'a minimum quantity of %1$d%2$s', 'minorda' ),
				(int) $rule['min_quantity'],
				$per_product_suffix
			);
		}

		if ( $has_max ) {
			return sprintf(
				__( 'a maximum quantity of %1$d%2$s', 'minorda' ),
				(int) $rule['max_quantity'],
				$per_product_suffix
			);
		}

		return '';
	}

	protected function get_quantity_item_key( $product_id, array $rule ) {
		$candidate_ids = $this->get_candidate_product_ids( $product_id );

		foreach ( (array) ( $rule['product_ids'] ?? array() ) as $target_product_id ) {
			if ( in_array( (int) $target_product_id, $candidate_ids, true ) ) {
				return 'product:' . (int) $target_product_id;
			}
		}

		$product = wc_get_product( $product_id );

		if ( $product && $product->is_type( 'variation' ) ) {
			return 'product:' . (int) $product->get_parent_id();
		}

		return 'product:' . (int) $product_id;
	}

	protected function build_value_requirement_text( array $rule ) {
		$has_min = null !== ( $rule['min_value'] ?? null );
		$has_max = null !== ( $rule['max_value'] ?? null );

		if ( $has_min && $has_max ) {
			return sprintf(
				__( 'a value between %1$s and %2$s', 'minorda' ),
				wp_strip_all_tags( wc_price( $rule['min_value'] ) ),
				wp_strip_all_tags( wc_price( $rule['max_value'] ) )
			);
		}

		if ( $has_min ) {
			return sprintf(
				__( 'a minimum value of %s', 'minorda' ),
				wp_strip_all_tags( wc_price( $rule['min_value'] ) )
			);
		}

		if ( $has_max ) {
			return sprintf(
				__( 'a maximum value of %s', 'minorda' ),
				wp_strip_all_tags( wc_price( $rule['max_value'] ) )
			);
		}

		return '';
	}
}

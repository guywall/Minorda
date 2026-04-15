<?php
/**
 * Admin UI.
 *
 * @package WooCommerceMinimumRules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCMR_Admin {
	const PAGE_SLUG = 'wcmr-minimum-rules';

	protected $repository;

	public function __construct( WCMR_Rule_Repository $repository ) {
		$this->repository = $repository;

		add_action( 'admin_menu', array( $this, 'register_menu' ), 40 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wcmr_search_products', array( $this, 'ajax_search_products' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Minorda', 'minorda' ),
			__( 'Minorda', 'minorda' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function handle_actions() {
		if ( ! $this->is_plugin_page_request() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$action = isset( $_GET['wcmr_action'] ) ? sanitize_key( wp_unslash( $_GET['wcmr_action'] ) ) : '';

		if ( 'save_rule' === $action && 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			$this->handle_save();
			return;
		}

		if ( ! in_array( $action, array( 'toggle', 'delete' ), true ) ) {
			return;
		}

		check_admin_referer( 'wcmr_rule_action' );

		$rule_id = isset( $_GET['rule_id'] ) ? sanitize_key( wp_unslash( $_GET['rule_id'] ) ) : '';

		if ( empty( $rule_id ) ) {
			$this->redirect_with_notice( 'missing_rule', 'error' );
		}

		if ( 'toggle' === $action ) {
			$rule = $this->repository->get( $rule_id );

			if ( ! $rule ) {
				$this->redirect_with_notice( 'missing_rule', 'error' );
			}

			$this->repository->set_enabled( $rule_id, empty( $rule['enabled'] ) );
			$this->redirect_with_notice( 'updated' );
		}

		$this->repository->delete( $rule_id );
		$this->redirect_with_notice( 'deleted' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'minorda' ) );
		}

		$current_rule = $this->get_current_rule();
		$taxonomies   = $this->get_product_taxonomies();
		$rules        = $this->repository->all();
		?>
		<div class="wrap wcmr-wrap">
			<h1><?php esc_html_e( 'Minorda', 'minorda' ); ?></h1>
			<?php $this->render_notices(); ?>

			<div class="wcmr-layout">
				<div class="wcmr-panel">
					<h2><?php echo $current_rule['id'] ? esc_html__( 'Edit Rule', 'minorda' ) : esc_html__( 'Add Rule', 'minorda' ); ?></h2>
					<form method="post" action="<?php echo esc_url( $this->build_page_url( array( 'wcmr_action' => 'save_rule' ) ) ); ?>">
						<?php wp_nonce_field( 'wcmr_save_rule' ); ?>
						<input type="hidden" name="rule_id" value="<?php echo esc_attr( $current_rule['id'] ); ?>">

						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="wcmr-name"><?php esc_html_e( 'Rule name', 'minorda' ); ?></label></th>
									<td>
										<input id="wcmr-name" name="name" type="text" class="regular-text" value="<?php echo esc_attr( $current_rule['name'] ); ?>" required>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Status', 'minorda' ); ?></th>
									<td>
										<label>
											<input name="enabled" type="checkbox" value="1" <?php checked( ! empty( $current_rule['enabled'] ) ); ?>>
											<?php esc_html_e( 'Enable this rule', 'minorda' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="wcmr-product-ids"><?php esc_html_e( 'Products', 'minorda' ); ?></label></th>
									<td>
										<select
											id="wcmr-product-ids"
											name="product_ids[]"
											class="wcmr-product-search"
											multiple="multiple"
											data-placeholder="<?php echo esc_attr__( 'Search products...', 'minorda' ); ?>"
										>
											<?php foreach ( $this->get_product_labels( $current_rule['product_ids'] ) as $product_id => $product_label ) : ?>
												<option value="<?php echo esc_attr( $product_id ); ?>" selected="selected"><?php echo esc_html( $product_label ); ?></option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Optional. Select one or more products that should trigger this rule.', 'minorda' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Taxonomy terms', 'minorda' ); ?></th>
									<td class="wcmr-taxonomy-grid">
										<?php foreach ( $taxonomies as $taxonomy ) : ?>
											<?php
											$selected_term_ids = $current_rule['taxonomy_terms'][ $taxonomy->name ] ?? array();
											$terms             = get_terms(
												array(
													'taxonomy'   => $taxonomy->name,
													'hide_empty' => false,
												)
											);
											?>
											<div class="wcmr-taxonomy-card">
												<label for="wcmr-taxonomy-<?php echo esc_attr( $taxonomy->name ); ?>">
													<strong><?php echo esc_html( $taxonomy->labels->singular_name ?: $taxonomy->label ); ?></strong>
												</label>
												<select
													id="wcmr-taxonomy-<?php echo esc_attr( $taxonomy->name ); ?>"
													name="taxonomy_terms[<?php echo esc_attr( $taxonomy->name ); ?>][]"
													class="wcmr-term-select"
													multiple="multiple"
												>
													<?php if ( ! is_wp_error( $terms ) ) : ?>
														<?php foreach ( $terms as $term ) : ?>
															<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, $selected_term_ids, true ) ); ?>>
																<?php echo esc_html( $term->name ); ?>
															</option>
														<?php endforeach; ?>
													<?php endif; ?>
												</select>
											</div>
										<?php endforeach; ?>
										<p class="description wcmr-full-width"><?php esc_html_e( 'Optional. Match any selected term within the chosen taxonomies.', 'minorda' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="wcmr-min-quantity"><?php esc_html_e( 'Minimum quantity', 'minorda' ); ?></label></th>
									<td>
										<input id="wcmr-min-quantity" name="min_quantity" type="number" class="small-text" min="1" step="1" value="<?php echo esc_attr( $current_rule['min_quantity'] ?? '' ); ?>">
										<p class="description"><?php esc_html_e( 'Optional. Positive whole number.', 'minorda' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="wcmr-min-value"><?php esc_html_e( 'Minimum value', 'minorda' ); ?></label></th>
									<td>
										<input id="wcmr-min-value" name="min_value" type="number" class="small-text" min="0" step="0.01" value="<?php echo esc_attr( null !== $current_rule['min_value'] ? wc_format_localized_price( $current_rule['min_value'] ) : '' ); ?>">
										<p class="description"><?php esc_html_e( 'Optional. Matched subtotal excluding tax, shipping, and fees.', 'minorda' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="wcmr-max-quantity"><?php esc_html_e( 'Maximum quantity', 'minorda' ); ?></label></th>
									<td>
										<input id="wcmr-max-quantity" name="max_quantity" type="number" class="small-text" min="1" step="1" value="<?php echo esc_attr( $current_rule['max_quantity'] ?? '' ); ?>">
										<p class="description"><?php esc_html_e( 'Optional. Positive whole number.', 'minorda' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="wcmr-max-value"><?php esc_html_e( 'Maximum value', 'minorda' ); ?></label></th>
									<td>
										<input id="wcmr-max-value" name="max_value" type="number" class="small-text" min="0" step="0.01" value="<?php echo esc_attr( null !== $current_rule['max_value'] ? wc_format_localized_price( $current_rule['max_value'] ) : '' ); ?>">
										<p class="description"><?php esc_html_e( 'Optional. Matched subtotal excluding tax, shipping, and fees.', 'minorda' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="wcmr-quantity-scope"><?php esc_html_e( 'Quantity rule scope', 'minorda' ); ?></label></th>
									<td>
										<select id="wcmr-quantity-scope" name="quantity_scope">
											<option value="combined" <?php selected( 'combined', $current_rule['quantity_scope'] ?? 'combined' ); ?>><?php esc_html_e( 'Across all matched products', 'minorda' ); ?></option>
											<option value="per_product" <?php selected( 'per_product', $current_rule['quantity_scope'] ?? 'combined' ); ?>><?php esc_html_e( 'Apply to each matched product', 'minorda' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Choose the second option when each selected product should have its own quantity limit instead of sharing one combined quantity limit.', 'minorda' ); ?></p>
									</td>
								</tr>
							</tbody>
						</table>

						<p class="submit">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save rule', 'minorda' ); ?></button>
							<a href="<?php echo esc_url( $this->build_page_url() ); ?>" class="button"><?php esc_html_e( 'Add new rule', 'minorda' ); ?></a>
						</p>
					</form>
				</div>

				<div class="wcmr-panel">
					<h2><?php esc_html_e( 'Existing Rules', 'minorda' ); ?></h2>
					<?php if ( empty( $rules ) ) : ?>
						<p><?php esc_html_e( 'No rules found yet.', 'minorda' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Rule', 'minorda' ); ?></th>
									<th><?php esc_html_e( 'Targets', 'minorda' ); ?></th>
									<th><?php esc_html_e( 'Limits', 'minorda' ); ?></th>
									<th><?php esc_html_e( 'Status', 'minorda' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'minorda' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rules as $rule ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $rule['name'] ); ?></strong></td>
										<td><?php echo wp_kses_post( $this->render_rule_targets_summary( $rule ) ); ?></td>
										<td><?php echo esc_html( $this->render_rule_limits_summary( $rule ) ); ?></td>
										<td>
											<span class="wcmr-status <?php echo ! empty( $rule['enabled'] ) ? 'is-enabled' : 'is-disabled'; ?>">
												<?php echo ! empty( $rule['enabled'] ) ? esc_html__( 'Enabled', 'minorda' ) : esc_html__( 'Disabled', 'minorda' ); ?>
											</span>
										</td>
										<td>
											<a class="button button-secondary" href="<?php echo esc_url( $this->build_page_url( array( 'wcmr_action' => 'edit', 'rule_id' => $rule['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'minorda' ); ?></a>
											<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( $this->build_page_url( array( 'wcmr_action' => 'toggle', 'rule_id' => $rule['id'] ) ), 'wcmr_rule_action' ) ); ?>"><?php echo ! empty( $rule['enabled'] ) ? esc_html__( 'Disable', 'minorda' ) : esc_html__( 'Enable', 'minorda' ); ?></a>
											<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( $this->build_page_url( array( 'wcmr_action' => 'delete', 'rule_id' => $rule['id'] ) ), 'wcmr_rule_action' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'minorda' ) ); ?>');"><?php esc_html_e( 'Delete', 'minorda' ); ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style(
			'wcmr-admin',
			WCMR_PLUGIN_URL . 'assets/admin.css',
			array( 'woocommerce_admin_styles' ),
			WCMR_VERSION
		);

		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_script(
			'wcmr-admin',
			WCMR_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery', 'selectWoo' ),
			WCMR_VERSION,
			true
		);

		wp_localize_script(
			'wcmr-admin',
			'wcmrAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'searchNonce'    => wp_create_nonce( 'wcmr_search_products' ),
				'searchProducts' => __( 'Search products...', 'minorda' ),
				'noResults'      => __( 'No products found.', 'minorda' ),
			)
		);
	}

	public function ajax_search_products() {
		check_ajax_referer( 'wcmr_search_products', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$term = isset( $_GET['term'] ) ? wc_clean( wp_unslash( $_GET['term'] ) ) : '';

		if ( '' === $term ) {
			wp_send_json( array() );
		}

		$query = new WP_Query(
			array(
				'post_type'              => array( 'product', 'product_variation' ),
				'post_status'            => array( 'publish', 'private' ),
				'posts_per_page'         => 20,
				's'                      => $term,
				'fields'                 => 'ids',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$results = array();

		foreach ( $query->posts as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$results[] = array(
				'id'   => $product_id,
				'text' => wp_strip_all_tags( $this->get_product_label( $product ) ),
			);
		}

		wp_send_json( $results );
	}

	protected function handle_save() {
		check_admin_referer( 'wcmr_save_rule' );

		$raw_rule = array(
			'id'             => isset( $_POST['rule_id'] ) ? sanitize_key( wp_unslash( $_POST['rule_id'] ) ) : '',
			'name'           => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'enabled'        => ! empty( $_POST['enabled'] ),
			'product_ids'    => isset( $_POST['product_ids'] ) ? (array) wp_unslash( $_POST['product_ids'] ) : array(),
			'taxonomy_terms' => isset( $_POST['taxonomy_terms'] ) ? (array) wp_unslash( $_POST['taxonomy_terms'] ) : array(),
			'min_quantity'   => isset( $_POST['min_quantity'] ) ? wp_unslash( $_POST['min_quantity'] ) : '',
			'min_value'      => isset( $_POST['min_value'] ) ? wc_clean( wp_unslash( $_POST['min_value'] ) ) : '',
			'max_quantity'   => isset( $_POST['max_quantity'] ) ? wp_unslash( $_POST['max_quantity'] ) : '',
			'max_value'      => isset( $_POST['max_value'] ) ? wc_clean( wp_unslash( $_POST['max_value'] ) ) : '',
			'quantity_scope' => isset( $_POST['quantity_scope'] ) ? sanitize_key( wp_unslash( $_POST['quantity_scope'] ) ) : 'combined',
		);

		$errors = $this->validate_rule_submission( $raw_rule );

		if ( ! empty( $errors ) ) {
			$this->redirect_with_notice( implode( ' ', $errors ), 'error', array( 'wcmr_action' => 'edit', 'rule_id' => $raw_rule['id'] ) );
		}

		$rule = $this->repository->save( $raw_rule );

		$this->redirect_with_notice(
			'saved',
			'success',
			array(
				'wcmr_action' => 'edit',
				'rule_id'     => $rule['id'],
			)
		);
	}

	protected function validate_rule_submission( array $raw_rule ) {
		$errors = array();
		$name   = sanitize_text_field( $raw_rule['name'] ?? '' );

		if ( '' === $name ) {
			$errors[] = __( 'Rule name is required.', 'minorda' );
		}

		$product_ids = array_filter( array_map( 'absint', (array) ( $raw_rule['product_ids'] ?? array() ) ) );
		$term_groups = array_filter(
			(array) ( $raw_rule['taxonomy_terms'] ?? array() ),
			static function( $term_ids ) {
				return ! empty( array_filter( array_map( 'absint', (array) $term_ids ) ) );
			}
		);

		if ( empty( $product_ids ) && empty( $term_groups ) ) {
			$errors[] = __( 'Select at least one product or taxonomy term.', 'minorda' );
		}

		$min_quantity = trim( (string) ( $raw_rule['min_quantity'] ?? '' ) );
		$min_value    = trim( (string) ( $raw_rule['min_value'] ?? '' ) );
		$max_quantity = trim( (string) ( $raw_rule['max_quantity'] ?? '' ) );
		$max_value    = trim( (string) ( $raw_rule['max_value'] ?? '' ) );

		if ( '' === $min_quantity && '' === $min_value && '' === $max_quantity && '' === $max_value ) {
			$errors[] = __( 'Set at least one minimum or maximum threshold.', 'minorda' );
		}

		if ( '' !== $min_quantity && ( ! ctype_digit( $min_quantity ) || (int) $min_quantity <= 0 ) ) {
			$errors[] = __( 'Minimum quantity must be a positive whole number.', 'minorda' );
		}

		if ( '' !== $min_value ) {
			$normalized_value = wc_format_decimal( $min_value );
			if ( '' === $normalized_value || (float) $normalized_value <= 0 ) {
				$errors[] = __( 'Minimum value must be a positive amount.', 'minorda' );
			}
		}

		if ( '' !== $max_quantity && ( ! ctype_digit( $max_quantity ) || (int) $max_quantity <= 0 ) ) {
			$errors[] = __( 'Maximum quantity must be a positive whole number.', 'minorda' );
		}

		if ( '' !== $max_value ) {
			$normalized_value = wc_format_decimal( $max_value );
			if ( '' === $normalized_value || (float) $normalized_value <= 0 ) {
				$errors[] = __( 'Maximum value must be a positive amount.', 'minorda' );
			}
		}

		if ( '' !== $min_quantity && '' !== $max_quantity && (int) $min_quantity > (int) $max_quantity ) {
			$errors[] = __( 'Maximum quantity must be greater than or equal to minimum quantity.', 'minorda' );
		}

		if ( '' !== $min_value && '' !== $max_value ) {
			$normalized_min_value = (float) wc_format_decimal( $min_value );
			$normalized_max_value = (float) wc_format_decimal( $max_value );

			if ( $normalized_min_value > $normalized_max_value ) {
				$errors[] = __( 'Maximum value must be greater than or equal to minimum value.', 'minorda' );
			}
		}

		return $errors;
	}

	protected function get_current_rule() {
		$action  = isset( $_GET['wcmr_action'] ) ? sanitize_key( wp_unslash( $_GET['wcmr_action'] ) ) : '';
		$rule_id = isset( $_GET['rule_id'] ) ? sanitize_key( wp_unslash( $_GET['rule_id'] ) ) : '';

		if ( 'edit' === $action && $rule_id ) {
			$rule = $this->repository->get( $rule_id );

			if ( $rule ) {
				return $rule;
			}
		}

		return array(
			'id'             => '',
			'name'           => '',
			'enabled'        => true,
			'product_ids'    => array(),
			'taxonomy_terms' => array(),
			'min_quantity'   => null,
			'min_value'      => null,
			'max_quantity'   => null,
			'max_value'      => null,
			'quantity_scope' => 'combined',
		);
	}

	protected function render_notices() {
		if ( empty( $_GET['wcmr_notice'] ) ) {
			return;
		}

		$notice = sanitize_text_field( wp_unslash( $_GET['wcmr_notice'] ) );
		$type   = isset( $_GET['wcmr_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['wcmr_notice_type'] ) ) : 'success';
		$class  = 'notice notice-' . ( 'error' === $type ? 'error' : 'success' );

		$messages = array(
			'saved'        => __( 'Rule saved.', 'minorda' ),
			'updated'      => __( 'Rule updated.', 'minorda' ),
			'deleted'      => __( 'Rule deleted.', 'minorda' ),
			'missing_rule' => __( 'Rule not found.', 'minorda' ),
		);

		$message = $messages[ $notice ] ?? $notice;
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	protected function build_page_url( array $args = array() ) {
		$args = array_merge(
			array(
				'page' => self::PAGE_SLUG,
			),
			$args
		);

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	protected function redirect_with_notice( $notice, $type = 'success', array $args = array() ) {
		$args['wcmr_notice']      = $notice;
		$args['wcmr_notice_type'] = $type;

		wp_safe_redirect( $this->build_page_url( $args ) );
		exit;
	}

	protected function is_plugin_page_request() {
		return isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	protected function get_product_taxonomies() {
		$taxonomies = get_object_taxonomies( 'product', 'objects' );

		return array_values(
			array_filter(
				$taxonomies,
				static function( $taxonomy ) {
					return $taxonomy instanceof WP_Taxonomy && ! empty( $taxonomy->show_ui );
				}
			)
		);
	}

	protected function get_product_labels( array $product_ids ) {
		$labels = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$labels[ $product_id ] = $this->get_product_label( $product );
		}

		return $labels;
	}

	protected function get_product_label( WC_Product $product ) {
		$name = $product->get_formatted_name();
		$sku  = $product->get_sku();

		if ( $sku ) {
			return sprintf( '%1$s (#%2$s)', $name, $sku );
		}

		return sprintf( '%1$s (ID: %2$d)', $name, $product->get_id() );
	}

	protected function render_rule_targets_summary( array $rule ) {
		$parts = array();

		if ( ! empty( $rule['product_ids'] ) ) {
			$parts[] = sprintf(
				esc_html__( '%d products', 'minorda' ),
				count( $rule['product_ids'] )
			);
		}

		if ( ! empty( $rule['taxonomy_terms'] ) ) {
			foreach ( $rule['taxonomy_terms'] as $taxonomy => $term_ids ) {
				$taxonomy_obj = get_taxonomy( $taxonomy );

				if ( ! $taxonomy_obj ) {
					continue;
				}

				$parts[] = sprintf(
					esc_html__( '%1$s: %2$d terms', 'minorda' ),
					$taxonomy_obj->labels->singular_name ?: $taxonomy_obj->label,
					count( $term_ids )
				);
			}
		}

		return implode( '<br>', array_map( 'esc_html', $parts ) );
	}

	protected function render_rule_limits_summary( array $rule ) {
		$parts = array();

		if ( null !== $rule['min_quantity'] ) {
			$parts[] = sprintf(
				__( 'Min qty %d', 'minorda' ),
				(int) $rule['min_quantity']
			);
		}

		if ( null !== $rule['min_value'] ) {
			$parts[] = sprintf(
				__( 'Min value %s', 'minorda' ),
				wp_strip_all_tags( wc_price( $rule['min_value'] ) )
			);
		}

		if ( null !== ( $rule['max_quantity'] ?? null ) ) {
			$parts[] = sprintf(
				__( 'Max qty %d', 'minorda' ),
				(int) $rule['max_quantity']
			);
		}

		if ( null !== ( $rule['max_value'] ?? null ) ) {
			$parts[] = sprintf(
				__( 'Max value %s', 'minorda' ),
				wp_strip_all_tags( wc_price( $rule['max_value'] ) )
			);
		}

		if ( 'per_product' === ( $rule['quantity_scope'] ?? 'combined' ) && ( null !== ( $rule['min_quantity'] ?? null ) || null !== ( $rule['max_quantity'] ?? null ) ) ) {
			$parts[] = __( 'Each product', 'minorda' );
		}

		return implode( ' / ', $parts );
	}
}

<?php
/**
 * Product normalization and configuration validation.
 *
 * @package Form27
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class F27_Product_Schema {
	public const META = array(
		'f27_code'       => 'string',
		'f27_dimensions' => 'string',
		'f27_wattages'   => 'integer_array',
		'f27_lumens'     => 'integer_array',
		'f27_cct'        => 'integer_array',
		'f27_cri'        => 'integer_array',
		'f27_beams'      => 'string_array',
		'f27_finishes'   => 'string_array',
		'f27_controls'   => 'string_array',
		'f27_ip'         => 'string',
		'f27_price'      => 'integer',
		'f27_featured'   => 'boolean',
		'f27_image_url'  => 'url',
	);

	/**
	 * Register all public product meta fields.
	 */
	public static function register_meta(): void {
		foreach ( self::META as $key => $kind ) {
			$type        = 'string';
			$rest_schema = array( 'type' => 'string' );
			$sanitize    = 'sanitize_text_field';

			if ( 'integer' === $kind ) {
				$type        = 'integer';
				$rest_schema = array( 'type' => 'integer' );
				$sanitize    = 'absint';
			} elseif ( 'boolean' === $kind ) {
				$type        = 'boolean';
				$rest_schema = array( 'type' => 'boolean' );
				$sanitize    = 'rest_sanitize_boolean';
			} elseif ( 'url' === $kind ) {
				$sanitize = 'esc_url_raw';
			} elseif ( 'integer_array' === $kind ) {
				$type        = 'array';
				$rest_schema = array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				);
				$sanitize    = array( self::class, 'sanitize_integer_array' );
			} elseif ( 'string_array' === $kind ) {
				$type        = 'array';
				$rest_schema = array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				);
				$sanitize    = array( self::class, 'sanitize_string_array' );
			}

			register_post_meta(
				'f27_product',
				$key,
				array(
					'single'            => true,
					'type'              => $type,
					'show_in_rest'      => array( 'schema' => $rest_schema ),
					'sanitize_callback' => $sanitize,
					'auth_callback'     => '__return_true',
				)
			);
		}

		$case_fields = array(
			'f27_case_location'     => 'sanitize_text_field',
			'f27_case_area'         => 'sanitize_text_field',
			'f27_case_year'         => 'absint',
			'f27_case_before_image' => 'esc_url_raw',
			'f27_case_after_image'  => 'esc_url_raw',
		);

		foreach ( $case_fields as $key => $sanitize ) {
			$is_year = 'f27_case_year' === $key;
			register_post_meta(
				'f27_case',
				$key,
				array(
					'single'            => true,
					'type'              => $is_year ? 'integer' : 'string',
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => '__return_true',
				)
			);
		}
	}

	/**
	 * @param mixed $value Incoming value.
	 * @return int[]
	 */
	public static function sanitize_integer_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	/**
	 * @param mixed $value Incoming value.
	 * @return string[]
	 */
	public static function sanitize_string_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array_map( 'sanitize_text_field', $value );
		return array_values( array_unique( array_filter( $clean ) ) );
	}

	/**
	 * Return normalized products for blocks and API responses.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array<int,array<string,mixed>>
	 */
	public static function query_products( array $args = array() ): array {
		$defaults = array(
			'post_type'      => 'f27_product',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'  => true,
		);

		$query = new WP_Query( wp_parse_args( $args, $defaults ) );
		return array_map( array( self::class, 'normalize_product' ), $query->posts );
	}

	/**
	 * @param WP_Post|int $product Product post or ID.
	 * @return array<string,mixed>
	 */
	public static function normalize_product( $product ): array {
		$post = get_post( $product );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$data = array(
			'id'          => $post->ID,
			'slug'        => $post->post_name,
			'name'        => get_the_title( $post ),
			'excerpt'     => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 24 ),
			'url'         => get_permalink( $post ),
			'image'       => (string) get_post_meta( $post->ID, 'f27_image_url', true ),
			'collections' => self::term_values( $post->ID, 'f27_collection' ),
			'mounting'    => self::term_values( $post->ID, 'f27_mounting' ),
			'application' => self::term_values( $post->ID, 'f27_application' ),
		);

		if ( '' === $data['image'] ) {
			$data['image'] = (string) get_the_post_thumbnail_url( $post, 'large' );
		}

		foreach ( self::META as $key => $kind ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( str_ends_with( $kind, '_array' ) ) {
				$value = is_array( $value ) ? array_values( $value ) : array();
			} elseif ( 'integer' === $kind ) {
				$value = (int) $value;
			} elseif ( 'boolean' === $kind ) {
				$value = (bool) $value;
			}
			$data[ str_replace( 'f27_', '', $key ) ] = $value;
		}

		return $data;
	}

	/**
	 * @return array<int,array{slug:string,name:string}>
	 */
	private static function term_values( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_map(
			static fn ( WP_Term $term ): array => array(
				'slug' => $term->slug,
				'name' => $term->name,
			),
			$terms
		);
	}

	/**
	 * Validate submitted configured products and rebuild trusted values.
	 *
	 * @param mixed $items Incoming items.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public static function validate_items( $items ) {
		if ( ! is_array( $items ) || empty( $items ) || count( $items ) > 30 ) {
			return new WP_Error( 'f27_invalid_project', 'Добавьте в проект от одного до 30 светильников.', array( 'status' => 400 ) );
		}

		$validated = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				return new WP_Error( 'f27_invalid_item', 'Одна из позиций проекта имеет неверный формат.', array( 'status' => 400 ) );
			}

			$product = null;
			if ( ! empty( $item['productId'] ) ) {
				$product = get_post( absint( $item['productId'] ) );
			} elseif ( ! empty( $item['slug'] ) ) {
				$found   = get_page_by_path( sanitize_title( (string) $item['slug'] ), OBJECT, 'f27_product' );
				$product = $found instanceof WP_Post ? $found : null;
			}

			if ( ! $product instanceof WP_Post || 'f27_product' !== $product->post_type || 'publish' !== $product->post_status ) {
				return new WP_Error( 'f27_unknown_product', 'Один из светильников не найден.', array( 'status' => 400 ) );
			}

			$options       = isset( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : array();
			$fields        = array(
				'power'   => 'f27_wattages',
				'cct'     => 'f27_cct',
				'cri'     => 'f27_cri',
				'beam'    => 'f27_beams',
				'finish'  => 'f27_finishes',
				'control' => 'f27_controls',
			);
			$clean_options = array();

			foreach ( $fields as $option_key => $meta_key ) {
				$allowed = get_post_meta( $product->ID, $meta_key, true );
				$allowed = is_array( $allowed ) ? array_map( 'strval', $allowed ) : array();
				$value   = isset( $options[ $option_key ] ) ? sanitize_text_field( (string) $options[ $option_key ] ) : '';
				if ( '' === $value || ! in_array( $value, $allowed, true ) ) {
					return new WP_Error( 'f27_invalid_option', sprintf( 'Недопустимая конфигурация для %s.', get_the_title( $product ) ), array( 'status' => 400 ) );
				}
				$clean_options[ $option_key ] = $value;
			}

			$quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;
			if ( $quantity < 1 || $quantity > 99 ) {
				return new WP_Error( 'f27_invalid_quantity', 'Количество должно быть от 1 до 99.', array( 'status' => 400 ) );
			}

			$normalized  = self::normalize_product( $product );
			$validated[] = array(
				'productId' => $product->ID,
				'slug'      => $product->post_name,
				'name'      => get_the_title( $product ),
				'quantity'  => $quantity,
				'options'   => $clean_options,
				'sku'       => self::build_sku( (string) $normalized['code'], $clean_options ),
				'price'     => (int) $normalized['price'],
			);
		}

		return $validated;
	}

	/**
	 * @param array<string,string> $options Configuration.
	 */
	public static function build_sku( string $base_code, array $options ): string {
		$finish_codes  = array(
			'Чёрный RAL 9005'        => 'BK',
			'Белый RAL 9003'         => 'WH',
			'Графит'                 => 'GR',
			'Тёмная бронза'          => 'BZ',
			'Анодированный алюминий' => 'AL',
		);
		$control_codes = array(
			'On/Off' => 'ON',
			'DALI-2' => 'DALI',
			'TRIAC'  => 'TRIAC',
		);
		$beam          = str_replace( '×', 'x', (string) ( $options['beam'] ?? '' ) );
		$beam          = preg_replace( '/[^0-9x]/i', '', $beam );
		$cct           = (string) (int) floor( (int) ( $options['cct'] ?? 0 ) / 100 );

		$base_code = strtoupper( (string) preg_replace( '/[^A-Z0-9]+/i', '-', $base_code ) );
		$base_code = trim( $base_code, '-' );

		return implode(
			'-',
			array_filter(
				array(
					'F27',
					$base_code,
					(string) ( $options['power'] ?? '' ),
					$cct,
					(string) ( $options['cri'] ?? '' ),
					$beam,
					$finish_codes[ $options['finish'] ?? '' ] ?? 'NA',
					$control_codes[ $options['control'] ?? '' ] ?? 'NA',
				)
			)
		);
	}
}

<?php
/**
 * FORM 27 REST API.
 *
 * @package Form27
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class F27_REST {
	private const NAMESPACE   = 'form27/v1';
	private const RATE_LIMIT  = 5;
	private const RATE_WINDOW = HOUR_IN_SECONDS;

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_products' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'        => array(
						'sanitize_callback' => 'absint',
						'default'           => 1,
					),
					'per_page'    => array(
						'sanitize_callback' => 'absint',
						'default'           => 24,
					),
					's'           => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'collection'  => array( 'sanitize_callback' => 'sanitize_title' ),
					'mounting'    => array( 'sanitize_callback' => 'sanitize_title' ),
					'application' => array( 'sanitize_callback' => 'sanitize_title' ),
					'featured'    => array( 'sanitize_callback' => 'rest_sanitize_boolean' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'create_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function get_products( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$args     = array(
			'post_type'      => 'f27_product',
			'post_status'    => 'publish',
			'paged'          => $page,
			'posts_per_page' => $per_page,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'  => false,
		);

		$search = trim( (string) $request->get_param( 's' ) );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$tax_query = array();
		foreach ( array(
			'collection'  => 'f27_collection',
			'mounting'    => 'f27_mounting',
			'application' => 'f27_application',
		) as $parameter => $taxonomy ) {
			$value = sanitize_title( (string) $request->get_param( $parameter ) );
			if ( '' !== $value ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array( $value ),
				);
			}
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query;
		}

		if ( null !== $request->get_param( 'featured' ) ) {
			if ( rest_sanitize_boolean( $request->get_param( 'featured' ) ) ) {
				$args['meta_query'] = array(
					array(
						'key'     => 'f27_featured',
						'value'   => '1',
						'compare' => '=',
					),
				);
			} else {
				$args['meta_query'] = array(
					'relation' => 'OR',
					array(
						'key'     => 'f27_featured',
						'value'   => '0',
						'compare' => '=',
					),
					array(
						'key'     => 'f27_featured',
						'value'   => '',
						'compare' => '=',
					),
					array(
						'key'     => 'f27_featured',
						'compare' => 'NOT EXISTS',
					),
				);
			}
		}

		$query    = new WP_Query( $args );
		$response = new WP_REST_Response(
			array(
				'schemaVersion' => 1,
				'demo'          => F27_Settings::is_demo(),
				'generatedAt'   => gmdate( DATE_ATOM ),
				'products'      => array_map( array( 'F27_Product_Schema', 'normalize_product' ), $query->posts ),
				'pagination'    => array(
					'page'       => $page,
					'perPage'    => $per_page,
					'total'      => (int) $query->found_posts,
					'totalPages' => (int) $query->max_num_pages,
				),
			),
			200
		);
		$response->header( 'Cache-Control', 'public, max-age=60, stale-while-revalidate=300' );
		return $response;
	}

	/**
	 * Create and optionally email a private specification request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_request( WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'f27_bad_nonce', 'Обновите страницу и попробуйте ещё раз.', array( 'status' => 403 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'f27_invalid_body', 'Не удалось прочитать данные формы.', array( 'status' => 400 ) );
		}
		if ( ! array_key_exists( 'schemaVersion', $params ) || ! is_int( $params['schemaVersion'] ) ) {
			return new WP_Error( 'f27_invalid_schema', 'Укажите целочисленную версию схемы запроса.', array( 'status' => 400 ) );
		}
		if ( 1 !== $params['schemaVersion'] ) {
			return new WP_Error( 'f27_unsupported_schema', 'Версия схемы запроса не поддерживается.', array( 'status' => 422 ) );
		}

		if ( ! empty( $params['website'] ) ) {
			return new WP_Error( 'f27_spam', 'Форма не прошла проверку.', array( 'status' => 400 ) );
		}

		$started_at = isset( $params['startedAt'] ) ? (float) $params['startedAt'] : 0.0;
		if ( $started_at > 100000000000.0 ) {
			$started_at /= 1000;
		}
		$elapsed = time() - (int) $started_at;
		if ( $started_at <= 0 || $elapsed < 3 || $elapsed > 7200 ) {
			return new WP_Error( 'f27_bad_timing', 'Заполните форму ещё раз без спешки.', array( 'status' => 400 ) );
		}

		if ( ! rest_sanitize_boolean( $params['consent'] ?? false ) ) {
			return new WP_Error( 'f27_no_consent', 'Подтвердите согласие на обработку данных.', array( 'status' => 400 ) );
		}

		$contact = isset( $params['contact'] ) && is_array( $params['contact'] ) ? $params['contact'] : $params;
		$project = isset( $params['project'] ) && is_array( $params['project'] ) ? $params['project'] : $params;
		$name    = sanitize_text_field( (string) ( $contact['name'] ?? '' ) );
		$email   = sanitize_email( (string) ( $contact['email'] ?? '' ) );
		$phone   = sanitize_text_field( (string) ( $contact['phone'] ?? '' ) );
		$company = sanitize_text_field( (string) ( $contact['company'] ?? '' ) );
		$message = sanitize_textarea_field( (string) ( $params['message'] ?? '' ) );
		if ( self::text_length( $name ) < 2 || self::text_length( $name ) > 100 ) {
			return new WP_Error( 'f27_bad_name', 'Укажите имя от 2 до 100 символов.', array( 'status' => 400 ) );
		}
		if ( '' === $email && '' === $phone ) {
			return new WP_Error( 'f27_no_contact', 'Укажите телефон или электронную почту.', array( 'status' => 400 ) );
		}
		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'f27_bad_email', 'Проверьте адрес электронной почты.', array( 'status' => 400 ) );
		}
		if ( self::text_length( $phone ) > 40 || self::text_length( $company ) > 120 ) {
			return new WP_Error( 'f27_bad_contact', 'Контактные данные слишком длинные.', array( 'status' => 400 ) );
		}
		if ( self::text_length( $message ) > 2000 ) {
			return new WP_Error( 'f27_bad_message', 'Комментарий должен быть короче 2000 символов.', array( 'status' => 400 ) );
		}

		$items = F27_Product_Schema::validate_items( $project['items'] ?? null );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$rate_limit = self::consume_rate_limit( $email, $phone );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'f27_request',
				'post_status' => 'private',
				'post_title'  => sprintf( 'Спецификация: %s, %s', $name, wp_date( 'd.m.Y H:i' ) ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'f27_store_failed', 'Не удалось сохранить заявку. Свяжитесь с нами другим способом.', array( 'status' => 500 ) );
		}

		$ip_hash = hash_hmac( 'sha256', self::client_ip(), wp_salt( 'auth' ) );
		$meta    = array(
			'f27_request_status'  => 'new',
			'f27_request_name'    => $name,
			'f27_request_email'   => $email,
			'f27_request_phone'   => $phone,
			'f27_request_company' => $company,
			'f27_request_message' => $message,
			'f27_request_items'   => $items,
			'f27_request_ip_hash' => $ip_hash,
			'f27_request_consent' => wp_date( DATE_ATOM ),
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( (int) $post_id, $key, $value );
		}

		$brand = F27_Settings::brand_name();
		$lines = array(
			'Новая заявка ' . $brand,
			'Имя: ' . $name,
			'Компания: ' . ( '' !== $company ? $company : 'не указана' ),
			'Email: ' . ( '' !== $email ? $email : 'не указан' ),
			'Телефон: ' . ( '' !== $phone ? $phone : 'не указан' ),
			'Комментарий: ' . ( '' !== $message ? $message : 'не указан' ),
			'',
			'Спецификация:',
		);
		foreach ( $items as $item ) {
			$lines[] = sprintf( '%s, %s, %d шт.', $item['name'], $item['sku'], $item['quantity'] );
		}

		$recipient = F27_Settings::public_email();
		if ( ! is_email( $recipient ) ) {
			$recipient = (string) get_option( 'admin_email' );
		}
		update_post_meta( (int) $post_id, 'f27_request_delivery_state', 'pending' );
		$mail_sent = wp_mail(
			$recipient,
			'Новая спецификация ' . $brand,
			implode( "\n", $lines ),
			is_email( F27_Settings::public_email() ) ? array( 'Reply-To: ' . $brand . ' <' . F27_Settings::public_email() . '>' ) : array()
		);
		update_post_meta( (int) $post_id, 'f27_request_mail_sent', $mail_sent ? '1' : '0' );
		update_post_meta( (int) $post_id, 'f27_request_delivery_state', $mail_sent ? 'accepted' : 'failed' );

		return new WP_REST_Response(
			array(
				'schemaVersion' => 1,
				'request'       => array(
					'id'       => (int) $post_id,
					'status'   => 'new',
					'stored'   => true,
					'mailSent' => $mail_sent,
					'delivery' => $mail_sent ? 'accepted' : 'failed',
				),
				'message'       => F27_Settings::is_demo()
					? ( $mail_sent
						? 'Заявка сохранена во временной панели этой WordPress-сессии и передана тестовому почтовому транспорту.'
						: 'Заявка сохранена только во временной панели этой WordPress-сессии. Письмо не отправлено.' )
					: ( $mail_sent
						? 'Заявка сохранена и передана почтовому серверу. Мы свяжемся с вами.'
						: 'Заявка сохранена, но письмо не отправлено. Проверьте её в панели управления.' ),
			),
			201
		);
	}

	/** @return true|WP_Error */
	private static function consume_rate_limit( string $email, string $phone ) {
		$contact  = '' !== $email ? strtolower( trim( $email ) ) : preg_replace( '/\D+/', '', $phone );
		$identity = (string) $contact . '|' . self::client_ip();
		$digest   = substr( hash_hmac( 'sha256', $identity, wp_salt( 'nonce' ) ), 0, 40 );
		$rate_key = 'f27_rate_' . $digest;
		$lock_key = 'f27_rate_lock_' . $digest;
		$now      = time();
		$locked   = add_option( $lock_key, $now, '', false );
		if ( ! $locked ) {
			$locked_at = (int) get_option( $lock_key, 0 );
			if ( $locked_at > 0 && $locked_at < $now - 10 ) {
				delete_option( $lock_key );
				$locked = add_option( $lock_key, $now, '', false );
			}
		}
		if ( ! $locked ) {
			return new WP_Error( 'f27_rate_busy', 'Запрос уже обрабатывается. Повторите через несколько секунд.', array( 'status' => 429 ) );
		}

		try {
			$count = (int) get_transient( $rate_key );
			if ( $count >= self::RATE_LIMIT ) {
				return new WP_Error( 'f27_rate_limited', 'Слишком много попыток. Попробуйте через час.', array( 'status' => 429 ) );
			}
			set_transient( $rate_key, $count + 1, self::RATE_WINDOW );
			return true;
		} finally {
			delete_option( $lock_key );
		}
	}

	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	private static function text_length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value );
		}
		$count = preg_match_all( '/./us', $value, $matches );
		return false === $count ? strlen( $value ) : $count;
	}
}

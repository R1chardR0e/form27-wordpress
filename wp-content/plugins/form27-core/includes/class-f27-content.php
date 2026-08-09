<?php
/**
 * Content types, taxonomies and request administration.
 *
 * @package Form27
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class F27_Content {
	public static function register(): void {
		register_post_type(
			'f27_product',
			array(
				'labels'       => self::labels( 'Светильник', 'Светильники' ),
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-lightbulb',
				'has_archive'  => 'catalog',
				'rewrite'      => array( 'slug' => 'product' ),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ),
			)
		);

		register_post_type(
			'f27_case',
			array(
				'labels'       => self::labels( 'Проект', 'Проекты' ),
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-building',
				'has_archive'  => 'projects',
				'rewrite'      => array( 'slug' => 'project' ),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ),
			)
		);

		register_post_type(
			'f27_request',
			array(
				'labels'              => self::labels( 'Заявка', 'Заявки' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-clipboard',
				'supports'            => array( 'title' ),
				'map_meta_cap'        => false,
				'capability_type'     => 'f27_request',
				'capabilities'        => array(
					'edit_post'              => 'manage_options',
					'read_post'              => 'manage_options',
					'delete_post'            => 'manage_options',
					'edit_posts'             => 'manage_options',
					'edit_others_posts'      => 'manage_options',
					'delete_posts'           => 'manage_options',
					'delete_private_posts'   => 'manage_options',
					'delete_published_posts' => 'manage_options',
					'delete_others_posts'    => 'manage_options',
					'publish_posts'          => 'do_not_allow',
					'read_private_posts'     => 'manage_options',
					'create_posts'           => 'do_not_allow',
				),
			)
		);

		$taxonomies = array(
			'f27_collection'  => array( 'Коллекция', 'Коллекции' ),
			'f27_mounting'    => array( 'Монтаж', 'Типы монтажа' ),
			'f27_application' => array( 'Применение', 'Применение' ),
		);

		foreach ( $taxonomies as $taxonomy => $names ) {
			register_taxonomy(
				$taxonomy,
				array( 'f27_product' ),
				array(
					'labels'            => self::labels( $names[0], $names[1] ),
					'public'            => true,
					'show_in_rest'      => true,
					'hierarchical'      => true,
					'show_admin_column' => true,
					'rewrite'           => array( 'slug' => str_replace( 'f27_', '', $taxonomy ) ),
				)
			);
		}

		F27_Product_Schema::register_meta();
		self::register_request_meta();
	}

	/**
	 * @return array<string,string>
	 */
	private static function labels( string $singular, string $plural ): array {
		$lower = static fn ( string $value ): string => function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'add_new_item'  => 'Добавить: ' . $lower( $singular ),
			'edit_item'     => 'Редактировать: ' . $lower( $singular ),
			'new_item'      => 'Новый объект',
			'view_item'     => 'Посмотреть',
			'search_items'  => 'Поиск',
			'not_found'     => 'Ничего не найдено',
			'all_items'     => 'Все: ' . $lower( $plural ),
		);
	}

	private static function register_request_meta(): void {
		$fields = array(
			'f27_request_status'         => 'sanitize_key',
			'f27_request_notes'          => 'sanitize_textarea_field',
			'f27_request_message'        => 'sanitize_textarea_field',
			'f27_request_name'           => 'sanitize_text_field',
			'f27_request_email'          => 'sanitize_email',
			'f27_request_phone'          => 'sanitize_text_field',
			'f27_request_company'        => 'sanitize_text_field',
			'f27_request_consent'        => 'sanitize_text_field',
			'f27_request_mail_sent'      => 'sanitize_key',
			'f27_request_delivery_state' => 'sanitize_key',
			'f27_request_ip_hash'        => 'sanitize_text_field',
		);

		foreach ( $fields as $key => $sanitize ) {
			register_post_meta(
				'f27_request',
				$key,
				array(
					'single'            => true,
					'type'              => 'string',
					'show_in_rest'      => false,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => static fn (): bool => current_user_can( 'edit_posts' ),
				)
			);
		}
	}

	public static function add_product_metabox(): void {
		add_meta_box( 'f27-product-specification', 'Параметры светильника', array( self::class, 'render_product_metabox' ), 'f27_product', 'normal', 'high' );
	}

	public static function render_product_metabox( WP_Post $post ): void {
		wp_nonce_field( 'f27_save_product', 'f27_product_nonce' );
		$fields = array(
			'f27_code'       => array( 'Артикул', 'text', 'Например, S48-SPOT' ),
			'f27_dimensions' => array( 'Размеры', 'text', 'Например, Ø45 × 135 мм' ),
			'f27_wattages'   => array( 'Мощности, Вт', 'array', 'Через запятую или с новой строки' ),
			'f27_lumens'     => array( 'Световой поток, лм', 'array', 'Значения соответствуют вариантам мощности' ),
			'f27_cct'        => array( 'Цветовая температура, K', 'array', 'Например, 2700, 3000, 4000' ),
			'f27_cri'        => array( 'CRI', 'array', 'Например, 90, 95' ),
			'f27_beams'      => array( 'Оптика', 'array', 'Например, 24°, 36°' ),
			'f27_finishes'   => array( 'Отделки', 'array', 'Через запятую или с новой строки' ),
			'f27_controls'   => array( 'Управление', 'array', 'Например, On/Off, DALI-2' ),
			'f27_ip'         => array( 'Степень защиты', 'text', 'Например, IP20' ),
			'f27_price'      => array( 'Демо-цена, ₽', 'number', 'Целое число' ),
			'f27_image_url'  => array( 'URL изображения', 'url', 'Если пусто, используется изображение записи' ),
		);
		?>
		<table class="form-table" role="presentation"><tbody>
		<?php foreach ( $fields as $key => $field ) : ?>
			<?php
			$value = get_post_meta( $post->ID, $key, true );
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			}
			?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
				<td>
				<?php if ( 'array' === $field[1] ) : ?>
					<textarea class="large-text" rows="2" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php else : ?>
					<input class="regular-text" type="<?php echo esc_attr( $field[1] ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>"<?php echo 'number' === $field[1] ? ' min="0" step="1"' : ''; ?>>
				<?php endif; ?>
				<p class="description"><?php echo esc_html( $field[2] ); ?></p>
				</td>
			</tr>
		<?php endforeach; ?>
			<tr><th scope="row">Витрина</th><td><label><input type="checkbox" name="f27_featured" value="1" <?php checked( (bool) get_post_meta( $post->ID, 'f27_featured', true ) ); ?>> Показывать как избранную модель</label></td></tr>
		</tbody></table>
		<?php
	}

	public static function save_product_metabox( int $post_id ): void {
		if ( ! self::can_save_metabox( $post_id, 'f27_product_nonce', 'f27_save_product' ) ) {
			return;
		}

		foreach ( F27_Product_Schema::META as $key => $kind ) {
			if ( 'boolean' === $kind ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by can_save_metabox() before this loop.
				update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? 1 : 0 );
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by can_save_metabox() before this loop.
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			if ( str_ends_with( $kind, '_array' ) ) {
				$values = preg_split( '/[\r\n,]+/u', (string) $value );
				$values = false === $values ? array() : $values;
				$value  = 'integer_array' === $kind
					? F27_Product_Schema::sanitize_integer_array( $values )
					: F27_Product_Schema::sanitize_string_array( $values );
			} elseif ( 'integer' === $kind ) {
				$value = absint( $value );
			} elseif ( 'url' === $kind ) {
				$value = esc_url_raw( (string) $value );
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			update_post_meta( $post_id, $key, $value );
		}
	}

	public static function add_case_metabox(): void {
		add_meta_box( 'f27-case-details', 'Параметры проекта', array( self::class, 'render_case_metabox' ), 'f27_case', 'normal', 'high' );
	}

	public static function render_case_metabox( WP_Post $post ): void {
		wp_nonce_field( 'f27_save_case', 'f27_case_nonce' );
		$fields = array(
			'f27_case_location'     => array( 'Город', 'text' ),
			'f27_case_area'         => array( 'Площадь', 'text' ),
			'f27_case_year'         => array( 'Год', 'number' ),
			'f27_case_before_image' => array( 'URL изображения «до»', 'url' ),
			'f27_case_after_image'  => array( 'URL изображения «после»', 'url' ),
		);
		?>
		<table class="form-table" role="presentation"><tbody>
		<?php foreach ( $fields as $key => $field ) : ?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
				<td><input class="regular-text" type="<?php echo esc_attr( $field[1] ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, $key, true ) ); ?>"<?php echo 'number' === $field[1] ? ' min="1900" max="2200" step="1"' : ''; ?>></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<p class="description">Карточка использует оба URL для сравнения сцен. Если они пусты, задайте изображения здесь или повторите seed с флагом <code>--force</code>.</p>
		<?php
	}

	public static function save_case_metabox( int $post_id ): void {
		if ( ! self::can_save_metabox( $post_id, 'f27_case_nonce', 'f27_save_case' ) ) {
			return;
		}
		$fields = array(
			'f27_case_location'     => 'text',
			'f27_case_area'         => 'text',
			'f27_case_year'         => 'integer',
			'f27_case_before_image' => 'url',
			'f27_case_after_image'  => 'url',
		);
		foreach ( $fields as $key => $kind ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by can_save_metabox() before this loop.
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			if ( 'integer' === $kind ) {
				$value = absint( $value );
			} elseif ( 'url' === $kind ) {
				$value = esc_url_raw( (string) $value );
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			update_post_meta( $post_id, $key, $value );
		}
	}

	private static function can_save_metabox( int $post_id, string $nonce_key, string $action ): bool {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is the nonce verification helper itself.
		if ( ! isset( $_POST[ $nonce_key ] ) || ! is_string( $_POST[ $nonce_key ] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The value is verified immediately below.
		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) );
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	public static function add_request_metabox(): void {
		add_meta_box( 'f27-request-details', 'Состояние заявки', array( self::class, 'render_request_metabox' ), 'f27_request', 'normal', 'high' );
	}

	public static function render_request_metabox( WP_Post $post ): void {
		wp_nonce_field( 'f27_save_request', 'f27_request_nonce' );
		$status    = (string) get_post_meta( $post->ID, 'f27_request_status', true );
		$notes     = (string) get_post_meta( $post->ID, 'f27_request_notes', true );
		$items     = get_post_meta( $post->ID, 'f27_request_items', true );
		$items     = is_array( $items ) ? $items : array();
		$message   = (string) get_post_meta( $post->ID, 'f27_request_message', true );
		$delivery  = (string) get_post_meta( $post->ID, 'f27_request_delivery_state', true );
		$mail_sent = (string) get_post_meta( $post->ID, 'f27_request_mail_sent', true );
		$readonly  = array(
			'Имя'                    => (string) get_post_meta( $post->ID, 'f27_request_name', true ),
			'Компания'               => (string) get_post_meta( $post->ID, 'f27_request_company', true ),
			'Электронная почта'      => (string) get_post_meta( $post->ID, 'f27_request_email', true ),
			'Телефон'                => (string) get_post_meta( $post->ID, 'f27_request_phone', true ),
			'Согласие получено'      => (string) get_post_meta( $post->ID, 'f27_request_consent', true ),
			'Письмо принято wp_mail' => '' === $mail_sent ? 'нет данных' : ( '1' === $mail_sent ? 'да' : 'нет' ),
			'Доставка'               => array(
				'accepted' => 'передано почтовому транспорту',
				'sent'     => 'передано почтовому транспорту',
				'failed'   => 'ошибка передачи',
				'pending'  => 'ожидает передачи',
			)[ $delivery ] ?? ( '' !== $delivery ? $delivery : 'нет данных' ),
		);
		?>
		<table class="widefat striped" style="margin-bottom:1rem"><tbody>
		<?php foreach ( $readonly as $label => $value ) : ?>
			<tr><th scope="row" style="width:220px"><?php echo esc_html( $label ); ?></th><td><?php echo '' !== $value ? esc_html( $value ) : '<span aria-label="не указано">-</span>'; ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<p>
			<label for="f27-request-status"><strong><?php esc_html_e( 'Статус', 'form27' ); ?></strong></label><br>
			<select id="f27-request-status" name="f27_request_status">
				<?php foreach ( self::request_statuses() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( '' !== $status ? $status : 'new', $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="f27-request-notes"><strong><?php esc_html_e( 'Заметки', 'form27' ); ?></strong></label><br>
			<textarea id="f27-request-notes" name="f27_request_notes" rows="5" style="width:100%"><?php echo esc_textarea( $notes ); ?></textarea>
		</p>
		<?php
		if ( '' !== $message ) :
			?>
			<p><strong><?php esc_html_e( 'Комментарий клиента', 'form27' ); ?></strong><br><?php echo nl2br( esc_html( $message ) ); ?></p><?php endif; ?>
		<?php if ( $items ) : ?>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Позиция', 'form27' ); ?></th><th><?php esc_html_e( 'SKU', 'form27' ); ?></th><th><?php esc_html_e( 'Конфигурация', 'form27' ); ?></th><th><?php esc_html_e( 'Количество', 'form27' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $items as $item ) : ?>
					<?php $options = isset( $item['options'] ) && is_array( $item['options'] ) ? implode( ' / ', array_map( 'strval', $item['options'] ) ) : ''; ?>
					<tr><td><?php echo esc_html( (string) ( $item['name'] ?? '' ) ); ?></td><td><code><?php echo esc_html( (string) ( $item['sku'] ?? '' ) ); ?></code></td><td><?php echo esc_html( $options ); ?></td><td><?php echo esc_html( (string) ( $item['quantity'] ?? 1 ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	public static function save_request_metabox( int $post_id ): void {
		if ( ! isset( $_POST['f27_request_nonce'] ) || ! is_string( $_POST['f27_request_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f27_request_nonce'] ) ), 'f27_save_request' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status   = isset( $_POST['f27_request_status'] ) ? sanitize_key( wp_unslash( $_POST['f27_request_status'] ) ) : 'new';
		$statuses = self::request_statuses();
		update_post_meta( $post_id, 'f27_request_status', isset( $statuses[ $status ] ) ? $status : 'new' );
		$notes = isset( $_POST['f27_request_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['f27_request_notes'] ) ) : '';
		update_post_meta( $post_id, 'f27_request_notes', $notes );
	}

	/** @return array<string,string> */
	public static function request_statuses(): array {
		return array(
			'new'         => 'Новая',
			'in_progress' => 'В работе',
			'closed'      => 'Закрыта',
		);
	}

	public static function request_columns( array $columns ): array {
		return array(
			'cb'          => $columns['cb'] ?? '<input type="checkbox">',
			'title'       => 'Заявка',
			'f27_status'  => 'Статус',
			'f27_contact' => 'Контакт',
			'date'        => 'Дата',
		);
	}

	public static function render_request_column( string $column, int $post_id ): void {
		if ( 'f27_status' === $column ) {
			$status = (string) get_post_meta( $post_id, 'f27_request_status', true );
			echo esc_html( self::request_statuses()[ $status ] ?? self::request_statuses()['new'] );
		}
		if ( 'f27_contact' === $column ) {
			$email = (string) get_post_meta( $post_id, 'f27_request_email', true );
			$phone = (string) get_post_meta( $post_id, 'f27_request_phone', true );
			echo esc_html( implode( ' / ', array_filter( array( $email, $phone ) ) ) );
		}
	}

	public static function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( 'f27_cleanup_requests' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'f27_cleanup_requests' );
		}
	}

	public static function cleanup_requests(): void {
		$days = max( 1, (int) apply_filters( 'f27_request_retention_days', 30 ) );
		$ids  = get_posts(
			array(
				'post_type'      => 'f27_request',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'before'    => gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) ),
						'inclusive' => true,
						'column'    => 'post_date_gmt',
					),
				),
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/** @param array<string,array<string,mixed>> $exporters Existing exporters. */
	public static function privacy_exporters( array $exporters ): array {
		$exporters['form27-requests'] = array(
			'exporter_friendly_name' => 'Заявки FORM 27',
			'callback'               => array( self::class, 'export_personal_data' ),
		);
		return $exporters;
	}

	/** @return array{data:array<int,array<string,mixed>>,done:bool} */
	public static function export_personal_data( string $email, int $page = 1 ): array {
		$posts = get_posts(
			array(
				'post_type'      => 'f27_request',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'paged'          => max( 1, $page ),
				'meta_key'       => 'f27_request_email',
				'meta_value'     => sanitize_email( $email ),
			)
		);
		$data  = array();
		foreach ( $posts as $post ) {
			$data[] = array(
				'group_id'    => 'form27-requests',
				'group_label' => 'Заявки FORM 27',
				'item_id'     => 'f27-request-' . $post->ID,
				'data'        => array(
					array(
						'name'  => 'Имя',
						'value' => get_post_meta( $post->ID, 'f27_request_name', true ),
					),
					array(
						'name'  => 'Email',
						'value' => get_post_meta( $post->ID, 'f27_request_email', true ),
					),
					array(
						'name'  => 'Телефон',
						'value' => get_post_meta( $post->ID, 'f27_request_phone', true ),
					),
					array(
						'name'  => 'Компания',
						'value' => get_post_meta( $post->ID, 'f27_request_company', true ),
					),
					array(
						'name'  => 'Комментарий',
						'value' => get_post_meta( $post->ID, 'f27_request_message', true ),
					),
					array(
						'name'  => 'Дата',
						'value' => get_the_date( DATE_ATOM, $post ),
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => count( $posts ) < 50,
		);
	}

	/** @param array<string,array<string,mixed>> $erasers Existing erasers. */
	public static function privacy_erasers( array $erasers ): array {
		$erasers['form27-requests'] = array(
			'eraser_friendly_name' => 'Заявки FORM 27',
			'callback'             => array( self::class, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/** @return array{items_removed:bool,items_retained:bool,messages:string[],done:bool} */
	public static function erase_personal_data( string $email, int $page = 1 ): array {
		unset( $page );
		$ids = get_posts(
			array(
				'post_type'      => 'f27_request',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_key'       => 'f27_request_email',
				'meta_value'     => sanitize_email( $email ),
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
		return array(
			'items_removed'  => ! empty( $ids ),
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $ids ) < 50,
		);
	}
}

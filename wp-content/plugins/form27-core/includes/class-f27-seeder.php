<?php
/**
 * Idempotent demo content seeder.
 *
 * @package Form27
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class F27_Seeder {
	private const CORE_SAMPLE_PAGE_HASH = 'bdd3f0e81bba85fbadd3613f68d15f8f5b06aa2745bbd51a74cf6d2ec440072f';
	private const MIGRATION_VERSION     = '2026-08-09.3';
	private const MIGRATION_OPTION      = 'f27_migration_version';
	private const MEDIA_MARKER          = '_f27_seed_asset';

	public static function maybe_seed(): void {
		if ( F27_CORE_VERSION !== get_option( 'f27_seed_version' ) ) {
			self::seed( false, true );
		}
		if ( self::MIGRATION_VERSION === get_option( self::MIGRATION_OPTION ) ) {
			return;
		}
		if ( ! self::theme_assets_available() ) {
			return;
		}

		$result = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);
		self::maybe_draft_core_sample_page();
		self::seed_home( false, $result );
		self::migrate_support_pages( $result );
		$media_ready = self::migrate_seeded_media( $result );
		if ( $media_ready && empty( $result['errors'] ) ) {
			update_option( self::MIGRATION_OPTION, self::MIGRATION_VERSION, false );
		}
	}

	/**
	 * Seed demo content without touching edited entries unless forced.
	 *
	 * @return array{created:int,updated:int,skipped:int,errors:string[]}
	 */
	public static function seed( bool $force = false, bool $include_home = true ): array {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		self::maybe_draft_core_sample_page();
		self::seed_terms();
		foreach ( self::products() as $product ) {
			self::upsert( $product, 'f27_product', $force, $result );
		}
		foreach ( self::cases() as $case ) {
			self::upsert( $case, 'f27_case', $force, $result );
		}
		if ( $include_home ) {
			self::seed_home( $force, $result );
			if ( ! $force ) {
				self::migrate_support_pages( $result );
			}
		}

		if ( $include_home && empty( $result['errors'] ) ) {
			update_option( 'f27_seed_version', F27_CORE_VERSION, false );
		}
		return $result;
	}

	/**
	 * Draft only the untouched English sample page created by WordPress core.
	 */
	private static function maybe_draft_core_sample_page(): void {
		$page = get_page_by_path( 'sample-page', OBJECT, 'page' );
		if ( ! $page instanceof WP_Post || 2 !== $page->ID || 'publish' !== $page->post_status ) {
			return;
		}

		$normalized_content = str_replace( admin_url(), '{{ADMIN_URL}}', $page->post_content, $admin_url_count );
		if ( 1 !== $admin_url_count ) {
			return;
		}

		$expected             = array(
			'slug'         => 'sample-page',
			'title'        => 'Sample Page',
			'content_hash' => self::CORE_SAMPLE_PAGE_HASH,
		);
		$actual               = array(
			'slug'         => $page->post_name,
			'title'        => $page->post_title,
			'content_hash' => hash( 'sha256', $normalized_content ),
		);
		$expected_fingerprint = hash( 'sha256', (string) wp_json_encode( $expected, JSON_UNESCAPED_SLASHES ) );
		$actual_fingerprint   = hash( 'sha256', (string) wp_json_encode( $actual, JSON_UNESCAPED_SLASHES ) );

		if ( ! hash_equals( $expected_fingerprint, $actual_fingerprint ) ) {
			return;
		}
		if ( $page->post_date !== $page->post_modified || $page->post_date_gmt !== $page->post_modified_gmt ) {
			return;
		}
		if ( '' !== $page->post_excerpt || '' !== $page->post_password || 'closed' !== $page->comment_status || 0 !== (int) $page->post_parent ) {
			return;
		}
		if ( get_option( 'home' ) . '/?page_id=2' !== $page->guid ) {
			return;
		}
		if ( 'default' !== (string) get_post_meta( $page->ID, '_wp_page_template', true ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'          => $page->ID,
				'post_status' => 'draft',
			)
		);
	}

	/**
	 * Register WP CLI command: wp form27 seed [--force].
	 */
	public static function register_cli(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command(
			'form27 seed',
			static function ( array $args, array $assoc_args ): void {
				$result = self::seed( isset( $assoc_args['force'] ) );
				foreach ( $result['errors'] as $error ) {
					\WP_CLI::warning( $error );
				}
				\WP_CLI::success(
					sprintf(
						'FORM 27: created %d, updated %d, skipped %d.',
						$result['created'],
						$result['updated'],
						$result['skipped']
					)
				);
			},
			array(
				'shortdesc' => 'Seed FORM 27 demo products and cases.',
				'synopsis'  => array(
					array(
						'type'        => 'flag',
						'name'        => 'force',
						'optional'    => true,
						'description' => 'Update existing seeded entries by slug.',
					),
				),
			)
		);
	}

	private static function seed_terms(): void {
		$terms = array(
			'f27_collection'  => array(
				'system-48' => 'SYSTEM 48',
				'cut'       => 'CUT',
				'object'    => 'OBJECT',
			),
			'f27_mounting'    => array(
				'track'    => 'Трековый',
				'recessed' => 'Встраиваемый',
				'pendant'  => 'Подвесной',
				'wall'     => 'Настенный',
			),
			'f27_application' => array(
				'retail'      => 'Ритейл',
				'office'      => 'Офис',
				'hospitality' => 'Гостеприимство',
				'residential' => 'Жилой интерьер',
				'gallery'     => 'Галерея',
			),
		);

		foreach ( $terms as $taxonomy => $values ) {
			foreach ( $values as $slug => $name ) {
				if ( ! term_exists( $slug, $taxonomy ) ) {
					wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
				}
			}
		}
	}

	/**
	 * Create an editable front page from the theme pattern after patterns register.
	 *
	 * @param array{created:int,updated:int,skipped:int,errors:string[]}|null $result Result accumulator.
	 */
	public static function seed_home( bool $force = false, ?array &$result = null ): void {
		if ( null === $result ) {
			$page_result = array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'errors'  => array(),
			);
			self::seed_support_pages( $force, $page_result );
		} else {
			self::seed_support_pages( $force, $result );
		}

		$existing         = get_page_by_path( 'home', OBJECT, 'page' );
		$pattern_content  = self::home_pattern_content();
		$fallback_content = self::fallback_home_content();
		if ( $existing instanceof WP_Post && ! $force ) {
			$seed_source        = (string) get_post_meta( $existing->ID, '_f27_seed_source', true );
			$untouched_fallback = 'Главная' === $existing->post_title
				&& $fallback_content === $existing->post_content
				&& ( 'fallback' === $seed_source || (
					(int) get_option( 'page_on_front' ) === $existing->ID
					&& $existing->post_date === $existing->post_modified
					&& $existing->post_date_gmt === $existing->post_modified_gmt
				) );
			if ( ! $untouched_fallback || '' === trim( $pattern_content ) ) {
				if ( is_array( $result ) ) {
					++$result['skipped'];
				}
				return;
			}
		}

		$content = '' !== trim( $pattern_content ) ? $pattern_content : $fallback_content;

		$postarr = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => 'home',
			'post_title'   => 'Главная',
			'post_content' => $content,
		);
		if ( $existing instanceof WP_Post ) {
			$postarr['ID'] = $existing->ID;
		}
		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			if ( is_array( $result ) ) {
				$result['errors'][] = $post_id->get_error_message();
			}
			return;
		}
		update_post_meta( (int) $post_id, '_f27_seed_source', $content === $pattern_content ? 'pattern' : 'fallback' );

		if ( ! ( $existing instanceof WP_Post ) && ! get_option( 'page_on_front' ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $post_id );
		}
		if ( is_array( $result ) ) {
			if ( $existing instanceof WP_Post ) {
				++$result['updated'];
			} else {
				++$result['created'];
			}
		}
	}

	/**
	 * Resolve the active theme pattern after normal registration has completed.
	 */
	private static function home_pattern_content(): string {
		if ( class_exists( 'WP_Block_Patterns_Registry' ) ) {
			$registry = WP_Block_Patterns_Registry::get_instance();
			if ( $registry->is_registered( 'form27/home' ) ) {
				$pattern = $registry->get_registered( 'form27/home' );
				$content = is_array( $pattern ) ? (string) ( $pattern['content'] ?? '' ) : '';
				if ( self::is_complete_home_pattern( $content ) ) {
					return $content;
				}
			}
		}

		$active_themes = array_filter( array( get_stylesheet(), get_template() ) );
		if ( ! in_array( 'form27', $active_themes, true ) ) {
			return '';
		}
		$pattern_file = get_theme_file_path( 'patterns/home.php' );
		if ( ! is_readable( $pattern_file ) ) {
			return '';
		}

		ob_start();
		try {
			include $pattern_file;
			$content = (string) ob_get_clean();
			return self::is_complete_home_pattern( $content ) ? $content : '';
		} catch ( Throwable $error ) {
			ob_end_clean();
			return '';
		}
	}

	private static function is_complete_home_pattern( string $content ): bool {
		$required_blocks = array(
			'wp:form27/catalog',
			'wp:form27/configurator',
			'wp:form27/cases',
			'wp:form27/project-tray',
			'wp:form27/request-form',
		);
		foreach ( $required_blocks as $block_name ) {
			if ( ! str_contains( $content, $block_name ) ) {
				return false;
			}
		}

		return substr_count( $content, '"tagName":"section"' ) >= 7;
	}

	private static function fallback_home_content(): string {
		return '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} --><main class="wp-block-group"><!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Свет, собранный по системе</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Архитектурные светильники с точной оптикой и понятной спецификацией.</p><!-- /wp:paragraph --><!-- wp:form27/catalog {"title":""} /--><!-- wp:form27/configurator {"title":""} /--><!-- wp:form27/cases {"title":""} /--><!-- wp:form27/project-tray {"title":""} /--><!-- wp:form27/request-form {"title":""} /--></main><!-- /wp:group -->';
	}

	/**
	 * Seed editable pages that back navigation routes. Catalog and projects use CPT archives.
	 *
	 * @param array{created:int,updated:int,skipped:int,errors:string[]} $result Result accumulator.
	 */
	private static function seed_support_pages( bool $force, array &$result ): void {
		foreach ( self::support_pages() as $page ) {
			self::upsert( $page, 'page', $force, $result );
		}
	}

	/** @return array<int,array<string,string>> */
	private static function support_pages(): array {
		return array(
			array(
				'slug'           => 'specification',
				'title'          => 'Спецификация',
				'excerpt'        => 'Сохранённые модели и отправка проекта.',
				'content'        => '<!-- wp:paragraph --><p>Соберите выбранные модели, проверьте конфигурации и сохраните печатную версию.</p><!-- /wp:paragraph --><!-- wp:form27/project-tray {"title":""} /--><!-- wp:form27/request-form {"title":""} /-->',
				'legacy_content' => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Спецификация проекта</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Соберите выбранные модели, проверьте конфигурации и сохраните печатную версию.</p><!-- /wp:paragraph --><!-- wp:form27/project-tray {"title":""} /--><!-- wp:form27/request-form {"title":""} /-->',
			),
			array(
				'slug'           => 'contacts',
				'title'          => 'Контакты',
				'excerpt'        => 'Публичные контакты демонстрационного светового бренда.',
				'legacy_excerpt' => 'Контакты демонстрационного бренда FORM 27.',
				'content'        => '<!-- wp:paragraph --><p><strong>[form27_brand]</strong> является демонстрационным брендом. Для проверки интерфейса используйте форму спецификации.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Электронная почта: [form27_email_link]<br>Телефон: [form27_phone_link]</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>[form27_disclaimer]</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="../specification/">Открыть спецификацию</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
				'legacy_content' => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Обсудить световой проект</h1><!-- /wp:heading --><!-- wp:paragraph --><p>FORM 27 является демонстрационным брендом. Для проверки интерфейса используйте форму спецификации. Письма для демо: <a href="mailto:hello@form27.demo">hello@form27.demo</a>.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="../specification/">Открыть спецификацию</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
			),
			array(
				'slug'           => 'privacy',
				'title'          => 'Обработка данных',
				'excerpt'        => 'Как демонстрационный сайт работает с данными.',
				'legacy_excerpt' => 'Как демо FORM 27 работает с данными.',
				'content'        => '<!-- wp:paragraph --><p>На статическом демосайте введённые данные не отправляются и не сохраняются. Подборка светильников хранится только в локальном хранилище браузера и удаляется кнопкой «Очистить».</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Во временной WordPress-версии заявка сохраняется в закрытом разделе панели управления на срок до 30 дней. Администратор может экспортировать или удалить данные по адресу электронной почты через штатные инструменты WordPress.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>[form27_disclaimer]</p><!-- /wp:paragraph -->',
				'legacy_content' => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Обработка данных</h1><!-- /wp:heading --><!-- wp:paragraph --><p>На статическом демосайте введённые данные не отправляются и не сохраняются. Подборка светильников хранится только в локальном хранилище браузера и удаляется кнопкой «Очистить».</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Во временной WordPress-версии заявка сохраняется в закрытом разделе панели управления на срок до 30 дней. Администратор может экспортировать или удалить данные по адресу электронной почты через штатные инструменты WordPress.</p><!-- /wp:paragraph -->',
			),
		);
	}

	/** @param array{created:int,updated:int,skipped:int,errors:string[]} $result Result accumulator. */
	private static function migrate_support_pages( array &$result ): void {
		foreach ( self::support_pages() as $page ) {
			$existing = get_page_by_path( $page['slug'], OBJECT, 'page' );
			if ( ! $existing instanceof WP_Post || $existing->post_content !== $page['legacy_content'] ) {
				continue;
			}
			$legacy_excerpt = $page['legacy_excerpt'] ?? $page['excerpt'];
			if ( $existing->post_title !== $page['title'] || $existing->post_excerpt !== $legacy_excerpt || 'publish' !== $existing->post_status ) {
				continue;
			}
			$updated = wp_update_post(
				wp_slash(
					array(
						'ID'           => $existing->ID,
						'post_content' => $page['content'],
						'post_excerpt' => $page['excerpt'],
					)
				),
				true
			);
			if ( is_wp_error( $updated ) ) {
				$result['errors'][] = $updated->get_error_message();
			} else {
				++$result['updated'];
			}
		}
	}

	/**
	 * @param array<string,mixed>                                        $entry Entry data.
	 * @param array{created:int,updated:int,skipped:int,errors:string[]} $result Result accumulator.
	 */
	private static function upsert( array $entry, string $post_type, bool $force, array &$result ): void {
		$existing = get_page_by_path( (string) $entry['slug'], OBJECT, $post_type );
		if ( $existing instanceof WP_Post && ! $force ) {
			self::seed_media_for_post( $existing->ID, $post_type, $entry, false, $result );
			++$result['skipped'];
			return;
		}

		$postarr = array(
			'post_type'    => $post_type,
			'post_status'  => 'publish',
			'post_name'    => $entry['slug'],
			'post_title'   => $entry['title'],
			'post_excerpt' => $entry['excerpt'],
			'post_content' => $entry['content'],
			'menu_order'   => (int) ( $entry['order'] ?? 0 ),
		);
		if ( $existing instanceof WP_Post ) {
			$postarr['ID'] = $existing->ID;
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			$result['errors'][] = $post_id->get_error_message();
			return;
		}

		foreach ( (array) ( $entry['meta'] ?? array() ) as $key => $value ) {
			update_post_meta( (int) $post_id, (string) $key, $value );
		}
		foreach ( (array) ( $entry['terms'] ?? array() ) as $taxonomy => $slugs ) {
			wp_set_object_terms( (int) $post_id, $slugs, (string) $taxonomy, false );
		}
		self::seed_media_for_post( (int) $post_id, $post_type, $entry, $force, $result );

		if ( $existing instanceof WP_Post ) {
			++$result['updated'];
		} else {
			++$result['created'];
		}
	}

	private static function theme_assets_available(): bool {
		if ( ! in_array( 'form27', array_filter( array( get_stylesheet(), get_template() ) ), true ) ) {
			return false;
		}
		foreach ( array_merge( self::products(), self::cases() ) as $entry ) {
			if ( empty( $entry['image'] ) || ! is_readable( get_theme_file_path( 'assets/images/' . $entry['image'] ) ) ) {
				return false;
			}
		}
		return true;
	}

	/** @param array{created:int,updated:int,skipped:int,errors:string[]} $result Result accumulator. */
	private static function migrate_seeded_media( array &$result ): bool {
		$ready = true;
		foreach ( array(
			'f27_product' => self::products(),
			'f27_case'    => self::cases(),
		) as $post_type => $entries ) {
			foreach ( $entries as $entry ) {
				$post = get_page_by_path( (string) $entry['slug'], OBJECT, $post_type );
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				if ( ! self::seed_media_for_post( $post->ID, $post_type, $entry, false, $result ) ) {
					$ready = false;
				}
			}
		}
		return $ready;
	}

	/**
	 * Import one theme image and only fill empty seeded media fields unless forced.
	 *
	 * @param array<string,mixed>                                        $entry Seed entry.
	 * @param array{created:int,updated:int,skipped:int,errors:string[]} $result Result accumulator.
	 */
	private static function seed_media_for_post( int $post_id, string $post_type, array $entry, bool $force, array &$result ): bool {
		$filename = sanitize_file_name( (string) ( $entry['image'] ?? '' ) );
		if ( '' === $filename ) {
			return true;
		}
		$source = get_theme_file_path( 'assets/images/' . $filename );
		if ( ! is_readable( $source ) ) {
			return false;
		}

		$attachment_id = self::seed_attachment( $filename, (string) $entry['title'], $source );
		if ( is_wp_error( $attachment_id ) ) {
			$result['errors'][] = $attachment_id->get_error_message();
			return false;
		}
		$url = (string) wp_get_attachment_url( $attachment_id );
		if ( '' === $url ) {
			$result['errors'][] = sprintf( 'Не удалось получить URL изображения %s.', $filename );
			return false;
		}

		$had_thumbnail = has_post_thumbnail( $post_id );
		if ( $force || ! $had_thumbnail ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
		$preferred_url = ! $force && $had_thumbnail ? (string) get_the_post_thumbnail_url( $post_id, 'full' ) : $url;
		if ( '' === $preferred_url ) {
			$preferred_url = $url;
		}
		if ( 'f27_product' === $post_type ) {
			if ( $force || '' === trim( (string) get_post_meta( $post_id, 'f27_image_url', true ) ) ) {
				update_post_meta( $post_id, 'f27_image_url', esc_url_raw( $preferred_url ) );
			}
		} elseif ( 'f27_case' === $post_type ) {
			foreach ( array( 'f27_case_before_image', 'f27_case_after_image' ) as $meta_key ) {
				if ( $force || '' === trim( (string) get_post_meta( $post_id, $meta_key, true ) ) ) {
					update_post_meta( $post_id, $meta_key, esc_url_raw( $preferred_url ) );
				}
			}
		}
		return true;
	}

	/** @return int|WP_Error */
	private static function seed_attachment( string $filename, string $title, string $source ) {
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::MEDIA_MARKER,
				'meta_value'     => $filename,
			)
		);
		if ( $existing ) {
			return (int) $existing[0];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Trusted local theme asset, not a URL.
		$contents = file_get_contents( $source );
		if ( false === $contents ) {
			return new WP_Error( 'f27_media_read', sprintf( 'Не удалось прочитать изображение %s.', $filename ) );
		}
		$upload = wp_upload_bits( $filename, null, $contents );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'f27_media_upload', (string) $upload['error'] );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$filetype      = wp_check_filetype( (string) $upload['file'], null );
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => (string) ( $filetype['type'] ?? 'image/webp' ),
				'post_title'     => sanitize_text_field( $title ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'guid'           => esc_url_raw( (string) $upload['url'] ),
			),
			(string) $upload['file'],
			0,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		$metadata = wp_generate_attachment_metadata( (int) $attachment_id, (string) $upload['file'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( (int) $attachment_id, $metadata );
		}
		update_post_meta( (int) $attachment_id, self::MEDIA_MARKER, $filename );
		update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );
		return (int) $attachment_id;
	}

	/** @return array<int,array<string,mixed>> */
	public static function products(): array {
		$system_options = array(
			'f27_cct'      => array( 2700, 3000, 4000 ),
			'f27_cri'      => array( 90, 95 ),
			'f27_finishes' => array( 'Чёрный RAL 9005', 'Белый RAL 9003' ),
			'f27_controls' => array( 'On/Off', 'DALI-2' ),
		);
		$object_options = array(
			'f27_cct'      => array( 2700, 3000 ),
			'f27_cri'      => array( 95 ),
			'f27_finishes' => array( 'Графит', 'Тёмная бронза', 'Анодированный алюминий' ),
			'f27_controls' => array( 'TRIAC', 'DALI-2' ),
		);

		return array(
			array(
				'slug'    => 'line-s48',
				'image'   => 'product-line-s48.webp',
				'title'   => 'LINE S48',
				'excerpt' => 'Линейный модуль для равномерного света в магнитной системе 48 В.',
				'content' => '<!-- wp:paragraph --><p>Непрерывная световая линия для рабочих поверхностей, навигации и общего освещения.</p><!-- /wp:paragraph -->',
				'order'   => 10,
				'meta'    => array_merge(
					$system_options,
					array(
						'f27_code'       => 'S48-LINE',
						'f27_dimensions' => '600 / 1200 мм',
						'f27_wattages'   => array( 16, 32 ),
						'f27_lumens'     => array( 1450, 2900 ),
						'f27_beams'      => array( '105°' ),
						'f27_ip'         => 'IP20',
						'f27_price'      => 18900,
						'f27_featured'   => true,
					)
				),
				'terms'   => array(
					'f27_collection'  => array( 'system-48' ),
					'f27_mounting'    => array( 'track' ),
					'f27_application' => array( 'office', 'retail' ),
				),
			),
			array(
				'slug'    => 'spot-s48',
				'image'   => 'product-spot-s48.webp',
				'title'   => 'SPOT S48',
				'excerpt' => 'Поворотный акцентный модуль для экспозиций и архитектурных деталей.',
				'content' => '<!-- wp:paragraph --><p>Корпус поворачивается на 355 градусов и наклоняется на 90 градусов.</p><!-- /wp:paragraph -->',
				'order'   => 20,
				'meta'    => array_merge(
					$system_options,
					array(
						'f27_code'       => 'S48-SPOT',
						'f27_dimensions' => 'Ø45 × 135 мм',
						'f27_wattages'   => array( 12, 18 ),
						'f27_lumens'     => array( 980, 1500 ),
						'f27_beams'      => array( '24°', '36°' ),
						'f27_ip'         => 'IP20',
						'f27_price'      => 24500,
						'f27_featured'   => true,
					)
				),
				'terms'   => array(
					'f27_collection'  => array( 'system-48' ),
					'f27_mounting'    => array( 'track' ),
					'f27_application' => array( 'retail', 'gallery' ),
				),
			),
			array(
				'slug'    => 'down-c60',
				'image'   => 'product-down-c60.webp',
				'title'   => 'DOWN C60',
				'excerpt' => 'Компактный downlight с рамкой или безрамочным монтажом.',
				'content' => '<!-- wp:paragraph --><p>Чистая потолочная геометрия и сменная оптика для жилых и общественных пространств.</p><!-- /wp:paragraph -->',
				'order'   => 30,
				'meta'    => array_merge(
					$system_options,
					array(
						'f27_code'       => 'C60-DOWN',
						'f27_dimensions' => 'Ø82 × 78 мм, вырез Ø72 мм',
						'f27_wattages'   => array( 9, 13 ),
						'f27_lumens'     => array( 720, 1050 ),
						'f27_beams'      => array( '24°', '36°', '55°' ),
						'f27_ip'         => 'IP44 со стороны помещения',
						'f27_price'      => 15900,
						'f27_featured'   => false,
					)
				),
				'terms'   => array(
					'f27_collection'  => array( 'cut' ),
					'f27_mounting'    => array( 'recessed' ),
					'f27_application' => array( 'residential', 'hospitality' ),
				),
			),
			array(
				'slug'    => 'wash-c80',
				'image'   => 'product-wash-c80.webp',
				'title'   => 'WASH C80',
				'excerpt' => 'Встраиваемый wallwasher с асимметричной оптикой 30 × 60 градусов.',
				'content' => '<!-- wp:paragraph --><p>Равномерно освещает стены и вертикальные экспозиции, наклон оптической части до 20 градусов.</p><!-- /wp:paragraph -->',
				'order'   => 40,
				'meta'    => array_merge(
					$system_options,
					array(
						'f27_code'       => 'C80-WASH',
						'f27_dimensions' => '86 × 86 × 98 мм',
						'f27_wattages'   => array( 14, 20 ),
						'f27_lumens'     => array( 950, 1380 ),
						'f27_beams'      => array( '30×60°' ),
						'f27_ip'         => 'IP20',
						'f27_price'      => 27300,
						'f27_featured'   => false,
					)
				),
				'terms'   => array(
					'f27_collection'  => array( 'cut' ),
					'f27_mounting'    => array( 'recessed' ),
					'f27_application' => array( 'gallery', 'hospitality' ),
				),
			),
			array(
				'slug'    => 'arc-o1200',
				'image'   => 'product-arc-o1200.webp',
				'title'   => 'ARC O1200',
				'excerpt' => 'Подвесной светильник для переговорных, лобби и длинных столов.',
				'content' => '<!-- wp:paragraph --><p>Мягкий прямой свет, тонкий алюминиевый профиль и регулируемый подвес от 0,4 до 2,5 метра.</p><!-- /wp:paragraph -->',
				'order'   => 50,
				'meta'    => array_merge(
					$object_options,
					array(
						'f27_code'       => 'O-ARC',
						'f27_dimensions' => '1200 / 1800 × 42 × 65 мм',
						'f27_wattages'   => array( 32, 48 ),
						'f27_lumens'     => array( 2300, 3500 ),
						'f27_beams'      => array( '110°' ),
						'f27_ip'         => 'IP20',
						'f27_price'      => 46500,
						'f27_featured'   => true,
					)
				),
				'terms'   => array(
					'f27_collection'  => array( 'object' ),
					'f27_mounting'    => array( 'pendant' ),
					'f27_application' => array( 'office', 'hospitality' ),
				),
			),
			array(
				'slug'    => 'fold-o600',
				'image'   => 'product-fold-o600.webp',
				'title'   => 'FOLD O600',
				'excerpt' => 'Настенный светильник отражённого света из цельного листа алюминия.',
				'content' => '<!-- wp:paragraph --><p>Складка корпуса скрывает источник и направляет свет на стену и потолок.</p><!-- /wp:paragraph -->',
				'order'   => 60,
				'meta'    => array_merge(
					$object_options,
					array(
						'f27_code'       => 'O-FOLD',
						'f27_dimensions' => '600 / 900 × 118 × 72 мм',
						'f27_wattages'   => array( 18, 28 ),
						'f27_lumens'     => array( 1100, 1750 ),
						'f27_beams'      => array( 'Непрямой' ),
						'f27_ip'         => 'IP20',
						'f27_price'      => 32900,
						'f27_featured'   => false,
					)
				),
				'terms'   => array(
					'f27_collection'  => array( 'object' ),
					'f27_mounting'    => array( 'wall' ),
					'f27_application' => array( 'residential', 'hospitality' ),
				),
			),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function cases(): array {
		return array(
			array(
				'slug'    => 'gallery-27',
				'image'   => 'case-gallery.webp',
				'title'   => 'Галерея 27',
				'excerpt' => 'Акцентный свет без бликов для сменной экспозиции.',
				'content' => '<!-- wp:paragraph --><p>Трековая система SPOT S48 адаптируется к новой развеске без изменения потолка.</p><!-- /wp:paragraph -->',
				'order'   => 10,
				'meta'    => array(
					'f27_case_location' => 'Москва',
					'f27_case_area'     => '240 м²',
					'f27_case_year'     => 2026,
				),
			),
			array(
				'slug'    => 'bureau-6',
				'image'   => 'case-studio.webp',
				'title'   => 'Бюро 6',
				'excerpt' => 'Рабочий свет и спокойная вечерняя сцена в одном проекте.',
				'content' => '<!-- wp:paragraph --><p>LINE S48 даёт равномерный рабочий свет, ARC O1200 выделяет переговорные столы.</p><!-- /wp:paragraph -->',
				'order'   => 20,
				'meta'    => array(
					'f27_case_location' => 'Санкт-Петербург',
					'f27_case_area'     => '510 м²',
					'f27_case_year'     => 2026,
				),
			),
			array(
				'slug'    => 'house-line',
				'image'   => 'case-restaurant.webp',
				'title'   => 'Дом на линии',
				'excerpt' => 'Низкий контраст и тёплый отражённый свет для жилого интерьера.',
				'content' => '<!-- wp:paragraph --><p>DOWN C60 и FOLD O600 формируют несколько спокойных световых сценариев.</p><!-- /wp:paragraph -->',
				'order'   => 30,
				'meta'    => array(
					'f27_case_location' => 'Казань',
					'f27_case_area'     => '178 м²',
					'f27_case_year'     => 2026,
				),
			),
		);
	}
}

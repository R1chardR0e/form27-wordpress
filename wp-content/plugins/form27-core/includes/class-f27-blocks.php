<?php
/**
 * Dynamic Gutenberg blocks.
 *
 * @package Form27
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class F27_Blocks {
	public static function register(): void {
		wp_register_style( 'f27-core-frontend', F27_CORE_URL . 'assets/css/frontend.css', array(), F27_CORE_VERSION );
		wp_register_script( 'f27-core-frontend', F27_CORE_URL . 'assets/js/frontend.js', array(), F27_CORE_VERSION, true );
		wp_register_script(
			'f27-core-editor',
			F27_CORE_URL . 'assets/js/editor.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			F27_CORE_VERSION,
			true
		);

		$config = array(
			'restUrl'       => esc_url_raw( rest_url( 'form27/v1/' ) ),
			'homeUrl'       => esc_url_raw( trailingslashit( wp_make_link_relative( home_url( '/' ) ) ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'staticDemo'    => self::is_static_demo(),
			'demo'          => F27_Settings::is_demo(),
			'projectKey'    => 'form27.project.v1',
			'schemaVersion' => 1,
			'currency'      => 'RUB',
			'brandName'     => F27_Settings::brand_name(),
			'disclaimer'    => F27_Settings::disclaimer(),
		);
		wp_add_inline_script( 'f27-core-frontend', 'window.F27_CONFIG = ' . wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';', 'before' );

		$blocks = array(
			'catalog'      => array(
				'attributes'      => array(
					'title'       => array(
						'type'    => 'string',
						'default' => 'Каталог систем',
					),
					'limit'       => array(
						'type'    => 'number',
						'default' => 6,
					),
					'showFilters' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'render_callback' => array( self::class, 'render_catalog' ),
			),
			'configurator' => array(
				'attributes'      => array(
					'title'              => array(
						'type'    => 'string',
						'default' => 'Соберите светильник',
					),
					'defaultProductSlug' => array(
						'type'    => 'string',
						'default' => 'spot-s48',
					),
				),
				'render_callback' => array( self::class, 'render_configurator' ),
			),
			'project-tray' => array(
				'attributes'      => array(
					'title' => array(
						'type'    => 'string',
						'default' => 'Ваш проект',
					),
				),
				'render_callback' => array( self::class, 'render_project' ),
			),
			'cases'        => array(
				'attributes'      => array(
					'title' => array(
						'type'    => 'string',
						'default' => 'Свет в проектах',
					),
					'limit' => array(
						'type'    => 'number',
						'default' => 3,
					),
				),
				'render_callback' => array( self::class, 'render_cases' ),
			),
			'request-form' => array(
				'attributes'      => array(
					'title' => array(
						'type'    => 'string',
						'default' => 'Отправить спецификацию',
					),
				),
				'render_callback' => array( self::class, 'render_request_form' ),
			),
		);

		foreach ( $blocks as $name => $settings ) {
			register_block_type(
				'form27/' . $name,
				array_merge(
					$settings,
					array(
						'api_version'   => 3,
						'editor_script' => 'f27-core-editor',
						'style'         => 'f27-core-frontend',
						'view_script'   => 'f27-core-frontend',
					)
				)
			);
		}
	}

	public static function is_static_demo(): bool {
		$constant = defined( 'F27_STATIC_DEMO' ) && true === F27_STATIC_DEMO;
		return (bool) apply_filters( 'f27_static_demo', $constant || '1' === (string) get_option( 'f27_static_demo', '0' ) );
	}

	/** @param array<string,mixed> $attributes Block attributes. */
	public static function render_catalog( array $attributes ): string {
		self::enqueue();
		$limit        = min( 24, max( 1, (int) ( $attributes['limit'] ?? 6 ) ) );
		$products     = F27_Product_Schema::query_products( array( 'posts_per_page' => $limit ) );
		$title        = trim( (string) ( $attributes['title'] ?? 'Каталог систем' ) );
		$filters      = ! isset( $attributes['showFilters'] ) || (bool) $attributes['showFilters'];
		$terms        = get_terms(
			array(
				'taxonomy'   => 'f27_collection',
				'hide_empty' => true,
			)
		);
		$mounting     = get_terms(
			array(
				'taxonomy'   => 'f27_mounting',
				'hide_empty' => true,
			)
		);
		$applications = get_terms(
			array(
				'taxonomy'   => 'f27_application',
				'hide_empty' => true,
			)
		);

		ob_start();
		?>
		<section class="f27-block f27-catalog" data-f27-catalog>
			<?php echo self::heading( $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $filters ) : ?>
				<div class="f27-catalog__toolbar">
					<label class="f27-field f27-field--search">
						<span><?php esc_html_e( 'Поиск по каталогу', 'form27' ); ?></span>
						<input type="search" data-f27-search placeholder="Название или задача" autocomplete="off">
					</label>
					<div class="f27-catalog__filters" role="group" aria-label="<?php esc_attr_e( 'Фильтр по коллекции', 'form27' ); ?>">
						<button class="f27-filter is-active" type="button" data-f27-filter="all" aria-pressed="true"><?php esc_html_e( 'Все системы', 'form27' ); ?></button>
						<?php if ( is_array( $terms ) ) : ?>
							<?php foreach ( $terms as $term ) : ?>
								<button class="f27-filter" type="button" data-f27-filter="<?php echo esc_attr( $term->slug ); ?>" aria-pressed="false"><?php echo esc_html( $term->name ); ?></button>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<div class="f27-catalog__selects">
						<?php echo self::taxonomy_select( 'mounting', 'Монтаж', 'Любой монтаж', $mounting ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::taxonomy_select( 'application', 'Применение', 'Любая задача', $applications ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>
			<?php endif; ?>
			<div class="f27-catalog__grid" data-f27-grid>
				<?php foreach ( $products as $index => $product ) : ?>
					<?php echo self::product_card( $product, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
			<p class="f27-catalog__empty" data-f27-empty hidden><?php esc_html_e( 'По этому запросу ничего не найдено.', 'form27' ); ?></p>
			<?php echo self::json_data( $products ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $attributes Block attributes. */
	public static function render_configurator( array $attributes ): string {
		self::enqueue();
		$products = F27_Product_Schema::query_products();
		if ( ! $products ) {
			return '<p class="f27-notice">Каталог пока пуст.</p>';
		}
		$slug = sanitize_title( (string) ( $attributes['defaultProductSlug'] ?? 'spot-s48' ) );
		if ( is_singular( 'f27_product' ) ) {
			$queried_slug = (string) get_post_field( 'post_name', get_queried_object_id() );
			if ( '' !== $queried_slug ) {
				$slug = $queried_slug;
			}
		}
		$current = $products[0];
		foreach ( $products as $product ) {
			if ( $slug === $product['slug'] ) {
				$current = $product;
				break;
			}
		}
		$title = trim( (string) ( $attributes['title'] ?? 'Соберите светильник' ) );

		ob_start();
		?>
		<section class="f27-block f27-configurator" data-f27-configurator>
			<?php echo self::heading( $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="f27-configurator__stage">
				<div class="f27-configurator__visual" data-f27-visual data-finish="black">
					<div class="f27-fixture" aria-hidden="true"><span></span></div>
					<div class="f27-beam" aria-hidden="true"><span class="f27-beam__cone"></span><span class="f27-beam__floor"></span></div>
					<div class="f27-configurator__readout">
						<span><strong data-f27-spot>2,7 м</strong> <?php esc_html_e( 'световое пятно', 'form27' ); ?></span>
						<span><strong data-f27-height-output>3,0 м</strong> <?php esc_html_e( 'высота', 'form27' ); ?></span>
					</div>
				</div>
				<form class="f27-configurator__controls" data-f27-config-form>
					<label class="f27-field"><span><?php esc_html_e( 'Модель', 'form27' ); ?></span><select name="product">
						<?php foreach ( $products as $product ) : ?>
							<option value="<?php echo esc_attr( $product['slug'] ); ?>" <?php selected( $current['slug'], $product['slug'] ); ?>><?php echo esc_html( $product['name'] ); ?></option>
						<?php endforeach; ?>
					</select></label>
					<div class="f27-configurator__option-grid">
						<?php echo self::option_select( 'power', 'Мощность', $current['wattages'], ' Вт' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::option_select( 'cct', 'Температура', $current['cct'], ' K' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::option_select( 'cri', 'Цветопередача', $current['cri'], '', 'CRI ' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::option_select( 'beam', 'Оптика', $current['beams'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::option_select( 'finish', 'Отделка', $current['finishes'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::option_select( 'control', 'Управление', $current['controls'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<label class="f27-field f27-field--range"><span><?php esc_html_e( 'Высота потолка', 'form27' ); ?></span><input type="range" name="height" min="2" max="6" step="0.1" value="3"></label>
					<div class="f27-configurator__summary" aria-live="polite">
						<div><span><?php esc_html_e( 'Артикул', 'form27' ); ?></span><strong data-f27-sku></strong></div>
						<div><span><?php esc_html_e( 'Световой поток', 'form27' ); ?></span><strong data-f27-lumens></strong></div>
						<div><span><?php esc_html_e( 'Демо-цена', 'form27' ); ?></span><strong data-f27-price></strong></div>
					</div>
					<button class="f27-button" type="submit"><?php esc_html_e( 'Добавить в проект', 'form27' ); ?></button>
					<p class="f27-form-message" data-f27-config-message aria-live="polite"></p>
				</form>
			</div>
			<?php echo self::json_data( $products ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $attributes Block attributes. */
	public static function render_project( array $attributes ): string {
		self::enqueue();
		$title = trim( (string) ( $attributes['title'] ?? 'Ваш проект' ) );
		ob_start();
		?>
		<section class="f27-block f27-project" data-f27-project>
			<div class="f27-project__header">
				<?php echo self::heading( $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="f27-project__count" data-f27-project-count>0 позиций</span>
			</div>
			<p class="f27-project__empty" data-f27-project-empty><?php esc_html_e( 'Добавьте настроенные светильники. Спецификация сохранится в этом браузере.', 'form27' ); ?></p>
			<div class="f27-project__items" data-f27-project-items aria-live="polite"></div>
			<div class="f27-project__footer" data-f27-project-footer hidden>
				<div><span><?php esc_html_e( 'Демо-стоимость', 'form27' ); ?></span><strong data-f27-project-total>0 ₽</strong></div>
				<div class="f27-project__actions">
					<button class="f27-button" type="button" data-f27-print><?php esc_html_e( 'Сохранить PDF', 'form27' ); ?></button>
					<button class="f27-button f27-button--quiet" type="button" data-f27-clear><?php esc_html_e( 'Очистить', 'form27' ); ?></button>
				</div>
			</div>
			<p class="f27-project__status" data-f27-project-status aria-live="polite"></p>
			<p class="f27-disclaimer"><?php esc_html_e( 'PDF создаётся в браузере. Данные проекта никуда не отправляются.', 'form27' ); ?></p>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $attributes Block attributes. */
	public static function render_cases( array $attributes ): string {
		self::enqueue();
		$title = trim( (string) ( $attributes['title'] ?? 'Свет в проектах' ) );
		$limit = min( 12, max( 1, (int) ( $attributes['limit'] ?? 3 ) ) );
		$cases = get_posts(
			array(
				'post_type'      => 'f27_case',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);
		ob_start();
		?>
		<section class="f27-block f27-cases" data-f27-cases>
			<?php echo self::heading( $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="f27-cases__grid">
				<?php foreach ( $cases as $index => $case ) : ?>
					<?php
					$before       = (string) get_post_meta( $case->ID, 'f27_case_before_image', true );
					$after        = (string) get_post_meta( $case->ID, 'f27_case_after_image', true );
					$before_style = $before ? ' style="' . esc_attr( 'background-image:url("' . esc_url_raw( $before ) . '")' ) . '"' : '';
					$after_style  = $after ? ' style="' . esc_attr( 'background-image:url("' . esc_url_raw( $after ) . '")' ) . '"' : '';
					?>
					<article class="f27-case" style="--case-index:<?php echo esc_attr( (string) $index ); ?>">
						<div class="f27-case__compare" data-f27-compare>
						<div class="f27-case__image f27-case__image--before"<?php echo $before_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><span><?php esc_html_e( 'Общий', 'form27' ); ?></span></div>
						<div class="f27-case__image f27-case__image--after" data-f27-after<?php echo $after_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><span><?php esc_html_e( 'Сценарий', 'form27' ); ?></span></div>
							<label><span class="screen-reader-text"><?php esc_html_e( 'Сравнить световые сценарии', 'form27' ); ?></span><input type="range" min="0" max="100" value="54" data-f27-compare-range></label>
						</div>
						<div class="f27-case__body">
							<p class="f27-case__meta"><span><?php echo esc_html( (string) get_post_meta( $case->ID, 'f27_case_location', true ) ); ?></span><span><?php echo esc_html( (string) get_post_meta( $case->ID, 'f27_case_area', true ) ); ?></span><span><?php echo esc_html( (string) get_post_meta( $case->ID, 'f27_case_year', true ) ); ?></span></p>
							<h3><a href="<?php echo esc_url( get_permalink( $case ) ); ?>"><?php echo esc_html( get_the_title( $case ) ); ?></a></h3>
							<p><?php echo esc_html( get_the_excerpt( $case ) ); ?></p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $attributes Block attributes. */
	public static function render_request_form( array $attributes ): string {
		self::enqueue();
		$title = trim( (string) ( $attributes['title'] ?? 'Отправить спецификацию' ) );
		ob_start();
		?>
		<section class="f27-block f27-request" data-f27-request data-static-demo="<?php echo self::is_static_demo() ? 'true' : 'false'; ?>">
			<?php echo self::heading( $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p><?php esc_html_e( 'Приложите собранный проект и оставьте удобный контакт.', 'form27' ); ?></p>
			<form class="f27-request__form" data-f27-request-form hidden>
				<input type="hidden" name="startedAt" value="">
				<div class="f27-request__grid">
					<label class="f27-field"><span><?php esc_html_e( 'Имя', 'form27' ); ?></span><input name="name" required minlength="2" maxlength="100" autocomplete="name"></label>
					<label class="f27-field"><span><?php esc_html_e( 'Компания', 'form27' ); ?></span><input name="company" maxlength="120" autocomplete="organization"></label>
					<label class="f27-field"><span><?php esc_html_e( 'Электронная почта', 'form27' ); ?></span><input name="email" type="email" autocomplete="email"></label>
					<label class="f27-field"><span><?php esc_html_e( 'Телефон', 'form27' ); ?></span><input name="phone" type="tel" maxlength="40" autocomplete="tel"></label>
				</div>
				<label class="f27-field"><span><?php esc_html_e( 'Комментарий', 'form27' ); ?></span><textarea name="message" rows="4" maxlength="2000"></textarea></label>
				<label class="f27-honeypot" aria-hidden="true" tabindex="-1"><span>Сайт</span><input name="website" tabindex="-1" autocomplete="off"></label>
				<label class="f27-check"><input name="consent" type="checkbox" required><span><?php esc_html_e( 'Согласен на обработку данных для ответа на запрос.', 'form27' ); ?></span></label>
				<button class="f27-button" type="submit"><?php esc_html_e( 'Отправить проект', 'form27' ); ?></button>
				<p class="f27-request__status" data-f27-request-status aria-live="polite"></p>
			</form>
			<noscript><p class="f27-notice"><?php esc_html_e( 'Для сборки и отправки спецификации включите JavaScript.', 'form27' ); ?></p></noscript>
			<?php
			if ( F27_Settings::is_demo() ) :
				?>
				<p class="f27-disclaimer"><?php echo esc_html( F27_Settings::disclaimer() ); ?></p><?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function enqueue(): void {
		wp_enqueue_style( 'f27-core-frontend' );
		wp_enqueue_script( 'f27-core-frontend' );
	}

	private static function heading( string $title ): string {
		return '' === $title ? '' : '<h2 class="f27-block__title">' . esc_html( $title ) . '</h2>';
	}

	/** @param array<string,mixed> $product Product data. */
	private static function product_card( array $product, int $index ): string {
		$collection_slugs = wp_list_pluck( $product['collections'], 'slug' );
		$collection_names = wp_list_pluck( $product['collections'], 'name' );
		$search           = implode( ' ', array_merge( array( $product['name'], $product['excerpt'] ), $collection_names, wp_list_pluck( $product['application'], 'name' ) ) );
		ob_start();
		?>
		<article class="f27-product-card<?php echo $product['featured'] ? ' is-featured' : ''; ?>" data-f27-product data-collection="<?php echo esc_attr( implode( ' ', $collection_slugs ) ); ?>" data-mounting="<?php echo esc_attr( implode( ' ', wp_list_pluck( $product['mounting'], 'slug' ) ) ); ?>" data-application="<?php echo esc_attr( implode( ' ', wp_list_pluck( $product['application'], 'slug' ) ) ); ?>" data-search="<?php echo esc_attr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search ) ); ?>" style="--product-index:<?php echo esc_attr( (string) $index ); ?>">
			<a class="f27-product-card__visual" href="<?php echo esc_url( $product['url'] ); ?>" aria-label="<?php echo esc_attr( 'Подробнее: ' . $product['name'] ); ?>">
				<?php
				if ( $product['image'] ) :
					?>
					<img src="<?php echo esc_url( $product['image'] ); ?>" alt="" loading="lazy" decoding="async">
					<?php
else :
	?>
					<span class="f27-product-card__shape" aria-hidden="true"></span><?php endif; ?>
				<span class="f27-product-card__code"><?php echo esc_html( $product['code'] ); ?></span>
			</a>
			<div class="f27-product-card__body">
				<p><?php echo esc_html( implode( ' / ', $collection_names ) ); ?></p>
				<h3><a href="<?php echo esc_url( $product['url'] ); ?>"><?php echo esc_html( $product['name'] ); ?></a></h3>
				<p><?php echo esc_html( $product['excerpt'] ); ?></p>
				<p class="f27-product-card__facts"><?php echo esc_html( $product['dimensions'] . ' / ' . $product['ip'] ); ?></p>
				<dl><div><dt><?php esc_html_e( 'Мощность', 'form27' ); ?></dt><dd><?php echo esc_html( implode( ' / ', $product['wattages'] ) . ' Вт' ); ?></dd></div><div><dt><?php esc_html_e( 'От', 'form27' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $product['price'] ) . ' ₽' ); ?></dd></div></dl>
				<button class="f27-text-button" type="button" data-f27-configure="<?php echo esc_attr( $product['slug'] ); ?>"><?php esc_html_e( 'Настроить модель', 'form27' ); ?></button>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<int|string> $values Values. */
	private static function option_select( string $name, string $label, array $values, string $suffix = '', string $prefix = '' ): string {
		ob_start();
		?>
		<label class="f27-field"><span><?php echo esc_html( $label ); ?></span><select name="<?php echo esc_attr( $name ); ?>">
			<?php
			foreach ( $values as $value ) :
				?>
				<option value="<?php echo esc_attr( (string) $value ); ?>"><?php echo esc_html( $prefix . $value . $suffix ); ?></option><?php endforeach; ?>
		</select></label>
		<?php
		return (string) ob_get_clean();
	}

	/** @param WP_Term[]|WP_Error $terms Taxonomy terms. */
	private static function taxonomy_select( string $name, string $label, string $all_label, $terms ): string {
		ob_start();
		?>
		<label class="f27-field"><span><?php echo esc_html( $label ); ?></span><select data-f27-tax-filter="<?php echo esc_attr( $name ); ?>"><option value="all"><?php echo esc_html( $all_label ); ?></option>
			<?php
			if ( is_array( $terms ) ) :
				?>
				<?php
				foreach ( $terms as $term ) :
					?>
				<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?><?php endif; ?>
		</select></label>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<int,array<string,mixed>> $data Data. */
	private static function json_data( array $data ): string {
		$json = wp_json_encode(
			array(
				'schemaVersion' => 1,
				'demo'          => F27_Settings::is_demo(),
				'generatedAt'   => gmdate( DATE_ATOM ),
				'products'      => $data,
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		return '<script type="application/json" data-f27-products>' . $json . '</script>';
	}
}

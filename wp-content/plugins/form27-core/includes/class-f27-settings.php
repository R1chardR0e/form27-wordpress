<?php
/**
 * Centralized public brand settings and mail transport.
 *
 * @package Form27
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class F27_Settings {
	private const OPTION = 'f27_settings';

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'brand_name'   => 'FORM 27',
			'public_email' => 'hello@form27.demo',
			'public_phone' => '+7 (000) 000-00-27',
			'demo_mode'    => true,
			'disclaimer'   => 'Демонстрационный проект. Характеристики, цены и объекты вымышлены и не являются технической документацией или офертой.',
		);
	}

	/** @return array<string,mixed> */
	public static function all(): array {
		$value = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $value ) ? $value : array() );
	}

	/** @return mixed */
	public static function get( string $key ) {
		$settings = self::all();
		return $settings[ $key ] ?? null;
	}

	public static function brand_name(): string {
		return (string) self::get( 'brand_name' );
	}

	public static function public_email(): string {
		return (string) self::get( 'public_email' );
	}

	public static function public_phone(): string {
		return (string) self::get( 'public_phone' );
	}

	public static function is_demo(): bool {
		return (bool) self::get( 'demo_mode' );
	}

	public static function disclaimer(): string {
		return (string) self::get( 'disclaimer' );
	}

	public static function register(): void {
		register_setting(
			'f27_settings_group',
			self::OPTION,
			array(
				'type'              => 'object',
				'default'           => self::defaults(),
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'f27_public_identity',
			'Публичные данные',
			static function (): void {
				echo '<p>Эти значения используются в шапке, подвале, формах, письмах и демонстрационных страницах.</p>';
			},
			'f27-settings'
		);

		$fields = array(
			'brand_name'   => array( 'Название бренда', 'text' ),
			'public_email' => array( 'Публичная электронная почта', 'email' ),
			'public_phone' => array( 'Публичный телефон', 'text' ),
			'demo_mode'    => array( 'Демо-режим', 'checkbox' ),
			'disclaimer'   => array( 'Дисклеймер', 'textarea' ),
		);
		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'f27_' . $key,
				$field[0],
				array( self::class, 'render_field' ),
				'f27-settings',
				'f27_public_identity',
				array(
					'key'  => $key,
					'type' => $field[1],
				)
			);
		}
	}

	public static function add_page(): void {
		add_options_page( 'Настройки FORM 27', 'FORM 27', 'manage_options', 'form27-settings', array( self::class, 'render_page' ) );
	}

	/** @param mixed $input Incoming option. @return array<string,mixed> */
	public static function sanitize( $input ): array {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$brand    = sanitize_text_field( (string) ( $input['brand_name'] ?? '' ) );
		$email    = sanitize_email( (string) ( $input['public_email'] ?? '' ) );
		$phone    = sanitize_text_field( (string) ( $input['public_phone'] ?? '' ) );
		$notice   = sanitize_textarea_field( (string) ( $input['disclaimer'] ?? '' ) );

		return array(
			'brand_name'   => '' !== $brand ? $brand : $defaults['brand_name'],
			'public_email' => is_email( $email ) ? $email : $defaults['public_email'],
			'public_phone' => '' !== $phone ? $phone : $defaults['public_phone'],
			'demo_mode'    => ! empty( $input['demo_mode'] ),
			'disclaimer'   => '' !== $notice ? $notice : $defaults['disclaimer'],
		);
	}

	/** @param array{key:string,type:string} $args Field arguments. */
	public static function render_field( array $args ): void {
		$key   = $args['key'];
		$type  = $args['type'];
		$value = self::get( $key );
		$name  = self::OPTION . '[' . $key . ']';
		if ( 'checkbox' === $type ) {
			printf( '<label><input type="checkbox" name="%1$s" value="1" %2$s> Показывать маркировку и дисклеймер демонстрационного проекта</label>', esc_attr( $name ), checked( (bool) $value, true, false ) );
			return;
		}
		if ( 'textarea' === $type ) {
			printf( '<textarea class="large-text" rows="4" name="%1$s">%2$s</textarea>', esc_attr( $name ), esc_textarea( (string) $value ) );
			return;
		}
		printf( '<input class="regular-text" type="%1$s" name="%2$s" value="%3$s">', esc_attr( $type ), esc_attr( $name ), esc_attr( (string) $value ) );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Настройки FORM 27', 'form27' ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'f27_settings_group' ); ?>
				<?php do_settings_sections( 'f27-settings' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function register_shortcodes(): void {
		add_shortcode( 'form27_brand', static fn (): string => esc_html( self::brand_name() ) );
		add_shortcode( 'form27_email', static fn (): string => esc_html( self::public_email() ) );
		add_shortcode( 'form27_phone', static fn (): string => esc_html( self::public_phone() ) );
		add_shortcode( 'form27_disclaimer', static fn (): string => self::is_demo() ? esc_html( self::disclaimer() ) : '' );
		add_shortcode(
			'form27_email_link',
			static function (): string {
				$email = self::public_email();
				return '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
			}
		);
		add_shortcode(
			'form27_phone_link',
			static function (): string {
				$phone = self::public_phone();
				$href  = preg_replace( '/[^0-9+]/', '', $phone );
				return '<a href="tel:' . esc_attr( (string) $href ) . '">' . esc_html( $phone ) . '</a>';
			}
		);
	}

	/** @param mixed $value Existing site title. @return mixed */
	public static function filter_blogname( $value ) {
		$brand = self::brand_name();
		return '' !== $brand ? $brand : $value;
	}

	/**
	 * Replace the few legacy literals in the active block theme at render time.
	 *
	 * @param string              $block_content Rendered block.
	 * @param array<string,mixed> $block Block data.
	 */
	public static function filter_render_block( string $block_content, array $block ): string {
		$class_name = (string) ( $block['attrs']['className'] ?? '' );
		if ( str_contains( $class_name, 'f27-demo-label' ) ) {
			if ( ! self::is_demo() ) {
				return '';
			}
			return (string) preg_replace( '/(<p\b[^>]*\bf27-demo-label\b[^>]*>).*?(<\/p>)/su', '$1' . esc_html( 'Демо-проект' ) . '$2', $block_content, 1 );
		}

		if ( str_contains( $block_content, 'id="f27-menu-title"' ) ) {
			$block_content = (string) preg_replace_callback(
				'/(<strong\b[^>]*id="f27-menu-title"[^>]*>).*?(<\/strong>)/su',
				static fn ( array $matches ): string => $matches[1] . esc_html( self::brand_name() ) . $matches[2],
				$block_content,
				1
			);
		}
		if ( str_contains( $class_name, 'f27-footer__legal' ) ) {
			$legacy        = 'Учебный демонстрационный проект. Характеристики, цены и объекты вымышлены.';
			$block_content = str_replace( $legacy, self::is_demo() ? esc_html( self::disclaimer() ) : '', $block_content );
			$block_content = str_replace( 'Материалы не являются технической документацией или офертой.', '', $block_content );
			$block_content = (string) preg_replace( '/<p\b[^>]*>\s*<\/p>/u', '', $block_content );
		}

		return $block_content;
	}

	public static function configure_mailer( $phpmailer ): void {
		if ( ! defined( 'FORM27_SMTP_HOST' ) || '' === trim( (string) FORM27_SMTP_HOST ) ) {
			return;
		}

		$host       = trim( (string) FORM27_SMTP_HOST );
		$port       = defined( 'FORM27_SMTP_PORT' ) ? absint( FORM27_SMTP_PORT ) : 1025;
		$username   = defined( 'FORM27_SMTP_USERNAME' ) ? (string) FORM27_SMTP_USERNAME : '';
		$password   = defined( 'FORM27_SMTP_PASSWORD' ) ? (string) FORM27_SMTP_PASSWORD : '';
		$encryption = defined( 'FORM27_SMTP_ENCRYPTION' ) ? strtolower( trim( (string) FORM27_SMTP_ENCRYPTION ) ) : '';
		$auth       = '' !== $username;
		if ( defined( 'FORM27_SMTP_AUTH' ) ) {
			$auth = (bool) filter_var( FORM27_SMTP_AUTH, FILTER_VALIDATE_BOOLEAN );
		}

		$phpmailer->isSMTP();
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer public API.
		$phpmailer->Host        = $host;
		$phpmailer->Port        = $port > 0 ? $port : 1025;
		$phpmailer->SMTPAuth    = $auth;
		$phpmailer->Username    = $username;
		$phpmailer->Password    = $password;
		$phpmailer->SMTPAutoTLS = in_array( $encryption, array( 'tls', 'ssl' ), true );
		$phpmailer->SMTPSecure  = in_array( $encryption, array( 'tls', 'ssl' ), true ) ? $encryption : '';
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	public static function mail_from( string $email ): string {
		$from = defined( 'FORM27_SMTP_FROM' ) ? sanitize_email( (string) FORM27_SMTP_FROM ) : '';
		return is_email( $from ) ? $from : $email;
	}

	public static function mail_from_name( string $name ): string {
		$from_name = '';
		if ( defined( 'FORM27_SMTP_NAME' ) ) {
			$from_name = sanitize_text_field( (string) FORM27_SMTP_NAME );
		} elseif ( defined( 'FORM27_SMTP_FROM_NAME' ) ) {
			$from_name = sanitize_text_field( (string) FORM27_SMTP_FROM_NAME );
		}
		return '' !== $from_name ? $from_name : $name;
	}
}

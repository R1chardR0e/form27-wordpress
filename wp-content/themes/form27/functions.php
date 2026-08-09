<?php
/**
 * FORM 27 theme setup.
 *
 * @package Form27
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register theme features that complement theme.json.
 */
function form27_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 72,
			'width'       => 220,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_editor_style( 'assets/css/editor.css' );
}
add_action( 'after_setup_theme', 'form27_setup' );

/**
 * Use a neutral title separator that matches the editorial visual language.
 */
function form27_document_title_separator(): string {
	return '|';
}
add_filter( 'document_title_separator', 'form27_document_title_separator' );

/**
 * Enqueue the small presentation layer shared by templates and dynamic blocks.
 */
function form27_enqueue_assets(): void {
	$theme   = wp_get_theme();
	$version = (string) $theme->get( 'Version' );
	$css     = get_theme_file_path( 'assets/css/main.css' );
	$js      = get_theme_file_path( 'assets/js/theme.js' );

	wp_enqueue_style(
		'form27-theme',
		get_theme_file_uri( 'assets/css/main.css' ),
		array(),
		file_exists( $css ) ? (string) filemtime( $css ) : $version
	);

	wp_enqueue_script(
		'form27-theme',
		get_theme_file_uri( 'assets/js/theme.js' ),
		array(),
		file_exists( $js ) ? (string) filemtime( $js ) : $version,
		array(
			'in_footer' => false,
			'strategy'  => 'defer',
		)
	);

	wp_add_inline_script(
		'form27-theme',
		"try{var t=localStorage.getItem('form27.theme');if(t==='light'||t==='dark'){document.documentElement.dataset.theme=t;}}catch(e){}",
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'form27_enqueue_assets' );

/**
 * Keep the public hero image discoverable before the template is parsed.
 *
 * @param array<int, array<string, string>> $preloads Existing preload records.
 * @return array<int, array<string, string>>
 */
function form27_preload_hero( array $preloads ): array {
	$preloads[] = array(
		'href'        => get_theme_file_uri( 'assets/fonts/onest-cyrillic-wght-normal.woff2' ),
		'as'          => 'font',
		'type'        => 'font/woff2',
		'crossorigin' => 'anonymous',
	);

	$preloads[] = array(
		'href'        => get_theme_file_uri( 'assets/fonts/onest-latin-wght-normal.woff2' ),
		'as'          => 'font',
		'type'        => 'font/woff2',
		'crossorigin' => 'anonymous',
	);

	if ( is_front_page() ) {
		$preloads[] = array(
			'href' => get_theme_file_uri( 'assets/images/hero.avif' ),
			'as'   => 'image',
			'type' => 'image/avif',
		);
	}

	return $preloads;
}
add_filter( 'wp_preload_resources', 'form27_preload_hero' );

/**
 * Register the few semantic block styles used by editors.
 */
function form27_register_block_styles(): void {
	register_block_style(
		'core/button',
		array(
			'name'  => 'outline-signal',
			'label' => __( 'Outline signal', 'form27' ),
		)
	);

	register_block_style(
		'core/group',
		array(
			'name'  => 'technical-panel',
			'label' => __( 'Technical panel', 'form27' ),
		)
	);
}
add_action( 'init', 'form27_register_block_styles' );

/**
 * Add a stable body class for static exports and browser checks.
 *
 * @param array<int, string> $classes Existing classes.
 * @return array<int, string>
 */
function form27_body_classes( array $classes ): array {
	$classes[] = 'form27-theme';

	return $classes;
}
add_filter( 'body_class', 'form27_body_classes' );

/**
 * Resolve seed-owned root-relative links against the actual WordPress home URL.
 *
 * Block patterns and template parts are portable files, so their known routes
 * cannot know whether WordPress will later be installed at the domain root or
 * in a subdirectory. Dynamic permalinks are already absolute and are left
 * untouched.
 *
 * @param string $block_content Rendered block markup.
 * @return string
 */
function form27_resolve_internal_links( string $block_content ): string {
	if ( '' === $block_content || false === strpos( $block_content, 'href="/' ) ) {
		return $block_content;
	}

	$home   = untrailingslashit( home_url( '/' ) );
	$routes = array(
		'/',
		'/#configurator',
		'/catalog/',
		'/projects/',
		'/specification/',
		'/contacts/',
		'/privacy/',
	);

	foreach ( $routes as $route ) {
		$resolved      = esc_url( $home . $route );
		$block_content = str_replace( 'href="' . $route . '"', 'href="' . $resolved . '"', $block_content );
	}

	return $block_content;
}
add_filter( 'render_block', 'form27_resolve_internal_links', 20 );

/**
 * Preserve standards-based responsive image markup in editable HTML blocks.
 *
 * The allowlist is deliberately limited to media elements and attributes.
 * Scripts, event handlers and arbitrary embedded markup remain disallowed.
 *
 * @param array<string, array<string, bool>> $tags Allowed HTML tags.
 * @param string                             $context KSES context.
 * @return array<string, array<string, bool>>
 */
function form27_allow_responsive_media_markup( array $tags, string $context ): array {
	if ( 'post' !== $context ) {
		return $tags;
	}

	$tags['picture'] = array(
		'class' => true,
	);
	$tags['source']  = array(
		'srcset' => true,
		'sizes'  => true,
		'type'   => true,
		'media'  => true,
		'width'  => true,
		'height' => true,
	);

	if ( isset( $tags['img'] ) ) {
		$tags['img']['loading']       = true;
		$tags['img']['fetchpriority'] = true;
		$tags['img']['decoding']      = true;
	}

	return $tags;
}
add_filter( 'wp_kses_allowed_html', 'form27_allow_responsive_media_markup', 10, 2 );

/**
 * Treat a theme without the companion plugin as a safe, non-indexable demo.
 */
function form27_is_demo_site(): bool {
	return ! class_exists( 'F27_Settings' ) || F27_Settings::is_demo();
}

/**
 * Keep the demonstration build out of search indexes.
 *
 * @param array<string, bool|string> $robots Existing robots directives.
 * @return array<string, bool|string>
 */
function form27_demo_robots( array $robots ): array {
	if ( form27_is_demo_site() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}

	return $robots;
}
add_filter( 'wp_robots', 'form27_demo_robots' );

/**
 * Build one concise description for standard and social metadata.
 */
function form27_document_description(): string {
	$description = '';

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$description = has_excerpt( $post )
				? get_the_excerpt( $post )
				: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 28, '' );
		}
	} elseif ( is_post_type_archive( 'f27_product' ) ) {
		$description = 'Каталог архитектурных световых систем FORM 27 для жилых и общественных пространств.';
	} elseif ( is_post_type_archive( 'f27_case' ) ) {
		$description = 'Проекты FORM 27 с архитектурным светом для галерей, ресторанов и рабочих пространств.';
	}

	if ( is_front_page() || '' === trim( $description ) ) {
		$description = get_bloginfo( 'description', 'display' );
	}

	if ( '' === trim( $description ) ) {
		$description = 'Точные световые системы FORM 27 для жилых, общественных и выставочных пространств.';
	}

	return sanitize_text_field( html_entity_decode( $description, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
}

/**
 * Return a stable social preview image for the current document.
 */
function form27_social_image_url(): string {
	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		$image   = (string) get_post_meta( $post_id, 'f27_image_url', true );

		if ( '' === $image ) {
			$image = (string) get_post_meta( $post_id, 'f27_case_after_image', true );
		}
		if ( '' === $image ) {
			$image = (string) get_the_post_thumbnail_url( $post_id, 'full' );
		}
		if ( '' !== $image ) {
			return esc_url_raw( $image );
		}
	}

	return esc_url_raw( get_theme_file_uri( 'assets/images/hero.webp' ) );
}

/**
 * Print essential description and social sharing metadata without replacing
 * the title managed by WordPress core.
 */
function form27_render_social_metadata(): void {
	if ( is_404() || is_search() ) {
		return;
	}

	$title       = wp_get_document_title();
	$description = form27_document_description();
	$url         = is_singular() ? get_permalink() : get_pagenum_link();
	$image       = form27_social_image_url();
	$type        = is_singular( 'f27_product' ) ? 'product' : ( is_singular( array( 'post', 'f27_case' ) ) ? 'article' : 'website' );
	$site_name   = class_exists( 'F27_Settings' ) ? F27_Settings::brand_name() : get_bloginfo( 'name' );

	echo "\n";
	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
	printf( '<meta property="og:locale" content="ru_RU">' . "\n" );
	printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $type ) );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( $site_name ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( (string) $url ) );
	printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
	printf( '<meta name="twitter:card" content="summary_large_image">' . "\n" );
	printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $description ) );
	printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image ) );
}
add_action( 'wp_head', 'form27_render_social_metadata', 5 );

/**
 * Print production-only Organization and Product structured data.
 */
function form27_render_schema(): void {
	if ( form27_is_demo_site() || is_404() || is_search() ) {
		return;
	}

	$home_url        = home_url( '/' );
	$organization_id = trailingslashit( $home_url ) . '#organization';
	$brand_name      = F27_Settings::brand_name();
	$organization    = array(
		'@type' => 'Organization',
		'@id'   => $organization_id,
		'name'  => $brand_name,
		'url'   => $home_url,
	);
	$logo_id         = (int) get_theme_mod( 'custom_logo', 0 );
	$logo_url        = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'full' ) : false;

	if ( is_string( $logo_url ) && '' !== $logo_url ) {
		$organization['logo'] = $logo_url;
	}
	if ( is_email( F27_Settings::public_email() ) ) {
		$organization['email'] = F27_Settings::public_email();
	}
	if ( '' !== trim( F27_Settings::public_phone() ) ) {
		$organization['telephone'] = F27_Settings::public_phone();
	}

	$graph = array( $organization );

	if ( is_singular( 'f27_product' ) ) {
		$post_id     = get_queried_object_id();
		$product_url = get_permalink( $post_id );
		$product     = array(
			'@type'       => 'Product',
			'@id'         => (string) $product_url . '#product',
			'name'        => get_the_title( $post_id ),
			'description' => form27_document_description(),
			'url'         => $product_url,
			'image'       => array( form27_social_image_url() ),
			'brand'       => array( '@id' => $organization_id ),
		);
		$sku         = (string) get_post_meta( $post_id, 'f27_code', true );
		$price       = (int) get_post_meta( $post_id, 'f27_price', true );

		if ( '' !== $sku ) {
			$product['sku'] = $sku;
		}
		if ( $price > 0 ) {
			$product['offers'] = array(
				'@type'         => 'Offer',
				'url'           => $product_url,
				'priceCurrency' => 'RUB',
				'price'         => $price,
				'availability'  => 'https://schema.org/InStock',
			);
		}

		$graph[] = $product;
	}

	$schema = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	);

	if ( is_string( $schema ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() uses all JSON_HEX flags above.
		printf( "\n<script type=\"application/ld+json\">%s</script>\n", $schema );
	}
}
add_action( 'wp_head', 'form27_render_schema', 20 );

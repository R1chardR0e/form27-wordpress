<?php
/**
 * Title: FORM 27 home
 * Slug: form27/home
 * Categories: featured, form27
 *
 * @package Form27
 */

$form27_theme_uri   = untrailingslashit( get_theme_file_uri() );
$form27_theme_host  = strtolower( (string) wp_parse_url( $form27_theme_uri, PHP_URL_HOST ) );
$form27_theme_port  = (int) wp_parse_url( $form27_theme_uri, PHP_URL_PORT );
$form27_theme_path  = trailingslashit( (string) wp_parse_url( $form27_theme_uri, PHP_URL_PATH ) );
$form27_local_bases = array( home_url( '/' ), site_url( '/' ) );

foreach ( $form27_local_bases as $form27_local_base ) {
	$form27_local_host = strtolower( (string) wp_parse_url( $form27_local_base, PHP_URL_HOST ) );
	$form27_local_port = (int) wp_parse_url( $form27_local_base, PHP_URL_PORT );
	$form27_local_path = trailingslashit( (string) wp_parse_url( $form27_local_base, PHP_URL_PATH ) );
	if (
		$form27_theme_host === $form27_local_host
		&& $form27_theme_port === $form27_local_port
		&& str_starts_with( $form27_theme_path, $form27_local_path )
	) {
		$form27_theme_uri = untrailingslashit( wp_make_link_relative( $form27_theme_uri ) );
		break;
	}
}
?>

<!-- wp:group {"tagName":"section","align":"full","className":"f27-section f27-hero","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull f27-section f27-hero">
	<!-- wp:group {"align":"wide","className":"f27-hero__grid","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide f27-hero__grid">
		<!-- wp:group {"className":"f27-hero__copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group f27-hero__copy">
			<!-- wp:paragraph {"className":"f27-eyebrow","fontSize":"micro"} -->
			<p class="f27-eyebrow has-micro-font-size">Архитектурный свет</p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":1,"className":"f27-hero__title","fontSize":"display"} -->
			<h1 class="wp-block-heading f27-hero__title has-display-font-size">Свет, собранный по системе.</h1>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"f27-hero__lead","fontSize":"body"} -->
			<p class="f27-hero__lead has-body-font-size">Точные световые системы для жилых, общественных и выставочных пространств.</p>
			<!-- /wp:paragraph -->
			<!-- wp:buttons {"className":"f27-hero__actions","layout":{"type":"flex","flexWrap":"wrap"}} -->
			<div class="wp-block-buttons f27-hero__actions">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#configurator">Собрать проект</a></div>
				<!-- /wp:button -->
				<!-- wp:button {"className":"is-style-outline-signal"} -->
				<div class="wp-block-button is-style-outline-signal"><a class="wp-block-button__link wp-element-button" href="/catalog/">Открыть каталог</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->

		<!-- wp:html -->
		<figure class="wp-block-image size-full f27-hero__media"><picture class="f27-responsive-picture"><source srcset="<?php echo esc_url( $form27_theme_uri . '/assets/images/hero.avif' ); ?>" type="image/avif"><img src="<?php echo esc_url( $form27_theme_uri . '/assets/images/hero.webp' ); ?>" alt="Линейная система FORM 27 в графитовом интерьере" width="1536" height="960" loading="eager" fetchpriority="high" decoding="async"></picture></figure>
		<!-- /wp:html -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","anchor":"catalog","align":"full","className":"f27-section f27-catalog-section f27-reveal","layout":{"type":"constrained"}} -->
<section id="catalog" class="wp-block-group alignfull f27-section f27-catalog-section f27-reveal">
	<!-- wp:group {"align":"wide","className":"f27-section-heading f27-section-heading--stacked","layout":{"type":"constrained","justifyContent":"left"}} -->
	<div class="wp-block-group alignwide f27-section-heading f27-section-heading--stacked">
		<!-- wp:heading {"level":2,"fontSize":"section"} -->
		<h2 class="wp-block-heading has-section-font-size">Шесть инструментов для работы со светом</h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"className":"f27-section-intro"} -->
		<p class="f27-section-intro">Три системы закрывают основной свет, акценты и мягкую подсветку объекта.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:form27/catalog {"title":"","limit":6,"showFilters":true} /-->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","anchor":"configurator","align":"full","className":"f27-section f27-configurator-section","layout":{"type":"constrained"}} -->
<section id="configurator" class="wp-block-group alignfull f27-section f27-configurator-section">
	<!-- wp:group {"align":"wide","className":"f27-section-heading f27-section-heading--narrow","layout":{"type":"constrained","justifyContent":"left"}} -->
	<div class="wp-block-group alignwide f27-section-heading f27-section-heading--narrow">
		<!-- wp:heading {"level":2,"fontSize":"section"} -->
		<h2 class="wp-block-heading has-section-font-size">Настройте свет до чертежа</h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"className":"f27-section-intro"} -->
		<p class="f27-section-intro">Температура, оптика и отделка меняют сцену сразу. Допустимые варианты проверяются автоматически.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:form27/configurator {"title":""} /-->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"f27-section f27-assembly f27-reveal","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull f27-section f27-assembly f27-reveal">
	<!-- wp:group {"align":"wide","className":"f27-assembly__grid","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide f27-assembly__grid">
		<!-- wp:group {"className":"f27-assembly__copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group f27-assembly__copy">
			<!-- wp:heading {"level":2,"fontSize":"section"} -->
			<h2 class="wp-block-heading has-section-font-size">Система читается по слоям</h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"f27-section-intro"} -->
			<p class="f27-section-intro">Корпус задаёт линию. Оптика формирует задачу. Управление связывает свет со сценарием.</p>
			<!-- /wp:paragraph -->
			<!-- wp:group {"className":"f27-assembly__parts","layout":{"type":"default"}} -->
			<div class="wp-block-group f27-assembly__parts">
				<!-- wp:group {"className":"f27-assembly__part f27-assembly__part--body","layout":{"type":"constrained"}} -->
				<div class="wp-block-group f27-assembly__part f27-assembly__part--body"><!-- wp:heading {"level":3,"fontSize":"lead"} --><h3 class="wp-block-heading has-lead-font-size">Корпус</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Литой алюминий отводит тепло и сохраняет геометрию системы.</p><!-- /wp:paragraph --></div>
				<!-- /wp:group -->
				<!-- wp:group {"className":"f27-assembly__part f27-assembly__part--optic","layout":{"type":"constrained"}} -->
				<div class="wp-block-group f27-assembly__part f27-assembly__part--optic"><!-- wp:heading {"level":3,"fontSize":"lead"} --><h3 class="wp-block-heading has-lead-font-size">Оптика</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Узкий акцент, широкий заливной свет или асимметричная засветка стены.</p><!-- /wp:paragraph --></div>
				<!-- /wp:group -->
				<!-- wp:group {"className":"f27-assembly__part f27-assembly__part--control","layout":{"type":"constrained"}} -->
				<div class="wp-block-group f27-assembly__part f27-assembly__part--control"><!-- wp:heading {"level":3,"fontSize":"lead"} --><h3 class="wp-block-heading has-lead-font-size">Управление</h3><!-- /wp:heading --><!-- wp:paragraph --><p>On/Off, TRIAC и DALI-2 для простых групп и программируемых сцен.</p><!-- /wp:paragraph --></div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:html -->
		<figure class="wp-block-image size-full f27-assembly__image"><picture class="f27-responsive-picture"><source srcset="<?php echo esc_url( $form27_theme_uri . '/assets/images/product-line-s48.avif' ); ?>" type="image/avif"><img src="<?php echo esc_url( $form27_theme_uri . '/assets/images/product-line-s48.webp' ); ?>" alt="Конструкция линейного светильника LINE S48" width="1200" height="1200" loading="lazy" fetchpriority="low" decoding="async"></picture></figure>
		<!-- /wp:html -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","anchor":"projects","align":"full","className":"f27-section f27-cases-section","layout":{"type":"constrained"}} -->
<section id="projects" class="wp-block-group alignfull f27-section f27-cases-section">
	<!-- wp:group {"align":"wide","className":"f27-section-heading f27-section-heading--stacked","layout":{"type":"constrained","justifyContent":"left"}} -->
	<div class="wp-block-group alignwide f27-section-heading f27-section-heading--stacked">
		<!-- wp:paragraph {"className":"f27-eyebrow","fontSize":"micro"} -->
		<p class="f27-eyebrow has-micro-font-size">Проекты</p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"level":2,"fontSize":"section"} -->
		<h2 class="wp-block-heading has-section-font-size">Свет меняет способ видеть пространство</h2>
		<!-- /wp:heading -->
	</div>
	<!-- /wp:group -->
	<!-- wp:form27/cases {"title":"","limit":3} /-->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"f27-section f27-materials f27-reveal","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull f27-section f27-materials f27-reveal">
	<!-- wp:group {"align":"wide","className":"f27-materials__grid","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide f27-materials__grid">
		<!-- wp:group {"className":"f27-materials__media","layout":{"type":"default"}} -->
		<div class="wp-block-group f27-materials__media">
			<!-- wp:html -->
			<figure class="wp-block-image size-full f27-materials__image f27-materials__image--metal"><picture class="f27-responsive-picture"><source srcset="<?php echo esc_url( $form27_theme_uri . '/assets/images/material-fold.avif' ); ?>" type="image/avif"><img src="<?php echo esc_url( $form27_theme_uri . '/assets/images/material-fold.webp' ); ?>" alt="Фактура анодированного алюминия" width="1200" height="1200" loading="lazy" fetchpriority="low" decoding="async"></picture></figure>
			<!-- /wp:html -->
			<!-- wp:html -->
			<figure class="wp-block-image size-full f27-materials__image f27-materials__image--optic"><picture class="f27-responsive-picture"><source srcset="<?php echo esc_url( $form27_theme_uri . '/assets/images/material-finishes.avif' ); ?>" type="image/avif"><img src="<?php echo esc_url( $form27_theme_uri . '/assets/images/material-finishes.webp' ); ?>" alt="Образцы отделки архитектурного светильника" width="1200" height="1200" loading="lazy" fetchpriority="low" decoding="async"></picture></figure>
			<!-- /wp:html -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"f27-materials__copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group f27-materials__copy">
			<!-- wp:heading {"level":2,"fontSize":"section"} -->
			<h2 class="wp-block-heading has-section-font-size">Материал остаётся в тени</h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"f27-section-intro"} -->
			<p class="f27-section-intro">Матовый алюминий не спорит с архитектурой. Точная оптика оставляет в кадре только свет.</p>
			<!-- /wp:paragraph -->
			<!-- wp:group {"className":"f27-materials__facts","layout":{"type":"default"}} -->
			<div class="wp-block-group f27-materials__facts">
				<!-- wp:paragraph --><p><strong>Покрытия</strong><br>Графит, тёмная бронза, анодированный алюминий</p><!-- /wp:paragraph -->
				<!-- wp:paragraph --><p><strong>Свет</strong><br>CRI 90 или 95, три температуры</p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","anchor":"specification","align":"full","className":"f27-section f27-specification","layout":{"type":"constrained"}} -->
<section id="specification" class="wp-block-group alignfull f27-section f27-specification">
	<!-- wp:group {"align":"wide","className":"f27-specification__heading","layout":{"type":"constrained","justifyContent":"left"}} -->
	<div class="wp-block-group alignwide f27-specification__heading">
		<!-- wp:heading {"level":2,"fontSize":"section"} -->
		<h2 class="wp-block-heading has-section-font-size">Соберите спецификацию в одном окне</h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"className":"f27-section-intro"} -->
		<p class="f27-section-intro">Сохраните приборы, задайте количество и скачайте PDF. Заявка в демоверсии не отправляется.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"align":"wide","className":"f27-specification__grid","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide f27-specification__grid">
		<!-- wp:group {"className":"f27-specification__project is-style-technical-panel","layout":{"type":"constrained"}} -->
		<div class="wp-block-group f27-specification__project is-style-technical-panel"><!-- wp:form27/project-tray {"title":""} /--></div>
		<!-- /wp:group -->
		<!-- wp:group {"className":"f27-specification__request","layout":{"type":"constrained"}} -->
		<div class="wp-block-group f27-specification__request"><!-- wp:form27/request-form {"title":""} /--></div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

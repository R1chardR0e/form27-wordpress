(function (blocks, blockEditor, components, element, i18n) {
	'use strict';

	const el = element.createElement;
	const InspectorControls = blockEditor.InspectorControls;
	const useBlockProps = blockEditor.useBlockProps;
	const PanelBody = components.PanelBody;
	const TextControl = components.TextControl;
	const RangeControl = components.RangeControl;
	const ToggleControl = components.ToggleControl;
	const __ = i18n.__;

	function editFactory(label, description, controlsFactory) {
		return function Edit(props) {
			const controls = controlsFactory ? controlsFactory(props) : [];
			return el(
				element.Fragment,
				null,
				controls.length ? el(InspectorControls, null, el(PanelBody, { title: __('Настройки блока', 'form27'), initialOpen: true }, controls)) : null,
				el('div', useBlockProps({ className: 'f27-editor-block' }),
					el('strong', null, label),
					el('p', null, description),
					props.attributes.title ? el('small', null, __('Заголовок: ', 'form27') + props.attributes.title) : el('small', null, __('Внутренний заголовок скрыт', 'form27'))
				)
			);
		};
	}

	function titleControl(props) {
		return el(TextControl, {
			label: __('Внутренний заголовок', 'form27'),
			help: __('Оставьте пустым, если заголовок уже добавлен в редакторе.', 'form27'),
			value: props.attributes.title || '',
			onChange: function (value) { props.setAttributes({ title: value }); }
		});
	}

	blocks.registerBlockType('form27/catalog', {
		apiVersion: 3,
		title: __('FORM 27: каталог', 'form27'),
		icon: 'grid-view',
		category: 'widgets',
		description: __('Фильтруемый каталог светильников.', 'form27'),
		supports: { html: false, align: ['wide', 'full'], anchor: true },
		attributes: {
			title: { type: 'string', default: 'Каталог систем' },
			limit: { type: 'number', default: 6 },
			showFilters: { type: 'boolean', default: true }
		},
		edit: editFactory(__('Каталог FORM 27', 'form27'), __('Карточки и фильтры строятся из записей светильников.', 'form27'), function (props) {
			return [
				titleControl(props),
				el(RangeControl, { label: __('Количество', 'form27'), min: 1, max: 24, value: props.attributes.limit, onChange: function (value) { props.setAttributes({ limit: value }); } }),
				el(ToggleControl, { label: __('Показывать фильтры', 'form27'), checked: props.attributes.showFilters, onChange: function (value) { props.setAttributes({ showFilters: value }); } })
			];
		}),
		save: function () { return null; }
	});

	blocks.registerBlockType('form27/configurator', {
		apiVersion: 3,
		title: __('FORM 27: конфигуратор', 'form27'),
		icon: 'admin-generic',
		category: 'widgets',
		description: __('Выбор модели, света, оптики, отделки и управления.', 'form27'),
		supports: { html: false, align: ['wide', 'full'], anchor: true },
		attributes: {
			title: { type: 'string', default: 'Соберите светильник' },
			defaultProductSlug: { type: 'string', default: 'spot-s48' }
		},
		edit: editFactory(__('Конфигуратор FORM 27', 'form27'), __('Интерактивная версия появится на сайте.', 'form27'), function (props) {
			return [
				titleControl(props),
				el(TextControl, { label: __('Модель по умолчанию', 'form27'), value: props.attributes.defaultProductSlug || '', onChange: function (value) { props.setAttributes({ defaultProductSlug: value }); } })
			];
		}),
		save: function () { return null; }
	});

	[
		['project-tray', __('FORM 27: проект', 'form27'), __('Локальная спецификация выбранных моделей.', 'form27'), 'clipboard'],
		['request-form', __('FORM 27: заявка', 'form27'), __('Форма отправки собранной спецификации.', 'form27'), 'email']
	].forEach(function (definition) {
		blocks.registerBlockType('form27/' + definition[0], {
			apiVersion: 3,
			title: definition[1],
			icon: definition[3],
			category: 'widgets',
			description: definition[2],
			supports: { html: false, align: ['wide', 'full'], anchor: true },
			attributes: { title: { type: 'string', default: definition[0] === 'project-tray' ? 'Ваш проект' : 'Отправить спецификацию' } },
			edit: editFactory(definition[1], definition[2], function (props) { return [titleControl(props)]; }),
			save: function () { return null; }
		});
	});

	blocks.registerBlockType('form27/cases', {
		apiVersion: 3,
		title: __('FORM 27: проекты', 'form27'),
		icon: 'building',
		category: 'widgets',
		description: __('Проекты с интерактивным сравнением сценариев.', 'form27'),
		supports: { html: false, align: ['wide', 'full'], anchor: true },
		attributes: { title: { type: 'string', default: 'Свет в проектах' }, limit: { type: 'number', default: 3 } },
		edit: editFactory(__('Проекты FORM 27', 'form27'), __('Карточки строятся из записей проектов.', 'form27'), function (props) {
			return [
				titleControl(props),
				el(RangeControl, { label: __('Количество', 'form27'), min: 1, max: 12, value: props.attributes.limit, onChange: function (value) { props.setAttributes({ limit: value }); } })
			];
		}),
		save: function () { return null; }
	});
}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n));

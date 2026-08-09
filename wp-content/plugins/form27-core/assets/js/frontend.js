(function () {
	'use strict';

	const runtime = Object.assign(
		{},
		window.F27_CONFIG || {},
		window.FORM27_RUNTIME || {}
	);
	runtime.staticDemo = Boolean(runtime.staticDemo || runtime.mode === 'static');
	runtime.requestsEnabled = runtime.requestsEnabled !== false && !runtime.staticDemo;
	const storageKey = runtime.projectKey || 'form27.project.v1';
	const MAX_PROJECT_ITEMS = 30;
	const optionKeys = ['power', 'cct', 'cri', 'beam', 'finish', 'control'];
	const productFields = {
		power: 'wattages',
		cct: 'cct',
		cri: 'cri',
		beam: 'beams',
		finish: 'finishes',
		control: 'controls'
	};
	const finishCodes = {
		'Чёрный RAL 9005': 'BK',
		'Белый RAL 9003': 'WH',
		'Графит': 'GR',
		'Тёмная бронза': 'BZ',
		'Анодированный алюминий': 'AL'
	};
	const controlCodes = { 'On/Off': 'ON', 'DALI-2': 'DALI', TRIAC: 'TRIAC' };
	const money = new Intl.NumberFormat('ru-RU', {
		style: 'currency',
		currency: runtime.currency || 'RUB',
		maximumFractionDigits: 0
	});

	function showDataError(root, message) {
		let status = root.querySelector('[data-f27-data-status]');
		if (!status) {
			status = document.createElement('p');
			status.className = 'f27-notice';
			status.dataset.f27DataStatus = '';
			status.setAttribute('role', 'alert');
			root.prepend(status);
		}
		status.textContent = message;
	}

	function productsFromEnvelope(data) {
		if (!data || data.schemaVersion !== 1) {
			throw new Error('Версия данных каталога не поддерживается. Обновите страницу.');
		}
		if (!Array.isArray(data.products)) {
			throw new Error('Каталог получил данные неверного формата.');
		}
		return data.products;
	}

	function parseInlineProducts(root) {
		const node = root.querySelector('[data-f27-products]');
		if (!node) return [];
		try {
			return productsFromEnvelope(JSON.parse(node.textContent || '{}'));
		} catch (error) {
			showDataError(root, error.message || 'Не удалось прочитать данные каталога.');
			return [];
		}
	}

	async function getProducts(root) {
		const inline = parseInlineProducts(root);
		if (inline.length) return inline;
		if (!runtime.productsUrl) return [];
		try {
			const response = await fetch(runtime.productsUrl, { credentials: 'same-origin' });
			if (!response.ok) throw new Error('Не удалось загрузить каталог.');
			const data = await response.json();
			return productsFromEnvelope(data);
		} catch (error) {
			showDataError(root, error.message || 'Не удалось загрузить каталог.');
			return [];
		}
	}

	function emptyProject() {
		return { version: 1, items: [], updatedAt: new Date().toISOString() };
	}
	let memoryProject = emptyProject();
	let storageAvailable = true;

	function sanitizeProject(project) {
		if (!project || project.version !== 1 || !Array.isArray(project.items)) return emptyProject();
		return {
			version: 1,
			updatedAt: typeof project.updatedAt === 'string' ? project.updatedAt : new Date().toISOString(),
			items: project.items.filter(function (item) {
				return item && typeof item.slug === 'string' && item.options && Number(item.quantity) > 0;
			}).slice(0, MAX_PROJECT_ITEMS).map(function (item) {
				return Object.assign({}, item, {
					quantity: Math.min(99, Math.max(1, Number(item.quantity) || 1)),
					options: Object.assign({}, item.options)
				});
			})
		};
	}

	function readProject() {
		if (!storageAvailable) return sanitizeProject(memoryProject);
		try {
			const project = sanitizeProject(JSON.parse(localStorage.getItem(storageKey) || 'null'));
			memoryProject = project;
			return project;
		} catch (error) {
			storageAvailable = false;
			return sanitizeProject(memoryProject);
		}
	}

	function writeProject(project) {
		const safeProject = sanitizeProject(project);
		safeProject.updatedAt = new Date().toISOString();
		memoryProject = safeProject;
		if (storageAvailable) {
			try {
				localStorage.setItem(storageKey, JSON.stringify(safeProject));
			} catch (error) {
				storageAvailable = false;
				// The project still updates in the current document when storage is unavailable.
			}
		}
		document.dispatchEvent(new CustomEvent('f27:project-changed', { detail: safeProject }));
		return safeProject;
	}

	function itemKey(item) {
		return [item.slug].concat(optionKeys.map(function (key) {
			return String(item.options[key] || '');
		})).join('|');
	}

	function buildSku(product, options) {
		const base = String(product.code || product.slug || '')
			.toUpperCase()
			.replace(/[^A-Z0-9]+/g, '-')
			.replace(/^-|-$/g, '');
		const beam = String(options.beam || '').replace(/×/g, 'x').replace(/[^0-9x]/gi, '');
		const cct = String(Math.floor(Number(options.cct || 0) / 100));
		return [
			'F27', base, options.power, cct, options.cri, beam,
			finishCodes[options.finish] || 'NA',
			controlCodes[options.control] || 'NA'
		].filter(Boolean).join('-');
	}

	function addItem(product, options) {
		const project = readProject();
		const item = {
			productId: Number(product.id),
			slug: product.slug,
			name: product.name,
			quantity: 1,
			options: options,
			sku: buildSku(product, options),
			price: Number(product.price || 0)
		};
		const key = itemKey(item);
		const existing = project.items.find(function (entry) { return itemKey(entry) === key; });
		if (existing) {
			existing.quantity = Math.min(99, Number(existing.quantity || 1) + 1);
		} else {
			if (project.items.length >= MAX_PROJECT_ITEMS) {
				return { ok: false, message: 'В проекте может быть не более 30 разных позиций.' };
			}
			project.items.push(item);
		}
		writeProject(project);
		return { ok: true, message: product.name + ' добавлен в проект.' };
	}

	function initCatalog(root) {
		parseInlineProducts(root);
		const cards = Array.from(root.querySelectorAll('[data-f27-product]'));
		const input = root.querySelector('[data-f27-search]');
		const buttons = Array.from(root.querySelectorAll('[data-f27-filter]'));
		const taxFilters = Array.from(root.querySelectorAll('[data-f27-tax-filter]'));
		const empty = root.querySelector('[data-f27-empty]');
		let collection = 'all';

		function applyFilters() {
			const query = input ? input.value.trim().toLocaleLowerCase('ru') : '';
			let shown = 0;
			cards.forEach(function (card) {
				const matchesCollection = collection === 'all' || String(card.dataset.collection || '').split(' ').includes(collection);
				const matchesTaxonomies = taxFilters.every(function (select) {
					const value = select.value;
					return value === 'all' || String(card.dataset[select.dataset.f27TaxFilter] || '').split(' ').includes(value);
				});
				const matchesQuery = !query || String(card.dataset.search || '').includes(query);
				card.hidden = !(matchesCollection && matchesTaxonomies && matchesQuery);
				if (!card.hidden) shown += 1;
			});
			if (empty) empty.hidden = shown > 0;
		}

		if (input) input.addEventListener('input', applyFilters);
		taxFilters.forEach(function (select) { select.addEventListener('change', applyFilters); });
		buttons.forEach(function (button) {
			button.addEventListener('click', function () {
				collection = button.dataset.f27Filter || 'all';
				buttons.forEach(function (candidate) {
					const active = candidate === button;
					candidate.classList.toggle('is-active', active);
					candidate.setAttribute('aria-pressed', String(active));
				});
				applyFilters();
			});
		});

		root.addEventListener('click', function (event) {
			const trigger = event.target.closest('[data-f27-configure]');
			if (!trigger) return;
			const slug = trigger.dataset.f27Configure;
			const configurator = document.querySelector('[data-f27-configurator]');
			if (!configurator) {
				const target = new URL(runtime.homeUrl || '/', window.location.origin);
				target.searchParams.set('product', slug);
				target.hash = 'configurator';
				window.location.assign(target.toString());
				return;
			}
			const url = new URL(window.location.href);
			url.searchParams.set('product', slug);
			history.replaceState({}, '', url);
			document.dispatchEvent(new CustomEvent('f27:select-product', { detail: { slug: slug } }));
			configurator.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
		});
	}

	function replaceOptions(select, values, preferred) {
		select.replaceChildren();
		(values || []).forEach(function (value) {
			const option = document.createElement('option');
			option.value = String(value);
			if (select.name === 'power') option.textContent = value + ' Вт';
			else if (select.name === 'cct') option.textContent = value + ' K';
			else if (select.name === 'cri') option.textContent = 'CRI ' + value;
			else option.textContent = String(value);
			select.append(option);
		});
		if (preferred && Array.from(select.options).some(function (option) { return option.value === String(preferred); })) {
			select.value = String(preferred);
		}
	}

	function selectedOptions(form) {
		const result = {};
		optionKeys.forEach(function (key) {
			result[key] = form.elements[key] ? form.elements[key].value : '';
		});
		return result;
	}

	async function initConfigurator(root) {
		const products = await getProducts(root);
		const form = root.querySelector('[data-f27-config-form]');
		if (!form || !products.length) {
			if (form) form.setAttribute('aria-disabled', 'true');
			return;
		}
		const params = new URLSearchParams(window.location.search);
		const productSelect = form.elements.product;
		const preferredSlug = params.get('product') || productSelect.value;
		if (products.some(function (product) { return product.slug === preferredSlug; })) productSelect.value = preferredSlug;

		function currentProduct() {
			return products.find(function (product) { return product.slug === productSelect.value; }) || products[0];
		}

		function configureProduct(useQuery) {
			const product = currentProduct();
			optionKeys.forEach(function (key) {
				const preferred = useQuery ? params.get(key) : '';
				replaceOptions(form.elements[key], product[productFields[key]], preferred);
			});
			updatePreview();
		}

		function updatePreview() {
			const product = currentProduct();
			const options = selectedOptions(form);
			const height = Number(form.elements.height.value || 3);
			const beamNumbers = String(options.beam || '').match(/[0-9]+/g) || ['60'];
			const angle = Math.max.apply(null, beamNumbers.map(Number));
			const spot = Math.min(30, 2 * height * Math.tan((angle * Math.PI / 180) / 2));
			const visual = root.querySelector('[data-f27-visual]');
			if (visual) {
				visual.style.setProperty('--f27-beam-angle', Math.min(150, Math.max(14, angle)) + 'deg');
				visual.style.setProperty('--f27-beam-scale', String(Math.min(2.2, Math.max(.35, angle / 60))));
				visual.style.setProperty('--f27-cct', options.cct === '2700' ? '#ffd3a0' : options.cct === '4000' ? '#eef6ff' : '#fff0d2');
				visual.dataset.finish = finishCodes[options.finish] || 'AL';
			}
			const spotOutput = root.querySelector('[data-f27-spot]');
			const heightOutput = root.querySelector('[data-f27-height-output]');
			const sku = root.querySelector('[data-f27-sku]');
			const lumens = root.querySelector('[data-f27-lumens]');
			const price = root.querySelector('[data-f27-price]');
			if (spotOutput) spotOutput.textContent = (Number.isFinite(spot) ? spot : height).toLocaleString('ru-RU', { maximumFractionDigits: 1 }) + ' м';
			if (heightOutput) heightOutput.textContent = height.toLocaleString('ru-RU', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' м';
			if (sku) sku.textContent = buildSku(product, options);
			if (lumens) {
				const powerIndex = (product.wattages || []).map(String).indexOf(String(options.power));
				lumens.textContent = Number((product.lumens || [])[Math.max(0, powerIndex)] || 0).toLocaleString('ru-RU') + ' лм';
			}
			if (price) price.textContent = money.format(Number(product.price || 0));

			const url = new URL(window.location.href);
			url.searchParams.set('product', product.slug);
			optionKeys.forEach(function (key) { url.searchParams.set(key, options[key]); });
			history.replaceState({}, '', url);
		}

		productSelect.addEventListener('change', function () { configureProduct(false); });
		form.addEventListener('input', updatePreview);
		form.addEventListener('change', updatePreview);
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			const product = currentProduct();
			const options = selectedOptions(form);
			const result = addItem(product, options);
			const message = root.querySelector('[data-f27-config-message]');
			if (message) message.textContent = result.message;
		});
		document.addEventListener('f27:select-product', function (event) {
			const slug = event.detail && event.detail.slug;
			if (!products.some(function (product) { return product.slug === slug; })) return;
			productSelect.value = slug;
			configureProduct(false);
		});
		configureProduct(true);
	}

	function pluralPositions(number) {
		const mod10 = number % 10;
		const mod100 = number % 100;
		if (mod10 === 1 && mod100 !== 11) return number + ' позиция';
		if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return number + ' позиции';
		return number + ' позиций';
	}

	function appendText(parent, tag, text, className) {
		const node = document.createElement(tag);
		node.textContent = text;
		if (className) node.className = className;
		parent.append(node);
		return node;
	}

	function renderProject(root, project) {
		const container = root.querySelector('[data-f27-project-items]');
		const empty = root.querySelector('[data-f27-project-empty]');
		const footer = root.querySelector('[data-f27-project-footer]');
		const count = root.querySelector('[data-f27-project-count]');
		const total = root.querySelector('[data-f27-project-total]');
		if (!container) return;
		container.replaceChildren();
		if (count) count.textContent = pluralPositions(project.items.length);
		if (empty) empty.hidden = project.items.length > 0;
		if (footer) footer.hidden = project.items.length === 0;
		let sum = 0;

		project.items.forEach(function (item, index) {
			const article = document.createElement('article');
			article.className = 'f27-project-item';
			article.dataset.index = String(index);
			const info = document.createElement('div');
			appendText(info, 'h3', item.name || item.slug);
			appendText(info, 'code', item.sku || '');
			appendText(info, 'p', optionKeys.map(function (key) { return item.options[key]; }).filter(Boolean).join(' / '));
			const controls = document.createElement('div');
			controls.className = 'f27-project-item__controls';
			const minus = appendText(controls, 'button', '−');
			minus.type = 'button';
			minus.dataset.f27Quantity = '-1';
			minus.setAttribute('aria-label', 'Уменьшить количество');
			appendText(controls, 'strong', String(item.quantity || 1));
			const plus = appendText(controls, 'button', '+');
			plus.type = 'button';
			plus.dataset.f27Quantity = '1';
			plus.setAttribute('aria-label', 'Увеличить количество');
			const remove = appendText(controls, 'button', 'Удалить', 'f27-text-button');
			remove.type = 'button';
			remove.dataset.f27Remove = 'true';
			article.append(info, controls);
			container.append(article);
			sum += Number(item.price || 0) * Number(item.quantity || 1);
		});
		if (total) total.textContent = money.format(sum);
	}

	function wrapCanvasText(context, text, maxWidth) {
		const words = String(text || '').split(/\s+/).filter(Boolean);
		const lines = [];
		let current = '';
		words.forEach(function (word) {
			const candidate = current ? current + ' ' + word : word;
			if (current && context.measureText(candidate).width > maxWidth) {
				lines.push(current);
				current = word;
			} else {
				current = candidate;
			}
		});
		if (current) lines.push(current);
		return lines.length ? lines : [''];
	}

	function drawCanvasLines(context, lines, x, y, lineHeight, maxLines) {
		lines.slice(0, maxLines || lines.length).forEach(function (line, index) {
			context.fillText(line, x, y + index * lineHeight);
		});
	}

	async function renderSpecificationPages(project) {
		if (document.fonts && document.fonts.ready) await document.fonts.ready;

		const width = 1240;
		const height = 1754;
		const margin = 88;
		const rowsPerPage = 5;
		const pages = [];
		const pageCount = Math.ceil(project.items.length / rowsPerPage);
		const total = project.items.reduce(function (sum, item) {
			return sum + Number(item.price || 0) * Number(item.quantity || 1);
		}, 0);

		for (let pageIndex = 0; pageIndex < pageCount; pageIndex += 1) {
			const canvas = document.createElement('canvas');
			canvas.width = width;
			canvas.height = height;
			const context = canvas.getContext('2d', { alpha: false });
			context.fillStyle = '#f2f1ec';
			context.fillRect(0, 0, width, height);
			context.textBaseline = 'top';
			context.fillStyle = '#171918';
			context.font = '720 68px Onest, Arial, sans-serif';
			context.fillText(String(runtime.brandName || 'FORM 27'), margin, 72);
			context.font = '600 21px "IBM Plex Mono", Consolas, monospace';
			context.fillText('СПЕЦИФИКАЦИЯ', margin, 162);
			context.textAlign = 'right';
			context.font = '400 19px "IBM Plex Mono", Consolas, monospace';
			context.fillText(new Date().toLocaleDateString('ru-RU'), width - margin, 84);
			context.fillText((pageIndex + 1) + ' / ' + pageCount, width - margin, 122);
			context.textAlign = 'left';
			context.fillStyle = '#f05a28';
			context.fillRect(margin, 212, width - margin * 2, 8);

			const chunk = project.items.slice(pageIndex * rowsPerPage, (pageIndex + 1) * rowsPerPage);
			chunk.forEach(function (item, rowIndex) {
				const y = 266 + rowIndex * 242;
				const itemNumber = pageIndex * rowsPerPage + rowIndex + 1;
				const quantity = Number(item.quantity || 1);
				const rowTotal = Number(item.price || 0) * quantity;
				context.fillStyle = '#171918';
				context.font = '600 17px "IBM Plex Mono", Consolas, monospace';
				context.fillText(String(itemNumber).padStart(2, '0'), margin, y + 4);
				context.font = '620 38px Onest, Arial, sans-serif';
				context.fillText(String(item.name || item.slug), margin + 74, y);
				context.font = '400 18px "IBM Plex Mono", Consolas, monospace';
				context.fillStyle = '#4d5250';
				context.fillText(String(item.sku || ''), margin + 74, y + 54);
				const optionText = optionKeys.map(function (key) {
					return item.options && item.options[key] ? item.options[key] : '';
				}).filter(Boolean).join(' / ');
				context.font = '400 22px Onest, Arial, sans-serif';
				drawCanvasLines(context, wrapCanvasText(context, optionText, 690), margin + 74, y + 96, 31, 2);
				context.textAlign = 'right';
				context.fillStyle = '#171918';
				context.font = '600 20px "IBM Plex Mono", Consolas, monospace';
				context.fillText(quantity + ' шт.', width - margin, y + 12);
				context.font = '620 28px Onest, Arial, sans-serif';
				context.fillText(money.format(rowTotal), width - margin, y + 58);
				context.textAlign = 'left';
				context.fillStyle = '#b8bcba';
				context.fillRect(margin, y + 200, width - margin * 2, 2);
			});

			if (pageIndex === pageCount - 1) {
				const footerY = 1482;
				context.fillStyle = '#171918';
				context.font = '600 18px "IBM Plex Mono", Consolas, monospace';
				context.fillText('ИТОГО', margin, footerY);
				context.textAlign = 'right';
				context.font = '720 42px Onest, Arial, sans-serif';
				context.fillText(money.format(total), width - margin, footerY - 8);
				context.textAlign = 'left';
				context.fillStyle = '#4d5250';
				context.font = '400 18px Onest, Arial, sans-serif';
				if (runtime.demo !== false && runtime.disclaimer) {
					drawCanvasLines(
						context,
						wrapCanvasText(context, String(runtime.disclaimer), width - margin * 2),
						margin,
						footerY + 72,
						27,
						2
					);
				}
			}

			const jpeg = await new Promise(function (resolve, reject) {
				canvas.toBlob(function (blob) {
					if (blob) resolve(blob);
					else reject(new Error('Не удалось подготовить страницу PDF.'));
				}, 'image/jpeg', 0.9);
			});
			pages.push(new Uint8Array(await jpeg.arrayBuffer()));
		}

		return { pages: pages, width: width, height: height };
	}

	function asciiBytes(value) {
		return new TextEncoder().encode(value);
	}

	function byteLength(parts) {
		return parts.reduce(function (sum, part) { return sum + part.length; }, 0);
	}

	function buildPdf(pageData) {
		const pageWidth = 595.28;
		const pageHeight = 841.89;
		const pageIds = pageData.pages.map(function (_page, index) { return 3 + index * 3; });
		const objects = new Array(3 + pageData.pages.length * 3);
		objects[1] = [asciiBytes('<< /Type /Catalog /Pages 2 0 R >>')];
		objects[2] = [asciiBytes('<< /Type /Pages /Kids [' + pageIds.map(function (id) { return id + ' 0 R'; }).join(' ') + '] /Count ' + pageIds.length + ' >>')];

		pageData.pages.forEach(function (imageBytes, index) {
			const pageId = pageIds[index];
			const contentId = pageId + 1;
			const imageId = pageId + 2;
			const content = asciiBytes('q\n' + pageWidth + ' 0 0 ' + pageHeight + ' 0 0 cm\n/Im' + (index + 1) + ' Do\nQ');
			objects[pageId] = [asciiBytes('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pageWidth + ' ' + pageHeight + '] /Resources << /XObject << /Im' + (index + 1) + ' ' + imageId + ' 0 R >> >> /Contents ' + contentId + ' 0 R >>')];
			objects[contentId] = [asciiBytes('<< /Length ' + content.length + ' >>\nstream\n'), content, asciiBytes('\nendstream')];
			objects[imageId] = [asciiBytes('<< /Type /XObject /Subtype /Image /Width ' + pageData.width + ' /Height ' + pageData.height + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + imageBytes.length + ' >>\nstream\n'), imageBytes, asciiBytes('\nendstream')];
		});

		const header = new Uint8Array([37, 80, 68, 70, 45, 49, 46, 52, 10, 37, 226, 227, 207, 211, 10]);
		const output = [header];
		const offsets = [0];
		let offset = header.length;
		for (let id = 1; id < objects.length; id += 1) {
			const prefix = asciiBytes(id + ' 0 obj\n');
			const suffix = asciiBytes('\nendobj\n');
			offsets[id] = offset;
			output.push(prefix);
			objects[id].forEach(function (part) { output.push(part); });
			output.push(suffix);
			offset += prefix.length + byteLength(objects[id]) + suffix.length;
		}

		const xrefOffset = offset;
		let xref = 'xref\n0 ' + objects.length + '\n0000000000 65535 f \n';
		for (let id = 1; id < objects.length; id += 1) {
			xref += String(offsets[id]).padStart(10, '0') + ' 00000 n \n';
		}
		xref += 'trailer\n<< /Size ' + objects.length + ' /Root 1 0 R >>\nstartxref\n' + xrefOffset + '\n%%EOF';
		output.push(asciiBytes(xref));
		return new Blob(output, { type: 'application/pdf' });
	}

	async function downloadProjectPdf(project, status) {
		if (!project.items.length) {
			if (status) status.textContent = 'Сначала добавьте хотя бы один светильник в проект.';
			return;
		}
		if (status) status.textContent = 'Готовим PDF...';
		try {
			const pageData = await renderSpecificationPages(project);
			const url = URL.createObjectURL(buildPdf(pageData));
			const link = document.createElement('a');
			link.href = url;
			const fileBrand = String(runtime.brandName || 'FORM 27').replace(/[^A-Za-z0-9А-Яа-яЁё]+/g, '-').replace(/^-|-$/g, '') || 'FORM27';
			link.download = fileBrand + '-specification.pdf';
			document.body.appendChild(link);
			link.click();
			link.remove();
			setTimeout(function () { URL.revokeObjectURL(url); }, 30000);
			if (status) status.textContent = 'PDF готов. Файл сохранён в загрузках браузера.';
		} catch (error) {
			if (status) status.textContent = error.message || 'Не удалось создать PDF.';
		}
	}

	function initProject(root) {
		function refresh(event) { renderProject(root, event && event.detail ? event.detail : readProject()); }
		document.addEventListener('f27:project-changed', refresh);
		window.addEventListener('storage', function (event) { if (event.key === storageKey) refresh(); });
		root.addEventListener('click', function (event) {
			const project = readProject();
			const itemNode = event.target.closest('.f27-project-item');
			if (itemNode && event.target.matches('[data-f27-quantity]')) {
				const index = Number(itemNode.dataset.index);
				if (!project.items[index]) return;
				const next = Number(project.items[index].quantity || 1) + Number(event.target.dataset.f27Quantity);
				if (next < 1) project.items.splice(index, 1);
				else project.items[index].quantity = Math.min(99, next);
				writeProject(project);
			}
			if (itemNode && event.target.matches('[data-f27-remove]')) {
				project.items.splice(Number(itemNode.dataset.index), 1);
				writeProject(project);
			}
			if (event.target.closest('[data-f27-clear]')) writeProject(emptyProject());
			if (event.target.closest('[data-f27-print]')) downloadProjectPdf(project, root.querySelector('[data-f27-project-status]'));
		});
		refresh();
	}

	function initCases(root) {
		root.querySelectorAll('[data-f27-compare]').forEach(function (compare) {
			const range = compare.querySelector('[data-f27-compare-range]');
			const after = compare.querySelector('[data-f27-after]');
			if (!range || !after) return;
			function update() { after.style.clipPath = 'inset(0 ' + (100 - Number(range.value)) + '% 0 0)'; }
			range.addEventListener('input', update);
			update();
		});
	}

	function initRequest(root) {
		const form = root.querySelector('[data-f27-request-form]');
		const status = root.querySelector('[data-f27-request-status]');
		if (!form || !status) return;
		form.hidden = false;
		if (!runtime.requestsEnabled || root.dataset.staticDemo === 'true') {
			root.dataset.staticDemo = 'true';
			const submit = form.querySelector('[type="submit"]');
			if (submit) submit.textContent = 'Проверить демо-форму';
		}
		form.elements.startedAt.value = String(Date.now());
		form.addEventListener('submit', async function (event) {
			event.preventDefault();
			if (!form.reportValidity()) return;
			const project = readProject();
			if (!project.items.length) {
				status.textContent = 'Сначала добавьте хотя бы один светильник в проект.';
				return;
			}
			if (!form.elements.email.value.trim() && !form.elements.phone.value.trim()) {
				status.textContent = 'Укажите телефон или электронную почту.';
				return;
			}
			const flat = Object.fromEntries(new FormData(form).entries());
			const data = {
				schemaVersion: 1,
				contact: { name: flat.name || '', email: flat.email || '', phone: flat.phone || '', company: flat.company || '' },
				project: { items: project.items },
				message: flat.message || '',
				consent: form.elements.consent.checked,
				startedAt: flat.startedAt,
				website: flat.website || ''
			};
			if (!runtime.requestsEnabled || root.dataset.staticDemo === 'true') {
				status.textContent = 'Демо-режим: данные не отправлены и не сохранены.';
				return;
			}

			const button = form.querySelector('[type="submit"]');
			button.disabled = true;
			status.textContent = 'Отправляем проект...';
			try {
				const baseUrl = String(runtime.restUrl || '/wp-json/form27/v1/').replace(/\/?$/, '/');
				const response = await fetch(baseUrl + 'requests', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': runtime.nonce || '' },
					body: JSON.stringify(data)
				});
				const result = await response.json();
				if (!response.ok) {
					if (result.code === 'f27_bad_timing') form.elements.startedAt.value = String(Date.now());
					throw new Error(result.message || 'Не удалось отправить проект.');
				}
				status.textContent = result.message;
				form.reset();
				form.elements.startedAt.value = String(Date.now());
			} catch (error) {
				status.textContent = error.message || 'Не удалось отправить проект.';
			} finally {
				button.disabled = false;
			}
		});
	}

	function boot() {
		document.querySelectorAll('[data-f27-catalog]').forEach(initCatalog);
		document.querySelectorAll('[data-f27-configurator]').forEach(initConfigurator);
		document.querySelectorAll('[data-f27-project]').forEach(initProject);
		document.querySelectorAll('[data-f27-cases]').forEach(initCases);
		document.querySelectorAll('[data-f27-request]').forEach(initRequest);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
	else boot();
}());

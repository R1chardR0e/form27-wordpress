(function () {
	"use strict";

	var root = document.documentElement;
	var storageKey = "form27.theme";
	var modes = ["auto", "light", "dark"];
	var labels = {
		auto: "Тема: авто",
		light: "Тема: светлая",
		dark: "Тема: тёмная"
	};

	function readMode() {
		try {
			var saved = window.localStorage.getItem(storageKey);
			return modes.indexOf(saved) !== -1 ? saved : "auto";
		} catch (error) {
			return "auto";
		}
	}

	function writeMode(mode) {
		try {
			if (mode === "auto") {
				window.localStorage.removeItem(storageKey);
			} else {
				window.localStorage.setItem(storageKey, mode);
			}
		} catch (error) {
			return;
		}
	}

	function applyMode(mode) {
		if (mode === "auto") {
			delete root.dataset.theme;
		} else {
			root.dataset.theme = mode;
		}

		document.querySelectorAll("[data-f27-theme-toggle]").forEach(function (button) {
			button.textContent = labels[mode];
			button.dataset.mode = mode;
			button.setAttribute("aria-label", labels[mode] + ". Нажмите, чтобы изменить цветовую схему");
		});
	}

	function initThemeControls() {
		var mode = readMode();
		applyMode(mode);

		document.querySelectorAll("[data-f27-theme-toggle]").forEach(function (button) {
			button.addEventListener("click", function () {
				var current = readMode();
				var next = modes[(modes.indexOf(current) + 1) % modes.length];
				writeMode(next);
				applyMode(next);
			});
		});
	}

	function cloneNavigation(target) {
		var source = document.querySelector(".f27-desktop-nav .wp-block-navigation__container");
		if (!source || !target) {
			return;
		}

		var links = source.querySelectorAll("a[href]");
		if (!links.length) {
			return;
		}

		var fragment = document.createDocumentFragment();
		links.forEach(function (sourceLink) {
			var link = document.createElement("a");
			link.href = sourceLink.href;
			link.textContent = sourceLink.textContent.trim();
			if (sourceLink.getAttribute("target")) {
				link.setAttribute("target", sourceLink.getAttribute("target"));
			}
			if (sourceLink.getAttribute("rel")) {
				link.setAttribute("rel", sourceLink.getAttribute("rel"));
			}
			fragment.appendChild(link);
		});

		target.replaceChildren(fragment);
	}

	function initMenu() {
		var dialog = document.querySelector("[data-f27-menu-dialog]");
		var openButton = document.querySelector("[data-f27-menu-open]");
		var closeButton = dialog ? dialog.querySelector("[data-f27-menu-close]") : null;
		var dialogNavigation = dialog ? dialog.querySelector("[data-f27-dialog-navigation]") : null;

		if (!dialog || !openButton || !closeButton) {
			return;
		}

		cloneNavigation(dialogNavigation);
		var restoreFocusAfterClose = false;

		function syncClosedState() {
			openButton.setAttribute("aria-expanded", "false");
			document.body.classList.remove("f27-dialog-open");
			if (restoreFocusAfterClose && openButton.isConnected) {
				openButton.focus();
			}
			restoreFocusAfterClose = false;
		}

		function closeDialog(restoreFocus) {
			restoreFocusAfterClose = restoreFocus !== false;
			if (dialog.open && typeof dialog.close === "function") {
				dialog.close();
			} else {
				dialog.removeAttribute("open");
				syncClosedState();
			}
		}

		openButton.addEventListener("click", function () {
			if (typeof dialog.showModal === "function") {
				dialog.showModal();
			} else {
				dialog.setAttribute("open", "");
			}
			openButton.setAttribute("aria-expanded", "true");
			document.body.classList.add("f27-dialog-open");
			closeButton.focus();
		});

		closeButton.addEventListener("click", function () {
			closeDialog(true);
		});
		dialog.addEventListener("cancel", function () {
			restoreFocusAfterClose = true;
			openButton.setAttribute("aria-expanded", "false");
			document.body.classList.remove("f27-dialog-open");
		});
		dialog.addEventListener("close", syncClosedState);
		dialog.addEventListener("click", function (event) {
			if (event.target === dialog) {
				closeDialog(true);
			}
		});

		dialog.querySelectorAll("a").forEach(function (link) {
			link.addEventListener("click", function () {
				closeDialog(false);
			});
		});

		window.matchMedia("(min-width: 960px)").addEventListener("change", function (event) {
			if (event.matches) {
				closeDialog(false);
			}
		});
	}

	function initReveals() {
		var elements = document.querySelectorAll(".f27-reveal");
		if (!elements.length) {
			return;
		}

		if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) {
			elements.forEach(function (element) {
				element.classList.add("is-visible");
			});
			return;
		}

		root.classList.add("f27-motion-ready");
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add("is-visible");
					observer.unobserve(entry.target);
				}
			});
		}, {
			rootMargin: "0px 0px -12% 0px",
			threshold: 0.08
		});

		elements.forEach(function (element) {
			observer.observe(element);
		});
	}

	function init() {
		initThemeControls();
		initMenu();
		initReveals();
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init, { once: true });
	} else {
		init();
	}
}());

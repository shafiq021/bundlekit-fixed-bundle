/**
 * BundleKit – Fixed Bundle global styling page (1.8.0).
 * Live preview: every styling control on the left updates the
 * preview boxes on the right instantly, using the same field →
 * CSS variable map as the frontend (bundlekitStyling.varMap,
 * localized from PHP). Color fields use the WP color picker; its
 * change callback also refreshes the preview. The layout select
 * switches which preview (Style 1 / Style 2) is visible via the
 * .is-hidden class. Dimming of layout-specific cards is handled by
 * the shared framework script (bundlekit-admin-ui.js) through
 * [data-bk-group] / [data-bk-layout] — no inline styles here.
 */
(function ($) {
	'use strict';

	function fieldVar(field) {
		var map = window.bundlekitStyling && window.bundlekitStyling.varMap ? window.bundlekitStyling.varMap : {};
		// varMap is var -> [field, format]; invert it for field lookup.
		for (var v in map) {
			if (Object.prototype.hasOwnProperty.call(map, v) && map[v][0] === field) {
				return { variable: v, format: map[v][1] };
			}
		}
		return null;
	}

	function fieldValue(field) {
		var input = document.getElementById('bundlekit_style_' + field);
		if (!input) {
			return '';
		}
		return input.value ? input.value.trim() : '';
	}

	function updatePreview() {
		var boxes = document.querySelectorAll('#bundlekit-preview-style1 .bundlekit-bundle-box, #bundlekit-preview-style2 .bundlekit-bundle-box');
		boxes.forEach(function (box) {
			// Rebuild the inline variables from the current controls.
			var fields = document.querySelectorAll('[id^="bundlekit_style_"]');
			fields.forEach(function (input) {
				var field = input.id.replace('bundlekit_style_', '');
				var mapping = fieldVar(field);
				if (!mapping) {
					return;
				}
				var value = input.value ? input.value.trim() : '';
				if (!value) {
					box.style.removeProperty(mapping.variable);
					return;
				}
				if (mapping.format === 'px') {
					value += 'px';
				} else if (mapping.format === 'font') {
					var fonts = window.bundlekitStyling && window.bundlekitStyling.fonts ? window.bundlekitStyling.fonts : {};
					value = fonts[value] || '';
					if (!value) {
						box.style.removeProperty(mapping.variable);
						return;
					}
				}
				box.style.setProperty(mapping.variable, value);
			});
		});
	}

	function syncLayout() {
		var layout = document.getElementById('bundlekit_fixed_bundle_default_layout');
		var value = layout ? layout.value : 'style1';
		var s1 = document.getElementById('bundlekit-preview-style1');
		var s2 = document.getElementById('bundlekit-preview-style2');
		if (s1) {
			s1.classList.toggle('is-hidden', value !== 'style1');
		}
		if (s2) {
			s2.classList.toggle('is-hidden', value !== 'style2');
		}
		// Card dimming for layout-specific control groups is handled
		// by the shared framework script via [data-bk-layout-select].
	}

	function init() {
		var layout = document.getElementById('bundlekit_fixed_bundle_default_layout');
		if (layout) {
			layout.addEventListener('change', syncLayout);
		}
		syncLayout();

		// Color pickers: refresh the preview whenever a color changes.
		$('.bundlekit-color-field').wpColorPicker({
			change: function () {
				// Iris fires change before the input value updates;
				// wait a tick so we read the new value.
				setTimeout(updatePreview, 0);
			},
			clear: function () {
				setTimeout(updatePreview, 0);
			}
		});

		// All other controls: live update on input / change.
		document.querySelectorAll('[id^="bundlekit_style_"]').forEach(function (input) {
			input.addEventListener('input', updatePreview);
			input.addEventListener('change', updatePreview);
		});
		updatePreview();
	}

	$(init);
})(jQuery);

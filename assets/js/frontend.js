/**
 * BundleKit – Fixed Bundle frontend.
 * Loaded ONLY on a product page that shows a bundle box.
 *
 * 1. Whole-box click toggles the checkbox. The checkbox itself is a
 *    <label> target, so a direct click on it toggles natively — the
 *    box click handler ignores clicks that land on the checkbox or
 *    the label, so it never toggles twice.
 * 2. Visual state: .is-checked on the box when the checkbox is ticked.
 * 3. Relocation: if the box was rendered outside the main add-to-cart
 *    form (a theme that skipped the in-form hook), move it inside
 *    form.cart, just before the Add to cart button, so the checkbox
 *    is actually submitted with the order. If there is no form.cart
 *    on the page, leave the box where it is.
 * 4. Session flag: on every tick/untick — and once on page load, to
 *    clear a stale flag from an earlier visit — POST the checkbox state
 *    to the bundlekit_set_bundle wc-ajax endpoint (data-ajax-url /
 *    data-nonce on the box). The server stores it in the WooCommerce
 *    session, so the bundle is still added when a theme's Add to cart
 *    or Buy now button submits via AJAX and only sends product id +
 *    quantity (the checkbox field never arrives in that case). If this
 *    request fails, the checkbox inside form.cart still covers a
 *    normal form POST.
 * 5. Style 2 Breakdown popup: the Breakdown button toggles the popup
 *    above the footer (chevron flips), a click anywhere outside the
 *    footer closes it, and clicks on the button / popup never reach
 *    the box click handler, so opening the breakdown cannot tick or
 *    untick the bundle.
 */
(function () {
	'use strict';

	function syncFlag(box, checkbox) {
		var url = box.getAttribute('data-ajax-url');
		var productId = box.getAttribute('data-product-id');
		if (!url || !productId) {
			return;
		}
		var data = new FormData();
		data.append('product_id', productId);
		data.append('checked', checkbox.checked ? '1' : '0');
		var nonce = box.getAttribute('data-nonce');
		if (nonce) {
			data.append('nonce', nonce);
		}
		fetch(url, { method: 'POST', body: data, credentials: 'same-origin' }).catch(function () {
			/* The checkbox inside form.cart still covers a normal POST. */
		});
	}

	function initBox(box) {
		var checkbox = box.querySelector('.bundlekit-bundle-checkbox');
		if (!checkbox) {
			return;
		}

		function sync() {
			box.classList.toggle('is-checked', checkbox.checked);
		}
		checkbox.addEventListener('change', function () {
			sync();
			syncFlag(box, checkbox);
		});
		sync();

		box.addEventListener('click', function (e) {
			// A click on the checkbox / its label already toggles it.
			if (e.target === checkbox || e.target.closest('label')) {
				return;
			}
			// Style 2: opening the Breakdown popup must not toggle
			// the bundle on or off.
			if (e.target.closest('.bundlekit-bundle2-breakdown, .bundlekit-bundle2-popup')) {
				return;
			}
			checkbox.checked = !checkbox.checked;
			sync();
			syncFlag(box, checkbox);
		});

		// Clear any stale flag left from an earlier visit (the box always
		// starts unticked).
		syncFlag(box, checkbox);
	}

	function initBreakdown(box) {
		var btn = box.querySelector('.bundlekit-bundle2-breakdown');
		var foot = box.querySelector('.bundlekit-bundle2-foot');
		if (!btn || !foot) {
			return;
		}
		btn.addEventListener('click', function (e) {
			e.stopPropagation();
			var open = foot.classList.toggle('is-open');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		document.addEventListener('click', function (e) {
			if (foot.classList.contains('is-open') && !foot.contains(e.target)) {
				foot.classList.remove('is-open');
				btn.setAttribute('aria-expanded', 'false');
			}
		});
	}

	function relocateBox(box) {
		if (box.closest('form.cart')) {
			return;
		}
		var form = document.querySelector('form.cart');
		if (!form) {
			return;
		}
		var button = form.querySelector('.single_add_to_cart_button');
		if (button && button.parentNode) {
			button.parentNode.insertBefore(box, button);
		} else {
			form.appendChild(box);
		}
	}

	function init() {
		document.querySelectorAll('[data-bundlekit-box]').forEach(function (box) {
			relocateBox(box);
			initBox(box);
			initBreakdown(box);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

/**
 * reCAPTCHA v3 is invisible — no widget, so a fresh token has to be minted
 * right before each submit (tokens expire after ~2 minutes) and dropped
 * into the hidden field the form already carries. Intercepts submit,
 * waits for the token, then submits for real.
 */
(function () {
	if (typeof grecaptcha === 'undefined' || !window.bootgRecaptchaV3) { return; }

	document.querySelectorAll('form.bootg-form').forEach(function (form) {
		var tokenField = form.querySelector('.bootg-recaptcha-v3-token');
		if (!tokenField) { return; }

		form.addEventListener('submit', function (e) {
			if (form.dataset.recaptchaReady === '1') { return; }
			e.preventDefault();
			grecaptcha.ready(function () {
				grecaptcha.execute(window.bootgRecaptchaV3.siteKey, { action: 'submit' }).then(function (token) {
					tokenField.value = token;
					form.dataset.recaptchaReady = '1';
					form.submit();
				});
			});
		});
	});
})();

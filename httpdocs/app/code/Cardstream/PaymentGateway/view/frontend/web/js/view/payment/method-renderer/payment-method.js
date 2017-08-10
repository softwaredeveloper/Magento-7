/*browser:true*/
/*global define*/
define(
	[
		'Magento_Checkout/js/view/payment/default',
		'mage/url'
	],
	function (Component, url) {
		'use strict';

		return Component.extend({
			defaults: {
				template: 'Cardstream_PaymentGateway/payment/form'
			},
			redirectAfterPlaceOrder: false,
			getData: function() {
				// Compile a custom data object
				var data = {
					method: this.getCode(),
					additional_data: {}
				};
				// Get all input fields during quote process
				var fields = 'payment_form_' + this.getCode();
				fields = document.getElementById(fields);
				if (fields) {
					fields = fields.getElementsByTagName('input');
					[].slice.call(fields).forEach((i) => {
						var name = i.name;
						data.additional_data[name] = i.value;
					});
				}
				return data;
			},
			validate: function() {
				var form = 'payment_form_' + this.getCode();
				form = document.getElementById(form);
				var validators = {
					cardNumber: {
						error: 'Must be a numeric 13-19 digit number',
						validate: v => v.match(/^(?:\d{4} ?){3} ?\d{1,4}(?: ?\d{0,3})?$/)
					},
					cardExpiryMonth: {
						error: 'Must be a valid numberic month',
						validate: v => v >= 1 && v <= 12
					},
					cardExpiryYear: {
						error: () => {
							return 'Must be a valid 2-digit year (' + new Date().getFullYear() + ' and above)';
						},
						validate: v => v >= (new Date().getFullYear().toString().substr(-2))
					},
					cardCVV: {
						error: 'Must be a valid 3 or 4 digit number',
						validate: v => v.match(/^\d{3,4}/)
					}
				};
				var isValid = true;
				var buildAlert = '';
				if (form) {
					var fields = [].slice.call(form.getElementsByTagName('input'));
					for (var i = 0; i < fields.length; i++) {

						var field = fields[i];
						var errorId = field.getAttribute('id') + '_error';
						var errorEl = document.getElementById(errorId);

						if (validators.hasOwnProperty(field.name)) {

							var validation = validators[field.name];
							var isFieldValid = validation.validate(field.value);
							var error = (typeof(validation.error) == 'function' ? validation.error() : validation.error);

							if (!isFieldValid && errorEl) {
								isValid = false;
								errorEl.innerText = error;
								errorEl.style.display = 'block';
							} else if (!isFieldValid && !errorEl) {
								isValid = false;
								buildAlert += field.getAttribute('title') + ' ' + error.charAt(0).toLowerCase() + error.substring(1) + '\n';
								// Stop spamming
							} else if (isFieldValid && errorEl) {
								errorEl.style.display = 'none';
							}
						}
					}
				}
				if (buildAlert.length > 0) {
					alert(buildAlert);
				}
				return isValid;
			},
			/**
			 * After place order callback
			 */
			afterPlaceOrder: function () {
				window.location.replace(url.build('cardstream/order/process'));
			}
		});
	}
);



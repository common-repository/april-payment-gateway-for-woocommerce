/* global wc_april_params, wc_april_params_amount, wc_april_params_currency */

jQuery( function( $ ) {
	'use strict';

	var lpWooComForm = {

		init: function() {
			this.id = 'april';	// Change the id to match payment method
			this.method = 'april';	// Change the method name to match payment method

			if (this.isCheckout()) {
				this.wcForm = $('form.woocommerce-checkout');
			}

			if (this.isOrderReview()) {
				this.wcForm = $('form#order_review');
			}

			if (this.isAddPaymentMethod()) {
				this.wcForm = $('form#add_payment_method');
			}

			if (!this.wcForm) {
				return;
			}

			$('form#order_review, form#add_payment_method').on(
				'submit',
				this.onInitialSubmit
			);

			this.wcForm.on('checkout_place_order_' + this.id, this.onInitialSubmit);

			this.aprilCheckout = AprilCheckout.createCheckout();
			this.aprilPaymentSource = AprilCheckout.createPaymentSource();
			if (!this.orderPayPaymentAction()) {
				this.renderOnCheckoutUpdates();
				this.renderApril();
			}

			this.paymentTokenInvalid = true;
			this.paymentSourceInvalid = true;
			window.addEventListener('hashchange', lpWooComForm.onHashChange);
		},

		loadAprilParams: function() {
			try {
				lpWooComForm.aprilCurrency = $('input#wc_' + lpWooComForm.method + '_params_currency').val();
				lpWooComForm.aprilAmount = parseInt( $('input#wc_' + lpWooComForm.method + '_params_amount').val() );
				lpWooComForm.aprilParams = wc_april_params;
			} catch(e) {
				console.log('April checkout parameters are missing');
				return false;
			}
			return true;
		},

		checkoutDisabledMessage: function(message) {
			$('#' + lpWooComForm.aprilParams.element_id).html(message);
		},

		isOrderReview: function() {
			return !!$('form#order_review').length;
		},

		isAddPaymentMethod: function() {
			return !!$('form#add_payment_method').length;
		},

		isCheckout: function() {
			return !!$('form.woocommerce-checkout').length;
		},

		renderApril: function() {
			if (lpWooComForm.loadAprilParams()) {
				if (lpWooComForm.aprilParams.checkout_disabled) {
					lpWooComForm.checkoutDisabledMessage(lpWooComForm.aprilParams.checkout_disabled_message);
				} else {
					if (lpWooComForm.aprilParams.pay_source_only) {
						lpWooComForm.renderAprilPaymentSource();
					} else {
						lpWooComForm.renderAprilCheckout();
					}
				}
			}
		},

		renderAprilCheckout: function() {
			var initParams = {
				publicKey: lpWooComForm.aprilParams.publishable_key,
				preventWalletSubmit: lpWooComForm.aprilParams.prevent_wallet_submit,
				email: lpWooComForm.aprilParams.email,
				customerFirstName: lpWooComForm.aprilParams.first_name,
				customerLastName: lpWooComForm.aprilParams.last_name,
				hidePayLaterOption: (lpWooComForm.aprilParams.available_payment_option === "paycard"),
				hideFullPayOption: (lpWooComForm.aprilParams.available_payment_option === "payplan"),
				paymentToken: lpWooComForm.handlePaymentToken,
				platform: lpWooComForm.aprilParams.platform,
				platformVersion: lpWooComForm.aprilParams.platform_version,
				platformPluginVersion: lpWooComForm.aprilParams.platform_plugin_version,
				customerToken: lpWooComForm.aprilParams.custom_token
			};

			lpWooComForm.aprilCheckout.init(initParams);
			lpWooComForm.aprilCheckout.errorHandler(this.handleAprilError);
			lpWooComForm.aprilCheckout.eventHandler(this.handleAprilEvent);

			var renderParams = {
				elementId: lpWooComForm.aprilParams.element_id,
				currency: lpWooComForm.aprilCurrency,
				amount: lpWooComForm.aprilAmount,
				paymentType: lpWooComForm.aprilParams.payment_type,
				showPayNow: false,
				showPayPlanSubmit: false,
			};
			if (lpWooComForm.aprilParams.primary_color) {
				renderParams.primaryColor = lpWooComForm.aprilParams.primary_color;
			}
			lpWooComForm.aprilCheckout.render(renderParams);
		},

		renderAprilPaymentSource: function() {
			var initParams = {
				publicKey: lpWooComForm.aprilParams.publishable_key,
				// submitCallbackFunction: lpWooComForm.handlePaymentSource,
				// platform: lpWooComForm.aprilParams.platform,
				// platformVersion: lpWooComForm.aprilParams.platform_version,
				// platformPluginVersion: lpWooComForm.aprilParams.platform_plugin_version,
				customerToken: lpWooComForm.aprilParams.custom_token
			};

			lpWooComForm.aprilPaymentSource.init(initParams);
			// lpWooComForm.aprilPaymentSource.errorHandler(this.handleAprilError); // TODO

			var renderParams = {
				elementId: lpWooComForm.aprilParams.element_id,
				showSubmit: false,
				hideSavedCards: false
			};
			if (lpWooComForm.aprilParams.primary_color) {
				renderParams.primaryColor = lpWooComForm.aprilParams.primary_color;
			}
			lpWooComForm.aprilPaymentSource.render(renderParams);
		},

		renderOnCheckoutUpdates: function() {
			$(document.body).on('updated_checkout', this.renderApril);
		},

		handlePaymentToken: function(paymentToken, paymentData) {
			lpWooComForm.setPaymentTokenAndData(paymentToken, paymentData);
			lpWooComForm.formSubmit();
		},

		handlePaymentSource: function(paymentSource) {
			if (paymentSource && paymentSource.cardPaymentSource) {
				lpWooComForm.setPaymentSource(paymentSource.cardPaymentSource.cardPaymentSourceId);
				lpWooComForm.formSubmit();
			}
		},

		onInitialSubmit: function() {
			if (lpWooComForm.isChecked()) {
				if (lpWooComForm.aprilParams.pay_source_only) {
					return lpWooComForm.requestPaymentSource();
				} else {
					return lpWooComForm.requestPaymentToken();
				}
			}
			return true;
		},

		formSubmit: function() {
			lpWooComForm.wcForm.trigger('submit');
		},

		requestPaymentToken: function() {
				var $pTInput = $('input.' + lpWooComForm.method + '-payment-token');
				if ($pTInput.length && $pTInput.val() && $pTInput.val() != '0' && !this.paymentTokenInvalid) {
					this.paymentTokenInvalid = true;
					return true;
				}
				lpWooComForm.aprilCheckout.submit();
				return false;
		},

		requestPaymentSource: function() {
				var $pSInput = $('input.' + lpWooComForm.method + '-payment-source');
				if ($pSInput.length && $pSInput.val() && $pSInput.val() != '0' && !this.paymentSourceInvalid) {
					this.paymentSourceInvalid = true;
					return true;
				}
				lpWooComForm.aprilPaymentSource.submit( lpWooComForm.handlePaymentSource );
				return false;
		},

		isChecked: function() {
			var aprilInput = $('input#payment_method_' + lpWooComForm.id);
			return aprilInput.is(':checked');
		},

		scrollIntoViewApril: function () {
			var aprilPH = document.getElementById(lpWooComForm.aprilParams.element_id);
		  aprilPH.scrollIntoView();
		},

		setPaymentTokenAndData: function(paymentToken, paymentData) {
			$('input.' + lpWooComForm.method + '-payment-token').remove();
			$('input.' + lpWooComForm.method + '-payment-data').remove();
			lpWooComForm.wcForm.append($('<input type="hidden" />').addClass(lpWooComForm.method + '-payment-token').attr('name', lpWooComForm.id + '_payment_token').val(paymentToken));
			lpWooComForm.wcForm.append($('<input type="hidden" />').addClass(lpWooComForm.method + '-payment-data').attr('name', lpWooComForm.id + '_payment_data').val(JSON.stringify(paymentData)));
			this.paymentTokenInvalid = false;
		},

		setPaymentSource: function(paymentSource) {
			$('input.' + lpWooComForm.method + '-payment-source').remove();
			lpWooComForm.wcForm.append($('<input type="hidden" />').addClass(lpWooComForm.method + '-payment-source').attr('name', lpWooComForm.id + '_payment_source').val(paymentSource));
			this.paymentSourceInvalid = false;
		},

		onHashChange: function() {
			var payActParams = window.location.hash.split('::');
			if ( ! payActParams || 2 > payActParams.length ) {
				return;
			}

			var payAction = JSON.parse(decodeURIComponent(payActParams[1]));
			var redirectURL = decodeURIComponent( payActParams[2] );
			var paymentMethodId = decodeURIComponent( payActParams[3] );

			if (paymentMethodId === lpWooComForm.id) {
				window.location.hash = '';
				lpWooComForm.openPayActionModal(payAction, redirectURL);
			}
		},

		orderPayPaymentAction: function() {
			if ( ! $( '#' + lpWooComForm.method + '-payment-action' ).length || ! $( '#' + lpWooComForm.method + '-payment-action-url' ).length ) {
				return false;
			}

			var payAction = JSON.parse($( '#' + lpWooComForm.method + '-payment-action' ).val());
			var payActionUrl = $( '#' + lpWooComForm.method + '-payment-action-url' ).val();

			lpWooComForm.openPayActionModal(payAction, payActionUrl);
			return true;
		},

		openPayActionModal: function( payAction, redirectURL ) {
			lpWooComForm.aprilCheckout.handleThreeDSAuthorisationRequired(
			  payAction,
			  lpWooComForm.submitAfterPaymentAction( redirectURL ),
			  function ( message ) {
			    lpWooComForm.submitError( message );
			  }
			);
		},

		submitAfterPaymentAction: function ( redirectURL ) {
			return function() {
				$.ajax({
					type:	'GET',
					url: redirectURL + (lpWooComForm.isOrderReview() ? '&wc-april-order-review=1' : '') + '&methodId=' + lpWooComForm.id + '&is_ajax',
					dataType: 'json',
					success: function( result ) {
						try {
							if ( true === result.success ) {
								if ( result.data && result.data.payment_action_required) {
									var payAction = JSON.parse(result.data.payment_action_required);
									var redirect = result.data.redirect;
									lpWooComForm.openPayActionModal( payAction, redirect );
								} else if ( result.data && result.data.redirect) {
									if ( -1 === result.data.redirect.indexOf( 'https://' ) || -1 === result.data.redirect.indexOf( 'http://' ) ) {
										window.location = result.data.redirect;
									} else {
										window.location = decodeURI( result.data.redirect );
									}
								} else {
									throw 'Redirect URL not found';
								}
							} else if ( false === result.success ) {
								throw 'Result failure';
							} else {
								throw 'Invalid response';
							}
						} catch( err ) {
							if ( result.data.message ) {
								lpWooComForm.submitError( result.data.message );
							} else {
								lpWooComForm.submitError( '<div class="woocommerce-error">' + err.message + '</div>' );
							}
						}
					},
					error:	function( jqXHR, textStatus, errorThrown ) {
						lpWooComForm.submitError( '<div class="woocommerce-error">' + errorThrown + '</div>' );
					}
				});
			}
		},

		submitError: function( errorMessage ) {
			var checkoutForm = $( 'form.checkout' );
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			checkoutForm.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error"><li>' + errorMessage + '</li></ul></div>' );
			checkoutForm.removeClass( 'processing' ).unblock();
			checkoutForm.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			lpWooComForm.scrollToNotices();
			$( document.body ).trigger( 'checkout_error' );
		},

		scrollToNotices: function() {
			var scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );

			if ( ! scrollElement.length ) {
				scrollElement = $( '.form.checkout' );
			}
			$.scroll_to_notices( scrollElement );
		},

		handleAprilEvent: function(lpEvent) {
			if (lpEvent.eventName == 'april_card_3DS_pending') {
				this.scrollIntoViewApril();
			}
		},

		handleAprilError: function(error) {
			lpWooComForm.formUnblock();
			lpWooComForm.scrollIntoViewApril();
		},

		reset: function() {
			$('.' + lpWooComForm.method + '-payment-token').remove();
			$('.' + lpWooComForm.method + '-payment-data').remove();
		},

		formUnblock: function() {
			lpWooComForm.wcForm && lpWooComForm.wcForm.unblock();
		},

		formBlock: function() {
			lpWooComForm.wcForm && lpWooComForm.wcForm.block();
		},

	};

	lpWooComForm.init();
});

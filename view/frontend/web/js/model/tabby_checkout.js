define(
    [
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/step-navigator',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Ui/js/model/messageList',
        'mage/storage'
    ],
    function(Customer, Quote, UrlBuilder, StepNavigator, fullScreenLoader, additionalValidators, messageList, storage) {
        'use strict';
        var instance;

        function createInstance() {

            return {
                checkout_id: null,
                relaunchTabby: false,
                timeout_id: null,
				products: null,
				renderers: {},

                initialize: function() {

                    this.config = window.checkoutConfig.payment.tabby_checkout;
		    		this.total_prefix = window.checkoutConfig.payment.tabby_checkout.config.total_prefix;
                    window.tabbyModel = this;
                    this.payment = null;
					this.product = null;
                    this.initCheckout();
                    this.initUpdates();
                    return this;
                },
				isCheckoutAllowed: function (code) {
					if (this.products) {
						if (code == 'tabby_installments' && this.products.hasOwnProperty('installments')) return true; 
						if (code == 'tabby_checkout'     && this.products.hasOwnProperty('payLater')) return true; 
					}
					return false;
				},
                initCheckout: function() {
                    //console.log("initCheckout");
                    this.disableButton();
                    if (!this.loadOrderHistory()) return;
                    var tabbyConfig = {
						apiKey: this.config.config.apiKey
					};
					//console.log(tabbyConfig);
                    var payment = this.getPaymentObject();
                    //console.log(payment);
                    if (!payment.buyer || !payment.buyer.name || payment.buyer.name == ' ') {
                        //console.log('buyer empty');
                        // no shipping address, hide checkout.
                        return;
                    }
                    if (JSON.stringify(this.payment) == JSON.stringify(payment)) {
                        if (this.checkout_id) this.enableButton();
                        // objects same
                        return;
                    }
                    this.checkout_id = null;
                    this.payment = payment;
                    tabbyConfig.payment = payment;
					tabbyModel.products = null;
                    tabbyConfig.onChange = data => {
                        console.log(data);
                        switch (data.status) {
                            case 'created':
                                //console.log('created', data);
                                fullScreenLoader.stopLoader();
                                tabbyModel.checkout_id = data.id;
								tabbyModel.products = data.products;
                                tabbyModel.enableButton();
                                if (tabbyModel.relaunchTabby) {
                                    fullScreenLoader.stopLoader();
                                    tabbyModel.launch();
                                    tabbyModel.relaunchTabby = false;
                                }
                                break;
                            case 'authorized':
                            case 'approved':
                                tabbyModel.checkout_id = data.payment.id;
								if (data.payment.status == 'authorized' || data.payment.status == 'AUTHORIZED') {
									if (tabbyModel.renderers.hasOwnProperty(tabbyModel.product)) 
										tabbyModel.renderers[tabbyModel.product].placeTabbyOrder();
								}
                                break;
                            case 'error':
                                if (data.id == null) {
                                    const msg = document.querySelector('#tabby-checkout-info');
                                    msg.innerHTML = data.statusMessage;
                                    msg.style.display = 'block';
                                }
                                fullScreenLoader.stopLoader();
                                break;
                            default:
                                fullScreenLoader.stopLoader();
                                break;
                        }
                    };
                    tabbyConfig.onClose = () => {
                        tabbyModel.relaunchTabby = true;
                    };

                    //console.log(tabbyConfig);
                    Tabby.init(tabbyConfig);
                    this.create();
                    tabbyModel.relaunchTabby = false;
                },
				setProduct: function (product) {
					if (product == 'installments') 
						this.product = product;
					else 
						this.product = 'pay_later';
				},
                getOrderHistoryObject: function() {
                    return this.order_history;
                },
                loadOrderHistory: function() {
                    if (window.isCustomerLoggedIn) {
                        this.order_history = this.config.payment.order_history;
                        return true;
                    }
                    // email and phone same
                    if (
                        (Quote.guestEmail && this.email == Quote.guestEmail || (Quote.shippingAddress() && Quote.shippingAddress().telephone && this.phone == Quote.shippingAddress().telephone)) &&
                        this.order_history
                    ) {
                        return true;
                    }

                    this.order_history = null;
                    this.email = Quote.guestEmail;
                    this.phone = Quote.shippingAddress() ? Quote.shippingAddress().telephone : null;

                    if (!this.email || !this.phone) return false;

                    fullScreenLoader.startLoader();
                    storage.get(
                        UrlBuilder.createUrl('/guest-carts/:cartId/order-history/:email/:phone', {
                            cartId: Quote.getQuoteId(),
                            email: this.email,
                            phone: this.phone
                        })
                    ).done(function(response) {
                        fullScreenLoader.stopLoader();
                        tabbyModel.order_history = response;
                        tabbyModel.initCheckout();
                    }).fail(function() {
                        fullScreenLoader.stopLoader();
                        tabbyModel.order_history = null;
                    });

                    return false;
                },
                tabbyCheckout: function() {
					// if there is no active checkout - ignore chekcout request
					if (!this.checkout_id) return;
                    //console.log('Tabby.launch');
					if (this.renderers.hasOwnProperty(this.product)) {
						var renderer = this.renderers[this.product];
						if (!(renderer && renderer.validate() && additionalValidators.validate())) {
							return; 
						}
					}

                    if (this.relaunchTabby) {
                        fullScreenLoader.startLoader();
                        this.create();
                    } else {
                        this.launch(this.product);
                    }
                },
                launch: function() {
                    const checkout = document.querySelector('#tabby-checkout');
                    if (checkout) checkout.style.display = 'block';
			//console.log('launch with product', this.product);
			var prod = this.product;
                    Tabby.launch({ product: prod });
                },
                create: function() {
                    Tabby.create();
                    const checkout = document.querySelector('#tabby-checkout');
                    if (checkout) checkout.style.display = 'none';
                },
                disableButton: function() {
					for (var i in this.renderers) {
						if (!this.renderers.hasOwnProperty(i)) continue;
						this.renderers[i].disableButton();
					}
                },
                enableButton: function() {
					for (var i in this.renderers) {
						if (!this.renderers.hasOwnProperty(i)) continue;
						if ( this.products .hasOwnProperty(i)) this.renderers[i].enableButton();
					}
                },
                initUpdates: function() {
                    Quote.shippingAddress.subscribe(this.checkoutUpdated);
                    Quote.shippingMethod.subscribe(this.checkoutUpdated);
                    //Quote.billingAddress.subscribe(this.checkoutUpdated);
                    //console.log(Quote);
                    Quote.totals.subscribe(this.checkoutUpdated);
                },
                checkoutUpdated: function() {
                    if (tabbyModel.timeout_id) clearTimeout(tabbyModel.timeout_id);
                    tabbyModel.timeout_id = setTimeout(function() {
                        return tabbyModel.initCheckout();
                    }, 100);
                },
                getPaymentObject: function() {
                    var totals = (Quote.getTotals())();
		    var currency_prefix = this.total_prefix == 'base_' ? this.total_prefix : 'quote_';
                    return {
                        "amount": this.getTotalSegment(totals, 'grand_total'),
                        "currency": window.checkoutConfig.quoteData[currency_prefix + 'currency_code'],
                        "description": window.checkoutConfig.quoteData.entity_id,
                        "buyer": this.getBuyerObject(),
                        "order": this.getOrderObject(),
                        "shipping_address": this.getShippingAddressObject(),
                        "order_history": this.getOrderHistoryObject()
                    };
                },

                getBuyerObject: function() {
                    // buyer object
                    var buyer = {
                        "phone": "",
                        "email": "",
                        "name": "",
                        "dob": null
                    };
                    var shipping = Quote.shippingAddress();
                    if (!shipping) {
                        //StepNavigator.navigateTo('shipping');
                        return buyer;
                    }
                    buyer.name = shipping.firstname + " " + shipping.lastname;
                    buyer.phone = shipping.telephone;
                    if (window.isCustomerLoggedIn) {
                        // existing customer details
                        buyer.email = Customer.customerData.email;
                        if (Customer.customerData.hasOwnProperty('dob')) {
                            buyer.dob = Customer.customerData.dob;
                        }
                    } else {
                        // guest
                        buyer.email = Quote.guestEmail;
                    }
                    return buyer;
                },

                getOrderObject: function() {
                    var totals = (Quote.getTotals())();
//console.log(totals);

                    return {
                        "tax_amount": this.getTotalSegment(totals, 'tax_amount'),
                        "shipping_amount": this.getTotalSegment(totals, 'shipping_incl_tax'),
                        "discount_amount": this.getTotalSegment(totals, 'discount_amount'),
                        "reference_id": Quote.getQuoteId(),
                        "items": this.getOrderItemsObject()
                    }
                },
                getShippingAddressObject: function() {
                    var shippingAddress = Quote.shippingAddress();

                    if (!shippingAddress.city) {
                        StepNavigator.navigateTo('shipping');
                        return;
                    }

                    return {
                        "city": shippingAddress.city,
                        "address": shippingAddress.hasOwnProperty('street') ? shippingAddress.street.join(", ") : '',
                        "zip": shippingAddress.postcode ? shippingAddress.postcode : null
                    }
                },

                getTotalSegment: function(totals, name) {
		                name = this.total_prefix + name;
                    //console.log(name);
                    //for (var i = 0; i < totals.total_segments.length; i++) {
		                //for (var i in totals) {
                    //    if (i == name) return totals[i];
                    //}

		                if (name == 'grand_total') {
			                return 0 + totals['grand_total'] + totals['tax_amount'];
		                }
                    if (totals.hasOwnProperty(name)) return totals[name];
                    return 0;
                },

                getOrderItemsObject: function() {
                    var items = Quote.getItems();
                    var itemsObject = [];
                    for (var i = 0; i < items.length; i++) {
                        var item_id = items[i].item_id;
                        itemsObject[i] = {
                            "title": items[i].name,
                            "quantity": items[i].qty,
                            "unit_price": items[i][this.total_prefix + 'price_incl_tax'],
                            "tax_amount": items[i][this.total_prefix + 'tax_amount'],
                            "reference_id": items[i].sku,
                            "image_url": this.config.urls.hasOwnProperty(item_id) ? this.config.urls[item_id].image_url : null,
                            "product_url": this.config.urls.hasOwnProperty(item_id) ? this.config.urls[item_id].product_url : null
                        };
                    }
                    return itemsObject;
                }
            }
        }

        function getSingletonInstance() {
            if (!instance) {
                instance = createInstance();
                instance.initialize();
            }
            return instance;
        }
        return getSingletonInstance();
    }
);

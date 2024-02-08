define(
    [
        'Magento_Customer/js/model/customer',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/step-navigator',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Ui/js/model/messageList',
        'mage/storage',
        'Tabby_Checkout/js/action/quote-item-data'
    ],
    function (
        Customer, customerData, checkoutData, Quote, UrlBuilder, StepNavigator, fullScreenLoader, additionalValidators,
        messageList, storage, quoteItemData) {
        'use strict';
        var instance;

        function createInstance() {

            return {
                payment_id: null,
                timeout_id: null,
                products: null,
                renderers: {},
                services: {},

                initialize: function () {

                    this.config = window.checkoutConfig.payment.tabby_checkout;
                    window.tabbyModel = this;
                    this.pricePrefix = window.checkoutConfig.payment.tabby_checkout.config.local_currency
                        ? ''
                        : 'base_';
                    this.payment = null;
                    this.product = null;
                    fullScreenLoader = {startLoader : function () {}, stopLoader: function (force = true) {}};
                    this.fullScreenLoader = fullScreenLoader;
                    this.initCheckout();
                    this.initUpdates();
                    return this;
                },
                registerRenderer: function (renderer) {
                    this.renderers[renderer.getTabbyCode()] = renderer;
                    this.services [renderer.getCode()] = renderer.getTabbyCode();
                    this.initTabbyCard();
                },
                isCheckoutAllowed: function (code) {
                    if (this.products) {
                        if (this.services.hasOwnProperty(code) &&
                            this.products.hasOwnProperty(this.services[code])) return true;
                    }
                    return false;
                },
                initTabbyCard: function () {
                    //if (!document.getElementById('tabbyCard') || !this.payment) return;
                    // tabbyCard init
                    for (var i in this.renderers) {
                        if (this.renderers.hasOwnProperty(i)) this.renderers[i].initTabbyCard(this.payment);
                    }
                },
                initCheckout: function () {
                    this.disableButton();
                    // load order history
                    if (!this.loadOrderHistory()) return;

                    var payment = this.getPaymentObject();
                    //console.log(payment);
                    if (!payment.buyer || !payment.buyer.name || payment.buyer.name == ' ') {
                        //console.log('buyer empty');
                        // no address, hide checkout.
                        return;
                    }
                    if (JSON.stringify(this.payment) == JSON.stringify(payment)) {
                        if (this.payment_id) this.enableButton();
                        // objects same
                        return;
                    }
                    this.payment_id = null;
                    this.payment = payment;
                    this.initTabbyCard();
                    tabbyModel.products = null;

                    var tabbyConfig = {
                        'apiKey'        : this.config.config.apiKey,
                        'lang'          : this.config.lang,
                        'merchantCode'  : this.config.storeGroupCode + ((this.pricePrefix == '') ? '_' + this.getTabbyCurrency() : ''),
                        'merchantUrls'  : this.config.config.merchantUrls,
                        'payment'       : payment
                    };

                    this.create(tabbyConfig);
                },
                setProduct: function (product) {
                    this.product = product;
                },
                getOrderHistoryObject: function () {
                    return this.order_history;
                },
                getBuyerHistoryObject: function () {
                    if (!window.isCustomerLoggedIn) {
                        // update loyalty_level for guests
                        tabbyModel.buyer_history = tabbyModel.config.payment.buyer_history;
                        tabbyModel.buyer_history.loyalty_level = 0;
                        tabbyModel.order_history.forEach((order) => {tabbyModel.buyer_history.loyalty_level += (order.status == 'complete') ? 1 : 0;});
                    }
                    return this.buyer_history;
                },
                loadOrderHistory: function () {
                    if (window.isCustomerLoggedIn) {
                        this.order_history = this.config.payment.order_history;
                        this.buyer_history = this.config.payment.buyer_history;
                        return true;
                    }
                    let phone = Quote.billingAddress() && Quote.billingAddress().telephone
                        ? Quote.billingAddress().telephone
                        : '';
                    // email and phone same
                    if (Quote.guestEmail && this.email == Quote.guestEmail && phone == this.phone &&
                        this.order_history) {
                        return true;
                    }

                    this.order_history = null;

                    if (this.config.config.hasOwnProperty('use_history') &&
                        !this.config.config.use_history) return true;

                    this.email = Quote.guestEmail;
                    this.phone = Quote.billingAddress() && Quote.billingAddress().telephone
                        ? Quote.billingAddress().telephone
                        : '';

                    if (!this.email || !this.phone) return false;

                    fullScreenLoader.startLoader();
                    storage.get(
                        UrlBuilder.createUrl('/guest-carts/:cartId/order-history/:email/:phone', {
                            cartId: Quote.getQuoteId(),
                            email: this.email,
                            phone: this.phone
                        })
                    ).done(function (response) {
                        fullScreenLoader.stopLoader();
                        tabbyModel.order_history = response;
                        tabbyModel.initCheckout();
                    }).fail(function () {
                        fullScreenLoader.stopLoader();
                        tabbyModel.order_history = null;
                    });

                    return false;
                },
                create: function (tabbyConfig) {
                    fullScreenLoader.startLoader();
                    window.TabbyCmsPlugins.createSession(tabbyConfig).then( (sess) => {
                        fullScreenLoader.stopLoader();
                        if (!sess.hasOwnProperty('status') || sess.status != 'created') {
                            tabbyModel.payment_id = null;
                            tabbyModel.products = [];
                            tabbyModel.enableButton();
                        } else {
                            tabbyModel.payment_id = sess.payment.id;
                            tabbyModel.products = sess.availableProducts;
                            tabbyModel.enableButton();
                        }
                    });
                },
                disableButton: function () {
                    for (var i in this.renderers) {
                        if (!this.renderers.hasOwnProperty(i)) continue;
                        this.renderers[i].disableButton();
                    }
                },
                enableButton: function () {
                    for (var i in this.renderers) {
                        if (!this.renderers.hasOwnProperty(i)) continue;
                        if (this.products && this.products.hasOwnProperty(i)) {
                            this.renderers[i].enableButton();
                            this.renderers[i].isRejected(false);
                        } else {
                            this.renderers[i].isRejected(true);
                        }
                    }
                },
                initUpdates: function () {
                    Quote.billingAddress.subscribe(this.checkoutUpdated);
                    Quote.shippingAddress.subscribe(this.checkoutUpdated);
                    Quote.shippingMethod.subscribe(this.checkoutUpdated);
                    var email = document.querySelector('#customer-email');
                    if (email) email.addEventListener('change', this.checkoutUpdated);
                    Quote.totals.subscribe(this.checkoutUpdated);
                    customerData.get('cart').subscribe(this.cartUpdated);
                },
                cartUpdated: function () {
                    quoteItemData.execute().success(function (data) {
                        window.checkoutConfig.quoteItemData = data;
                        tabbyModel.checkoutUpdated();
                    });
                },
                checkoutUpdated: function () {
                    if (tabbyModel.timeout_id) clearTimeout(tabbyModel.timeout_id);
                    tabbyModel.timeout_id = setTimeout(function () {
                        return tabbyModel.initCheckout();
                    }, 100);
                },
                getPaymentObject: function () {
                    var totals = (Quote.getTotals())();

                    return {
                        'amount': this.getTotalSegment(totals, 'grand_total'),
                        'currency': this.getTabbyCurrency(),
                        'description': window.checkoutConfig.quoteData.entity_id,
                        'buyer': this.getBuyerObject(),
                        'order': this.getOrderObject(),
                        'shipping_address': this.getShippingAddressObject(),
                        'order_history': this.getOrderHistoryObject(),
                        'buyer_history': this.getBuyerHistoryObject()
                    };
                },
                getTabbyCurrency: function () {
                    var currency = this.pricePrefix == ''
                        ? window.checkoutConfig.quoteData['quote_currency_code']
                        : window.checkoutConfig.quoteData['base_currency_code'];

                    return currency;
                },
                getGrandTotal: function () {
                    return this.getTotalSegment((Quote.getTotals())(), 'grand_total');
                },
                getBuyerObject: function () {
                    // buyer object
                    var buyer = {
                        'phone': '',
                        'email': '',
                        'name': '',
                        'dob': null
                    };
                    var address = Quote.billingAddress();
                    if (!address) {
                        //StepNavigator.navigateTo('shipping');
                        return buyer;
                    }
                    buyer.name = address.firstname + ' ' + address.lastname;
                    buyer.phone = address.telephone;
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

                getOrderObject: function () {
                    var totals = (Quote.getTotals())();

                    return {
                        'tax_amount': this.getTotalSegment(totals, 'tax_amount'),
                        'shipping_amount': this.getTotalSegment(totals, 'shipping_incl_tax'),
                        'discount_amount': this.getTotalSegment(totals, 'discount_amount'),
                        'items': this.getOrderItemsObject()
                    };
                },
                getShippingAddressObject: function () {
                    var address = Quote.billingAddress();

                    return {
                        'city': address && address.city ? address.city : '',
                        'address': address && address.hasOwnProperty('street') && address.street instanceof Array ? address.street.join(', ') : '',
                        'zip': address && address.postcode ? address.postcode : null
                    };
                },

                getTotalSegment: function (totals, name) {
                    if (name == 'grand_total' && this.pricePrefix == '') {
                        return this.formatPrice(parseFloat(totals[this.pricePrefix + name]) +
                            parseFloat(totals[this.pricePrefix + 'tax_amount']));
                    }
                    if (totals.hasOwnProperty(this.pricePrefix + name)) {
                        return this.formatPrice(totals[this.pricePrefix + name]);
                    }
                    return 0;
                },

                getOrderItemsObject: function () {
                    var items = Quote.getItems();
                    var itemsObject = [];
                    for (var i = 0; i < items.length; i++) {
                        var item_id = items[i].item_id;
                        itemsObject[i] = {
                            'title': items[i].name,
                            'quantity': items[i].qty,
                            'unit_price': this.getItemPrice(items[i]),
                            'tax_amount': this.getItemTax(items[i]),
                            'reference_id': items[i].sku,
                            'category': this.config.urls.hasOwnProperty(item_id)
                                ? this.config.urls[item_id].category
                                : null,
                            'image_url': this.config.urls.hasOwnProperty(item_id)
                                ? this.config.urls[item_id].image_url
                                : null,
                            'product_url': this.config.urls.hasOwnProperty(item_id)
                                ? this.config.urls[item_id].product_url
                                : null
                        };
                    }
                    return itemsObject;
                },
                formatPrice: function (price) {
                    var value = parseFloat(price);
                    return isNaN(value) ? 0 : value.toFixed(2);
                },
                getItemPrice: function (item) {
                    let price = 0;
                    price += parseFloat(item[this.pricePrefix + 'price']);
                    price -= parseFloat(item[this.pricePrefix + 'discount_amount']);
                    price += parseFloat(item[this.pricePrefix + 'tax_amount']);
                    return this.formatPrice(price);
                },
                getItemTax: function (item) {
                    return this.formatPrice(item[this.pricePrefix + 'tax_amount']);
                }
            };
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

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
        'Tabby_Checkout/js/action/get-session-data',
        'Tabby_Checkout/js/action/quote-item-data'
    ],
    function (
        Customer, customerData, checkoutData, Quote, UrlBuilder, StepNavigator, fullScreenLoader, additionalValidators,
        messageList, storage, getSessionData, quoteItemData) {
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
                    this.add_tax = window.checkoutConfig.payment.tabby_checkout.config.checkout_remove_tax ? false : true;
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

                    var payment = this.getPaymentObject();
                    if (!payment.buyer || !payment.buyer.name || payment.buyer.name == ' ') {
                        // no address, hide checkout.
                        return;
                    }
                    if (JSON.stringify(this.payment) == JSON.stringify(payment)) {
                        this.enableButton();
                        // objects same
                        return;
                    }
                    this.payment_id = null;
                    this.payment = payment;
                    this.initTabbyCard();
                    tabbyModel.products = null;

                    var tabbyConfig = {
                        'lang'          : this.config.lang.substring(0, 2),
                        'merchant_code' : this.config.storeGroupCode + ((this.pricePrefix == '') ? '_' + this.getTabbyCurrency() : ''),
                        'merchant_urls' : this.config.config.merchantUrls,
                        'payment'       : payment
                    };

                    this.create(tabbyConfig);
                },
                setProduct: function (product) {
                    this.product = product;
                },
                create: function (tabbyConfig) {
                    fullScreenLoader.startLoader();
                    getSessionData.execute(tabbyConfig).then( (data) => {
                        var result = data[0];
                        fullScreenLoader.stopLoader();
                        if (!result.hasOwnProperty('status') || result.status != 'created') {
                            tabbyModel.payment_id = null;
                            tabbyModel.products = [];
                            tabbyModel.enableButton();
                        } else {
                            tabbyModel.payment_id = result.payment_id;
                            tabbyModel.products = result.available_products;
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
                    // TODO: remove as no needed (session generated from backend with cart data)
                    // remove function below
                    //customerData.get('cart').subscribe(this.cartUpdated);
                },
/*
                cartUpdated: function () {
                    quoteItemData.execute().done(function (data) {
                        window.checkoutConfig.quoteItemData = data;
                        tabbyModel.checkoutUpdated();
                    });
                },
*/
                checkoutUpdated: function () {
                    if (tabbyModel.timeout_id) clearTimeout(tabbyModel.timeout_id);
                    tabbyModel.timeout_id = setTimeout(function () {
                        return tabbyModel.initCheckout();
                    }, 100);
                },
                getPaymentObject: function () {
                    //var totals = (Quote.getTotals())();

                    return {
                        //'amount': this.getTotalSegment(totals, 'grand_total'),
                        //'currency': this.getTabbyCurrency(),
                        //'description': window.checkoutConfig.quoteData.entity_id,
                        'buyer': this.getBuyerObject(),
                        //'order': this.getOrderObject(),
                        'shipping_address': this.getShippingAddressObject()
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
                        'name': ''
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
                    if (name == 'grand_total' && this.pricePrefix == '' && this.add_tax) {
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

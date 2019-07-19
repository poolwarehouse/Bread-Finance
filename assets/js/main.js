/*
Version:   1.0.0
Created:   July 11, 2019
*/
;(function ($, undefined) {

    "use strict";

    var breadController = mwp.controller('woocommerce-gateway-bread', {

        init: function () {

            switch (breadController.local.page_type) {
                case 'category':
                    this.breadCheckoutHandler = new CategoryHandler();
                    break;
                case 'product':
                    this.breadCheckoutHandler = new ProductHandler();
                    break;
                case 'cart_summary':
                    this.breadCheckoutHandler = new CartHandler();
                    break;
                case 'checkout':
                    this.breadCheckoutHandler = new CheckoutHandler();
                    break;
                default:
                    this.breadCheckoutHandler = new ProductHandler();
                    break;
            }

            breadController.viewModel = this.breadCheckoutHandler.getViewModel();

            this.breadCheckoutHandler.init();

            if (!bread.apiKey) bread.setAPIKey(breadController.local.bread_api_key);
        },

        debug: function ($err) {
            if (breadController.local.debug) {
                console.log($err);
            }
        }

    });

    $.extend(ko.bindingHandlers, {
        /**
         * The `bread` data binding attribute contains metadata and the immutable configuration/options for a button
         * instance.
         *
         *  {
         *      "productId": 99,
         *      "productType": "simple",
         *      "opts": {
         *          "buttonId": "bread_checkout_button_99",
         *          "buttonLocation": "product"
         *      }
         *  }
         */
        bread: {
            init: function (element, valueAccessor) {
                var el = $(element);
                var placeholder = el.html();

                element._reset = function () {
                    el.html(placeholder).removeAttr('data-loaded').css('visibility', 'visible');
                };

                $(document.body).trigger('bread_button_bind', [element, valueAccessor]);
            }
        }
    });

    var CategoryHandler = function () {
        this.$buttons = {};
        this.configs = {};
    };

    CategoryHandler.prototype.init = function () {

        var self = this;

        $(document.body).on('bread_button_bind', function (e, element, valueAccessor) {
            breadController.breadCheckoutHandler.onButtonBind(e, element, valueAccessor);
        });

        $('div.bread-checkout-button').each(function () {
            if (self.$buttons[this.id] === undefined) {
                self.$buttons[this.id] = $(this);
            }
        });

    };

    CategoryHandler.prototype.getViewModel = function () {
        return {};
    };

    CategoryHandler.prototype.onButtonBind = function (e, element, valueAccessor) {
        var config = ko.unwrap(valueAccessor());

        this.configs[config.opts.buttonId] = {config: config, loaded: false};

        // Avoid excessive ajax requests by fetching button options only after all buttons have been bound.
        if (Object.keys(this.configs).length === Object.keys(this.$buttons).length) {
            this.renderButtons();
        }

    };

    CategoryHandler.prototype.renderButtons = function () {

        var configs = [],
            self = this;

        /*
         * Ensure we only render the button once per item by setting a `loaded` property. This is needed
         * to support infinite-scrolling on category pages.
         */
        Object.keys(this.configs).forEach(function (key) {
            if (!self.configs[key].loaded) {
                configs[key] = self.configs[key].config;
                self.configs[key].config.loaded = true;
            }
        });

        $.post(breadController.local.ajaxurl, {
            action: 'bread_get_options',
            source: breadController.local.page_type,
            configs: Object.values(configs)
        })
            .done(function (response) {
                var self = breadController.breadCheckoutHandler;

                if (!response.success) {
                    return breadController.debug(response.data);
                }

                response.data.forEach(function (opts) {
                    if (opts.healthcareMode) {
                        ['billingContact', 'shippingContact', 'items', 'discounts', 'shippingOptions'].forEach(function(el) {
                            delete opts[el];
                          });
                        opts.allowCheckout = false;
                    }

                    var _opts = Object.assign(
                        opts,
                        configs[opts.buttonId].opts,
                        self.getBreadCallbacks()
                    );

                    bread.checkout(_opts);

                });

            })
            .fail(function (xhr, status) {
                breadController.debug(status);
            });

    };

    CategoryHandler.prototype.resetButton = function () {
        if (this.$buttons[this.opts.buttonId].attr('data-loaded')) {
            this.$buttons[this.opts.buttonId][0]._reset();
        }
    };

    CategoryHandler.prototype.fail = function (xhr, error) {
        this.resetButton();
        breadController.debug(error);
    };

    /**
     * Define the Bread checkout callback functions for category pages.
     */
    CategoryHandler.prototype.getBreadCallbacks = function () {
        var self = this;
        return {
            onCustomerOpen: function (err, data, callback) {
                self.opts = data.opts;
                callback(data);
            },
            onCustomerClose: function (err, custData) {
                delete self.opts;
                if (!err) {
                    $.post(breadController.local.ajaxurl, {
                        action: 'bread_set_qualstate',
                        customer_data: custData
                    })
                }
            },
            calculateTax: function (shippingContact, billingContact, callback) {
                $.post(breadController.local.ajaxurl, {
                    action: 'bread_calculate_tax',
                    button_opts: {items: breadController.breadCheckoutHandler.opts.items},
                    shipping_contact: shippingContact,
                    billing_contact: billingContact
                })
                    .done(function (response) {
                        if (response.success) {
                            callback(null, response.data.tax);
                        } else {
                            callback(response.data);
                            self.fail(null, response.data);
                        }
                    })
                    .fail(function (xhr, status) {
                        callback(status);
                        self.fail(xhr, status);
                    });
            },
            calculateShipping: function (shippingContact, callback) {
                $.post(breadController.local.ajaxurl, {
                    action: 'bread_calculate_shipping',
                    button_opts: {items: breadController.breadCheckoutHandler.opts.items},
                    shipping_contact: shippingContact
                })
                    .done(function (response) {
                        if (response.success) {
                            callback(null, response.data.shippingOptions);
                        } else {
                            callback(response.data);
                            self.fail(null, response.data);
                        }
                    })
                    .fail(function (xhr, status) {
                        callback(status);
                        self.fail(xhr, status);
                    });
            },
            done: function (err, txToken) {
                if (err) {
                    self.fail(null, err);
                    return window.alert("Error completing checkout.");
                }

                $.post(breadController.local.ajaxurl, {
                    action: 'bread_complete_checkout',
                    tx_id: txToken
                }).done(function (response) {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        self.fail(null, response.data);
                        window.alert("Error completing checkout.");
                    }
                }).fail(function (xhr, status) {
                    self.fail(xhr, status);
                    window.alert("Error completing checkout.");
                });

            }
        };
    };

    var ProductHandler = function () {
        this.$form = $('form.cart');
        this.$button = $('div.bread-checkout-button');
        this.config = {};   // placeholder for button config. populated on bind.
    };

    ProductHandler.prototype.init = function () {

        $(document.body).on('bread_button_bind', function (e, element, valueAccessor) {
            breadController.breadCheckoutHandler.onButtonBind(e, element, valueAccessor);
        });

        $(document).ready(function () {
            breadController.breadCheckoutHandler.$form.on('change', function (event) {
                breadController.breadCheckoutHandler.onFormChange(event);
            });
        });

        // Variable Products Only: Setup variable product event bindings.
        if ($('form.variations_form').length > 0) {
            this.setupBindingsVariable();
        }

        // Composite Products Only: Setup composite product event bindings.
        if ($('.composite_data').length > 0) {
            this.setupBindingsComposite();
        }

    };

    ProductHandler.prototype.getViewModel = function () {
        return {};
    };

    /*
     * When knockout.js binds to the button element, trigger the button setup/rendering function for
     * the current product-type. This will also be called when certain form values change that require
     * an update to the Bread button options.
     *
     * simple products: Render the button immediately.
     * composite products: The button can't be rendered until valid component selections have been made.
     *                     Wire-up the event handlers for `component_selection_changed`.
     */
    ProductHandler.prototype.onButtonBind = function (e, element, valueAccessor) {

        this.config = ko.unwrap(valueAccessor());
        this.toggleButton();

    };

    /*
     * Update the Bread button options in response to changes on `form.cart`.
     */
    ProductHandler.prototype.onFormChange = function (event) {

        if (this.timeout) window.clearTimeout(this.timeout);

        this.timeout = window.setTimeout(function () {
            breadController.breadCheckoutHandler.updateButton();
        }, 1000);

    };

    ProductHandler.prototype.setupBindingsVariable = function () {
        var self = this;
        this.$form.on('show_variation', function (variation) {
            self.variation = variation;
            self.toggleButton();
        });

        this.$form.on('reset_data', function () {
            delete self.variation;
            self.toggleButton();
        });
    };

    /**
     * Hook `component_selection_changed` action/event of a composite product and render the Bread
     * checkout button only when a valid configuration has been selected.
     */
    ProductHandler.prototype.setupBindingsComposite = function () {
        $(document).on('wc-composite-initializing', '.composite_data', function (event, composite) {
            breadController.breadCheckoutHandler.composite = composite;

            composite.actions.add_action('component_selection_changed', function () {
                this.toggleButton();
            }, 100, breadController.breadCheckoutHandler);
        });
    };

    ProductHandler.prototype.validateSelections = function () {

        var self = this,
            validators = {
                simple: function () {
                    return true;
                },

                grouped: function () {
                    return self.$form.find('input.qty').filter(function (index, element) {
                        return parseInt(element.value) > 0;
                    }).length > 0;
                },

                variable: function () {
                    return self.variation !== undefined;
                },

                composite: function () {
                    return (self.composite && self.composite.api.get_composite_validation_status() === 'pass');
                }
            };

        this.isValid = validators[breadController.local.product_type]();

        return this.isValid;

    };

    ProductHandler.prototype.getPostData = function (breadAction, shippingContact, billingContact) {
        var data = this.$form.serializeObject();

        data['add-to-cart'] = this.$form[0]['add-to-cart'].value;
        data['action'] = breadAction;
        data['config'] = this.config;
        data['source'] = breadController.local.page_type;

        if (shippingContact !== null) {
            data['shipping_contact'] = shippingContact;
        }

        if (billingContact !== null) {
            data['billing_contact'] = billingContact;
        }

        return data;
    };

    ProductHandler.prototype.renderButton = function () {
        var config = this.config,
            url = this.$form.attr('action') || window.location.href;

        $.post(url, this.getPostData('bread_get_options'))
            .done(function (response) {
                var self = breadController.breadCheckoutHandler,
                    opts = Object.assign(response.data, config.opts, self.getBreadCallbacks());

                if (response.success) {
                    if (response.data.healthcareMode) {
                        ['billingContact', 'shippingContact', 'items', 'discounts', 'shippingOptions'].forEach(function(el) {
                            delete opts[el];
                          });
                        opts.allowCheckout = false;
                    }
                    bread.checkout(opts);
                } else {
                    self.fail(null, response.data);
                }
            })
            .fail(self.fail);
    };

    ProductHandler.prototype.toggleButton = function () {

        if (!this.validateSelections()) {
            return this.resetButton();
        }

        if (this.config.buttonType === 'composite' || this.config.buttonType === 'variable') {
            var iframe = this.$button.find('div > iframe');
            if (iframe.length > 0 && !iframe.parent().is(':visible')) {
                iframe.show();
            }
        }

        this.renderButton();

    };

    /**
     * Unbind/Rebind the bread button to trigger an update of the Bread button options.
     */
    ProductHandler.prototype.updateButton = function () {
        ko.cleanNode(this.$button[0]);
        ko.applyBindings(breadController.viewModel, this.$button[0]);
    };

    ProductHandler.prototype.resetButton = function () {
        if (this.$button.attr('data-loaded')) {
            this.$button[0]._reset();
        }
    };

    ProductHandler.prototype.fail = function (xhr, error) {
        this.resetButton();
        breadController.debug(error);
    };

    /**
     * Define the Bread checkout callback functions for product pages.
     */
    ProductHandler.prototype.getBreadCallbacks = function () {
        var self = this;
        return {
            onCustomerOpen: function (err, data, callback) {
                self.opts = data.opts;
                callback(data);
            },
            onCustomerClose: function (err, custData) {
                delete self.opts;
                if (!err) {
                    $.post(breadController.local.ajaxurl, {
                        action: 'bread_set_qualstate',
                        customer_data: custData
                    })
                }
            },
            calculateTax: function (shippingContact, billingContact, callback) {
                var url = self.$form.attr('action') || window.location.href;

                $.post(url, self.getPostData('bread_calculate_tax', shippingContact, billingContact))
                    .done(function (response) {
                        if (response.success) {
                            callback(null, response.data.tax);
                        } else {
                            callback(response.data);
                            self.fail(null, response.data);
                        }
                    })
                    .fail(function (xhr, status) {
                        callback(status);
                        self.fail(xhr, status);
                    });


            },
            calculateShipping: function (shippingContact, callback) {
                var url = self.$form.attr('action') || window.location.href;

                $.post(url, self.getPostData('bread_calculate_shipping', shippingContact))
                    .done(function (response) {
                        if (response.success) {
                            callback(null, response.data.shippingOptions);
                        } else {
                            callback(response.data);
                            self.fail(null, response.data);
                        }
                    })
                    .fail(function (xhr, status) {
                        callback(status);
                        self.fail(xhr, status);
                    });
            },
            done: function (err, txToken) {
                if (err) {
                    self.fail(null, err);
                    return window.alert("Error completing checkout.");
                }

                $.post(breadController.local.ajaxurl, {
                    action: 'bread_complete_checkout',
                    tx_id: txToken,
                    form: breadController.breadCheckoutHandler.$form.serializeArray()
                }).done(function (response) {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        self.fail(null, response.data);
                        window.alert("Error completing checkout.");
                    }
                }).fail(function (xhr, status) {
                    self.fail(xhr, status);
                    window.alert("Error completing checkout.");
                });

            }
        };
    };


    var CartHandler = function () {
        this.$form = $('form.woocommerce-cart-form');
        this.$button = $('div.bread-checkout-button');
    };

    CartHandler.prototype.init = function () {

        var self = this;

        $(document.body).on('bread_button_bind', function (e, element, valueAccessor) {
            breadController.breadCheckoutHandler.onButtonBind(e, element, valueAccessor);
        });

        this.$form.on('change', function (event) {
            breadController.breadCheckoutHandler.onFormChange(event);
        });

        $(document.body).on('updated_wc_div', function (event) {
            breadController.breadCheckoutHandler.updateButton();
        });

        $(document.body).on('updated_shipping_method', function (event) {
            this.$button = $('div.bread-checkout-button');
            breadController.breadCheckoutHandler.updateButton();
        });

    };

    CartHandler.prototype.getViewModel = function () {
        return {};
    };

    CartHandler.prototype.onButtonBind = function (e, element, valueAccessor) {
        this.config = ko.unwrap(valueAccessor());
        this.renderButton();
    };

    CartHandler.prototype.onFormChange = function (event) {

        if (this.timeout) window.clearTimeout(this.timeout);

        if ($(event.target).hasClass('qty')) {
            this.timeout = window.setTimeout(function () {
                breadController.breadCheckoutHandler.updateButton();
            }, 100);
        }

    };

    CartHandler.prototype.renderButton = function () {
        var self = breadController.breadCheckoutHandler,
            config = this.config;

        $.post(breadController.local.ajaxurl, {
            action: 'bread_get_options',
            source: 'cart_summary',
            config: config,
            form: this.$form.serializeArray()
        })
            .done(function (response) {
                var opts = Object.assign(response.data, config.opts, self.getBreadCallbacks());

                if (response.success) {

                    if ( response.data.healthcareMode ) {
                        ['billingContact', 'shippingContact', 'items', 'discounts', 'shippingOptions'].forEach(function(el) {
                            delete opts[el];
                          });
                        opts.allowCheckout = false;
                    }
                    
                    bread.checkout(opts);
                } else {
                    self.fail(null, response.data);
                }
            })
            .fail(self.fail);
    };

    CartHandler.prototype.updateButton = function () {
        ko.cleanNode(this.$button[0]);
        ko.applyBindings(breadController.viewModel, this.$button[0]);
    };

    CartHandler.prototype.resetButton = function () {
        if (this.$button.attr('data-loaded')) {
            this.$button[0]._reset();
        }
    };

    CartHandler.prototype.fail = function (xhr, error) {
        this.resetButton();
        breadController.debug(error);
    };

    CartHandler.prototype.getBreadCallbacks = function () {
        var self = this;
        return {
            calculateTax: function (shippingContact, billingContact, callback) {
                $.post(breadController.local.ajaxurl, {
                    action: 'bread_calculate_tax',
                    source: breadController.local.page_type,
                    shipping_contact: shippingContact,
                    billing_contact: billingContact
                })
                    .done(function (response) {
                        if (response.success) {
                            callback(null, response.data.tax);
                        } else {
                            callback(response.data);
                            self.fail(null, response.data);
                        }
                    })
                    .fail(function (xhr, status) {
                        callback(status);
                        self.fail(xhr, status);
                    });
            },
            calculateShipping: function (shippingContact, callback) {
                $.post(breadController.local.ajaxurl, {
                    action: 'bread_calculate_shipping',
                    source: breadController.local.page_type,
                    shipping_contact: shippingContact
                })
                    .done(function (response) {
                        if (response.success) {
                            callback(null, response.data.shippingOptions)
                        } else {
                            callback(response.data);
                            self.fail(null, response.data);
                        }
                    })
                    .fail(function (xhr, status) {
                        callback(status);
                        self.fail(xhr, status);
                    });
            },
            done: function (err, txToken) {
                if (err) {
                    self.fail(null, err);
                    return window.alert("Error completing checkout.");
                }

                $.post(breadController.local.ajaxurl, {
                    action: 'bread_complete_checkout',
                    tx_id: txToken
                }).done(function (response) {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        self.fail(null, response.data);
                        window.alert("Error completing checkout.");
                    }
                }).fail(function (xhr, status) {
                    self.fail(xhr, status);
                    window.alert("Error completing checkout.");
                });

            }
        }
    };

    var CheckoutHandler = function () {
        this.$form = $('form.checkout');
    };

    CheckoutHandler.prototype.init = function () {

        var self = this;

        this.$form.on('checkout_place_order_' + breadController.local.gateway_token, function () {
            /*  If the hidden input `bread_tx_token` exists, checkout has been completed and the form should be submitted */
            if (self.$form.find('input[name="bread_tx_token"]').length > 0) {
                return true;
            }

            self.doBreadCheckout();
            return false;
        });

    };

    CheckoutHandler.prototype.getViewModel = function () {
        return {};
    };

    CheckoutHandler.prototype.doBreadCheckout = function () {

        /*
         * Borrowed from plugins/woocommerce/assets/js/frontend/checkout.js->submit()
         */
        this.$form.addClass('processing').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        var self = this,
            formIsValid = false,
            breadOpts = null;

        /*
         * Checkout form validation & Bread options ajax call must run synchronously in order for the
         * call to bread.showCheckout to happen in the context of the original button-click event.
         * Otherwise the Bread dialog will be treated as a pop-up and blocked by some browsers.
         */
        $.ajax({
                type: 'POST',
                url: wc_checkout_params.checkout_url + '&bread_validate=true',
                data: this.$form.serialize(),
                dataType: 'json',
                async: false,
                success: function (result) {
                    if (result.result === 'success') {
                        formIsValid = true;
                    } else {
                        self.$form.removeClass('processing').unblock();
                        self.wc_submit_error(result.messages);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    self.$form.removeClass('processing').unblock();
                    self.wc_submit_error('<div class="woocommerce-error">' + errorThrown + '</div>');
                }
            }
        );

        if (formIsValid) {
            var data = {
                action: 'bread_get_options',
                source: 'checkout'
            };

            self.$form.serializeArray().forEach(function (item) {
                data[item.name] = item.value;
            });

            $.ajax({
                type: 'POST',
                url: breadController.local.ajaxurl,
                data: data,
                async: false,
                success: function (result) {
                    if (result.data.error) {
                        window.alert("Error completing checkout. " + result.data.error);
                    } else if (result.success) {
                        breadOpts = Object.assign(result.data, self.getBreadCallbacks());
                    } else {
                        breadController.debug(result.data);
                        window.alert("Error completing checkout.");
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    breadController.debug(textStatus);
                    window.alert("Error completing checkout.");
                }
            });
        }

        if (breadOpts !== null) {
            if (breadOpts.healthcareMode) {
                ['billingContact', 'shippingContact', 'items', 'discounts', 'shippingOptions', 'tax'].forEach(function(el) {
                    delete breadOpts[el];
                  });
            }
            bread.showCheckout(breadOpts);
        }

    };

    CheckoutHandler.prototype.getBreadCallbacks = function () {
        var self = this;
        return {
            onCustomerClose: function (err, custData) {
                self.$form.removeClass('processing').unblock();
            },
            done: function (err, tx_token) {
                var $tokenField = self.$form.find('input[name="bread_tx_token"]');

                self.$form.removeClass('processing').unblock();

                if (err) {
                    return self.$form.remove('input[name="bread_tx_token"]');
                }

                if ($tokenField.length > 0) {
                    $tokenField.val(token);
                } else {
                    self.$form.append(
                        $('<input />').attr('name', 'bread_tx_token').attr('type', 'hidden').val(tx_token)
                    );
                }
                self.$form.submit();
            }
        };
    };

    CheckoutHandler.prototype.wc_submit_error = function (error_message) {
        $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
        this.$form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>');
        this.$form.removeClass('processing').unblock();
        this.$form.find('.input-text, select, input:checkbox').trigger('validate').blur();
        this.wc_scroll_to_notices();
        $(document.body).trigger('checkout_error');
    };

    CheckoutHandler.prototype.wc_scroll_to_notices = function () {
        var scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout'),
            isSmoothScrollSupported = 'scrollBehavior' in document.documentElement.style;

        if (!scrollElement.length) {
            scrollElement = $('.form.checkout');
        }

        if (scrollElement.length) {
            if (isSmoothScrollSupported) {
                scrollElement[0].scrollIntoView({
                    behavior: 'smooth'
                });
            } else {
                $('html, body').animate({
                    scrollTop: (scrollElement.offset().top - 100)
                }, 1000);
            }
        }
    }

})(jQuery);
/**
 * Banorte Payment Gateway JS - v1.7 Compliance
 * Handles credit card validation and payment processing
 */

(function($) {
    $(function() {
        // Configuration from PHP
        const banorteConfig = banorte_params || {};
        const $form = $('#banorte-payment-form');
        const $errors = $('#banorte-errors');
        const $submitBtn = $('#banorte-submit');
        
        // Initialize card visualization if enabled
        if ($('#card-element').length > 0) {
            new Card({
                form: document.querySelector('#banorte-payment-form'),
                container: '.card-wrapper',
                formSelectors: {
                    numberInput: 'input[name="card_number"]',
                    expiryInput: 'input[name="card_expiry"]',
                    cvcInput: 'input[name="card_cvc"]',
                    nameInput: 'input[name="card_name"]'
                }
            });
        }

        // Validate card before submission
        $submitBtn.on('click', function(e) {
            e.preventDefault();
            $errors.hide().empty();
            
            // Validate card fields
            if (!validateCardFields()) {
                return false;
            }

            // Process payment through Banorte lightbox
            processPayment();
        });

        /**
         * Validate card fields
         */
        function validateCardFields() {
            // Basic validation - extend as needed
            const $cardNumber = $('input[name="card_number"]');
            const $cardExpiry = $('input[name="card_expiry"]');
            const $cardCvc = $('input[name="card_cvc"]');
            
            // Validate card number
            if (!$cardNumber.val() || !valid_credit_card($cardNumber.val())) {
                showError(banorteConfig.i18n.invalid_card);
                $cardNumber.focus();
                return false;
            }
            
            // Validate expiry date
            if (!$cardExpiry.val() || !validateExpiry($cardExpiry.val())) {
                showError(banorteConfig.i18n.invalid_expiry);
                $cardExpiry.focus();
                return false;
            }
            
            // Validate CVC
            if (!$cardCvc.val() || $cardCvc.val().length < 3) {
                showError(banorteConfig.i18n.invalid_cvc);
                $cardCvc.focus();
                return false;
            }
            
            return true;
        }

        /**
         * Process payment through Banorte lightbox
         */
        function processPayment() {
            // Show loading state
            $form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            // Initialize Banorte payment
            if (typeof Payment !== 'undefined') {
                Payment.setEnv(banorteConfig.environment === 'PRD' ? 'pro' : 'test');
                
                // Format expiry date (MMYY)
                const expiry = $('input[name="card_expiry"]').val().replace(/\s/g, '').split('/');
                const formattedExpiry = (expiry[0] || '').padStart(2, '0') + 
                                      (expiry[1] ? expiry[1].slice(-2) : '');

                // Prepare payment data
                const paymentData = {
                    cardNumber: $('input[name="card_number"]').val().replace(/\s/g, ''),
                    cardExpiry: formattedExpiry,
                    cardCvc: $('input[name="card_cvc"]').val(),
                    cardName: $('input[name="card_name"]').val()
                };

                // Start payment process
                Payment.startPayment({
                    Params: banorteConfig.encrypted_data,
                    onSuccess: function(response) {
                        // Handle successful payment
                        window.location.href = banorteConfig.redirect_url;
                    },
                    onError: function(response) {
                        // Handle errors
                        $form.unblock();
                        showError(response.message || banorteConfig.i18n.payment_error);
                    },
                    onCancel: function() {
                        // Handle cancellation
                        $form.unblock();
                        showError(banorteConfig.i18n.payment_cancelled);
                    },
                    onClosed: function() {
                        // Handle modal close
                        $form.unblock();
                    }
                });
            } else {
                $form.unblock();
                showError(banorteConfig.i18n.script_error);
            }
        }

        /**
         * Validate credit card using Luhn algorithm
         */
        function valid_credit_card(value) {
            if (/[^0-9-\s]+/.test(value)) return false;
            
            let nCheck = 0, nDigit = 0, bEven = false;
            value = value.replace(/\D/g, "");
            
            if (value.length < 13 || value.length > 19) return false;
            
            for (let n = value.length - 1; n >= 0; n--) {
                const cDigit = value.charAt(n);
                nDigit = parseInt(cDigit, 10);
                if (bEven) {
                    if ((nDigit *= 2) > 9) nDigit -= 9;
                }
                nCheck += nDigit;
                bEven = !bEven;
            }
            
            return (nCheck % 10) === 0;
        }

        /**
         * Validate expiry date (MM/YY format)
         */
        function validateExpiry(value) {
            const parts = value.split('/');
            if (parts.length !== 2) return false;
            
            const month = parseInt(parts[0], 10);
            const year = parseInt(parts[1], 10);
            const currentYear = new Date().getFullYear() % 100;
            const currentMonth = new Date().getMonth() + 1;
            
            if (month < 1 || month > 12) return false;
            if (year < currentYear || year > currentYear + 20) return false;
            if (year === currentYear && month < currentMonth) return false;
            
            return true;
        }

        /**
         * Display error messages
         */
        function showError(message) {
            $errors.text(message).show();
            $('html, body').animate({
                scrollTop: $errors.offset().top - 100
            }, 300);
        }

        // Track card type changes
        $('input[name="card_number"]').on('keyup change', function() {
            const cardNumber = $(this).val().replace(/\s/g, '');
            const cardType = getCardType(cardNumber);
            
            // Validate allowed card types
            if (cardNumber.length > 4) {
                const allowedTypes = ['visa', 'mastercard', 'amex'];
                if (cardType && !allowedTypes.includes(cardType)) {
                    showError(banorteConfig.i18n.card_type_error);
                } else {
                    $errors.hide();
                }
            }
        });

        /**
         * Detect card type based on number
         */
        function getCardType(number) {
            const re = {
                visa: /^4/,
                mastercard: /^5[1-5]/,
                amex: /^3[47]/
            };
            
            for (const type in re) {
                if (re[type].test(number)) {
                    return type;
                }
            }
            return null;
        }
    });
})(jQuery);
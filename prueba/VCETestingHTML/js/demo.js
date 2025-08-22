

    window.addEventListener("DOMContentLoaded", () => {
        const applePayButton = document.querySelector("#live-button");
        if (!window.PaymentRequest || !window.ApplePaySession || !ApplePaySession.canMakePayments())
            applePayButton.classList.add("apple-pay-not-supported");
        else {
            applePayButton.classList.add("apple-pay-button");
            applePayButton.addEventListener("click", applePayButtonClicked);   
        }
        applePayButton.classList.remove("hidden");
    });


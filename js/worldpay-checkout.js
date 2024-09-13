jQuery(document).ready(function($) {
    // Function to check form validity
    function checkFormValidity() {
        const cardNumber = $('#worldpay-card-number').val().replace(/\D/g, '');
        const expiry = $('#worldpay-card-expiry').val();
        const cvc = $('#worldpay-card-cvc').val().replace(/\D/g, '');

        // Check if card number, expiry date, and CVC are valid
        const isCardNumberValid = cardNumber.length >= 13 && cardNumber.length <= 19;
        const isExpiryValid = /^\s*(0[1-9]|1[0-2])\s*\/\s*([0-9]{2})\s*$/.test(expiry);
        const isCvcValid = /^[0-9]{3,4}$/.test(cvc);
    }

    // Function to show alert if form is incomplete
    function validateBeforeSubmit() {
        const cardNumber = $('#worldpay-card-number').val().replace(/\D/g, '');
        const expiry = $('#worldpay-card-expiry').val();
        const cvc = $('#worldpay-card-cvc').val().replace(/\D/g, '');

        if (!cardNumber || !expiry || !cvc) {
            alert('Please fill in all required card details.');
            return false; // Prevent form submission
        }
        return true; // Allow form submission
    }

    // Function to show error message
    function showError(message) {
        // You can customize this to show errors in your preferred way
        $('.woocommerce-error').remove(); // Remove any existing errors
        $('<div class="woocommerce-error">' + message + '</div>').insertAfter('.woocommerce-billing-fields');
    }

    // Function to remove error message
    function removeError() {
        $('.woocommerce-error').remove();
    }

        // Debounce function to limit the rate of formatting application
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    
        function formatCardNumber(cardNumber) {
            return cardNumber
                .replace(/\D/g, '')  // Remove non-numeric characters
                .replace(/(.{4})/g, '$1 ')  // Add space every 4 digits
                .trim();  // Remove trailing spaces
        }
    
        function updateCardNumberField() {
            let rawCardNumber = $('#worldpay-card-number').val().replace(/\D/g, '');
            let formattedCardNumber = formatCardNumber(rawCardNumber);
            $('#worldpay-card-number').val(formattedCardNumber);
        }
    
        // Handle multiple events with debouncing
        $(document).on('input keyup change blur', '#worldpay-card-number', debounce(function() {
            updateCardNumberField();
    
            let rawCardNumber = $(this).val().replace(/\D/g, '');
            if (rawCardNumber.length < 13 || rawCardNumber.length > 19) {
                $(this).addClass('woocommerce-invalid').removeClass('woocommerce-validated');
                showError('Card number is invalid');
            } else {
                $(this).addClass('woocommerce-validated').removeClass('woocommerce-invalid');
                removeError();
            }
            checkFormValidity(); // Call your form validity check function
        }, 300)); // Debounce time (in milliseconds)
    
        // Function to display error messages
        function showError(message) {
            $('.woocommerce-error').remove(); // Clear any existing error messages
            $('<div class="woocommerce-error">' + message + '</div>').insertAfter('#worldpay-card-number');
        }
    
        // Function to remove error messages
        function removeError() {
            $('.woocommerce-error').remove();
        }
    
        // Example placeholder for checkFormValidity function
        function checkFormValidity() {
            // Implement your form validity logic here
        }
    

    // Automatically format and validate expiry date (MM/YY)
    $(document).on('input', '#worldpay-card-expiry', function(){
        let expiry = $(this).val().replace(/\D/g, ''); // Remove non-numeric characters

        if (expiry.length >= 2) {
            expiry = expiry.substring(0, 2) + ' / ' + expiry.substring(2); // Insert slash after month
        }
        if (expiry.length > 5) {
            expiry = expiry.substring(0, 7); // Limit to MM/YY
        }

        $(this).val(expiry); // Set the formatted expiry date

        const regex = /^(0[1-9]|1[0-2])\/([0-9]{2})$/; // Match MM/YY format

        // const [month, year] = expiry.split('/');
        // const expMonth = parseInt(month, 10);
        // const expYear = parseInt('20' + year, 10);
        // const currentDate = new Date();
        // const currentMonth = currentDate.getMonth() + 1; // Months are zero-based
        // const currentYear = currentDate.getFullYear();

        // if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
        //     $(this).addClass('woocommerce-invalid').removeClass('woocommerce-validated');
        //     showError('Expiry date is expired');
        // } else {
        //     $(this).addClass('woocommerce-validated').removeClass('woocommerce-invalid');
        //     removeError();
        // }
        checkFormValidity(); // Check form validity after updating
    });

    // Validate CVC
    $(document).on('input', '#worldpay-card-cvc', function() {
        const cvc = $(this).val().replace(/\D/g, ''); // Only allow numeric input
        $(this).val(cvc); // Update field with numeric-only value

        const regex = /^[0-9]{3,4}$/; // Match 3 or 4 digits

        if (!regex.test(cvc) || cvc === '') {
            $(this).addClass('woocommerce-invalid').removeClass('woocommerce-validated');
            showError('CVC is invalid');
        } else {
            $(this).addClass('woocommerce-validated').removeClass('woocommerce-invalid');
            removeError();
        }
        checkFormValidity(); // Check form validity after updating
    });
});
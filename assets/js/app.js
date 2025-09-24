// AJAX Transaction Processing
document.addEventListener('DOMContentLoaded', function() {
    // Get form elements
    const depositForm = document.getElementById('depositForm');
    const withdrawForm = document.getElementById('withdrawForm');
    const messageArea = document.getElementById('messageArea');
    const messageContent = document.getElementById('messageContent');
    const balanceElement = document.getElementById('currentBalance');

    // Add event listeners for forms
    if (depositForm) {
        depositForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = document.getElementById('deposit_amount').value;
            processTransaction('deposit', amount);
        });
    }

    if (withdrawForm) {
        withdrawForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = document.getElementById('withdraw_amount').value;
            processTransaction('withdraw', amount);
        });
    }

    /**
     * Process transaction via AJAX
     * @param {string} type - Transaction type (deposit/withdraw)
     * @param {string} amount - Transaction amount
     */
    function processTransaction(type, amount) {
        // Validate input
        if (!amount || parseFloat(amount) <= 0) {
            showMessage('Please enter a valid amount greater than 0', 'error');
            return;
        }

        // Show loading state
        showMessage('Processing transaction...', 'loading');
        
        // Disable form buttons during processing
        const buttons = document.querySelectorAll('.quick-transaction-forms button');
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });

        // Prepare data for API
        const transactionData = {
            transaction_type: type,
            amount: parseFloat(amount)
        };

        // Make AJAX request
        fetch('api/process_transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(transactionData)
        })
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Transaction successful
                showMessage(data.message, 'success');
                
                // Update balance display
                updateBalance(data.data.formatted_new_balance);
                
                // Clear form
                document.getElementById(type + '_amount').value = '';
                
                // Update withdraw form max value if it was a deposit
                if (type === 'deposit') {
                    const withdrawInput = document.getElementById('withdraw_amount');
                    if (withdrawInput) {
                        withdrawInput.setAttribute('max', data.data.new_balance);
                    }
                }
                
                // Refresh recent activities after a delay
                setTimeout(() => {
                    refreshRecentActivities();
                }, 1000);
                
            } else {
                // Transaction failed
                showMessage(data.message || 'Transaction failed', 'error');
            }
        })
        .catch(error => {
            console.error('Transaction error:', error);
            showMessage('Network error. Please check your connection and try again.', 'error');
        })
        .finally(() => {
            // Re-enable form buttons
            resetFormButtons();
        });
    }

    /**
     * Show message to user
     * @param {string} message - Message text
     * @param {string} type - Message type (success/error/loading)
     */
    function showMessage(message, type) {
        if (messageArea && messageContent) {
            messageContent.textContent = message;
            
            // Remove existing Bootstrap alert classes
            messageArea.className = 'alert alert-dismissible fade show';
            
            // Add appropriate Bootstrap alert class
            switch(type) {
                case 'success':
                    messageArea.classList.add('alert-success');
                    break;
                case 'error':
                    messageArea.classList.add('alert-danger');
                    break;
                case 'loading':
                    messageArea.classList.add('alert-info');
                    break;
                default:
                    messageArea.classList.add('alert-secondary');
            }
            
            // Remove d-none class to show the alert
            messageArea.classList.remove('d-none');
            
            // Auto-hide success and error messages after 5 seconds
            if (type !== 'loading') {
                setTimeout(() => {
                    hideMessage();
                }, 5000);
            }
        }
    }

    /**
     * Hide message area
     */
    function hideMessage() {
        if (messageArea) {
            messageArea.classList.add('d-none');
        }
    }

    /**
     * Update balance display
     * @param {string} newBalance - Formatted balance string
     */
    function updateBalance(newBalance) {
        if (balanceElement) {
            // Add animation class
            balanceElement.classList.add('balance-updating');
            
            // Update balance text with animation
            setTimeout(() => {
                balanceElement.textContent = '฿' + newBalance;
                balanceElement.classList.remove('balance-updating');
                balanceElement.classList.add('balance-updated');
                
                // Remove animation class after animation completes
                setTimeout(() => {
                    balanceElement.classList.remove('balance-updated');
                }, 500);
            }, 200);
        }
    }

    /**
     * Reset form buttons to original state
     */
    function resetFormButtons() {
        const depositBtn = depositForm ? depositForm.querySelector('button') : null;
        const withdrawBtn = withdrawForm ? withdrawForm.querySelector('button') : null;
        
        if (depositBtn) {
            depositBtn.disabled = false;
            depositBtn.innerHTML = '<i class="fas fa-plus"></i> Deposit';
        }
        
        if (withdrawBtn) {
            withdrawBtn.disabled = false;
            withdrawBtn.innerHTML = '<i class="fas fa-minus"></i> Withdraw';
        }
    }

    /**
     * Refresh recent activities (optional enhancement)
     */
    function refreshRecentActivities() {
        // This could be enhanced to dynamically update the recent activities section
        // For now, we'll just reload the page after successful transactions to show updated activities
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    }

    // Add click handler to close messages
    if (messageArea) {
        messageArea.addEventListener('click', function() {
            hideMessage();
        });
    }

    // Form validation enhancements
    const amountInputs = document.querySelectorAll('input[type="number"]');
    amountInputs.forEach(input => {
        // Format input on blur
        input.addEventListener('blur', function() {
            if (this.value) {
                const value = parseFloat(this.value);
                this.value = value.toFixed(2);
            }
        });

        // Prevent negative values
        input.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = '';
            }
        });
    });
});
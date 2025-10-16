jQuery(document).ready(function($){
    const $fab = $('#mwai-fab');
    const $chat = $('#mwai-chat-window');
    const $body = $('#mwai-chat-body');
    const $input = $('#mwai-user-input');
    const $send = $('#mwai-send-btn');
    const $close = $('#mwai-close-btn');
    const CHAT_HISTORY_KEY = 'mwai_chat_history';
    const CHAT_OPEN_STATE_KEY = 'mwai_chat_open';
    const CHAT_DRAFT_KEY = 'mwai_chat_draft'; // New key for draft message
    let history = []; // For multi-turn conversation

    // Accessibility: focus input when chat opens
    function openChat() {
        localStorage.setItem(CHAT_OPEN_STATE_KEY, 'true');
        $chat.addClass('open').removeClass('hidden');
        $fab.addClass('hidden-fab');
        setTimeout(() => {
            $input.focus();
            // Restore draft message if available
            const savedDraft = localStorage.getItem(CHAT_DRAFT_KEY);
            if (savedDraft) {
                $input.val(savedDraft);
            }
        }, 300);
        // Scroll to bottom when opening
        $body.scrollTop($body[0].scrollHeight);
    }
    function closeChat() {
        $chat.removeClass('open').addClass('hidden');
        $fab.removeClass('hidden-fab');
        localStorage.setItem(CHAT_OPEN_STATE_KEY, 'false');
    }

    // Load history from localStorage
    function loadHistory() {
        const storedHistory = localStorage.getItem(CHAT_HISTORY_KEY);
        if (storedHistory) {
            try {
                history = JSON.parse(storedHistory);
                // Clear current chat body before rendering loaded history
                $body.empty();
                history.forEach(entry => {
                    if (entry.role === 'user') {
                        appendMessage('user', $('<div>').text(entry.parts[0].text).html(), [], false, false); // Don't save, don't animate
                    } else if (entry.role === 'model') {
                        const products = entry.products || [];
                        appendMessage('ai', entry.parts[0].text, products, false, false); // Don't save, don't animate
                    }
                });
                $body.scrollTop($body[0].scrollHeight);
                // After loading all messages, ensure quick buttons are set based on the final state
                // This is now handled by appendMessage calling updateProductActionButtons,
                // but we ensure default buttons are added if no products were rendered.
                updateFinalQuickButtons(); // Ensure quick buttons are set after history loads
                return true; // History loaded
            } catch (e) {
                console.error("Failed to parse chat history from localStorage", e);
                history = [];
                return false;
            }
        }
        return false; // No history found
    }

    // Save history to localStorage
    function saveHistory() {
        localStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(history));
    }

    // Determine and render the appropriate quick action buttons
    function updateFinalQuickButtons() {
        const lastAiMessage = history.slice().reverse().find(entry => entry.role === 'model');
        if (lastAiMessage && lastAiMessage.products && lastAiMessage.products.length > 0) {
            // If the last AI message had products, show product action buttons (if any are selected)
            // This will be handled by updateProductActionButtons which clears and re-adds based on selection
            updateProductActionButtons(); 
        } else {
            // Otherwise, show default quick buttons
            addDefaultQuickButtons();
        }
    }

    // Toggle chat
    $fab.on('click', function(){
        if ($chat.hasClass('hidden')) { // Check for 'hidden' class to determine current state
            openChat();
            const historyLoaded = loadHistory();
            if (!historyLoaded) {
                showGreeting();
            }
        } else {
            closeChat();
        }
    });

    $close.on('click', function(){ closeChat(); });

    // Append message (role: 'ai' | 'user', contentHtml: string, productsData: array, save: boolean, animate: boolean)
    function appendMessage(role, contentHtml, productsData = [], save = true, animate = true) {
        const wrapper = $('<div>').addClass('mwai-msg ' + role);
        const $p = $('<p>');
        wrapper.append($p);
        $body.append(wrapper);

        const afterMessageRender = () => {
            if (productsData.length > 0) {
                // Create a container for products within this specific message wrapper
                const $productsContainer = $('<div class="mwai-products-container"></div>');
                wrapper.append($productsContainer);
                renderProducts(productsData, $productsContainer); // Pass the container to renderProducts
            }
            // Always update product action buttons after any message that might contain products
            // or after any message that might clear product selection.
            updateProductActionButtons();
            if (save) { saveHistory(); }
            $body.scrollTop($body[0].scrollHeight);
        };

        if (role === 'ai' && animate) {
            typeMessage($p, contentHtml, afterMessageRender);
        } else {
            $p.html(contentHtml);
            afterMessageRender();
        }
    }

    // Typing animation for AI messages
    function typeMessage($element, text, callback) {
        let i = 0;
        const speed = 5; // Typing speed in milliseconds (adjusted for faster typing)
        const interval = setInterval(() => {
            if (i < text.length) {
                $element.html(text.substring(0, i + 1));
                // Only scroll to bottom if user is near the bottom
                if ($body[0].scrollHeight - $body.scrollTop() - $body.outerHeight() < 50) { // 50px threshold
                    $body.scrollTop($body[0].scrollHeight);
                }
                i++;
            } else {
                clearInterval(interval);
                // Ensure final scroll to bottom after message is fully typed
                $body.scrollTop($body[0].scrollHeight);
                if (callback) callback();
            }
        }, speed);
    }

    // Typing indicator element
    function createTyping() {
        return $('<div class="mwai-msg ai typing"><span></span><span></span><span></span></div>');
    }

    // Show greeting sequence
    function showGreeting() {
        const greetings = [
            "👋 Hi! I'm MarketWhale AI — your shopping assistant.",
            "🛍️ I can help answer questions, find products, and recommend items.",
            "💬 Try: “I'm looking for a blue jacket” or ask any product question."
        ];

        let delay = 500;
        greetings.forEach((g, i) => {
            setTimeout(() => {
                const wrapper = $('<div>').addClass('mwai-msg ai');
                const $p = $('<p>').html(g);
                wrapper.append($p);
                $body.append(wrapper);
                $body.scrollTop($body[0].scrollHeight);

                if (i === greetings.length - 1) {
                    updateFinalQuickButtons(); // Ensure quick buttons are set after greeting
                }
            }, delay * (i + 1));
        });

        // Add greetings to history as model responses
        greetings.forEach(g => {
            history.push({role: 'model', parts: [{text: g}]});
        });
        saveHistory(); // Save history after greeting
    }

    const $quickActionsContainer = $('#mwai-quick-actions-container'); // Reference to the new persistent container

    // Add default quick action buttons
    function addDefaultQuickButtons() {
        $quickActionsContainer.empty(); // Clear previous buttons
        $quickActionsContainer.html(`
            <button data-message="Show catalog">Catalog</button>
            <button data-message="List categories">Categories</button>
            <button data-message="Search for a product">Search</button>
        `);
        // Handle button clicks for default buttons
        $quickActionsContainer.off('click', 'button').on('click', 'button', function(){
            const message = $(this).data('message');
            $input.val(message);
            sendMessage();
        });
        $body.scrollTop($body[0].scrollHeight);
    }

    // Add product-specific quick action buttons
    function addProductActionButtons() {
        $quickActionsContainer.empty(); // Clear previous buttons
        const numSelected = selectedProducts.size;

        if (numSelected === 1) {
            $quickActionsContainer.append('<button data-action="show_details">Show details</button>');
        } else if (numSelected >= 2) {
            $quickActionsContainer.append('<button data-action="compare_products">Compare</button>');
        }

        // Re-attach event listeners for the new product action buttons
        $quickActionsContainer.off('click', 'button').on('click', 'button', function(){
            const action = $(this).data('action');

            if (action === 'show_details') {
                const productId = Array.from(selectedProducts)[0];
                const $selectedCard = $(`.mwai-product-card[data-product-id="${productId}"]`);
                const productLink = $selectedCard.data('product-link');
                const productTitle = $selectedCard.data('product-title');
                
                const userMessage = `Tell me more about "${productTitle}"`;
                $input.val(userMessage);
                sendMessage();
            } else if (action === 'compare_products') {
                const productIds = Array.from(selectedProducts);
                const productTitles = productIds.map(id => $(`.mwai-product-card[data-product-id="${id}"]`).data('product-title'));
                
                const userMessage = `Compare these products: ${productTitles.join(', ')}`;
                $input.val(userMessage);
                sendMessage();
            }
        });
        $body.scrollTop($body[0].scrollHeight);
    }

    // Render unified product cards
    function renderProducts(products, $targetContainer, callback) { // Added $targetContainer parameter
        if (!products || products.length === 0) {
            if (callback) callback();
            return;
        }
        const container = $('<div class="mwai-products"></div>');
        products.forEach(function(p){
            const image = p.image || '';
            const title = p.title || '';
            const price = p.price || '';
            const link  = p.link || '#';
            const source = p.source || 'WooCommerce'; // Default to WooCommerce
            const store = p.store ? p.store : '';

            const sourceBadge = source === 'WooCommerce' ? '<span class="mwai-badge local">Local</span>' : '';

            const card = $(`
                <div class="mwai-product-card" data-product-id="${p.id}" data-product-link="${link}" data-product-title="${$('<div>').text(title).html()}">
                    <input type="checkbox" class="mwai-product-checkbox" data-product-id="${p.id}">
                    <div class="mwai-product-content">
                        <div class="mwai-product-media">
                            <img src="${image}" alt="${$('<div>').text(title).html()}">
                        </div>
                        <div class="mwai-product-meta">
                            <h4>${$('<div>').text(title).html()}</h4>
                            <div class="mwai-product-price">${price}</div>
                            ${sourceBadge}
                        </div>
                    </div>
                </div>
            `);
            container.append(card);
        });
        $targetContainer.append(container); // Append to the specified target container
        $body.scrollTop($body[0].scrollHeight);

        // Attach event listener for checkboxes
        // These listeners should be attached to the products within the specific container
        $targetContainer.find('.mwai-product-checkbox').off('change').on('change', updateProductActionButtons);
        // Attach event listener for product card content clicks (excluding checkbox)
        $targetContainer.find('.mwai-product-card .mwai-product-content').off('click').on('click', function() {
            const link = $(this).parent().data('product-link');
            if (link && link !== '#') {
                window.location.href = link; // Open in the same tab
            }
        });

        if (callback) callback();
    }

    let selectedProducts = new Set(); // Store product IDs of selected products

    function updateProductActionButtons() {
        selectedProducts.clear();
        $('.mwai-product-checkbox:checked').each(function() {
            selectedProducts.add($(this).data('product-id'));
        });
        addProductActionButtons(); // Call the function to render/update product action buttons
    }

    // Send user query
    function sendMessage() {
        const message = $input.val().trim();
        if (!message) return;

        appendMessage('user', $('<div>').text(message).html() ); // escape
        $input.val('');
        $body.scrollTop($body[0].scrollHeight);

        // Add to history
        history.push({role: 'user', parts: [{text: message}]});
        saveHistory(); // Save history after user message

        // Add typing indicator
        const $typing = createTyping();
        $body.append($typing);
        $body.scrollTop($body[0].scrollHeight);

        // Prepare history for API (remove products field)
        const historyForApi = history.map(entry => {
            const apiEntry = { role: entry.role, parts: entry.parts };
            return apiEntry;
        });

        // AJAX with history
        $.post(MWAI_Ajax.ajax_url, {
            action: 'mwai_get_response',
            history: JSON.stringify(historyForApi), // Send history without products
            _wpnonce: MWAI_Ajax.nonce
        }, function(response){
            $typing.remove();

            if (response && response.success && response.data) {
                const aiMessage = response.data.message || '';
                const productsData = response.data.products || [];

                // Add to history with products immediately after receiving response
                history.push({role: 'model', parts: [{text: aiMessage}], products: productsData});
                saveHistory(); // Save history immediately

                // Append AI text and products. The appendMessage function now handles adding quick buttons.
                appendMessage('ai', aiMessage, productsData, false, true); // Pass false for save, as it's already saved
            } else {
                const errorMessage = '⚠️ Sorry — unable to get a response. Please try again.';
                history.push({role: 'model', parts: [{text: errorMessage}]});
                saveHistory(); // Save history for error message
                appendMessage('ai', errorMessage, [], false, false); // Don't animate error messages, already saved
            }
            // Unselect all product checkboxes after AI response
            $('.mwai-product-checkbox').prop('checked', false);
            selectedProducts.clear();
            updateFinalQuickButtons(); // Update buttons after clearing selection and receiving response
        }, 'json').fail(function(){
            $typing.remove();
            appendMessage('ai', '⚠️ Network error — please try again.', [], true, false); // Don't animate network error messages
            history.push({role: 'model', parts: [{text: '⚠️ Network error — please try again.'}]});
            saveHistory(); // Save history for network error
            // Unselect all product checkboxes after AI response
            $('.mwai-product-checkbox').prop('checked', false);
            selectedProducts.clear();
            updateFinalQuickButtons(); // Update buttons after clearing selection and network error
        });
    }

    // Send on click / enter
    $send.on('click', sendMessage);
    $input.on('keypress', function(e){
        if (e.which === 13) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Ensure chat responsive on orientation change
    $(window).on('orientationchange resize', function(){
        $body.scrollTop($body[0].scrollHeight);
    });

    // Initial load of history when the page loads
    $(window).on('load', function() {
        const wasChatOpen = localStorage.getItem(CHAT_OPEN_STATE_KEY) === 'true';
        if (wasChatOpen) {
            openChat();
            const historyLoaded = loadHistory();
            if (!historyLoaded) {
                showGreeting();
            }
        }
    });

    // Save user's draft input to localStorage on keyup
    $input.on('keyup', function() {
        localStorage.setItem(CHAT_DRAFT_KEY, $input.val());
    });

    // Clear draft when message is sent
    function clearDraft() {
        localStorage.removeItem(CHAT_DRAFT_KEY);
    }

    // Modify sendMessage to clear draft
    const originalSendMessage = sendMessage;
    sendMessage = function() {
        originalSendMessage();
        clearDraft();
    };
});

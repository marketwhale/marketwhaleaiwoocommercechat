jQuery(document).ready(function($){
    const $fab = $('#mwai-fab');
    const $chat = $('#mwai-chat-window');
    const $body = $('#mwai-chat-body');
    const $input = $('#mwai-user-input');
    const $send = $('#mwai-send-btn');
    const $close = $('#mwai-close-btn');
    const CHAT_HISTORY_KEY = 'mwai_chat_history';
    const CHAT_OPEN_STATE_KEY = 'mwai_chat_open';
    let history = []; // For multi-turn conversation

    // Accessibility: focus input when chat opens
    function openChat() {
        localStorage.setItem(CHAT_OPEN_STATE_KEY, 'true');
        $chat.addClass('open').removeClass('hidden');
        $fab.addClass('hidden-fab');
        setTimeout(() => { $input.focus(); }, 300);
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
                        appendMessage('user', $('<div>').text(entry.parts[0].text).html(), [], false); // Don't save again
                    } else if (entry.role === 'model') {
                        const products = entry.products || [];
                        appendMessage('ai', entry.parts[0].text, products, false, false); // Don't save again, pass products, and don't animate
                    }
                });
                $body.scrollTop($body[0].scrollHeight);
                // Do not add default quick buttons here; handled by the load event or after AI response
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

    // Toggle chat
    $fab.on('click', function(){
        if ($chat.hasClass('hidden')) { // Check for 'hidden' class to determine current state
            openChat();
            const historyLoaded = loadHistory();
            if (!historyLoaded) {
                showGreeting();
            } else {
                // After loading history, check the last message to determine which buttons to show
                const lastMessage = history[history.length - 1];
                if (lastMessage && lastMessage.role === 'model' && lastMessage.products && lastMessage.products.length > 0) {
                    // If the last AI message had products, ensure product action buttons are updated
                    updateProductActionButtons();
                } else {
                    // Otherwise, show default quick buttons
                    addDefaultQuickButtons();
                }
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

        if (role === 'ai') {
            if (animate) {
                typeMessage($p, contentHtml, () => {
                    // After typing, render products and save history
                    if (productsData.length > 0) {
                        renderProducts(productsData);
                    }
                    $body.scrollTop($body[0].scrollHeight);
                    if (save) {
                        saveHistory();
                    }
                });
            } else {
                $p.html(contentHtml);
                if (productsData.length > 0) {
                    renderProducts(productsData);
                }
                $body.scrollTop($body[0].scrollHeight);
                if (save) {
                    saveHistory();
                }
            }
        } else {
            $p.html(contentHtml);
            if (productsData.length > 0) {
                renderProducts(productsData);
            }
            $body.scrollTop($body[0].scrollHeight);
            if (save) {
                saveHistory();
            }
        }
    }

    // Typing animation for AI messages
    function typeMessage($element, text, callback) {
        let i = 0;
        const speed = 20; // Typing speed in milliseconds
        const interval = setInterval(() => {
            if (i < text.length) {
                $element.html(text.substring(0, i + 1));
                $body.scrollTop($body[0].scrollHeight); // Keep scrolling to bottom
                i++;
            } else {
                clearInterval(interval);
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
                // For greeting messages, we don't want a typing animation for each one,
                // but rather append them directly. The typing animation is for actual AI responses.
                // However, to maintain consistency with the new appendMessage, we'll pass a dummy callback.
                const wrapper = $('<div>').addClass('mwai-msg ai');
                const $p = $('<p>').html(g);
                wrapper.append($p);
                $body.append(wrapper);
                $body.scrollTop($body[0].scrollHeight);

                if (i === greetings.length - 1) {
                    addDefaultQuickButtons(); // Use default buttons after greeting
                }
            }, delay * (i + 1));
        });

        // Add greetings to history as model responses
        greetings.forEach(g => {
            history.push({role: 'model', parts: [{text: g}]});
        });
        saveHistory(); // Save history after greeting
    }

    // Add default quick action buttons
    function addDefaultQuickButtons() {
        const $defaultButtonsContainer = $('#mwai-default-quick-buttons');
        if ($defaultButtonsContainer.length === 0) {
            $body.append('<div id="mwai-default-quick-buttons" class="mwai-quick-buttons"></div>');
        }
        $('#mwai-default-quick-buttons').html(`
            <button data-message="Show catalog">Catalog</button>
            <button data-message="List categories">Categories</button>
            <button data-message="Search for a product">Search</button>
        `);
        // Handle button clicks for default buttons
        $('#mwai-default-quick-buttons button').off('click').on('click', function(){
            const message = $(this).data('message');
            $input.val(message);
            sendMessage();
        });
        $body.scrollTop($body[0].scrollHeight);
    }

    // Add product-specific quick action buttons
    function addProductActionButtons() {
        let $productActionButtonsContainer = $('#mwai-product-action-buttons');
        if ($productActionButtonsContainer.length === 0) {
            $body.append('<div id="mwai-product-action-buttons" class="mwai-quick-buttons"></div>');
            $productActionButtonsContainer = $('#mwai-product-action-buttons');
        }
        $productActionButtonsContainer.empty(); // Clear existing buttons

        const numSelected = selectedProducts.size;

        if (numSelected === 1) {
            $productActionButtonsContainer.append('<button data-action="show_details">Show details</button>');
        } else if (numSelected >= 2) {
            $productActionButtonsContainer.append('<button data-action="compare_products">Compare</button>');
        }

        // Re-attach event listeners for the new product action buttons
        $productActionButtonsContainer.off('click', 'button').on('click', 'button', function(){
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
    function renderProducts(products) {
        if (!products || products.length === 0) return;
        const container = $('<div class="mwai-products"></div>');
        products.forEach(function(p){
            const image = p.image || '';
            const title = p.title || '';
            const price = p.price || '';
            const link  = p.link || '#';
            const source = p.source || 'WooCommerce'; // Default to WooCommerce
            const store = p.store ? p.store : '';

            const sourceBadge = source === 'WooCommerce' ? '<span class="mwai-badge local">Local</span>' : ''; // Only show local badge

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
        $body.append(container);
        $body.scrollTop($body[0].scrollHeight);

        // Attach event listener for checkboxes
        $('.mwai-product-checkbox').off('change').on('change', updateProductActionButtons);
        // Attach event listener for product card content clicks (excluding checkbox)
        $('.mwai-product-card .mwai-product-content').off('click').on('click', function() {
            const link = $(this).parent().data('product-link');
            if (link && link !== '#') {
                window.location.href = link; // Open in the same tab
            }
        });
        updateProductActionButtons(); // Update buttons immediately after rendering products
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

                // Append AI text and products. The appendMessage function now handles saving history after typing.
                appendMessage('ai', aiMessage, productsData, true, true); // Pass true to save history after typing and animate

                // Add to history with products (this will be saved by appendMessage's callback)
                history.push({role: 'model', parts: [{text: aiMessage}], products: productsData});

                // Conditionally add quick buttons based on product data
                if (productsData.length > 0) {
                    updateProductActionButtons(); // Show product-specific buttons
                } else {
                    addDefaultQuickButtons(); // Show default buttons
                }

                $body.scrollTop($body[0].scrollHeight);
            } else {
                appendMessage('ai', '⚠️ Sorry — unable to get a response. Please try again.', [], true, false); // Don't animate error messages
                history.push({role: 'model', parts: [{text: '⚠️ Sorry — unable to get a response. Please try again.'}]});
                saveHistory(); // Save history for error message
            }
        }, 'json').fail(function(){
            $typing.remove();
            appendMessage('ai', '⚠️ Network error — please try again.', [], true, false); // Don't animate network error messages
            history.push({role: 'model', parts: [{text: '⚠️ Network error — please try again.'}]});
            saveHistory(); // Save history for network error
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
            } else {
                // After loading history, check the last message to determine which buttons to show
                const lastMessage = history[history.length - 1];
                if (lastMessage && lastMessage.role === 'model' && lastMessage.products && lastMessage.products.length > 0) {
                    // If the last AI message had products, ensure product action buttons are updated
                    updateProductActionButtons();
                } else {
                    // Otherwise, show default quick buttons
                    addDefaultQuickButtons();
                }
            }
        }
    });
});

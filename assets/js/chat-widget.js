jQuery(document).ready(function($){
    const $fab = $('#mwai-fab');
    const $chat = $('#mwai-chat-window');
    const $body = $('#mwai-chat-body');
    const $input = $('#mwai-user-input');
    const $send = $('#mwai-send-btn');
    const $close = $('#mwai-close-btn');
    const CHAT_HISTORY_KEY = 'mwai_chat_history';
    let history = []; // For multi-turn conversation

    // Accessibility: focus input when chat opens
    function openChat() {
        $chat.removeClass('hidden');
        setTimeout(() => { $input.focus(); }, 300);
        // Scroll to bottom when opening
        $body.scrollTop($body[0].scrollHeight);
    }
    function closeChat() {
        $chat.addClass('hidden');
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
                        appendMessage('ai', entry.parts[0].text, products, false); // Don't save again, pass products
                    }
                });
                $body.scrollTop($body[0].scrollHeight);
                addQuickButtons(); // Add quick buttons after loading history
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
        if ($chat.hasClass('hidden')) {
            openChat();
            // Only show greeting if no history was loaded
            if (!loadHistory()) {
                showGreeting();
            }
        } else {
            closeChat();
        }
    });

    $close.on('click', function(){ closeChat(); });

    // Append message (role: 'ai' | 'user', contentHtml: string, productsData: array, save: boolean)
    function appendMessage(role, contentHtml, productsData = [], save = true) {
        const wrapper = $('<div>').addClass('mwai-msg ' + role);
        wrapper.append($('<p>').html(contentHtml));
        $body.append(wrapper);
        // If productsData is provided, render them immediately
        if (productsData.length > 0) {
            renderProducts(productsData);
        }
        $body.scrollTop($body[0].scrollHeight);
        if (save) {
            saveHistory(); // Save history after appending message
        }
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
                appendMessage('ai', g);
                if (i === greetings.length - 1) {
                    addQuickButtons();
                }
            }, delay * (i + 1));
        });

        // Add greetings to history as model responses
        greetings.forEach(g => {
            history.push({role: 'model', parts: [{text: g}]});
        });
        saveHistory(); // Save history after greeting
    }

    // Add quick action buttons
    function addQuickButtons() {
        if ($('.mwai-quick-buttons').length > 0) {
            $('.mwai-quick-buttons').remove(); // Remove existing to avoid duplicates
        }
        const buttonsHTML = $(`
            <div class="mwai-quick-buttons">
                <button data-message="Show catalog">Catalog</button>
                <button data-message="List categories">Categories</button>
                <button data-message="Search for a product">Search</button>
            </div>
        `);
        $body.append(buttonsHTML);
        $body.scrollTop($body[0].scrollHeight);

        // Handle button clicks
        $('.mwai-quick-buttons button').on('click', function(){
            const message = $(this).data('message');
            $input.val(message);
            sendMessage();
        });
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
                <a class="mwai-product-card" href="${link}">
                    <div class="mwai-product-media">
                        <img src="${image}" alt="${$('<div>').text(title).html()}">
                    </div>
                    <div class="mwai-product-meta">
                        <h4>${$('<div>').text(title).html()}</h4>
                        <div class="mwai-product-price">${price}</div>
                        ${sourceBadge}
                    </div>
                </a>
            `);
            container.append(card);
        });
        $body.append(container);
        $body.scrollTop($body[0].scrollHeight);
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

                // Append AI text and products
                appendMessage('ai', aiMessage, productsData);

                // Add to history with products
                history.push({role: 'model', parts: [{text: aiMessage}], products: productsData});

                // Add quick buttons after response
                addQuickButtons();

                $body.scrollTop($body[0].scrollHeight);
                saveHistory(); // Save history after AI response
            } else {
                appendMessage('ai', '⚠️ Sorry — unable to get a response. Please try again.');
            }
        }, 'json').fail(function(){
            $typing.remove();
            appendMessage('ai', '⚠️ Network error — please try again.');
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
        // If chat is already open (e.g., user refreshed page with chat open), load history
        if (!$chat.hasClass('hidden')) {
            loadHistory();
        }
    });
});

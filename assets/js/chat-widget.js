jQuery(document).ready(function($){
    const $chatBar = $('#mwai-chat-bar');
    const $chat = $('#mwai-chat-window');
    const $body = $('#mwai-chat-body');
    const $input = $('#mwai-user-input');
    const $toggleSendBtn = $('#mwai-toggle-send-btn');
    const $toggleSendBtnImg = $toggleSendBtn.find('img');
    const $close = $('#mwai-close-btn');

    const CHAT_HISTORY_KEY = 'mwai_chat_history';
    const CHAT_OPEN_STATE_KEY = 'mwai_chat_open';
    const CHAT_DRAFT_KEY = 'mwai_chat_draft';
    let history = [];

    const OPEN_CHAT_ICON = MWAI_Ajax.plugin_url + 'assets/images/openchat.png'; // Assuming plugin_url is localized
    const SEND_ICON = MWAI_Ajax.plugin_url + 'assets/images/send-icon.png';

    // Helper to get plugin URL (if not already localized)
    if (typeof MWAI_Ajax.plugin_url === 'undefined') {
        // Fallback if plugin_url is not localized, though it should be.
        // This would require knowing the plugin's base URL, which is hard in JS.
        // For now, assume MWAI_Ajax.plugin_url is available.
        console.error("MWAI_Ajax.plugin_url is not defined. Please ensure it's localized.");
        // Using relative paths as a last resort, but this is less robust.
        // OPEN_CHAT_ICON = 'assets/images/openchat.png';
        // SEND_ICON = 'assets/images/send-icon.png';
    }

    function openChat() {
        localStorage.setItem(CHAT_OPEN_STATE_KEY, 'true');
        $chat.addClass('open').removeClass('hidden');
        $toggleSendBtnImg.attr('src', SEND_ICON).attr('alt', 'Send');
        setTimeout(() => {
            $input.focus();
            const savedDraft = localStorage.getItem(CHAT_DRAFT_KEY);
            if (savedDraft) {
                $input.val(savedDraft);
            }
        }, 300);
        $body.scrollTop($body[0].scrollHeight);
    }

    function closeChat() {
        $chat.removeClass('open').addClass('hidden');
        $toggleSendBtnImg.attr('src', OPEN_CHAT_ICON).attr('alt', 'Open Chat');
        localStorage.setItem(CHAT_OPEN_STATE_KEY, 'false');
    }

    function loadHistory() {
        const storedHistory = localStorage.getItem(CHAT_HISTORY_KEY);
        if (storedHistory) {
            try {
                history = JSON.parse(storedHistory);
                $body.empty();
                history.forEach(entry => {
                    if (entry.role === 'user') {
                        appendMessage('user', $('<div>').text(entry.parts[0].text).html(), [], false, false);
                    } else if (entry.role === 'model') {
                        const products = entry.products || [];
                        appendMessage('ai', entry.parts[0].text, products, false, false);
                    }
                });
                $body.scrollTop($body[0].scrollHeight);
                updateFinalQuickButtons();
                return true;
            } catch (e) {
                console.error("Failed to parse chat history from localStorage", e);
                history = [];
                return false;
            }
        }
        return false;
    }

    function saveHistory() {
        localStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(history));
    }

    function updateFinalQuickButtons() {
        const lastAiMessage = history.slice().reverse().find(entry => entry.role === 'model');
        if (lastAiMessage && lastAiMessage.products && lastAiMessage.products.length > 0) {
            updateProductActionButtons();
        } else {
            addDefaultQuickButtons();
        }
    }

    // Handle the single toggle/send button click
    $toggleSendBtn.on('click', function(){
        if ($chat.hasClass('hidden')) {
            openChat();
            const historyLoaded = loadHistory();
            if (!historyLoaded) {
                showGreeting();
            }
        } else {
            sendMessage(); // If chat is open, send message
        }
    });

    // Handle the close button in the chat header
    $close.on('click', function(){
        closeChat();
    });

    function appendMessage(role, contentHtml, productsData = [], save = true, animate = true) {
        const wrapper = $('<div>').addClass('mwai-msg ' + role);
        const $p = $('<p>');
        wrapper.append($p);
        $body.append(wrapper);

        const afterMessageRender = () => {
            if (productsData.length > 0) {
                const $productsContainer = $('<div class="mwai-products-container"></div>');
                wrapper.append($productsContainer);
                renderProducts(productsData, $productsContainer);
            }
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

    function typeMessage($element, text, callback) {
        let i = 0;
        const speed = 5;
        const interval = setInterval(() => {
            if (i < text.length) {
                $element.html(text.substring(0, i + 1));
                if ($body[0].scrollHeight - $body.scrollTop() - $body.outerHeight() < 50) {
                    $body.scrollTop($body[0].scrollHeight);
                }
                i++;
            } else {
                clearInterval(interval);
                $body.scrollTop($body[0].scrollHeight);
                if (callback) callback();
            }
        }, speed);
    }

    function createTyping() {
        return $('<div class="mwai-msg ai typing"><span></span><span></span><span></span></div>');
    }

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
                    updateFinalQuickButtons();
                }
            }, delay * (i + 1));
        });

        greetings.forEach(g => {
            history.push({role: 'model', parts: [{text: g}]});
        });
        saveHistory();
    }

    const $quickActionsContainer = $('#mwai-quick-actions-container');

    function addDefaultQuickButtons() {
        $quickActionsContainer.empty();
        $quickActionsContainer.html(`
            <button data-message="Show catalog">Catalog</button>
            <button data-message="List categories">Categories</button>
            <button data-message="Search for a product">Search</button>
        `);
        $quickActionsContainer.off('click', 'button').on('click', 'button', function(){
            const message = $(this).data('message');
            $input.val(message);
            sendMessage();
        });
        $body.scrollTop($body[0].scrollHeight);
    }

    function addProductActionButtons() {
        $quickActionsContainer.empty();
        const numSelected = selectedProducts.size;

        if (numSelected === 1) {
            $quickActionsContainer.append('<button data-action="show_details">Show details</button>');
        } else if (numSelected >= 2) {
            $quickActionsContainer.append('<button data-action="compare_products">Compare</button>');
        }

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

    function renderProducts(products, $targetContainer, callback) {
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
            const source = p.source || 'WooCommerce';
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
        $targetContainer.append(container);
        $body.scrollTop($body[0].scrollHeight);

        $targetContainer.find('.mwai-product-checkbox').off('change').on('change', updateProductActionButtons);
        $targetContainer.find('.mwai-product-card .mwai-product-content').off('click').on('click', function() {
            const link = $(this).parent().data('product-link');
            if (link && link !== '#') {
                window.location.href = link;
            }
        });

        if (callback) callback();
    }

    let selectedProducts = new Set();

    function updateProductActionButtons() {
        selectedProducts.clear();
        $('.mwai-product-checkbox:checked').each(function() {
            selectedProducts.add($(this).data('product-id'));
        });
        addProductActionButtons();
    }

    function sendMessage() {
        const message = $input.val().trim();
        if (!message) return;

        appendMessage('user', $('<div>').text(message).html() );
        $input.val('');
        $body.scrollTop($body[0].scrollHeight);

        history.push({role: 'user', parts: [{text: message}]});
        saveHistory();

        const $typing = createTyping();
        $body.append($typing);
        $body.scrollTop($body[0].scrollHeight);

        const historyForApi = history.map(entry => {
            const apiEntry = { role: entry.role, parts: entry.parts };
            return apiEntry;
        });

        $.post(MWAI_Ajax.ajax_url, {
            action: 'mwai_get_response',
            history: JSON.stringify(historyForApi),
            _wpnonce: MWAI_Ajax.nonce
        }, function(response){
            $typing.remove();

            if (response && response.success && response.data) {
                const aiMessage = response.data.message || '';
                const productsData = response.data.products || [];

                history.push({role: 'model', parts: [{text: aiMessage}], products: productsData});
                saveHistory();

                appendMessage('ai', aiMessage, productsData, false, true);
            } else {
                const errorMessage = '⚠️ Sorry — unable to get a response. Please try again.';
                history.push({role: 'model', parts: [{text: errorMessage}]});
                saveHistory();
                appendMessage('ai', errorMessage, [], false, false);
            }
            $('.mwai-product-checkbox').prop('checked', false);
            selectedProducts.clear();
            updateFinalQuickButtons();
        }, 'json').fail(function(){
            $typing.remove();
            appendMessage('ai', '⚠️ Network error — please try again.', [], true, false);
            history.push({role: 'model', parts: [{text: '⚠️ Network error — please try again.'}]});
            saveHistory();
            $('.mwai-product-checkbox').prop('checked', false);
            selectedProducts.clear();
            updateFinalQuickButtons();
        });
    }

    $input.on('keypress', function(e){
        if (e.which === 13) {
            e.preventDefault();
            if ($chat.hasClass('open')) {
                sendMessage();
            } else {
                openChat();
                const historyLoaded = loadHistory();
                if (!historyLoaded) {
                    showGreeting();
                }
            }
        }
    });

    $(window).on('orientationchange resize', function(){
        $body.scrollTop($body[0].scrollHeight);
    });

    $(window).on('load', function() {
        const wasChatOpen = localStorage.getItem(CHAT_OPEN_STATE_KEY) === 'true';
        if (wasChatOpen) {
            openChat();
            const historyLoaded = loadHistory();
            if (!historyLoaded) {
                showGreeting();
            }
        } else {
            closeChat(); // Ensure button is 'openchat.png' if chat is closed on load
        }
    });

    $input.on('keyup', function() {
        localStorage.setItem(CHAT_DRAFT_KEY, $input.val());
    });
});

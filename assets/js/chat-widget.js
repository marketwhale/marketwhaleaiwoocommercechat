jQuery(document).ready(function($){
    const $fab = $('#mwai-fab');
    const $chat = $('#mwai-chat-window');
    const $body = $('#mwai-chat-body');
    const $input = $('#mwai-user-input');
    const $send = $('#mwai-send-btn');
    const $close = $('#mwai-close-btn');
    let firstOpen = true;
    let history = []; // For multi-turn conversation

    // Accessibility: focus input when chat opens
    function openChat() {
        $chat.removeClass('hidden');
        setTimeout(() => { $input.focus(); }, 300);
    }
    function closeChat() {
        $chat.addClass('hidden');
    }

    // Toggle chat
    $fab.on('click', function(){
        if ($chat.hasClass('hidden')) {
            openChat();
            if (firstOpen) {
                firstOpen = false;
                showGreeting();
            }
        } else {
            closeChat();
        }
    });

    $close.on('click', function(){ closeChat(); });

    // Append message (role: 'ai' | 'user')
    function appendMessage(role, contentHtml) {
        const wrapper = $('<div>').addClass('mwai-msg ' + role);
        if (role === 'ai') {
            wrapper.append($('<p>').html(contentHtml));
        } else {
            wrapper.append($('<p>').html(contentHtml));
        }
        $body.append(wrapper);
        $body.scrollTop($body[0].scrollHeight);
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

    // Send user query
    function sendMessage() {
        const message = $input.val().trim();
        if (!message) return;

        appendMessage('user', $('<div>').text(message).html() ); // escape
        $input.val('');
        $body.scrollTop($body[0].scrollHeight);

        // Add to history
        history.push({role: 'user', parts: [{text: message}]});

        // Add typing indicator
        const $typing = createTyping();
        $body.append($typing);
        $body.scrollTop($body[0].scrollHeight);

        // AJAX with history
        $.post(MWAI_Ajax.ajax_url, {
            action: 'mwai_get_response',
            history: JSON.stringify(history)
        }, function(response){
            $typing.remove();

            if (response && response.success && response.data) {
                // AI text
                if (response.data.message) {
                    const formattedMessage = response.data.message; // Already HTML
                    appendMessage('ai', formattedMessage );
                    // Add to history
                    history.push({role: 'model', parts: [{text: response.data.message}]});
                }

                // Products (clickable cards)
                if (response.data.products && response.data.products.length > 0) {
                    let productsHTML = $('<div class="mwai-products"></div>');
                    response.data.products.forEach(function(p){
                        const image = p.image || '';
                        const title = p.title || '';
                        const price = p.price || '';
                        const link  = p.link || '#';

                        const card = $(`
                            <a class="mwai-product-card" href="${link}" target="_blank" rel="noopener noreferrer">
                                <img src="${image}" alt="${$('<div>').text(title).html()}">
                                <h4>${$('<div>').text(title).html()}</h4>
                                <span>${price}</span>
                            </a>
                        `);
                        productsHTML.append(card);
                    });
                    $body.append(productsHTML);
                }

                // Add quick buttons after response
                addQuickButtons();

                $body.scrollTop($body[0].scrollHeight);
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
});
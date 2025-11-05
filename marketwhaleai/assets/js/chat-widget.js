(function($) {
    $(document).ready(function(){
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

        const OPEN_CHAT_ICON = MWAI_Ajax.plugin_url + 'assets/images/openchat.png';
        const SEND_ICON = MWAI_Ajax.plugin_url + 'assets/images/send-icon.png';

        const placeholders = [
            "Ask Shopping AI: What’s the best deal today?",
            "Find me sneakers under $50",
            "Compare iPhone 15 vs Samsung S24",
            "Show trending fashion this week",
            "Which laptop is best for students?",
            "Search top-rated headphones",
            "What’s on discount right now?",
            "Suggest gifts for under ₹2000",
            "Find eco-friendly products",
            "Show me today’s top offers"
        ];
        let currentPlaceholderIndex = 0;
        let typingInterval;
        let placeholderRotationInterval;

        // Helper to get plugin URL (if not already localized)
        if (typeof MWAI_Ajax.plugin_url === 'undefined') {
            console.error("MWAI_Ajax.plugin_url is not defined. Please ensure it's localized.");
        }

        function typePlaceholder(text, callback) {
            let i = 0;
            const speed = 50; // Speed for placeholder typing
            $input.attr('placeholder', ''); // Clear existing placeholder

            function typeCharPlaceholder() {
                if (i < text.length) {
                    $input.attr('placeholder', $input.attr('placeholder') + text[i]);
                    i++;
                    typingInterval = setTimeout(typeCharPlaceholder, speed);
                } else {
                    setTimeout(callback, 1500); // Wait before rotating to next
                }
            }
            typeCharPlaceholder();
        }

        function startPlaceholderRotation() {
            stopPlaceholderRotation(); // Ensure any existing rotation is stopped
            placeholderRotationInterval = setInterval(() => {
                if ($input.is(':focus') || $input.val().length > 0) {
                    // If input is focused or has text, don't rotate
                    return;
                }
                typePlaceholder(placeholders[currentPlaceholderIndex], () => {
                    currentPlaceholderIndex = (currentPlaceholderIndex + 1) % placeholders.length;
                });
            }, 3000); // Rotate every 3 seconds (after typing effect + 1.5s pause)
            // Initial call
            typePlaceholder(placeholders[currentPlaceholderIndex], () => {
                currentPlaceholderIndex = (currentPlaceholderIndex + 1) % placeholders.length;
            });
        }

        function stopPlaceholderRotation() {
            clearInterval(typingInterval);
            clearInterval(placeholderRotationInterval);
            $input.attr('placeholder', ''); // Clear placeholder when stopped
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
                    stopPlaceholderRotation(); // Stop if draft exists
                } else {
                    startPlaceholderRotation(); // Start if no draft
                }
            }, 300);
            $body.scrollTop($body[0].scrollHeight);
        }

        function closeChat() {
            $chat.removeClass('open').addClass('hidden');
            $toggleSendBtnImg.attr('src', OPEN_CHAT_ICON).attr('alt', 'Open Chat');
            localStorage.setItem(CHAT_OPEN_STATE_KEY, 'false');
            stopPlaceholderRotation(); // Stop rotation when chat is closed
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

        const PRODUCT_DISPLAY_THRESHOLD = 3; // Number of products to switch from grid to carousel

        function appendMessage(role, contentHtml, productsData = [], save = true, animate = true) {
            const wrapper = $('<div>').addClass('mwai-msg ' + role);
            const $p = $('<p>');
            wrapper.append($p);
            $body.append(wrapper);

            const afterMessageRender = () => {
                if (productsData.length > 0) {
                    if (productsData.length <= PRODUCT_DISPLAY_THRESHOLD) {
                        // Display as grid
                        const $productsGridContainer = $('<div class="mwai-products-grid-container"></div>'); // New wrapper for grid
                        wrapper.append($productsGridContainer);
                        renderProductsGrid(productsData, $productsGridContainer, () => {
                            updateProductActionButtons();
                        });
                    } else {
                        // Display as carousel
                        const $productsCarouselContainer = $('<div class="mwai-products-carousel-wrapper"></div>');
                        wrapper.append($productsCarouselContainer);
                        renderProductsCarousel(productsData, $productsCarouselContainer, () => {
                            updateProductActionButtons();
                        });
                    }
                } else {
                    updateProductActionButtons();
                }
                if (save) { saveHistory(); }
                $body.scrollTop($body[0].scrollHeight);
                updateFinalQuickButtons();
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
            const speed = 20; // Typing speed in milliseconds per character (increased for consistency)

            function typeChar() {
                if (i < text.length) {
                    $element.html(text.substring(0, i + 1));
                    // Auto-scroll to bottom if near the end
                    if ($body[0].scrollHeight - $body.scrollTop() - $body.outerHeight() < 50) {
                        $body.scrollTop($body[0].scrollHeight);
                    }
                    i++;
                    setTimeout(typeChar, speed); // Schedule next character
                } else {
                    $body.scrollTop($body[0].scrollHeight);
                    if (callback) callback(); // All characters typed, call callback
                }
            }
            typeChar(); // Start typing
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

        // New function to render products in a grid layout
        function renderProductsGrid(products, $targetContainer, callback) {
            if (!products || products.length === 0) {
                if (callback) callback();
                return;
            }
            const $grid = $('<div class="mwai-products mwai-products-grid"></div>'); // Add a class for grid-specific styling

            products.forEach(function(p){
                const image = p.image || '';
                const title = p.title || '';
                const price = p.price || '';
                const link  = p.link || '#';
                const source = p.source || 'WooCommerce';

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
                $grid.append(card);
            });
            $targetContainer.append($grid);
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

        // Existing function, renamed to renderProductsCarousel
        function renderProductsCarousel(products, $targetCarouselWrapper, callback) {
            if (!products || products.length === 0) {
                if (callback) callback();
                return;
            }

            const $carousel = $('<div class="mwai-products-carousel"></div>');
            const $productsContainer = $('<div class="mwai-products"></div>'); // This will be the flex container

            products.forEach(function(p){
                const image = p.image || '';
                const title = p.title || '';
                const price = p.price || '';
                const link  = p.link || '#';
                const source = p.source || 'WooCommerce';

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
                $productsContainer.append(card);
            });

            $carousel.append($productsContainer);
            $targetCarouselWrapper.append($carousel);

            // Add scroll buttons
            const $leftButton = $('<div class="mwai-scroll-button left hidden"><</div>');
            const $rightButton = $('<div class="mwai-scroll-button right hidden">></div>');
            $targetCarouselWrapper.append($leftButton).append($rightButton);

            // Function to update scroll button visibility
            function updateScrollButtons() {
                if ($carousel[0].scrollWidth > $carousel[0].clientWidth) {
                    if ($carousel[0].scrollLeft === 0) {
                        $leftButton.addClass('hidden');
                    } else {
                        $leftButton.removeClass('hidden');
                    }

                    if ($carousel[0].scrollLeft + $carousel[0].clientWidth >= $carousel[0].scrollWidth) {
                        $rightButton.addClass('hidden');
                    } else {
                        $rightButton.removeClass('hidden');
                    }
                } else {
                    $leftButton.addClass('hidden');
                    $rightButton.addClass('hidden');
                }
            }

            // Attach scroll event listener
            $carousel.on('scroll', updateScrollButtons);
            // Update on resize
            $(window).on('resize', updateScrollButtons);

            // Initial check for button visibility
            setTimeout(updateScrollButtons, 100);

            // Attach click handlers for scroll buttons
            $leftButton.on('click', function() {
                $carousel.animate({ scrollLeft: $carousel.scrollLeft() - 200 }, 300);
            });

            $rightButton.on('click', function() {
                $carousel.animate({ scrollLeft: $carousel.scrollLeft() + 200 }, 300);
            });

            $body.scrollTop($body[0].scrollHeight);

            $targetCarouselWrapper.find('.mwai-product-checkbox').off('change').on('change', updateProductActionButtons);
            $targetCarouselWrapper.find('.mwai-product-card .mwai-product-content').off('click').on('click', function() {
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
            startPlaceholderRotation(); // Always start rotation on load
        });

        $input.on('keyup', function() {
            localStorage.setItem(CHAT_DRAFT_KEY, $input.val());
            if ($input.val().length > 0) {
                stopPlaceholderRotation();
            } else {
                startPlaceholderRotation(); // Resume if input becomes empty
            }
        });

        $input.on('focus', function() {
            stopPlaceholderRotation();
        });

        $input.on('blur', function() {
            if ($input.val().length === 0) {
                startPlaceholderRotation();
            }
        });
    });
})(jQuery);

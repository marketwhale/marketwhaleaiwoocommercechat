jQuery(document).ready(function($) {
    const $shopContent = $('.woocommerce-products-header, .woocommerce-notices-wrapper, .woocommerce-archive-description, .woocommerce-result-count, .woocommerce-ordering, ul.products');
    const $mainContent = $('.site-main'); // Or a more specific container for your shop page content

    // Create main container for our enhancements
    const $enhancementsContainer = $('<div class="mwai-shop-enhancements"></div>');
    const $categoryScrollerContainer = $('<div id="mwai-category-scrollers"></div>');
    const $productGridContainer = $('<div id="mwai-product-grid-wrapper" style="position: relative;"></div>');
    const $productGrid = $('<div class="mwai-product-grid"></div>');
    const $loadingOverlay = $('<div class="mwai-loading-overlay"><div class="mwai-spinner"></div></div>');

    $productGridContainer.append($productGrid).append($loadingOverlay);
    $enhancementsContainer.append($categoryScrollerContainer).append($productGridContainer);

    // Replace existing WooCommerce content with our enhanced structure
    if ($shopContent.length) {
        $shopContent.first().before($enhancementsContainer);
        $shopContent.hide(); // Hide original WooCommerce elements
    } else {
        // Fallback if standard WooCommerce elements are not found
        $mainContent.prepend($enhancementsContainer);
    }

    let currentCategoryPath = []; // Stores the IDs of categories in the current path
    let currentSearchQuery = '';

    // Function to get URL parameter
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        const results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    function showLoading() {
        $loadingOverlay.addClass('active');
    }

    function hideLoading() {
        $loadingOverlay.removeClass('active');
        $('body').removeClass('mwai-shop-loading'); // Remove loading class when content is ready
    }

    function fetchCategories(parentId, level) {
        showLoading();
        $.ajax({
            url: MWAI_Shop_Ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mwai_get_categories',
                parent_id: parentId,
                nonce: MWAI_Shop_Ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderCategoryScroller(response.data.categories, parentId, level);
                } else {
                    console.error('Error fetching categories:', response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error fetching categories:', textStatus, errorThrown);
            },
            complete: function() {
                hideLoading();
            }
        });
    }

    function renderCategoryScroller(categories, parentId, level) {
        // Remove scrollers at deeper levels
        $categoryScrollerContainer.find(`.mwai-category-scroller-wrapper[data-level="${level}"] ~ .mwai-category-scroller-wrapper`).remove();
        $categoryScrollerContainer.find(`.mwai-category-scroller-wrapper[data-level="${level}"]`).remove();


        if (categories.length === 0 && level > 0) {
            // If no subcategories, don't render a new scroller
            return;
        }

        const $scrollerWrapper = $('<div class="mwai-category-scroller-wrapper"></div>').attr('data-level', level);
        const $scroller = $('<div class="mwai-category-scroller"></div>').attr('data-level', level);

        // Add an "All" tab for the current level if it's not the top level
        if (level > 0) {
            const parentCategoryName = currentCategoryPath.length > 0 ? $(`#mwai-category-scrollers .mwai-category-tab[data-category-id="${currentCategoryPath[level - 1]}"]`).text().replace(/\s\(\d+\)/, '') : 'All';
            const $allTab = $('<div class="mwai-category-tab active"></div>')
                .text(`All ${parentCategoryName}`)
                .data('category-id', parentId)
                .data('level', level);
            $scroller.append($allTab);
        } else {
            // For the top level, an "All Products" tab
            const $allProductsTab = $('<div class="mwai-category-tab active"></div>')
                .text('All Products')
                .data('category-id', 0) // 0 for all products
                .data('level', level);
            $scroller.append($allProductsTab);
        }


        categories.forEach(category => {
            const $tab = $('<div class="mwai-category-tab"></div>')
                .text(`${category.name} (${category.count})`)
                .data('category-id', category.id)
                .data('level', level);
            $scroller.append($tab);
        });

        $scrollerWrapper.append($scroller);

        // Add scroll buttons
        const $leftButton = $('<div class="mwai-scroll-button left hidden"><</div>');
        const $rightButton = $('<div class="mwai-scroll-button right hidden">></div>');
        $scrollerWrapper.append($leftButton).append($rightButton);

        $categoryScrollerContainer.append($scrollerWrapper);

        // Function to update scroll button visibility
        function updateScrollButtons() {
            if ($scroller[0].scrollWidth > $scroller[0].clientWidth) {
                // Scroller is actually scrollable
                if ($scroller[0].scrollLeft === 0) {
                    $leftButton.addClass('hidden');
                } else {
                    $leftButton.removeClass('hidden');
                }

                if ($scroller[0].scrollLeft + $scroller[0].clientWidth >= $scroller[0].scrollWidth) {
                    $rightButton.addClass('hidden');
                } else {
                    $rightButton.removeClass('hidden');
                }
            } else {
                // Not scrollable, hide both buttons
                $leftButton.addClass('hidden');
                $rightButton.addClass('hidden');
            }
        }

        // Attach scroll event listener
        $scroller.on('scroll', updateScrollButtons);
        // Update on resize
        $(window).on('resize', updateScrollButtons);

        // Initial check for button visibility
        setTimeout(updateScrollButtons, 100); // Small delay to ensure content is rendered

        // Attach click handlers for scroll buttons
        $leftButton.on('click', function() {
            $scroller.animate({ scrollLeft: $scroller.scrollLeft() - 200 }, 300);
        });

        $rightButton.on('click', function() {
            $scroller.animate({ scrollLeft: $scroller.scrollLeft() + 200 }, 300);
        });

        // Attach click handlers for category tabs
        $scroller.find('.mwai-category-tab').on('click', function() {
            const $clickedTab = $(this);
            const categoryId = $clickedTab.data('category-id');
            const clickedLevel = $clickedTab.data('level');

            // Update active class for the clicked tab within its scroller
            $clickedTab.siblings().removeClass('active');
            $clickedTab.addClass('active');

            // Update currentCategoryPath
            currentCategoryPath = currentCategoryPath.slice(0, clickedLevel);
            if (categoryId !== 0) { // If it's a specific category, add it to the path
                currentCategoryPath[clickedLevel] = categoryId;
            }

            // Fetch products for the selected category
            fetchProducts(categoryId);

            // Logic for handling subcategory scrollers
            if (categoryId === 0) { // "All Products" tab clicked (top level)
                // Remove all scrollers except the very first one (level 0)
                $categoryScrollerContainer.find(`.mwai-category-scroller-wrapper[data-level="0"] ~ .mwai-category-scroller-wrapper`).remove();
                // No need to fetch subcategories for "All Products"
            } else if ($clickedTab.text().startsWith('All ') && clickedLevel > 0) { // "All [Parent Category]" tab clicked
                // Remove scrollers deeper than the current level
                $categoryScrollerContainer.find(`.mwai-category-scroller-wrapper[data-level="${clickedLevel}"] ~ .mwai-category-scroller-wrapper`).remove();
                // No need to fetch subcategories when clicking "All" for a parent
            } else { // A specific category tab clicked
                // Fetch subcategories for the clicked category
                fetchCategories(categoryId, clickedLevel + 1);
            }
        });
    }

    function fetchProducts(categoryId = 0, searchQuery = '', paged = 1) {
        showLoading();
        $.ajax({
            url: MWAI_Shop_Ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mwai_filter_products',
                category_id: categoryId,
                search_query: searchQuery,
                paged: paged,
                nonce: MWAI_Shop_Ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $productGrid.html(response.data.products_html);
                    // Handle pagination if needed (response.data.max_pages, response.data.current_page)
                } else {
                    $productGrid.html('<p>' + (response.data.message || 'Error fetching products.') + '</p>');
                    console.error('Error fetching products:', response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $productGrid.html('<p>Network error: Unable to load products.</p>');
                console.error('AJAX error fetching products:', textStatus, errorThrown);
            },
            complete: function() {
                hideLoading();
            }
        });
    }

    // Initial load
    const initialSearchTerm = getUrlParameter('s');
    const isProductSearchPage = getUrlParameter('post_type') === 'product';

    fetchCategories(0, 0); // Load top-level categories

    if (initialSearchTerm && isProductSearchPage) {
        currentSearchQuery = initialSearchTerm;
        fetchProducts(0, currentSearchQuery); // Load products based on search query
    } else {
        fetchProducts(0); // Load all products by default
    }
});

jQuery(document).ready(function($) {
    // Determine if we are on a standard WooCommerce shop page or within an embedded shortcode
    const isShopPage = $('body').hasClass('woocommerce-shop') || $('body').hasClass('tax-product_cat') || $('body').hasClass('tax-product_tag');
    const $embeddedShopContainer = $('.mwai-embedded-shop');
    const isEmbedded = $embeddedShopContainer.length > 0;

    let $targetContainer;
    let initialProductLimit = 12; // Default limit

    if (isEmbedded) {
        $targetContainer = $embeddedShopContainer;
        initialProductLimit = parseInt($embeddedShopContainer.data('product-limit')) || 12;
        // For embedded shops, we don't hide existing content, we just initialize within the shortcode's div
    } else if (isShopPage) {
        const $shopContent = $('.woocommerce-products-header, .woocommerce-notices-wrapper, .woocommerce-archive-description, .woocommerce-result-count, .woocommerce-ordering, ul.products, .woocommerce-pagination');
        const $mainContent = $('.site-main'); // Or a more specific container for your shop page content

        // Create main container for our enhancements
        const $enhancementsContainer = $('<div class="mwai-shop-enhancements"></div>');
        $shopContent.first().before($enhancementsContainer);
        $shopContent.hide(); // Hide original WooCommerce elements
        $('.woocommerce-pagination').remove(); // Also remove pagination elements from DOM to prevent interaction
        $targetContainer = $enhancementsContainer;
    } else {
        // If neither shop page nor embedded, do nothing
        return;
    }

    const $categoryScrollerContainer = $('<div id="mwai-category-scrollers"></div>');
    const $productGridContainer = $('<div id="mwai-product-grid-wrapper" style="position: relative;"></div>');
    const $productGrid = $('<div class="mwai-products-grid"></div>');
    const $loadingOverlay = $('<div class="mwai-loading-overlay"><div class="mwai-spinner"></div></div>');

    $productGridContainer.append($productGrid).append($loadingOverlay);
    $targetContainer.append($categoryScrollerContainer).append($productGridContainer);

    let currentCategoryPath = []; // Stores the IDs of categories in the current path
    let currentSearchQuery = '';
    let initialCategoryId = 0; // To store the category ID derived from the URL path
    let activeCategoryPathIds = []; // Stores the full path of active category IDs
    let categoriesLoadedCount = 0;
    let totalCategoriesToLoad = 0;
    let currentPage = 1; // Current page for product loading
    let maxPages = 1;    // Total pages available for the current product query
    let isLoadingProducts = false; // Flag to prevent multiple simultaneous AJAX requests
    let productsPerPage = initialProductLimit; // Use the initialProductLimit here

    // Function to get URL parameter
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        const results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    // Function to extract category slugs from the URL path
    function getCategorySlugsFromUrl() {
        if (!isShopPage) return []; // Only extract from URL if on a shop page
        const path = window.location.pathname;
        const parts = path.split('/').filter(part => part !== '');
        const categoryIndex = parts.indexOf('product-category');
        if (categoryIndex !== -1) {
            return parts.slice(categoryIndex + 1);
        }
        return [];
    }

    function showLoading() {
        $loadingOverlay.addClass('active');
    }

    function hideLoading() {
        $loadingOverlay.removeClass('active');
        if (isShopPage) {
            $('body').removeClass('mwai-shop-loading'); // Only remove loading class from body if on shop page
        }
    }

    function fetchCategories(parentId, level, activeCategoryPath = [], callback = null) {
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
                    renderCategoryScroller(response.data.categories, parentId, level, activeCategoryPath);
                    if (callback) callback(response.data.categories);
                } else {
                    console.error('Error fetching categories:', response.data.message);
                    if (callback) callback([]);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error fetching categories:', textStatus, errorThrown);
                if (callback) callback([]);
            },
            complete: function() {
                categoriesLoadedCount++;
                if (categoriesLoadedCount >= totalCategoriesToLoad) {
                    hideLoading();
                }
            }
        });
    }

    function renderCategoryScroller(categories, parentId, level, activeCategoryPath = []) {
        // Remove scrollers at deeper levels
        $categoryScrollerContainer.find(`.mwai-category-scroller-wrapper[data-level="${level}"] ~ .mwai-category-scroller-wrapper`).remove();
        $categoryScrollerContainer.find(`.mwai-category-scroller-wrapper[data-level="${level}"]`).remove();

        if (categories.length === 0 && level > 0) {
            // If no subcategories, don't render a new scroller
            return;
        }

        const $scrollerWrapper = $('<div class="mwai-category-scroller-wrapper"></div>').attr('data-level', level);
        const $scroller = $('<div class="mwai-category-scroller"></div>').attr('data-level', level);

        const activeCategoryIdForLevel = activeCategoryPath[level];

        // Add an "All" tab for the current level if it's not the top level
        if (level > 0) {
            // Determine the name of the parent category for the "All" tab text
            const parentCategoryName = $categoryScrollerContainer.find(`.mwai-category-tab[data-category-id="${parentId}"]`).text().replace(/\s\(\d+\)/, '');
            const $allTab = $('<div class="mwai-category-tab"></div>')
                .text(`All ${parentCategoryName}`)
                .data('category-id', parentId)
                .data('level', level);
            // The "All" tab for a level is active if no specific subcategory is active at this level
            // and the parentId itself is the active category for this level in the path.
            if (activeCategoryIdForLevel === undefined && activeCategoryPath[level - 1] === parentId) {
                $allTab.addClass('active');
            }
            $scroller.append($allTab);
        } else {
            // For the top level, an "All Products" tab
            const $allProductsTab = $('<div class="mwai-category-tab"></div>')
                .text('All Products')
                .data('category-id', 0) // 0 for all products
                .data('level', level);
            // The "All Products" tab is active if no specific category is active in the path
            if (activeCategoryPath.length === 0 || activeCategoryIdForLevel === 0) {
                $allProductsTab.addClass('active');
            }
            $scroller.append($allProductsTab);
        }


        categories.forEach(category => {
            const $tab = $('<div class="mwai-category-tab"></div>')
                .text(`${category.name} (${category.count})`)
                .data('category-id', category.id)
                .data('level', level);
            if (activeCategoryIdForLevel === category.id) {
                $tab.addClass('active');
            }
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

        // Auto-scroll to active tab
        setTimeout(() => {
            const $activeTab = $scroller.find('.mwai-category-tab.active');
            if ($activeTab.length) {
                const scrollerWidth = $scroller.width();
                const scrollerScrollLeft = $scroller.scrollLeft();
                const tabOffsetLeft = $activeTab.position().left;
                const tabWidth = $activeTab.outerWidth(true); // Include margins

                // Check if tab is outside the current view
                if (tabOffsetLeft < 0 || tabOffsetLeft + tabWidth > scrollerWidth) {
                    // Calculate new scroll position to center the active tab
                    const newScrollLeft = scrollerScrollLeft + tabOffsetLeft - (scrollerWidth / 2) + (tabWidth / 2);
                    $scroller.animate({ scrollLeft: newScrollLeft }, 300);
                }
            }
            updateScrollButtons(); // Call after potential scroll
        }, 150); // Small delay to ensure rendering is complete before calculating positions

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

            // Update currentCategoryPath based on the clicked tab
            currentCategoryPath = activeCategoryPathIds.slice(0, clickedLevel); // Start with the initial path up to the clicked level's parent
            if (categoryId !== 0) { // If it's a specific category, add it to the path
                currentCategoryPath[clickedLevel] = categoryId;
            } else { // If "All" tab is clicked, ensure currentCategoryPath reflects this
                currentCategoryPath = currentCategoryPath.slice(0, clickedLevel); // Truncate path
            }
            activeCategoryPathIds = [...currentCategoryPath]; // Update activeCategoryPathIds for consistent state

            // Reset pagination state for new category/search
            currentPage = 1;
            maxPages = 1;

            // Fetch products for the selected category
            fetchProducts(categoryId, currentSearchQuery, currentPage, false, productsPerPage);

            // Logic for handling subcategory scrollers
            const isAllTabForLevel = (categoryId === parentId && clickedLevel > 0); // Check if it's an "All [Parent Category]" tab
            
            if (categoryId === 0 || isAllTabForLevel) { // "All Products" tab clicked (top level) or "All [Parent Category]" tab clicked
                // Remove all scrollers deeper than the current level
                $categoryScrollerContainer.find(`.mwai-category-scroller-wrapper[data-level="${clickedLevel}"] ~ .mwai-category-scroller-wrapper`).remove();
                // If "All Products" (top level) or "All [Parent Category]" is clicked, no need to fetch subcategories
            } else { // A specific category tab clicked
                // Fetch subcategories for the clicked category
                fetchCategories(categoryId, clickedLevel + 1, activeCategoryPathIds);
            }
        });
    }

    function fetchProducts(categoryId = 0, searchQuery = '', paged = 1, append = false, limit = productsPerPage) {
        if (isLoadingProducts) return; // Prevent multiple requests
        isLoadingProducts = true;
        showLoading();

        $.ajax({
            url: MWAI_Shop_Ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mwai_filter_products',
                category_id: categoryId,
                search_query: searchQuery,
                paged: paged,
                posts_per_page: limit, // Pass the limit here
                nonce: MWAI_Shop_Ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (append) {
                        $productGrid.append(response.data.products_html);
                    } else {
                        $productGrid.html(response.data.products_html);
                        // Reset scroll position for new category/search
                        $productGridContainer.scrollTop(0);
                    }
                    currentPage = response.data.current_page;
                    maxPages = response.data.max_pages;
                } else {
                    if (!append) {
                        $productGrid.html('<p>' + (response.data.message || 'Error fetching products.') + '</p>');
                    }
                    console.error('Error fetching products:', response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                if (!append) {
                    $productGrid.html('<p>Network error: Unable to load products.</p>');
                }
                console.error('AJAX error fetching products:', textStatus, errorThrown);
            },
            complete: function() {
                hideLoading();
                isLoadingProducts = false;
            }
        });
    }

    // Function to recursively load category scrollers
    function loadCategoryScrollersRecursively(pathIds, currentLevel, parentId) {
        if (currentLevel >= pathIds.length) {
            return; // All levels loaded
        }

        const targetCategoryId = pathIds[currentLevel];
        
        fetchCategories(parentId, currentLevel, pathIds, (categories) => {
            // Find the category that matches targetCategoryId at this level
            const nextParentId = targetCategoryId;
            if (nextParentId) {
                loadCategoryScrollersRecursively(pathIds, currentLevel + 1, nextParentId);
            }
        });
    }

    // Initial load logic
    const initialSearchTerm = getUrlParameter('s');
    const isProductSearchPage = getUrlParameter('post_type') === 'product';
    const categorySlugs = getCategorySlugsFromUrl();

    if (categorySlugs.length > 0) {
        showLoading();
        // First, get the full path of category IDs from the slugs
        $.ajax({
            url: MWAI_Shop_Ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mwai_get_category_path_by_slugs',
                slugs: categorySlugs,
                nonce: MWAI_Shop_Ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.category_path_ids.length > 0) {
                    activeCategoryPathIds = response.data.category_path_ids;
                    activeCategoryPathIds = response.data.category_path_ids;
                    initialCategoryId = activeCategoryPathIds[activeCategoryPathIds.length - 1]; // Deepest category ID
                    currentCategoryPath = [...activeCategoryPathIds]; // Initialize currentCategoryPath

                    // Set total categories to load for hideLoading logic
                    totalCategoriesToLoad = activeCategoryPathIds.length + 1; // +1 for the initial top-level fetch

                    // Start loading scrollers recursively
                    loadCategoryScrollersRecursively(activeCategoryPathIds, 0, 0);
                    currentPage = 1; // Reset page for new category
                    fetchProducts(initialCategoryId, initialSearchTerm, currentPage, false, productsPerPage);
                } else {
                    console.error('Error fetching category path by slugs:', response.data.message);
                    // Fallback to default if path not found
                    totalCategoriesToLoad = 1;
                    fetchCategories(0, 0, []);
                    currentPage = 1; // Reset page
                    fetchProducts(0, '', currentPage, false, productsPerPage);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error fetching category path by slugs:', textStatus, errorThrown);
                // Fallback to default on error
                totalCategoriesToLoad = 1;
                fetchCategories(0, 0, []);
                currentPage = 1; // Reset page
                fetchProducts(0, '', currentPage, false, productsPerPage);
            },
            complete: function() {
                // This complete is for the path fetching, not for all category scrollers
            }
        });
    } else if (initialSearchTerm && isProductSearchPage) {
        currentSearchQuery = initialSearchTerm;
        totalCategoriesToLoad = 1; // Only top-level categories
        fetchCategories(0, 0, []); // Load top-level categories
        currentPage = 1; // Reset page
        fetchProducts(0, currentSearchQuery, currentPage, false, productsPerPage); // Load products based on search query
    } else {
        totalCategoriesToLoad = 1; // Only top-level categories
        fetchCategories(0, 0, []); // Load top-level categories
        currentPage = 1; // Reset page
        fetchProducts(0, '', currentPage, false, productsPerPage); // Load all products by default
    }

    // Infinite scrolling logic
    $productGridContainer.on('scroll', function() {
        const container = $(this)[0];
        // Check if scrolled to 80% of the way down
        if (container.scrollTop + container.clientHeight >= container.scrollHeight * 0.8) {
            if (currentPage < maxPages && !isLoadingProducts) {
                currentPage++;
                const currentCategoryId = currentCategoryPath.length > 0 ? currentCategoryPath[currentCategoryPath.length - 1] : 0;
                fetchProducts(currentCategoryId, currentSearchQuery, currentPage, true, productsPerPage);
            }
        }
    });
});

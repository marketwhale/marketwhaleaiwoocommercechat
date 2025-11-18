(function($) {
    $(document).ready(function() {
        // Determine if we are on a standard WooCommerce shop page or within an embedded shortcode
        const isShopPage = $('body').hasClass('woocommerce-shop') || $('body').hasClass('tax-product_cat') || $('body').hasClass('tax-product_tag');
        const $embeddedShopContainer = $('.mwai-embedded-shop');
        const isEmbedded = $embeddedShopContainer.length > 0;

        let $targetContainer;
        let initialProductLimit = 12; // Default limit
        let minPrice = 0;
        let maxPrice = 999999; // A sufficiently large number
        let selectedAttributes = {}; // { 'pa_color': ['red', 'blue'], 'pa_size': ['large'] }
        let stockStatus = ''; // 'instock', 'outofstock', or ''
        let currentOrderBy = 'menu_order title'; // Default sorting
        let currentOrder = 'ASC'; // Default order

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

        // New filter and sort wrapper
        const $filtersSortWrapper = $('<div class="mwai-shop-filters-sort-wrapper"></div>');
        const $filtersSidebar = $('<div class="mwai-shop-filters-sidebar"></div>');
        const $productsContent = $('<div class="mwai-shop-products-content"></div>');

        const $categoryScrollerContainer = $('<div id="mwai-category-scrollers"></div>');
        const $productGridContainer = $('<div id="mwai-product-grid-wrapper" style="position: relative;"></div>');
        const $productGrid = $('<div class="mwai-products-grid"></div>');
        const $loadingOverlay = $('<div class="mwai-loading-overlay"><div class="mwai-spinner"></div></div>');

        $productGridContainer.append($productGrid).append($loadingOverlay);
        $productsContent.append($categoryScrollerContainer).append($productGridContainer);
        $filtersSortWrapper.append($filtersSidebar).append($productsContent);
        $targetContainer.append($filtersSortWrapper);


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

        // Function to get all product attributes
        function fetchProductAttributes(callback) {
            $.ajax({
                url: MWAI_Shop_Ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mwai_get_product_attributes',
                    nonce: MWAI_Shop_Ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        callback(response.data.attributes);
                    } else {
                        console.error('Error fetching product attributes:', response.data.message);
                        callback([]);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error fetching product attributes:', textStatus, errorThrown);
                    callback([]);
                }
            });
        }

        // Function to get min/max price
        function fetchMinMaxPrice(callback) {
            $.ajax({
                url: MWAI_Shop_Ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mwai_get_min_max_price',
                    nonce: MWAI_Shop_Ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        callback(response.data.min_price, response.data.max_price);
                    } else {
                        console.error('Error fetching min/max price:', response.data.message);
                        callback(0, 1000); // Fallback
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error fetching min/max price:', textStatus, errorThrown);
                    callback(0, 1000); // Fallback
                }
            });
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

        // Function to recursively load category scrollers
        function loadCategoryScrollersRecursively(pathIds, currentLevel, parentId) {
            if (currentLevel >= pathIds.length) {
                // All active categories in the path have been processed.
                // Now fetch subcategories for the deepest active category, or top-level if path is empty.
                const finalParentId = pathIds.length > 0 ? pathIds[pathIds.length - 1] : 0;
                fetchCategories(finalParentId, pathIds.length, pathIds, () => {
                    categoriesLoadedCount++;
                    if (categoriesLoadedCount >= totalCategoriesToLoad) {
                        hideLoading();
                    }
                });
                return;
            }

            const categoryIdToLoad = pathIds[currentLevel];
            fetchCategories(parentId, currentLevel, pathIds, (categories) => {
                categoriesLoadedCount++;
                if (categoriesLoadedCount >= totalCategoriesToLoad) {
                    hideLoading();
                }
                // Find the category that matches categoryIdToLoad to get its children
                const foundCategory = categories.find(cat => cat.id === categoryIdToLoad);
                if (foundCategory) {
                    loadCategoryScrollersRecursively(pathIds, currentLevel + 1, categoryIdToLoad);
                } else {
                    // If a category in the path is not found at this level, stop recursion
                    console.warn(`Category ID ${categoryIdToLoad} not found at level ${currentLevel}. Stopping recursive category loading.`);
                }
            });
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
                    // Removed hideLoading() from here. It will be handled by fetchProducts.
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
                const decodedCategoryName = $('<textarea/>').html(category.name).text(); // Decode HTML entities
                const $tab = $('<div class="mwai-category-tab"></div>')
                    .text(`${decodedCategoryName} (${category.count})`)
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

            const $embeddedShop = $('.mwai-embedded-shop');
            const slideshowEnabledForAjax = $embeddedShop.length ? ($embeddedShop.data('slideshow-enabled') === true || $embeddedShop.data('slideshow-enabled') === 'true') : true;

            $.ajax({
                url: MWAI_Shop_Ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mwai_filter_products',
                    category_id: categoryId,
                    search_query: searchQuery,
                    paged: paged,
                    posts_per_page: limit, // Pass the limit here
                    slideshow_enabled: slideshowEnabledForAjax, // Pass slideshow state to AJAX
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

        // Function to apply all filters and sorting
        function applyFiltersAndSort(append = false) {
            const currentCategoryId = currentCategoryPath.length > 0 ? currentCategoryPath[currentCategoryPath.length - 1] : 0;
            fetchProducts(currentCategoryId, currentSearchQuery, currentPage, append, productsPerPage, minPrice, maxPrice, selectedAttributes, stockStatus, currentOrderBy, currentOrder);
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
                        initialCategoryId = activeCategoryPathIds[activeCategoryPathIds.length - 1]; // Deepest category ID
                        currentCategoryPath = [...activeCategoryPathIds]; // Initialize currentCategoryPath

                        // Set total categories to load for hideLoading logic
                        // +1 for the initial top-level fetch, +1 for the subcategories of the deepest active category
                        totalCategoriesToLoad = activeCategoryPathIds.length + 1; 

                        // Start loading scrollers recursively
                        loadCategoryScrollersRecursively(activeCategoryPathIds, 0, 0);
                        currentPage = 1; // Reset page for new category
                        applyFiltersAndSort();
                    } else {
                        console.error('Error fetching category path by slugs:', response.data.message);
                        // Fallback to default if path not found
                        totalCategoriesToLoad = 1; // Only top-level categories
                        fetchCategories(0, 0, []);
                        currentPage = 1; // Reset page
                        applyFiltersAndSort();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error fetching category path by slugs:', textStatus, errorThrown);
                    // Fallback to default on error
                    totalCategoriesToLoad = 1; // Only top-level categories
                    fetchCategories(0, 0, []);
                    currentPage = 1; // Reset page
                    applyFiltersAndSort();
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
            applyFiltersAndSort(); // Load products based on search query
        } else {
            totalCategoriesToLoad = 1; // Only top-level categories
            fetchCategories(0, 0, []); // Load top-level categories
            currentPage = 1; // Reset page
            applyFiltersAndSort(); // Load all products by default
        }

        // Initialize filters and sorting UI
        function initializeFiltersAndSortUI(minGlobalPrice, maxGlobalPrice, attributesData) {
            $filtersSidebar.empty(); // Clear existing content

            // Price Filter
            $filtersSidebar.append('<h3>Filters</h3>');
            const $priceFilterGroup = $('<div class="mwai-filter-group"></div>');
            $priceFilterGroup.append('<label>Price Range</label>');
            const $priceRangeDisplay = $('<div class="mwai-price-range-display"></div>');
            $priceFilterGroup.append($priceRangeDisplay);
            const $priceSliderContainer = $('<div class="mwai-price-slider-container"></div>');
            $priceFilterGroup.append($priceSliderContainer);
            $filtersSidebar.append($priceFilterGroup);

            $priceSliderContainer.slider({
                range: true,
                min: minGlobalPrice,
                max: maxGlobalPrice,
                values: [minGlobalPrice, maxGlobalPrice],
                slide: function(event, ui) {
                    $priceRangeDisplay.text(`₹${ui.values[0]} - ₹${ui.values[1]}`);
                },
                change: function(event, ui) {
                    minPrice = ui.values[0];
                    maxPrice = ui.values[1];
                    currentPage = 1; // Reset page on filter change
                    applyFiltersAndSort();
                }
            });
            $priceRangeDisplay.text(`₹${minGlobalPrice} - ₹${maxGlobalPrice}`); // Initial display

            // Attribute Filters
            if (attributesData && attributesData.length > 0) {
                attributesData.forEach(attr => {
                    const $attrFilterGroup = $('<div class="mwai-filter-group"></div>');
                    $attrFilterGroup.append(`<label>${attr.label}</label>`);
                    const $attrOptions = $('<div class="mwai-filter-options"></div>');
                    attr.terms.forEach(term => {
                        const checkboxId = `mwai-attr-${attr.slug}-${term.slug}`;
                        $attrOptions.append(`
                            <label for="${checkboxId}">
                                <input type="checkbox" id="${checkboxId}" data-attribute="${attr.slug}" value="${term.slug}">
                                ${term.name}
                            </label>
                        `);
                    });
                    $attrFilterGroup.append($attrOptions);
                    $filtersSidebar.append($attrFilterGroup);
                });

                $filtersSidebar.on('change', '.mwai-filter-group input[type="checkbox"]', function() {
                    const $checkbox = $(this);
                    const attributeSlug = $checkbox.data('attribute');
                    const termSlug = $checkbox.val();

                    if (!$checkbox.is(':checked')) {
                        if (selectedAttributes[attributeSlug]) {
                            selectedAttributes[attributeSlug] = selectedAttributes[attributeSlug].filter(s => s !== termSlug);
                            if (selectedAttributes[attributeSlug].length === 0) {
                                delete selectedAttributes[attributeSlug];
                            }
                        }
                    } else {
                        if (!selectedAttributes[attributeSlug]) {
                            selectedAttributes[attributeSlug] = [];
                        }
                        selectedAttributes[attributeSlug].push(termSlug);
                    }
                    currentPage = 1; // Reset page on filter change
                    applyFiltersAndSort();
                });
            }

            // Stock Status Filter
            const $stockFilterGroup = $('<div class="mwai-filter-group"></div>');
            $stockFilterGroup.append('<label>Availability</label>');
            const $stockOptions = $('<div class="mwai-filter-options"></div>');
            $stockOptions.append(`
                <label for="mwai-stock-instock">
                    <input type="checkbox" id="mwai-stock-instock" value="instock">
                    In Stock
                </label>
            `);
            $stockFilterGroup.append($stockOptions);
            $filtersSidebar.append($stockFilterGroup);

            $filtersSidebar.on('change', '#mwai-stock-instock', function() {
                stockStatus = $(this).is(':checked') ? 'instock' : '';
                currentPage = 1; // Reset page on filter change
                applyFiltersAndSort();
            });

            // Sorting Dropdown (moved to products content area for better layout)
            const $sortOptions = $('<div class="mwai-sort-options"></div>');
            $sortOptions.append('<label for="mwai-sort-by">Sort by:</label>');
            const $sortSelect = $(`
                <select id="mwai-sort-by">
                    <option value="menu_order title ASC">Default sorting</option>
                    <option value="popularity DESC">Sort by popularity</option>
                    <option value="rating DESC">Sort by average rating</option>
                    <option value="date DESC">Sort by newness</option>
                    <option value="price ASC">Sort by price: low to high</option>
                    <option value="price DESC">Sort by price: high to low</option>
                </select>
            `);
            $sortOptions.append($sortSelect);
            $productsContent.prepend($sortOptions); // Prepend to product content area

            $sortSelect.on('change', function() {
                const [orderBy, order] = $(this).val().split(' ');
                currentOrderBy = orderBy;
                currentOrder = order;
                currentPage = 1; // Reset page on sort change
                applyFiltersAndSort();
            });
        }

        // Initial data fetching for filters and sorting
        $.when(
            fetchMinMaxPrice(function(min, max) {
                minPrice = min;
                maxPrice = max;
            }),
            fetchProductAttributes(function(attrs) {
                // Store attributes data globally if needed, or pass directly to UI init
                MWAI_Shop_Ajax.product_attributes = attrs; // Store for later use if needed
            })
        ).done(function() {
            initializeFiltersAndSortUI(minPrice, maxPrice, MWAI_Shop_Ajax.product_attributes);
            // After initializing UI, ensure products are fetched with initial filters/sort
            // This is already handled by the initial load logic below, but good to be explicit.
        });

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

        // --- Product Image Slideshow Logic ---
        function startSlideshow(productCard) {
            const $slideshowContainer = $(productCard).find('.mwai-product-image-slideshow');
            const imagesData = $slideshowContainer.data('images');
            if (!imagesData || imagesData.length <= 1) {
                return; // No slideshow needed if 0 or 1 image
            }

            let images = imagesData;
            let currentIndex = 0;
            
            // Ensure the first image is active initially if no active class is set
            let $currentImage = $slideshowContainer.find('.mwai-slideshow-image.active');
            if ($currentImage.length === 0) {
                $currentImage = $slideshowContainer.find('.mwai-slideshow-image').first().addClass('active');
            }
            currentIndex = $slideshowContainer.find('.mwai-slideshow-image').index($currentImage);


            function showNextImage() {
                // Remove active class from the current image
                $currentImage.removeClass('active');
                
                // Calculate the next index
                currentIndex = (currentIndex + 1) % images.length;
                
                // Get the next image and add the active class
                const $nextImage = $slideshowContainer.find(`.mwai-slideshow-image:eq(${currentIndex})`);
                $nextImage.addClass('active');
                
                // Update $currentImage to the new active image
                $currentImage = $nextImage;

                // Set the random interval for the next transition
                const randomInterval = Math.floor(Math.random() * (7000 - 3000 + 1)) + 3000;
                setTimeout(showNextImage, randomInterval);
            }

            // Start the slideshow after a random initial delay for staggered effect
            const initialDelay = Math.floor(Math.random() * 2000); // 0 to 2 seconds
            setTimeout(showNextImage, initialDelay);
        }

        // Initialize slideshows for all product cards after products are loaded
        function initializeSlideshows() {
            const $embeddedShop = $('.mwai-embedded-shop');
            const slideshowEnabledForEmbedded = $embeddedShop.length ? ($embeddedShop.data('slideshow-enabled') === true || $embeddedShop.data('slideshow-enabled') === 'true') : true;

            $('.mwai-shop-product-card').each(function() {
                const $productCard = $(this);
                const $slideshowContainer = $productCard.find('.mwai-product-image-slideshow');

                // Only start slideshow if it's enabled globally (for embedded shop) AND the slideshow container exists for this product
                if (slideshowEnabledForEmbedded && $slideshowContainer.length > 0) {
                    startSlideshow(this);
                }
            });
        }

        // Call initializeSlideshows after initial product load and subsequent loads
        $(document).ajaxStop(function() {
            // This will run after any AJAX request completes.
            // We need to ensure it only runs after product loading AJAX.
            // A more robust solution might involve custom events or checking the specific AJAX action.
            // For now, we'll re-initialize all slideshows.
            initializeSlideshows();
        });

        // Initial call on document ready for any products rendered directly by PHP
        initializeSlideshows();

        // Autoplay logic for product scrollers (similar to the one in product-shortcode.php)
        $('.mwai-shortcode-product-scroller').each(function() {
            const $currentScrollerWrapper = $(this);
            const $scroller = $currentScrollerWrapper.find('.mwai-product-scroller');
            const $leftButton = $currentScrollerWrapper.find('.mwai-scroll-button.left');
            const $rightButton = $currentScrollerWrapper.find('.mwai-scroll-button.right');
            const autoplayEnabled = $currentScrollerWrapper.data('autoplay-enabled');
            const scrollDirection = $currentScrollerWrapper.data('scroll-direction') || 'ltr'; // Default to ltr
            let scrollInterval;

            function updateScrollButtons() {
                if ($scroller[0].scrollWidth > $scroller[0].clientWidth) {
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
                    $leftButton.addClass('hidden');
                    $rightButton.addClass('hidden');
                }
            }

            function startAutoplay() {
                if (!autoplayEnabled) {
                    console.log('MWAI Autoplay: Autoplay is not enabled.');
                    return;
                }
                stopAutoplay(); // Clear any existing interval

                console.log(`MWAI Autoplay: Starting autoplay in ${scrollDirection} direction...`);

                // Initial position for RTL scrolling
                if (scrollDirection === 'rtl') {
                    $scroller.scrollLeft($scroller[0].scrollWidth - $scroller[0].clientWidth);
                }

                scrollInterval = setInterval(function() {
                    const scrollAmount = 1; // Pixels to scroll per interval
                    let currentScrollLeft = $scroller.scrollLeft();
                    const scrollerWidth = $scroller[0].scrollWidth;
                    const clientWidth = $scroller[0].clientWidth;
                    const maxScrollLeft = scrollerWidth - clientWidth;

                    if (maxScrollLeft <= 0) { // No need to scroll if content fits
                        console.log('MWAI Autoplay: Content fits, stopping autoplay.');
                        stopAutoplay();
                        return;
                    }

                    if (scrollDirection === 'ltr') {
                        if (currentScrollLeft >= maxScrollLeft) {
                            // If at the end, smoothly scroll back to the beginning
                            console.log('MWAI Autoplay: Reached end (LTR), resetting scroll to 0.');
                            stopAutoplay();
                            $scroller.stop(true, true).animate({ scrollLeft: 0 }, 800, function() {
                                updateScrollButtons();
                                startAutoplay();
                            });
                        } else {
                            $scroller.scrollLeft(currentScrollLeft + scrollAmount);
                            updateScrollButtons();
                        }
                    } else { // RTL direction
                        if (currentScrollLeft <= 0) {
                            // If at the beginning, smoothly scroll back to the end
                            console.log('MWAI Autoplay: Reached beginning (RTL), resetting scroll to max.');
                            stopAutoplay();
                            $scroller.stop(true, true).animate({ scrollLeft: maxScrollLeft }, 800, function() {
                                updateScrollButtons();
                                startAutoplay();
                            });
                        } else {
                            $scroller.scrollLeft(currentScrollLeft - scrollAmount);
                            updateScrollButtons();
                        }
                    }
                }, 20); // Adjust interval for speed
            }

            function stopAutoplay() {
                clearInterval(scrollInterval);
            }

            $scroller.on('scroll', updateScrollButtons);
            $(window).on('resize', function() {
                updateScrollButtons();
                startAutoplay(); // Restart autoplay on resize to adjust to new width
            });
            setTimeout(updateScrollButtons, 100); // Initial check

            // Ensure buttons are updated after all images are loaded
            $(window).on('load', updateScrollButtons);

            $leftButton.on('click', function() {
                stopAutoplay(); // Stop autoplay on manual interaction
                const scrollAmount = $scroller.width() * 0.7; // Scroll by 70% of scroller width
                $scroller.stop(true, true).animate({ scrollLeft: $scroller.scrollLeft() - scrollAmount }, 300, function() {
                    updateScrollButtons(); // Update buttons after animation completes
                });
            });

            $rightButton.on('click', function() {
                stopAutoplay(); // Stop autoplay on manual interaction
                const scrollAmount = $scroller.width() * 0.7; // Scroll by 70% of scroller width
                $scroller.stop(true, true).animate({ scrollLeft: $scroller.scrollLeft() + scrollAmount }, 300, function() {
                    updateScrollButtons(); // Update buttons after animation completes
                });
            });

            // Start autoplay if enabled
            startAutoplay();

            // Pause autoplay on hover
            $currentScrollerWrapper.hover(stopAutoplay, startAutoplay);
        });

    });
})(jQuery);

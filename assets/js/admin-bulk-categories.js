jQuery(document).ready(function($) {
    // Only proceed if on the product categories admin page
    if ($('body').hasClass('taxonomy-product_cat')) {
        console.log('MWAI: admin-bulk-categories.js loaded on product categories page.');

        // Add the "Bulk Add Categories" button
        const $addTagButton = $('#submit'); // The default "Add New Category" button
        if ($addTagButton.length) {
            const $bulkAddButton = $('<button type="button" id="mwai-bulk-add-categories-btn" class="button button-secondary mwai-bulk-add-categories-button">Bulk Add Categories</button>');
            $addTagButton.after($bulkAddButton);
            console.log('MWAI: Bulk Add Categories button added.');
        }

        // Create the modal HTML structure
        const $modalOverlay = $(`
            <div class="mwai-modal-overlay">
                <div class="mwai-modal-content">
                    <div class="mwai-modal-header">
                        <h2>Bulk Add Product Categories</h2>
                        <button type="button" class="mwai-modal-close">&times;</button>
                    </div>
                    <div class="mwai-modal-body">
                        <p>Enter categories and subcategories, one per line. Use hyphens (-) for indentation to define hierarchy.</p>
                        <p>Example:</p>
                        <pre>Category1
-SubCategory1
--SubSubCategoryA
-SubCategory2
Category2</pre>
                        <textarea id="mwai-bulk-category-textarea" placeholder="Enter your categories here..."></textarea>
                    </div>
                    <div class="mwai-modal-footer">
                        <span id="mwai-modal-status" class="mwai-modal-status"></span>
                        <button type="button" class="button button-secondary mwai-modal-close">Cancel</button>
                        <button type="button" id="mwai-bulk-submit-btn" class="button button-primary">Add Categories</button>
                    </div>
                </div>
            </div>
        `);
        $('body').append($modalOverlay);
        console.log('MWAI: Bulk Add Categories modal HTML appended.');

        const $bulkAddBtn = $('#mwai-bulk-add-categories-btn');
        const $modalCloseBtns = $('.mwai-modal-close');
        const $bulkSubmitBtn = $('#mwai-bulk-submit-btn');
        const $textarea = $('#mwai-bulk-category-textarea');
        const $modalStatus = $('#mwai-modal-status');

        // Open modal
        $bulkAddBtn.on('click', function() {
            $modalOverlay.addClass('active');
            $textarea.val(''); // Clear textarea on open
            $modalStatus.text(''); // Clear status message
            setTimeout(() => $textarea.focus(), 300); // Focus textarea after transition
        });

        // Close modal
        $modalCloseBtns.on('click', function() {
            $modalOverlay.removeClass('active');
        });

        // Submit categories
        $bulkSubmitBtn.on('click', function() {
            const categoryData = $textarea.val().trim();
            if (!categoryData) {
                $modalStatus.css('color', 'red').text('Please enter category data.');
                return;
            }

            $bulkSubmitBtn.prop('disabled', true).text('Adding...');
            $modalStatus.css('color', 'blue').text('Processing categories...');

            $.ajax({
                url: MWAI_Bulk_Categories_Ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mwai_bulk_add_categories',
                    category_data: categoryData,
                    nonce: MWAI_Bulk_Categories_Ajax.nonce
                },
                success: function(response) {
                    console.log('MWAI: Bulk Add Categories AJAX Success:', response);
                    if (response.success) {
                        $modalStatus.css('color', 'green').text(response.data.message);
                        // Reload the page to show new categories
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        $modalStatus.css('color', 'red').text('Error: ' + (response.data.message || 'An unknown error occurred.'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('MWAI: Bulk Add Categories AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                    $modalStatus.css('color', 'red').text('Network error: Unable to connect to server.');
                },
                complete: function() {
                    $bulkSubmitBtn.prop('disabled', false).text('Add Categories');
                }
            });
        });
    }
});

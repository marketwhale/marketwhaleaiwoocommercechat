jQuery(document).ready(function(jQuery) {
    const $ = jQuery; // Use $ safely within this scope
    const $tabs = $('.mwai-admin-tabs a');
    const $tabContents = $('.mwai-tab-content');

    // Function to show a specific tab
    function showTab(tabId) {
        $tabContents.addClass('hidden');
        $tabs.parent('li').removeClass('active');

        $(`[data-tab-id="${tabId}"]`).removeClass('hidden');
        $(`a[data-tab="${tabId}"]`).parent('li').addClass('active');
    }

    // Handle tab clicks
    $tabs.on('click', function(e) {
        e.preventDefault();
        const tabId = $(this).data('tab');
        showTab(tabId);

        // Update URL hash for direct linking to tabs
        window.location.hash = tabId;
    });

    // Check URL hash on page load to activate specific tab
    const initialTab = window.location.hash ? window.location.hash.substring(1) : $tabs.first().data('tab');
    showTab(initialTab);
});

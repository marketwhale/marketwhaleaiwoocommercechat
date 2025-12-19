jQuery(document).ready(function(jQuery) {
    const $ = jQuery; // Use $ safely within this scope
    const $tabs = $('.mwai-admin-tabs a');
    const $tabContents = $('.mwai-tab-content');
    const $subtabs = $('.mwai-admin-subtabs a.nav-tab-sub');
    const $subtabContents = $('.mwai-admin-subtab-content');

    // Function to show a specific main tab
    function showTab(tabId) {
        // Deactivate current active main tab content and link
        $tabContents.removeClass('active').addClass('hidden'); // Ensure hidden is applied
        $tabs.parent('li').removeClass('active');

        const $activeTabContent = $(`[data-tab-id="${tabId}"]`);
        // Activate the new main tab content and link
        $activeTabContent.removeClass('hidden').addClass('active'); // Remove hidden, then add active
        $(`a[data-tab="${tabId}"]`).parent('li').addClass('active');

        // If the active tab is 'shortcode-settings', activate its first subtab or the one from hash
        if (tabId === 'shortcode-settings') {
            // Use setTimeout to ensure the main tab transition starts before subtab transition
            setTimeout(() => {
                const initialSubtab = window.location.hash && $(`a[data-subtab="${window.location.hash.substring(1)}"]`).length ? window.location.hash.substring(1) : $subtabs.first().data('subtab');
                showSubtab(initialSubtab);
            }, 100); // Small delay to allow main tab content to be visible
        } else {
            // Hide all subtab contents if not in shortcode settings tab
            $subtabContents.removeClass('active').addClass('hidden'); // Ensure hidden is applied
            $subtabs.parent('li').removeClass('active');
        }
    }

    // Function to show a specific subtab
    function showSubtab(subtabId) {
        // Deactivate current active subtab content and link
        $subtabContents.removeClass('active').addClass('hidden'); // Ensure hidden is applied
        $subtabs.parent('li').removeClass('active');

        // Activate the new subtab content and link
        $(`[data-subtab-id="${subtabId}"]`).removeClass('hidden').addClass('active'); // Remove hidden, then add active
        $(`a[data-subtab="${subtabId}"]`).parent('li').addClass('active');
    }

    // Handle main tab clicks
    $tabs.on('click', function(e) {
        e.preventDefault();
        const tabId = $(this).data('tab');
        showTab(tabId);

        // Update URL hash for direct linking to tabs
        window.location.hash = tabId;
    });

    // Handle subtab clicks
    $subtabs.on('click', function(e) {
        e.preventDefault();
        const subtabId = $(this).data('subtab');
        showSubtab(subtabId);

        // Update URL hash for direct linking to subtabs
        window.location.hash = subtabId;
    });

    // Check URL hash on page load to activate specific tab/subtab
    const initialHash = window.location.hash ? window.location.hash.substring(1) : $tabs.first().data('tab');
    
    // Determine if the hash corresponds to a main tab or a subtab
    if ($(`a[data-tab="${initialHash}"]`).length) {
        showTab(initialHash);
    } else if ($(`a[data-subtab="${initialHash}"]`).length) {
        // If it's a subtab hash, first activate the parent main tab, then the subtab
        const parentTabId = 'shortcode-settings'; // Assuming all subtabs are under 'shortcode-settings'
        showTab(parentTabId);
        showSubtab(initialHash);
    } else {
        // If no hash or invalid, show the first main tab
        showTab($tabs.first().data('tab'));
    }
});

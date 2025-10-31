jQuery(document).ready(function($) {
    console.log('MWAI: admin-tabs.js loaded and executing.'); // Added for debugging
    $('.mwai-admin-tabs .nav-tab-wrapper a').on('click', function(e) {
        e.preventDefault();
        const $this = $(this);
        const targetTab = $this.attr('href');
        console.log('MWAI: Tab clicked:', targetTab); // Added for debugging

        // Deactivate all tabs and hide all content
        $this.closest('.nav-tab-wrapper').find('a').removeClass('nav-tab-active');
        $this.closest('.mwai-admin-tabs').find('.tab-content').addClass('hidden');

        // Activate clicked tab and show its content
        $this.addClass('nav-tab-active');
        $(targetTab).removeClass('hidden');

        // Store active tab in localStorage
        localStorage.setItem('mwai_admin_active_tab', targetTab);
    });

    // On page load, activate the stored tab or the first tab
    const activeTab = localStorage.getItem('mwai_admin_active_tab');
    if (activeTab && $(activeTab).length) {
        $(`.mwai-admin-tabs .nav-tab-wrapper a[href="${activeTab}"]`).trigger('click');
    } else {
        $('.mwai-admin-tabs .nav-tab-wrapper a').first().trigger('click');
    }
});

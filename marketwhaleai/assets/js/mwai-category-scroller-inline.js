jQuery(document).ready(function($){
    const $scrollerWrapper = $('.mwai-shortcode-category-scroller');
    $scrollerWrapper.each(function() { // Use .each to handle multiple instances
        const $currentScrollerWrapper = $(this);
        const $scroller = $currentScrollerWrapper.find('.mwai-category-scroller');
        const $leftButton = $currentScrollerWrapper.find('.mwai-scroll-button.left');
        const $rightButton = $currentScrollerWrapper.find('.mwai-scroll-button.right');

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

        $scroller.on('scroll', updateScrollButtons);
        $(window).on('resize', updateScrollButtons);
        setTimeout(updateScrollButtons, 100); // Initial check

        $leftButton.on('click', function() {
            $scroller.animate({ scrollLeft: $scroller.scrollLeft() - 200 }, 300);
        });

        $rightButton.on('click', function() {
            $scroller.animate({ scrollLeft: $scroller.scrollLeft() + 200 }, 300);
        });
    });
});

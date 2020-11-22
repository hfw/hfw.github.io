$(document).ready(function () {
    let activeLink; // declare for self-ref
    activeLink = function () {
        let uri = window.location.href.split('#')[0];
        $('a').each(function () {
            let a = this;
            let $a = $(a);
            // deactivate all
            $a.removeClass('active');
            // activate current uri (hashless) and current url (hashed)
            if (a.href === uri || a.href === window.location.href) {
                $a.addClass('active');
            }
        });
        // activate navtree link via ":hash" in class
        let hash = window.location.hash.split('#')[1];
        if (hash) {
            $('#nav-tree').find('a[class]').each(function () {
                let $navLink = $(this);
                $navLink.attr('class').split(/\s+/).forEach(function (cls) {
                    if (cls.split(':')[1] === hash) {
                        $navLink.addClass('active');
                    }
                })
            });
        }
    };
    // trigger on page load and change
    activeLink();
    let observer = new window.MutationObserver(activeLink);
    observer.observe(document, {subtree: true, childList: true});

    // make all external links open in new tabs
    $('a[href^="http"]').attr('target', '_blank');
});

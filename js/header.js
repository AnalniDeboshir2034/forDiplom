document.addEventListener('DOMContentLoaded', function () {
    var headerEl = document.querySelector('.header');
    var headerSpacer = document.querySelector('.header-spacer');
    var burgerBtn = document.getElementById('headerBurgerBtn');
    var nav = document.getElementById('siteMainNav');
    var menuOverlay = document.getElementById('headerMenuOverlay');
    var navCloseBtn = document.getElementById('headerNavClose');
    var catalogToggle = document.getElementById('headerCatalogToggle');
    var catalogDropdown = document.getElementById('headerCatalogDropdown');
    var mobileSearchBtn = document.getElementById('headerSearchMobileBtn');
    var searchModal = document.getElementById('headerSearchModal');
    var searchModalClose = document.getElementById('headerSearchModalClose');
    var searchModalForm = document.getElementById('headerSearchModalForm');
    var searchModalInput = document.getElementById('headerSearchModalInput');
    var searchForm = document.getElementById('headerSearchForm');
    var searchInput = document.getElementById('headerSearchInput');
    var modal = document.getElementById('headerLeadModal');
    var openBtn = document.querySelector('[data-lead-open]');
    var openModalLinks = document.querySelectorAll('.open-modal-form');
    var closeBtn = document.querySelector('[data-lead-close]');
    var form = document.getElementById('headerLeadForm');
    var status = document.getElementById('headerLeadStatus');

    function syncHeaderSpacerHeight() {
        if (!headerEl || !headerSpacer) return;
        headerSpacer.style.height = headerEl.offsetHeight + 'px';
    }
    syncHeaderSpacerHeight();
    window.addEventListener('resize', syncHeaderSpacerHeight);

    function closeMenu() {
        if (!nav || !burgerBtn) return;
        nav.classList.remove('is-open');
        if (menuOverlay) {
            menuOverlay.classList.remove('is-active');
            menuOverlay.setAttribute('aria-hidden', 'true');
        }
        burgerBtn.setAttribute('aria-expanded', 'false');
    }

    if (burgerBtn && nav) {
        burgerBtn.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('is-open');
            if (menuOverlay) {
                menuOverlay.classList.toggle('is-active', isOpen);
                menuOverlay.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            }
            burgerBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            nav.querySelectorAll('.nav__item').forEach(function (item, idx) {
                item.style.transitionDelay = isOpen ? ((idx * 55) + 'ms') : '0ms';
            });
        });

        if (menuOverlay) menuOverlay.addEventListener('click', closeMenu);
        if (navCloseBtn) navCloseBtn.addEventListener('click', closeMenu);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeMenu();
        });
    }

    function goSearch(value) {
        var q = (value || '').trim();
        var url = '/catalog';
        if (q) url += '?q=' + encodeURIComponent(q);
        window.location.href = url;
    }

    if (searchForm && searchInput) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            goSearch(searchInput.value);
        });
    }

    function openSearchModal() {
        if (!searchModal) return;
        searchModal.classList.add('is-active');
        searchModal.setAttribute('aria-hidden', 'false');
        if (searchModalInput) {
            setTimeout(function () { searchModalInput.focus(); }, 30);
        }
    }

    function closeSearchModal() {
        if (!searchModal) return;
        searchModal.classList.remove('is-active');
        searchModal.setAttribute('aria-hidden', 'true');
    }

    if (mobileSearchBtn) mobileSearchBtn.addEventListener('click', openSearchModal);
    if (searchModalClose) searchModalClose.addEventListener('click', closeSearchModal);
    if (searchModal) {
        searchModal.addEventListener('click', function (event) {
            if (event.target === searchModal) closeSearchModal();
        });
    }
    if (searchModalForm) {
        searchModalForm.addEventListener('submit', function (e) {
            e.preventDefault();
            goSearch(searchModalInput ? searchModalInput.value : '');
        });
    }

    if (catalogToggle && catalogDropdown) {
        catalogToggle.addEventListener('click', function (e) {
            if (!window.matchMedia('(max-width: 768px)').matches) return;
            e.preventDefault();
            var isOpen = catalogDropdown.classList.toggle('is-open');
            catalogToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.nav-catalog')) {
                catalogDropdown.classList.remove('is-open');
                catalogToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function openModal() {
        if (!modal) return;
        modal.classList.add('is-active');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-active');
        modal.setAttribute('aria-hidden', 'true');
    }

    function setStatus(message, isError) {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('is-error', !!isError);
        status.classList.toggle('is-success', !isError);
    }

    if (!modal || !closeBtn || !form || !status) return;

    if (openBtn) {
        openBtn.addEventListener('click', openModal);
    }

    if (openModalLinks.length) {
        openModalLinks.forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                openModal();
            });
        });
    }
    closeBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-active')) {
            closeModal();
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        setStatus('Отправляем заявку...', false);

        var submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        fetch('includes/bitrix_form.php', {
            method: 'POST',
            body: new FormData(form)
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data && data.success) {
                setStatus(data.message || 'Заявка отправлена! Мы свяжемся с вами.', false);
                form.reset();
                setTimeout(closeModal, 1200);
            } else {
                setStatus((data && data.message) || 'Ошибка отправки. Попробуйте позже.', true);
            }
        })
        .catch(function () {
            setStatus('Ошибка сети. Проверьте подключение и попробуйте снова.', true);
        })
        .finally(function () {
            submitBtn.disabled = false;
        });
    });
});

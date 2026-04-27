document.addEventListener('DOMContentLoaded', function() {
    const productPage = document.querySelector('.product-page');
    const productLeft = document.querySelector('.product-left');
    const productRight = document.querySelector('.product-right');
    const productGallery = document.querySelector('.product-gallery');
    const productTabs = document.querySelector('.product-tabs');
    const productBadge = document.querySelector('.product-right .product-badge');
    const productTitle = document.querySelector('.product-right .product-title');
    const productSpecs = document.querySelector('.product-right .product-specs');
    const productActions = document.querySelector('.product-right .product-actions');
    const badgePlaceholder = document.createComment('badge-placeholder');
    const titlePlaceholder = document.createComment('title-placeholder');
    const specsPlaceholder = document.createComment('specs-placeholder');
    const actionsPlaceholder = document.createComment('actions-placeholder');

    function rearrangeProductLayout() {
        if (!productLeft || !productRight || !productGallery || !productTabs || !productSpecs || !productActions) return;
        if (window.matchMedia('(max-width: 992px)').matches) {
            if (productBadge && !productLeft.contains(productBadge)) {
                productRight.insertBefore(badgePlaceholder, productBadge);
                productLeft.insertBefore(productBadge, productGallery);
            }
            if (productTitle && !productLeft.contains(productTitle)) {
                productRight.insertBefore(titlePlaceholder, productTitle);
                productLeft.insertBefore(productTitle, productGallery);
            }
            if (!productLeft.contains(productSpecs)) {
                productRight.insertBefore(specsPlaceholder, productSpecs);
                productLeft.insertBefore(productSpecs, productTabs);
            }
            if (!productLeft.contains(productActions)) {
                productRight.insertBefore(actionsPlaceholder, productActions);
                productLeft.insertBefore(productActions, productTabs);
            }
        } else {
            if (productBadge && badgePlaceholder.parentNode) badgePlaceholder.parentNode.insertBefore(productBadge, badgePlaceholder);
            if (productTitle && titlePlaceholder.parentNode) titlePlaceholder.parentNode.insertBefore(productTitle, titlePlaceholder);
            if (productSpecs && specsPlaceholder.parentNode) specsPlaceholder.parentNode.insertBefore(productSpecs, specsPlaceholder);
            if (productActions && actionsPlaceholder.parentNode) actionsPlaceholder.parentNode.insertBefore(productActions, actionsPlaceholder);
        }
    }

    rearrangeProductLayout();
    window.addEventListener('resize', rearrangeProductLayout);

    // ===== Галерея =====
    const slides = document.querySelectorAll('.gallery-slide');
    const thumbs = document.querySelectorAll('.thumb');
    const prevBtn = document.getElementById('galleryPrev');
    const nextBtn = document.getElementById('galleryNext');
    
    let currentIndex = 0;
    const totalSlides = slides.length;
    
    function showSlide(index) {
        if (index < 0) index = 0;
        if (index >= totalSlides) index = totalSlides - 1;
        
        currentIndex = index;
        
        slides.forEach(slide => {
            slide.style.display = 'none';
        });
        
        if (slides[currentIndex]) {
            slides[currentIndex].style.display = 'flex';
        }
        
        thumbs.forEach((thumb, i) => {
            if (i === currentIndex) {
                thumb.classList.add('active');
                thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } else {
                thumb.classList.remove('active');
            }
        });
    }
    
    if (thumbs.length > 0) {
        thumbs.forEach((thumb, index) => {
            thumb.addEventListener('click', () => {
                showSlide(index);
            });
        });
    }
    
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            showSlide(currentIndex - 1);
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            showSlide(currentIndex + 1);
        });
    }
    
    // Свайп для мобильных
    let touchStartX = 0;
    let touchEndX = 0;
    const galleryMain = document.querySelector('.gallery-main');
    
    if (galleryMain) {
        galleryMain.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });
        
        galleryMain.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            const diff = touchEndX - touchStartX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) {
                    showSlide(currentIndex - 1);
                } else {
                    showSlide(currentIndex + 1);
                }
            }
        });
    }
    
    // Показываем первый слайд
    if (slides.length > 0) {
        showSlide(0);
    }
    
    // ===== Табы =====
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.dataset.tab;
            
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            btn.classList.add('active');
            const activePane = document.getElementById(`tab-${tabId}`);
            if (activePane) {
                activePane.classList.add('active');
            }
        });
    });
});
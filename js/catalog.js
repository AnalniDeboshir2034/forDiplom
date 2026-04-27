// catalog.js
document.addEventListener('DOMContentLoaded', function() {
    // Фильтрация товаров
    const categoryBtns = document.querySelectorAll('.category-btn');
    const filterCheckboxes = document.querySelectorAll('.filter-submenu input[type="checkbox"]');
    const resetBtn = document.getElementById('reset-filters');
    const productsGrid = document.getElementById('products-grid');
    const productsCount = document.getElementById('products-count');
    const filterToggleBtn = document.getElementById('catalogFilterToggle');
    const filtersPanel = document.getElementById('catalogFiltersPanel');
    const filtersCloseBtn = document.getElementById('catalogFiltersClose');
    const filtersOverlay = document.getElementById('catalogFiltersOverlay');
    
    let activeCategory = 'all';
    let activeSubcategories = new Set();
    const queryParam = new URLSearchParams(window.location.search).get('q');
    const hashMatch = String(window.location.hash || '').match(/cat=([^&]+)/i);
    const categoryParam = hashMatch ? decodeURIComponent(hashMatch[1]).trim().toLowerCase() : '';
    const searchQuery = queryParam ? queryParam.trim().toLowerCase() : '';
    
    // Функция обновления счетчика товаров
    function updateProductsCount() {
        const visibleProducts = document.querySelectorAll('.product-card:not(.hidden)');
        productsCount.textContent = `Найдено: ${visibleProducts.length} товаров`;
    }
    
    function setActiveCategoryButton(category) {
        categoryBtns.forEach(btn => {
            const btnCategory = String(btn.dataset.category || '').trim().toLowerCase();
            btn.classList.toggle('active', btnCategory === category);
        });
    }

    function recalculateActiveSubcategories() {
        activeSubcategories.clear();
        filterCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                activeSubcategories.add(String(checkbox.value || '').trim().toLowerCase());
            }
        });
    }

    // Функция фильтрации товаров
    function filterProducts() {
        const products = document.querySelectorAll('.product-card');
        
        products.forEach(product => {
            const productCategory = String(product.dataset.category || '').trim().toLowerCase();
            const productSubcategory = String(product.dataset.subcategory || '').trim().toLowerCase();
            const titleEl = product.querySelector('.product-title');
            const productName = titleEl ? titleEl.textContent.trim().toLowerCase() : '';
            
            let categoryMatch = false;
            let subcategoryMatch = false;
            let searchMatch = true;
            
            // При активных подфильтрах разрешаем пересечение разных категорий.
            if (activeSubcategories.size > 0 || activeCategory === 'all') {
                categoryMatch = true;
            } else {
                categoryMatch = (productCategory === String(activeCategory).trim().toLowerCase());
            }
            
            if (activeSubcategories.size === 0) {
                subcategoryMatch = true;
            } else {
                subcategoryMatch = activeSubcategories.has(productSubcategory);
            }

            if (searchQuery) {
                searchMatch = productName.indexOf(searchQuery) !== -1;
            }
            
            if (categoryMatch && subcategoryMatch && searchMatch) {
                product.classList.remove('hidden');
            } else {
                product.classList.add('hidden');
            }
        });
        
        updateProductsCount();
    }
    
    // Обработчики для кнопок основных категорий
    categoryBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            categoryBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            activeCategory = String(this.dataset.category || '').trim().toLowerCase();
            
            // Если выбрана категория, сбрасываем фильтры подкатегорий
            if (activeCategory !== 'all') {
                // Снимаем все галочки с чекбоксов
                filterCheckboxes.forEach(checkbox => {
                    if (checkbox.dataset.category !== activeCategory) {
                        checkbox.checked = false;
                    }
                });
                
                // Обновляем активные подкатегории
                recalculateActiveSubcategories();
            }
            
            filterProducts();
        });
    });
    
    // Обработчики для чекбоксов подкатегорий
    filterCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            recalculateActiveSubcategories();

            // Если выбраны подфильтры из нескольких категорий, категория становится "Все".
            const selectedCategories = new Set();
            filterCheckboxes.forEach(cb => {
                if (cb.checked) {
                    selectedCategories.add(String(cb.dataset.category || '').trim().toLowerCase());
                }
            });

            if (selectedCategories.size > 1) {
                activeCategory = 'all';
                setActiveCategoryButton('all');
            } else if (selectedCategories.size === 1) {
                const [singleCategory] = selectedCategories;
                activeCategory = singleCategory || 'all';
                setActiveCategoryButton(activeCategory);
            }
            
            filterProducts();
        });
    });
    
    // Сброс всех фильтров
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            // Сбрасываем основную категорию
            activeCategory = 'all';
            categoryBtns.forEach(btn => {
                if (btn.dataset.category === 'all') {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            // Сбрасываем все чекбоксы
            filterCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Очищаем активные подкатегории
            activeSubcategories.clear();
            
            // Применяем фильтрацию
            filterProducts();
        });
    }
    
    // Dropdown меню для фильтров (мобильная версия)
    const dropdownBtns = document.querySelectorAll('.filter-dropdown');
    const filterSubmenus = document.querySelectorAll('.filter-submenu');

    function getSubmenuByFilterId(filterId) {
        for (const submenu of filterSubmenus) {
            if (submenu.dataset.submenu === filterId) {
                return submenu;
            }
        }
        return null;
    }
    
    dropdownBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const filterId = this.dataset.filter;
            const submenu = getSubmenuByFilterId(filterId);
            const filterGroup = this.closest('.filter-group');
            
            if (submenu && filterGroup) {
                filterGroup.classList.toggle('open');
                this.classList.toggle('active', filterGroup.classList.contains('open'));
                
                // Меняем стрелку
                const arrow = this.querySelector('.filter-arrow');
                if (arrow) {
                    arrow.textContent = filterGroup.classList.contains('open') ? '▲' : '▼';
                }
            }
        });
    });
    
    // Закрытие dropdown при клике вне
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.filter-group')) {
            dropdownBtns.forEach(btn => {
                const filterId = btn.dataset.filter;
                const submenu = getSubmenuByFilterId(filterId);
                const filterGroup = btn.closest('.filter-group');
                if (submenu && filterGroup && filterGroup.classList.contains('open')) {
                    filterGroup.classList.remove('open');
                    btn.classList.remove('active');
                    const arrow = btn.querySelector('.filter-arrow');
                    if (arrow) arrow.textContent = '▼';
                }
            });
        }
    });
    
    // Инициализация - показываем все товары
    if (categoryParam) {
        activeCategory = categoryParam;
        categoryBtns.forEach(btn => {
            btn.classList.toggle('active', String(btn.dataset.category || '').trim().toLowerCase() === activeCategory);
        });
    }
    filterProducts();

    function closeFiltersPanel() {
        if (!filtersPanel) return;
        filtersPanel.classList.remove('is-open');
        if (filtersOverlay) filtersOverlay.classList.remove('is-active');
        if (filterToggleBtn) filterToggleBtn.setAttribute('aria-expanded', 'false');
    }

    if (filterToggleBtn && filtersPanel) {
        filterToggleBtn.addEventListener('click', function () {
            const isOpen = filtersPanel.classList.toggle('is-open');
            if (filtersOverlay) filtersOverlay.classList.toggle('is-active', isOpen);
            filterToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen) {
                filtersPanel.querySelectorAll('.filter-group').forEach(function (group, idx) {
                    group.style.animationDelay = (idx * 70) + 'ms';
                });
            }
        });
    }
    if (filtersCloseBtn) filtersCloseBtn.addEventListener('click', closeFiltersPanel);
    if (filtersOverlay) filtersOverlay.addEventListener('click', closeFiltersPanel);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeFiltersPanel();
    });

    // "Подробнее" как button
    document.querySelectorAll('.product-btn--details').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var href = btn.getAttribute('data-href');
            if (href) window.location.href = href;
        });
    });
});
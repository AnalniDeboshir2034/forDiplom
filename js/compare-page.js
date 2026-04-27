document.addEventListener('DOMContentLoaded', function () {
    var allProducts = Array.isArray(window.ALL_PRODUCTS_FOR_COMPARE) ? window.ALL_PRODUCTS_FOR_COMPARE : [];
    var emptyBlock = document.getElementById('compare-empty');
    var tableWrap = document.getElementById('compare-table-wrap');
    var table = document.getElementById('compare-table');
    var clearBtn = document.getElementById('compare-clear');

    var fields = [
        { key: 'd_dosing', label: 'Диапазон дозирования' },
        { key: 'performance', label: 'Производительность' },
        { key: 'pressure', label: 'Рабочее давление' },
        { key: 'temperature', label: 'Температура жидкости' },
        { key: 'connections', label: 'Тип подключения' },
        { key: 'm_seal', label: 'Материал уплотнений' },
        { key: 'm_case', label: 'Материал корпуса' },
        { key: 'dop', label: 'Дополнительно' },
        { key: 'series', label: 'Серия' }
    ];

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getSelectedProducts() {
        var ids = (window.CompareStorage && window.CompareStorage.readIds)
            ? window.CompareStorage.readIds()
            : [];
        return ids
            .map(function (id) {
                return allProducts.find(function (product) {
                    return String(product.id) === String(id);
                });
            })
            .filter(Boolean);
    }

    function render() {
        var selected = getSelectedProducts();

        if (!selected.length) {
            emptyBlock.style.display = 'block';
            tableWrap.style.display = 'none';
            table.innerHTML = '';
            return;
        }

        emptyBlock.style.display = 'none';
        tableWrap.style.display = 'block';

        var html = '<tbody>';

        html += '<tr><th class="compare-label">Характеристики</th>';
        selected.forEach(function (product) {
            var base = window.APP_BASE || '';
            var href = base + '/product.php?slug=' + encodeURIComponent(product.slug || '');
            html += '<th>';
            html += '<div class="compare-product-card">';
            if (product.image) {
                html += '<a href="' + escapeHtml(href) + '"><img src="' + escapeHtml(product.image) + '" alt="' + escapeHtml(product.name) + '"></a>';
            }
            html += '<div class="compare-product-name"><a href="' + escapeHtml(href) + '">' + escapeHtml(product.name) + '</a></div>';
            html += '<div class="compare-product-actions">';
            html += '<button type="button" class="btn compare-remove-btn" data-compare-remove="' + escapeHtml(product.id) + '">Удалить</button>';
            html += '<button type="button" class="btn btn-secondary" data-cart-add data-cart-id="' + escapeHtml(product.id) + '">В корзину</button>';
            html += '<a class="btn btn-secondary" href="' + escapeHtml(href) + '">Подробнее</a>';
            html += '</div></div></th>';
        });
        html += '</tr>';

        fields.forEach(function (field) {
            html += '<tr>';
            html += '<td class="compare-label">' + field.label + '</td>';
            selected.forEach(function (product) {
                html += '<td>' + escapeHtml(product[field.key] || '-') + '</td>';
            });
            html += '</tr>';
        });

        html += '</tbody>';
        table.innerHTML = html;
    }

    document.addEventListener('click', function (event) {
        var removeBtn = event.target.closest('[data-compare-remove]');
        if (!removeBtn) return;

        var id = String(removeBtn.getAttribute('data-compare-remove'));
        var ids = window.CompareStorage.readIds().filter(function (item) {
            return String(item) !== id;
        });
        window.CompareStorage.saveIds(ids);
        window.CompareStorage.syncButtons();
        window.CompareStorage.updateCounter();
        render();
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            window.CompareStorage.saveIds([]);
            window.CompareStorage.syncButtons();
            window.CompareStorage.updateCounter();
            render();
        });
    }

    render();
});

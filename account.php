<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/site_settings.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/auth_lib.php';
$siteSettings = load_site_settings();

$user = app_current_user($mysqli);
if (!$user) {
    header('Location: ' . app_url('login.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <base href="<?= htmlspecialchars(app_url(''), ENT_QUOTES, 'UTF-8') ?>" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php seo_render_meta([
        'title' => 'Личный кабинет | Medikator.ru',
        'description' => 'Профиль и история заказов.',
        'canonical' => seo_canonical_url('/account'),
        'robots' => 'noindex,follow',
    ]); ?>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .cab{display:grid;grid-template-columns:320px 1fr;gap:14px}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px}
        .cab-nav a{display:block;padding:10px 12px;border-radius:10px;text-decoration:none;color:#111827;font-weight:700}
        .cab-nav a.active{background:#eef2ff;color:#1f5cff}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        input,textarea,select{width:100%;padding:10px 12px;border:1px solid #dbe1ea;border-radius:10px}
        .msg{margin-top:10px;font-size:14px}
        .msg.is-error{color:#b00020}
        .msg.is-success{color:#075f33}
        table{width:100%;border-collapse:collapse}
        td,th{border:1px solid #edf0f5;padding:8px;text-align:left;vertical-align:top}
        th{background:#f8fafc}
        @media(max-width:900px){.cab{grid-template-columns:1fr}}
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<main class="main">
    <div class="container">
        <h1>Личный кабинет</h1>
        <div class="cab">
            <div class="card cab-nav">
                <a href="#profile" class="active" data-tab="profile">Профиль</a>
                <a href="#orders" data-tab="orders">Заказы</a>
                <a href="<?= htmlspecialchars(app_url('includes/auth_logout.php'), ENT_QUOTES, 'UTF-8') ?>">Выйти</a>
                <div class="muted" style="margin-top:10px;">Вы вошли как: <?= htmlspecialchars($user['login'] ?? '') ?></div>
            </div>
            <div class="card">
                <div id="tab-profile">
                    <h2>Профиль</h2>
                    <form id="profileForm">
                        <div class="row">
                            <div>
                                <label class="muted">Имя профиля</label>
                                <input type="text" name="name" value="<?= htmlspecialchars((string)($user['name'] ?? '')) ?>">
                            </div>
                            <div>
                                <label class="muted">E-mail</label>
                                <input type="email" name="email" value="<?= htmlspecialchars((string)($user['email'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="row" style="margin-top:12px;">
                            <div>
                                <label class="muted">УНП (для юр. лиц)</label>
                                <input type="text" name="unp" value="<?= htmlspecialchars((string)($user['unp'] ?? '')) ?>">
                            </div>
                            <div>
                                <label class="muted">Новый пароль</label>
                                <input type="password" name="new_password" placeholder="Оставьте пустым, если не меняете">
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit" style="margin-top:12px;">Сохранить</button>
                        <p id="profileMsg" class="msg" aria-live="polite"></p>
                    </form>
                </div>

                <div id="tab-orders" style="display:none;">
                    <h2>Мои заказы</h2>
                    <div id="ordersBlock" class="muted">Загружаем...</div>
                    <div id="orderDetails" style="margin-top:12px; display:none;"></div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
function setTab(name){
  document.querySelectorAll('.cab-nav a[data-tab]').forEach(function(a){a.classList.toggle('active', a.getAttribute('data-tab')===name);});
  document.getElementById('tab-profile').style.display = name==='profile' ? 'block':'none';
  document.getElementById('tab-orders').style.display = name==='orders' ? 'block':'none';
  if (name==='orders') loadOrders();
}
document.querySelectorAll('.cab-nav a[data-tab]').forEach(function(a){
  a.addEventListener('click', function(e){
    e.preventDefault();
    setTab(a.getAttribute('data-tab'));
  });
});

document.getElementById('profileForm').addEventListener('submit', function(e){
  e.preventDefault();
  var msg = document.getElementById('profileMsg');
  msg.textContent = 'Сохраняем...';
  msg.className = 'msg';
  var fd = new FormData(e.target);
  fetch('<?= htmlspecialchars(app_url('includes/api_profile_update.php'), ENT_QUOTES, 'UTF-8') ?>', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data && data.success){
        msg.textContent = data.message || 'OK';
        msg.classList.add('is-success');
      } else {
        msg.textContent = (data && data.message) || 'Ошибка';
        msg.classList.add('is-error');
      }
    })
    .catch(function(){ msg.textContent='Ошибка сети'; msg.classList.add('is-error'); });
});

var ordersLoaded = false;
function loadOrders(){
  if (ordersLoaded) return;
  ordersLoaded = true;
  var block = document.getElementById('ordersBlock');
  fetch('<?= htmlspecialchars(app_url('includes/api_orders_list.php'), ENT_QUOTES, 'UTF-8') ?>')
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (!data || !data.success){ block.textContent = (data && data.message) || 'Ошибка'; return; }
      var orders = Array.isArray(data.orders) ? data.orders : [];
      if (!orders.length){ block.textContent = 'Заказов пока нет.'; return; }
      var html = '<table><thead><tr><th>ID</th><th>Статус</th><th>Сумма</th><th>Дата</th><th></th></tr></thead><tbody>';
      orders.forEach(function(o){
        html += '<tr>'
          + '<td>#'+esc(o.id)+'</td>'
          + '<td>'+esc(o.status)+'</td>'
          + '<td>'+esc(o.total)+'</td>'
          + '<td>'+esc(o.created_at || '')+'</td>'
          + '<td><button class="btn btn-secondary" type="button" data-order-open="'+esc(o.id)+'">Детали</button></td>'
          + '</tr>';
      });
      html += '</tbody></table>';
      block.innerHTML = html;
    })
    .catch(function(){ block.textContent = 'Ошибка сети'; });
}

document.addEventListener('click', function(e){
  var btn = e.target.closest('[data-order-open]');
  if (!btn) return;
  var id = btn.getAttribute('data-order-open');
  var details = document.getElementById('orderDetails');
  details.style.display = 'block';
  details.innerHTML = '<div class="muted">Загружаем детали...</div>';
  fetch('<?= htmlspecialchars(app_url('includes/api_order_details.php'), ENT_QUOTES, 'UTF-8') ?>' + '?order_id=' + encodeURIComponent(id))
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (!data || !data.success){ details.innerHTML = '<div class="msg is-error">'+esc((data&&data.message)||'Ошибка')+'</div>'; return; }
      var o = data.order || {};
      var items = Array.isArray(data.items) ? data.items : [];
      var html = '<div class="card" style="padding:12px; border-radius:12px; border:1px solid #e5e7eb;">'
        + '<div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">'
        + '<strong>Заказ #'+esc(o.id)+'</strong>'
        + '<span class="muted">Статус: '+esc(o.status)+'</span>'
        + '</div>'
        + '<div class="muted" style="margin-top:6px;">Итого: '+esc(o.total)+'</div>'
        + '<h3 style="margin:10px 0 6px;">Состав</h3>';
      if (!items.length){
        html += '<div class="muted">Нет позиций</div>';
      } else {
        html += '<table><thead><tr><th>Товар</th><th>Кол-во</th><th>Цена</th><th>Сумма</th></tr></thead><tbody>';
        items.forEach(function(it){
          html += '<tr>'
            + '<td>'+esc(it.product_name_snapshot)+'</td>'
            + '<td>'+esc(it.qty)+'</td>'
            + '<td>'+esc(it.unit_price_snapshot)+'</td>'
            + '<td>'+esc(it.line_total)+'</td>'
            + '</tr>';
        });
        html += '</tbody></table>';
      }
      if (String(o.status) === 'completed') {
        html += '<h3 style="margin:12px 0 6px;">Отзыв</h3>'
          + '<form data-review-form>'
          + '<input type="hidden" name="order_id" value="'+esc(o.id)+'">'
          + '<label class="muted">Оценка</label>'
          + '<select name="rating"><option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option></select>'
          + '<label class="muted" style="display:block;margin-top:8px;">Текст</label>'
          + '<textarea name="text" rows="4" placeholder="Ваш отзыв" required></textarea>'
          + '<button class="btn btn-primary" type="submit" style="margin-top:8px;">Отправить отзыв</button>'
          + '<div class="msg" data-review-msg aria-live="polite"></div>'
          + '</form>';
      } else {
        html += '<div class="muted" style="margin-top:10px;">Отзыв можно оставить после статуса completed.</div>';
      }
      html += '</div>';
      details.innerHTML = html;
    })
    .catch(function(){ details.innerHTML = '<div class="msg is-error">Ошибка сети</div>'; });
});

document.addEventListener('submit', function(e){
  var form = e.target.closest('form[data-review-form]');
  if (!form) return;
  e.preventDefault();
  var msg = form.querySelector('[data-review-msg]');
  msg.textContent = 'Отправляем...';
  msg.className = 'msg';
  var fd = new FormData(form);
  fetch('<?= htmlspecialchars(app_url('includes/api_review_create.php'), ENT_QUOTES, 'UTF-8') ?>', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data && data.success){
        msg.textContent = data.message || 'OK';
        msg.classList.add('is-success');
      } else {
        msg.textContent = (data && data.message) || 'Ошибка';
        msg.classList.add('is-error');
      }
    })
    .catch(function(){ msg.textContent='Ошибка сети'; msg.classList.add('is-error'); });
});

if (location.hash === '#orders') setTab('orders');
</script>
</body>
</html>


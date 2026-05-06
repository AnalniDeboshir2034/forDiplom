<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/site_settings.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/auth_lib.php';
$siteSettings = load_site_settings();

$user = app_current_user($mysqli);
if ($user) {
    header('Location: ' . app_url('account.php'));
    exit;
}

$token = trim((string)($_GET['token'] ?? ''));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <base href="<?= htmlspecialchars(app_url(''), ENT_QUOTES, 'UTF-8') ?>" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php seo_render_meta([
        'title' => 'Смена пароля | Medikator.ru',
        'description' => 'Смена пароля по ссылке.',
        'canonical' => seo_canonical_url('/reset'),
        'robots' => 'noindex,follow',
    ]); ?>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .auth-wrap{max-width:460px;margin:30px auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
        .auth-wrap h1{margin:0 0 10px}
        .auth-wrap input{width:100%;padding:10px 12px;margin:8px 0;border:1px solid #dbe1ea;border-radius:10px}
        .auth-wrap button{width:100%}
        .auth-msg{margin-top:10px;font-size:14px}
        .auth-msg.is-error{color:#b00020}
        .auth-msg.is-success{color:#075f33}
        .auth-links{display:flex;justify-content:space-between;gap:10px;margin-top:10px;font-size:14px}
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<main class="main">
    <div class="container">
        <div class="auth-wrap">
            <h1>Смена пароля</h1>
            <?php if ($token === ''): ?>
                <p class="auth-msg is-error">Ссылка некорректна: нет токена.</p>
                <div class="auth-links"><a href="<?= htmlspecialchars(app_url('forgot.php'), ENT_QUOTES, 'UTF-8') ?>">Запросить заново</a><span></span></div>
            <?php else: ?>
                <form id="resetForm">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="password" name="password" placeholder="Новый пароль (мин. 8 символов)" required>
                    <button class="btn btn-primary" type="submit">Сохранить пароль</button>
                </form>
                <div class="auth-links">
                    <a href="<?= htmlspecialchars(app_url('login.php'), ENT_QUOTES, 'UTF-8') ?>">Ко входу</a>
                    <span></span>
                </div>
                <p id="resetMsg" class="auth-msg" aria-live="polite"></p>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
function parseApiResponse(response){
  return response.text().then(function(text){
    var data = null;
    try { data = JSON.parse(text); } catch(e) {}
    if (!data || typeof data !== 'object') {
      data = {
        success: false,
        message: text ? text.replace(/<[^>]*>/g, '').trim().slice(0, 250) : 'Сервер вернул некорректный ответ'
      };
    }
    data.__ok = response.ok;
    return data;
  });
}

var f = document.getElementById('resetForm');
if (f){
  f.addEventListener('submit', function(e){
    e.preventDefault();
    var msg = document.getElementById('resetMsg');
    msg.textContent = 'Сохраняем...';
    msg.className = 'auth-msg';
    var fd = new FormData(e.target);
    fetch('<?= htmlspecialchars(app_url('includes/auth_reset.php'), ENT_QUOTES, 'UTF-8') ?>', { method:'POST', body: fd })
      .then(parseApiResponse)
      .then(function(data){
        if (data && data.success){
          msg.textContent = data.message || 'OK';
          msg.classList.add('is-success');
          setTimeout(function(){ window.location.href = '<?= htmlspecialchars(app_url('login.php'), ENT_QUOTES, 'UTF-8') ?>'; }, 500);
        } else {
          msg.textContent = (data && data.message) || (data && data.__ok === false ? 'Ошибка сервера' : 'Ошибка');
          msg.classList.add('is-error');
        }
      })
      .catch(function(){
        msg.textContent = 'Ошибка сети';
        msg.classList.add('is-error');
      });
  });
}
</script>
</body>
</html>


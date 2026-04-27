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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <base href="<?= htmlspecialchars(app_url(''), ENT_QUOTES, 'UTF-8') ?>" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php seo_render_meta([
        'title' => 'Регистрация | Medikator.ru',
        'description' => 'Регистрация личного кабинета.',
        'canonical' => seo_canonical_url('/register'),
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
            <h1>Регистрация</h1>
            <form id="registerForm">
                <input type="text" name="name" placeholder="Имя профиля (необязательно)">
                <input type="email" name="email" placeholder="E-mail" required>
                <input type="password" name="password" placeholder="Пароль (мин. 8 символов)" required>
                <button class="btn btn-primary" type="submit">Создать аккаунт</button>
            </form>
            <div class="auth-links">
                <a href="<?= htmlspecialchars(app_url('login.php'), ENT_QUOTES, 'UTF-8') ?>">Уже есть аккаунт?</a>
                <span></span>
            </div>
            <p id="registerMsg" class="auth-msg" aria-live="polite"></p>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
document.getElementById('registerForm').addEventListener('submit', function(e){
  e.preventDefault();
  var msg = document.getElementById('registerMsg');
  msg.textContent = 'Создаём аккаунт...';
  msg.className = 'auth-msg';
  var fd = new FormData(e.target);
  fetch('<?= htmlspecialchars(app_url('includes/auth_register.php'), ENT_QUOTES, 'UTF-8') ?>', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data && data.success){
        msg.textContent = data.message || 'OK';
        msg.classList.add('is-success');
        setTimeout(function(){ window.location.href = '<?= htmlspecialchars(app_url('account.php'), ENT_QUOTES, 'UTF-8') ?>'; }, 300);
      } else {
        msg.textContent = (data && data.message) || 'Ошибка';
        msg.classList.add('is-error');
      }
    })
    .catch(function(){
      msg.textContent = 'Ошибка сети';
      msg.classList.add('is-error');
    });
});
</script>
</body>
</html>


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
        .field-hint{display:block;margin:-4px 0 8px;font-size:12px;color:#6b7280}
        .auth-wrap select{width:100%;padding:10px 12px;margin:8px 0;border:1px solid #dbe1ea;border-radius:10px;background:#fff}
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<main class="main">
    <div class="container">
        <div class="auth-wrap">
            <h1>Регистрация</h1>
            <form id="registerForm">
                <select name="account_type" id="accountType" required>
                    <option value="individual" selected>Физическое лицо</option>
                    <option value="legal">Юридическое лицо</option>
                </select>
                <input type="text" name="name" placeholder="Имя профиля">
                <input type="tel" name="phone" placeholder="Номер телефона">
                <input type="email" name="email" placeholder="E-mail" required>
                <div id="legalFields" style="display:none;">
                    <input type="text" name="company_name" placeholder="Название компании" autocomplete="organization">
                    <input type="text" name="representative_name" placeholder="ФИО представителя" autocomplete="name">
                    <input type="text" name="unp" id="unpInput" placeholder="УНП (9 цифр)" inputmode="numeric" maxlength="9" pattern="\d{9}" title="УНП — 9 цифр">
                    <small class="field-hint">УНП юрлица в РБ — ровно 9 цифр, без пробелов</small>
                    <input type="text" name="address" placeholder="Юридический адрес компании" autocomplete="street-address">
                </div>
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
<script src="<?= htmlspecialchars(app_url('js/legal-fields.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script>
var accountType = document.getElementById('accountType');
var legalFields = document.getElementById('legalFields');
function digitsOnly(value){ return String(value || '').replace(/\D/g, ''); }
function syncLegalFields(){
  var isLegal = accountType && accountType.value === 'legal';
  legalFields.style.display = isLegal ? 'block' : 'none';
  legalFields.querySelectorAll('input').forEach(function(i){ i.required = isLegal; });
  var phoneInput = document.querySelector('#registerForm input[name="phone"]');
  if (phoneInput) phoneInput.required = isLegal;
}
if (accountType) {
  accountType.addEventListener('change', syncLegalFields);
  syncLegalFields();
}

document.getElementById('registerForm').addEventListener('submit', function(e){
  e.preventDefault();
  var msg = document.getElementById('registerMsg');
  var form = e.target;
  var isLegal = accountType && accountType.value === 'legal';
  var phoneInput = form.querySelector('input[name="phone"]');
  var unpInput = form.querySelector('input[name="unp"]');

  if (isLegal) {
    if (unpInput) {
      unpInput.value = digitsOnly(unpInput.value).slice(0, 9);
      if (digitsOnly(unpInput.value).length !== 9) {
        msg.textContent = 'УНП должен содержать ровно 9 цифр';
        msg.className = 'auth-msg is-error';
        unpInput.focus();
        return;
      }
    }
    if (phoneInput) {
      phoneInput.dispatchEvent(new Event('blur', { bubbles: true }));
      if (!phoneInput.checkValidity()) {
        msg.textContent = phoneInput.validationMessage || 'Введите корректный номер телефона';
        msg.className = 'auth-msg is-error';
        phoneInput.reportValidity();
        return;
      }
    }
  }

  msg.textContent = 'Создаём аккаунт...';
  msg.className = 'auth-msg';
  var fd = new FormData(form);
  if (isLegal && unpInput) {
    fd.set('unp', digitsOnly(unpInput.value));
  }
  fetch('<?= htmlspecialchars(app_url('includes/auth_register.php'), ENT_QUOTES, 'UTF-8') ?>', { method:'POST', body: fd })
    .then(function(r){
      return r.text().then(function(text){
        var data = null;
        try { data = JSON.parse(text); } catch (e) {}
        if (!data) {
          data = {
            success: false,
            message: text ? text.replace(/<[^>]*>/g, '').trim().slice(0, 250) : 'Сервер вернул некорректный ответ'
          };
        }
        data.__ok = r.ok;
        return data;
      });
    })
    .then(function(data){
      if (data && data.success){
        msg.textContent = data.message || 'OK';
        msg.classList.add('is-success');
        var nextUrl = new URLSearchParams(window.location.search).get('redirect');
        var safeNext = (nextUrl && nextUrl.charAt(0) === '/' && nextUrl.indexOf('//') !== 0)
          ? nextUrl
          : '<?= htmlspecialchars(app_url('account.php'), ENT_QUOTES, 'UTF-8') ?>';
        setTimeout(function(){ window.location.href = safeNext; }, 300);
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
</script>
</body>
</html>


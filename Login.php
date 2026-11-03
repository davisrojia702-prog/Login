<?php

require __DIR__ . '/db.php';
session_start();

if (!empty($_SESSION['user'])) { header('Location: home.php'); exit; }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$tab = ($_GET['tab'] ?? 'login') === 'register' ? 'register' : 'login';
$errors = [];
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $tab   = ($_POST['action'] ?? '') === 'register' ? 'register' : 'login';
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');
        $old['email'] = $email;

        if ($tab === 'register') {
            $name = trim((string)($_POST['name'] ?? ''));
            $old['name'] = $name;
            if (mb_strlen($name) < 2 || mb_strlen($name) > 30) $errors[] = 'Name must be 2 to 30 characters.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))      $errors[] = 'Enter a valid email address.';
            if (strlen($pass) < 8)                                $errors[] = 'Password must be at least 8 characters.';
            if ($pass !== (string)($_POST['confirm'] ?? ''))      $errors[] = 'Passwords do not match.';

            if (!$errors) {
                $st = db()->prepare('SELECT 1 FROM users WHERE email = ?');
                $st->execute([$email]);
                if ($st->fetch()) {
                    $errors[] = 'That email is already registered. Try logging in.';
                } else {
                    db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
                        ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                    session_regenerate_id(true);
                    $_SESSION['user'] = ['id' => (int)db()->lastInsertId(), 'name' => $name];
                    header('Location: home.php'); exit;
                }
            }
        } else {
            $_SESSION['fails'] = array_filter($_SESSION['fails'] ?? [], fn($t) => $t > time() - 300);
            if (count($_SESSION['fails']) >= 5) {
                $errors[] = 'Too many attempts. Please wait a few minutes.';
            } else {
                $st = db()->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
                $st->execute([$email]);
                $u = $st->fetch();
                if ($u && password_verify($pass, $u['password_hash'])) {
                    $_SESSION['fails'] = [];
                    session_regenerate_id(true);
                    $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name']];
                    header('Location: home.php'); exit;
                }
                $_SESSION['fails'][] = time();
                $errors[] = 'Incorrect email or password.';
            }
        }
    }
}
?>







<!doctype html>
<html lang="en">

<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>
    <?= $tab === 'register' ? 'Register' : 'Log in' ?>MemcheckJA
</title>

<link rel="stylesheet" href="auth.css?v=<?= filemtime(__DIR__ . '/auth.css') ?>">
</head>

<body>
<main class="card">
    <div style="text-align: center;">
    <div class="brand">Memcheck<span>JA</span>
    </div>

    <p class="tag">
    <?= $tab === 'register' ? 'Create your account' : 'Login or Create an account with us to get started' ?>
    </p>

    <?php if ($errors): ?>
    <div class="errors" role="alert">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li>
                    <?= h($e) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
  <?php endif; 
  ?>

   <div class="tabs" role="tablist">
     <a href="?tab=login" class="<?= $tab === 'login' ? 'on' : '' ?>">Log in</a>
     <a href="?tab=register" class="<?= $tab === 'register' ? 'on' : '' ?>">Register</a>
   </div>

  <form method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <input type="hidden" name="action" value="<?= $tab ?>">

    <?php if ($tab === 'register'): ?>
    <label for="name">Full name</label>
    <input id="name" name="name" type="text" autocomplete="name" maxlength="30" value="<?= h($old['name']) ?>" required>
    <?php endif; ?>

    <label for="email"> Create Email</label>
    <input id="email" name="email" type="email" autocomplete="email" value="<?= h($old['email']) ?>" required>

    <label for="password"> Create Password</label>

    <div class="pw">
      <input id="password" name="password" type="password" autocomplete="<?= $tab === 'register' ? 'new-password' : 'current-password' ?>" required>
      <button type="button" class="show" data-target="password">Show</button>
    </div>

    <?php if ($tab === 'register'): ?>
    <label for="confirm">Confirm password</label>

    <input id="confirm" name="confirm" type="password" autocomplete="new-password" required>
    <p class="hint">At least 8 characters.</p>
    <?php endif; ?>

    <button type="submit" class="submit">
        <?= $tab === 'register' ? 'Create account' : 'Log in' ?></button>
  </form>

  <p class="switch">
    <?php if ($tab === 'register'): ?>Already have an account? <a href="?tab=login">Log in</a>
    <?php else: ?>lets get started by <a href="?tab=register">Create an account</a>
    <?php endif; ?>
  </p>
</main>


<script>
document.querySelectorAll('.show').forEach(b => b.addEventListener('click', () => {
  const i = document.getElementById(b.dataset.target);
  const show = i.type === 'password';
  i.type = show ? 'text' : 'password';
  b.textContent = show ? 'Hide' : 'Show';
}));
</script>

</body>
</html>

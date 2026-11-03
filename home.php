<?php

session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: login.php'); exit; }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$levels = require __DIR__ . '/levels.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pick = (string)($_POST['level'] ?? '');
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Your session expired. Please try again.';
    } elseif (!isset($levels[$pick])) {
        $error = 'Please choose a level.';
    } else {
        $_SESSION['level'] = $pick;
        header('Location: play.php'); exit;
    }
}
$selected = $_SESSION['level'] ?? 'easy';
?>

<!doctype html>
<html lang="en">

<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Dashboard - MemCheckJa</title>

<link rel="stylesheet" href="home.css?v=<?= filemtime(__DIR__ . '/home.css') ?>">
</head>

<body>
<div class="wrap">
  
  <nav>
    <span class="word">MemCheck<b>Ja</b></span>
    <div class="links">

      <span class="who"><?= h($_SESSION['user']['name']) ?></span>

      <a href="#how-to-play">How to play</a>
      <a href="home.php?logout=1">Log out</a>
    </div>
  </nav>

  <header class="hero">
    <div style="text-align: center;">
      <h1>Welcome to <span><br>MemCheckJa</span></h1>
    </div>
  </header>

  <section aria-labelledby="pick">
    <h2 id="pick">Choose your level to get started</h2>
    <p>Choose a difficulty level to start playing! Each level has a different challenge to test your memory. 
      Start with an easy level or try a harder one if you're feeling confident.
      Pay attention, remember what you see, and have fun while playing. Good luck! 
    </p>

    <?php if ($error): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <div class="levels">
        <?php foreach ($levels as $key => $lv): ?>
        <div class="lvl">
          <input type="radio" name="level" id="lv-<?= $key ?>" value="<?= $key ?>" <?= $selected === $key ? 'checked' : '' ?>>
          <label for="lv-<?= $key ?>">
            <strong><?= h($lv['label']) ?></strong>
            <span class="secs"><?= (int)$lv['seconds'] ?>s</span>
            <small><?= (int)$lv['cards'] ?> cards &middot; <?= h($lv['note']) ?></small>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="btn">Start Game <span aria-hidden="true"></span></button>
    </form>
  </section>
  

  <section id="how-to-play">
    <h2>How to play</h2>
    <div class="steps">
      <article>
        <h3>Rules</h3>
        <p>Cards are shuffled and placed face down. 
          Flip two cards at a time to find matching pairs. 
          If they match, they stay face up. If not, both flip back, so pay attention and remember where each card is. 
          Match every pair before the timer runs out to win. Fewer moves and more time left means a better result. 
          The game trains your memory, concentration and observation skills.
        </p>
      </article>
    </div>
  </section>
</div>

</body>
</html>
<?php

require __DIR__ . '/db.php';
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

$levels = require __DIR__ . '/levels.php';
$keys   = array_keys($levels);

if (isset($_GET['level']) && isset($levels[$_GET['level']])) {  
    $_SESSION['level'] = $_GET['level'];
    header('Location: play.php'); exit;
}
$key = $_SESSION['level'] ?? '';
if (!isset($levels[$key])) { header('Location: home.php'); exit; }

if (isset($_GET['save'])) {                                      
    header('Content-Type: application/json');
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        db()->prepare('INSERT INTO results (user_id, level, won, moves, seconds_left) VALUES (?,?,?,?,?)')
            ->execute([$_SESSION['user']['id'], $key, !empty($d['won']) ? 1 : 0, (int)($d['moves'] ?? 0), (int)($d['left'] ?? 0)]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { echo json_encode(['ok' => false]); }
    exit;
}

$lv   = $levels[$key];
$i    = array_search($key, $keys);
$next = $keys[$i + 1] ?? null;
$cfg  = ['label' => $lv['label'], 'cards' => $lv['cards'], 'seconds' => $lv['seconds'], 'next' => $next, 'nextLabel' => $next ? $levels[$next]['label'] : null];
?>

<!doctype html>
<html lang="en">
  
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Play - MemCheckJa</title>
<link rel="stylesheet" href="game_2.css?v=<?= filemtime('game_2.css') ?>">
</head>
<body>
<div class="wrap">
  <nav>
    <span class="word">MemCheck<b>Ja</b></span>
    <span><a href="home.php">Change level</a><a href="home.php?logout=1">Log out</a></span>
  </nav>

  <div class="top">
    <h1>Level: <span><?= htmlspecialchars($lv['label']) ?></span></h1>
    <div class="stats" id="stats"></div>
  </div>

  <p class="err" id="status" style="color:#fed100!important">Loading game...</p>
  <div class="board" id="board"></div>

  <div class="row center" id="controls" hidden>
    <button class="btn-ghost" id="pauseBtn" type="button">Pause</button>
    <button class="btn-ghost" id="restartBtn" type="button">Restart</button>
  </div>

  <section class="panel" id="intro">
    <h2>Ready?</h2>
    <p><?= (int)$lv['cards'] ?> cards, <?= (int)$lv['seconds'] ?> seconds. The timer starts when you press Start.</p>
    <div class="row"><button class="btn" id="startBtn" type="button">Start</button></div>
  </section>

  <section class="panel" id="pause" hidden>
    <h2>Game paused</h2>
    <p>Time remaining: <b id="pleft"></b> seconds</p>
    <div class="row"><button class="btn" id="resumeBtn" type="button">Resume</button></div>
  </section>

  <section class="panel" id="end" hidden>
    <h2 id="endTitle"></h2>
    <p id="endText"></p>
    <div class="row" id="endRow"></div>
  </section>
</div>

<script>
var CFG = <?= json_encode($cfg) ?>;
var SYMS = ['\u{1F34B}','\u{1F347}','\u{1F352}','\u{1F95D}','\u{1F349}','\u{1F351}','\u{1F34D}','\u{1F965}','\u{1F336}\u{FE0F}','\u{1F955}'];
function $(id) { return document.getElementById(id); }

try {
  var deck = [], first = null, lock = false, running = false, paused = false;
  var moves = 0, pairs = 0, left = CFG.seconds, deadline = 0, timer = null;
  var total = CFG.cards / 2;

  function shuffle(a) {
    for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; }
    return a;
  }
  function showStats() {
    $('stats').innerHTML = 'Time<b class="' + (left <= 10 ? 'low' : '') + '">' + left + 's</b>'
      + 'Moves<b>' + moves + '</b>Matched<b>' + pairs + '/' + total + '</b>';
  }
  function card(i) { return $('board').children[i]; }

  function build() {
    var b = $('board');
    b.style.setProperty('--cols', CFG.cards <= 4 ? 2 : CFG.cards <= 16 ? 4 : 5);
    b.style.setProperty('--maxw', CFG.cards <= 4 ? '260px' : '520px');
    b.innerHTML = '';
    deck.forEach(function (sym, i) {
      var c = document.createElement('button');
      c.type = 'button'; c.className = 'card'; c.setAttribute('aria-label', 'Card ' + (i + 1) + ', face down');
      c.innerHTML = '<span class="inner"><span class="back">?</span><span class="face"></span></span>';
      c.querySelector('.face').textContent = sym;
      c.addEventListener('click', function () { flip(i); });
      b.appendChild(c);
    });
  }

  function start() {
    clearInterval(timer);
    $('intro').hidden = true; $('pause').hidden = true; $('end').hidden = true;
    $('controls').hidden = false; $('board').classList.remove('hidden');
    var syms = SYMS.slice(0, total);
    deck = shuffle(syms.concat(syms));
    first = null; lock = false; paused = false; running = true;
    moves = 0; pairs = 0; left = CFG.seconds;
    build(); showStats();
    deadline = Date.now() + left * 1000;
    timer = setInterval(tick, 250);
  }
  function tick() {
    if (paused) return;
    left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    showStats();
    if (left === 0) finish(false);
  }
  function flip(i) {
    if (!running || paused || lock) return;
    var c = card(i);
    if (c.classList.contains('up') || c.classList.contains('done')) return;
    c.classList.add('up');
    if (first === null) { first = i; return; }
    var a = first; first = null; moves++; lock = true;
    if (deck[a] === deck[i]) {
      [a, i].forEach(function (k) { card(k).classList.remove('up'); card(k).classList.add('done'); });
      pairs++; lock = false; showStats();
      if (pairs === total) finish(true);
    } else {
      [a, i].forEach(function (k) { card(k).classList.add('miss'); });
      showStats();
      setTimeout(function () {
        [a, i].forEach(function (k) { card(k).classList.remove('up'); card(k).classList.remove('miss'); });
        lock = false;
      }, 800);
    }
  }
  function setPause(on) {
    if (!running) return;
    paused = on;
    if (on) { left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000)); $('pleft').textContent = left; }
    else { deadline = Date.now() + left * 1000; }
    $('pause').hidden = !on; $('controls').hidden = on;
    $('board').classList.toggle('hidden', on);
  }
  function finish(won) {
    running = false; clearInterval(timer); showStats();
    $('controls').hidden = true; $('end').hidden = false;
    $('endTitle').textContent = won ? 'Level Complete!' : "Time's up";
    $('endText').textContent = won
      ? 'Great job! You matched every pair in ' + moves + ' moves with ' + left + ' seconds left.'
      : 'You matched ' + pairs + ' of ' + total + ' pairs in ' + moves + ' moves.';
    var row = $('endRow'); row.innerHTML = '';
    function add(label, primary, fn, href) {
      var e = document.createElement(href ? 'a' : 'button');
      e.textContent = label; e.className = primary ? 'btn' : 'btn-ghost';
      if (href) { e.href = href; } else { e.type = 'button'; e.addEventListener('click', fn); }
      row.appendChild(e);
    }
    if (won && CFG.next) add('Next level: ' + CFG.nextLabel, true, null, 'play.php?level=' + CFG.next);
    add(won ? 'Play again' : 'Try again', !(won && CFG.next), start);
    add('Change level', false, null, 'home.php');
    try {
      fetch('play.php?save=1', {method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({won: won, moves: moves, left: left})}).catch(function () {});
    } catch (e) {}
  }

  $('startBtn').addEventListener('click', start);
  $('restartBtn').addEventListener('click', start);
  $('pauseBtn').addEventListener('click', function () { setPause(true); });
  $('resumeBtn').addEventListener('click', function () { setPause(false); });
  showStats();
  $('status').hidden = true;
} catch (e) {
  $('status').textContent = 'Script error: ' + e.message;
}
</script>
</body>
</html>
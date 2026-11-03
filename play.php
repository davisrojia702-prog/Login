<?php
require __DIR__ . '/db.php';
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

$levels = require __DIR__ . '/levels.php';
$keys   = array_keys($levels);

if (isset($_GET['level']) && isset($levels[$_GET['level']])) {   // used by "Next level"
    $_SESSION['level'] = $_GET['level'];
    header('Location: play.php'); exit;
}
$key = $_SESSION['level'] ?? '';
if (!isset($levels[$key])) { header('Location: home.php'); exit; }

if (isset($_GET['save'])) {                                       // save a finished game (optional)
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
$cfg  = ['label' => $lv['label'], 'cards' => $lv['cards'], 'seconds' => $lv['seconds'],
         'next' => $next, 'nextLabel' => $next ? $levels[$next]['label'] : null];
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Play - MemCheckJa</title>
<style>
:root{--black:#0b0b0b;--panel:#161616;--line:#333;--green:#009b3a;--gold:#fed100;--white:#fff;--muted:#bdbdbd;--bad:#ff7b6b}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;color:var(--white);background:var(--black);font:16px/1.5 "Segoe UI","Helvetica Neue",system-ui,sans-serif}
body::before{content:"";position:fixed;inset:0 0 auto;height:6px;background:linear-gradient(90deg,var(--green) 0 33.3%,var(--gold) 33.3% 66.6%,var(--black) 66.6%)}
.wrap{max-width:720px;margin:0 auto;padding:26px 18px 56px}
nav{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:28px}
.word{font:900 1.2rem "Arial Black",sans-serif}.word b{color:var(--gold)}
nav a{color:var(--muted);text-decoration:none;font-weight:600;margin-left:18px}nav a:hover{color:var(--white)}
.top{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:8px 24px;margin-bottom:18px}
h1{font:900 clamp(1.8rem,6vw,2.6rem)/1.1 "Arial Black",sans-serif;margin:0}
h1 span{color:var(--gold)}
h2{font:900 1.4rem "Arial Black",sans-serif;margin:0 0 6px}
.stats{color:var(--muted)}.stats b{color:var(--gold);margin:0 16px 0 4px;font-variant-numeric:tabular-nums}.stats b.low{color:var(--bad)}

.board{display:grid;gap:10px;grid-template-columns:repeat(var(--cols,4),1fr);max-width:var(--maxw,520px);margin:0 auto}
.board.hidden{visibility:hidden}
.card{aspect-ratio:3/4;border:0;padding:0;background:none;position:relative;cursor:pointer;perspective:700px}
.card:focus-visible{outline:3px solid var(--white);outline-offset:3px}
.inner{position:absolute;inset:0;transition:transform .35s;transform-style:preserve-3d}
.card.up .inner,.card.done .inner{transform:rotateY(180deg)}
.back,.face{position:absolute;inset:0;border-radius:14px;backface-visibility:hidden;-webkit-backface-visibility:hidden;display:grid;place-items:center}
.back{background:linear-gradient(145deg,var(--green),#006b27);border:2px solid #38c56a;box-shadow:0 5px 0 #004d1c;color:var(--gold);font:900 1.6rem "Arial Black",sans-serif}
.card:hover .back{border-color:var(--gold)}
.face{background:var(--white);transform:rotateY(180deg);font-size:clamp(1.8rem,8vw,2.8rem);border:3px solid var(--line)}
.card.done .face{background:var(--gold);border-color:var(--gold)}
.card.miss .face{border-color:var(--bad)}

.panel{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:22px;margin:24px auto 0;max-width:520px}
.panel p{margin:0;color:var(--muted)}
.row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.row.center{justify-content:center;max-width:520px;margin:18px auto 0}
.btn,.btn-ghost{display:inline-block;font:inherit;font-weight:800;cursor:pointer;border-radius:999px;text-decoration:none;padding:13px 28px}
.btn{border:0;background:var(--green);color:var(--white);box-shadow:0 5px 0 #006b27}
.btn:active{transform:translateY(2px);box-shadow:0 3px 0 #006b27}
.btn-ghost{border:2px solid var(--line);background:var(--black);color:var(--white);padding:11px 22px}
.btn-ghost:hover{border-color:var(--gold);color:var(--gold)}
.btn:focus-visible,.btn-ghost:focus-visible{outline:3px solid var(--white);outline-offset:3px}
.err{color:var(--bad)!important;margin-top:14px!important;word-break:break-word}
[hidden]{display:none!important}
@media (prefers-reduced-motion:reduce){.inner{transition:none}}


</style>
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
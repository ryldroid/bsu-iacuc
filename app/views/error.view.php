<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?= htmlspecialchars($title ?? 'Error', ENT_QUOTES, 'UTF-8') ?></title>

  <script>
    (function() {
      var mode = localStorage.getItem('theme') || 'auto';
      var systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      var resolvedTheme = (mode === 'light' || mode === 'dark') ? mode : (systemPrefersDark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', resolvedTheme);
      document.documentElement.setAttribute('data-theme-mode', mode);
    })();
  </script>

  <link rel="stylesheet" href="<?= asset_css('body.css') ?>">
  <link rel="stylesheet" href="<?= asset_css('404.css') ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Akt:wght@100..900&family=Alfa+Slab+One&display=swap" rel="stylesheet">
</head>

<body>
  <main>
    <div>
      <h1><?= htmlspecialchars($heading ?? 'Error', ENT_QUOTES, 'UTF-8') ?></h1>
      <?php foreach ((array) ($lines ?? []) as $line): ?>
        <p><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
      <?php foreach ((array) ($actions ?? [['label' => '← Go Back Home', 'href' => ROOT . '/home']]) as $action): ?>
        <a href="<?= htmlspecialchars($action['href'], ENT_QUOTES, 'UTF-8') ?>" class="button">
          <?= htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </main>
</body>

</html>
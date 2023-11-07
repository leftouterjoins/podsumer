<!DOCTYPE html>
<html lang="<?= $LANGUAGE ?>">
<head>
    <title><?= $PAGE_TITLE ?: 'Podsumer' ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style type="text/css">
        body {font-family: sans !important;}
    </style>
</head>
<body class="bg-neutral-900 text-neutral-100 font-sans">
    <div class="container mx-auto p-10">
        <h1 class="text-m font-black text-right">
            <a href="/">Feeds</a>
            &nbsp;|&nbsp;
            <a href="/opml">OPML</a>
            &nbsp;|&nbsp;
           <?= round($db_size/1024/1024/1024, 2) ?> GB
        </h1>
        <? include($BODY) ?>
    </div>
    <div class="text-center py-8 text-m">
        <p>
            Thank you for listening with <a href="https://github.com/joshwbrick/podsumer">Podsumer</a>.
        </p>
    </div>
</body>
</html>


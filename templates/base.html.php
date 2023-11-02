<!DOCTYPE html>
<html lang="<?= $LANGUAGE ?>">
<head>
    <title><?= $PAGE_TITLE ?: 'Podsumer' ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-neutral-900 text-neutral-100 font-sans">
    <div class="container mx-auto p-10">
        <h1 class="text-xl font-black text-right">
            <a href="/">Home</a>
            &nbsp;|&nbsp;
            <a href="/opml">Download OPML</a>
            &nbsp;|&nbsp;
           <?= round($db_size/1024/1024, 2) ?>MB used
        </h1>
        <? include($BODY) ?>
    </div>
    <div class="text-center py-8 text-m">
        <p>
            Thank you for listening with <i>Podsumer</i> an open source app made by Josh Brickner
        </p>
    </div>
</body>
</html>


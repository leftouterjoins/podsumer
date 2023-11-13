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
        <h1 class="text-m font-black text-right">
            <a href="/">Feeds</a>
            &nbsp;|&nbsp;
            <a href="/opml">OPML</a>
            &nbsp;|&nbsp;
           <?= round($db_size/1024/1024/1024, 2) ?> GB
        </h1>
        <? include($BODY) ?>
    </div>
    <div class="text-center py-8 text-s text-neutral-500">
        <p>

            Thank You for Listening With Podsumer
            <br>
            <span class="text-xs">
                If you find this open source project of value please consider
                <a target="_blank" rel="noreferrer" href="https://github.com/sponsors/joshwbrick" class="text-green-700 underline">sponsoring</a>
                or <a target="_blank" rel="noreferrer" href="https://github.com/joshwbrick/podsumer" class="text-amber-700 underline">contributing to</a>
                further development.
            </span>

        </p>
        <p class="text-xs">
            <br>
            Released under the MIT License &ndash; Database version: <?= $this->main->getState()->getVersion(); ?>
        </p>
    </div>
</body>
</html>

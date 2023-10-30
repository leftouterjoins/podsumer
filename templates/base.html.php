<!DOCTYPE html>
<html lang="<?= $LANGUAGE ?>">
<head>
    <title><?= $PAGE_TITLE ?: 'Podsumer' ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black text-neutral-100 font-mono">
    <div class="container mx-auto p-10">
        <h1 class="text-3xl font-black">
            <a href="/">Podsumer</a>
        </h1>

        <? include($BODY) ?>
</div>
</body>
</html>


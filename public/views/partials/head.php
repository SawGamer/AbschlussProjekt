<?php
// Shared <head> content for the panels. $base and $title are set by the including page.
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= htmlspecialchars($base) ?>" />
    <title><?= htmlspecialchars($title) ?> | WorkForce</title>
    <script>
        // Apply the saved theme before the first paint.
        try {
            const theme = localStorage.getItem('wf-theme');
            if (theme) document.documentElement.dataset.theme = theme;
        } catch (e) { /* storage may be blocked */ }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Semi+Condensed:wght@500;600&display=swap">
    <link rel="stylesheet" href="views/styling.css">

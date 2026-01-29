<?php
function renderPage($title, $content, $options = []) {
    $currentPage = $options['currentPage'] ?? $_SERVER["REQUEST_URI"] ?? "";
    $pageTitle = $options['pageTitle'] ?? $title . " - MO Freelancing";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($pageTitle) ?></title>
        <link rel="stylesheet" href="<?= CSS_URL ?>/main.css">
        <?php if (isset($options['extraHead'])): ?>
            <?= $options['extraHead'] ?>
        <?php endif; ?>
    </head>
    <body>
        <?php includeTemplate('header'); ?>
        
        <div class="page-container">
            <?php 
            $current_page = $currentPage;
            includeTemplate('nav'); 
            ?>
            
            <main class="main-content">
                <div class="container">
                    <?php renderFlashMessages(); ?>
                    <?= $content ?>
                </div>
            </main>
        </div>
        
        <?php includeTemplate('footer'); ?>
    </body>
    </html>
    <?php
}


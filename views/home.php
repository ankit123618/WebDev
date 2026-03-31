<?php
declare(strict_types=1);

/** Home Page */
namespace views;
use function helpers\e;
?>
<html>
    <head>
        <title><?php echo e($title); ?> - project-template</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <h3>Welcome Page of the App <?php echo e($title); ?></h3>
    </body>
    <script src="/assets/js/script.js"></script>
</html>

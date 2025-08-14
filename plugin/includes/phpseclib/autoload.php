<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'phpseclib3\\') === 0) {
        $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen('phpseclib3\\'))) . '.php';
        if (file_exists($path)) {
            require $path;
        }
    }
});

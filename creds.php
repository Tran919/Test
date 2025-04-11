<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function findConfigFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if (strtolower($file->getFilename()) === 'config.php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

function parseDbCredentials($file) {
    $content = file_get_contents($file);
    $creds = [];

    $patterns = [
        'DB_DRIVER'   => "/define\(['\"]DB_DRIVER['\"],\s*['\"](.*?)['\"]\)/",
        'DB_HOSTNAME' => "/define\(['\"]DB_HOSTNAME['\"],\s*['\"](.*?)['\"]\)/",
        'DB_USERNAME' => "/define\(['\"]DB_USERNAME['\"],\s*['\"](.*?)['\"]\)/",
        'DB_PASSWORD' => "/define\(['\"]DB_PASSWORD['\"],\s*['\"](.*?)['\"]\)/",
        'DB_DATABASE' => "/define\(['\"]DB_DATABASE['\"],\s*['\"](.*?)['\"]\)/",
        'DB_PORT'     => "/define\(['\"]DB_PORT['\"],\s*['\"](.*?)['\"]\)/",
    ];

    foreach ($patterns as $key => $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            $creds[$key] = $matches[1];
        }
    }

    return $creds;
}

function getTables($creds) {
    $host = $creds['DB_HOSTNAME'] ? $creds['DB_HOSTNAME'] : 'localhost';
    $user = $creds['DB_USERNAME'] ? $creds['DB_USERNAME'] : 'root';
    $pass = $creds['DB_PASSWORD'] ? $creds['DB_PASSWORD'] : '';
    $db   = $creds['DB_DATABASE'] ? $creds['DB_DATABASE'] : '';
    $port = $creds['DB_PORT']     ?$creds['DB_PORT'] : 3306;


    $mysqli = @new mysqli($host, $user, $pass, $db, (int)$port);
    if ($mysqli->connect_error) {
        return ['error' => $mysqli->connect_error];
    }

    $tables = [];
    $result = $mysqli->query("SHOW TABLES");
    if ($result) {
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
    } else {
        $tables[] = 'Ошибка запроса SHOW TABLES';
    }

    $mysqli->close();
    return $tables;
}

// === HTML HEADER ===
echo "<!DOCTYPE html><html lang='ru'><head><meta charset='UTF-8'><title>DB Inspector</title>";
echo "<style>body{font-family:monospace;background:#f4f4f4;padding:20px}h2{color:#333}ul{margin-bottom:30px}li{margin:3px 0}</style>";
echo "</head><body><h1>🔍 Поиск config.php и таблиц в БД</h1>";

$searchPath = '/home/siteme'; // ← Корневая директория с сайтами
$configs = findConfigFiles($searchPath);

if (empty($configs)) {
    echo "<p>⚠️ Конфиги не найдены в <code>$searchPath</code></p>";
    exit;
}

foreach ($configs as $file) {
    echo "<h2>📄 $file</h2>";
    $creds = parseDbCredentials($file);
    if (count($creds) < 5) {
        echo "<p>❌ Недостаточно данных для подключения</p>";
        continue;
    }

    $tables = getTables($creds);
    if (isset($tables['error'])) {
        echo "<p>❌ Ошибка подключения: {$tables['error']}</p>";
    } else {
        echo "<ul>";
        foreach ($tables as $t) {
            echo "<li>🗂️ $t</li>";
        }
        echo "</ul>";
    }
}

echo "</body></html>";

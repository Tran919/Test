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

function inspectTargetTables($mysqli, $targetTables) {
    $results = [];

    foreach ($targetTables as $table) {
        $tableInfo = ['columns' => [], 'count' => 0, 'exists' => false];

        // Проверим, существует ли таблица
        $check = $mysqli->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            $tableInfo['exists'] = true;

            // Получить колонки
            $cols = $mysqli->query("SHOW COLUMNS FROM `$table`");
            if ($cols) {
                while ($col = $cols->fetch_assoc()) {
                    $tableInfo['columns'][] = $col['Field'];
                }
            }

            // Получить количество записей
            $count = $mysqli->query("SELECT COUNT(*) AS cnt FROM `$table`");
            if ($count) {
                $row = $count->fetch_assoc();
                $tableInfo['count'] = $row['cnt'] ? $row['cnt'] : 0;
            }
        }

        $results[$table] = $tableInfo;
    }

    return $results;
}

// === Настройки ===
$searchPath = '/home/siteme';
$targetTables = ['oc_customer', 'oc_order', 'oc_user', '28oc_customer', '28oc_order', '28oc_user', 'customer', 'order', 'user']; // ← ЗАДАЙ СВОЙ СПИСОК ТУТ

// === HTML HEADER ===
echo "<!DOCTYPE html><html lang='ru'><head><meta charset='UTF-8'><title>DB Inspector</title>";
echo "<style>body{font-family:monospace;background:#f4f4f4;padding:20px}h2{color:#333}ul{margin-bottom:30px}li{margin:3px 0}code{color:#006}</style>";
echo "</head><body><h1>🔍 Инспекция config.php и таблиц</h1>";

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

    $host = $creds['DB_HOSTNAME'];
    $user = $creds['DB_USERNAME'];
    $pass = $creds['DB_PASSWORD'];
    $db   = $creds['DB_DATABASE'];
    $port = (int)($creds['DB_PORT'] ? $creds['DB_PORT'] : 3306);

    $mysqli = @new mysqli($host, $user, $pass, $db, $port);
    if ($mysqli->connect_error) {
        echo "<p>❌ Ошибка подключения: {$mysqli->connect_error}</p>";
        continue;
    }

    echo "<ul>";
    $tablesInfo = inspectTargetTables($mysqli, $targetTables);

    foreach ($tablesInfo as $table => $info) {
        if (!$info['exists']) {
            echo "<li>⚠️ Таблица <code>$table</code> не найдена</li>";
            continue;
        }

        echo "<li>✅ <strong>$table</strong>: {$info['count']} записей";
        echo "<ul><li>🔑 Столбцы: ";
        echo implode(', ', array_map(function($c) {
            return "<code>$c</code>";
        }, $info['columns']));
        echo "</li></ul></li>";
    }

    echo "</ul>";
    $mysqli->close();
}

echo "</body></html>";

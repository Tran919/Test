<?php
// Путь к config.php
$configPath = '/home/siteme/bkbm.ua/www/config.php';

// Проверка наличия файла
if (!file_exists($configPath)) {
    die("Файл config.php не найден по пути: $configPath");
}

// Подгружаем config.php в изолированную область
$config = [];
ob_start();
require $configPath;
ob_end_clean();

// Получаем параметры из define()
$defined = get_defined_constants(true)['user'];

$dbHost = $defined['DB_HOSTNAME'] ? $defined['DB_HOSTNAME'] : null;
$dbUser = $defined['DB_USERNAME'] ? $defined['DB_USERNAME'] : null;
$dbPass = $defined['DB_PASSWORD'] ? $defined['DB_PASSWORD'] : null;
$dbName = $defined['DB_DATABASE'] ? $defined['DB_DATABASE'] : null;
$dbPort = $defined['DB_PORT'] ? $defined['DB_PORT'] : 3306;

if (!$dbHost || !$dbUser || !$dbName) {
    die("Не удалось извлечь параметры подключения из config.php");
}

// Подключение к базе данных
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($mysqli->connect_error) {
    die("Ошибка подключения: " . $mysqli->connect_error);
}

// Установка кодировки
$mysqli->set_charset("utf8mb4");

// Получаем список таблиц
$tables = [];
$res = $mysqli->query("SHOW TABLES");
if ($res) {
    while ($row = $res->fetch_array()) {
        $tables[] = $row[0];
    }
} else {
    die("Не удалось получить список таблиц");
}

// Выводим все записи из каждой таблицы
echo "<h1>Все данные из базы данных <code>$dbName</code></h1>";
foreach ($tables as $table) {
    echo "<h2>Таблица: <code>$table</code></h2>";

    $result = $mysqli->query("SELECT * FROM `$table`");
    if (!$result || $result->num_rows === 0) {
        echo "<p>Нет данных или ошибка запроса</p>";
        continue;
    }

    echo "<table border='1' cellpadding='5' cellspacing='0'><thead><tr>";
    // Заголовки
    while ($field = $result->fetch_field()) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr></thead><tbody>";

    // Данные
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $cell) {
            echo "<td>" . htmlspecialchars((string)$cell) . "</td>";
        }
        echo "</tr>";
    }

    echo "</tbody></table><br>";
}

$mysqli->close();
?>
<?php
// Путь к config.php
$configPath = __DIR__ . '/home/siteme/bkbm.ua/www/config.php';

if (!file_exists($configPath)) {
    die("Файл config.php не найден по пути: $configPath");
}

// Изоляция импорта
ob_start();
require $configPath;
ob_end_clean();

// Получаем параметры подключения
$defined = get_defined_constants(true)['user'];

$dbHost = $defined['DB_HOSTNAME'] ? $defined['DB_HOSTNAME'] : null;
$dbUser = $defined['DB_USERNAME'] ? $defined['DB_USERNAME'] : null;
$dbPass = $defined['DB_PASSWORD'] ? $defined['DB_PASSWORD'] : null;
$dbName = $defined['DB_DATABASE'] ? $defined['DB_DATABASE'] : null;
$dbPort = $defined['DB_PORT'] ? $defined['DB_PORT'] : 3306;

if (!$dbHost || !$dbUser || !$dbName) {
    die("Параметры подключения не заданы");
}

// Подключение
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($mysqli->connect_error) {
    die("Ошибка подключения: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

// Обработка удаления таблицы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_table'])) {
    $tableToDelete = $mysqli->real_escape_string($_POST['delete_table']);
    if (!empty($tableToDelete)) {
        $mysqli->query("DROP TABLE IF EXISTS `$tableToDelete`");
        echo "<p style='color: red; font-weight: bold;'>Таблица <code>$tableToDelete</code> удалена.</p>";
    }
}

// Получаем список таблиц
$tables = [];
$res = $mysqli->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
}

// Форма удаления таблицы
echo <<<HTML
<h2>Удалить таблицу</h2>
<form method="POST" onsubmit="return confirm('Точно удалить таблицу?');">
    <input type="text" name="delete_table" placeholder="Введите имя таблицы" required>
    <button type="submit">Удалить</button>
</form>
<hr>
HTML;

// Вывод таблиц (с лимитом 50)
echo "<h1>Содержимое базы данных <code>$dbName</code></h1>";
foreach ($tables as $table) {
    echo "<h2>Таблица: <code>$table</code></h2>";

    $result = $mysqli->query("SELECT * FROM `$table` LIMIT 50");

    if (!$result || $result->num_rows === 0) {
        echo "<p>Нет данных или ошибка запроса</p>";
        continue;
    }

    echo "<p>Показаны первые 50 записей</p>";
    echo "<table border='1' cellpadding='5' cellspacing='0'><thead><tr>";
    while ($field = $result->fetch_field()) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr></thead><tbody>";

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

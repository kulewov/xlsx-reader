<?php

$data      = readDataFromFile();
$tableName = 'simple_table';
$data      = prepareDataBeforeConnect($data, $tableName);

// Запись в базу
$host    = 'localhost';   // Хост, у нас все локально
$user    = 'root';        // Имя созданного вами пользователя
$pass    = 'root';        // Установленный вами пароль пользователю
$db_name = 'simple_db';   // Имя базы данных

$mysqli = new mysqli($host, $user, $pass, $db_name); // Соединяемся с базой

// Ругаемся, если соединение установить не удалось
if ($mysqli->connect_errno) {
    printf("Error connect to DB: %s\n", $mysqli->connect_error);
    die();
}

insertDataInto($tableName, $mysqli, $data);

// Проверка добавленных данных
$sqlSelect = "SELECT * FROM {$tableName}";
$result    = $mysqli->query($sqlSelect);

while ($row = mysqli_fetch_array($result)) {
    echo '<pre>';
    print_r($row);
}

$mysqli->close();

/** Устанавливает колонки таблицы */
function setColumns($columns): string
{
    $dbColumns = [];
    foreach ($columns as $column) {
        $dbColumns[] = sprintf(' `%s` %s  DEFAULT NULL ', $column,
            strtolower($column) === 'id' ? 'bigint(20)' : 'varchar(255)');
    }
    return implode(',', $dbColumns);
}

/**
 * Подготавливает массив перед работой
 *
 * @param array $data - Данные из файла
 * @param string $tableName - Название таблицы
 *
 * @return array
 */
function prepareDataBeforeConnect(array $data, string $tableName)
{
    $fields    = array_shift($data);
    $tableRows = setColumns($fields);
    $fields    = implode(',', $fields);

    $sql = "
       CREATE TABLE IF NOT EXISTS {$tableName} (
                {$tableRows}
            ) 
	        CHARACTER SET 'utf8mb4' 
            COLLATE 'utf8mb4_general_ci'; 
	    ";
    return [
        'fields'        => $fields,
        'create_sql'    => $sql,
        'insert_values' => $data
    ];
}

/**
 * Добавляет в баззу данные
 *
 * @param string $tableName - название таблицы
 * @param mysqli $db - Объект класса БД
 * @param array $data - Данные для работы
 */
function insertDataInto(string $tableName, mysqli $db, array $data)
{
    $sql = "
    INSERT INTO {$tableName}(%s)
    VALUES (%s)";

    $created = $db->query($data['create_sql']);

    foreach ($data['insert_values'] as $item) {
        $sqlInsert = sprintf($sql, $data['fields'], implode(",", array_map(function ($val) {
            return is_numeric($val) ? $val : '"' . $val . '"';
        }, $item)));

        $res = $db->query($sqlInsert);
        if (!$res) {
            echo 'Error';
        }
    }
}

function readDataFromFile()
{
    $filePath = realpath('test.xlsx');
    $file     = file_get_contents(sprintf('zip://%s#xl/sharedStrings.xml', $filePath));
    $xml      = (array)simplexml_load_string($file);
    $sst      = [];
    foreach ($xml['si'] as $item => $val) {
        $sst[] = iconv('UTF-8', 'windows-1251', (string)$val->t);
    }

    $file = file_get_contents(sprintf('zip://%s#xl/worksheets/sheet1.xml', $filePath));
    $xml  = simplexml_load_string($file);
    $data = [];

    foreach ($xml->sheetData->row as $row) {
        $currow = [];
        foreach ($row->c as $c) {
            $value = (string)$c->v;
            $attrs = $c->attributes();
            if ($attrs['t'] == 's') {
                $currow[] = $sst[$value];
            } else {
                $currow[] = $value;
            }
        }
        $data[] = $currow;
    }
    return $data;
}

<?php

/**
 * Скрипт заполнения очереди заданий на отправку.
 */

require 'config.php';

// Установка соединения с базой данных с помощью PDO
try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8", $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    exit("Не удалось подключиться к базе данных {$dbName}: ".$e->getMessage());
}

// Обеспечение глобальной видимости $pdo в функциях
global $pdo;

// Функция для постановки задач отправки email в очередь
function enqueueEmailTasks()
{
    global $pdo; // Обеспечиваем доступ к объекту PDO

    // Получаем timestamp на начало следующего дня и трех дней вперед
    $startNextDay = strtotime('tomorrow');
    $endNextDay = strtotime('tomorrow +1 day') - 1;
    $startThreeDaysAhead = strtotime('+3 days');
    $endThreeDaysAhead = strtotime('+4 days') - 1;

    //

    // Выбираем пользователей, чья подписка истекает на следующий день или через три дня
    // и почта валидная, либо не проверенная.
    $stmt = $pdo->prepare('SELECT email, username, valid FROM users WHERE (valid = 1 OR checked = 0) AND (validts BETWEEN ? AND ?) OR (validts BETWEEN ? AND ?)');
    $stmt->execute([$startNextDay, $endNextDay, $startThreeDaysAhead, $endThreeDaysAhead]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insertStmt = $pdo->prepare('INSERT INTO email_queue (username, email, email_valid, text) VALUES (?, ?, ?, ?)');
    foreach ($users as $user) {
        $text = "{$user['username']}, your subscription is expiring soon";
        $insertStmt->execute([$user['username'], $user['email'], $user['email_valid'], $text]);
    }
}

enqueueEmailTasks();

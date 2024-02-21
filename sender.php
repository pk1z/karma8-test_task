<?php

/**
 * Скрипт отправки писем из очереди.
 */

require 'config.php';

$email_per_process = 5;
// Создание процессов для отправки email
$maxProcesses = 10;

function send_email($from, $to, $text)
{
    // Симуляция отправки email
    echo "Отправка email на {$to}..." . PHP_EOL;
    sleep(rand(1, 10)); // Симуляция времени выполнения функции send_email
}

// Функция для обработки и отправки email из очереди
function sendEmailFromQueue()
{
    global $pdo, $from_email; // Обеспечиваем доступ к объекту PDO

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, email, text FROM email_queue WHERE status = 'pending' AND email_valid = 1 order by id LIMIT 1  FOR UPDATE ");
        $stmt->execute();
        $emailTask = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($emailTask) {
            $updateStmt = $pdo->prepare("UPDATE email_queue SET status = 'processing' WHERE id = ?");
            $updateStmt->execute([$emailTask['id']]);
            $pdo->commit();

            send_email($from_email, $emailTask['email'], $emailTask['text']);

            // После успешной отправки обновляем статус на 'sent'
            $pdo->beginTransaction();
            $updateStmt = $pdo->prepare("UPDATE email_queue SET status = 'sent' WHERE id = ?");
            $updateStmt->execute([$emailTask['id']]);
            $pdo->commit();
        } else {
            // Если подходящих заданий нет, просто завершаем транзакцию
            $pdo->commit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Не удалось отправить email: {$e->getMessage()}" . PHP_EOL;
    }
}

for ($i = 0; $i < $maxProcesses; ++$i) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        exit("Не удалось создать дочерний процесс." . PHP_EOL);
    }
    if ($pid) {
        // Родительский процесс
        echo "Запущен дочерний процесс {$pid}\n";
    } else {
        // Дочерний процесс
        // Установка соединения с базой данных с помощью PDO
        try {
            $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8", $dbUser, $dbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            exit("Не удалось подключиться к базе данных {$dbName}: ".$e->getMessage());
        }

        for ($i = $email_per_process; $i > 0; --$i) {
            sendEmailFromQueue();
        }

        exit(0);
    }
}

while (($pid = pcntl_waitpid(0, $status)) != -1) {
    $status = pcntl_wexitstatus($status);
    echo "Дочерний процесс завершен со статусом {$status}" . PHP_EOL;
}

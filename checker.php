<?php
/**
 * Скрипт проверки емейлов на вадилность через сторонний скрипт.
 */

require 'dbConfig.php';

function check_email($email): bool
{
    // Симуляция отправки email
    echo "Проверка почты {$email} на валидность ...".PHP_EOL;
    sleep(rand(1, 60)); // Симуляция времени выполнения функции check_email

    return rand(0, 1);
}

function checkEmailsFromQueue()
{
    global $pdo, $from_email; // Обеспечиваем доступ к объекту PDO

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, email, text FROM email_queue WHERE email_valid = 0 ORDER BY id LIMIT 1  FOR UPDATE ');
        $stmt->execute();
        $emailTask = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($emailTask) {
            $checkResult = check_email($emailTask['email']);

            if ($checkResult) {
                // почта валидная, задание остаётся в очереди
                $pdo->beginTransaction();
                $updateStmt = $pdo->prepare('UPDATE email_queue SET email_valid = 1 WHERE id = ?');
                $updateStmt->execute([$emailTask['id']]);

                // обновляем
                $updateStmt = $pdo->prepare('UPDATE email SET email_valid = 1, checked = 1 WHERE id = ?');
                $updateStmt->execute([$emailTask['id']]);
                $pdo->commit();
            } else {
                // почта невалидная,

                $pdo->beginTransaction();
                $updateStmt = $pdo->prepare("UPDATE email_queue SET email_valid = 0, status = 'failed' WHERE id = ?");
                $updateStmt->execute([$emailTask['id']]);

                // обновляем
                $updateStmt = $pdo->prepare('UPDATE email SET email_valid = 0, checked = 1 WHERE id = ?');
                $updateStmt->execute([$emailTask['id']]);
                $pdo->commit();
            }
        } else {
            // Если подходящих заданий нет, просто завершаем транзакцию
            $pdo->commit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Не удалось отправить email: {$e->getMessage()}" . PHP_EOL;
    }
}

$email_per_process = 5;
// Создание процессов для отправки email
$maxProcesses = 10;
for ($i = 0; $i < $maxProcesses; ++$i) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        exit("Не удалось создать дочерний процесс." . PHP_EOL);
    }
    if ($pid) {
        // Родительский процесс
        echo "Запущен дочерний процесс {$pid}" . PHP_EOL;
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
            checkEmailsFromQueue();
        }

        exit(0);
    }
}

while (($pid = pcntl_waitpid(0, $status)) != -1) {
    $status = pcntl_wexitstatus($status);
    echo "Дочерний процесс завершен со статусом {$status}" . PHP_EOL;
}

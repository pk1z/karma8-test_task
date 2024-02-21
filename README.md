
### Логика решения.

Исходя из требований (ежемесячная подписка, 1млн пользователей с активной подпиской, скорость отправки писем) понимаем,
что потребуется реализовать многопоточную систему.

Для многопоточной отправки используем метод создания дочернего php процесса **pcntl_fork()**.

Первый этап механизма отправки - выборка адресов пользователей с истекающей через 1 и 3 дня подпиской и наполнение
очереди заданий. Для условия выборки используем следующию логику - уведомление нужно отправить, если unixtime окончания
подписки пользователя находится в диаразоне от 00:00 до 23:59 следующих суток либо суток через 3 дня.

Для оптимизации потребнения ресурсов валидацию почты пользователя делаем только для тех, кто попал в очередь на отправку.

#### Особенности кода:
- Т.к. проиходит одновременное получение задания на отправку из нескольких процессов, для исключения дублирования
используется механизм блокировки (`SELECT ... FOR UPDATE`)
- Коннект к базе данных создается внутри каждого процесса

#### Структура решения:
- `fill_queue.php` - скрипт заполнения очереди заданий на отправку. Запускается ежедневно.
Формат записи в [CRONTAB - 0 3 * * * ]()(Запуск каждый день в 3 ночи)
- `sender.php`  - скрипт отправки уведомлений. Берет задания из очереди **email_queue** из тех, у кого валидный емейл
Инициирует 10 процессов, каждый процесс отправляет 5 емейлов.
Формат записи в [CRONTAB - * * * * *]() (Запуск каждую минуту)
- `checker.php` - скрипт проверки емейлов из очереди отправки на валидность через внешнюю функцию.

Автор - [Сафронов С.В.](https://t.me/sv_safronov)

### Исходное задание:
```


PHP Developer Test Cases

Вы разрабатываете сервис для рассылки уведомлений об истекающих
подписках.
За один и за три дня до истечения срока подписки, нужно отправить
письмо пользователю с текстом "{username}, your subscription is expiring
soon".
Имеем следующее
1. Таблица в DB с пользователями (5 000 000+ строк):
   username - Имя пользователя
   email - Емейл
   validts - unix timestamp до которого действует ежемесячная подписка, либо 0 если подписки нет
   conﬁrmed - 0 или 1 в зависимости от того, подтвердил ли пользователь свой емейл по ссылке (пользователю
   после регистрации приходит письмо с уникальный ссылкой на указанный емейл, если он нажал на
   ссылку в емейле в этом поле устанавливается 1)
   checked - Была ли проверка емейла на валидацию (1) или не было (0)
   valid - Является ли емейл валидным (1) или нет (0)
2. Около 80% пользователей не имеют подписки.
3. Только 15% пользователей подтверждают свой емейл (поле conﬁrmed).
4. Внешняя функция check_email( $email )
   Проверяет емейл на валидность (на валидный емейл письмо точно
   дойдёт) и возвращает 0 или 1. Функция работает от 1 секунды до 1
   минуты. Вызов функции стоит 1 руб.
5. Функция send_email( $from, $to, $text )
   Отсылает емейл. Функция работает от 1 секунды до 10 секунд.Ограничения:

Необходимо регулярно отправлять емейлы об истечении срока
подписки на те емейлы, на которые письмо точно дойдёт
Можно использовать cron
Можно создать необходимые таблицы в DB или изменить
существующие
Для функций check_email и send_email нужно написать "заглушки"
Не использовать ООП
Очереди реализовать без использования менеджеров
Преимуществом будет
1. Простой, читаемый и рабочий код
2. Readme
3. Код размещенный в GitHub
   Этим тестовым заданием мы хотим понять образ вашего мышления и
   умение найти подход к решению задач. Не стоит демонстрировать
   разнообразие технологий, которыми вы владеете. Код должен быть
   простым и решать поставленную задачу из тестового задания.

```


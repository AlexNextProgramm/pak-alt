<div class="page-documentation">
    <aside class="page-documentation__sidebar">
        <div class="page-documentation__sidebar-inner">
            <p class="page-documentation__sidebar-label">Содержание</p>
            <nav class="page-documentation__nav" aria-label="Содержание документации"></nav>
        </div>
    </aside>

    <div class="page-documentation__body">
        <div class="page-documentation__hero">
            <h1 class="page-documentation__title">Документация проекта «ПАК-АЛЬТ»</h1>
            <p class="page-documentation__lead">Бизнес-логика системы страхования. Два приложения: <strong>публичный сайт</strong> (калькулятор, заявки) и <strong>админ-панель</strong> (управление застрахованными, компаниями, заявками).</p>
            <div class="page-documentation__search">
                <input
                    type="search"
                    class="page-documentation__search-input"
                    placeholder="Поиск по документации…"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="Поиск по документации"
                >
            </div>
            <p class="page-documentation__search-meta" aria-live="polite"></p>
        </div>

        <article class="page-documentation__content">
    <h2>1. Публичный сайт — бизнес-процессы</h2>

    <h3>1.1. Калькулятор страхования</h3>
    <p><strong>Суть:</strong> Посетитель сайта может рассчитать стоимость страхового полиса онлайн.</p>
    <p><strong>Сценарий использования:</strong></p>
    <ol>
        <li>Посетитель заходит на главную страницу</li>
        <li>Заполняет форму калькулятора: тип страхования, срок, сумма покрытия</li>
        <li>Система рассчитывает предварительную стоимость</li>
        <li>Посетитель может оставить заявку на оформление полиса</li>
    </ol>

    <h3>1.2. Оформление заявки</h3>
    <p><strong>Суть:</strong> Клиент оставляет заявку на страхование.</p>
    <p><strong>Полный цикл:</strong></p>
    <ol>
        <li>Клиент заполняет форму: имя, телефон, email, данные для расчёта</li>
        <li>Форма отправляется на сервер</li>
        <li>Создаётся заявка со статусом «Новая»</li>
        <li>В админке менеджер видит новую заявку и обрабатывает её</li>
    </ol>

    <h3>1.3. Контакты</h3>
    <p><strong>Суть:</strong> Страница с контактной информацией компании.</p>
    <p><strong>Что отображается:</strong></p>
    <ul>
        <li>Телефон, email, адрес, график работы</li>
        <li>Telegram, WhatsApp</li>
        <li>Все данные управляются через переменные (<code>variable</code>) в админке</li>
        <li>Форма обратной связи</li>
    </ul>

    <h2>2. Админ-панель — управление бизнесом</h2>

    <h3>2.1. Застрахованные</h3>
    <p><strong>Суть:</strong> Учёт застрахованных лиц и их полисов.</p>
    <p><strong>Возможности:</strong></p>
    <ul>
        <li>Просмотр списка застрахованных</li>
        <li>Добавление нового застрахованного</li>
        <li>Редактирование данных застрахованного</li>
        <li>Просмотр истории полисов</li>
    </ul>

    <h3>2.2. Компании</h3>
    <p><strong>Суть:</strong> Управление страховыми компаниями-партнёрами.</p>
    <p><strong>Возможности:</strong></p>
    <ul>
        <li>Просмотр списка компаний</li>
        <li>Добавление новой компании</li>
        <li>Редактирование данных компании (название, email)</li>
        <li>Управление тарифами и условиями</li>
    </ul>
    <p><strong>Email компании</strong> используется фоновым cron-задачей: по адресу отправителя система определяет, от какой компании пришло письмо с вложениями. Email должен совпадать с адресом в поле «От» входящего письма.</p>

    <h3>2.3. Заявки</h3>
    <p><strong>Суть:</strong> Обработка заявок от клиентов.</p>
    <p><strong>Процесс:</strong></p>
    <ol>
        <li>Менеджер видит список заявок с бейджем новых</li>
        <li>В заявке: клиент, контакты, тип страхования, статус</li>
        <li>Менеджер меняет статус: Новая → В работе → Выполнена / Отменена</li>
    </ol>

    <h3>2.4. Переменные</h3>
    <p><strong>Суть:</strong> Управление настройками сайта без изменения кода.</p>
    <p><strong>Используется для:</strong></p>
    <ul>
        <li>Контактных данных (телефон, email, адрес, график, Telegram, WhatsApp)</li>
        <li>API-ключей (PlusOfon для SMS)</li>
        <li>Подключения к почте IMAP (тип <code>imap</code>: host, port, username, password, encryption, verify_ssl, folder)</li>
    </ul>
    <p><strong>Правила:</strong></p>
    <ul>
        <li>Добавление — через миграцию или админку</li>
        <li>Удаление — запрещено</li>
        <li>Редактирование — только значение; тип и имя фиксируются</li>
    </ul>

    <h3>2.5. Авторизация в админке</h3>
    <p><strong>Суть:</strong> Доступ сотрудников компании к админ-панели по номеру телефона и паролю.</p>
    <p><strong>Как работает:</strong></p>
    <ol>
        <li>Сотрудник заходит на страницу входа, вводит телефон и пароль</li>
        <li>Система проверяет комбинацию — если верно, открывает доступ к админке</li>
        <li>При последующих визитах сотрудник остаётся авторизованным, пока не выйдет</li>
        <li>Без авторизации доступны только страница входа и публичные маршруты</li>
    </ol>
    <p><strong>Где требуется авторизация:</strong></p>
    <ul>
        <li>Все страницы админки</li>
        <li>Все формы сохранения данных</li>
        <li>Таблицы с данными</li>
    </ul>
    <p><strong>Защита форм:</strong> Каждая форма в админке защищена от подделки межсайтовых запросов — при отправке данных система проверяет специальный токен безопасности.</p>

    <h3>2.6. Отчёт крона</h3>
    <p><strong>Суть:</strong> Журнал запусков фоновой задачи загрузки вложений из почты.</p>
    <p><strong>Что отображается:</strong></p>
    <ul>
        <li>Время запуска (<code>started_at</code>)</li>
        <li>Количество обработанных писем от компаний (<code>emails_found</code>)</li>
        <li>Текст ошибок (<code>errors</code>)</li>
        <li>Статус: выполняется, успешно, завершено с ошибками, ошибка</li>
    </ul>
    <p>Подробная логика работы cron — в разделе <strong>7. Cron — загрузка вложений из почты</strong>.</p>

    <h2>3. Схема данных (бизнес-сущности)</h2>

    <table>
        <tr><th>Сущность</th><th>Таблица</th><th>Назначение</th></tr>
        <tr><td>Застрахованные</td><td><code>zastrakhovannye</code></td><td>Застрахованные лица и их полисы</td></tr>
        <tr><td>Компании</td><td><code>company</code></td><td>Страховые компании-партнёры (название, email отправителя)</td></tr>
        <tr><td>Переменные</td><td><code>variable</code></td><td>Настройки сайта (контакты, API-ключи, IMAP)</td></tr>
        <tr><td>Фотографии</td><td><code>upload_photo</code></td><td>Изображения</td></tr>
        <tr><td>Файлы из почты</td><td><code>upload_file</code></td><td>Вложения, загруженные cron-задачей из писем компаний</td></tr>
        <tr><td>Журнал cron</td><td><code>cron_report</code></td><td>История запусков фоновой задачи загрузки вложений</td></tr>
        <tr><td>Пользователи админки</td><td><code>users</code></td><td>Администраторы системы</td></tr>
    </table>

    <h2>4. Основные бизнес-сценарии (end-to-end)</h2>

    <h3>4.1. Оформление страхового полиса</h3>
    <ol>
        <li>Клиент заходит на сайт, использует калькулятор</li>
        <li>Заполняет форму заявки: имя, телефон, email</li>
        <li>Заявка отправляется в админку со статусом «Новая»</li>
        <li>Менеджер обрабатывает заявку: связывается с клиентом</li>
        <li>Менеджер оформляет полис и меняет статус на «Выполнена»</li>
    </ol>

    <h3>4.2. Управление компаниями (админка)</h3>
    <ol>
        <li>Администратор добавляет страховую компанию</li>
        <li>Заполняет реквизиты, контакты, тарифы</li>
        <li>Компания отображается в списке и доступна для выбора</li>
        <li>При необходимости редактирует или отключает компанию</li>
    </ol>

    <h2>5. Роли и права доступа</h2>

    <table>
        <tr><th>Роль</th><th>Доступ</th></tr>
        <tr><td>Системный администратор</td><td>Полный доступ ко всем разделам админки</td></tr>
        <tr><td>Администратор</td><td>Управление застрахованными, компаниями, заявками</td></tr>
        <tr><td>Гость (сайт)</td><td>Просмотр калькулятора, оформление заявки</td></tr>
    </table>

    <h2>6. Интеграции</h2>

    <h3>6.1. SMS-сервис PlusOfon</h3>
    <p>Отправка кодов подтверждения. API-ключ хранится в переменной <code>plusofon.api_token</code>.</p>

    <h3>6.2. Загрузка файлов</h3>
    <p>Фотографии загружаются через модуль <code>Upload\Storage</code>. Файлы хранятся в <code>var/uploads/</code>, отдаются через маршрут <code>/uploads/*</code>.</p>

    <h3>6.3. Почта IMAP</h3>
    <p>Подключение к почтовому ящику для cron-задачи загрузки вложений. Настройки хранятся в переменных типа <code>imap</code> (миграция <code>6_imap.sql</code>). Используется модуль <code>Module\Imap\Client</code>.</p>

    <h2>7. Cron — загрузка вложений из почты</h2>

    <h3>7.1. Назначение</h3>
    <p>Фоновая задача автоматически проверяет почтовый ящик, находит <strong>непрочитанные</strong> письма от компаний-партнёров (по email из таблицы <code>company</code>), скачивает вложения на сервер и сохраняет результат в базу данных.</p>
    <p>Журнал запусков доступен в админке: раздел <strong>Отчёт крона</strong>.</p>

    <h3>7.2. Требования</h3>
    <ul>
        <li>PHP-расширение <code>imap</code></li>
        <li>Применённые миграции: <code>6_imap.sql</code> (переменные IMAP), <code>27_upload_file.sql</code> (таблица результатов)</li>
        <li>Заполненные переменные <code>imap.*</code> в разделе «Переменные»</li>
        <li>Компании в таблице <code>company</code> с корректным полем <code>email</code></li>
        <li>Каталог <code>var/uploads/</code> с правами на запись</li>
    </ul>

    <h3>7.3. Запуск</h3>
    <p>Скрипт: <code>script/php/cron</code></p>
    <pre><code># Ручной запуск из корня проекта
php script/php/cron

# Пример crontab (каждые 15 минут)
*/15 * * * * cd /path/to/pak-alt &amp;&amp; php script/php/cron &gt;&gt; log/cron.log 2&gt;&amp;1</code></pre>
    <p>Точка входа подключает Pet framework через <code>script/php/bootstrap.php</code> (конфиг admin, autoload, БД). Бизнес-логика — класс <code>Module\Cron\EmailAttachmentJob</code> в <code>service/Module/Cron/</code>.</p>

    <h3>7.4. Полный алгоритм работы</h3>
    <ol>
        <li><strong>Старт журнала.</strong> Создаётся запись в <code>cron_report</code> со статусом <code>running</code> и текущим временем <code>started_at</code>.</li>
        <li><strong>Загрузка компаний.</strong> Из <code>company</code> выбираются записи, у которых <code>email</code> не пустой. Email нормализуется (нижний регистр, trim) и проверяется через <code>filter_var</code>. Формируется карта «email → company_id». Если подходящих компаний нет — задача завершается с ошибкой.</li>
        <li><strong>Проверка IMAP.</strong> Модуль <code>Module\Imap\Client</code> читает настройки из переменных <code>imap.host</code>, <code>imap.port</code>, <code>imap.username</code>, <code>imap.password</code>, <code>imap.encryption</code>, <code>imap.verify_ssl</code>, <code>imap.folder</code> (по умолчанию <code>INBOX</code>). Если обязательные поля пусты — ошибка.</li>
        <li><strong>Поиск писем.</strong> Выполняется IMAP-поиск с критерием <code>UNSEEN</code> (только непрочитанные). Лимит — <strong>без ограничения</strong> (<code>getMessages(0, 'UNSEEN')</code>): за один запуск обрабатываются все непрочитанные письма в папке.</li>
        <li><strong>Фильтрация по отправителю.</strong> Для каждого письма из заголовка «От» извлекается email (форматы <code>name@domain.com</code> или <code>Имя &lt;name@domain.com&gt;</code>). Письмо обрабатывается только если email есть в карте компаний. Остальные пропускаются без изменений (остаются непрочитанными).</li>
        <li><strong>Чтение письма.</strong> По UID запрашивается полное содержимое и список вложений. Если прочитать не удалось — ошибка пишется в журнал cron, письмо <strong>не</strong> помечается прочитанным.</li>
        <li><strong>Проверка вложений.</strong> Если вложений нет — письмо пропускается, остаётся <code>UNSEEN</code> (будет проверяться снова при следующем запуске).</li>
        <li><strong>Сохранение файлов.</strong> Для каждого вложения:
            <ul>
                <li>Каталог: <code>var/uploads/download/{email}/</code> (email санитизируется для файловой системы; каталог создаётся автоматически)</li>
                <li>Имя файла: случайный префикс (16 hex-символов) + оригинальное имя вложения (очищенное от небезопасных символов)</li>
                <li>Сохранение через <code>Module\Upload\Storage::saveContent()</code></li>
            </ul>
        </li>
        <li><strong>Запись в БД.</strong> На каждое вложение создаётся строка в <code>upload_file</code>:
            <ul>
                <li><code>company_id</code> — ID компании-отправителя</li>
                <li><code>path</code> — относительный путь к файлу (например <code>download/partner@mail.ru/a1b2c3d4_document.pdf</code>) или <code>NULL</code> при ошибке</li>
                <li><code>exception</code> — текст ошибки или <code>NULL</code> при успехе</li>
            </ul>
        </li>
        <li><strong>Пометка прочитанным.</strong> После обработки всех вложений письмо помечается флагом <code>\Seen</code> (прочитано), даже если часть вложений сохранилась с ошибкой. Письма без вложений и письма с ошибкой чтения <strong>не</strong> помечаются прочитанными.</li>
        <li><strong>Завершение журнала.</strong> Запись <code>cron_report</code> обновляется: <code>emails_found</code> (сколько писем от компаний обработано), <code>errors</code> (текст ошибок через перевод строки или <code>NULL</code>), финальный статус.</li>
    </ol>

    <h3>7.5. Статусы cron_report</h3>
    <table>
        <tr><th>Статус</th><th>Когда выставляется</th></tr>
        <tr><td><code>running</code></td><td>В момент старта задачи</td></tr>
        <tr><td><code>success</code></td><td>Задача завершилась без ошибок</td></tr>
        <tr><td><code>completed</code></td><td>Задача завершилась, но были частичные ошибки (например, не удалось скачать отдельное вложение)</td></tr>
        <tr><td><code>error</code></td><td>Критическая ошибка: нет компаний с email, IMAP не настроен, нет связи с почтой и т.п.</td></tr>
    </table>

    <h3>7.6. Переменные IMAP</h3>
    <table>
        <tr><th>Имя</th><th>Значение по умолчанию</th><th>Назначение</th></tr>
        <tr><td><code>imap.host</code></td><td>—</td><td>Адрес почтового сервера</td></tr>
        <tr><td><code>imap.port</code></td><td><code>993</code></td><td>Порт</td></tr>
        <tr><td><code>imap.username</code></td><td>—</td><td>Логин</td></tr>
        <tr><td><code>imap.password</code></td><td>—</td><td>Пароль</td></tr>
        <tr><td><code>imap.encryption</code></td><td><code>ssl</code></td><td>Шифрование: <code>ssl</code>, <code>tls</code> или <code>none</code></td></tr>
        <tr><td><code>imap.verify_ssl</code></td><td><code>1</code></td><td>Проверка SSL-сертификата (<code>0</code> — отключить)</td></tr>
        <tr><td><code>imap.folder</code></td><td><code>INBOX</code></td><td>Папка для проверки писем</td></tr>
    </table>

    <h3>7.7. Схема потока данных</h3>
    <pre><code>Почтовый ящик (IMAP, UNSEEN)
        ↓
Фильтр: отправитель ∈ company.email
        ↓
Вложения → var/uploads/download/{email}/
        ↓
upload_file (company_id, path, exception)
        ↓
cron_report (журнал запуска)</code></pre>

    <h3>7.8. Частые вопросы по cron</h3>

    <p><strong>Сколько писем обрабатывается за один запуск?</strong></p>
    <p>Все непрочитанные (<code>UNSEEN</code>) в выбранной папке. Лимит не установлен.</p>

    <p><strong>Почему письмо не обрабатывается?</strong></p>
    <ul>
        <li>Email отправителя не совпадает ни с одной компанией в <code>company.email</code></li>
        <li>Письмо уже прочитано (флаг <code>Seen</code>)</li>
        <li>В письме нет вложений — оно остаётся непрочитанным и будет проверяться снова</li>
    </ul>

    <p><strong>Где лежат скачанные файлы?</strong></p>
    <p>На диске: <code>var/uploads/download/{email}/</code>. Путь в БД — в таблице <code>upload_file.path</code>.</p>

    <p><strong>Как добавить нового отправителя?</strong></p>
    <p>Админка → Компании → указать email, совпадающий с адресом в поле «От» входящих писем.</p>

    <h2>8. FAQ — частые вопросы</h2>

    <h3>Как добавить застрахованного?</h3>
    <p>Админка → Застрахованные → кнопка «+». Заполнить данные и сохранить.</p>

    <h3>Как добавить компанию?</h3>
    <p>Админка → Компании → кнопка «+». Заполнить реквизиты, контакты, тарифы.</p>

    <h3>Как изменить контактный телефон?</h3>
    <p>Админка → Переменные → найти <code>contacts.phone</code> → изменить значение.</p>

    <h3>Почему заявка не видна в админке?</h3>
    <p>Проверьте, что заявка была отправлена. Если проблема повторяется — проверьте логи сервера.</p>

    <h3>Как настроить загрузку файлов из почты?</h3>
    <p>Заполните переменные <code>imap.*</code>, добавьте email компаний, примените миграции и настройте crontab. Подробнее — раздел <strong>7. Cron — загрузка вложений из почты</strong>.</p>
        <h2>8. Парсинг Excel‑файлов</h2>
        <p><strong>Суть:</strong> Обработка вложений‑таблиц, полученных из писем компаний, и импорт данных о застрахованных.</p>
        <ul>
            <li>Детерминированный парсер <code>Module\Ai\InsuredParser</code> (<a href="service/Module/Ai/InsuredParser.php" target="_blank">service/Module/Ai/InsuredParser.php</a>) читает Excel‑файлы, определяет тип операции (прикрепление/открепление) и возвращает массив записей.</li>
            <li>Для выгрузок «ALL» от АльфаСтрахования используется специализированный парсер <code>Module\Ai\AlphaAllExportParser</code> (<a href="service/Module/Ai/AlphaAllExportParser.php" target="_blank">service/Module/Ai/AlphaAllExportParser.php</a>).</li>
            <li>Если требуется более гибкая обработка, задействуется AI‑пакет <code>ai/index.js</code> (<a href="ai/index.js" target="_blank">ai/index.js</a>) через Node.js. Конфигурация парсера хранится в <code>ai/mamp/parse-insured.json</code> (<a href="ai/mamp/parse-insured.json" target="_blank">ai/mamp/parse-insured.json</a>).</li>
            <li>Ключевые настройки (колонки, типы дат, ключевые слова) задаются в файле конфигурации и могут быть переопределены переменной <code>ai.parse_config</code> в таблице <code>variable</code>.</li>
            <li>Результат парсинга передаётся в <code>Module\Cron\ZastrakhovannyeImporter</code> (<a href="service/Module/Cron/ZastrakhovannyeImporter.php" target="_blank">service/Module/Cron/ZastrakhovannyeImporter.php</a>) для сохранения в таблицу <code>zastrakhovannye</code>.</li>
        </ul>
        <p>Подробный алгоритм парсинга:</p>
        <ol>
            <li>Файл сохраняется в <code>var/uploads/download/{email}/</code>.</li>
            <li>Определяется, является ли файл выгрузкой «ALL» (проверка заголовков). Если да – используется <code>AlphaAllExportParser</code>.</li>
            <li>Иначе – вызывается <code>InsuredParser::parseFile()</code>, который при необходимости делегирует работу Node‑скрипту.</li>
            <li>Полученный массив записей проходит валидацию (проверка ФИО, даты, полиса) и импортируется в БД.</li>
            <li>Ошибки импорта сохраняются в журнал <code>cron_report.errors</code> и в поле <code>exception</code> таблицы <code>upload_file</code>.</li>
        </ol>
        </article>
    </div>
</div>
<?php
return [
    'MODULE_NAME' => 'CSV,XLSX,XLS',
    'MODULE_DESC' => 'Импорт товаров, категорий, свойств из CSV,XLS XLSX файлов.',
    'ERROR_WRITE_BACKUP' => 'Ошибка. Директория для бэкапов недоступна для записи.',
    'IMPORT_PRODUCTS' => 'Импорт товаров',
    'EXPORT_PRODUCTS' => 'Экспорт товаров',
    'DOWNLOAD_CSV' => 'Скачать файл csv',
    'FILE_INPUT_HINT' => 'Максимальный размер файла: <strong>{0}</strong><br/>Доступные форматы: <strong>{1}</strong>',
    'HINT_UPLOAD_FILE' => 'Максимальный размер файла: <strong>{size}</strong><br/>Максимальный кол. файлов: <strong>{num}</strong>',
    'CREATE_PRODUCTS' => 'Создано товаров: <span class="badge badge-success">{0}</span>',
    'UPDATE_PRODUCTS' => 'Обновлено товаров: <span class="badge badge-success">{0}</span>',
    'DELETED_PRODUCTS' => 'Удалено товаров: <span class="badge badge-success">{0}</span>',
    'IMPORT' => 'Импорт',
    'EXPORT' => 'Экспорт',
    'SUCCESS_IMPORT' => 'Вы успешно загрузили товары',
    'WHOLESALE_PRICE' => 'Цена опт.<br/>Пример: <code>950=2</code> - Первое число цена, после знака "<code>=</code>" число количество.<br/>Поддерживает несколько цен, разделяя точкой с запятой "<code>;</code>"',
    'ERRORS_IMPORT' => 'Ошибки импорта',
    'WARNING_IMPORT' => 'Предупреждение импорта',
    'IMPORT_INFO1' => 'Первой строкой файла должны быть указаны колонки для импорта.',
    'IMPORT_INFO2' => 'Колонки <strong>{0}</strong> - обязательны.',
    'IMPORT_INFO3' => 'Разделитель поля - (<code>{0}</code>)',
    'IMPORT_INFO4' => 'Файл должен иметь кодировку UTF-8 или CP1251.',
    'EXAMPLE_FILE' => 'Пример файла',
    'REMOVE_IMAGES' => 'Удалить загруженные картинки',
    'ERROR_FILE' => 'Файл недоступен.',
    'SUCCESS_UPLOAD_IMAGES' => 'Вы успешно загрузили изображения',
    'ERROR_COLUMN_ATTRIBUTE' => 'Атрибут <strong>{attribute}</strong> используеться основым параметром товаров.',
    'ERROR_IMAGE' => 'Ошибка в изображениях.',
    'ERROR_IMAGE_EXTENSION' => 'Неверный формат файла, доступные форматы: <strong>{0}</strong>',
    'REQUIRE_COLUMN' => 'Укажите обязательную колонку <strong>{column}</strong>',
    'REQUIRE_COLUMN_EMPTY' => 'Не заполнена обязательная колонка <strong>{column}</strong>',
    'LINE' => 'Строка <strong>{0}</strong>:',
    'LIST' => 'Список <strong>{0}</strong>:',
    'AND_MORE' => 'и еще ({0}).',
    'FILENAME' => 'Файл',
    'EXPORT_FORMAT' => 'Формат экспорта',
    'FILES' => 'Изображения ({0})',
    'DB_BACKUP' => 'Создать резервную копию БД',
    'IMPORT_ALERT' => 'Перед загрузкой <strong>файла</strong>, необходимо загрузить изображения, если этого требует файл',
    'NO_FIND_CURRENCY' => 'Не найдена валюта: {0}',
    'PAGE' => 'Количество товаров в файле',
    'QUEUE_SUBJECT' => 'Ошибка фонового импорта товаров',
    'UPLOAD' => 'Загрузить',
    'IMAGE_FOR_UPLOAD' => 'Изображения для импорта',
    'QUEUE_ADD'=>'Список: <strong>{type}</strong>, добавлено в очередь: <strong>{count}</strong> товара',
];
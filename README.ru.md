# rasuvaeff/yii3-altcha

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/yii3-altcha/v)](https://packagist.org/packages/rasuvaeff/yii3-altcha)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-altcha/downloads)](https://packagist.org/packages/rasuvaeff/yii3-altcha)
[![Build](https://github.com/rasuvaeff/yii3-altcha/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-altcha/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/yii3-altcha/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-altcha/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/yii3-altcha/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-altcha/php)](https://packagist.org/packages/rasuvaeff/yii3-altcha)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[English version](README.md)

[ALTCHA](https://altcha.org) — CAPTCHA на proof-of-work для Yii3: сервер выдаёт подписанный challenge, браузер тратит
CPU на его решение, а сервер проверяет ответ локально. Без стороннего сервиса, без исходящих запросов и без аккаунта
с ключами сайта — данные не покидают ваш сервер.

Построен на официальной библиотеке [`altcha-org/altcha`](https://github.com/altcha-org/altcha-lib-php), которой
принадлежит вся криптография; этот пакет — обвязка для Yii3.

> **Используете AI-ассистента?** В [llms.txt](llms.txt) — компактный API-справочник, которым можно поделиться с моделью.

## Требования

- PHP 8.3+
- `altcha-org/altcha` 2.4.x (закреплён на одном миноре: библиотека ломает совместимость в минорах)
- `yiisoft/validator`, `yiisoft/widget`, `yiisoft/html`, `yiisoft/translator`
- PSR-16 кэш (`Psr\SimpleCache\CacheInterface`) в контейнере — только пока включена защита от повторного использования (по умолчанию включена)
- Необязательно: `yiisoft/translator-message-php` для встроенного русского перевода сообщения правила; без него
  сообщение остаётся английским

## Установка

```bash
composer require rasuvaeff/yii3-altcha
```

С `yiisoft/config` пакет сам регистрирует DI-определения, параметры и bootstrap.

## Использование

### Конфигурация

Задайте секрет через параметры приложения, читающие окружение; всё остальное имеет значения по умолчанию:

```php
// config/params.php
return [
    'rasuvaeff/yii3-altcha' => [
        'secret' => $_ENV['ALTCHA_SECRET'],   // >= 32 bytes, never committed
        'algorithm' => 'PBKDF2/SHA-256',
        'cost' => 5000,
        'maxCounter' => 1000,
        'ttlSeconds' => 600,
        'replayProtection' => true,
    ],
];
```

| Параметр | По умолчанию | Смысл |
|---|---|---|
| `secret` | `''` (обязателен) | HMAC-секрет, подписывающий каждый challenge, не короче 32 байт. Незаданный секрет громко роняет контейнер |
| `algorithm` | `PBKDF2/SHA-256` | `PBKDF2/SHA-256\|384\|512` или `SHA-256\|384\|512` — алгоритмы, которые браузерный виджет решает нативно |
| `cost` | `5000` | Итераций выработки ключа на одну попытку; выдача challenge стоит серверу одной выработки, поэтому потолок — 100 000 |
| `maxCounter` | `1000` | Верхняя граница секретного счётчика, который ищет браузер; в среднем решение занимает половину |
| `ttlSeconds` | `600` | Время жизни challenge, а значит и решённого payload |
| `replayProtection` | `true` | Тратить challenge на первой попытке с валидной подписью (нужен PSR-16 кэш в контейнере) |
| `translation.category` | `yii3-altcha` | Категория переводчика для сообщения правила |

Сложность в браузере — в среднем `cost × maxCounter / 2` итераций. Значения по умолчанию целятся примерно в секунду
на ноутбуке; замерьте на самом слабом поддерживаемом устройстве и сначала подбирайте `maxCounter`.

### Эндпоинт challenge

`ChallengeAction` — PSR-15 обработчик, возвращающий свежий challenge как некэшируемый JSON. Повесьте его на любой маршрут:

```php
use Rasuvaeff\Yii3Altcha\ChallengeAction;
use Yiisoft\Router\Route;

Route::get('/altcha/challenge')->action(ChallengeAction::class);
```

Каждый challenge стоит серверу одной выработки ключа (около 3 мс при `cost` по умолчанию, 60–110 мс на потолке
100 000 в зависимости от алгоритма), а маршрут публичный: поставьте перед ним ограничитель частоты.

### Виджет

`AltchaWidget` рисует тег `<altcha-widget>` виджета v3 (npm `altcha` 3.x), который берёт URL challenge из атрибута
`challenge` (виджеты v2 использовали `challengeurl`). Сам скрипт виджета подключает ваше приложение — зафиксируйте
конкретную версию, а не `latest`. Проверено в Chrome с `altcha` 3.7.0: все шесть алгоритмов решаются и проходят
проверку.

```php
use Rasuvaeff\Yii3Altcha\AltchaWidget;

echo AltchaWidget::widget()
    ->withChallengeUrl('/altcha/challenge')
    ->withFieldName('altcha')   // имя отправляемого поля формы, по умолчанию `altcha`
    ->withLanguage('ru')        // язык строк виджета; подключите соответствующий файл `altcha/i18n/ru`
    ->withAttributes(['auto' => 'onsubmit'])   // любой другой атрибут виджета
    ->render();
```

Если URL challenge находится на другом origin, отдавайте `Access-Control-Allow-Origin` с маршрута challenge.

### Валидация

`AltchaRule` подключается к `yiisoft/validator`; значение — сырая base64-строка, которую отправляет виджет:

```php
use Rasuvaeff\Yii3Altcha\AltchaRule;

final class RegisterForm
{
    #[AltchaRule]
    public string $altcha = '';
}
```

Обработчики правил резолвер `yiisoft/validator` по умолчанию создаёт без аргументов, поэтому обработчик берёт
верификатор из `AltchaRegistry`. Bootstrap пакета регистрирует там фабрику, поэтому верификатор создаётся при первой
проверке: приложение без секрета или кэша всё равно стартует и падает только тогда, когда форма проверяет CAPTCHA.
Переводчик ищется так же, на первом сообщении, которому он нужен.

Первая проверка тратит challenge. Валидируйте форму один раз за запрос: вторая валидация того же payload ответит
`replayed`. После любой неудачной отправки посетителю нужен новый challenge — виджет запросит его, когда страница
отрисуется снова. На одно свойство — одно `AltchaRule`.

### Прямая проверка

```php
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Rasuvaeff\Yii3Altcha\VerificationStatus;

$status = $verifier->verify($_POST['altcha'] ?? '');

if ($status !== VerificationStatus::Verified) {
    // $status->value: expired | invalid_signature | invalid_solution | replayed | malformed
}
```

`verify()` никогда не бросает исключение на враждебном входе — всё, что не декодируется, это `Malformed`. Ошибки
кэша повторов пробрасываются, включая отказ кэша записать маркер.

### Публичный API

| Класс | Описание |
|---|---|
| `AltchaConfig` | VO настроек с валидацией: secret, algorithm, cost, maxCounter, ttlSeconds |
| `AltchaAlgorithm` | Enum алгоритмов, которые умеет решать виджет |
| `ChallengeIssuer` | Выдаёт подписанный challenge с истечением в виде массива, который ждёт виджет |
| `ChallengeAction` | PSR-15 обработчик, отдающий challenge как JSON |
| `AltchaVerifier` | Локально проверяет присланный payload; опциональный кэш повторов тратит challenge при первом использовании |
| `VerificationStatus` | `Verified`, `Expired`, `InvalidSignature`, `InvalidSolution`, `Replayed`, `Malformed` |
| `AltchaRule` / `AltchaRuleHandler` | правило `yiisoft/validator` |
| `AltchaRegistry` | Статический держатель верификатора (или его ленивой фабрики) и переводчика для обработчика правила |
| `AltchaWidget` | Рисует тег `<altcha-widget>` (виджет v3: `challenge`, `name`, `language`, другие атрибуты) |

## Безопасность

- **Proof-of-work — это цена, а не доказательство человека.** Он делает массовую автоматизацию дорогой, но не
  невозможной; настойчивый бот с CPU всё равно пройдёт. Для важного сочетайте с ограничением частоты.
- **Секрет подписывает каждый challenge.** Без секрета подписи ничего не проверяется (fail-closed). Держите его в
  окружении, не короче 32 байт; ротация секрета обнуляет выданные challenge.
- **Challenge всегда истекают.** Отключить истечение нельзя; окно повторного использования — `ttlSeconds`.
- **Защита от повторного использования** (включена по умолчанию) тратит challenge через PSR-16 кэш на первой
  попытке с валидной подписью: решённый payload принимается один раз, а неверное решение нельзя повторить на том же
  challenge. Маркер живёт до истечения challenge плюс 60 с запаса на расхождение часов; его ключ в кэше — 64 символа,
  длина, которую обязан принять любой PSR-16 кэш. В PSR-16 нет атомарного add, поэтому две одновременные отправки
  одного payload могут обе пройти в крошечном окне; если форму обслуживают несколько воркеров, используйте общий кэш
  (Redis). Если кэш отказался записать маркер, бросается исключение, а не отключается защита молча. Если её
  выключить, одно решение годится для любого числа отправок до истечения.
- **Выдача стоит CPU, проверка — нет.** Выдача challenge выполняет одну выработку ключа (около 3 мс при `cost` по
  умолчанию — поэтому у `cost` потолок 100 000). Challenge несёт HMAC выработанного ключа, сделанный ключом,
  производным от вашего секрета, поэтому решение проверяется одним HMAC (микросекунды) без выработки: доказательство —
  сам выработанный ключ, присланный счётчик не сравнивается. Ограничивайте частоту на маршруте challenge.
- **Враждебный вход ограничен.** Payload больше 8 КиБ отклоняется до декодирования; подпись проверяется до любой
  выработки ключа, поэтому поддельный `cost` не сожжёт CPU сервера.
- Подписанные параметры — алгоритм, cost, истечение — берутся из challenge, а не из вашего конфига в момент проверки:
  изменение конфига действует только на новые challenge и никогда не обнуляет выданные. Истечение проверяется по
  системному времени, а если в `AltchaVerifier` переданы часы PSR-20 — ещё и по ним: challenge должен быть жив по
  обоим, поэтому часы, расходящиеся с системными, отклоняют больше и никогда не ослабляют защиту от повторов.

## Примеры

Исполняемые скрипты — в [examples/](examples/).
Примеры должны выполняться без фатальных ошибок и соответствовать
задокументированному публичному API.

| Скрипт | Что показывает | Нужен сервер? |
|---|---|---|
| [`round-trip.php`](examples/round-trip.php) | Выдача, решение как в браузере, проверка; отказ при повторе и на мусоре | нет |
| [`widget.php`](examples/widget.php) | Отрисовка тега `<altcha-widget>` | нет |

## Разработка

На хосте нет PHP/Composer — запуск через Docker, образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make install
make build
make cs-fix
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` и `make mutation` ставят `pcov` внутри контейнера
`composer:2`, потому что в базовом образе нет драйвера покрытия.

## Лицензия

[BSD-3-Clause](LICENSE.md)

# Telegram log target for Yii 2

[Telegram](https://telegram.org) log target for Yii 2.

![Screenshot](docs/README.png)

[![Code Quality](https://img.shields.io/scrutinizer/g/sergeymakinen/yii2-telegram-log.svg?style=flat-square)](https://scrutinizer-ci.com/g/sergeymakinen/yii2-telegram-log) [![Build Status](https://img.shields.io/travis/sergeymakinen/yii2-telegram-log.svg?style=flat-square)](https://travis-ci.org/sergeymakinen/yii2-telegram-log) [![Code Coverage](https://img.shields.io/codecov/c/github/sergeymakinen/yii2-telegram-log.svg?style=flat-square)](https://codecov.io/gh/sergeymakinen/yii2-telegram-log) [![SensioLabsInsight](https://img.shields.io/sensiolabs/i/8b4f3236-7c78-42d1-8355-54605598d941.svg?style=flat-square)](https://insight.sensiolabs.com/projects/8b4f3236-7c78-42d1-8355-54605598d941)

[![Packagist Version](https://img.shields.io/packagist/v/sergeymakinen/yii2-telegram-log.svg?style=flat-square)](https://packagist.org/packages/sergeymakinen/yii2-telegram-log) [![Total Downloads](https://img.shields.io/packagist/dt/sergeymakinen/yii2-telegram-log.svg?style=flat-square)](https://packagist.org/packages/sergeymakinen/yii2-telegram-log) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

## Installation

The preferred way to install this extension is through [composer](https://getcomposer.org/download/).

Either run

```bash
composer require "sergeymakinen/yii2-telegram-log:^2.0"
```

or add

```json
"sergeymakinen/yii2-telegram-log": "^2.0"
```

to the require section of your `composer.json` file.

## Usage

First [create a new bot](https://core.telegram.org/bots#6-botfather) and obtain its token. It should look like `123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11`.

You will also need a [chat ID](https://stackoverflow.com/questions/31078710/how-to-obtain-telegram-chat-id-for-a-specific-user) to send logs to. It should look like `123456789`.

Then set the following Yii 2 configuration parameters:

```php
'components' => [
    'log' => [
        'targets' => [
            [
                'class' => 'sergeymakinen\yii\telegramlog\Target',
                'token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
                'chatId' => 123456789,
            ],
        ],
    ],
],
```

## Configuration

By default `yii\log\Logger` error levels are mapped to emojis (you can tweak them in the `levelEmojis` property):

| Error level | Emoji
| --- | ---
`Logger::LEVEL_ERROR` | ☠️
`Logger::LEVEL_WARNING` | ⚠️
`Logger::LEVEL_INFO` | ℹ️
`Logger::LEVEL_TRACE` | 📝

It's also possible to disable notifications - entirely or per logger level (look at the `enableNotification` property), for example:

```php
public $enableNotification = [
    Logger::LEVEL_ERROR => true,
    Logger::LEVEL_WARNING => false,
    Logger::LEVEL_INFO => false,
];
```

This will disable notifications for warning and info level messages and enable them for other levels (honestly, you can omit the `LEVEL_ERROR` definition here as it's `true` by default).

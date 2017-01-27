<?php

namespace sergeymakinen\yii\telegramlog\tests;

use sergeymakinen\yii\logmessage\Message;
use sergeymakinen\yii\telegramlog\Target;
use sergeymakinen\yii\telegramlog\tests\stubs\TestController;
use sergeymakinen\yii\telegramlog\tests\stubs\TestException;
use sergeymakinen\yii\telegramlog\tests\stubs\TestIdentity;
use sergeymakinen\yii\telegramlog\tests\stubs\TestSession;
use sergeymakinen\yii\telegramlog\tests\stubs\TestUser;
use yii\base\ErrorHandler;
use yii\helpers\ArrayHelper;
use yii\helpers\UnsetArrayValue;
use yii\httpclient\Client;
use yii\httpclient\Request;
use yii\httpclient\Response;
use yii\log\Logger;
use yii\web\Application as WebApplication;

class TargetTest extends TestCase
{
    protected static $value;

    protected function mockClient($success)
    {
        $response = $this->createMock(Response::className());
        if ($success) {
            $response
                ->method('getIsOk')
                ->willReturn(true);
            $response
                ->method('getContent')
                ->willReturn('success');
            $response
                ->method('getStatusCode')
                ->willReturn(200);
        } else {
            $response
                ->method('getIsOk')
                ->willReturn(false);
            $response
                ->method('getContent')
                ->willReturn('error');
            $response
                ->method('getStatusCode')
                ->willReturn(404);
        }
        $request = $this->createMock(Request::className());
        $request
            ->method('setFormat')
            ->willReturnSelf();
        $request
            ->method('send')
            ->willReturn($response);
        $client = $this->createMock(Client::className());
        $client
            ->method('post')
            ->willReturn($request);
        return $client;
    }

    public function testRealExport()
    {
        $configPath = \Yii::getAlias('@tests/config-local.php');
        if (!is_file($configPath)) {
            $this->markTestSkipped('No config file: ' . $configPath);
            return;
        }

        \Yii::$app->log->targets['telegram'] = new Target();
        $config = require $configPath;
        \Yii::error('It happened again...', __METHOD__);
        \Yii::$app->log->logger->flush();
        \Yii::$app->log->targets['telegram']->token = $config['token'];
        \Yii::$app->log->targets['telegram']->chatId = $config['chatId'];
        \Yii::$app->log->targets['telegram']->export();
    }

    /**
     * @covers \sergeymakinen\yii\telegramlog\Target::export()
     */
    public function testExportOk()
    {
        \Yii::error(ErrorHandler::convertExceptionToString(new TestException('Hello & <world> 🌊')), __METHOD__);
        \Yii::$app->log->logger->flush();
        \Yii::$app->log->targets['telegram']->httpClient = $this->mockClient(true);
        \Yii::$app->log->targets['telegram']->export();
    }

    /**
     * @covers \sergeymakinen\yii\telegramlog\Target::export()

     * @expectedException \yii\base\InvalidValueException
     * @expectedExceptionCode 404
     */
    public function testExportError()
    {
        \Yii::error(ErrorHandler::convertExceptionToString(new TestException('Hello & <world> 🌊')), __METHOD__);
        \Yii::$app->log->logger->flush();
        \Yii::$app->log->targets['telegram']->httpClient = $this->mockClient(false);
        \Yii::$app->log->targets['telegram']->export();
    }

    public function messagesProvider()
    {
        $commandLine = implode(' ', $_SERVER['argv']);
        $cases = [
            'custom web' => [
                'web',
                [],
                [
                    'foobar',
                    Logger::LEVEL_ERROR,
                    'category',
                    123456,
                    [
                        [
                            'file' => 'file',
                            'line' => 123,
                        ]
                    ],
                ],
                [
                    'chat_id' => null,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                    'disable_notification' => false,
                    'text' => '[http://example.com/index.php?r=test](http://example.com/index.php?r=test)
*Level*: error

```text
foobar
```

*Category*: `category`
*Prefix*: `foo`
*User IP*: 0.0.0.0
*User ID*: `userId`
*Session ID*: `session_id`
*Stack Trace*:
```text
in file:123
```

ℹ `sergeymakinen\yii\telegramlog\tests\TargetTest`
',
                ],

            ],
            'custom console with error' => [
                'console',
                [],
                [
                    'foobar',
                    Logger::LEVEL_ERROR,
                    'category',
                    123456,
                    [
                        [
                            'file' => 'file',
                            'line' => 123,
                        ]
                    ],
                ],
                [
                    'chat_id' => null,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                    'disable_notification' => false,
                    'text' => $commandLine . '
*Level*: error

```text
foobar
```

*Category*: `category`
*Prefix*: `foo`
*Stack Trace*:
```text
in file:123
```

',
                ]
            ],
            'custom console with info' => [
                'console',
                [],
                [
                    'foobar',
                    Logger::LEVEL_INFO,
                    'category',
                    123456,
                    [
                        [
                            'file' => 'file',
                            'line' => 123,
                        ]
                    ],
                ],
                [
                    'chat_id' => null,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                    'disable_notification' => true,
                    'text' => $commandLine . '
*Level*: info

```text
foobar
```

*Category*: `category`
*Prefix*: `foo`
*Stack Trace*:
```text
in file:123
```

',
                ]
            ],
        ];
        if (class_exists('yii\helpers\UnsetArrayValue')) {
            $cases['default console with notifications'] = [
                'console',
                [
                    'targets' => [
                        'telegram' => [
                            'template' => new UnsetArrayValue(),
                            'substitutions' => new UnsetArrayValue()
                        ]
                    ],
                ],
                [
                    'foobar',
                    Logger::LEVEL_ERROR,
                    'category',
                    123456,
                    [
                        [
                            'file' => 'file',
                            'line' => 123,
                        ]
                    ],
                ],
                [
                    'chat_id' => NULL,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                    'disable_notification' => false,
                    'text' => '☠️ `' . $commandLine . '`

```text
foobar
```

📖 `category`
*Stack Trace*:
```text
in file:123
```',
                ]
            ];
            $cases['default web'] = [
                'web',
                [
                    'targets' => [
                        'telegram' => [
                            'template' => new UnsetArrayValue(),
                            'substitutions' => new UnsetArrayValue(),
                            'enableNotification' => new UnsetArrayValue()
                        ]
                    ],
                ],
                [
                    'foobar',
                    Logger::LEVEL_PROFILE,
                    'category',
                    123456,
                    [
                        [
                            'file' => 'file',
                            'line' => 123,
                        ]
                    ],
                ],
                [
                    'chat_id' => NULL,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                    'disable_notification' => false,
                    'text' => '*Profile* @ [http://example.com/index.php?r=test](http://example.com/index.php?r=test)

```text
foobar
```

📖 `category`
🙂 0.0.0.0    ID: `userId`
*Stack Trace*:
```text
in file:123
```',
                ]
            ];
        }
        return $cases;
    }

    /**
     * @dataProvider messagesProvider
     * @param string $type
     * @param array $config
     * @param array $message
     * @param array $expected
     */
    public function testFormatMessageRequest($type, array $config, array $message, array $expected)
    {
        static::$value = __CLASS__;
        $this->tearDown();
        if ($type === 'console') {
            $this->createConsoleApplication([
                'components' => [
                    'log' => $this->getLogConfig($config),
                ],
            ]);
        } else {
            $this->createWebApplication([
                'components' => [
                    'log' => $this->getLogConfig($config),
                    'session' => [
                        'class' => TestSession::className(),
                    ],
                    'user' => [
                        'class' => TestUser::className(),
                        'identityClass' => TestIdentity::className(),
                    ],
                ],
            ]);
            \Yii::$app->controller = new TestController('test', \Yii::$app);
            \Yii::$app->session->isActive;
            \Yii::$app->user->getIdentity();
        }
        //var_export($this->invokeInaccessibleMethod(\Yii::$app->log->targets['telegram'], 'formatMessageRequest', [$message]));
        $this->assertEquals($expected, $this->invokeInaccessibleMethod(\Yii::$app->log->targets['telegram'], 'formatMessageRequest', [$message]));
    }

    protected function setUp()
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '0.0.0.0';
        $this->createWebApplication([
            'components' => [
                'log' => $this->getLogConfig(),
                'session' => [
                    'class' => TestSession::className(),
                ],
                'user' => [
                    'class' => TestUser::className(),
                    'identityClass' => TestIdentity::className(),
                ],
            ],
        ]);
        \Yii::$app->controller = new TestController('test', \Yii::$app);
        \Yii::$app->session->isActive;
        \Yii::$app->user->getIdentity();
        static::$value = null;
    }

    protected function tearDown()
    {
        \Yii::$app->log->targets['telegram']->messages = [];
        \Yii::$app->log->logger->messages = [];
        parent::tearDown();
    }

    protected function getLogConfig(array $config = [])
    {
        return ArrayHelper::merge([
            'targets' => [
                'telegram' => [
                    'class' => Target::className(),
                    'levels' => ['error', 'info'],
                    'categories' => [
                        __NAMESPACE__ . '\*',
                    ],
                    'enableNotification' => [
                        Logger::LEVEL_INFO => false,
                    ],
                    'token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
                    'template' => '{request}
{level}

{text}

{category}
{prefix}
{userIp}
{userId}
{sessionId}
{stackTrace}

{static}
{notExisting}',
                    'substitutions' => [
                        'static' => [
                            'emojiTitle' => 'ℹ',
                            'short' => true,
                            'wrapAsCode' => true,
                            'value' => function (Message $message) {
                                if (\Yii::$app instanceof WebApplication) {
                                    return static::$value;
                                } else {
                                    return null;
                                }
                            }
                        ],
                        'request' => [
                            'title' => null,
                            'short' => false,
                            'wrapAsCode' => false,
                            'value' => function (Message $message) {
                                if ($message->getIsConsoleRequest()) {
                                    return $message->getCommandLine();
                                } else {
                                    return '[' . $message->getUrl() . '](' . $message->getUrl() . ')';
                                }
                            },
                        ],
                        'level' => [
                            'title' => 'Level',
                            'short' => true,
                            'wrapAsCode' => false,
                            'value' => function (Message $message) {
                                return $message->getLevel();
                            },
                        ],
                        'category' => [
                            'title' => 'Category',
                            'short' => true,
                            'wrapAsCode' => true,
                            'value' => function (Message $message) {
                                return $message->getCategory();
                            },
                        ],
                        'prefix' => [
                            'title' => 'Prefix',
                            'short' => true,
                            'wrapAsCode' => true,
                            'value' => function (Message $message) {
                                return $message->getPrefix();
                            },
                        ],
                        'userIp' => [
                            'title' => 'User IP',
                            'short' => true,
                            'wrapAsCode' => false,
                            'value' => function (Message $message) {
                                return $message->getUserIp();
                            },
                        ],
                        'userId' => [
                            'title' => 'User ID',
                            'short' => true,
                            'wrapAsCode' => true,
                            'value' => function (Message $message) {
                                return $message->getUserId();
                            },
                        ],
                        'sessionId' => [
                            'title' => 'Session ID',
                            'short' => true,
                            'wrapAsCode' => true,
                            'value' => function (Message $message) {
                                return $message->getSessionId();
                            },
                        ],
                        'stackTrace' => [
                            'title' => 'Stack Trace',
                            'short' => false,
                            'wrapAsCode' => true,
                            'value' => function (Message $message) {
                                return $message->getStackTrace();
                            },
                        ],
                        'text' => [
                            'title' => null,
                            'short' => false,
                            'wrapAsCode' => true,
                            'value' => function (Message $message) {
                                return $message->getText();
                            },
                        ],
                    ],
                    'prefix' => function () {
                        return 'foo';
                    },
                    'logVars' => [],
                ],
            ],
        ], $config);
    }
}

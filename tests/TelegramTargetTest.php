<?php

namespace sergeymakinen\tests\telegramlog;

use sergeymakinen\log\Message;
use sergeymakinen\log\TelegramTarget;
use sergeymakinen\tests\telegramlog\stubs\TestController;
use sergeymakinen\tests\telegramlog\stubs\TestException;
use yii\base\ErrorHandler;
use yii\httpclient\Client;
use yii\httpclient\Request;
use yii\httpclient\Response;
use yii\log\Logger;
use yii\web\Application as WebApplication;

class TelegramTargetTest extends TestCase
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

    public function testExportReal()
    {
        $configPath = \Yii::getAlias('@tests/config-local.php');
        if (!is_file($configPath)) {
            $this->markTestSkipped('No config file: ' . $configPath);
            return;
        }

        $config = require $configPath;
        \Yii::error('It happened again...', __METHOD__);
        \Yii::$app->log->logger->flush();
        \Yii::$app->log->targets['telegram']->token = $config['token'];
        \Yii::$app->log->targets['telegram']->chatId = $config['chatId'];
        \Yii::$app->log->targets['telegram']->export();
    }

    /**
     * @covers \sergeymakinen\log\TelegramTarget::export()
     */
    public function testExportOk()
    {
        \Yii::error(ErrorHandler::convertExceptionToString(new TestException('Hello & <world> 🌊')), __METHOD__);
        \Yii::$app->log->logger->flush();
        \Yii::$app->log->targets['telegram']->httpClient = $this->mockClient(true);
        \Yii::$app->log->targets['telegram']->export();
    }

    /**
     * @covers \sergeymakinen\log\TelegramTarget::export()
     *
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

    public function testFormatMessageRequest()
    {
        static::$value = __CLASS__;
        $message = [
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
        ];
        $this->assertEquals([
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

*Static*:
```text
sergeymakinen\tests\telegramlog\TelegramTargetTest
```
',
        ], $this->invokeInaccessibleMethod(\Yii::$app->log->targets['telegram'], 'formatMessageRequest', [$message]));

        $this->tearDown();
        $this->createConsoleApplication([
            'components' => [
                'log' => $this->getLogConfig(),
            ],
        ]);

        $commandLine = implode(' ', $_SERVER['argv']);
        $this->assertEquals([
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
        ], $this->invokeInaccessibleMethod(\Yii::$app->log->targets['telegram'], 'formatMessageRequest', [$message]));
    }

    protected function setUp()
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '0.0.0.0';
        $this->createWebApplication([
            'components' => [
                'log' => $this->getLogConfig(),
                'session' => [
                    'class' => 'sergeymakinen\tests\telegramlog\stubs\TestSession',
                ],
                'user' => [
                    'class' => 'sergeymakinen\tests\telegramlog\stubs\TestUser',
                    'identityClass' => 'sergeymakinen\tests\telegramlog\stubs\TestIdentity',
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

    protected function getLogConfig()
    {
        $defaultSubstitutions = (new TelegramTarget())->substitutions;
        return [
            'targets' => [
                'telegram' => [
                    'class' => 'sergeymakinen\log\TelegramTarget',
                    'levels' => ['error', 'info'],
                    'categories' => [
                        'sergeymakinen\tests\*',
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
                    'substitutions' => array_merge($defaultSubstitutions, [
                        'static' => [
                            'title' => 'Static',
                            'short' => false,
                            'wrapAsCode' => true,
                            'value' => function (Message $message) {
                                if (\Yii::$app instanceof WebApplication) {
                                    return static::$value;
                                } else {
                                    return null;
                                }
                            }
                        ]
                    ]),
                    'prefix' => function () {
                        return 'foo';
                    },
                    'logVars' => [],
                ],
            ],
        ];
    }
}

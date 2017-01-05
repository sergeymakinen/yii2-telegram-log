<?php
/**
 * Telegram log target for Yii 2
 *
 * @see       https://github.com/sergeymakinen/yii2-telegram-log
 * @copyright Copyright (c) 2017 Sergey Makinen (https://makinen.ru)
 * @license   https://github.com/sergeymakinen/yii2-telegram-log/blob/master/LICENSE MIT License
 */

namespace sergeymakinen\log;

use yii\base\InvalidValueException;
use yii\di\Instance;
use yii\httpclient\Client;
use yii\log\Target;

class TelegramTarget extends Target
{
    /**
     * @var Client|array|string Yii HTTP client configuration.
     * This can be a component ID, a configuration array or a Client instance.
     */
    public $httpClient = [
        'class' => 'yii\httpclient\Client',
    ];

    /**
     * @var string bot token.
     * @see https://core.telegram.org/bots/api#authorizing-your-bot
     */
    public $token;

    /**
     * @var int|string unique identifier for the target chat or username of the target channel
     * (in the format `{@}channelusername`).
     */
    public $chatId;

    /**
     * @var string log message template.
     */
    public $template = '{request}
{level}

{text}

{category}
{prefix}
{userIp}
{userId}
{sessionId}
{stackTrace}';

    /**
     * @var array log message template substitutions.
     * [[defaultSubstitutions()]] will be used by default.
     */
    public $substitutions;

    /**
     * @var bool whether to enable link previews for links in the message.
     */
    public $enableWebPagePreview = false;

    /**
     * @var bool whether to send the message silently.
     * iOS users will not receive a notification, Android users will receive a notification with no sound.
     */
    public $enableNotification = true;

    /**
     * @inheritDoc
     */
    public $exportInterval = 1;

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();
        $this->httpClient = Instance::ensure($this->httpClient, Client::className());
        if ($this->substitutions === null) {
            $this->substitutions = $this->defaultSubstitutions();
        }
    }

    /**
     * @inheritDoc
     */
    public function export()
    {
        foreach (array_map([$this, 'formatMessageRequest'], $this->messages) as $request) {
            $response = $this->httpClient
                ->post('https://api.telegram.org/bot' . $this->token . '/sendMessage', $request)
                ->setFormat(Client::FORMAT_JSON)
                ->send();
            if (!$response->getIsOk()) {
                if (isset($response->getData()['description'])) {
                    $description = $response->getData()['description'];
                } else {
                    $description = $response->getContent();
                }
                throw new InvalidValueException(
                    'Unable to send logs to Telegram: ' . $description, (int) $response->getStatusCode()
                );
            }
        }
    }

    /**
     * Returns a `sendMessage` request for Telegram.
     * @param array $message
     * @return array
     */
    protected function formatMessageRequest($message)
    {
        $message = new Message($message, $this);
        $request = [
            'chat_id' => $this->chatId,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => !$this->enableWebPagePreview,
            'disable_notification' => !$this->enableNotification,
        ];
        $request['text'] = preg_replace_callback('/{([^}]+)}([\n]*|$)/', function (array $matches) use ($message) {
            if (isset($this->substitutions[$matches[1]])) {
                $value = $this->substitute($matches[1], $message);
                if ($value !== '') {
                    return $value . $matches[2];
                }
            }
            return '';
        }, $this->template);
        return $request;
    }

    /**
     * Returns an array with the default substitutions.
     * @return array the default substitutions.
     */
    protected function defaultSubstitutions()
    {
        return [
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
        ];
    }

    /**
     * Returns a substituted value.
     * @param string $name
     * @param Message $message
     * @return string
     */
    private function substitute($name, Message $message)
    {
        $config = $this->substitutions[$name];
        $value = (string) call_user_func($config['value'], $message);
        if ($value === '') {
            return '';
        }

        if ($config['wrapAsCode']) {
            if ($config['short']) {
                $value = '`' . $value . '`';
            } else {
                $value = "```text\n" . $value . "\n```";
            }
        }
        if ($config['title'] !== null) {
            if ($config['short']) {
                $value = "*{$config['title']}*: {$value}";
            } else {
                $value = "*{$config['title']}*:\n{$value}";
            }
        }
        return $value;
    }
}

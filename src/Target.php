<?php
/**
 * Telegram log target for Yii 2
 *
 * @see       https://github.com/sergeymakinen/yii2-telegram-log
 * @copyright Copyright (c) 2017 Sergey Makinen (https://makinen.ru)
 * @license   https://github.com/sergeymakinen/yii2-telegram-log/blob/master/LICENSE MIT License
 */

namespace sergeymakinen\yii\telegramlog;

use sergeymakinen\yii\logmessage\Message;
use yii\base\InvalidValueException;
use yii\di\Instance;
use yii\helpers\StringHelper;
use yii\httpclient\Client;
use yii\log\Logger;

class Target extends \yii\log\Target
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
    public $template = '{levelAndRequest}

{text}

{category}
{user}
{stackTrace}';

    /**
     * @var array log message template substitutions.
     * [[defaultSubstitutions()]] will be used by default.
     */
    public $substitutions;

    /**
     * @var bool|bool[] whether to send the message silently (`bool` or an array of `bool` per a logger level).
     * iOS users will not receive a notification, Android users will receive a notification with no sound.
     */
    public $enableNotification = true;

    /**
     * @var string[] level emoji per a logger level.
     * @since 2.0
     */
    public $levelEmojis = [
        Logger::LEVEL_ERROR => '☠️',
        Logger::LEVEL_WARNING => '⚠️',
        Logger::LEVEL_INFO => 'ℹ️',
        Logger::LEVEL_TRACE => '📝',
    ];

    /**
     * @var int max character in message text.
     */
    public $messageMaxLength = 3000;

    /**
     * @inheritDoc
     */
    public function __construct($config = [])
    {
        $this->exportInterval = 1;
        parent::__construct($config);
    }

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
     * @throws \yii\base\InvalidValueException
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
     * @param array $message raw message.
     * @return array request as an array.
     */
    protected function formatMessageRequest($message)
    {
        $message = new Message($message, $this);
        $request = [
            'chat_id' => $this->chatId,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
            'disable_notification' => false,
        ];
        if (isset($this->enableNotification[$message->message[1]])) {
            $request['disable_notification'] = !$this->enableNotification[$message->message[1]];
        } elseif (is_bool($this->enableNotification)) {
            $request['disable_notification'] = !$this->enableNotification;
        }
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
     * @return array default substitutions.
     */
    protected function defaultSubstitutions()
    {
        return [
            'levelAndRequest' => [
                'title' => null,
                'short' => false,
                'wrapAsCode' => false,
                'value' => function (Message $message) {
                    if (isset($this->levelEmojis[$message->message[1]])) {
                        $value = $this->levelEmojis[$message->message[1]] . ' ';
                    } else {
                        $value = '*' . ucfirst($message->getLevel()) . '* @ ';
                    }
                    if ($message->getIsConsoleRequest()) {
                        $value .= '`' . $message->getCommandLine() . '`';
                    } else {
                        $value .= '[' . $message->getUrl() . '](' . $message->getUrl() . ')';
                    }
                    return $value;
                },
            ],
            'category' => [
                'emojiTitle' => '📖',
                'short' => true,
                'wrapAsCode' => false,
                'value' => function (Message $message) {
                    return '`' . $message->getCategory() . '`';
                },
            ],
            'user' => [
                'emojiTitle' => '🙂',
                'short' => true,
                'wrapAsCode' => false,
                'value' => function (Message $message) {
                    $value = [];
                    $ip = $message->getUserIp();
                    if ((string) $ip !== '') {
                        $value[] = $ip;
                    }
                    $id = $message->getUserId();
                    if ((string) $id !== '') {
                        $value[] = "ID: `{$id}`";
                    }
                    return implode(str_repeat(' ', 4), $value);
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
                $value = "```text\n" . StringHelper::truncate($value, $this->messageMaxLength) . "\n```";
            }
        }
        if (isset($config['title'])) {
            $separator = $config['short'] ? ' ' : "\n";
            $value = "*{$config['title']}*:{$separator}{$value}";
        } elseif (isset($config['emojiTitle'])) {
            $value = "{$config['emojiTitle']} {$value}";
        }
        return $value;
    }
}

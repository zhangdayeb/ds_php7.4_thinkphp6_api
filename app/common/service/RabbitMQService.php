<?php

namespace app\common\service;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use think\Exception;

class RabbitMQService
{
    public $connection;
    public $channel;

    public function __construct($config)
    {
        $this->connection = new AMQPStreamConnection(
            $config['host'], $config['port'], $config['username'], $config['password'], $config['vhost'], array('heartbeat' => 60)
        );
        $this->channel = $this->connection->channel();
    }

    /**
     * 发布消息
     * @param mixed $exchange
     * @param mixed $routing_key
     * @param mixed $message
     * @throws \think\Exception
     * @return void
     */
    public function publish($exchange, $routing_key, $message)
    {
        try {
            rebegin:
            $this->channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);
            $msg = new AMQPMessage($message);
            $this->channel->basic_publish($msg, $exchange, $routing_key);

            $this->channel->close();
            $this->connection->close();
        } catch (\Throwable $throwable) {
            goto rebegin;
            throw new Exception($throwable->getMessage());
        }
    }

    /**
     * 消费消息
     * @param mixed $exchange
     * @param mixed $routingKey
     * @param mixed $callback
     * @return void
     */
    public function consume($exchange, $routingKey, $callback)
    {
        $this->channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);
        // 定义队列名称
        $queueName = $this->generateQueueName($exchange, $routingKey);
        // 检查并声明队列
        if (!$this->checkQueue($queueName)) {
            $this->channel->queue_declare($queueName, false, true, false, false);
        }
        // 绑定队列到交换器
        $this->channel->queue_bind($queueName, $exchange, $routingKey);
        // 设置回调并开始消费
        try {
            // 设置回调并开始消费
            echo " [*] Waiting for messages. To exit press CTRL+C\n";
            $this->channel->basic_consume($queueName, '', false, true, false, false, $callback);
            while (count($this->channel->callbacks)) {
                $this->channel->wait();
            }
        } catch (\Throwable $e) {
            echo " [!] " . $e->getMessage() . "\n";
        }
    }

    /**
     * 生成队列名称
     * @param mixed $exchange
     * @param mixed $routingKey
     * @return string
     */
    private function generateQueueName($exchange, $routingKey)
    {
        return $exchange . '.' . $routingKey;
    }

    /**
     * 检测队列
     * @param mixed $queueName
     * @return bool
     */
    private function checkQueue($queueName)
    {
        try {
            $this->channel->queue_declare($queueName, false, false, false, false);
            return true;
        } catch (\Throwable $e) {
            echo " [!] " . $e->getMessage() . "\n";
            return false;
        }
    }
}
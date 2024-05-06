<?php

namespace QUI\ERP\Order\OrderProcess;

class OrderProcessMessage
{
    const MESSAGE_TYPE_INFO    = 'info';
    const MESSAGE_TYPE_ERROR   = 'error';
    const MESSAGE_TYPE_SUCCESS = 'success';

    /**
     * @var string
     */
    protected string $type;

    /**
     * @var string
     */
    protected string $msg;

    /**
     * OrderProcessMessage constructor.
     *
     * @param string $msg
     * @param string $type
     */
    public function __construct(string $msg, string $type = self::MESSAGE_TYPE_INFO)
    {
        $this->msg  = $msg;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getMsg(): string
    {
        return $this->msg;
    }
}

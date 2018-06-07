<?php

namespace dipl\Validator;

trait MessageContainer
{
    protected $messages = [];

    public function getMessages()
    {
        return $this->messages;
    }

    public function addMessage($message)
    {
        $args = func_get_args();
        array_shift($args);
        if (empty($args)) {
            $this->messages[] = $message;
        } else {
            $this->messages[] = vsprintf($message, $args);
        }

        return $this;
    }

    public function setMessages(array $messages)
    {
        $this->messages = $messages;

        return $this;
    }

    public function clearMessages()
    {
        return $this->setMessages([]);
    }
}

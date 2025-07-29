<?php

class HelloModel
{
    private $messages;

    public function __construct()
    {
        $this->messages = [
            'default' => 'Hello World from PHP OpenSwoole Microservice!',
            'welcome' => 'Welcome to our microservice architecture!',
            'goodbye' => 'Thank you for using our service!'
        ];
    }

    public function getDefaultMessage()
    {
        return $this->messages['default'];
    }

    public function getPersonalizedMessage($name)
    {
        return "Hello {$name}! Welcome to our PHP OpenSwoole microservice!";
    }

    public function createGreeting($greeting, $name)
    {
        return "{$greeting} {$name}! Hope you're having a great day!";
    }

    public function getAllMessages()
    {
        return $this->messages;
    }

    public function addMessage($key, $message)
    {
        $this->messages[$key] = $message;
    }

    public function getMessage($key)
    {
        return $this->messages[$key] ?? null;
    }

    public function getRandomMessage()
    {
        $keys = array_keys($this->messages);
        $randomKey = $keys[array_rand($keys)];
        return $this->messages[$randomKey];
    }
}
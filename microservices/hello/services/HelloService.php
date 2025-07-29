<?php

class HelloService
{
    private $helloModel;

    public function __construct()
    {
        $this->helloModel = new HelloModel();
    }

    public function getHelloMessage()
    {
        return $this->helloModel->getDefaultMessage();
    }

    public function getCustomHello($name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Name cannot be empty');
        }

        return $this->helloModel->getPersonalizedMessage($name);
    }

    public function getGreeting($name, $language)
    {
        $greetings = [
            'en' => 'Hello',
            'ar' => 'مرحبا',
            'fr' => 'Bonjour',
            'es' => 'Hola',
            'de' => 'Hallo'
        ];

        $greeting = $greetings[$language] ?? $greetings['en'];

        return $this->helloModel->createGreeting($greeting, $name);
    }

    public function validateName($name)
    {
        return !empty(trim($name)) && strlen($name) <= 100;
    }

    public function sanitizeName($name)
    {
        return htmlspecialchars(trim($name), ENT_QUOTES, 'UTF-8');
    }
}
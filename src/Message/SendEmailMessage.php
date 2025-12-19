<?php
namespace App\Message;

class SendEmailMessage
{
    private string $email;
    private string $content;
    private string $subject;

    public function __construct(string $email, string $content, string $subject = 'Notification')
    {
        $this->email = $email;
        $this->content = $content;
        $this->subject = $subject;
    }

    public function getEmail(): string { return $this->email; }
    public function getContent(): string { return $this->content; }
    public function getSubject(): string { return $this->subject; }
}

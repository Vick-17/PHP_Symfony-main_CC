<?php
namespace App\Service;

class SmsService
{
    public function send(string $telephone, string $message): void
    {
        // Simuler l'envoi de SMS en enregistrant dans un fichier
        file_put_contents('sms_log.txt', "SMS envoyé à $telephone : $message\n", FILE_APPEND);
    }
}
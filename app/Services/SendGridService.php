<?php

namespace App\Services;

use SendGrid\Mail\Mail;
use SendGrid;

class SendGridService
{
    public static function sendMail($to, $subject, $contentText, $contentHtml = null)
    {
        $email = new Mail();
        $email->setFrom(env('SENDGRID_FROM_EMAIL'), "88Labs");
        $email->setSubject($subject);
        $email->addTo($to);
        $email->addContent("text/plain", $contentText);
        if ($contentHtml) {
            $email->addContent("text/html", $contentHtml);
        }

        $sendgrid = new SendGrid(env('SENDGRID_API_KEY'));

        try {
            $response = $sendgrid->send($email);
            return [
                "status" => $response->statusCode(),
                "body" => $response->body()
            ];
        } catch (\Exception $e) {
            return [
                "error" => $e->getMessage()
            ];
        }
    }
}

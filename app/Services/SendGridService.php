<?php

namespace App\Services;

use App\Mail\DynamicTemplateMail;
use Illuminate\Support\Facades\Mail;

class SendGridService
{
    public static function sendMail(
        string $to,
        string $subject,
        string $contentText,
        ?string $contentHtml = null,
        array $templateData = []
    ): array
    {
        $resolvedTemplateData = self::buildTemplateData($subject, $contentText, $templateData);

        try {
            Mail::to($to)->send(new DynamicTemplateMail($subject, $resolvedTemplateData));

            return [
                'status' => 200,
                'body' => 'Mail sent successfully.',
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    protected static function buildTemplateData(string $subject, string $contentText, array $templateData): array
    {
        $textLines = preg_split('/\r\n|\r|\n/', $contentText) ?: [];
        $textLines = array_values(array_filter(array_map('trim', $textLines), static fn ($line) => $line !== ''));

        return array_merge([
            'heading' => $subject,
            'lines' => $textLines,
            'footer_lines' => [],
        ], $templateData);
    }
}

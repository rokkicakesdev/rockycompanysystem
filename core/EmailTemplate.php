<?php
// core/EmailTemplate.php
// ─────────────────────────────────────────────────────────────────────────────
//  EmailTemplate — renders HTML email templates from files.
//
//  Templates live in:  app/views/emails/*.php
//  Each template is a plain PHP file that uses $vars['key'] for its variables.
//  This approach keeps all HTML out of controller/view logic files,
//  makes templates previewable in a browser, and eliminates heredoc linter noise.
//
//  Usage:
//    $html = EmailTemplate::render('leave_notification', [
//        'company'     => COMPANY_NAME,
//        'name'        => 'Juan dela Cruz',
//        'statusWord'  => 'Approved',
//        'statusColor' => '#16a34a',
//        // ...
//    ]);
//    Mailer::send($email, $name, 'Subject', $html);
//
//  Template file: app/views/emails/leave_notification.php
//    It receives a $vars array — access values as $vars['company'], $vars['name'], etc.
//    The file should output (echo/print) its HTML. The renderer captures the output.
// ─────────────────────────────────────────────────────────────────────────────

declare(strict_types=1);

final class EmailTemplate
{
    // Absolute path to the email templates directory
    private static string $templateDir = '';

    // ── Render ────────────────────────────────────────────────────────────────
    /**
     * Render a named email template with the given variables.
     *
     * @param string $templateName  Filename without .php extension (e.g. 'leave_notification')
     * @param array  $vars          Key-value pairs accessible as $vars['key'] in the template
     * @return string               Rendered HTML ready to pass to Mailer::send()
     *
     * @throws RuntimeException     If the template file is not found
     */
    public static function render(string $templateName, array $vars = []): string
    {
        $dir  = self::getTemplateDir();
        $file = $dir . '/' . $templateName . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException(
                "EmailTemplate: template file not found — {$file}"
            );
        }

        // Capture output from the template file
        ob_start();
        (static function(string $_file, array $vars): void {
            include $_file;
        })($file, $vars);

        $html = ob_get_clean();
        return $html !== false ? $html : '';
    }

    // ── Directory resolution ──────────────────────────────────────────────────
    private static function getTemplateDir(): string
    {
        if (self::$templateDir === '') {
            // Resolve relative to this file: core/ → app/views/emails/
            self::$templateDir = realpath(__DIR__ . '/../app/views/emails') ?: '';
        }
        return self::$templateDir;
    }
}

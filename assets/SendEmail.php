<?php

declare(strict_types=1);

namespace Assets;

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

class SendEmail
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function send(
        string $to,
        string $subject,
        string $body,
        string $cc = '',
        string $bcc = '',
        ?array $files = null
    ): string {
        $validator = (new Validator([
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ]))
            ->required('to')
            ->email('to')
            ->required('subject')
            ->required('body');

        if ($validator->fails()) {
            return Response::encode(
                Response::validationPayload($validator->errors(), 'Dati email non validi')
            );
        }

        $smtp = $this->getSmtpConfigFromDb();
        if ($smtp === null) {
            return Response::encode(
                Response::errorPayload('Configurazione SMTP non trovata nel database')
            );
        }

        $missingField = $this->missingSmtpField($smtp);
        if ($missingField !== null) {
            return Response::encode(
                Response::errorPayload("Configurazione SMTP incompleta: {$missingField}")
            );
        }

        $mail = $this->buildMailer($smtp);

        try {
            $mail->addAddress($to);
            $this->addRecipients($mail, $cc, 'cc');
            $this->addRecipients($mail, $bcc, 'bcc');

            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            $this->addAttachments($mail, $files ?? $_FILES);
            $mail->send();

            return Response::encode(
                Response::okPayload([], 'Email inviata con successo')
            );
        } catch (MailerException | Throwable $e) {
            return Response::encode(
                Response::errorPayload('Errore invio email: ' . $mail->ErrorInfo)
            );
        }
    }

    private function getSmtpConfigFromDb(): ?array
    {
        try {
            $rows = $this->db->selectQuery(
                'SELECT host, port, secure, username, password, from_email, from_name
                 FROM smtp_config
                 WHERE is_active = 1
                 ORDER BY id DESC
                 LIMIT 1'
            );
        } catch (Throwable $e) {
            return null;
        }

        if (!is_array($rows) || count($rows) === 0) {
            return null;
        }

        $row = $rows[0];

        return [
            'host' => (string) ($row['host'] ?? ''),
            'port' => (int) ($row['port'] ?? 587),
            'secure' => strtolower((string) ($row['secure'] ?? 'tls')) === 'ssl' ? 'ssl' : 'tls',
            'username' => (string) ($row['username'] ?? ''),
            'password' => (string) ($row['password'] ?? ''),
            'from_email' => (string) ($row['from_email'] ?? ''),
            'from_name' => (string) ($row['from_name'] ?? ''),
        ];
    }

    private function missingSmtpField(array $smtp): ?string
    {
        $required = ['host', 'username', 'password', 'from_email'];

        foreach ($required as $field) {
            if (!isset($smtp[$field]) || trim((string) $smtp[$field]) === '') {
                return $field;
            }
        }

        return null;
    }

    private function buildMailer(array $smtp): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Port = (int) $smtp['port'];
        $mail->SMTPSecure = ((string) $smtp['secure']) === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Username = (string) $smtp['username'];
        $mail->Password = (string) $smtp['password'];
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Priority = 3;
        $mail->setFrom((string) $smtp['from_email'], (string) $smtp['from_name']);

        return $mail;
    }

    private function addRecipients(PHPMailer $mail, string $raw, string $type): void
    {
        $list = array_filter(
            array_map('trim', explode(';', $raw)),
            static fn(string $email): bool => $email !== ''
        );

        foreach ($list as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            if ($type === 'cc') {
                $mail->addCC($email);
                continue;
            }

            $mail->addBCC($email);
        }
    }

    private function addAttachments(PHPMailer $mail, array $files): void
    {
        $uploaded = $files['allegati'] ?? null;
        if (!is_array($uploaded) || !isset($uploaded['name'], $uploaded['tmp_name'], $uploaded['error'])) {
            return;
        }

        $names = is_array($uploaded['name']) ? $uploaded['name'] : [$uploaded['name']];
        $tmpNames = is_array($uploaded['tmp_name']) ? $uploaded['tmp_name'] : [$uploaded['tmp_name']];
        $errors = is_array($uploaded['error']) ? $uploaded['error'] : [$uploaded['error']];

        $count = count($names);
        for ($i = 0; $i < $count; $i++) {
            $error = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            $name = (string) ($names[$i] ?? '');
            $tmp = (string) ($tmpNames[$i] ?? '');
            if ($name === '' || $tmp === '') {
                continue;
            }

            $mail->addAttachment($tmp, $name);
        }
    }
}

<?php
declare(strict_types=1);

namespace core;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends application emails through PHPMailer using environment-driven SMTP settings.
 *
 * Delivery failures are logged so auth flows can fail gracefully when email is unavailable.
 */
class mailer {
    /**
     * Stores the configuration and logger services used during email delivery.
     */
    public function __construct(private env $env, private \Core\logger $logger)
    {
    }

    /**
     * Sends a single HTML email message.
     */
    public function send(string $to, string $subject, string $body): bool
    {
        $mail = new PHPMailer(true);

        try {

            $mail->isSMTP();

            $mail->Host = (string) $this->env->get('MAIL_HOST');
            $mail->SMTPAuth = true;

            $mail->Username = (string) $this->env->get('MAIL_USER');
            $mail->Password = (string) $this->env->get('MAIL_PASS');

            $mail->SMTPSecure = (string) $this->env->get('MAIL_ENCRYPTION');
            $mail->Port = (int) $this->env->get('MAIL_PORT', 587);

            $mail->setFrom(
                (string) $this->env->get('MAIL_FROM'),
                (string) $this->env->get('MAIL_FROM_NAME', 'App')
            );

            $mail->addAddress($to);

            $mail->isHTML(true);

            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();

            return true;

        } catch (Exception $e) {
            $this->logger->exception($e, 'Mail error', [
                'mail_error' => $mail->ErrorInfo,
                'to' => $to,
                'subject' => $subject,
            ]);

            return false;
        }
    }

}

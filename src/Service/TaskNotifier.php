<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskStatus;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

final readonly class TaskNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%app.mailer_from%')]
        private string $from,
    ) {
    }

    public function statusChanged(Task $task, TaskStatus $previous, User $actor): void
    {
        $admin = $task->getCreatedBy();

        if (null === $admin) {
            return;
        }

        $this->send(
            to: $admin->getEmail(),
            subject: \sprintf('[Task Manager] Task "%s" is now "%s"', $task->getTitle(), $task->getStatus()->label()),
            template: 'emails/task_status_changed.html.twig',
            context: [
                'task' => $task,
                'admin' => $admin,
                'actor' => $actor,
                'previousStatus' => $previous,
                'newStatus' => $task->getStatus(),
            ],
        );
    }

    public function validated(Task $task): void
    {
        $worker = $task->getAssignedTo();

        if (null === $worker) {
            return;
        }

        $this->send(
            to: $worker->getEmail(),
            subject: \sprintf('[Task Manager] Task "%s" was validated', $task->getTitle()),
            template: 'emails/task_validated.html.twig',
            context: ['task' => $task, 'worker' => $worker],
        );
    }

    public function rejected(Task $task): void
    {
        $worker = $task->getAssignedTo();

        if (null === $worker) {
            return;
        }

        $this->send(
            to: $worker->getEmail(),
            subject: \sprintf('[Task Manager] Task "%s" was rejected', $task->getTitle()),
            template: 'emails/task_rejected.html.twig',
            context: ['task' => $task, 'worker' => $worker],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(string $to, string $subject, string $template, array $context): void
    {
        $email = (new TemplatedEmail())
            ->from($this->from)
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Unable to send task notification e-mail.', [
                'exception' => $exception,
                'to' => $to,
                'subject' => $subject,
            ]);
        }
    }
}

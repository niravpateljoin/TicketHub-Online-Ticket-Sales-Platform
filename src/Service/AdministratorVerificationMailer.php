<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class AdministratorVerificationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $router,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function initializePendingVerification(User $user): void
    {
        $user
            ->setIsVerified(false)
            ->setVerifiedAt(null)
            ->setVerificationToken(bin2hex(random_bytes(32)));
    }

    public function sendVerificationEmail(User $user): void
    {
        if ($user->getVerificationToken() === null) {
            $this->initializePendingVerification($user);
        }

        $verificationUrl = $this->router->generate('app_react', [
            'reactRouting' => 'verify-email',
            'token' => $user->getVerificationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $isAdmin      = $user->getRole() === 'ROLE_ADMIN';
        $isOrganizer  = $user->getRole() === 'ROLE_ORGANIZER';

        if ($isAdmin) {
            $subject      = 'Verify your TicketHub administrator account';
            $htmlTemplate = 'emails/admin_verification.html.twig';
            $txtTemplate  = 'emails/admin_verification.txt.twig';
        } elseif ($isOrganizer) {
            $subject      = 'Verify your TicketHub organizer account';
            $htmlTemplate = 'emails/user_verification.html.twig';
            $txtTemplate  = 'emails/user_verification.txt.twig';
        } else {
            $subject      = 'Verify your TicketHub account';
            $htmlTemplate = 'emails/user_verification.html.twig';
            $txtTemplate  = 'emails/user_verification.txt.twig';
        }

        $email = (new TemplatedEmail())
            ->from('no-reply@tickethub.local')
            ->to($user->getEmail())
            ->subject($subject)
            ->htmlTemplate($htmlTemplate)
            ->textTemplate($txtTemplate)
            ->context([
                'administrator' => $user,
                'user'          => $user,
                'isOrganizer'   => $isOrganizer,
                'verificationUrl' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }

    public function sendEmailChangeVerificationEmail(User $administrator): void
    {
        if ($administrator->getPendingEmail() === null) {
            throw new \RuntimeException('Pending email is required before sending a change-verification email.');
        }

        if ($administrator->getVerificationToken() === null) {
            $administrator->setVerificationToken(bin2hex(random_bytes(32)));
        }

        $verificationUrl = $this->router->generate('app_react', [
            'reactRouting' => 'verify-email',
            'token' => $administrator->getVerificationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from('no-reply@tickethub.local')
            ->to($administrator->getPendingEmail())
            ->subject('Verify your new TicketHub administrator email')
            ->htmlTemplate('emails/admin_email_change_verification.html.twig')
            ->textTemplate('emails/admin_email_change_verification.txt.twig')
            ->context([
                'administrator' => $administrator,
                'currentEmail' => $administrator->getEmail(),
                'newEmail' => $administrator->getPendingEmail(),
                'verificationUrl' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }

    public function verifyToken(string $token): ?array
    {
        $user = $this->userRepository->findOneBy(['verificationToken' => $token]);

        if (!$user instanceof User) {
            return null;
        }

        $mode = 'account_verification';

        if ($user->getPendingEmail() !== null) {
            $mode = 'email_change';
            $user->setEmail($user->getPendingEmail());
            $user->setPendingEmail(null);
        } else {
            $user->setIsVerified(true);
        }

        $user
            ->setVerifiedAt(new \DateTime())
            ->setVerificationToken(null);

        $this->em->flush();

        return [
            'mode' => $mode,
            'user' => $user,
        ];
    }
}

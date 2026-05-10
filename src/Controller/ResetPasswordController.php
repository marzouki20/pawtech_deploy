<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

final class ResetPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        CacheItemPoolInterface $cache,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            if ($email === '') {
                $this->addFlash('error', 'Email is required.');
                return $this->redirectToRoute('app_forgot_password');
            }
            $user = $userRepository->findOneBy(['email' => $email]);
            $token = bin2hex(random_bytes(16));

            if ($user) {
                $code = (string) random_int(100000, 999999);
                $cacheKey = 'password_reset_'.$token;

                $item = $cache->getItem($cacheKey);
                $item->set([
                    'email' => $email,
                    'code' => $code,
                ]);
                $item->expiresAfter(900);
                $cache->save($item);

                $verifyUrl = $urlGenerator->generate('app_verify_reset', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                $logoUrl = $request->getSchemeAndHttpHost().'/logo.png';

                $fromEmail = $_ENV['MAILER_FROM'] ?? 'marzoukikalil20@gmail.com';
                if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                    $logger->warning('Invalid MAILER_FROM value; falling back to default sender.', [
                        'configured_from' => $fromEmail,
                    ]);
                    $fromEmail = 'marzoukikalil20@gmail.com';
                }

                $message = (new Email())
                    ->from($fromEmail)
                    ->to($user->getEmail())
                    ->subject('Your PawTech reset code')
                    ->text("Hello {$user->getPrenom()},\n\nYour reset code is: {$code}\n\nOpen this link to enter the code: {$verifyUrl}\n\nThis code will expire in 15 minutes.")
                    ->html(
                        '<div style="font-family:Arial,sans-serif;background:#fff7f0;padding:0;margin:0;">'
                        .'<div style="max-width:520px;margin:32px auto;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #ffd6a0;box-shadow:0 2px 12px #ffedd5;">'
                        .'<div style="background:#ff7300;padding:28px 0;text-align:center;">'
                        .'<img src="'.$logoUrl.'" alt="PawTech" style="height:60px;max-width:180px;object-fit:contain;display:inline-block;margin-bottom:10px;">'
                        .'<div style="margin-top:6px;font-size:22px;font-weight:800;color:#fff;letter-spacing:1px;">PawTech</div>'
                        .'</div>'
                        .'<div style="padding:32px 28px 28px 28px;">'
                        .'<h2 style="margin:0 0 16px;font-size:22px;color:#ff7300;font-weight:800;">Reset your password</h2>'
                        .'<p style="margin:0 0 18px;color:#4b5563;font-size:15px;">Hi '.$user->getPrenom().',<br><br>'
                        .'We received a request to reset your PawTech password. Use the code below to continue. If you did not request this, you can safely ignore this email.</p>'
                        ."<div style=\"font-size:28px;letter-spacing:4px;font-weight:700;color:#f97316;background:#fff7ed;padding:12px 16px;border-radius:12px;display:inline-block;\">{$code}</div>"
                        //.'<a href="'.$verifyUrl.'" style="display:inline-block;background:#ff7300;color:#fff;text-decoration:none;padding:12px 28px;border-radius:12px;font-weight:700;font-size:16px;margin:10px 0 0 0;box-shadow:0 2px 8px #ffd6a0;">Enter code</a>'
                        .'<p style="margin:22px 0 0;color:#9ca3af;font-size:13px;">This code will expire in 15 minutes.<br>If you have any questions, just reply to this email and our team will help you.</p>'
                        .'</div>'
                        .'</div>'
                        .'</div>'
                    );

                try {
                    $mailer->send($message);
                } catch (TransportExceptionInterface $exception) {
                    $logger->error('Reset email failed to send.', [
                        'error' => $exception->getMessage(),
                    ]);
                    $this->addFlash('error', 'Email could not be sent. Check mail configuration.');
                }
            }

            $this->addFlash('success', 'If your email exists, a reset code has been sent.');
            return $this->redirectToRoute('app_verify_reset', ['token' => $token]);
        }

        return $this->render('sign/forgot_password.html.twig');
    }

    #[Route('/reset-password/verify/{token}', name: 'app_verify_reset', methods: ['GET', 'POST'])]
    public function verifyCode(string $token, Request $request, CacheItemPoolInterface $cache): Response
    {
        $cacheKey = 'password_reset_'.$token;
        $item = $cache->getItem($cacheKey);

        if (!$item->isHit()) {
            $this->addFlash('error', 'This reset code is invalid or expired.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $payload = $item->get();
        $expectedCode = is_array($payload) && isset($payload['code']) ? (string) $payload['code'] : '';

        if ($request->isMethod('POST')) {
            $code = trim((string) $request->request->get('code', ''));

            if ($code === '') {
                $this->addFlash('error', 'Code is required.');
            } elseif ($code !== $expectedCode) {
                $this->addFlash('error', 'Invalid code.');
            } else {
                $verifiedKey = 'password_reset_verified_'.$token;
                $verifiedItem = $cache->getItem($verifiedKey);
                $verifiedItem->set(true);
                $verifiedItem->expiresAfter(900);
                $cache->save($verifiedItem);

                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }
        }

        return $this->render('sign/verify_code.html.twig', [
            'token' => $token,
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        CacheItemPoolInterface $cache,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $cacheKey = 'password_reset_'.$token;
        $item = $cache->getItem($cacheKey);
        $verifiedItem = $cache->getItem('password_reset_verified_'.$token);

        if (!$item->isHit() || !$verifiedItem->isHit()) {
            return $this->redirectToRoute('app_verify_reset', ['token' => $token]);
        }

        $payload = $item->get();
        $email = is_array($payload) && isset($payload['email']) ? (string) $payload['email'] : '';
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $cache->deleteItem($cacheKey);
            $cache->deleteItem('password_reset_verified_'.$token);
            $this->addFlash('error', 'This reset link is invalid or expired.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password', '');
            $confirm = (string) $request->request->get('confirm_password', '');

            if ($password === '' || $confirm === '') {
                $this->addFlash('error', 'Password and confirmation are required.');
            } elseif ($password !== $confirm) {
                $this->addFlash('error', 'Passwords do not match.');
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $entityManager->flush();
                $cache->deleteItem($cacheKey);
                $cache->deleteItem('password_reset_verified_'.$token);

                $this->addFlash('success', 'Password updated. You can sign in now.');
                return $this->redirectToRoute('app_signin');
            }
        }

        return $this->render('sign/reset_password.html.twig', [
            'token' => $token,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\Users;
use App\Services\Validation\RegistrationValidator;
use DI\Container;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends BaseController
{
    private RegistrationValidator $registrationValidator;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->registrationValidator = new RegistrationValidator();
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'login.twig');
    }

    public function showRegister(Request $request, Response $response): Response
    {
        return $this->render($response, 'register.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data['email']    = $request->getParsedBody()['email'];
        $data['password'] = $request->getParsedBody()['password'];

        $user = Users::findByEmail($data['email']);
        if ($user == null) {
            $data['error'] = 'Invalid email or password';
            return $this->render($response, 'login.twig', $data);
        }

        if (!password_verify($data['password'], $user->password_hash)) {
            $data['error'] = 'Invalid email or password';
            return $this->render($response, 'login.twig', $data);
        }

        // Store pending user in session
        $_SESSION['2fa_user_id'] = (int) $user->id;
        $_SESSION['2fa_role']    = $user->role;
        $_SESSION['2fa_name']    = $user->name;
        $_SESSION['2fa_email']   = $user->email;

        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';

        // First time: no TOTP secret yet → generate and show QR code
        if (empty($user->totp_secret)) {
            $tfa    = new TwoFactorAuth(new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'), 'CraveCart');
            $secret = $tfa->createSecret();
            $_SESSION['totp_secret'] = $secret;
            return $response->withStatus(302)->withHeader('Location', $basePath . '/verify-2fa?step=setup');
        }

        // Returning user: already has secret → just ask for code
        $_SESSION['totp_secret'] = $user->totp_secret;
        return $response->withStatus(302)->withHeader('Location', $basePath . '/verify-2fa?step=verify');
    }

    public function showVerify2fa(Request $request, Response $response): Response
    {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';

        if (empty($_SESSION['2fa_user_id'])) {
            return $response->withStatus(302)->withHeader('Location', $basePath . '/login');
        }

        $step = $request->getQueryParams()['step'] ?? 'verify';

        if ($step === 'setup') {
            $tfa      = new TwoFactorAuth(new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'), 'CraveCart');
            $qrCodeUri = $tfa->getQRCodeImageAsDataUri(
                $_SESSION['2fa_email'],
                $_SESSION['totp_secret']
            );
            return $this->render($response, 'verify-2fa.twig', [
                'step'     => 'setup',
                'qr_code'  => $qrCodeUri,
            ]);
        }

        return $this->render($response, 'verify-2fa.twig', ['step' => 'verify']);
    }

    public function verify2fa(Request $request, Response $response): Response
    {
        $code     = trim($request->getParsedBody()['code'] ?? '');
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';

        if (empty($_SESSION['2fa_user_id']) || empty($_SESSION['totp_secret'])) {
            return $response->withStatus(302)->withHeader('Location', $basePath . '/login');
        }

        $tfa = new TwoFactorAuth(new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'), 'CraveCart');

        if (!$tfa->verifyCode($_SESSION['totp_secret'], $code)) {
            return $this->render($response, 'verify-2fa.twig', [
                'step'  => 'verify',
                'error' => 'Invalid code. Please try again.',
            ]);
        }

        // First time setup: save secret to DB
        $userId = $_SESSION['2fa_user_id'];
        $user   = Users::findById($userId);
        $isFirstSetup = empty($user->totp_secret);

        if ($isFirstSetup) {
            Users::saveTotpSecret($userId, $_SESSION['totp_secret']);
        }

        // Log the user in
        $_SESSION['user_id'] = $userId;
        $_SESSION['role']    = $_SESSION['2fa_role'];
        $_SESSION['name']    = $_SESSION['2fa_name'];
        unset($_SESSION['2fa_user_id'], $_SESSION['2fa_role'], $_SESSION['2fa_name'], $_SESSION['2fa_email'], $_SESSION['totp_secret']);
        $_SESSION['last_activity'] = time();

        if ($_SESSION['role'] !== 'administrator') {
            $this->mergeSessionCartIntoSavedCart($userId);
            $this->flash('success', $isFirstSetup ? 'Account created! Welcome to CraveCart.' : 'Welcome back!');
            return $response->withStatus(302)->withHeader('Location', $basePath . '/');
        }

        $this->flash('success', 'Welcome back!');
        return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/orders');
    }

    public function logout(Request $request, Response $response): Response
    {
        session_unset();
        $this->flash('success', 'You have been successfully signed out.');
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . '/');
    }

    public function register(Request $request, Response $response): Response
    {
        $result = $this->registrationValidator->validate($request->getParsedBody());
        $data = $result['data'];

        if (!$result['valid']) {
            return $this->render($response, 'register.twig', [
                'errors' => $result['errors'],
                'old' => [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                ],
            ]);
        }

        $fullname = $data['first_name'] . ' ' . $data['last_name'];
        $userId = Users::create($fullname, $data['email'], $data['password'], 'client');
        if ($userId == 0) {
            return $this->render($response, 'register.twig', ['errors' => ['Error creating user']]);
        }

        // New user — no TOTP secret yet, go through setup
        $tfa    = new TwoFactorAuth(new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'), 'CraveCart');
        $secret = $tfa->createSecret();

        $_SESSION['2fa_user_id'] = $userId;
        $_SESSION['2fa_role']    = 'client';
        $_SESSION['2fa_name']    = $fullname;
        $_SESSION['2fa_email']   = $data['email'];
        $_SESSION['totp_secret'] = $secret;

        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . '/verify-2fa?step=setup');
    }
}

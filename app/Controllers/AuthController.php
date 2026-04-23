<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\Users;
use DI\Container;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    // Renders the login page when the user visits /login
    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'login.twig');
    }

    // Renders the register page when the user visits /register
    public function showRegister(Request $request, Response $response): Response
    {
        return $this->render($response, 'register.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        // Read email and password from the submitted form.
        // getParsedBody() gives us the POST data as an array — same as $_POST.
        $data['email']    = $request->getParsedBody()['email'];
        $data['password'] = $request->getParsedBody()['password'];

        // Look up the user by email in the database.
        // We search by email (not ID) because that's all we have at login time.
        $user = Users::findByEmail($data['email']);
        if ($user == null) {
            // No account found — show a vague error on purpose so attackers
            // can't tell whether the email exists or not.
            $data['error'] = 'Invalid email or password';
            return $this->render($response, 'login.twig', $data);
        }

        // Verify the submitted password against the hashed password stored in the DB.
        // password_verify() handles the hashing internally — never compare hashes directly.
        if (!password_verify($data['password'], $user->password_hash)) {
            $data['error'] = 'Invalid email or password';
            return $this->render($response, 'login.twig', $data);
        }

        // Credentials valid — generate 2FA code and email it
        $code = (string) random_int(100000, 999999);
        $_SESSION['2fa_code']    = $code;
        $_SESSION['2fa_expires'] = time() + 300;
        $_SESSION['2fa_user_id'] = (int) $user->id;
        $_SESSION['2fa_role']    = $user->role;
        $_SESSION['2fa_name']    = $user->name;

        $this->sendTwoFactorEmail($user->email, $user->name, $code);

        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . '/verify-2fa');
    }

    public function showVerify2fa(Request $request, Response $response): Response
    {
        if (empty($_SESSION['2fa_code'])) {
            $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
            return $response->withStatus(302)->withHeader('Location', $basePath . '/login');
        }
        return $this->render($response, 'verify-2fa.twig');
    }

    public function verify2fa(Request $request, Response $response): Response
    {
        $input    = trim($request->getParsedBody()['code'] ?? '');
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';

        if (empty($_SESSION['2fa_code'])) {
            return $response->withStatus(302)->withHeader('Location', $basePath . '/login');
        }

        if (time() > $_SESSION['2fa_expires']) {
            unset($_SESSION['2fa_code'], $_SESSION['2fa_expires'], $_SESSION['2fa_user_id'], $_SESSION['2fa_role'], $_SESSION['2fa_name']);
            return $this->render($response, 'verify-2fa.twig', ['error' => 'Code expired. Please log in again.']);
        }

        if ($input !== $_SESSION['2fa_code']) {
            return $this->render($response, 'verify-2fa.twig', ['error' => 'Invalid code. Please try again.']);
        }

        $_SESSION['user_id'] = $_SESSION['2fa_user_id'];
        $_SESSION['role']    = $_SESSION['2fa_role'];
        $_SESSION['name']    = $_SESSION['2fa_name'];
        unset($_SESSION['2fa_code'], $_SESSION['2fa_expires'], $_SESSION['2fa_user_id'], $_SESSION['2fa_role'], $_SESSION['2fa_name']);

        if ($_SESSION['role'] !== 'administrator') {
            return $response->withStatus(302)->withHeader('Location', $basePath . '/?loggedin=1');
        }
        return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/orders');
    }

    private function sendTwoFactorEmail(string $toEmail, string $toName, string $code): void
    {
        $env  = require APP_BASE_DIR_PATH . '/config/env.php';
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $env['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $env['smtp_username'];
        $mail->Password   = $env['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $env['smtp_port'];
        $mail->setFrom($env['smtp_from'], $env['smtp_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'Your CraveCart verification code';
        $mail->Body    = "Hi {$toName},\n\nYour verification code is: {$code}\n\nIt expires in 5 minutes.\n\n— CraveCart";
        $mail->send();
    }

    public function logout(Request $request, Response $response): Response
    {
        // Clear the session to log the user out.
        session_unset();
        session_destroy();

        // Redirect to the login page after logging out.
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . '/?logged_out=1');
    }


    public function register(Request $request, Response $response): Response
    {
        //getting data from form
        $data['first_name'] = $request->getParsedBody()['first_name'];
        $data['last_name'] = $request->getParsedBody()['last_name'];
        $fullname = $data['first_name'] . ' ' . $data['last_name'];

        $data['email']    = $request->getParsedBody()['email'];
        $data['password'] = $request->getParsedBody()['password'];
        $data['password_confirm'] = $request->getParsedBody()['password_confirm'];

        if($data['password']  != $data['password_confirm']){
            return $this->render($response, 'register.twig', ['errors' => ['Passwords do not match']]);
        }

        $user = Users::findByEmail($request->getParsedBody()['email'] ?? '');
        if ($user != null) {
            return $this->render($response, 'register.twig', ['errors' => ['Email already in use']]);
        }

        $user = Users::create($fullname, $data['email'], $data['password'], 'client');
        if ($user == 0) {
            return $this->render($response, 'register.twig', ['errors' => 'Error creating user']);
        }

        $newUser = Users::findByEmail($data['email']);
        $code = (string) random_int(100000, 999999);
        $_SESSION['2fa_code']    = $code;
        $_SESSION['2fa_expires'] = time() + 300;
        $_SESSION['2fa_user_id'] = (int) $newUser->id;
        $_SESSION['2fa_role']    = $newUser->role;
        $_SESSION['2fa_name']    = $newUser->name;

        $this->sendTwoFactorEmail($newUser->email, $newUser->name, $code);

        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . '/verify-2fa');
    }
}

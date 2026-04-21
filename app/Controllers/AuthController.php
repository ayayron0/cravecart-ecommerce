<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\Users;
use DI\Container;
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

        // Credentials are valid — save the user's info in the session.
        // The session persists across requests so the app remembers who is logged in.
        $_SESSION['user_id'] = $user->id;
        $_SESSION['role']    = $user->role;
        $_SESSION['name']    = $user->name;

        // Redirect based on role.
        if ($_SESSION['role'] != 'administrator') {
            $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
            return $response->withStatus(302)->withHeader('Location', $basePath . '/?loggedin=1');
        }

        // Admin: redirect to the orders dashboard.
        // 302 tells the browser to go to a new URL instead of rendering a page here.
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/orders');
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

        // Save the user's info in the session.
        $newUser = Users::findByEmail($data['email']);
        $_SESSION['user_id'] = $newUser->id;
        $_SESSION['name']    = $newUser->name;
        $_SESSION['role']    = $newUser->role;

        // Redirect to the home page after registering.
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . '/home?registered=1');
    }
}

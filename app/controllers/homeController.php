<?php


namespace App\controllers;

use App\classes\QueryBuilder;
use claviska\SimpleImage;
use Delight\Auth\Auth;
use League\Plates\Engine;
use Tamtamchik\SimpleFlash\Exceptions\FlashTemplateNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use ReallySimpleJWT\Token;


class homeController
{
    public $templates;
    private $qb;
    private $auth;
    private $image;
    private $payload;
    private $secret;




    public function __construct(Engine $engine, QueryBuilder $qb, Auth $auth, SimpleImage $image)
    {
        $this->templates = $engine;
        $this->qb = $qb;
        $this->auth = $auth;
        $this->image = $image;
        $this->secret = $this->qb->select('secret', 'other')[0]['secret'];
        $this->payload = [
            'iat' => time(),
            'uid' => 1,
            'exp' => time() + 10,
            'iss' => 'localhost'
        ];
        $this->templates->registerFunction('token', function () {$this->token();});
    }

    private function token() {
        echo $token = Token::customPayload($this->payload, $this->secret);
    }





    public function registerPage()
    {
        echo $this->templates->render('page_register');
    }

    public function register()
    {


        if (!Token::validate($_POST['token'], $this->secret)) {
            Flash::warning('Invalid token!');
            header("Location: http://exam/login");
            die();
        }

        try {
            $userId = $this->auth->register($_POST['email'], $_POST['password']);
            if(!empty($userId)) {

                $avatarPath = 'img/demo/avatars/' . uniqid('avatar-', true) . '.png';
                $this->image->fromFile('img/demo/avatars/avatar-m.png')->toFile($avatarPath);

                $this->qb->update('users', "id = $userId", [
                    'avatar' => $avatarPath,
                ]);

                flash()->message('You Welcome!');
                header("Location: http://exam/login");
                die();
            }
        } catch (\Delight\Auth\InvalidEmailException $e) {
            flash()->message('Invalid email');
            header("Location: http://exam/register");
            die();
        } catch (\Delight\Auth\InvalidPasswordException $e) {
            flash()->message('Invalid password');
            header("Location: http://exam/register");
            die();
        } catch (\Delight\Auth\UserAlreadyExistsException $e) {
            flash()->message('User already exists');
            header("Location: http://exam/register");
            die();
        }
        catch (\Delight\Auth\TooManyRequestsException $e) {
            flash()->message('Too many requests');
            header("Location: http://exam/register");
            die();
        }
    }




    public function loginPage()
    {
        echo $this->templates->render('page_login');
    }



    public function login()
    {
        if(!Token::validate($_POST['token'], $this->secret)) {
            Flash::warning('Invalid token!');
            header("Location: http://exam/login");
            die();
        }
        $rememberDuration = null;
        if(!empty($_POST['remember'])) {
            if ($_POST['remember'] == 'on') {
                $rememberDuration = (int) (60 * 60 * 24 * 365.25);
            }
            else {
                $rememberDuration = null;
            }}

        try {
            $email = $_POST['email'];
            $password = $_POST['password'];

            $this->auth->login($email, $password, $rememberDuration);

            flash()->success('You are logged in!');
            header("Location: http://exam/users");
            die;

        }
        catch (\Delight\Auth\InvalidEmailException $e) {

            Flash::error('Invalid email!');
            header("Location: http://exam/login");
            die();
        }
        catch (\Delight\Auth\InvalidPasswordException $e) {
            Flash::error('Wrong password!');
            header("Location: http://exam/login");
            die();
        }
        catch (\Delight\Auth\EmailNotVerifiedException $e) {
            Flash::error('Email not verified');
            header("Location: http://exam/login");
            die();
        }
        catch (\Delight\Auth\TooManyRequestsException $e) {
            Flash::error('Too many requests');
            header("Location: http://exam/login");
            die();
        }

    }




    public function logout() {
        try {
            $this->auth->logOutEverywhere();
            Flash::error('You are logged out!');
            header("Location: http://exam/login");
            die();
        }
        catch (\Delight\Auth\NotLoggedInException $e) {
            header("Location: http://exam/login");
            die();
        }
    }




    public function delete() {

        $id = $_GET['id'];
        $avatar = $this->qb->select('avatar', 'users', "id = $id")[0]['avatar'];
        if ($id == $this->auth->getUserId()) {
            $this->auth->admin()->deleteUserById($id);
            if(!empty($avatar)) {
                unlink($avatar);
            }
            Flash::error('Your account is deleted');
            header("Location: http://exam/login");
            die();
        } elseif($this->auth->hasRole(\Delight\Auth\Role::ADMIN)) {
            try {
                $this->auth->admin()->deleteUserById($id);
                if(!empty($avatar)) {
                    unlink($avatar);
                }
            }
            catch (\Delight\Auth\UnknownIdException $e) {
                Flash::error('Unknown user ID!');
                header("Location: http://exam/users");
                die();
            }
            Flash::error('User deleted!');
            header("Location: http://exam/users");
            die();
        } else {
            Flash::error('You cant delete this user!');
            header("Location: http://exam/users");
            die();
        }
    }




    public function editUserPage() {
        if (!$this->auth->isLoggedIn()) {
            Flash::error('You must login first!');
            header("Location: http://exam/login");
            die();
        }
        $id = '';
        if(empty($_GET['id'])) {
            $id = $this->auth->getUserId();
        } else {
            $id = $_GET['id'];
        }
        $user = $this->qb->select('*', 'users', "id = $id");
        $user = $user[0];

        if ($id == $this->auth->getUserId() || $this->auth->hasRole(\Delight\Auth\Role::ADMIN)) {
            echo $this->templates->addData($user)->render('page_edit');
        die();
    } else {
            Flash::error('You cant edit this user!');
            header("Location: http://exam/users");
            die();
        }

    }


    public function editUser() {
        if(!Token::validate($_POST['token'], $this->secret)) {
            Flash::warning('Invalid token!');
            header("Location: http://exam/users");
            die();
        }
        $id = $_POST['id'];

            $this->qb->update('users', "id = $id", [
                'occupation' => $_POST['occupation'],
                'address' => $_POST['address'],
                'telephone' => $_POST['telephone'],
                'username' => $_POST['username'],
            ]);
        Flash::success('Users profile updated!');
        header("Location: http://exam/profile?id=$id");
        die();
    }




    public function profile($vars)
    {
        if (!$this->auth->isLoggedIn()) {
            Flash::error('You must login first!');
            header("Location: http://exam/login");
            die();
        }
        $user = [];
        $id = null;
        if(!empty($vars)) {
            $id = $vars['id'];

            header("Location: http://exam/profile?id=$id");
            exit;
        }
        if(isset($_GET['id'])) {
            $id = $_GET['id'];
            $user = $this->qb->select('*', 'users', "id = $id");
            $user = $user[0];
        }else {
            $id = $this->auth->getUserId();
            $user = $this->qb->select('*', 'users', "id = $id");
            $user = $user[0];
        }

        echo $this->templates->addData($user)->render('page_profile');

    }




    public function users()
    {

        $users = $this->qb->getAll('users');
        $log = $this->auth->isLoggedIn();
        if (!$log) {
            Flash::error('You must login first!');
            header("Location: http://exam/login");
            die();
        }
        $id = $this->auth->getUserId();

        $this->templates->registerFunction('checkRole', function () {
            $role = false;
        if($this->auth->hasRole(\Delight\Auth\Role::ADMIN)) {
            $role = true;
        }
        return $role;
        });
        echo $this->templates->render('page_users', ['users' => $users, 'id' => $id]);
    }






    public function securityPage() {
        if (!$this->auth->isLoggedIn()) {
            Flash::error('You must login first!');
            header("Location: http://exam/login");
            die();
        }
        $id = '';
        if(empty($_GET['id'])) {
            $id = $this->auth->getUserId();
        } else {
            $id = $_GET['id'];
        }

        $user = $this->qb->select('*', 'users', "id = $id")[0];


        if ($id == $this->auth->getUserId() || $this->auth->hasRole(\Delight\Auth\Role::ADMIN)) {
            echo $this->templates->addData($user)->render('page_security');
            die();
        }
    }

    public function security() {
        if(!Token::validate($_POST['token'], $this->secret)) {
            Flash::warning('Invalid token!');
            header("Location: http://exam/users");
            die();
        }

        $id = $_POST['id'];

        if($_POST['password'] != $_POST['password_again']) {
            Flash::warning('New password and confirm password "doesnt match"!');
            header("Location: http://exam/security?id=$id");
            die();
        }

        if($_POST['password'] == $_POST['oldPassword']) {
            Flash::warning('New password can\'t be the same as old password!');
            header("Location: http://exam/security?id=$id");
            die();
        }


        try {
            $this->auth->changePassword($_POST['oldPassword'], $_POST['password']);

            Flash::success('Password has been changed!');
            header("Location: http://exam/profile?id=$id");
            die();
        }
        catch (\Delight\Auth\NotLoggedInException $e) {
            Flash::warning('Not logged in');
            header("Location: http://exam/login");
            die();
        }
        catch (\Delight\Auth\InvalidPasswordException $e) {
            Flash::warning('Invalid password!');
            header("Location: http://exam/security?=$id");
            die();
        }
        catch (\Delight\Auth\TooManyRequestsException $e) {
            Flash::warning('Too many requests');
            header("Location: http://exam/security?=$id");
            die();
        }

    }



    public function avatarPage() {
        if (!$this->auth->isLoggedIn()) {
            Flash::error('You must login first!');
            header("Location: http://exam/login");
            die();
        }
        $id = '';
        if(empty($_GET['id'])) {
            $id = $this->auth->getUserId();
        } else {
            $id = $_GET['id'];
        }
        $user = $this->qb->select('*', 'users', "id = $id")[0];


        if ($id == $this->auth->getUserId() || $this->auth->hasRole(\Delight\Auth\Role::ADMIN)) {
            echo $this->templates->addData($user)->render('page_media');
            die();
    }
    }

    public function avatar() {
        if(!Token::validate($_POST['token'], $this->secret)) {
            Flash::warning('Invalid token!');
            header("Location: http://exam/users");
            die();
        }

        $id = $_POST['id'];
        $avatar = $this->qb->select('*', 'users', "id = $id")[0]['avatar'];


        $avatarPath = 'img/demo/avatars/'.uniqid('avatar-', true).'.png';

        if(empty($_FILES['avatar']['tmp_name'])) {
            Flash::success('Avatar is not chosen!');
            header("Location: http://exam/avatar?id=$id");
            die();
        }

        if(file_exists($avatar)) {
            unlink($avatar);
        }
        $this->image->fromFile($_FILES['avatar']['tmp_name'])->toFile($avatarPath);

        $this->qb->update('users', "id = $id", [
            'avatar' => $avatarPath
        ]);
        Flash::success('Avatar changed!');
        header("Location: http://exam/avatar?id=$id");
        die();
    }







    public function createUserPage()
    {
        if(!$this->auth->isLoggedIn()) {
            Flash::error('You must login first!');
            header("Location: http://exam/users");
            die();
        }
        if(!$this->auth->hasRole(\Delight\Auth\Role::ADMIN)) {
            Flash::error('You cant create users!');
            header("Location: http://exam/users");
            die();
        }
        $log = $this->auth->isLoggedIn();
        if (!$log) {
            header("Location: http://exam/users");
            die();
        }
        echo $this->templates->render('page_create_user');
    }




    public function createUser()
    {
        if(!Token::validate($_POST['token'], $this->secret)) {
            Flash::warning('Invalid token!');
            header("Location: http://exam/create_user");
            die();
        }

        try {
            $userId = $this->auth->register($_POST['email'], $_POST['password'], $_POST['username']);

            if(!empty($userId)) {

                $avatarPath = '';
                if(!empty($_FILES['avatar']['tmp_name'])) {
                    $avatarPath = 'img/demo/avatars/'.uniqid('avatar-', true).'.png';
                    $this->image->fromFile($_FILES['avatar']['tmp_name'])->toFile($avatarPath);
                } else {
                    $avatarPath = 'img/demo/avatars/' . uniqid('avatar-', true) . '.png';
                    $this->image->fromFile('img/demo/avatars/avatar-m.png')->toFile($avatarPath);
                }


                $this->qb->update('users', "id = $userId", [
                    'occupation' => $_POST['occupation'],
                    'address' => $_POST['address'],
                    'avatar' => $avatarPath,
                    'telephone' => $_POST['telephone'],
                    'vk_link' => $_POST['vk_link'],
                    'telegram_link' => $_POST['telegram_link'],
                    'instagram_link' => $_POST['instagram_link']
                    ]);
            }

            flash()->message('We have signed up a new user with the ID ' . $userId);
//            echo 'We have signed up a new user with the ID ' . $userId;
            header("Location: http://exam/profile/$userId");
            die();
        } catch (\Delight\Auth\InvalidEmailException $e) {
            flash()->message('Invalid email');
            header("Location: http://exam/create_user");
            die();
        } catch (\Delight\Auth\InvalidPasswordException $e) {
            flash()->message('Invalid password');
            header("Location: http://exam/create_user");
            die();
        } catch (\Delight\Auth\UserAlreadyExistsException $e) {
            flash()->message('User already exists');
            header("Location: http://exam/create_user");
            die();
        }
        catch (\Delight\Auth\TooManyRequestsException $e) {
            flash()->message('Too many requests');
            header("Location: http://exam/create_user");
            die();
        }


    }


}
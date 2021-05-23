<?php
if( !session_id() ) @session_start();

require 'vendor/autoload.php';


use Aura\SqlQuery\QueryFactory;
use Delight\Auth\Auth;
use League\Plates\Engine;
use \Tamtamchik\SimpleFlash\Flash;
use \DI\ContainerBuilder;




$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    Engine::class => function() {
        return new Engine('app/views');
    },

    PDO::class => function() {
    $driver = "mysql";
    $host = "localhost";
    $database_name = "exam";
    $username = "root";
    $password = "root";

    return new PDO("$driver:host=$host;dbname=$database_name", $username, $password);
    },

    QueryFactory::class => function() {
        return  new QueryFactory('mysql');
    },

    Auth::class => function(PDO $pdo) {
    return new Auth($pdo, '', '', false);
    }
]);
$container = $containerBuilder->build();



$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', ['App\controllers\homeController', 'users']);
    $r->addRoute('GET', '/login', ['App\controllers\homeController', 'loginPage']);
    $r->addRoute('GET', '/delete[{id:\d+}]', ['App\controllers\homeController', "delete"]);
    $r->addRoute('GET', '/edit[{id:\d+}]', ['App\controllers\homeController', "editUserPage"]);
    $r->addRoute('POST', '/edit[{id:\d+}]', ['App\controllers\homeController', 'editUser']);
    $r->addRoute('POST', '/login', ['App\controllers\homeController', 'login']);
    $r->addRoute('GET', '/logout', ['App\controllers\homeController', 'logout']);
    $r->addRoute('GET', '/profile[/{id:\d+}]', ['App\controllers\homeController', "profile"]);
    $r->addRoute('GET', '/users', ['App\controllers\homeController', 'users']);
    $r->addRoute('GET', '/create_user', ['App\controllers\homeController', 'createUserPage']);
    $r->addRoute('POST', '/create_user', ['App\controllers\homeController', 'createUser']);
    $r->addRoute('GET', '/register', ['App\controllers\homeController', 'registerPage']);
    $r->addRoute('POST', '/register', ['App\controllers\homeController', 'register']);
    $r->addRoute('GET', '/avatar[{id:\d+}]', ['App\controllers\homeController', 'avatarPage']);
    $r->addRoute('POST', '/avatar[{id:\d+}]', ['App\controllers\homeController', 'avatar']);
    $r->addRoute('GET', '/security[{id:\d+}]', ['App\controllers\homeController', 'securityPage']);
    $r->addRoute('POST', '/security[{id:\d+}]', ['App\controllers\homeController', 'security']);
});

// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        Flash::warning('404 page not found!');
        header("Location: http://exam/users");
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        Flash::warning('405 method not allowed!');
        header("Location: http://exam/users");
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];

        $container->call($handler, [$vars]);
        // ... call $handler with $vars
        break;
}

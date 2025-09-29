<?php
// php entry point

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Dotenv\Dotenv;
// use Respect\Validation\Validator;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Set up dependency injection container
$container = new Container();
AppFactory::setContainer($container);

// Register PDO connection to Database
$container->set('db', function () {
    $dsn = 'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'];
    return new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
});

// // register validator for use in EmployeeCOntroller2
// $container->set('validator', function (){
//     return Validator::create();
// });

$app = AppFactory::create();

// Define routes for api calls
$app->get('/employees', '\App\controllers\EmployeeCOntroller:getAllEmployees');
$app->get('/employee/{emp_no}/profile', '\App\controllers\EmployeeController:getProfile');
$app->get('/departments/stats', '\App\controllers\DepartmentController:getStats');
$app->get('/managers', '\App\controllers\ManagerController:getHierarchy');
$app->get('/employee/{emp_no}/salary-history', '\App\controllers\EmployeeController:getSalaryHistory');
$app->get('/promotions/trends', '\App\controllers\EmployeeController:getPromotionTrends');
$app->get('/hires', '\App\controllers\EmployeeController:getHires');

// mkae post request
$app->post('/new_profile', '\App\controllers\EmployeeController:createProfile');

// make delete request
$app->patch('/profile/{emp_no}', '\App\controllers\EmployeeController:updateProfile');

//make patch request
$app->delete('/profile/{emp_no}', '\App\controllers\EmployeeController:deleteProfile');

// verify route actually works
$app->any('/test', function (Request $request, Response $response) {
    $response->getBody()->write('Route works!');
    return $response;
});

$app->run();
<?php
// manager endpoints to retrun queries from manager's table.

namespace App\controllers;

use App\responses\JsonResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ManagerController {
    private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function getHierarchy(Request $request, Response $response): Response {
        $stmt = $this->container->get('db')->prepare("SELECT dm.dept_no, d.dept_name, e.emp_no, CONCAT(e.first_name, ' ', e.last_name) AS manager_name,
                                                           dm.from_date, dm.to_date, DATEDIFF(dm.to_date, dm.from_date) AS tenure_days
                                                    FROM dept_manager dm
                                                    JOIN employees e ON dm.emp_no = e.emp_no
                                                    JOIN departments d ON dm.dept_no = d.dept_no
                                                    WHERE dm.to_date > CURDATE()
                                                    ORDER BY d.dept_name");
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return JsonResponse::success($response, $data);
    }
}
<?php
// app/controllers/DepartmentController.php - Handles department endpoints.

namespace App\controllers;

use App\responses\JsonResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DepartmentController {
    private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function getStats(Request $request, Response $response): Response {
        $stmt = $this->container->get('db')->prepare("SELECT d.dept_no, d.dept_name,
                                                           COUNT(de.emp_no) AS employee_count,
                                                           AVG(s.salary) AS avg_salary,
                                                           SUM(CASE WHEN e.gender = 'M' THEN 1 ELSE 0 END) AS male_count,
                                                           SUM(CASE WHEN e.gender = 'F' THEN 1 ELSE 0 END) AS female_count,
                                                           (SELECT MAX(s2.salary) FROM salaries s2 JOIN dept_emp de2 ON s2.emp_no = de2.emp_no WHERE de2.dept_no = d.dept_no AND s2.to_date > CURDATE()) AS max_salary
                                                    FROM departments d
                                                    JOIN dept_emp de ON d.dept_no = de.dept_no
                                                    JOIN employees e ON de.emp_no = e.emp_no
                                                    JOIN salaries s ON e.emp_no = s.emp_no
                                                    WHERE de.to_date > CURDATE() AND s.to_date > CURDATE()
                                                    GROUP BY d.dept_no");
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return JsonResponse::success($response, $data);
    }
}
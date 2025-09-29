<?php
// employee-related endpoints

namespace App\controllers;

use App\interfaces\RepositoryInterface;
use App\requests\BaseRequest;
use App\responses\JsonResponse;
use App\validations\EmployeeValidator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EmployeeController {
    private $container;
    private $repo;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        
        $this->repo = new class($this->container->get('db')) implements RepositoryInterface {
            private $db;
            public function __construct($db) { $this->db = $db; }
            public function fetchData(array $params = []): array {
                // Generic fetch; extend per method
                return [];
            }
        };
    }

    // get list of all employees but limit to 10 rows
    public function getAllEmployees(Request $request, Response $response): Response {
        
        $stmt = $this->container->get('db')->prepare("SELECT * 
                                                    FROM employees
                                                    LIMIT 5");
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC ?: []);
        return JsonResponse::is200Response($response, $data);
    }

    // get complete details of one employee
    public function getProfile(Request $request, Response $response, array $args): Response {
        $emp_no = $args['emp_no'];
        // $v = EmployeeValidator::validateEmpNo($emp_no);
        // echo "(" . json_encode($v) . ")";
        if (!EmployeeValidator::validateEmpNo($emp_no)) {
            return JsonResponse::is400Response($response, 'Invalid emp_no');
        }
        $stmt = $this->container->get('db')->prepare("SELECT e.emp_no, e.first_name, e.last_name, e.gender, e.hire_date, e.birth_date,
                                                        d.dept_name, s.salary, t.title
                                                    FROM employees e
                                                    JOIN dept_emp de ON e.emp_no = de.emp_no
                                                    JOIN departments d ON de.dept_no = d.dept_no
                                                    JOIN salaries s ON e.emp_no = s.emp_no
                                                    JOIN titles t ON e.emp_no = t.emp_no
                                                    WHERE de.to_date > CURDATE() AND s.to_date > CURDATE() AND t.to_date > CURDATE()
                                                    AND e.emp_no = ?");
        $stmt->execute([$emp_no]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        return JsonResponse::is200Response($response, $data);
        
    }

    // return details of one emploee salary history
    public function getSalaryHistory(Request $request, Response $response, array $args): Response {
        // $req = new BaseRequest($request);
        // $emp_no = $req->get('emp_no');
        $emp_no = $args['emp_no'];
        if (!EmployeeValidator::validateEmpNo($emp_no)) {
            return JsonResponse::is400Response($response, 'Invalid emp_no');
        }
        $stmt = $this->container->get('db')->prepare("SELECT emp_no, salary, from_date, to_date,
                                                           ROW_NUMBER() OVER (PARTITION BY emp_no ORDER BY from_date) AS change_number,
                                                           salary - LAG(salary) OVER (PARTITION BY emp_no ORDER BY from_date) AS raise_amount
                                                    FROM salaries
                                                    WHERE emp_no = ?
                                                    ORDER BY from_date DESC");
        $stmt->execute([$emp_no]);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return JsonResponse::is200Response($response, $data);
    }

    // return details of hiring histroy limited to 10 rows
    public function getHires(Request $request, Response $response): Response {
        $req = new BaseRequest($request);
        $order = strtoupper($req->get('order', 'ASC'));
        if (!EmployeeValidator::validateOrder($order)) {
            return JsonResponse::is400Response($response, 'Invalid order');
        }
        $stmt = $this->container->get('db')->prepare("SELECT e.emp_no, CONCAT(e.first_name, ' ', e.last_name) AS name, e.hire_date, d.dept_name
                                                    FROM employees e
                                                    JOIN dept_emp de ON e.emp_no = de.emp_no
                                                    JOIN departments d ON de.dept_no = d.dept_no
                                                    WHERE de.to_date > CURDATE()
                                                    ORDER BY e.hire_date $order
                                                    LIMIT 10");
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return JsonResponse::is200Response($response, $data);
    }

    public function createProfile(Request $request, Response $response): Response {
        $req = new BaseRequest($request);
        $first_name = $req->get('first_name');
        $last_name = $req->get('last_name');
        $gender = $req->get('gender');
        $hire_date = $req->get('hire_date');
        $birth_date = $req->get('birth_date');
        $dept_no = $req->get('dept_no');
        $salary = $req->get('salary');
        $title = $req->get('title');

        // Simple validation
        if (!$first_name || !$last_name || !EmployeeValidator::validateGender($gender) ||
            !EmployeeValidator::validateDate($hire_date) || !EmployeeValidator::validateDate($birth_date) ||
            !EmployeeValidator::validateDeptNo($dept_no) || !is_numeric($salary) || !$title) {
            return JsonResponse::is400Response($response, 'Invalid input data');
        }

        $db = $this->container->get('db');
        $db->beginTransaction();
        try {
            // Insert employee (emp_no auto-increment)
            $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, gender, hire_date, birth_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $gender, $hire_date, $birth_date]);
            $emp_no = $db->lastInsertId();

            // Insert dept_emp (current)
            $stmt = $db->prepare("INSERT INTO dept_emp (emp_no, dept_no, from_date, to_date) VALUES (?, ?, CURDATE(), '9999-01-01')");
            $stmt->execute([$emp_no, $dept_no]);

            // Insert salary (current)
            $stmt = $db->prepare("INSERT INTO salaries (emp_no, salary, from_date, to_date) VALUES (?, ?, CURDATE(), '9999-01-01')");
            $stmt->execute([$emp_no, $salary]);

            // Insert title (current)
            $stmt = $db->prepare("INSERT INTO titles (emp_no, title, from_date, to_date) VALUES (?, ?, CURDATE(), '9999-01-01')");
            $stmt->execute([$emp_no, $title]);

            $db->commit();
            return JsonResponse::is200Response($response, ['emp_no' => $emp_no, 'message' => 'Profile created']);
        } catch (\Exception $e) {
            $db->rollBack();
            return JsonResponse::is400Response($response, 'Creation failed: ' . $e->getMessage(), 500);
        }
    }

    // update existing profile
    public function updateProfile(Request $request, Response $response, array $args): Response {
        $emp_no = $args['emp_no'];
        if (!EmployeeValidator::validateEmpNo($emp_no)) {
            return JsonResponse::is400Response($response, 'Invalid emp_no');
        }

        $req = new BaseRequest($request);
        $last_name = $req->get('last_name');
        $salary = $req->get('salary');
        $dept_no = $req->get('dept_no');

        if (!$last_name && !$salary && !$dept_no) {
            return JsonResponse::is400Response($response, 'No updates provided');
        }

        $db = $this->container->get('db');
        $db->beginTransaction();
        try {
            // update surname
            if ($last_name) {
                $stmt = $db->prepare("UPDATE employees SET last_name = ? WHERE emp_no = ?");
                $stmt->execute([$last_name, $emp_no]);
            }

            // update new salary
            if ($salary && is_numeric($salary)) {
                $stmt = $db->prepare("INSERT INTO salaries (emp_no, salary, from_date, to_date) VALUES (?, ?, CURDATE(), '9999-01-01')");
                $stmt->execute([$emp_no, $salary]);
            }

            // update department
            if ($dept_no && EmployeeValidator::validateDeptNo($dept_no)) {
                $stmt = $db->prepare("INSERT INTO dept_emp (emp_no, dept_no, from_date, to_date) VALUES (?, ?, CURDATE(), '9999-01-01')");
                $stmt->execute([$emp_no, $dept_no]);
            }

            $db->commit();
            return JsonResponse::is200Response($response, ['message' => 'Profile updated']);
        } catch (\Exception $e) {
            $db->rollBack();
            return JsonResponse::is400Response($response, 'Update failed: ' . $e->getMessage(), 500);
        }
    }

    public function deleteProfile(Request $request, Response $response, array $args): Response {
        $emp_no = $args['emp_no'];
        if (!EmployeeValidator::validateEmpNo($emp_no)) {
            return JsonResponse::is400Response($response, 'Invalid emp_no');
        }

        $db = $this->container->get('db');
        $db->beginTransaction();
        try {
            // Delete child records first
            $db->prepare("DELETE FROM salaries WHERE emp_no = ?")->execute([$emp_no]);
            $db->prepare("DELETE FROM titles WHERE emp_no = ?")->execute([$emp_no]);
            $db->prepare("DELETE FROM dept_emp WHERE emp_no = ?")->execute([$emp_no]);
            $db->prepare("DELETE FROM dept_manager WHERE emp_no = ?")->execute([$emp_no]); // If manager
            $db->prepare("DELETE FROM employees WHERE emp_no = ?")->execute([$emp_no]);

            $db->commit();
            return JsonResponse::is200Response($response, ['message' => 'Profile deleted']);
        } catch (\Exception $e) {
            $db->rollBack();
            return JsonResponse::is400Response($response, 'Delete failed: ' . $e->getMessage(), 500);
        }
    }
}
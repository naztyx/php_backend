<?php

namespace App\controllers;

use App\interfaces\RepositoryInterface;
use App\requests\BaseRequest;
use App\responses\JsonResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class EmployeeController2
{
    private $container;
    private $validator;
    private $customResponse;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->validator = $container->get('validator');
        $this->customResponse = new JsonResponse(); 
    }
    // create a new employee with details
    public function addEmployee(Request $request, Response $response)
    {
        $req = new BaseRequest($request);

        $body = $req->getBody();
        if (empty($body)) {
            return $this->customResponse->is400Response($response, ['error' => 'Request body is empty']);
        }

        try {
            v::key('first_name', v::notEmpty()->stringType()->length(1, 14))
              ->key('last_name', v::notEmpty()->stringType()->length(1, 16))
              ->key('gender', v::notEmpty()->in(['M', 'F']))
              ->key('hire_date', v::notEmpty()->date('Y-m-d'))
              ->key('birth_date', v::notEmpty()->date('Y-m-d'))
              ->key('dept_name', v::notEmpty()->stringType()->length(1, 40))
              ->key('salary', v::notEmpty()->intVal()->positive())
              ->key('title', v::notEmpty()->stringType()->length(1, 50))
              ->assert($req->$body);
        } catch (NestedValidationException $e) {
            $responseMessage = $e->getMessages();
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        if ($this->validator->failed()) {
            $responseMessage = $this->validator->errors;
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        $db = $this->container->get('db');
        $db->beginTransaction();

        try {
            //// auto generate emp_no
            // $stmt = $db->prepare("SELECT MAX(emp_no) AS last_emp_no FROM employees");
            // $stmt->execute();
            // $lastEmpNo = $stmt->fetchColumn() ?: 0;
            // $newEmpNo = $lastEmpNo + 1;

            // // verify uniqueness of generated emp_no
            // while (true) {
            //     $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE emp_no = ?");
            //     $stmt->execute([$newEmpNo]);
            //     if ($stmt->fetchColumn() == 0) {
            //         break; // Unique emp_no found
            //     }
            //     $newEmpNo++;
            // }

            // Insert into employees
            $stmt = $db->prepare("INSERT INTO employees (emp_no, first_name, last_name, gender, hire_date, birth_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $req->get('first_name'),
                $req->get('last_name'),
                $req->get('gender'),
                $req->get('hire_date'),
                $req->get('birth_date')
            ]);
            // retrieve the emp_no after executing the above code
            $emp_no = $db->lastInsertId();

            // Get dept_no from dept_name
            $stmt = $db->prepare("SELECT dept_no FROM departments WHERE dept_name = ?");
            $stmt->execute([$req->get('dept_name')]);
            $dept_no = $stmt->fetchColumn();
            if (!$dept_no) {
                throw new \Exception('Department not found');
            }

            $today = date('Y-m-d');
            $to_date = '9999-01-01';

            // Insert into department table
            $stmt = $db->prepare("INSERT INTO dept_emp (emp_no, dept_no, from_date, to_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$emp_no, $dept_no, $today, $to_date]);

            // Insert into salaries table
            $stmt = $db->prepare("INSERT INTO salaries (emp_no, salary, from_date, to_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$emp_no, $req->get('salary'), $today, $to_date]);

            // Insert into titles tbale
            $stmt = $db->prepare("INSERT INTO titles (emp_no, title, from_date, to_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$emp_no, $req->get('title'), $today, $to_date]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $responseMessage = $e->getMessage();
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        $responseMessage = "Employee added successfully with emp_no: $emp_no";
        return $this->customResponse->is200Response($response, $responseMessage);
    }

    // update or patch data into an existing entry
    public function updateEmployee(Request $request, Response $response, array $args)
    {
        $req = new BaseRequest($request);
        $emp_no = $args['emp_no'];

        $this->validator->validate($request, [
            'salary' => v::optional(v::intVal()->positive()),
            'last_name' => v::optional(v::stringType()->length(1, 16)),
            'dept_name' => v::optional(v::stringType()->length(1, 40)),
        ], $args);

        if ($this->validator->failed()) {
            $responseMessage = $this->validator->errors;
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        if (!$req->get('salary') && !$req->get('last_name') && !$req->get('dept_name')) {
            $responseMessage = "No updates provided";
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        $db = $this->container->get('db');
        $db->beginTransaction();

        try {
            // Update last_name if provided
            if ($req->get('last_name')) {
                $stmt = $db->prepare("UPDATE employees SET last_name = ? WHERE emp_no = ?");
                $stmt->execute([$req->get('last_name'), $emp_no]);
            }

            // Update salary: Insert new salary, end the current
            if ($req->get('salary')) {
                $today = date('Y-m-d');
                $to_date = '9999-01-01';

                // End current salary
                $stmt = $db->prepare("UPDATE salaries SET to_date = ? WHERE emp_no = ? AND to_date > CURDATE()");
                $stmt->execute([$today, $emp_no]);

                // Insert new salary
                $stmt = $db->prepare("INSERT INTO salaries (emp_no, salary, from_date, to_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$emp_no, $req->get('salary'), $today, $to_date]);
            }

            // Update department: Insert new dept_emp, end the current
            if ($req->get('dept_name')) {
                $stmt = $db->prepare("SELECT dept_no FROM departments WHERE dept_name = ?");
                $stmt->execute([$req->get('dept_name')]);
                $dept_no = $stmt->fetchColumn();
                if (!$dept_no) {
                    throw new \Exception('Department not found');
                }

                $today = date('Y-m-d');
                $to_date = '9999-01-01';

                // End current dept
                $stmt = $db->prepare("UPDATE dept_emp SET to_date = ? WHERE emp_no = ? AND to_date > CURDATE()");
                $stmt->execute([$today, $emp_no]);

                // Insertor assign new
                $stmt = $db->prepare("INSERT INTO dept_emp (emp_no, dept_no, from_date, to_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$emp_no, $dept_no, $today, $to_date]);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $responseMessage = $e->getMessage();
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        $responseMessage = "Employee updated successfully";
        return $this->customResponse->is200Response($response, $responseMessage);
    }

    // delete all records belonging to an emloyee
    public function deleteEmployee(Request $request, Response $response, array $args)
    {
        $req = new BaseRequest($request);
        $emp_no = $args['emp_no'];

        $this->validator->validate($request, [
            'emp_no' => v::notEmpty()->intVal()->positive(),
        ], $args);

        if ($this->validator->failed()) {
            $responseMessage = $this->validator->errors;
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        $db = $this->container->get('db');
        $db->beginTransaction();

        try {
            // Delete from dept_emp
            $stmt = $db->prepare("DELETE FROM dept_emp WHERE emp_no = ?");
            $stmt->execute([$emp_no]);

            // Delete from dept_manager if applicable
            $stmt = $db->prepare("DELETE FROM dept_manager WHERE emp_no = ?");
            $stmt->execute([$emp_no]);

            // Delete from salaries
            $stmt = $db->prepare("DELETE FROM salaries WHERE emp_no = ?");
            $stmt->execute([$emp_no]);

            // Delete from titles
            $stmt = $db->prepare("DELETE FROM titles WHERE emp_no = ?");
            $stmt->execute([$emp_no]);

            // Delete from employees
            $stmt = $db->prepare("DELETE FROM employees WHERE emp_no = ?");
            $stmt->execute([$emp_no]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $responseMessage = $e->getMessage();
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        $responseMessage = "Employee deleted successfully";
        return $this->customResponse->is200Response($response, $responseMessage);
    }
}
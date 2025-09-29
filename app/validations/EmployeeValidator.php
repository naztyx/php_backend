<?php
// app/validations/EmployeeValidator.php - Validates employee-related inputs.

namespace App\validations;

class EmployeeValidator {

    // Validates emp_no is a positive integer.
    public static function validateEmpNo($emp_no): bool {
        return is_numeric($emp_no) && $emp_no > 0;
    }

    // Validates order is ASC or DESC
    public static function validateOrder($order): bool {
        return in_array(strtoupper($order), ['ASC', 'DESC']);
    }

    public static function validateGender($gender): bool {
        return in_array($gender, ['M', 'F']);
    }

    // check date is in right format i.e YYYY-MM-DD
    public static function validateDate($date): bool {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date); 
    }

    // validate dpt no. like d001
    public static function validateDeptNo($dept_no): bool {
        return preg_match('/^d\d{3}$/', $dept_no); 
    }
}
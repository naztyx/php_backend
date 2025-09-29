<?php
// Simple model representing an Employee entity

namespace App\models;

class Employees {
    public $emp_no;
    public $first_name;
    public $last_name;
    public $birth_date;
    public $gender;
    public $hire_date;
    
    public function __construct(array $data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}

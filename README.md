# php_backend

```markdown
# Employee Management API

This is a simple PHP-based RESTful API built using the Slim Framework to manage employee data, leveraging a MySQL Employees sample database. The API provides endpoints for creating, retrieving, updating, and deleting employee profiles.

## Features
- Create new employee profiles with details like name, department, salary, and title.
- Retrieve detailed employee profiles, department statistics, manager hierarchies, salary histories, promotion trends, and hire lists.
- Update employee information (e.g., salary, surname, department) with historical tracking.
- Delete all records associated with an employee.
- Secured with basic input validation and database transactions.

## Prerequisites
- PHP 7.4 or higher
- Composer (for dependency management)
- MySQL (with the Employees sample database)
- XAMPP or equivalent local server environment
```
The sample databse can be gotten from [here](https://github.com/datacharmer/test_db.git)

## Project Installation

1. **Clone the Repository**
   ```bash
   git clone this repository and, cd into it
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Configure Environment**
   - Copy `.env.example` to `.env` and update with your MySQL credentials:
     ```
     DB_HOST=localhost
     DB_NAME=employees
     DB_USER=your_username
     DB_PASS=your_password
     ```
   - Ensure the Employees database is set up locally.

4. **Run the Application**
   - Start the PHP built-in server:
     ```bash
     php -S localhost:8080 -t public
     ```
     or
     ```bash
     composer start
     ```
   - Alternatively, configure with XAMPP's Apache + PHP-FPM for better performance.

## API Endpoints
- **GET /employee/{emp_no}/profile**: Fetch an employee's full profile.
- **GET /departments/stats**: Get department statistics.
- **GET /managers**: Retrieve manager hierarchy.
- **Get /hires**: List hires (body: {"order": "ASC"}).
- **POST /new_profile**: Create a new employee profile (COntent-Type: apllication/json, body: {"first_name": "John", "last_name": "Doe", "gender": "M", "hire_date": "2025-09-28", "birth_date": "1990-01-01", "dept_no": "d001", "salary": 60000, "title": "Engineer"}.
- **PATCH /profile/{emp_no}**: Update employee details (body: {"last_name": "NewDoe", ...}).
- **DELETE /profile/{emp_no}**: Delete an employee’s records.

## Testing
Use [Postman](https://www.postman.com/downloads/), [HTTPie](https://httpie.io/download), [Insomnia](https://insomnia.rest/download) or similar tools to test endpoints. Example:
- GET http://localhost:8080/employee/{emp_no}/profile
- POST http://localhost:8080/employees (Content-Type: application/json. Body: {"first_name": "John", "last_name": "Doe", "gender": "M", "hire_date": "2025-09-28", "birth_date": "1990-01-01", "dept_no": "d001", "salary": 60000, "title": "Engineer"})
- PATCH http://localhost:8080/employee/10001 (Body: {"salary": 70000})
- DELETE http://localhost:8080/employee/10001

## Deployment
This project is configured for local development. For production, consider:
- Switching to a web server (e.g., Nginx + PHP-FPM) and Securing API with authentication.

## Demonstration
Check out a detailed walkthrough of the API in action here: [😉].

## Contributing
Feel free to fork this repository, submit issues, or pull requests. Ensure you follow the existing code style.

## Contact
For questions, reach out via the GitHub Issues page or send a mail via samuelpaulyila@gmail.com

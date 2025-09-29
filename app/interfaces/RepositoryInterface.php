<?php
//Defines a common interface for repositories to ensure OOP consistency.

namespace App\interfaces;

interface RepositoryInterface {
    // /**
    //  * Fetches data based on provided parameters.
    //  * @param array $params Parameters for the query.
    //  * @return array Fetched data.
    //  */
    public function fetchData(array $params = []): array;
}
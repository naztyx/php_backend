<?php
// Base class for handling incoming requests

namespace App\requests;

use Psr\Http\Message\ServerRequestInterface as Request;

class BaseRequest {
    protected $request;
    protected $body;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->body = json_decode($request->getBody()->getContents(), true) ?? [];
    }

    // Gets a value from the request body.
    public function get(string $key, $default = null) {
        return $this->body[$key] ?? $default;
    }
}
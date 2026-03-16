<?php

class Response {
    public static function success($data = null, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public static function error($message, $code_str = 'ERROR', $http_code = 400) {
        http_response_code($http_code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $message, 'code' => $code_str]);
        exit;
    }
}
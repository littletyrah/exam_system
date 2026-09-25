<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

define('JWT_SECRET', 'your_super_secret_key_change_this_later');

function generateToken($user) {
    $payload = [
        'user_id' => $user['user_id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'iat' => time(),
        'exp' => time() + (60 * 60 * 2) // token valid for 2 hours
    ];

    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

function verifyToken($token) {
    try {
        return JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
    } catch (Exception $e) {
        return null;
    }
}

function requireAuth($request, $requiredRoles = []) {
    $authHeader = $request->getHeaderLine('Authorization');

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return ['error' => 'Missing or invalid Authorization header', 'status' => 401];
    }

    $token = $matches[1];
    $decoded = verifyToken($token);

    if (!$decoded) {
        return ['error' => 'Invalid or expired token', 'status' => 401];
    }

    if (!empty($requiredRoles) && !in_array($decoded->role, $requiredRoles)) {
        return ['error' => 'Insufficient permissions for this action', 'status' => 403];
    }

    return ['user' => $decoded];
}
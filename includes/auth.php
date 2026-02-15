<?php
function startAppSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function currentUser(): ?array
{
    startAppSession();
    return $_SESSION['user'] ?? null;
}

function currentStudent(): ?array
{
    startAppSession();
    return $_SESSION['student_user'] ?? null;
}

function requireLogin(bool $isApi = false): array
{
    $user = currentUser();
    if ($user !== null) {
        return $user;
    }

    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized. Please log in.']);
        exit;
    }

    header('Location: login.php');
    exit;
}

function requireRole(array $roles, bool $isApi = false): array
{
    $user = requireLogin($isApi);
    if (in_array($user['role'], $roles, true)) {
        return $user;
    }

    if ($isApi) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Forbidden for your role.']);
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function requireStudentLogin(bool $isApi = false): array
{
    $student = currentStudent();
    if ($student !== null) {
        return $student;
    }

    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized student access. Please log in.']);
        exit;
    }

    header('Location: login.php');
    exit;
}


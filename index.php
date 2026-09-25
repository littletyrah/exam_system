<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->setBasePath('/exam-system-api');


// Centralized error handling middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    $request,
    $exception,
    $displayErrorDetails,
    $logErrors,
    $logErrorDetails
) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    $statusCode = 500;

    if (method_exists($exception, 'getCode') && $exception->getCode() >= 400 && $exception->getCode() < 600) {
        $statusCode = $exception->getCode();
    }

    $payload = [
        'error' => $exception->getMessage() ?: 'An unexpected error occurred'
    ];

    $response->getBody()->write(json_encode($payload));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
});

// LOGIN - authenticate user and issue JWT
$app->post('/login', function ($request, $response) {
    $data = json_decode($request->getBody()->getContents(), true);

    if (empty($data['email']) || empty($data['password'])) {
        $response->getBody()->write(json_encode(['error' => 'email and password are required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data['password'], $user['password'])) {
        $response->getBody()->write(json_encode(['error' => 'Invalid email or password']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    $token = generateToken($user);

    $response->getBody()->write(json_encode([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'user_id' => $user['user_id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/ping', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/users', function ($request, $response) {
    $pdo = getDbConnection();
    $stmt = $pdo->query('SELECT user_id, full_name, email, role, created_at FROM users');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($users));
    return $response->withHeader('Content-Type', 'application/json');
});

// GET single user by ID
$app->get('/users/{id}', function ($request, $response, $args) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT user_id, full_name, email, role, created_at FROM users WHERE user_id = ?');
    $stmt->execute([$args['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'User not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $response->getBody()->write(json_encode($user));
    return $response->withHeader('Content-Type', 'application/json');
});

// CREATE a new user
// CREATE a new user
$app->post('/users', function ($request, $response) {
    $data = json_decode($request->getBody()->getContents(), true);

    if (empty($data['full_name']) || empty($data['email']) || empty($data['password'])) {
        $response->getBody()->write(json_encode(['error' => 'full_name, email, and password are required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // Default role is student unless an authenticated admin sets it explicitly
    $requestedRole = $data['role'] ?? 'student';
    $authHeader = $request->getHeaderLine('Authorization');
    $isAdmin = false;

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $decoded = verifyToken($matches[1]);
        if ($decoded && $decoded->role === 'admin') {
            $isAdmin = true;
        }
    }

    if ($requestedRole !== 'student' && !$isAdmin) {
        $response->getBody()->write(json_encode(['error' => 'Only an admin can create lecturer or admin accounts']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    if (!in_array($requestedRole, ['admin', 'lecturer', 'student'])) {
        $response->getBody()->write(json_encode(['error' => 'role must be admin, lecturer, or student']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $data['role'] = $requestedRole;
    $pdo = getDbConnection();
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['full_name'], $data['email'], $hashedPassword, $data['role']]);

        $newId = $pdo->lastInsertId();
        $response->getBody()->write(json_encode(['message' => 'User created', 'user_id' => $newId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => 'Email already exists or invalid data']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

// UPDATE a user
$app->put('/users/{id}', function ($request, $response, $args) {
    $data = json_decode($request->getBody()->getContents(), true);
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ?');
    $stmt->execute([$args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'User not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    if (empty($data['full_name']) || empty($data['email']) || empty($data['role'])) {
        $response->getBody()->write(json_encode(['error' => 'full_name, email, and role are required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, role = ? WHERE user_id = ?');
    $stmt->execute([$data['full_name'], $data['email'], $data['role'], $args['id']]);

    $response->getBody()->write(json_encode(['message' => 'User updated']));
    return $response->withHeader('Content-Type', 'application/json');
});

// DELETE a user
$app->delete('/users/{id}', function ($request, $response, $args) {
    $auth = requireAuth($request, ['admin']);
    if (isset($auth['error'])) {
        $response->getBody()->write(json_encode(['error' => $auth['error']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($auth['status']);
    }

    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ?');
    $stmt->execute([$args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'User not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
    $stmt->execute([$args['id']]);

    $response->getBody()->write(json_encode(['message' => 'User deleted']));
    return $response->withHeader('Content-Type', 'application/json');
});

// GET all courses
$app->get('/courses', function ($request, $response) {
    $pdo = getDbConnection();
    $stmt = $pdo->query('SELECT * FROM courses');
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode($courses));
    return $response->withHeader('Content-Type', 'application/json');
});

// GET single course
$app->get('/courses/{id}', function ($request, $response, $args) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE course_id = ?');
    $stmt->execute([$args['id']]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        $response->getBody()->write(json_encode(['error' => 'Course not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $response->getBody()->write(json_encode($course));
    return $response->withHeader('Content-Type', 'application/json');
});

// CREATE a course
$app->post('/courses', function ($request, $response) {
    $data = json_decode($request->getBody()->getContents(), true);



    $auth = requireAuth($request, ['admin', 'lecturer']);
    if (isset($auth['error'])) {
        $response->getBody()->write(json_encode(['error' => $auth['error']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($auth['status']);
    }

    if (empty($data['course_code']) || empty($data['course_name']) || empty($data['lecturer_id'])) {
        $response->getBody()->write(json_encode(['error' => 'course_code, course_name, and lecturer_id are required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $pdo = getDbConnection();

    // verify lecturer_id actually exists and is a lecturer
    $check = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ? AND role = "lecturer"');
    $check->execute([$data['lecturer_id']]);
    if (!$check->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'lecturer_id must reference an existing lecturer']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO courses (course_code, course_name, lecturer_id) VALUES (?, ?, ?)');
        $stmt->execute([$data['course_code'], $data['course_name'], $data['lecturer_id']]);

        $newId = $pdo->lastInsertId();
        $response->getBody()->write(json_encode(['message' => 'Course created', 'course_id' => $newId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => 'course_code already exists or invalid data']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

// UPDATE a course
// UPDATE a course
$app->put('/courses/{id}', function ($request, $response, $args) {
    $auth = requireAuth($request, ['admin', 'lecturer']);
    if (isset($auth['error'])) {
        $response->getBody()->write(json_encode(['error' => $auth['error']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($auth['status']);
    }

    $data = json_decode($request->getBody()->getContents(), true);
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT course_id FROM courses WHERE course_id = ?');
    $stmt->execute([$args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Course not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    if (empty($data['course_code']) || empty($data['course_name']) || empty($data['lecturer_id'])) {
        $response->getBody()->write(json_encode(['error' => 'course_code, course_name, and lecturer_id are required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $stmt = $pdo->prepare('UPDATE courses SET course_code = ?, course_name = ?, lecturer_id = ? WHERE course_id = ?');
    $stmt->execute([$data['course_code'], $data['course_name'], $data['lecturer_id'], $args['id']]);

    $response->getBody()->write(json_encode(['message' => 'Course updated']));
    return $response->withHeader('Content-Type', 'application/json');
});

// DELETE a course
// DELETE a course
$app->delete('/courses/{id}', function ($request, $response, $args) {
    $auth = requireAuth($request, ['admin', 'lecturer']);
    if (isset($auth['error'])) {
        $response->getBody()->write(json_encode(['error' => $auth['error']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($auth['status']);
    }

    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT course_id FROM courses WHERE course_id = ?');
    $stmt->execute([$args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Course not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $stmt = $pdo->prepare('DELETE FROM courses WHERE course_id = ?');
    $stmt->execute([$args['id']]);

    $response->getBody()->write(json_encode(['message' => 'Course deleted']));
    return $response->withHeader('Content-Type', 'application/json');
});

// GET all examinations (with pagination + filtering)
$app->get('/examinations', function ($request, $response) {
    $pdo = getDbConnection();
    $params = $request->getQueryParams();

    $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
    $limit = isset($params['limit']) ? max(1, (int)$params['limit']) : 10;
    $offset = ($page - 1) * $limit;

    $where = [];
    $bindings = [];

    if (!empty($params['course_id'])) {
        $where[] = 'course_id = ?';
        $bindings[] = $params['course_id'];
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM examinations $whereClause");
    $countStmt->execute($bindings);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->prepare("SELECT * FROM examinations $whereClause LIMIT $limit OFFSET $offset");
    $stmt->execute($bindings);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode([
        'data' => $exams,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'total_pages' => ceil($total / $limit)
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});
// GET single examination
$app->get('/examinations/{id}', function ($request, $response, $args) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM examinations WHERE exam_id = ?');
    $stmt->execute([$args['id']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        $response->getBody()->write(json_encode(['error' => 'Examination not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $response->getBody()->write(json_encode($exam));
    return $response->withHeader('Content-Type', 'application/json');
});

// CREATE an examination
// CREATE an examination
$app->post('/examinations', function ($request, $response) {
    $auth = requireAuth($request, ['admin', 'lecturer']);
    if (isset($auth['error'])) {
        $response->getBody()->write(json_encode(['error' => $auth['error']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($auth['status']);
    }

    $data = json_decode($request->getBody()->getContents(), true);

    if (empty($data['course_id']) || empty($data['exam_title']) || empty($data['exam_date']) || empty($data['exam_time'])) {
        $response->getBody()->write(json_encode(['error' => 'course_id, exam_title, exam_date, and exam_time are required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $pdo = getDbConnection();

    $check = $pdo->prepare('SELECT course_id FROM courses WHERE course_id = ?');
    $check->execute([$data['course_id']]);
    if (!$check->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'course_id must reference an existing course']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $stmt = $pdo->prepare('INSERT INTO examinations (course_id, exam_title, exam_date, exam_time, venue) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$data['course_id'], $data['exam_title'], $data['exam_date'], $data['exam_time'], $data['venue'] ?? null]);

    $newId = $pdo->lastInsertId();
    $response->getBody()->write(json_encode(['message' => 'Examination created', 'exam_id' => $newId]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

// UPDATE an examination
// UPDATE an examination
$app->put('/examinations/{id}', function ($request, $response, $args) {
    $auth = requireAuth($request, ['admin', 'lecturer']);
    if (isset($auth['error'])) {
        $response->getBody()->write(json_encode(['error' => $auth['error']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($auth['status']);
    }

    $data = json_decode($request->getBody()->getContents(), true);
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT exam_id FROM examinations WHERE exam_id = ?');
    $stmt->execute([$args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Examination not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    if (empty($data['exam_title']) || empty($data['exam_date']) || empty($data['exam_time'])) {
        $response->getBody()->write(json_encode(['error' => 'exam_title, exam_date, and exam_time are required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $stmt = $pdo->prepare('UPDATE examinations SET exam_title = ?, exam_date = ?, exam_time = ?, venue = ? WHERE exam_id = ?');
    $stmt->execute([$data['exam_title'], $data['exam_date'], $data['exam_time'], $data['venue'] ?? null, $args['id']]);

    $response->getBody()->write(json_encode(['message' => 'Examination updated']));
    return $response->withHeader('Content-Type', 'application/json');
});

// DELETE an examination
// DELETE an examination
$app->delete('/examinations/{id}', function ($request, $response, $args) {
    $auth = requireAuth($request, ['admin', 'lecturer']);
    if (isset($auth['error'])) {
        $response->getBody()->write(json_encode(['error' => $auth['error']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($auth['status']);
    }

    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT exam_id FROM examinations WHERE exam_id = ?');
    $stmt->execute([$args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Examination not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $stmt = $pdo->prepare('DELETE FROM examinations WHERE exam_id = ?');
    $stmt->execute([$args['id']]);

    $response->getBody()->write(json_encode(['message' => 'Examination deleted']));
    return $response->withHeader('Content-Type', 'application/json');
});

// GET all results (with pagination + filtering)
$app->get('/results', function ($request, $response) {
    $pdo = getDbConnection();
    $params = $request->getQueryParams();

    $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
    $limit = isset($params['limit']) ? max(1, (int)$params['limit']) : 10;
    $offset = ($page - 1) * $limit;

    $where = [];
    $bindings = [];

    if (!empty($params['exam_id'])) {
        $where[] = 'exam_id = ?';
        $bindings[] = $params['exam_id'];
    }
    if (!empty($params['student_id'])) {
        $where[] = 'student_id = ?';
        $bindings[] = $params['student_id'];
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM results $whereClause");
    $countStmt->execute($bindings);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->prepare("SELECT * FROM results $whereClause LIMIT $limit OFFSET $offset");
    $stmt->execute($bindings);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode([
        'data' => $results,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'total_pages' => ceil($total / $limit)
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});
// CREATE a result
// CREATE a result
$app->post('/results', function ($request, $response) {
    $auth = requireAuth($request, ['admin', 'lecturer']);
    if (isset($auth['error'])) {
        $response->getBody()->write(json_encode(['error' => $auth['error']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($auth['status']);
    }

    $data = json_decode($request->getBody()->getContents(), true);

    if (empty($data['exam_id']) || empty($data['student_id']) || !isset($data['score'])) {
        $response->getBody()->write(json_encode(['error' => 'exam_id, student_id, and score are required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    if ($data['score'] < 0 || $data['score'] > 100) {
        $response->getBody()->write(json_encode(['error' => 'score must be between 0 and 100']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $pdo = getDbConnection();

    try {
        $stmt = $pdo->prepare('INSERT INTO results (exam_id, student_id, score, grade) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['exam_id'], $data['student_id'], $data['score'], $data['grade'] ?? null]);

        $newId = $pdo->lastInsertId();
        $response->getBody()->write(json_encode(['message' => 'Result created', 'result_id' => $newId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => 'Invalid exam_id/student_id or duplicate result']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

// UPDATE a result
// UPDATE a result
$app->put('/results/{id}', function ($request, $response, $args) {
    $auth = requireAuth($request, ['admin', 'lecturer']);
    if (isset($auth['error'])) {
        $response->getBody()->write(json_encode(['error' => $auth['error']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($auth['status']);
    }

    $data = json_decode($request->getBody()->getContents(), true);
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT result_id FROM results WHERE result_id = ?');
    $stmt->execute([$args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Result not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    if (!isset($data['score']) || $data['score'] < 0 || $data['score'] > 100) {
        $response->getBody()->write(json_encode(['error' => 'score is required and must be between 0 and 100']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $stmt = $pdo->prepare('UPDATE results SET score = ?, grade = ? WHERE result_id = ?');
    $stmt->execute([$data['score'], $data['grade'] ?? null, $args['id']]);

    $response->getBody()->write(json_encode(['message' => 'Result updated']));
    return $response->withHeader('Content-Type', 'application/json');
});

// DELETE a result
// DELETE a result
$app->delete('/results/{id}', function ($request, $response, $args) {
    $auth = requireAuth($request, ['admin', 'lecturer']);
    if (isset($auth['error'])) {
        $response->getBody()->write(json_encode(['error' => $auth['error']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($auth['status']);
    }

    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT result_id FROM results WHERE result_id = ?');
    $stmt->execute([$args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Result not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $stmt = $pdo->prepare('DELETE FROM results WHERE result_id = ?');
    $stmt->execute([$args['id']]);

    $response->getBody()->write(json_encode(['message' => 'Result deleted']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
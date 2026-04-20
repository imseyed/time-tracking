<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_input(): array
{
    $raw = file_get_contents('php://input') ?: '{}';
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function send(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function minutes_between(string $start, string $end): int
{
    $s = strtotime($start);
    $e = strtotime($end);
    if ($s === false || $e === false || $e <= $s) {
        return 0;
    }

    return (int) round(($e - $s) / 60);
}

function current_user(PDO $pdo): ?array
{
    $authUserId = (int) ($_GET['auth_user_id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_input();
        $authUserId = (int) ($data['auth_user_id'] ?? $authUserId);
        $GLOBALS['__json_input'] = $data;
    }

    if ($authUserId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, full_name, username, role FROM users WHERE id = :id');
    $stmt->execute([':id' => $authUserId]);
    $user = $stmt->fetch();

    return is_array($user) ? $user : null;
}

function require_login(?array $user): array
{
    if (!$user) {
        send(['error' => 'ابتدا وارد شوید.'], 401);
    }

    return $user;
}

function require_admin(array $user): void
{
    if (($user['role'] ?? '') !== 'admin') {
        send(['error' => 'شما دسترسی ادمین ندارید.'], 403);
    }
}

function body(): array
{
    return $GLOBALS['__json_input'] ?? json_input();
}

$action = $_GET['action'] ?? '';

try {
    $pdo = db();

    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_input();
        $username = trim((string) ($data['username'] ?? ''));
        $password = trim((string) ($data['password'] ?? ''));

        $stmt = $pdo->prepare('SELECT id, full_name, username, password, role FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password'])) {
            send(['error' => 'نام کاربری یا رمز عبور نادرست است.'], 401);
        }

        send([
            'message' => 'ورود موفق بود.',
            'user' => [
                'id' => (int) $user['id'],
                'full_name' => $user['full_name'],
                'username' => $user['username'],
                'role' => $user['role'],
            ],
        ]);
    }

    $me = current_user($pdo);

    if ($action === 'users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $auth = require_login($me);
        require_admin($auth);
        $rows = $pdo->query('SELECT id, full_name, username, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
        send(['data' => $rows]);
    }

    if ($action === 'user-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);
        $data = body();

        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, username, password, role) VALUES (:full_name, :username, :password, :role)'
        );
        $stmt->execute([
            ':full_name' => trim((string) ($data['full_name'] ?? '')),
            ':username' => trim((string) ($data['username'] ?? '')),
            ':password' => password_hash(trim((string) ($data['password'] ?? '')), PASSWORD_BCRYPT),
            ':role' => trim((string) ($data['role'] ?? 'user')),
        ]);

        send(['message' => 'کاربر جدید ایجاد شد.']);
    }

    if ($action === 'user-update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);
        $data = body();

        $id = (int) ($data['id'] ?? 0);
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $role = trim((string) ($data['role'] ?? 'user'));
        $password = trim((string) ($data['password'] ?? ''));

        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE users SET full_name=:full_name, role=:role, password=:password WHERE id=:id');
            $stmt->execute([
                ':id' => $id,
                ':full_name' => $fullName,
                ':role' => $role,
                ':password' => password_hash($password, PASSWORD_BCRYPT),
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET full_name=:full_name, role=:role WHERE id=:id');
            $stmt->execute([
                ':id' => $id,
                ':full_name' => $fullName,
                ':role' => $role,
            ]);
        }

        send(['message' => 'کاربر بروزرسانی شد.']);
    }

    if ($action === 'user-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);
        $data = body();
        $id = (int) ($data['id'] ?? 0);

        if ($id === (int) $auth['id']) {
            send(['error' => 'حذف ادمین جاری مجاز نیست.'], 422);
        }

        $pdo->prepare('DELETE FROM time_entries WHERE user_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
        send(['message' => 'کاربر حذف شد.']);
    }

    if ($action === 'projects' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        require_login($me);
        $rows = $pdo->query('SELECT id, name, color, created_at FROM projects ORDER BY created_at DESC')->fetchAll();
        send(['data' => $rows]);
    }

    if ($action === 'project-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);
        $data = body();
        $pdo->prepare('INSERT INTO projects (name, color) VALUES (:name, :color)')->execute([
            ':name' => trim((string) ($data['name'] ?? '')),
            ':color' => trim((string) ($data['color'] ?? '#FC572C')),
        ]);
        send(['message' => 'پروژه جدید ایجاد شد.']);
    }

    if ($action === 'project-update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);
        $data = body();
        $pdo->prepare('UPDATE projects SET name=:name, color=:color WHERE id=:id')->execute([
            ':id' => (int) ($data['id'] ?? 0),
            ':name' => trim((string) ($data['name'] ?? '')),
            ':color' => trim((string) ($data['color'] ?? '#FC572C')),
        ]);
        send(['message' => 'پروژه ویرایش شد.']);
    }

    if ($action === 'project-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);
        $data = body();
        $id = (int) ($data['id'] ?? 0);
        $pdo->prepare('DELETE FROM time_entries WHERE project_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM projects WHERE id = :id')->execute([':id' => $id]);
        send(['message' => 'پروژه حذف شد.']);
    }

    if ($action === 'entry-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        $data = body();

        $requestedUserId = (int) ($data['user_id'] ?? 0);
        $targetUserId = $auth['role'] === 'admin' && $requestedUserId > 0 ? $requestedUserId : (int) $auth['id'];

        $duration = minutes_between(trim((string) ($data['start_time'] ?? '')), trim((string) ($data['end_time'] ?? '')));
        if ($duration <= 0) {
            send(['error' => 'زمان پایان باید بعد از زمان شروع باشد.'], 422);
        }

        $pdo->prepare(
            'INSERT INTO time_entries (user_id, project_id, work_date, start_time, end_time, description, duration_minutes)
             VALUES (:user_id, :project_id, :work_date, :start_time, :end_time, :description, :duration)'
        )->execute([
            ':user_id' => $targetUserId,
            ':project_id' => (int) ($data['project_id'] ?? 0),
            ':work_date' => trim((string) ($data['work_date'] ?? '')),
            ':start_time' => trim((string) ($data['start_time'] ?? '')),
            ':end_time' => trim((string) ($data['end_time'] ?? '')),
            ':description' => trim((string) ($data['description'] ?? '')),
            ':duration' => $duration,
        ]);

        send(['message' => 'ثبت ساعت با موفقیت انجام شد.']);
    }

    if ($action === 'entry-recent' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $auth = require_login($me);
        $stmt = $pdo->prepare(
            'SELECT te.id, te.work_date, te.start_time, te.end_time, te.duration_minutes, te.description, p.name AS project_name
             FROM time_entries te
             INNER JOIN projects p ON p.id = te.project_id
             WHERE te.user_id = :user_id
             ORDER BY te.work_date DESC, te.start_time DESC
             LIMIT 25'
        );
        $stmt->execute([':user_id' => (int) $auth['id']]);
        send(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'report' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $auth = require_login($me);
        $userId = (int) ($_GET['user_id'] ?? 0);
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $fromDate = trim((string) ($_GET['from_date'] ?? ''));
        $toDate = trim((string) ($_GET['to_date'] ?? ''));

        $where = [];
        $params = [];

        if ($auth['role'] !== 'admin') {
            $where[] = 'te.user_id = :auth_user_id';
            $params[':auth_user_id'] = (int) $auth['id'];
        } elseif ($userId > 0) {
            $where[] = 'te.user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        if ($projectId > 0) {
            $where[] = 'te.project_id = :project_id';
            $params[':project_id'] = $projectId;
        }
        if ($fromDate !== '') {
            $where[] = 'te.work_date >= :from_date';
            $params[':from_date'] = $fromDate;
        }
        if ($toDate !== '') {
            $where[] = 'te.work_date <= :to_date';
            $params[':to_date'] = $toDate;
        }

        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $details = $pdo->prepare(
            "SELECT te.id, te.work_date, te.start_time, te.end_time, te.description, te.duration_minutes,
                    u.full_name AS user_name, p.name AS project_name, p.color AS project_color
             FROM time_entries te
             INNER JOIN users u ON u.id = te.user_id
             INNER JOIN projects p ON p.id = te.project_id
             $sqlWhere
             ORDER BY te.work_date DESC, te.start_time DESC"
        );
        $details->execute($params);
        $rows = $details->fetchAll();

        $totalMinutes = array_sum(array_map(static fn(array $row): int => (int) $row['duration_minutes'], $rows));

        send([
            'details' => $rows,
            'summary' => [
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 2),
                'entries_count' => count($rows),
            ],
        ]);
    }

    send(['error' => 'Endpoint نامعتبر است.'], 404);
} catch (Throwable $e) {
    send(['error' => 'خطای سرور', 'message' => $e->getMessage()], 500);
}

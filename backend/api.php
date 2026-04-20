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
        $rows = $pdo->query('SELECT id, full_name, username, role, created_at FROM users ORDER BY id DESC')->fetchAll();
        send(['data' => $rows]);
    }

    if ($action === 'user-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);

        $data = body();
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $username = trim((string) ($data['username'] ?? ''));
        $password = trim((string) ($data['password'] ?? ''));
        $role = trim((string) ($data['role'] ?? 'user'));

        if ($fullName === '' || $username === '' || $password === '') {
            send(['error' => 'نام، نام کاربری و رمز عبور الزامی است.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO users (full_name, username, password, role) VALUES (:full_name, :username, :password, :role)');
        $stmt->execute([
            ':full_name' => $fullName,
            ':username' => $username,
            ':password' => password_hash($password, PASSWORD_BCRYPT),
            ':role' => in_array($role, ['admin', 'user'], true) ? $role : 'user',
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

        if ($id <= 0 || $fullName === '') {
            send(['error' => 'اطلاعات ویرایش کاربر ناقص است.'], 422);
        }

        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, role = :role, password = :password WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':full_name' => $fullName,
                ':role' => in_array($role, ['admin', 'user'], true) ? $role : 'user',
                ':password' => password_hash($password, PASSWORD_BCRYPT),
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, role = :role WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':full_name' => $fullName,
                ':role' => in_array($role, ['admin', 'user'], true) ? $role : 'user',
            ]);
        }

        send(['message' => 'کاربر ویرایش شد.']);
    }

    if ($action === 'user-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);

        $id = (int) (body()['id'] ?? 0);
        if ($id <= 0) {
            send(['error' => 'شناسه کاربر معتبر نیست.'], 422);
        }
        if ($id === (int) $auth['id']) {
            send(['error' => 'ادمین نمی‌تواند خودش را حذف کند.'], 422);
        }

        $check = $pdo->prepare('SELECT COUNT(*) FROM time_entries WHERE user_id = :id');
        $check->execute([':id' => $id]);
        if ((int) $check->fetchColumn() > 0) {
            send(['error' => 'این کاربر رکورد زمانی دارد و قابل حذف نیست.'], 422);
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);

        send(['message' => 'کاربر حذف شد.']);
    }

    if ($action === 'projects' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        require_login($me);
        $rows = $pdo->query('SELECT id, name, color, created_at FROM projects ORDER BY id DESC')->fetchAll();
        send(['data' => $rows]);
    }

    if ($action === 'project-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);
        $data = body();
        $name = trim((string) ($data['name'] ?? ''));
        $color = trim((string) ($data['color'] ?? '#FC572C'));

        if ($name === '') {
            send(['error' => 'نام پروژه الزامی است.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO projects (name, color) VALUES (:name, :color)');
        $stmt->execute([':name' => $name, ':color' => $color]);
        send(['message' => 'پروژه ایجاد شد.']);
    }

    if ($action === 'project-update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);
        $data = body();
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $color = trim((string) ($data['color'] ?? '#FC572C'));

        if ($id <= 0 || $name === '') {
            send(['error' => 'اطلاعات پروژه ناقص است.'], 422);
        }

        $stmt = $pdo->prepare('UPDATE projects SET name = :name, color = :color WHERE id = :id');
        $stmt->execute([':id' => $id, ':name' => $name, ':color' => $color]);
        send(['message' => 'پروژه ویرایش شد.']);
    }

    if ($action === 'project-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        require_admin($auth);
        $id = (int) (body()['id'] ?? 0);

        if ($id <= 0) {
            send(['error' => 'شناسه پروژه معتبر نیست.'], 422);
        }

        $check = $pdo->prepare('SELECT COUNT(*) FROM time_entries WHERE project_id = :id');
        $check->execute([':id' => $id]);
        if ((int) $check->fetchColumn() > 0) {
            send(['error' => 'این پروژه رکورد زمانی دارد و قابل حذف نیست.'], 422);
        }

        $stmt = $pdo->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        send(['message' => 'پروژه حذف شد.']);
    }

    if ($action === 'entry-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = require_login($me);
        $data = body();

        $requestedUserId = (int) ($data['user_id'] ?? 0);
        $targetUserId = $auth['role'] === 'admin' && $requestedUserId > 0 ? $requestedUserId : (int) $auth['id'];
        $projectId = (int) ($data['project_id'] ?? 0);
        $workDate = trim((string) ($data['work_date'] ?? ''));
        $startTime = trim((string) ($data['start_time'] ?? ''));
        $endTime = trim((string) ($data['end_time'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($targetUserId <= 0 || $projectId <= 0 || $workDate === '' || $startTime === '' || $endTime === '') {
            send(['error' => 'تمام فیلدهای اجباری را تکمیل کنید.'], 422);
        }

        $duration = minutes_between($startTime, $endTime);
        if ($duration <= 0) {
            send(['error' => 'زمان پایان باید بعد از زمان شروع باشد.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO time_entries (user_id, project_id, work_date, start_time, end_time, description, duration_minutes) VALUES (:user_id, :project_id, :work_date, :start_time, :end_time, :description, :duration)');
        $stmt->execute([
            ':user_id' => $targetUserId,
            ':project_id' => $projectId,
            ':work_date' => $workDate,
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':description' => $description,
            ':duration' => $duration,
        ]);

        send(['message' => 'ثبت ساعت با موفقیت انجام شد.']);
    }

    if ($action === 'entries-recent' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $auth = require_login($me);
        $userId = (int) ($auth['id']);

        $stmt = $pdo->prepare("SELECT te.id, te.work_date, te.start_time, te.end_time, te.duration_minutes, te.description, p.name AS project_name
            FROM time_entries te
            INNER JOIN projects p ON p.id = te.project_id
            WHERE te.user_id = :user_id
            ORDER BY te.work_date DESC, te.id DESC
            LIMIT 25");
        $stmt->execute([':user_id' => $userId]);
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

        $detailSql = "SELECT te.id, te.work_date, te.start_time, te.end_time, te.description, te.duration_minutes,
                   u.full_name AS user_name, p.name AS project_name, p.color AS project_color
            FROM time_entries te
            INNER JOIN users u ON u.id = te.user_id
            INNER JOIN projects p ON p.id = te.project_id
            $sqlWhere
            ORDER BY te.work_date DESC, te.start_time DESC";

        $detailStmt = $pdo->prepare($detailSql);
        $detailStmt->execute($params);
        $details = $detailStmt->fetchAll();

        $dailySql = "SELECT te.work_date, SUM(te.duration_minutes) AS total_minutes
            FROM time_entries te
            $sqlWhere
            GROUP BY te.work_date
            ORDER BY te.work_date ASC";
        $dailyStmt = $pdo->prepare($dailySql);
        $dailyStmt->execute($params);
        $daily = $dailyStmt->fetchAll();

        $totalMinutes = array_sum(array_map(static fn(array $row): int => (int) $row['duration_minutes'], $details));

        send([
            'details' => $details,
            'daily_chart' => $daily,
            'summary' => [
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 2),
                'entries_count' => count($details),
            ],
        ]);
    }

    send(['error' => 'Endpoint نامعتبر است.'], 404);
} catch (Throwable $e) {
    send(['error' => 'خطای سرور', 'message' => $e->getMessage()], 500);
}

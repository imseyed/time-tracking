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

$action = $_GET['action'] ?? '';

try {
    $pdo = db();

    if ($action === 'users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();
        send(['data' => $rows]);
    }

    if ($action === 'projects' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = $pdo->query('SELECT id, name, color FROM projects ORDER BY name')->fetchAll();
        send(['data' => $rows]);
    }

    if ($action === 'entry-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_input();
        $userId = (int) ($data['user_id'] ?? 0);
        $projectId = (int) ($data['project_id'] ?? 0);
        $workDate = trim((string) ($data['work_date'] ?? ''));
        $startTime = trim((string) ($data['start_time'] ?? ''));
        $endTime = trim((string) ($data['end_time'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if (!$userId || !$projectId || !$workDate || !$startTime || !$endTime) {
            send(['error' => 'تمام فیلدهای اجباری را تکمیل کنید.'], 422);
        }

        $duration = minutes_between($startTime, $endTime);
        if ($duration <= 0) {
            send(['error' => 'زمان پایان باید بعد از زمان شروع باشد.'], 422);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO time_entries (user_id, project_id, work_date, start_time, end_time, description, duration_minutes)
             VALUES (:user_id, :project_id, :work_date, :start_time, :end_time, :description, :duration)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':project_id' => $projectId,
            ':work_date' => $workDate,
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':description' => $description,
            ':duration' => $duration,
        ]);

        send(['message' => 'ثبت ساعت با موفقیت انجام شد.']);
    }

    if ($action === 'report' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $userId = (int) ($_GET['user_id'] ?? 0);
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $fromDate = trim((string) ($_GET['from_date'] ?? ''));
        $toDate = trim((string) ($_GET['to_date'] ?? ''));

        $where = [];
        $params = [];

        if ($userId > 0) {
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

        $detailSql = "
            SELECT te.id, te.work_date, te.start_time, te.end_time, te.description, te.duration_minutes,
                   u.full_name AS user_name,
                   p.name AS project_name,
                   p.color AS project_color
            FROM time_entries te
            INNER JOIN users u ON u.id = te.user_id
            INNER JOIN projects p ON p.id = te.project_id
            $sqlWhere
            ORDER BY te.work_date DESC, te.start_time DESC
        ";

        $detailStmt = $pdo->prepare($detailSql);
        $detailStmt->execute($params);
        $details = $detailStmt->fetchAll();

        $chartSql = "
            SELECT p.name AS project_name,
                   p.color AS project_color,
                   SUM(te.duration_minutes) AS total_minutes
            FROM time_entries te
            INNER JOIN projects p ON p.id = te.project_id
            $sqlWhere
            GROUP BY te.project_id, p.name, p.color
            ORDER BY total_minutes DESC
        ";

        $chartStmt = $pdo->prepare($chartSql);
        $chartStmt->execute($params);
        $chart = $chartStmt->fetchAll();

        $totalMinutes = array_sum(array_map(static fn(array $row): int => (int) $row['duration_minutes'], $details));

        send([
            'details' => $details,
            'chart' => $chart,
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

<?php
// api.php - كامل مع كل الـ actions
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    exit;
}

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function notifySSEUpdate() {
    @file_put_contents('last_update.txt', time());
}

try {
    // 1. جلب قائمة المستخدمين
    if ($action === 'list' && $method === 'GET') {
        $stmt = $pdo->query("SELECT id, name, email, created_at FROM users ORDER BY id DESC");
        $users = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $users]);
        exit;
    }

    // 2. إنشاء مستخدم جديد
    if ($action === 'create' && $method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data');
        }
        
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($name) || empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // تشفير الباسورد إذا موجود
        $hashedPassword = null;
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql = "INSERT INTO users (name, email, password) VALUES (:name, :email, :password)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $hashedPassword
        ]);

        notifySSEUpdate();
        echo json_encode(['success' => true, 'message' => 'User created successfully', 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // 3. تحديث مستخدم موجود
    if ($action === 'update' && $method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data');
        }
        
        $id = intval($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($id <= 0 || empty($name) || empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // إذا كان في باسورد جديد، غيريه. إذا لا، اعدل البيانات بدون الباسورد
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET name = :name, email = :email, password = :password WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':id' => $id
            ]);
        } else {
            $sql = "UPDATE users SET name = :name, email = :email WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':id' => $id
            ]);
        }

        notifySSEUpdate();
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        exit;
    }

    // 4. حذف مستخدم
    if ($action === 'delete' && $method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data');
        }
        
        $id = intval($data['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);

        notifySSEUpdate();
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        exit;
    }

    // إذا لم يتم العثور على الـ action
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Action not found: ' . $action]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
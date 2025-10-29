<?php
// sse.php - الإصدار النهائي
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('X-Accel-Buffering: no');

// تنظيف أي buffering
while (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

// تجاهل وقت التنفيذ المحدد
set_time_limit(0);

require_once 'db.php';

// إرسال بيانات مع retry
function sendEvent($data, $eventType = null) {
    if ($eventType) {
        echo "event: $eventType\n";
    }
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// إرسال ping أولي
sendEvent(['type' => 'connected', 'message' => 'SSE connection established'], 'connect');

// دالة جلب المستخدمين
function getUsersUpdate($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, email, created_at FROM users ORDER BY id DESC");
        $users = $stmt->fetchAll();
        
        return [
            'type' => 'users_update',
            'data' => $users,
            'timestamp' => time(),
            'count' => count($users)
        ];
    } catch (Exception $e) {
        return [
            'type' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => time()
        ];
    }
}

// الإرسال الأولي للمستخدمين
$initialData = getUsersUpdate($pdo);
sendEvent($initialData, 'users');

// متابعة التحديثات
$lastUpdate = file_exists('last_update.txt') ? file_get_contents('last_update.txt') : 0;
$counter = 0;

while (true) {
    // التوقف إذا انقطع الاتصال
    if (connection_aborted()) {
        break;
    }
    
    // التحقق من التحديثات كل 2 ثانية
    $currentUpdate = file_exists('last_update.txt') ? file_get_contents('last_update.txt') : 0;
    
    if ($currentUpdate != $lastUpdate || $counter % 5 == 0) {
        // هناك تحديث جديد أو مرور 10 ثواني
        $usersData = getUsersUpdate($pdo);
        sendEvent($usersData, 'users');
        $lastUpdate = $currentUpdate;
    }
    
    // إرسال ping للحفاظ على الاتصال
    if ($counter % 3 == 0) {
        sendEvent(['type' => 'ping', 'timestamp' => time()], 'ping');
    }
    
    $counter++;
    sleep(2); // انتظار 2 ثانية بين كل دورة
}

// إرسال رسالة إغلاق إذا انتهت الحلقة
sendEvent(['type' => 'closed', 'message' => 'SSE connection ended'], 'close');
?>
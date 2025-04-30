<?php
session_start();
include 'db.php'; // Include your database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must log in first.']);
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $recipientId = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : null;
    $filePath = null;

    // Handle file upload if a file is provided
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];

        // Move the uploaded file
        $uploadDir = 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filePath = $uploadDir . time() . '_' . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to upload file.']);
            exit;
        }
    }

    // Ensure at least one of message or file is provided
    if (empty($message) && !$filePath) {
        echo json_encode(['success' => false, 'error' => 'Message or file is required.']);
        exit;
    }

    if ($recipientId) {
        // Insert into private_messages table
        $stmt = $conn->prepare("INSERT INTO private_messages (from_user_id, to_user_id, message, file_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $userId, $recipientId, $message, $filePath);
    } else {
        // Insert into messages table
        $stmt = $conn->prepare("INSERT INTO messages (user_id, message, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $userId, $message, $filePath);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save message.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $recipientId = isset($_GET['recipient_id']) ? intval($_GET['recipient_id']) : null;

    if ($recipientId) {
        // Fetch private messages
        $query = "SELECT private_messages.*, users.username AS username FROM private_messages
                  JOIN users ON private_messages.from_user_id = users.id
                  WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)
                  ORDER BY created_at ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iiii', $userId, $recipientId, $recipientId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];

        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        echo json_encode(['data' => $messages, 'loggedInUserId' => $userId]);
        exit;
    } else {
        // Fetch all common area messages
        $query = "SELECT messages.*, users.username, messages.created_at AS time FROM messages 
                  JOIN users ON messages.user_id = users.id 
                  ORDER BY created_at ASC";
        $result = $conn->query($query);
        $messages = [];

        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        echo json_encode(['data' => $messages, 'loggedInUserId' => $userId]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $messageId = isset($data['delete_id']) ? intval($data['delete_id']) : null;

    if (!$messageId) {
        echo json_encode(['success' => false, 'error' => 'Message ID is required.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $messageId, $userId);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'You are not authorized to delete this message.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete message.']);
    }
    $stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $modifyId = isset($data['modify_id']) ? intval($data['modify_id']) : null;
    $newMessage = isset($data['message']) ? trim($data['message']) : '';

    if ($modifyId && $newMessage) {
        $stmt = $conn->prepare("UPDATE messages SET message = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('sii', $newMessage, $modifyId, $userId);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'You are not authorized to modify this message.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to modify message.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Message ID and new message are required.']);
    }
    exit;
}
?>

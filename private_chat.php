<?php
session_start();
include 'db.php'; // Include your database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

        // Generate a unique file name to prevent overwrites and potential security issues
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = uniqid('file_', true) . '.' . $fileExtension;

        // Move the uploaded file
        $uploadDir = 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filePath = $uploadDir . $newFileName;
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
        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO private_messages (from_user_id, to_user_id, message, file_path, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Failed to prepare statement.']);
            exit;
        }
        $stmt->bind_param('iiss', $userId, $recipientId, $message, $filePath);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save message.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Recipient is required for private messages.']);
        exit;
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $recipientId = isset($_GET['recipient_id']) ? intval($_GET['recipient_id']) : null;

    if ($recipientId) {
        // Fetch private messages
        $query = "SELECT private_messages.*, users.username AS sender, private_messages.created_at AS time FROM private_messages
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
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $messageId = isset($data['delete_id']) ? intval($data['delete_id']) : null;

    if (!$messageId) {
        echo json_encode(['success' => false, 'error' => 'Message ID is required.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM private_messages WHERE id = ? AND from_user_id = ?");
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
        $stmt = $conn->prepare("UPDATE private_messages SET message = ? WHERE id = ? AND from_user_id = ?");
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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Private Chat</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/crypto-js@4.1.1/crypto-js.min.js"></script>
  <style>
    .message {
      display: flex;
      justify-content: flex-start;
      align-items: center;
      padding: 10px;
      border-bottom: 1px solid #ddd;
    }

    .message p {
      margin: 0;
      padding: 10px;
      background-color: #f1f1f1;
      border-radius: 10px;
      max-width: 70%;
      word-wrap: break-word;
    }

    .message-checkbox {
      margin-right: 10px;
    }

    .message a {
      color: #007bff;
      text-decoration: none;
    }

    .message a:hover {
      text-decoration: underline;
    }

    .popup {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background-color: white;
      border: 1px solid #ccc;
      padding: 20px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      z-index: 1000;
      display: none;
    }

    .popup button {
      margin-top: 10px;
    }
    #chatForm {
  max-width: 600px;
  margin: 10px auto;
  display: flex;
  gap: 10px;
}
  </style>
</head>
<body>
  <div class="container">
    <div class="sidebar">
      <h3>Private Chat</h3>
      <select id="userDropdown" onchange="selectRecipient(this.value)">
        <option value="">Select a user</option>
        <?php
        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE id != ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['username']}</option>";
        }
        $stmt->close();
        ?>
      </select>
    </div>
    <div class="chat-container">
      <h3 id="chatHeader">Chat</h3>
      <div id="chatBox"></div>

      <div id="commonActions" style="display: none; margin-top: 10px;">
        <button id="commonDelete" onclick="handleCommonDelete()">Delete</button>
        <button id="commonModify" onclick="handleCommonModify()">Modify</button>
      </div>

      <form id="chatForm" enctype="multipart/form-data">
        <input type="hidden" id="recipientId" name="recipient_id">
        <div class="input-container">
          <textarea id="message" name="message" placeholder="Type your message..." required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;"></textarea> 
           <button type="submit">Send Message</button>
        </div>
      </form>
      <form id="fileForm" enctype="multipart/form-data">
        <div class="file-upload">
          <input type="file" id="file" name="file" required><button type="submit">Upload File</button>
        </div>
      </form>
       <button onclick="window.location.href='chat.html';">Back to Common Area</button>
       <button onclick="showLogoutPopup()">Logout</button>
    </div>
  </div>

  <div id="popup" class="popup">
    <p id="popupMessage"></p>
    <button onclick="closePopup()">Close</button>
  </div>

  <script>
    const chatBox = document.getElementById('chatBox');
    const chatForm = document.getElementById('chatForm');
    const fileForm = document.getElementById('fileForm');
    const recipientIdInput = document.getElementById('recipientId');
    const userDropdown = document.getElementById('userDropdown');
    const popup = document.getElementById('popup');
    const popupMessage = document.getElementById('popupMessage');

    const encryptionKey = "your-secret-key"; // Replace with a strong key

    function showPopup(message) {
      popupMessage.textContent = message;
      popup.style.display = 'block';
    }

    function closePopup() {
      popup.style.display = 'none';
    }

    function selectRecipient(recipientId) {
      recipientIdInput.value = recipientId;
      const selectedUser = userDropdown.options[userDropdown.selectedIndex].text;
      document.getElementById('chatHeader').textContent = `Chat with ${selectedUser}`;
      fetchMessages();

      // Scroll chat-container into view
      const chatContainer = document.querySelector('.chat-container');
      if (chatContainer) {
        chatContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    async function fetchMessages() {
      const recipientId = recipientIdInput.value;
      console.log('recipientId:', recipientId);
      if (!recipientId) return;
      try {
        const res = await fetch(`private_chat.php?recipient_id=${recipientId}`);
        const data = await res.json();
        console.log('Received data:', data);
        console.log('data.data:', data.data);
        if (data.data && data.data.length > 0) {
          chatBox.innerHTML = data.data.map(m => {
            let decryptedMessage = m.message;
            try {
              const bytes = CryptoJS.AES.decrypt(m.message, encryptionKey);
              decryptedMessage = bytes.toString(CryptoJS.enc.Utf8);
              if (!decryptedMessage) {
                decryptedMessage = "Failed to decrypt";
              }
            } catch (e) {
              console.error("Decryption error:", e);
              decryptedMessage = "Decryption Error";
            }

            return `
              <div class="message" data-id="${m.id}">
                <input type="checkbox" class="message-checkbox" onchange="updateCommonActionsVisibility()">
                <p><b>${m.sender}:</b> ${decryptedMessage || ''}
                ${m.file_path ? `<a href="${m.file_path}" download="${m.file_path.split('/').pop()}">Download ${m.file_path.split('/').pop()}</a>` : ''}
                <span style="font-size: 0.8em; color: gray;">(${new Date(m.created_at).toLocaleString()})</span>
                </p>
              </div>
            `;
          }).join('');
          chatBox.scrollTop = chatBox.scrollHeight;
        } else {
          console.error('Error fetching messages:', data.error);
          chatBox.innerHTML = ''; // Clear previous messages
        }
      } catch (error) {
        console.error('Error fetching messages:', error);
      }
    }

    chatForm.onsubmit = async (e) => {
      e.preventDefault();
      const message = document.getElementById("message").value;
      const encryptedMessage = CryptoJS.AES.encrypt(message, encryptionKey).toString();
      const formData = new FormData(chatForm);
      formData.set('message', encryptedMessage); // Override with encrypted message

      try {
        const res = await fetch('private_chat.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          fetchMessages();
          chatForm.reset();
        } else {
          showPopup(data.error);
        }
      } catch (error) {
        console.error('Error sending message:', error);
      }
    };

    fileForm.onsubmit = async (e) => {
      e.preventDefault();
      const formData = new FormData(fileForm);
      formData.append('recipient_id', document.getElementById('recipientId').value); // Add recipient ID
      try {
        const res = await fetch('private_chat.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          showPopup('File uploaded successfully!');
          fetchMessages();
        } else {
          showPopup(data.error);
        }
        fileForm.reset();
      } catch (error) {
        console.error('Error uploading file:', error);
      }
    };

    function updateCommonActionsVisibility() {
      const checkboxes = document.querySelectorAll('.message-checkbox:checked');
      const commonActions = document.getElementById('commonActions');
      commonActions.style.display = checkboxes.length > 0 ? 'block' : 'none';
    }

    function handleCommonDelete() {
      const checkboxes = document.querySelectorAll('.message-checkbox:checked');
      const idsToDelete = Array.from(checkboxes).map(cb => cb.closest('.message').dataset.id);
      if (idsToDelete.length > 0) {
        idsToDelete.forEach(async id => {
          try {
            const res = await fetch('private_chat.php', {
              method: 'DELETE',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ delete_id: id })
            });
            const data = await res.json();
            if (!data.success) {
              showPopup(`Failed to delete message with ID: ${id}`);
            }
          } catch (error) {
            console.error('Error deleting message:', error);
          }
        });
        fetchMessages();
      }
    }

    function handleCommonModify() {
      const checkbox = document.querySelector('.message-checkbox:checked');
      if (checkbox) {
        const selectedMessage = checkbox.closest('.message');
        const messageText = selectedMessage.querySelector('p').textContent.split(': ')[1];
        const newMessage = prompt('Modify your message:', messageText);
        if (newMessage !== null) {
          const id = selectedMessage.dataset.id;
          fetch('private_chat.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modify_id: id, message: newMessage })
          }).then(res => res.json()).then(data => {
            if (data.success) {
              fetchMessages();
            } else {
              showPopup(data.error);
            }
          }).catch(error => console.error('Error modifying message:', error));
        }
      }
    }

    function showLogoutPopup() {
      if (confirm("Are you sure you want to logout?")) {
        window.location.href = 'logout.php';
      }
    }

    window.onload = () => {
      setInterval(fetchMessages, 3000); // Fetch messages every 3 seconds
    };
  </script>
</body>
</html>
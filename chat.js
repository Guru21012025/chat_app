document.getElementById("chatForm").onsubmit = async e => {
  e.preventDefault();
  const formData = new FormData(e.target);
  try {
    const res = await fetch("chat.php", {
      method: "POST",
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      fetchMessages();
      e.target.reset(); // Reset the text input field
    } else {
      alert(data.error);
    }
  } catch (error) {
    alert("An error occurred while sending the message.");
  }
};

document.getElementById("fileForm").onsubmit = async e => {
  e.preventDefault();
  const formData = new FormData(e.target);
  try {
    const res = await fetch("chat.php", {
      method: "POST",
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      alert("File uploaded successfully!");
      fetchMessages();
      e.target.reset(); // Reset the file input field
    } else {
      alert(data.error);
    }
  } catch (error) {
    alert("An error occurred while uploading the file.");
  }
};

async function fetchUsers() {
  const res = await fetch('users.php');
  const users = await res.json();
  const userList = document.getElementById('userList');
  
  // Add "Group Chat" option
  userList.innerHTML = '<li onclick="selectRecipient(null)" style="cursor: pointer;">Group Chat</li>';
  
  // Add individual users
  users.forEach(user => {
    const userItem = document.createElement('li');
    userItem.textContent = user.username;
    userItem.style.cursor = 'pointer'; // Ensure the cursor changes to indicate it's clickable
    userItem.onclick = () => selectRecipient(user.id); // Set the onclick event
    userList.appendChild(userItem);
  });
}

function selectRecipient(recipientId) {
  document.getElementById('recipientId').value = recipientId || '';
  fetchMessages(); // Fetch messages for the selected recipient (or common area if null)
}

let selectedMessageId = null;

async function fetchMessages() {
  const recipientId = document.getElementById('recipientId').value;
  const res = await fetch(`chat.php?recipient_id=${recipientId}`);
  const messages = await res.json();
  const chatBox = document.getElementById('chatBox');
  const loggedInUserId = messages.loggedInUserId; // Backend will return the logged-in user's ID

  // Store the currently checked message IDs
  const checkedMessageIds = Array.from(document.querySelectorAll('.message-checkbox:checked')).map(cb => cb.closest('.message').dataset.id);

  // Check if the user is already at the bottom of the chat box
  const isAtBottom = Math.abs(chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight) < 1;

  chatBox.innerHTML = messages.data.map(m => {
    const isChecked = checkedMessageIds.includes(m.id.toString());
    let content = `<div class="message" data-id="${m.id}">
      <input type="checkbox" class="message-checkbox" ${isChecked ? 'checked' : ''} onchange="updateCommonActionsVisibility()">
      <p title="Sent by ${m.username}"><b>${m.username}:</b> ${m.message || ''}`;
    if (m.file_path) {
      content += ` <a href="${m.file_path}" target="_blank" download title="Sent by ${m.username}">${m.file_path.split('/').pop()}</a>`;
    }
    content += `</p></div>`;
    return content;
  }).join('');

  // Scroll to the bottom only if the user was already at the bottom
  if (isAtBottom) {
    chatBox.scrollTop = chatBox.scrollHeight;
  }
}

function deleteMessage(messageId) {
  selectedMessageId = messageId;
  document.getElementById('deleteModal').style.display = 'flex';
}

function modifyMessage(messageId, messageText) {
  selectedMessageId = messageId;
  document.getElementById('modifyText').value = messageText;
  document.getElementById('modifyModal').style.display = 'flex';
}

function updateCommonActionsVisibility() {
  const checkboxes = document.querySelectorAll('.message-checkbox:checked');
  const commonActions = document.getElementById('commonActions');
  const commonModify = document.getElementById('commonModify');
  const commonDelete = document.getElementById('commonDelete');

  if (checkboxes.length === 1) {
    const selectedMessage = checkboxes[0].closest('.message');
    const isFile = selectedMessage.querySelector('a') !== null;

    commonActions.style.display = 'block';
    commonModify.style.display = isFile ? 'none' : 'inline-block';
    commonDelete.style.display = 'inline-block';
  } else if (checkboxes.length > 1) {
    commonActions.style.display = 'block';
    commonModify.style.display = 'none';
    commonDelete.style.display = 'inline-block';
  } else {
    commonActions.style.display = 'none';
  }
}

document.addEventListener('change', (e) => {
  if (e.target.classList.contains('message-checkbox')) {
    updateCommonActionsVisibility();
  }
});

function handleCommonDelete() {
  const checkboxes = document.querySelectorAll('.message-checkbox:checked');
  const idsToDelete = Array.from(checkboxes).map(cb => cb.closest('.message').dataset.id);

  if (idsToDelete.length > 0) {
    selectedMessageId = idsToDelete; // Store the IDs to delete
    document.getElementById('deleteModal').style.display = 'flex';
  } else {
    alert("Please select at least one message to delete.");
  }
}

document.getElementById('confirmDelete').onclick = async () => {
  try {
    if (Array.isArray(selectedMessageId)) {
      for (const id of selectedMessageId) {
        const res = await fetch(`chat.php`, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ delete_id: id })
        });
        const data = await res.json();
        if (!data.success) {
          alert(`Failed to delete message with ID: ${id}`);
        }
      }
    } else {
      const res = await fetch(`chat.php`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ delete_id: selectedMessageId })
      });
      const data = await res.json();
      if (!data.success) {
        alert(`Failed to delete message with ID: ${selectedMessageId}`);
      }
    }
    document.getElementById('deleteModal').style.display = 'none';
    fetchMessages(); // Refresh messages after deletion
  } catch (error) {
    alert("An error occurred while deleting the message.");
  }
};

function handleCommonModify() {
  const checkbox = document.querySelector('.message-checkbox:checked');
  if (checkbox) {
    const selectedMessage = checkbox.closest('.message');
    const messageText = selectedMessage.querySelector('p').textContent.split(': ')[1];
    selectedMessageId = selectedMessage.dataset.id;

    document.getElementById('modifyText').value = messageText;
    document.getElementById('modifyModal').style.display = 'flex';
  } else {
    alert("Please select exactly one message to modify.");
  }
}

document.getElementById('confirmModify').onclick = async () => {
  const newText = document.getElementById('modifyText').value.trim();
  if (!newText) {
    alert("Message cannot be empty.");
    return;
  }

  try {
    const res = await fetch("chat.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ modify_id: selectedMessageId, message: newText })
    });
    const data = await res.json();
    if (data.success) {
      document.getElementById('modifyModal').style.display = 'none';
      fetchMessages(); // Refresh messages to reflect the update
    } else {
      alert(data.error);
    }
  } catch (error) {
    alert("An error occurred while modifying the message.");
  }
}

document.getElementById('cancelDelete').onclick = () => {
  document.getElementById('deleteModal').style.display = 'none';
};

document.getElementById('cancelModify').onclick = () => {
  document.getElementById('modifyModal').style.display = 'none';
};

function logout() {
  window.location.href = "logout.php";
}

window.onload = () => {
  fetchUsers();
  fetchMessages();
  setInterval(fetchMessages, 2000);
};

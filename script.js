async function fetchPrivateMessages(recipientId) {
    const res = await fetch(`private_chat.php?recipient_id=${recipientId}`);
    const messages = await res.json();
    const chatBox = document.getElementById('chatBox');
    chatBox.innerHTML = messages.data.map(m => {
        return `<p><b>${m.sender}:</b> ${m.message}</p>`;
    }).join('');
}
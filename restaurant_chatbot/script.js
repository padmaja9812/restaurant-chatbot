document.addEventListener("DOMContentLoaded", function () {
    const userInput = document.querySelector("#userInput");
    const sendButton = document.querySelector("#send");
    const chatBox = document.querySelector("#chat-box");
    const logoutButton = document.querySelector("#logout");

    sendButton.addEventListener("click", sendMessageHandler);
    userInput.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
            sendMessageHandler();
        }
    });


    logoutButton.addEventListener("click", function () {
        window.location.href = 'logout1.php'; // Redirect to the PHP logout script
    });

    async function sendMessageHandler() {
        const userMessage = userInput.value.trim();
        if (userMessage !== "") {
            appendMessage("user", userMessage); // Display user message in chatbox
            userInput.value = ""; // Clear input field

            // Send user message to server
            try {
                const response = await sendMessage(userMessage);
                appendMessage("bot", response); // Display bot response in chatbox
            } catch (error) {
                console.error("Error sending message:", error);
                appendMessage("bot", "Error sending message. Please try again."); // Display error message in chatbox
            }
        }
    }

    function appendMessage(sender, message) {
        const messageContainer = document.createElement("div");
        const timestamp = document.createElement("div");
        const now = new Date();
        const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        messageContainer.classList.add(`${sender}-message`);
        if (sender === "bot") {
            messageContainer.innerHTML = message.replace(/\n/g, "<br>"); // Use innerHTML for bot messages with line breaks
        } else {
            messageContainer.textContent = message; // Use textContent for user messages
        }

        timestamp.classList.add("timestamp");
        timestamp.textContent = `${now.toLocaleDateString()} ${timeString}`;

        const wrapper = document.createElement("div");
        wrapper.classList.add("message-container");
        wrapper.appendChild(timestamp);
        wrapper.appendChild(messageContainer);

        chatBox.appendChild(wrapper);
        chatBox.scrollTop = chatBox.scrollHeight; // Scroll to bottom of chatbox
    }

    async function sendMessage(message) {
        const response = await fetch("query.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `messageValue=${encodeURIComponent(message)}`,
        });
        if (!response.ok) {
            throw new Error("Failed to send message");
        }
        return await response.text();
    }
});

<?php
session_start();
require_once 'core/dbConfig.php';

// Ensure the user is logged in as an applicant
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'applicant') {
    header("Location: login.php");
    exit();
}

// Fetch messages where the applicant is either the sender or recipient
$stmt = $pdo->prepare("SELECT m.id, m.from_user_id, m.to_user_id, m.message, m.created_at, u.username AS sender_username, u2.username AS recipient_username 
                        FROM messages m
                        JOIN users u ON m.from_user_id = u.id
                        LEFT JOIN users u2 ON m.to_user_id = u2.id
                        WHERE m.from_user_id = ? OR m.to_user_id = ?
                        ORDER BY m.created_at DESC");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$messages = $stmt->fetchAll();

// Handle the reply form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $messageContent = $_POST['message'];
    $hrUserId = $_POST['hr_user_id'];  // HR who sent the original message

    // Insert the reply into the messages table
    $query = "INSERT INTO messages (from_user_id, to_user_id, message, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $hrUserId, $messageContent]);

    // Redirect back to the messages page after sending the reply
    header("Location: applicant_messages.php");
    exit();
}

// Handle the new message form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $messageContent = $_POST['message'];
    $hrUserId = $_POST['hr_user_id'];  // HR user to send the message to

    // Insert the new message into the messages table
    $query = "INSERT INTO messages (from_user_id, to_user_id, message, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $hrUserId, $messageContent]);

    // Redirect back to the messages page after sending the new message
    header("Location: applicant_messages.php");
    exit();
}

// Fetch all HR users to populate the dropdown for sending messages
function getHRUsers() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE role = 'hr'");
    $stmt->execute();
    return $stmt->fetchAll();
}

$hrUsers = getHRUsers();  // Get list of HR users
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Messages</title>
</head>
<body>
    <h1>Messages</h1>
    <a href="applicant_dashboard.php">Back to Dashboard</a> | <a href="core/handleForms.php?logoutAUser=1">Logout</a>

    <h2>Send Message to HR</h2>
    <!-- Form for sending a new message -->
    <form method="POST" action="applicant_messages.php">
        <label for="hr_user_id">Select HR User:</label>
        <select name="hr_user_id" id="hr_user_id" required>
            <?php
            // Populate dropdown with HR users
            foreach ($hrUsers as $hr) {
                echo "<option value='" . $hr['id'] . "'>" . htmlspecialchars($hr['username']) . "</option>";
            }
            ?>
        </select>
        <br><br>

        <label for="message">Your Message:</label>
        <textarea name="message" id="message" rows="4" cols="50" required></textarea>
        <br><br>
        <button type="submit" name="send_message">Send Message</button>
    </form>

    <h2>Message History</h2>
    <?php if (!empty($messages)): ?>
        <ul>
            <?php foreach ($messages as $message): ?>
                <li>
                    <?php
                    // Determine whether the current message is sent by the applicant or received by the applicant
                    if ($message['from_user_id'] == $_SESSION['user_id']) {
                        // Sent message
                        echo "<strong>To: " . htmlspecialchars($message['recipient_username']) . "</strong>";
                    } else {
                        // Received message
                        echo "<strong>From: " . htmlspecialchars($message['sender_username']) . "</strong>";
                    }
                    ?>
                    <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                    <p><small>Sent/Received on: <?php echo htmlspecialchars($message['created_at']); ?></small></p>

                    <!-- Form to reply to the message -->
                    <?php if ($message['from_user_id'] != $_SESSION['user_id']): // Show reply form for received messages ?>
                        <form method="POST" action="applicant_messages.php">
                            <textarea name="message" rows="4" cols="50" required placeholder="Your reply..."></textarea><br>
                            <input type="hidden" name="hr_user_id" value="<?php echo $message['from_user_id']; ?>">
                            <button type="submit" name="reply_message">Send Reply</button>
                        </form>
                    <?php endif; ?>
                </li>
                <hr>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No messages available.</p>
    <?php endif; ?>
</body>
</html>

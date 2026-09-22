<?php
session_start();
include "config/db.php";

$message = "";
$messageType = "";

if (!isset($_SESSION["reset_email"]) || !isset($_SESSION["reset_verified"]) || $_SESSION["reset_verified"] !== true) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION["reset_email"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newPassword = $_POST["new_password"];
    $confirmPassword = $_POST["confirm_password"];

    if (empty($newPassword) || empty($confirmPassword)) {
        $message = "Please fill in both password fields.";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } elseif (strlen($newPassword) < 6) {
        $message = "Password must be at least 6 characters.";
        $messageType = "error";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            UPDATE users
            SET password = ?,
                reset_code = NULL,
                reset_code_expires = NULL
            WHERE email = ?
        ");

        $stmt->bind_param("ss", $hashedPassword, $email);

        if ($stmt->execute()) {
            unset($_SESSION["reset_email"]);
            unset($_SESSION["reset_verified"]);

            $_SESSION["password_reset_success"] = "Password reset successfully. You can now login using your new password.";

            header("Location: login.php");
            exit();
        } else {
            $message = "Failed to reset password. Please try again.";
            $messageType = "error";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | DOrSU Bulletin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root {
            --dorsu-blue-dark: #002b7f;
            --dorsu-blue: #003fae;
            --dorsu-blue-light: #0b63d8;
            --dorsu-gold: #f6c400;
            --dorsu-gold-dark: #d9a400;
            --dorsu-text: #1f2937;
            --dorsu-muted: #5b6472;
            --dorsu-shadow: rgba(0, 43, 127, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(246, 196, 0, 0.42), transparent 32%),
                radial-gradient(circle at bottom right, rgba(0, 63, 174, 0.35), transparent 35%),
                linear-gradient(135deg, var(--dorsu-blue-dark), var(--dorsu-blue), #eef4ff);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 25px;
            color: var(--dorsu-text);
        }

        .container {
            width: 100%;
            max-width: 460px;
            background: rgba(255, 255, 255, 0.97);
            padding: 38px;
            border-radius: 28px;
            box-shadow: 0 25px 65px rgba(0, 0, 0, 0.28);
            border: 1px solid rgba(246, 196, 0, 0.55);
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: "";
            position: absolute;
            width: 210px;
            height: 210px;
            border-radius: 50%;
            background: rgba(246, 196, 0, 0.14);
            top: -100px;
            right: -90px;
        }

        .content {
            position: relative;
            z-index: 2;
        }

        .system-badge {
            width: fit-content;
            margin: 0 auto 18px;
            padding: 9px 15px;
            background: #fff7cc;
            color: var(--dorsu-blue-dark);
            border: 1px solid rgba(246, 196, 0, 0.75);
            border-radius: 30px;
            font-size: 13px;
            font-weight: bold;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .header h1 {
            color: var(--dorsu-blue-dark);
            margin-bottom: 8px;
            font-size: 32px;
        }

        .header h1 span {
            color: var(--dorsu-gold-dark);
        }

        .header p {
            color: var(--dorsu-muted);
            line-height: 1.5;
            font-size: 15px;
        }

        .message {
            padding: 14px;
            border-radius: 14px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
            line-height: 1.5;
        }

        .error {
            background: #fff0f0;
            color: #9b0000;
            border: 1px solid #ffc9c9;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: var(--dorsu-blue-dark);
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #cfd8ea;
            border-radius: 14px;
            outline: none;
            font-size: 15px;
            color: var(--dorsu-text);
        }

        input:focus {
            border-color: var(--dorsu-blue);
            box-shadow: 0 0 0 3px rgba(0, 63, 174, 0.13);
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--dorsu-blue-dark), var(--dorsu-blue));
            color: white;
            border: none;
            border-radius: 14px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: linear-gradient(135deg, var(--dorsu-blue), var(--dorsu-blue-light));
            transform: translateY(-3px);
            box-shadow: 0 12px 28px var(--dorsu-shadow);
        }

        .links {
            text-align: center;
            margin-top: 20px;
            line-height: 1.9;
            color: var(--dorsu-muted);
            font-size: 15px;
        }

        .links a {
            color: var(--dorsu-blue-dark);
            text-decoration: none;
            font-weight: bold;
        }

        .links a:hover {
            color: var(--dorsu-gold-dark);
            text-decoration: underline;
        }

        .note {
            margin-top: 20px;
            padding: 14px;
            background: #f4f7ff;
            border: 1px solid rgba(0, 63, 174, 0.18);
            border-left: 6px solid var(--dorsu-gold);
            border-radius: 14px;
            color: var(--dorsu-muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .note strong {
            color: var(--dorsu-blue-dark);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="content">
        <div class="system-badge">Davao Oriental State University</div>

        <div class="header">
            <h1>Reset <span>Password</span></h1>
            <p>Create a new password for your account.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="Enter new password" required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password" required>
            </div>

            <button type="submit">Update Password</button>
        </form>

        <div class="links">
            <a href="login.php">Back to Login</a>
        </div>

        <div class="note">
            <strong>Tip:</strong> Use a password that you can remember but others cannot easily guess.
        </div>
    </div>
</div>

</body>
</html>
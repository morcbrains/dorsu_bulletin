<?php
session_start();
include "config/db.php";

$message = "";
$messageType = "";

if (isset($_SESSION["password_reset_success"])) {
    $message = $_SESSION["password_reset_success"];
    $messageType = "success";
    unset($_SESSION["password_reset_success"]);
}

if (isset($_SESSION["user_id"])) {
    if ($_SESSION["role"] === "admin") {
        header("Location: dashboards/admin_dashboard.php");
        exit();
    } elseif ($_SESSION["role"] === "informant") {
        header("Location: dashboards/informant_dashboard.php");
        exit();
    } elseif ($_SESSION["role"] === "faculty") {
        header("Location: dashboards/faculty_dashboard.php");
        exit();
    } elseif ($_SESSION["role"] === "student") {
        header("Location: dashboards/student_dashboard.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $message = "Please enter your email and password.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password"])) {
                if ($user["status"] === "pending") {
                    $message = "Your account is still pending. Please wait for admin approval.";
                    $messageType = "error";
                } elseif ($user["status"] === "rejected") {
                    $message = "Your account has been rejected. Please contact the administrator.";
                    $messageType = "error";
                } elseif ($user["status"] === "approved") {
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["full_name"] = $user["full_name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];

                    if ($user["role"] === "admin") {
                        header("Location: dashboards/admin_dashboard.php");
                        exit();
                    } elseif ($user["role"] === "informant") {
                        header("Location: dashboards/informant_dashboard.php");
                        exit();
                    } elseif ($user["role"] === "faculty") {
                        header("Location: dashboards/faculty_dashboard.php");
                        exit();
                    } elseif ($user["role"] === "student") {
                        header("Location: dashboards/student_dashboard.php");
                        exit();
                    }
                }
            } else {
                $message = "Invalid email or password.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid email or password.";
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
    <title>Login | DOrSU Bulletin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root {
            --dorsu-blue-dark: #002b7f;
            --dorsu-blue: #003fae;
            --dorsu-blue-light: #0b63d8;
            --dorsu-gold: #f6c400;
            --dorsu-gold-dark: #d9a400;
            --dorsu-white: #ffffff;
            --dorsu-bg: #f4f7ff;
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
            animation: pageFadeIn 0.55s ease forwards;
        }

        @keyframes pageFadeIn {
            from {
                opacity: 0;
                transform: scale(0.97);
                filter: blur(5px);
            }

            to {
                opacity: 1;
                transform: scale(1);
                filter: blur(0);
            }
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
            top: -95px;
            right: -80px;
        }

        .container::after {
            content: "";
            position: absolute;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(0, 63, 174, 0.08);
            bottom: -70px;
            left: -65px;
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
            box-shadow: 0 8px 18px rgba(246, 196, 0, 0.18);
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .header h1 {
            color: var(--dorsu-blue-dark);
            margin-bottom: 8px;
            font-size: 34px;
            letter-spacing: -0.5px;
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

        .success {
            background: #ecfff1;
            color: #187333;
            border: 1px solid #a8e8b4;
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
            background: #ffffff;
            color: var(--dorsu-text);
            transition: 0.25s;
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
            margin-top: 4px;
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

        .login-note {
            margin-top: 22px;
            padding: 14px;
            background: #f4f7ff;
            border: 1px solid rgba(0, 63, 174, 0.18);
            border-left: 6px solid var(--dorsu-gold);
            border-radius: 14px;
            color: var(--dorsu-muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .login-note strong {
            color: var(--dorsu-blue-dark);
        }

        @media (max-width: 480px) {
            body {
                padding: 15px;
            }

            .container {
                padding: 30px 22px;
                border-radius: 24px;
            }

            .header h1 {
                font-size: 30px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="content">
        <div class="system-badge">Davao Oriental State University</div>

        <div class="header">
            <h1>DOrSU <span>Connect</span></h1>
            <p>Login to the University Announcement System</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit">Login</button>
        </form>

        <div class="links">
            <a href="forgot_password.php">Forgot Password?</a><br>
            Don't have an account? <a href="signup.php">Create account</a><br>
            <a href="index.php">Back to Home</a>
        </div>

        <div class="login-note">
            <strong>Reminder:</strong> New student accounts must be approved by the administrator before login access is allowed.
        </div>
    </div>
</div>

</body>
</html>
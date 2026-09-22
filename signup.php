<?php
session_start();
include "config/db.php";

$message = "";
$messageType = "";

$facultyPrograms = [
    "Faculty of Computing, Engineering and Technology (FaCET)" => [
        "BS Information Technology",
        "BS Civil Engineering",
        "Industrial Technology Management",
        "BS Mathematics with Research Statistics"
    ],
    "Faculty of Teacher Education (FTED)" => [
        "Bachelor of Elementary Education",
        "Bachelor of Secondary Education",
        "Bachelor of Physical Education",
        "Bachelor of Early Childhood Education",
        "Bachelor of Special Needs Education"
    ],
    "Faculty of Business Management (FBM)" => [
        "BS Business Administration",
        "BS Hospitality Management"
    ],
    "Faculty of Agriculture and Life Sciences (FALS)" => [
        "BS Agribusiness Management",
        "Bachelor of Agricultural Technology",
        "BS Biology",
        "BS Environmental Science"
    ],
    "Faculty of Nursing and Allied Health Sciences (FNAHS)" => [
        "BS Nursing"
    ],
    "Faculty of Humanities, Social Sciences, and Communication (FHuSoCom)" => [
        "BA Political Science",
        "BS Psychology",
        "Bachelor of Development Communication"
    ],
    "Faculty of Criminal Justice Education (FCJE)" => [
        "BS Criminology"
    ]
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $role = "student";
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    $student_id = !empty($_POST["student_id"]) ? trim($_POST["student_id"]) : null;
    $faculty_id = null;
    $office_name = null;
    $department = !empty($_POST["faculty_name"]) ? trim($_POST["faculty_name"]) : null;
    $program = !empty($_POST["program"]) ? trim($_POST["program"]) : null;
    $year_level = !empty($_POST["year_level"]) ? trim($_POST["year_level"]) : null;
    $section = null;

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
        $messageType = "error";
    } elseif (empty($student_id) || empty($department) || empty($program) || empty($year_level)) {
        $message = "Please complete all student information.";
        $messageType = "error";
    } elseif (!array_key_exists($department, $facultyPrograms)) {
        $message = "Invalid faculty selected.";
        $messageType = "error";
    } elseif (!in_array($program, $facultyPrograms[$department])) {
        $message = "Invalid program selected for the chosen faculty.";
        $messageType = "error";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $message = "Email is already registered.";
            $messageType = "error";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $status = "pending";

            $stmt = $conn->prepare("
                INSERT INTO users (
                    full_name,
                    email,
                    password,
                    role,
                    student_id,
                    faculty_id,
                    office_name,
                    program,
                    year_level,
                    section,
                    department,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssssssssssss",
                $full_name,
                $email,
                $hashed_password,
                $role,
                $student_id,
                $faculty_id,
                $office_name,
                $program,
                $year_level,
                $section,
                $department,
                $status
            );

            if ($stmt->execute()) {
                $message = "Student account created successfully. Please wait for admin approval before logging in.";
                $messageType = "success";
            } else {
                $message = "Something went wrong. Please try again.";
                $messageType = "error";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Sign Up | DOrSU Bulletin</title>
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
            max-width: 720px;
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
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(246, 196, 0, 0.14);
            top: -110px;
            right: -95px;
        }

        .container::after {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(0, 63, 174, 0.08);
            bottom: -80px;
            left: -75px;
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .full {
            grid-column: 1 / 3;
        }

        label {
            margin-bottom: 7px;
            color: var(--dorsu-blue-dark);
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select {
            padding: 13px;
            border: 1px solid #cfd8ea;
            border-radius: 14px;
            outline: none;
            font-size: 15px;
            background: #ffffff;
            color: var(--dorsu-text);
            transition: 0.25s;
            width: 100%;
        }

        input:focus,
        select:focus {
            border-color: var(--dorsu-blue);
            box-shadow: 0 0 0 3px rgba(0, 63, 174, 0.13);
        }

        button {
            width: 100%;
            margin-top: 22px;
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

        .signup-note {
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

        .signup-note strong {
            color: var(--dorsu-blue-dark);
        }

        .page-fade-out {
            animation: pageFadeOut 0.45s ease forwards;
        }

        @keyframes pageFadeOut {
            from {
                opacity: 1;
                transform: scale(1);
                filter: blur(0);
            }

            to {
                opacity: 0;
                transform: scale(0.97);
                filter: blur(5px);
            }
        }

        @media (max-width: 650px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: 1 / 2;
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
            <h1>Student <span>Sign Up</span></h1>
            <p>DOrSU Connect: University Announcement System</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter your full name" required>
                </div>

                <div class="form-group full">
                    <label>Active Email</label>
                    <input type="email" name="email" placeholder="Enter your active email" required>
                </div>

                <div class="form-group full">
                    <label>Student ID</label>
                    <input type="text" name="student_id" placeholder="Enter student ID" required>
                </div>

                <div class="form-group full">
                    <label>Faculty</label>
                    <select name="faculty_name" id="facultyName" required onchange="loadPrograms()">
                        <option value="">Select faculty</option>
                        <?php foreach ($facultyPrograms as $facultyName => $programs): ?>
                            <option value="<?php echo htmlspecialchars($facultyName); ?>">
                                <?php echo htmlspecialchars($facultyName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Program</label>
                    <select name="program" id="programName" required>
                        <option value="">Select faculty first</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Year Level</label>
                    <select name="year_level" required>
                        <option value="">Select year level</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create password" required>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm password" required>
                </div>
            </div>

            <button type="submit">Create Student Account</button>
        </form>

        <div class="links">
            Already have an account? <a href="login.php" class="page-link">Login here</a><br>
            <a href="index.php" class="page-link">Back to Home</a>
        </div>

        <div class="signup-note">
            <strong>Reminder:</strong> This public registration is for students only. University Office and Faculty accounts are created by the administrator.
        </div>
    </div>
</div>

<script>
const facultyPrograms = <?php echo json_encode($facultyPrograms); ?>;

function loadPrograms() {
    const facultyName = document.getElementById("facultyName").value;
    const programName = document.getElementById("programName");

    programName.innerHTML = "";

    const defaultOption = document.createElement("option");
    defaultOption.value = "";

    if (facultyName === "") {
        defaultOption.textContent = "Select faculty first";
        programName.appendChild(defaultOption);
        return;
    }

    defaultOption.textContent = "Select program";
    programName.appendChild(defaultOption);

    if (facultyPrograms[facultyName]) {
        facultyPrograms[facultyName].forEach(function(program) {
            const option = document.createElement("option");
            option.value = program;
            option.textContent = program;
            programName.appendChild(option);
        });
    }
}

const pageLinks = document.querySelectorAll(".page-link");

pageLinks.forEach(function(link) {
    link.addEventListener("click", function(event) {
        event.preventDefault();

        const targetPage = this.getAttribute("href");

        document.body.classList.add("page-fade-out");

        setTimeout(function() {
            window.location.href = targetPage;
        }, 430);
    });
});
</script>

</body>
</html>
<?php
session_start();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DOrSU Bulletin</title>
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
            padding: 20px;
            color: var(--dorsu-text);
        }

        .container {
            width: 100%;
            max-width: 1080px;
            min-height: 580px;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 28px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            box-shadow: 0 28px 70px rgba(0, 0, 0, 0.28);
            border: 1px solid rgba(246, 196, 0, 0.55);
        }

        .left {
            padding: 70px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .left::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(246, 196, 0, 0.14);
            top: -100px;
            left: -85px;
        }

        .left::after {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(0, 63, 174, 0.08);
            right: -70px;
            bottom: -60px;
        }

        .left-content {
            position: relative;
            z-index: 2;
        }

        .badge {
            display: inline-block;
            width: fit-content;
            padding: 10px 18px;
            background: #fff7cc;
            color: var(--dorsu-blue-dark);
            border: 1px solid rgba(246, 196, 0, 0.75);
            border-radius: 30px;
            font-weight: bold;
            margin-bottom: 25px;
            box-shadow: 0 8px 18px rgba(246, 196, 0, 0.18);
        }

        h1 {
            font-size: 52px;
            color: var(--dorsu-blue-dark);
            margin-bottom: 15px;
            line-height: 1.08;
            letter-spacing: -1px;
        }

        h1 span {
            color: var(--dorsu-gold-dark);
        }

        h2 {
            font-size: 24px;
            color: #173b75;
            margin-bottom: 25px;
            font-weight: bold;
        }

        p {
            font-size: 17px;
            color: var(--dorsu-muted);
            line-height: 1.75;
            margin-bottom: 35px;
            max-width: 640px;
        }

        .buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 14px;
            font-weight: bold;
            transition: 0.3s;
            display: inline-block;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--dorsu-blue-dark), var(--dorsu-blue));
            color: white;
            border: 2px solid transparent;
        }

        .btn-signup {
            border: 2px solid var(--dorsu-gold);
            color: var(--dorsu-blue-dark);
            background: #fff7cc;
        }

        .btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px var(--dorsu-shadow);
        }

        .right {
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.20), transparent 35%),
                linear-gradient(160deg, var(--dorsu-blue-dark), var(--dorsu-blue), var(--dorsu-gold));
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .right::before {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.11);
            right: -75px;
            top: -65px;
        }

        .right::after {
            content: "";
            position: absolute;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: rgba(246, 196, 0, 0.22);
            left: -50px;
            bottom: -45px;
        }

        .card {
            background: rgba(255, 255, 255, 0.16);
            padding: 25px;
            border-radius: 22px;
            margin-bottom: 20px;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 241, 170, 0.40);
            position: relative;
            z-index: 2;
            transition: 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.22);
        }

        .card h3 {
            margin-bottom: 10px;
            font-size: 20px;
            color: #fff7cc;
        }

        .card p {
            color: #ffffff;
            margin: 0;
            font-size: 15px;
            line-height: 1.55;
        }

        .footer-note {
            margin-top: 22px;
            color: var(--dorsu-blue-dark);
            font-size: 13px;
            font-weight: bold;
        }

        @media (max-width: 850px) {
            .container {
                grid-template-columns: 1fr;
            }

            .left {
                padding: 45px 28px;
            }

            .right {
                padding: 32px 25px;
            }

            h1 {
                font-size: 38px;
            }

            h2 {
                font-size: 21px;
            }

            p {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }

            .container {
                border-radius: 22px;
            }

            .left {
                padding: 38px 22px;
            }

            .right {
                padding: 26px 20px;
            }

            h1 {
                font-size: 34px;
            }

            .buttons {
                flex-direction: column;
            }

            .btn {
                text-align: center;
                width: 100%;
            }
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
    </style>
</head>
<body>

<div class="container">
    <div class="left">
        <div class="left-content">
            <span class="badge">Davao Oriental State University</span>

            <h1>DOrSU <span>Connect</span></h1>
            <h2>A Web-Based University Information and Communication System</h2>

            <p>
                A centralized web-based platform that brings the DOrSU community closer through official announcements, academic reminders, campus events, student activities, and timely university updates for students, faculty members, university office informants, and administrators.
            </p>

            <div class="buttons">
                <a href="login.php" class="btn btn-login page-link">Login</a>
                <a href="signup.php" class="btn btn-signup page-link">Create Account</a>
            </div>

            <div class="footer-note">
                Official updates. Faster communication. One DOrSU community.
            </div>
        </div>
    </div>

    <div class="right">
        <div class="card">
            <h3>Official Announcements</h3>
            <p>View verified university updates from authorized users.</p>
        </div>

        <div class="card">
            <h3>Events and Activities</h3>
            <p>Stay informed about upcoming school events, programs, and activities.</p>
        </div>

        <div class="card">
            <h3>Role-Based Access</h3>
            <p>Separate areas for Admin, University Offices Informant, Faculty, and Students.</p>
        </div>
    </div>
</div>
    <script>
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
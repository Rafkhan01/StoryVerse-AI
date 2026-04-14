<?php
session_start();
require_once 'db_connect.php'; // Your database connection

// Check if the user is logged in. If not, redirect to the signin page.
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

// User data from session
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email = $_SESSION['email'];
$total_score = $_SESSION['total_score'];

// --- Featured Story Logic ---
$featured_story = null;

try {
    // SQL query to find the single active story with the nearest prediction deadline
    $sql = "
        SELECT
            s.*,
            sp.part_number,
            sp.content AS part_content,
            sp.prediction_deadline,
            sp.upload_date
        FROM
            stories s
        JOIN
            story_parts sp ON s.story_id = sp.story_id
        WHERE
            sp.upload_date <= NOW() AND sp.prediction_deadline >= NOW()
        ORDER BY
            sp.prediction_deadline ASC
        LIMIT 1;
    ";
    
    $stmt = $pdo->query($sql);
    $featured_story = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching featured story: " . $e->getMessage());
    $featured_story = null; // Ensure no data is displayed on error
}

// Helper function to calculate time left for the countdown timer
function getTimeLeft($deadline) {
    $now = new DateTime();
    $deadline_dt = new DateTime($deadline);
    $interval = $now->diff($deadline_dt);

    $parts = [];
    if ($interval->d > 0) {
        $parts[] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
    }
    if ($interval->h > 0) {
        $parts[] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
    }
    if ($interval->i > 0) {
        $parts[] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
    }
    if (empty($parts)) {
        return 'Less than 1 minute';
    }
    return implode(', ', $parts) . ' left';
}

// Helper function to check for the story's status
function getStoryStatus($upload_date, $prediction_deadline) {
    $current_time = time();
    $upload_timestamp = strtotime($upload_date);
    $deadline_timestamp = strtotime($prediction_deadline);

    if ($current_time < $upload_timestamp) {
        return 'coming_soon';
    } elseif ($current_time >= $upload_timestamp && $current_time <= $deadline_timestamp) {
        return 'active';
    } else {
        return 'completed';
    }
}

// Get story status
$story_status = $featured_story ? getStoryStatus($featured_story['upload_date'], $featured_story['prediction_deadline']) : 'none';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Read... Predict! Win!</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Define a modern color palette and fonts using CSS variables */
        :root {
            --primary-color: #6a4cff;
            --secondary-color: #4c6fff;
            --text-color: #2c3e50;
            --background-color: #f0f4f8;
            --card-background: #ffffff;
            --accent-color: #ff6b6b;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.05);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.1);
            --shadow-strong: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        /* General Body Styles */
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        /* Navigation Bar */
        nav {
            display: flex;
            width: 100%;
            background: var(--card-background);
            padding: 15px 5%;
            box-shadow: var(--shadow-light);
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navtitle {
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 800;
            margin: 0;
        }

        .navcta {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .navlinks {
            border: none;
            background: transparent;
            padding: 8px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
            font-weight: 600;
            border-radius: 50px; /* Pill shape */
        }

        nav a {
            text-decoration: none;
            color: var(--text-color);
            transition: color 0.3s ease;
        }

        .navlinks:hover {
            background-color: var(--primary-color);
        }

        .navlinks:hover a {
            color: var(--card-background);
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #e0e9ff, #d5e1ff);
            padding: 80px 25px;
            text-align: center;
            color: var(--text-color);
            position: relative;
            overflow: hidden;
            border-bottom-left-radius: 50px;
            border-bottom-right-radius: 50px;
        }

        .hero-section h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(2.5em, 5vw, 4.5em);
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
        }

        .hero-section span {
            font-size: clamp(1em, 2vw, 1.2em);
            line-height: 1.6;
            color: #555;
            display: block;
            margin-bottom: 30px;
        }

        .hero-cta {
            margin-top: 25px;
        }

        .cta {
            padding: 12px 30px;
            background: var(--primary-color);
            border-radius: 50px;
            color: white;
            transition: all 0.4s ease;
            outline: none;
            border: none;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(106, 76, 255, 0.4);
        }

        .cta:hover {
            background: var(--secondary-color);
            color: white;
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 20px rgba(76, 111, 255, 0.6);
        }
        
        .welcome-info {
            background-color: var(--card-background);
            border-radius: 20px;
            max-width: 800px;
            margin: -50px auto 0;
            padding: 40px;
            text-align: center;
            box-shadow: var(--shadow-medium);
            position: relative;
            z-index: 10;
        }
        
        .welcome-info h1 {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .welcome-info span {
            font-size: 1.1em;
            color: #777;
        }

        /* Featured Story Section */
        h2 {
            text-align: center;
            margin: 60px 0 20px;
            color: var(--primary-color);
            font-family: 'Poppins', sans-serif;
            font-size: 2.5em;
            font-weight: 700;
        }
        
        .featured-story {
            margin: 20px auto;
            max-width: 900px;
            padding: 30px;
            border: none;
            border-radius: 25px;
            background-color: var(--card-background);
            box-shadow: var(--shadow-medium);
            transition: transform 0.3s ease;
        }
        .featured-story:hover {
            transform: translateY(-5px);
        }

        .featured-story-title {
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 25px;
        }
        @media (min-width: 768px) {
            .featured-story-title {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .details {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .status {
            background: #d4edda;
            height: 35px;
            border-radius: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 5px 15px;
            color: #155724;
            font-weight: 600;
            box-shadow: inset 0 0 5px rgba(21, 87, 36, 0.2);
        }

        .status-ping {
            background: #28a745;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }

        .count-down {
            font-size: 1.1em;
            color: #777;
            font-weight: 500;
        }
        
        .featured-story p {
            line-height: 1.8;
            color: #555;
            margin: 20px 0;
            font-size: 1.05em;
        }
        
        .part-container {
            display: flex;
            width: 100%;
            height: 12px;
            background: #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            margin: 20px 0;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
        }

        .part-segment {
            flex-grow: 1;
            height: 100%;
            background: #e0e0e0;
        }

        .part-segment.filled {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        }

        .read-now-cta {
            display: block;
            text-align: center;
            margin-top: 30px;
        }

        /* How It Works Section */
    .short-working {
        display: flex;
        flex-wrap: wrap; 
        justify-content: center;
        gap: 30px;
        padding: 0 5%;
        margin-top: 40px;
        margin-bottom: 80px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .step-box {
        background-color: var(--card-background); 
        border: none;
        border-radius: 20px;
        padding: 30px 20px;
        text-align: center;            
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); /* Lighter shadow for a modern feel */
        flex: 1 1 250px; 
        max-width: 280px;
    }

    .step-box:hover {
        transform: translateY(-8px); 
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); /* More pronounced shadow on hover */
    }

    .step-box .step-number {
        width: 65px;
        height: 65px;
        background: linear-gradient(135deg, #a88bff, #6a4cff); /* New gradient */
        color: white;
        border-radius: 50%; 
        display: flex;
        justify-content: center;
        align-items: center;
        font-family: 'Poppins', sans-serif; /* Using a different font for numbers */
        font-size: 32px;
        font-weight: 800;
        margin: 0 auto 25px; 
        box-shadow: 0 5px 15px rgba(106, 76, 255, 0.4);
    }

    .step-box h4 {
        font-size: 1.4em;
        font-weight: 700;
        color: var(--text-color);
        margin-top: 0;
    }

    .step-box p {
        font-size: 1em;
        color: #777;
    }
        hr {
            border: none;
            border-top: 1px solid #eee;
            margin: 25px 0;
        }
    </style>
</head>
<body>

    <nav>
        <div class="navtitle">StoryPulse</div>
        <div class="navcta">
            <button class="navlinks"><a href="index.php">Home</a></button>
            <button class="navlinks"><a href="stories.php">Stories</a></button>
            <button class="navlinks"><a href="leaderboard.php">Leaderboard</a></button>
            <button class="navlinks"><a href="hiw.html">How it works</a></button>
            <button class="cta"><a href="logout.php">Logout</a></button>
        </div>
    </nav>
    
    <div class="hero-section">
        <div data-aos="fade-down" data-aos-duration="1000">
            <h1>Predict the story. Win Rewards.</h1>
            <span>
                Immerse yourself in captivating stories and test your intuition<br> by predicting what happens next. Match the author's continuation and win...!
            </span>
        </div>
    </div>
    
    <div class="welcome-info" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="200">
        <h1>Welcome, <?php echo htmlspecialchars($first_name); ?>! 👋</h1>
        <span>Your total score: <?php echo htmlspecialchars($total_score); ?> pts</span>
        <div class="hero-cta">
            <a href="stories.php" class="cta">Start Reading!</a>
        </div>
    </div>
    
    <div data-aos="fade-up" data-aos-duration="1000">
        <h2>Featured Story</h2>
    </div>
    <div class="featured-story" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="300">
        <?php if ($featured_story): ?>
            <div class="featured-story-title">
                <div class="details">
                    <img src="<?php echo htmlspecialchars($featured_story['cover_image_url']); ?>" alt="<?php echo htmlspecialchars($featured_story['title']); ?> Cover" class="w-16 h-16 rounded-lg mr-4 object-cover">
                    <div>
                        <h3><?php echo htmlspecialchars($featured_story['title']); ?> - Part <?php echo htmlspecialchars($featured_story['part_number']); ?></h3>
                        <span class="text-sm text-gray-500">By: <?php echo htmlspecialchars($featured_story['created_by']); ?></span>
                    </div>
                </div>
                <div class="details">
                    <div class="status">
                        <div class="status-ping"></div>
                        Active
                    </div>
                    <div class="count-down">
                        <?php echo getTimeLeft($featured_story['prediction_deadline']); ?>
                    </div>
                </div>
            </div>
            <p><?php echo nl2br(htmlspecialchars($featured_story['description'])); ?></p>
            <div class="part-container">
                <?php
                    // Display progress bar based on total parts and current part number
                    for ($i = 1; $i <= $featured_story['total_parts']; $i++) {
                        $class = ($i <= $featured_story['part_number']) ? 'filled' : '';
                        echo '<div class="part-segment ' . $class . '"></div>';
                    }
                ?>
            </div>
            <div class="read-now-cta">
                <a href="story_detail.php?story_id=<?php echo htmlspecialchars($featured_story['story_id']); ?>&part_number=<?php echo htmlspecialchars($featured_story['part_number']); ?>" class="cta">Read Now & Predict!</a>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-500">No active stories at the moment. Check back soon!</p>
            <?php endif; ?>
        </div>
        
    <div data-aos="fade-up" data-aos-duration="1000">
        <h2>How it works</h2>
    </div>
    <div class="short-working">
    
        <div class="step-box" data-aos="fade-up" data-aos-delay="100">
            <div class="step-number">1</div>
            <h4>Read the Story</h4>
        </div>
        <div class="step-box" data-aos="fade-up" data-aos-delay="200">
            <div class="step-number">2</div>
            <h4>Make Your Prediction</h4>
        </div>    
        <div class="step-box" data-aos="fade-up" data-aos-delay="300">
            <div class="step-number">3</div>
            <h4>Match the Author</h4>
        </div>
        <div class="step-box" data-aos="fade-up" data-aos-delay="400">
            <div class="step-number">4</div>
            <h4>Win Rewards!</h4>
        </div>
    
    </div>
    
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      AOS.init({
        once: true, // Only animate once as you scroll down
      });
    </script>
</body>
</html>
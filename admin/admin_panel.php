<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - StoryPulse</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; background: #f6f9ff; color: #333; }
    header { background: #4b6cb7; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
    header h1 { margin: 0; font-size: 1.5rem; color: #0ff2feff;}
    nav a { color: white; margin-left: 20px; text-decoration: none; }
    .container { padding: 2rem; }
    .card { background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); padding: 1rem; margin-bottom: 2rem; }
    h2 { margin-top: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    th, td { border: 1px solid #ccc; padding: 0.5rem; text-align: left; }
    th { background-color: #eee; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; }
    .form-group input, .form-group textarea { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
    .btn { background: #4b6cb7; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; }
    .btn:hover { background: #39519b; }
    /*Add new story*/
  </style>
</head>
<body>
  <header>
    <h1>StoryPulse Admin Panel</h1>
    <nav>
      <a href="admin_dash.php">Dashboard</a>
      <a href="manage_users.php">Users</a>
      <a href="manage_stories.php">Stories</a>
      <a href="add_story.php">Add Story</a>
    </nav>
  </header>
  <div class="container">

    <!-- Dashboard Summary -->
    <div class="card" id="dashboard">
      <h2>Dashboard Summary</h2>
      <?php
      // Sample PHP Summary Data (replace with DB values)
      echo "<p><strong>Total Users:</strong> 52</p>";
      echo "<p><strong>Total Stories:</strong> 8</p>";
      echo "<p><strong>Active Predictions:</strong> 134</p>";
      echo "<p><strong>Total Bonus Paid:</strong> ₹2380</p>";
      ?>
    </div>

    <!-- Users Table -->
    <div class="card" id="users">
      <h2>Manage Users</h2>
      <table>
        <tr><th>User ID</th><th>Name</th><th>Email</th><th>Bonus</th><th>Action</th></tr>
        <?php
        // Sample data (replace with actual DB fetch loop)
        $users = [
          ["id" => 101, "name" => "Sarah", "email" => "sarah@email.com", "bonus" => 120],
          ["id" => 102, "name" => "Rafi", "email" => "rafkhan@email.com", "bonus" => 90],
        ];
        foreach ($users as $u) {
          echo "<tr><td>{$u['id']}</td><td>{$u['name']}</td><td>{$u['email']}</td><td>₹{$u['bonus']}</td>
                <td><form method='POST' action='update_bonus.php' style='display:inline;'>
                <input type='hidden' name='user_id' value='{$u['id']}'>
                <input type='number' name='bonus' placeholder='New bonus' required>
                <button class='btn' type='submit'>Update</button></form></td></tr>";
        }
        ?>
      </table>
    </div>

    <!-- Story List -->
    <div class="card" id="stories">
      <h2>Manage Stories</h2>
      <table>
        <tr><th>Story ID</th><th>Title</th><th>Status</th><th>Deadline</th><th>Action</th></tr>
        <?php
        // Replace with dynamic DB data
        $stories = [
          ["id" => 1, "title" => "The Forgotten Path", "status" => "Active", "deadline" => "2025-08-08"],
          ["id" => 2, "title" => "Beneath the Waves", "status" => "Closed", "deadline" => "2025-08-01"],
        ];
        foreach ($stories as $s) {
          echo "<tr><td>{$s['id']}</td><td>{$s['title']}</td><td>{$s['status']}</td><td>{$s['deadline']}</td>
                <td><a class='btn' href='edit_story.php?id={$s['id']}'>Edit</a></td></tr>";
        }
        ?>
      </table>
    </div>

    <!-- Add New Story -->
    

  </div>
</body>
</html>

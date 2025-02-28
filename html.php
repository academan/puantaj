<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Personnel Time-Keeping</title>
	<linkkkk rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap4.min.css">
    <style>
        body { padding-top: 20px; }
        .container { max-width: 1260px; }
        .logged-in-user { margin-bottom: 20px; }
        /* Optional: Add some spacing between sections */
        .section-heading { margin-top: 1rem; margin-bottom: 0.5rem;}
    </style>
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap4.min.js"></script>

    <script>
        function handleCheckboxChange(checkbox) {
            const userId = checkbox.value;
            const hoursInput = document.querySelector(`input[name="assigned_users[${userId}]"][placeholder="Hours"]`);

            if (checkbox.checked) {
                hoursInput.required = true; // Make the hours input required
            } else {
                hoursInput.required = false; // Make the hours input not required
                hoursInput.value = '';     // Clear the hours input
            }
        }

        $(document).ready(function() {
            // Checkbox handling (using jQuery for consistency)
            $('input[type="checkbox"][name^="assigned_users["]').on('change', function() {
                handleCheckboxChange(this);
            });

            // Initialize DataTables
        $('#tasksTable').DataTable();

        // Collapse/Expand functionality
        $('.toggle-section').on('click', function(e) {
         e.preventDefault();  // Prevent default link behavior
         const target = $(this).data('target'); // Get the target ID (#addTaskForm, #addUserForm, etc.)
         const action = $(this).data('action');
		 $(target).slideToggle(); // Toggle visibility with animation

         // Send AJAX request to update session variable
         $.ajax({
          url: 'index.php',  // Submit to the same page
          method: 'POST',
          data: { toggle_section: target.substring(1), action: action }, // Send section name (without #)
          // No success/error handling needed - we only care about updating the session
         });
        });
	<?php if ($initialSummaryCollapsed): ?>
    $('#summarySection').hide();
<?php endif; ?>
        // Initially collapse sections based on session variables
        <?php if ($_SESSION['addTaskCollapsed'] ?? true): ?>
         $('#addTaskForm').hide();
        <?php endif; ?>

        <?php if ($_SESSION['addUserCollapsed'] ?? true): ?>
         $('#addUserForm').hide();
        <?php endif; ?>

        <?php if (!isset($_POST['summary_user_id']) && ($_SESSION['summaryCollapsed'] ?? true)): ?>
         $('#summarySection').hide();
        <?php endif; ?>
       });

    </script>
</head>
<body>
    <div class="container">
        <h1>Academic Personnel Time-Keeping</h1>

        <?php if (isset($_SESSION['user_name'])): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <form method="POST" action="">
                    <button type="submit" class="btn btn-info" name="refresh">Refresh</button>
                </form>
                <p>Logged in as: <strong><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></strong></p>
                <form method="POST" action="">
                    <button type="submit" class="btn btn-warning" name="logout">Logout</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user_name'])): ?>
            <form method="POST" action="" class="mb-4">
                <h2>Login</h2>
                <div class="form-group">
                    <input type="text" name="username" placeholder="Username" required class="form-control">
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required class="form-control">
                </div>
                <button type="submit" name="login" class="btn btn-primary">Login</button>
            </form>
        <?php endif; ?>
<?php // --- Add User Form (Admin Only, Collapsible) ---
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
            <h2 class="section-heading">
                <button class="btn btn-success toggle-section" type="button" data-target="#addUserForm">
                    Add User
                </button>
            </h2>
            <form method="POST" action="" class="mb-4 collapse" id="addUserForm">
                <div class="form-group">
                    <input type="text" name="user_name" placeholder="Username" required class="form-control">
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required class="form-control">
                </div>
                <div class="form-group">
                    <select name="user_type" required class="form-control">
                        <option value="Student">Student</option>
                        <option value="Professor">Professor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="number" name="user_prev_id" placeholder="Previous User ID (Optional)" class="form-control">
                </div>
                <button type="submit" name="add_user" class="btn btn-success">Add User</button>
            </form>
        <?php endif; ?>

        <?php // --- Edit Task Form (Displayed Conditionally) ---
        if (isset($_SESSION['editing_task_id'])):
            // Fetch the task data
            $stmt = $pdo->prepare("SELECT * FROM Tasks WHERE Task_id = ?");
            $stmt->execute([$_SESSION['editing_task_id']]);
            $editing_task = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch assigned users and their hours
        $stmt = $pdo->prepare("SELECT u.User_id, u.User_name, u.User_type, ut.UserTask_hour FROM User_Tasks ut JOIN User u ON ut.User_id = u.User_id WHERE ut.Task_id = ?");
        $stmt->execute([$_SESSION['editing_task_id']]);
        $assigned_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($editing_task): // Make sure task exists
       ?>
            <form method="POST" action="" class="mb-4">
                <h2>Edit Task</h2>
                <input type="hidden" name="task_id" value="<?= $editing_task['Task_id'] ?>">
     <input type="hidden" id="task_authorized_by_hidden_debug" value="<?php echo htmlspecialchars($editing_task['Task_AuthorizedBy'] ?? 'NULL'); ?>">
                <div class="form-group">
                    <label for="task_info">Task Description:</label>
                    <textarea name="task_info" id="task_info" class="form-control" required><?= htmlspecialchars($editing_task['Task_Info'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="task_type">Task Type:</label>
                    <select name="task_type" id="task_type" class="form-control" required>
                        <?php foreach ($task_types as $type): ?>
                            <option value="<?= $type['TaskType_id'] ?>" <?= ($type['TaskType_id'] == $editing_task['TaskType_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['TaskType_name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="task_multiplier">Multiplier:</label>
                    <input type="number" name="task_multiplier" id="task_multiplier" step="0.1" class="form-control" value="<?= htmlspecialchars($editing_task['Task_Multiplier'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="task_date">Task Date:</label>
                    <input type="datetime-local" name="task_date" id="task_date" class="form-control" value="<?php
                        if (!empty($editing_task['Task_Date'])) {
                            $dateTime = new DateTime($editing_task['Task_Date']);
                            echo $dateTime->format('Y-m-d\TH:i');
                        }
                 ?>" >
                </div>
                <h3>Assign Users</h3>
                <?php foreach ($users as $user): ?>
                    <div class="form-check">
                    <?php
                    // Check if user is assigned
                    $isAssigned = false;
                    $assignedHours = 0;
                    foreach ($assigned_users as $assigned_user) {
                        if ($assigned_user['User_id'] == $user['User_id']) {
                            $isAssigned = true;
                            $assignedHours = $assigned_user['UserTask_hour'];
                            break;
                        }
                    }
                    ?>
                    <input class="form-check-input" type="checkbox" name="assigned_users[<?= $user['User_id'] ?>]" value="<?= $user['User_id'] ?>" <?= $isAssigned ? 'checked' : '' ?> onchange="handleCheckboxChange(this)">
                    <label class="form-check-label">
                        <?= htmlspecialchars($user['User_name'] ?? '') ?> (<?= htmlspecialchars($user['User_type'] ?? '') ?>)
                    </label>
                    <input type="number" name="assigned_users[<?= $user['User_id'] ?>]" placeholder="Hours" step="0.1" class="form-control"  value="<?= $isAssigned ? htmlspecialchars($assignedHours) : '' ?>">
                </div>
                <?php endforeach; ?>

                <button type="submit" name="update_task" class="btn btn-success">Update Task</button>
                <button type="submit" name="cancel_edit" class="btn btn-secondary">Cancel</button>
            </form>
        <?php
            else:
                echo "<p>Error: Task not found.</p>";
            endif;
        //Closing if statement for edit form.
        endif;
        ?>
<?php if (isset($_SESSION['user_id'])): ?>
        <h2 class="section-heading">
            <button class="btn btn-info toggle-section" type="button" data-target="#summarySection" data-action="toggle-summary">
                Task Summary
            </button>
        </h2>
			<div class="summary-section mt-4 <?php echo ($_SESSION['summaryCollapsed'] ?? true) ? 'collapse' : ''; ?>" id="summarySection">
              <?php if ($_SESSION['user_type'] === 'Professor' || $_SESSION['user_type'] === 'admin'): ?>
<form method="POST" action="" class="mb-3">
    <input type="hidden" name="summary_form_submitted" value="1">
    <div class="form-group">
        <label for="summary_user_id">Select User:</label>
        <select name="summary_user_id" id="summary_user_id" class="form-control" onchange="this.form.submit()">
            <option value="">-- Select User --</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['User_id'] ?>" <?= (isset($summary_user_id) && $summary_user_id == $user['User_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['User_name'] . " (" . $user['User_type'] . ")") ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<!--
    <div class="form-group">
        <label for="start_date">Start Date:</label>
        <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $start_date ?? '' ?>">
    </div>

    <div class="form-group">
        <label for="end_date">End Date:</label>
        <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $end_date ?? '' ?>">
    </div>
-->
    <input type="hidden" name="view_summary" value="1">
</form>
          <?php endif; ?>

          <p><strong>Authorized Tasks:</strong> <?= $summary['authorized_tasks'] ?></p>
          <p><strong>Authorized Total Hours:</strong> <?= $summary['total_hours'] ?></p>
          <p><strong>Authorized Weighted Hours:</strong> <?= $summary['total_weighted_hours'] ?></p>
          <p><strong>Unauthorized Tasks:</strong> <?= $summary['unauthorized_tasks'] ?></p>
          <p><strong>Unauthorized Hours:</strong> <?= $summary['unauthorized_hours'] ?></p>
          <p><strong>Unauthorized Weighted Hours:</strong> <?= $summary['unauthorized_weighted_hours'] ?></p>
          </div>
  <?php endif; ?>

        <?php // --- Add Task Form (Displayed Conditionally) ---
        if (isset($_SESSION['user_id']) && !isset($_SESSION['editing_task_id'])): ?>
            <h2 class="section-heading">
            <button class="btn btn-primary toggle-section" type="button" data-target="#addTaskForm" >
                Add Task
            </button>
        </h2>

            <form method="POST" action="" class="mb-4 collapse" id="addTaskForm">
                <div class="form-group">
                    <textarea name="task_info" placeholder="Task Description" required class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <select name="task_type" required class="form-control">
                    <?php foreach ($task_types as $type): ?>
                        <option value="<?= $type['TaskType_id'] ?>"><?= htmlspecialchars($type['TaskType_name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                </div>
                <div class="form-group">
                    <input type="number" name="task_multiplier" placeholder="Multiplier" step="0.1" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="task_date">Task Date:</label>
                    <input type="datetime-local" name="task_date" id="task_date"  required class="form-control">
                </div>

                <h3>Assign Users</h3>
                <?php foreach ($users as $user): ?>
                    <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="assigned_users[<?= $user['User_id'] ?>]" value="<?= $user['User_id'] ?>" onchange="handleCheckboxChange(this)">
                    <label class="form-check-label">
                        <?= htmlspecialchars($user['User_name'] ?? '') ?> (<?= htmlspecialchars($user['User_type'] ?? '') ?>)
                    </label>
                    <input type="number" name="assigned_users[<?= $user['User_id'] ?>]" placeholder="Hours" step="0.1" class="form-control">
                </div>
                <?php endforeach; ?>

                <button type="submit" name="add_task" class="btn btn-primary mt-3">Add Task</button>
            </form>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id'])): ?>
            <h2>Tasks</h2>
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="tasksTable" width="95%">
                    <thead class="thead-dark">
                        <tr>
                        <th>Task ID</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Mult.</th>
                        <th>Creator</th>
                        <th>Date&Time</th>
                        <th>Checked By</th>
                        <th>Assigned Users</th>
                        <th></th>
                      </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td><?= $task['Task_id'] ?></td>
                            <td><?= htmlspecialchars($task['Task_Info'] ?? '') ?></td>
                            <td>
                                <?php
                                $stmt = $pdo->prepare("SELECT TaskType_name FROM Task_Type WHERE TaskType_id = ?");
                                $stmt->execute([$task['TaskType_id']]);
                                $task_type = $stmt->fetch(PDO::FETCH_ASSOC);
                                echo htmlspecialchars($task_type['TaskType_name'] ?? '');
                                ?>
                            </td>
                            <td><?= $task['Task_Multiplier'] ?? '' ?></td>
                            <td>
                                <?php
                            $stmt = $pdo->prepare("SELECT User_name FROM User WHERE User_id = ?");
                            $stmt->execute([$task['Task_Creator']]);
                            $creator = $stmt->fetch(PDO::FETCH_ASSOC);
                            echo htmlspecialchars($creator['User_name'] ?? '');
                            ?></td>
                           <td><?= $task['Task_Date'] ?? '' ?></td>
                            <td>
                                <?php
                                if ($task['Task_AuthorizedBy']) {
                                    $stmt = $pdo->prepare("SELECT User_name FROM User WHERE User_id = ?");
                                    $stmt->execute([$task['Task_AuthorizedBy']]);
                                    $authorized_by = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if($authorized_by) {
                                        echo htmlspecialchars($authorized_by['User_name'] ?? '');
									}
                                }
                                ?>
                            </td>
							<td>
								<?php
								$stmt = $pdo->prepare("
									SELECT u.User_name, ut.UserTask_hour, t.Task_AuthorizedBy, u.User_id
									FROM User_Tasks ut
									JOIN User u ON ut.User_id = u.User_id
									JOIN Tasks t ON ut.Task_id = t.Task_id
									WHERE ut.Task_id = ?
								");
								$stmt->execute([$task['Task_id']]);
								$assignedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

								if ($assignedUsers) {
									foreach ($assignedUsers as $assignedUser) {
										// Show hours if user is professor/admin OR task is authorized OR user is the logged-in user
										if ($_SESSION['user_type'] === 'Professor' || $_SESSION['user_type'] === 'admin' || $assignedUser['User_id'] === $_SESSION['user_id'] || ($assignedUser['User_id'] !== $_SESSION['user_id'] && $assignedUser['Task_AuthorizedBy'] === null)) {
											echo htmlspecialchars($assignedUser['User_name'] ?? '') . " (" . htmlspecialchars($assignedUser['UserTask_hour'] ?? '') . ")<br>";
										} else {
											echo htmlspecialchars($assignedUser['User_name'] ?? '') . "<br>"; // User_name only
										}
									}
								} else {
									echo "No users assigned";
								}
								?>
							</td>							
							<td>
							<?php if (($_SESSION['user_type'] === 'Professor' && $task['Task_AuthorizedBy'] === null) || $_SESSION['user_type'] === 'admin'): ?>
                            <form method='POST' action=''>
                                <input type="hidden" name="edit_task_id" value="<?= $task['Task_id'] ?>">
                                <button type='submit' class='btn btn-sm btn-warning' name="edit_task_button">Edit</button>
                            </form>
							<?php endif; ?>
							<?php if ($_SESSION['user_type'] === 'admin'): ?>
                            <form method='POST' action=''>
                                <input type="hidden" name="delete_task_id" value="<?= $task['Task_id'] ?>">
                                <button type='submit' class='btn btn-sm btn-danger' onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                            <?php endif; ?>
							<?php if ($task['Task_AuthorizedBy'] === null && ($_SESSION['user_type'] === 'Professor' || $_SESSION['user_type'] === 'admin')): ?>
							<form method="POST" action="">
								<input type="hidden" name="task_id" value="<?= $task['Task_id'] ?>">
								<button type="submit" class="btn btn-sm btn-success" name="authorize_task">Authorize</button>
							</form>
							<?php endif; ?>
							<?php if ($_SESSION['user_type'] === 'admin' && $task['Task_AuthorizedBy'] !== null): ?>
							<form method="POST" action="">
								<input type="hidden" name="task_id" value="<?= $task['Task_id'] ?>">
								<button type="submit" class="btn btn-sm btn-danger" name="unauthorize_task">Unauthorize</button>
							</form>
							<?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
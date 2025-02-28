<?php
ini_set('error_log', './php_errors.log');
require_once 'functions.php';

// --- Helper Function to Calculate Task Summary ---
function get_task_summary(PDO $pdo, int $user_id, $start_date = null, $end_date = null): array
{
    $summary = [
        'authorized_tasks' => 0,
        'unauthorized_tasks' => 0,
        'total_hours' => 0.0,
        'total_weighted_hours' => 0.0,
        'unauthorized_hours' => 0.0,
        'unauthorized_weighted_hours' => 0.0,
    ];

    $stmt_tasks = $pdo->prepare("SELECT t.Task_id, ut.UserTask_hour, t.Task_AuthorizedBy, t.Task_Multiplier, t.Task_Date FROM Tasks t LEFT JOIN User_Tasks ut ON t.Task_id = ut.Task_id WHERE ut.User_id = ?");
    $stmt_tasks->execute([$user_id]);
    $tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);

    if ($start_date || $end_date) {
        $formatted_start_date = $start_date ? date('Y-m-d', strtotime($start_date)) : null;
        $formatted_end_date = $end_date ? date('Y-m-d', strtotime($end_date)) : null;

        $filtered_tasks = []; // Array to store filtered tasks
        foreach ($tasks as $task) {
            if ($task['Task_Date'] !== null) {
                $task_date_obj = new DateTime($task['Task_Date']);
                $task_date = $task_date_obj->format('Y-m-d');

                if (($formatted_start_date === null || $task_date >= $formatted_start_date) && ($formatted_end_date === null || $task_date <= $formatted_end_date)) {
                   $filtered_tasks[] = $task; // Add task to filtered array
                }
            }
        }

        foreach ($filtered_tasks as $task) { // Iterate over filtered tasks
            if ($task['Task_AuthorizedBy']) {
                $summary['authorized_tasks']++;
                $summary['total_hours'] += $task['UserTask_hour'];
                $summary['total_weighted_hours'] += $task['UserTask_hour'] * $task['Task_Multiplier'];
            } else {
                $summary['unauthorized_tasks']++;
                $summary['unauthorized_hours'] += $task['UserTask_hour'];
                $summary['unauthorized_weighted_hours'] += $task['UserTask_hour'] * $task['Task_Multiplier'];
            }
        }
    } else {
        foreach ($tasks as $task) {
          if ($task['Task_Date'] !== null) {
            if ($task['Task_AuthorizedBy']) {
                $summary['authorized_tasks']++;
                $summary['total_hours'] += $task['UserTask_hour'];
                $summary['total_weighted_hours'] += $task['UserTask_hour'] * $task['Task_Multiplier'];
            } else {
                $summary['unauthorized_tasks']++;
                $summary['unauthorized_hours'] += $task['UserTask_hour'];
                $summary['unauthorized_weighted_hours'] += $task['UserTask_hour'] * $task['Task_Multiplier'];
            }
          }
        }
    }

    return $summary;
}

// --- Handle AJAX request to toggle collapse state ---
if (isset($_POST['toggle_section'])) {
    $section = $_POST['toggle_section'];
    $allowed_sections = ['addTask', 'addUser', 'summary']; // Whitelist
    if (in_array($section, $allowed_sections)) {
        $_SESSION[$section . 'Collapsed'] = !$_SESSION[$section . 'Collapsed']; // Toggle the state
        exit(); // Stop execution *only* after handling the AJAX request.
    } else {
        // Log or handle invalid section attempt (optional, but good practice)
        error_log("Invalid section toggle attempted: " . htmlspecialchars($section));
        exit(); // Still exit to stop further execution
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['login'])) {
        // --- Login Logic ---
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Validation - Example basic validation, adjust as needed
        if (empty($username) || empty($password)) {
            echo "<script>alert('Username and password are required.');</script>";
            goto end_post_processing_login; // Stop processing
        }
        if (strlen($username) > 45 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) { // Example username rules: alphanumeric and underscore
            echo "<script>alert('Invalid username format.');</script>";
            goto end_post_processing_login;
        }
        // Password validation could be added here (length, complexity if needed later)

        $stmt = $pdo->prepare("SELECT * FROM User WHERE User_name = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && $user['Password'] === $password) {
            $_SESSION['user_id'] = $user['User_id'];
            $_SESSION['user_name'] = $user['User_name'];
            $_SESSION['user_type'] = $user['User_type'];
            //echo "<script>alert('Login successful!');</script>";
        } else {
            echo "<script>alert('Invalid credentials!');</script>";
        }
        end_post_processing_login:
    } elseif (isset($_POST['add_user'])) {
        // --- Add User (Admin Only) ---
        if ($_SESSION['user_type'] === 'admin') {
            $user_name = $_POST['user_name'];
            $password = $_POST['password'];
            $user_type = $_POST['user_type'];
            $user_prev_id = $_POST['user_prev_id'];

            // --- Validation ---
            if (empty($user_name) || empty($password) || empty($user_type)) {
                echo "<script>alert('Username, password, and user type are required.');</script>";
                goto end_post_processing_adduser;
            }
            if (strlen($user_name) > 45 || !preg_match('/^[a-zA-Z0-9_]+$/', $user_name)) {
                echo "<script>alert('Invalid username format.');</script>";
                goto end_post_processing_adduser;
            }
            // Password validation could be added here (length, complexity)
            $allowed_user_types = ['Student', 'Professor', 'admin']; // Whitelist for user types
            if (!in_array($user_type, $allowed_user_types)) {
                echo "<script>alert('Invalid user type.');</script>";
                goto end_post_processing_adduser;
            }
            if (!empty($user_prev_id) && !is_numeric($user_prev_id)) {
                echo "<script>alert('Previous User ID must be a number.');</script>";
                goto end_post_processing_adduser;
            }
            if (!empty($user_prev_id)) {
                $user_prev_id = filter_var($user_prev_id, FILTER_VALIDATE_INT);
                if ($user_prev_id === false) {
                    echo "<script>alert('Invalid Previous User ID format.');</script>"; // More specific error
                    goto end_post_processing_adduser;
                }
            } else {
                $user_prev_id = null; // Set to null if empty and validation passes
            }


            $stmt = $pdo->prepare("SELECT * FROM User WHERE User_name = ?");
            $stmt->execute([$user_name]);
            if ($stmt->rowCount() > 0) {
                echo "<script>alert('Username already exists');</script>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO User (User_name, Password, User_type, User_AddedOn, User_PrevId) VALUES (?, ?, ?, NOW(), ?)");
                $stmt->execute([$user_name, $password, $user_type, $user_prev_id]);

                if ($stmt->rowCount() > 0) {
                    //echo "<script>alert('User added successfully!');</script>";
                } else {
                    echo "<script>alert('Error adding user.');</script>";
                }
            }
        } else {
            echo "<script>alert('Only admins can add users.');</script>";
        }
        end_post_processing_adduser:
    } elseif (isset($_POST['add_task'])) {
        // --- Add Task ---
        if (isset($_SESSION['user_id'])) {
            $task_info = $_POST['task_info'];
            $task_type = $_POST['task_type'];
            $task_multiplier = $_POST['task_multiplier'];
            $task_creator = $_SESSION['user_id'];
            $task_date = $_POST['task_date'];
            $assigned_users_input = $_POST['assigned_users'] ?? []; // Get assigned users input

            // --- Validation ---
            if (empty($task_info) || empty($task_type) || empty($task_multiplier) || empty($task_date)) {
                echo "<script>alert('Task description, type, multiplier, and date are required.');</script>";
                goto end_post_processing_addtask;
            }
            if (strlen($task_info) > 255) {
                echo "<script>alert('Task description is too long (max 255 characters).');</script>";
                goto end_post_processing_addtask;
            }
            if (!is_numeric($task_type) || filter_var($task_type, FILTER_VALIDATE_INT) === false) {
                echo "<script>alert('Invalid task type format.');</script>";
                goto end_post_processing_addtask;
            } else {
                $task_type = intval($task_type); // Ensure task_type is integer
            }
            if (!is_numeric($task_multiplier) || filter_var($task_multiplier, FILTER_VALIDATE_FLOAT) === false) {
                echo "<script>alert('Invalid multiplier format.');</script>";
                goto end_post_processing_addtask;
            } else {
                $task_multiplier = floatval($task_multiplier); // Ensure task_multiplier is float
            }
            if ($task_multiplier <= 0) {
                echo "<script>alert('Multiplier must be greater than zero.');</script>";
                goto end_post_processing_addtask;
            }

            // Date validation
            $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $task_date);
            if (!$dateTime || $dateTime->format('Y-m-d\TH:i') !== $task_date) {
                echo "<script>alert('Invalid date/time format.');</script>";
                goto end_post_processing_addtask;
            }


            $stmt = $pdo->prepare("INSERT INTO Tasks (TaskType_id, Task_Info, Task_Multiplier, Task_Creator, Task_CreatedOn, Task_Date) VALUES (?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$task_type, $task_info, $task_multiplier, $task_creator, $task_date]);

            if ($stmt->rowCount() > 0) {
                $task_id = $pdo->lastInsertId();

                if (!empty($assigned_users_input) && is_array($assigned_users_input)) {
                    foreach ($assigned_users_input as $user_id => $hours) {
                        if(isset($user_id) && is_numeric($user_id) && isset($hours)) {
                            $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
                            $hours = filter_var($hours, FILTER_VALIDATE_FLOAT);
                            if ($user_id !== false && $hours !== false && $hours > 0) {
                                $stmt = $pdo->prepare("INSERT INTO User_Tasks (Task_id, User_id, UserTask_hour) VALUES (?, ?, ?)");
                                $stmt->execute([$task_id, $user_id, $hours]);
                            } else {
                                // Log or handle invalid assigned user data - important for data integrity
                                error_log("Invalid assigned user data: User ID=" . htmlspecialchars($user_id) . ", Hours=" . htmlspecialchars($hours));
                                // You might want to show a warning to the user or skip this invalid entry
                            }
                        }
                    }
                }

                //echo "<script>alert('Task created successfully!');</script>";
            } else {
                echo "<script>alert('Error creating task.');</script>";
            }
        } else {
            echo "<script>alert('You must be logged in to add tasks.');</script>";
        }
        end_post_processing_addtask:
    } elseif (isset($_POST['authorize_task'])) {
        // --- Authorize Task ---
    if ($_SESSION['user_type'] === 'Professor' || $_SESSION['user_type'] === 'admin') {
        $task_id = $_POST['task_id']; // <-- This line was missing!

        if (!is_numeric($task_id) || filter_var($task_id, FILTER_VALIDATE_INT) === false) {
            echo "<script>alert('Invalid Task ID format.');</script>";
            goto end_post_processing_authorizetask;
        } else {
            $task_id = intval($task_id); // Ensure task_id is integer
        }

        $stmt = $pdo->prepare("SELECT Task_AuthorizedBy FROM Tasks WHERE Task_id = ?"); // Check if task exists
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            echo "<script>alert('Task not found.');</script>";
        } elseif ($task['Task_AuthorizedBy'] !== null) {
            echo "<script>alert('Task is already authorized.');</script>";
        } else {
            $stmt = $pdo->prepare("UPDATE Tasks SET Task_AuthorizedBy = ? WHERE Task_id = ?");
            $stmt->execute([$_SESSION['user_id'], $task_id]); // Corrected: Use $_SESSION['user_id']
            // Removed rowCount() check here
        }
    } else {
        echo "<script>alert('Only professors and admins can authorize tasks.');</script>";
    }
        end_post_processing_authorizetask:
    } elseif (isset($_POST['edit_task_button'])) {
        // --- Edit Task (Set Session Variable) ---
        $_SESSION['editing_task_id'] = $_POST['edit_task_id'];

    } elseif (isset($_POST['update_task'])) {
        // --- Update Task ---
        $task_id = $_POST['task_id'];
        $task_info = $_POST['task_info'];
        $task_multiplier = $_POST['task_multiplier'];
        $task_date = $_POST['task_date'];
        $task_type = $_POST['task_type'];
        $assigned_users_input = $_POST['assigned_users'] ?? []; // Get assigned users input


        // --- Validation ---
        if (!is_numeric($task_id) || filter_var($task_id, FILTER_VALIDATE_INT) === false) {
            echo "<script>alert('Invalid Task ID format.');</script>";
            goto end_post_processing_updatetask;
        } else {
            $task_id = intval($task_id); // Ensure task_id is integer
        }
        if (empty($task_info) || empty($task_type) || empty($task_multiplier) || empty($task_date)) {
            echo "<script>alert('Task description, type, multiplier, and date are required.');</script>";
            goto end_post_processing_updatetask;
        }
        if (strlen($task_info) > 255) {
            echo "<script>alert('Task description is too long (max 255 characters).');</script>";
            goto end_post_processing_updatetask;
        }
        if (!is_numeric($task_type) || filter_var($task_type, FILTER_VALIDATE_INT) === false) {
            echo "<script>alert('Invalid task type format.');</script>";
            goto end_post_processing_updatetask;
        } else {
            $task_type = intval($task_type); // Ensure task_type is integer
        }
        if (!is_numeric($task_multiplier) || filter_var($task_multiplier, FILTER_VALIDATE_FLOAT) === false) {
            echo "<script>alert('Invalid multiplier format.');</script>";
            goto end_post_processing_updatetask;
        } else {
            $task_multiplier = floatval($task_multiplier); // Ensure task_multiplier is float
        }
        if ($task_multiplier <= 0) {
            echo "<script>alert('Multiplier must be greater than zero.');</script>";
            goto end_post_processing_updatetask;
        }
        // Date validation
        $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $task_date);
        if (!$dateTime || $dateTime->format('Y-m-d\TH:i') !== $task_date) {
            echo "<script>alert('Invalid date/time format.');</script>";
            goto end_post_processing_updatetask;
        }


        if ($_SESSION['user_type'] === 'admin') {
            $stmt = $pdo->prepare("UPDATE Tasks SET Task_Info = ?, Task_Multiplier = ?, Task_Date = ?, TaskType_id = ? WHERE Task_id = ?");
            $stmt->execute([$task_info, $task_multiplier, $task_date, $task_type, $task_id]);
			//$result = $stmt->fetchAll();
			//error_log("Admin Update - rowCount(): " . $stmt->rowCount());  // <-- ADD THIS LINE for temporary logging
        // echo "<script>alert('Task updated by admin!');</script>"; // Removed
        } elseif ($_SESSION['user_type'] === 'Professor') {
            // Corrected condition: Allow update if unauthorized OR authorized by the current professor.
			$stmt = $pdo->prepare("UPDATE Tasks SET Task_Info = ?, Task_Multiplier = ?, Task_Date = ?, TaskType_id = ? WHERE Task_id = ? AND Task_AuthorizedBy IS NULL");
            $stmt->execute([$task_info, $task_multiplier, $task_date, $task_type, $task_id]);
            /*
			if ($stmt->rowCount() == 0) {
                echo "<script>alert('Task update failed. You might not have permission.');</script>";
                goto end_post_processing_updatetask; // Stop if update failed due to permission
            }
            // echo "<script>alert('Task updated successfully!');</script>"; // Removed
			*/
        }
        else{
            echo "<script>alert('Task update failed.');</script>";  // Keep error messages
            goto end_post_processing_updatetask;
        }

        // 2. Delete *existing* user assignments for this task.
        $stmt = $pdo->prepare("DELETE FROM User_Tasks WHERE Task_id = ?");
        $stmt->execute([$task_id]);

        // 3. Insert the *new* user assignments.
        if (!empty($assigned_users_input) && is_array($assigned_users_input)) {
            foreach ($assigned_users_input as $user_id => $hours) {
                if(isset($user_id) && is_numeric($user_id) && isset($hours)) {
                    $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
                    $hours = filter_var($hours, FILTER_VALIDATE_FLOAT);
                    if ($user_id !== false && $hours !== false && $hours > 0) {
                        $stmt = $pdo->prepare("INSERT INTO User_Tasks (Task_id, User_id, UserTask_hour) VALUES (?, ?, ?)");
                        $stmt->execute([$task_id, $user_id, $hours]);
                    } else {
                        // Log or handle invalid assigned user data during update
                        error_log("Invalid assigned user data during update: User ID=" . htmlspecialchars($user_id) . ", Hours=" . htmlspecialchars($hours));
                        // You might want to show a warning or skip invalid entries
                    }
                }
            }
        }

        unset($_SESSION['editing_task_id']); // Clear edit mode
        end_post_processing_updatetask:

    } elseif (isset($_POST['cancel_edit'])) {
        // --- Cancel Edit ---
        unset($_SESSION['editing_task_id']); // Simply clear the session variable

    } elseif (isset($_POST['unauthorize_task'])) {
        // --- Unauthorize Task (Admin Only) ---
        if ($_SESSION['user_type'] === 'admin') {
            $task_id = $_POST['task_id'];
            if (!is_numeric($task_id) || filter_var($task_id, FILTER_VALIDATE_INT) === false) {
                echo "<script>alert('Invalid Task ID format.');</script>";
                goto end_post_processing_unauthorizetask;
            } else {
                $task_id = intval($task_id); // Ensure task_id is integer
            }
            $stmt = $pdo->prepare("UPDATE Tasks SET Task_AuthorizedBy = NULL WHERE Task_id = ?");
            $stmt->execute([$task_id]);
        } else {
            echo "<script>alert('Only admins can unauthorize tasks.');</script>";
        }
        end_post_processing_unauthorizetask:

    } elseif (isset($_POST['view_summary'])) {
    $summary_user_id = $_POST['summary_user_id'] ?? $_SESSION['user_id']; // Default to logged-in user
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;

    // Sanitize date inputs (important!)
    if ($start_date) {
        $start_date = date('Y-m-d', strtotime($start_date)); // Format: YYYY-MM-DD
    }
    if ($end_date) {
        $end_date = date('Y-m-d', strtotime($end_date));   // Format: YYYY-MM-DD
    }
    if (!empty($_POST['summary_user_id'])) { // Validate only if user selected a user from dropdown
        if (!is_numeric($summary_user_id) || filter_var($summary_user_id, FILTER_VALIDATE_INT) === false) {
            echo "<script>alert('Invalid User ID format for summary.');</script>";
            $summary_user_id = $_SESSION['user_id']; // Fallback to logged-in user ID in case of invalid input
        } else {
            $summary_user_id = intval($summary_user_id); // Ensure summary_user_id is integer
        }
    }
    $summary = get_task_summary($pdo, $summary_user_id, $start_date, $end_date);
     // Determine visibility based on *current* summary data.
    $summarySectionIsCurrentlyVisible = !($summary['authorized_tasks'] === 0 && $summary['unauthorized_tasks'] === 0 && $summary['total_hours'] === 0.0 && $summary['total_weighted_hours'] === 0.0);
    // Update the session *only* if the collapse/expand button was clicked.
    if (isset($_POST['toggle_section']) && $_POST['toggle_section'] === 'summarySection') {
        $_SESSION['summaryCollapsed'] = !$_SESSION['summaryCollapsed'];  // Toggle the value
    } else if (!isset($_POST['summary_form_submitted'])){
        $_SESSION['summaryCollapsed'] = !$summarySectionIsCurrentlyVisible;
    }

    if (isset($_POST['summary_form_submitted'])) {
        $_SESSION['summaryCollapsed'] = !$summarySectionIsCurrentlyVisible; // Invert the state
    }
    } elseif (isset($_POST['delete_task_id'])) {
    if ($_SESSION['user_type'] === 'admin') { // Only admins can delete
        $task_id = $_POST['delete_task_id'];

        // Sanitize $task_id (important!)
        if (!is_numeric($task_id) || filter_var($task_id, FILTER_VALIDATE_INT) === false) {
            echo "<script>alert('Invalid Task ID format.');</script>";
            goto end_post_processing_deletetask;
        } else {
            $task_id = intval($task_id); // Ensure task_id is integer
        }

        // Check if task exists before deleting
        $stmt = $pdo->prepare("SELECT 1 FROM Tasks WHERE Task_id = ?");
        $stmt->execute([$task_id]);
        $taskExists = $stmt->fetchColumn();

        if ($taskExists) {
            $stmt = $pdo->prepare("DELETE FROM User_Tasks WHERE Task_id = ?"); // Delete from User_Tasks first
            $stmt->execute([$task_id]);

            $stmt = $pdo->prepare("DELETE FROM Tasks WHERE Task_id = ?"); // Then delete from Tasks
            $stmt->execute([$task_id]);
        } else {
            echo "<script>alert('Task not found.');</script>";
        }
    } else {
        echo "<script>alert('Only admins can delete tasks.');</script>";
    }
    end_post_processing_deletetask:
	}
}

// Fetch users and task types.
$users = $pdo->query("SELECT * FROM User")->fetchAll(PDO::FETCH_ASSOC);
$task_types = $pdo->query("SELECT * FROM Task_Type")->fetchAll(PDO::FETCH_ASSOC);

// Fetch tasks based on user role.
$tasks = [];
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        $stmt = $pdo->prepare("SELECT * FROM Tasks");
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($_SESSION['user_type'] === 'Professor') {
        // Professors: All unauthorized tasks + tasks they authorized + tasks they created+ tasks assigned to .
        $stmt = $pdo->prepare("
            SELECT * FROM Tasks WHERE Task_AuthorizedBy IS NULL
            UNION ALL
            SELECT * FROM Tasks WHERE Task_AuthorizedBy = ?
            UNION ALL
            SELECT * FROM Tasks WHERE Task_Creator = ?
            UNION ALL
            SELECT t.* FROM Tasks t JOIN User_Tasks ut ON t.Task_id = ut.Task_id WHERE ut.User_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'],$_SESSION['user_id'],$_SESSION['user_id']]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($_SESSION['user_type'] === 'Student') {
        // Students: Only tasks assigned to them.
        $stmt = $pdo->prepare("SELECT t.* FROM Tasks t JOIN User_Tasks ut ON t.Task_id = ut.Task_id WHERE ut.User_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get initial summary data (for logged-in user, or selected user)

    if(isset($_POST['view_summary'])){
        $summary_user_id = $_POST['summary_user_id'];
    }
    elseif(isset($_SESSION['user_id'])){
        $summary_user_id = $_SESSION['user_id'];
    }

    if(isset($summary_user_id))
        $summary = get_task_summary($pdo, $summary_user_id);
    else
        $summary = [
            'authorized_tasks' => 0,
            'unauthorized_tasks' => 0,
            'total_hours' => 0.0,
            'total_weighted_hours' => 0.0,
            'unauthorized_hours' => 0.0,
            'unauthorized_weighted_hours' => 0.0
        ];

$initialSummaryCollapsed = $_SESSION['summaryCollapsed'] ?? true; // Get initial state from session or default
require_once 'html.php';
?>
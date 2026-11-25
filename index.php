<?php
require_once "db.php";

// Make mysqli throw exceptions (same behaviour on every PHP version)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = "";
$message_type = "success";

// Allowed values (also used to build the dropdowns)
$courses  = ["BS Information Technology", "BS Computer Science", "BS Information Systems"];
$years    = ["1st Year", "2nd Year", "3rd Year", "4th Year"];
$statuses = ["Active", "Inactive"];

function clean($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* ------------------------------------------------------------------
   CREATE AND UPDATE
------------------------------------------------------------------ */
$save_failed = false;
$posted = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save"])) {

    $posted = [
        "id"             => trim($_POST["id"] ?? ""),
        "student_number" => trim($_POST["student_number"] ?? ""),
        "first_name"     => trim($_POST["first_name"] ?? ""),
        "last_name"      => trim($_POST["last_name"] ?? ""),
        "course"         => $_POST["course"] ?? "",
        "year_level"     => $_POST["year_level"] ?? "",
        "status"         => $_POST["status"] ?? "",
    ];

    // Server-side validation (client-side validation can be bypassed)
    $missing = false;
    foreach (["student_number", "first_name", "last_name", "course", "year_level", "status"] as $f) {
        if ($posted[$f] === "") {
            $missing = true;
        }
    }

    if ($missing) {
        $message = "Please complete all required fields.";
        $message_type = "error";
        $save_failed = true;

    } elseif (
        !in_array($posted["course"], $courses, true) ||
        !in_array($posted["year_level"], $years, true) ||
        !in_array($posted["status"], $statuses, true)
    ) {
        $message = "Invalid course, year level, or status selected.";
        $message_type = "error";
        $save_failed = true;

    } else {
        try {
            if ($posted["id"] === "") {

                // CREATE
                $stmt = $conn->prepare(
                    "INSERT INTO students
                    (student_number, first_name, last_name, course, year_level, status)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "ssssss",
                    $posted["student_number"],
                    $posted["first_name"],
                    $posted["last_name"],
                    $posted["course"],
                    $posted["year_level"],
                    $posted["status"]
                );
                $stmt->execute();
                $message = "Student successfully added!";

            } else {

                // UPDATE
                $id = (int)$posted["id"];
                $stmt = $conn->prepare(
                    "UPDATE students
                    SET student_number = ?,
                        first_name = ?,
                        last_name = ?,
                        course = ?,
                        year_level = ?,
                        status = ?
                    WHERE id = ?"
                );
                $stmt->bind_param(
                    "ssssssi",
                    $posted["student_number"],
                    $posted["first_name"],
                    $posted["last_name"],
                    $posted["course"],
                    $posted["year_level"],
                    $posted["status"],
                    $id
                );
                $stmt->execute();
                $message = "Student record successfully updated!";
            }

        } catch (mysqli_sql_exception $e) {
            $save_failed = true;
            $message_type = "error";
            if ($e->getCode() == 1062) {
                $message = "Student number already exists. Please use a different one.";
            } else {
                $message = "Error: " . $e->getMessage();
            }
        }
    }
}

/* ------------------------------------------------------------------
   DELETE
------------------------------------------------------------------ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete"])) {

    $id = (int)($_POST["delete_id"] ?? 0);

    try {
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $message = "Student record successfully deleted!";
    } catch (mysqli_sql_exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

/* ------------------------------------------------------------------
   SEARCH + FILTER (READ)
------------------------------------------------------------------ */
$search        = trim($_GET["search"] ?? "");
$filter_course = $_GET["course"] ?? "";
$filter_year   = $_GET["year_level"] ?? "";

$sql    = "SELECT * FROM students WHERE 1=1";
$types  = "";
$params = [];

if ($search !== "") {
    $sql .= " AND (student_number LIKE ?
               OR first_name LIKE ?
               OR last_name LIKE ?
               OR CONCAT(first_name, ' ', last_name) LIKE ?)";
    $like = "%" . $search . "%";
    $types .= "ssss";
    array_push($params, $like, $like, $like, $like);
}

if ($filter_course !== "") {
    $sql .= " AND course = ?";
    $types .= "s";
    $params[] = $filter_course;
}

if ($filter_year !== "") {
    $sql .= " AND year_level = ?";
    $types .= "s";
    $params[] = $filter_year;
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$shown  = $result->num_rows;

// Total registered students (ignores search/filters)
$total = $conn->query("SELECT COUNT(*) AS total FROM students")->fetch_assoc()["total"];

// Keep the current search/filters in links and form actions
$filters = array_filter([
    "search"     => $search,
    "course"     => $filter_course,
    "year_level" => $filter_year,
], function ($v) { return $v !== ""; });

$qs = http_build_query($filters);
$has_filters = !empty($filters);

/* ------------------------------------------------------------------
   FORM VALUES (add mode, edit mode, or re-fill after an error)
------------------------------------------------------------------ */
$form = [
    "id" => "", "student_number" => "", "first_name" => "",
    "last_name" => "", "course" => "", "year_level" => "",
    "status" => "Active",
];

if ($save_failed) {
    $form = array_merge($form, $posted);
} elseif (isset($_GET["edit"])) {
    $edit_id = (int)$_GET["edit"];
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $form = array_merge($form, $row);
    }
}

$is_editing = ($form["id"] !== "");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Record Management System</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

<header class="app-header">
    <h1>Student Record Management System</h1>
    <p class="subtitle">Manage student information efficiently.</p>
</header>

<div class="container">

    <?php if ($message !== ""): ?>
        <div class="message message-<?= clean($message_type) ?>">
            <?= clean($message) ?>
        </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <div class="stats">
        <div class="stat-card">
            <span class="stat-number"><?= clean($total) ?></span>
            <span class="stat-label">Total Students</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= clean($shown) ?></span>
            <span class="stat-label">Currently Shown</span>
        </div>
    </div>

    <!-- STUDENT FORM -->
    <section class="card">

        <h2><?= $is_editing ? "Update Student" : "Register Student" ?></h2>

        <form method="POST" action="index.php<?= $qs ? "?" . $qs : "" ?>" id="studentForm">

            <input type="hidden" name="id" value="<?= clean($form["id"]) ?>">

            <div class="form-grid">

                <div class="field">
                    <label for="student_number">Student Number</label>
                    <input type="text" name="student_number" id="student_number"
                           value="<?= clean($form["student_number"]) ?>" required>
                </div>

                <div class="field">
                    <label for="first_name">First Name</label>
                    <input type="text" name="first_name" id="first_name"
                           value="<?= clean($form["first_name"]) ?>" required>
                </div>

                <div class="field">
                    <label for="last_name">Last Name</label>
                    <input type="text" name="last_name" id="last_name"
                           value="<?= clean($form["last_name"]) ?>" required>
                </div>

                <div class="field">
                    <label for="course">Course</label>
                    <select name="course" id="course" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= clean($c) ?>"
                                <?= $form["course"] === $c ? "selected" : "" ?>>
                                <?= clean($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="year_level">Year Level</label>
                    <select name="year_level" id="year_level" required>
                        <option value="">Select Year Level</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= clean($y) ?>"
                                <?= $form["year_level"] === $y ? "selected" : "" ?>>
                                <?= clean($y) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="status">Status</label>
                    <select name="status" id="status" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= clean($s) ?>"
                                <?= $form["status"] === $s ? "selected" : "" ?>>
                                <?= clean($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" name="save" class="btn btn-primary">
                    <?= $is_editing ? "Update Student" : "Save Student" ?>
                </button>

                <button type="reset" class="btn btn-secondary">Reset</button>

                <?php if ($is_editing): ?>
                    <a href="index.php<?= $qs ? "?" . $qs : "" ?>" class="btn btn-cancel">Cancel</a>
                <?php endif; ?>
            </div>

        </form>

    </section>

    <!-- SEARCH AND FILTER -->
    <section class="card">

        <h2>Search and Filter</h2>

        <form method="GET" action="index.php" class="filter-form">

            <input type="text" name="search" placeholder="Search by student number or name"
                   value="<?= clean($search) ?>">

            <select name="course">
                <option value="">All Courses</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= clean($c) ?>"
                        <?= $filter_course === $c ? "selected" : "" ?>>
                        <?= clean($c) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="year_level">
                <option value="">All Year Levels</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= clean($y) ?>"
                        <?= $filter_year === $y ? "selected" : "" ?>>
                        <?= clean($y) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Search</button>

            <?php if ($has_filters): ?>
                <a href="index.php" class="btn btn-secondary">Clear Filters</a>
            <?php endif; ?>

        </form>

    </section>

    <!-- STUDENT RECORDS TABLE -->
    <section class="card table-container">

        <h2>
            Registered Students
            <span class="count-pill">Total: <?= clean($total) ?></span>
        </h2>

        <table>

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student Number</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Course</th>
                    <th>Year Level</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

                <?php if ($shown > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <?php
                        $edit_link = "index.php?" . http_build_query(
                            array_merge($filters, ["edit" => $row["id"]])
                        );
                        ?>

                        <tr>
                            <td><?= clean($row["id"]) ?></td>
                            <td><?= clean($row["student_number"]) ?></td>
                            <td><?= clean($row["first_name"]) ?></td>
                            <td><?= clean($row["last_name"]) ?></td>
                            <td><?= clean($row["course"]) ?></td>
                            <td><?= clean($row["year_level"]) ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower(clean($row["status"])) ?>">
                                    <?= clean($row["status"]) ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="<?= clean($edit_link) ?>" class="btn-edit">Edit</a>

                                <form method="POST"
                                      action="index.php<?= $qs ? "?" . $qs : "" ?>"
                                      class="delete-form">
                                    <input type="hidden" name="delete_id"
                                           value="<?= clean($row["id"]) ?>">
                                    <button type="submit" name="delete"
                                            class="btn-delete">Delete</button>
                                </form>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty">No student records found.</td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </section>

</div>

<script src="script.js"></script>

</body>
</html>

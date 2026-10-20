<?php
  include("session.php");
  $budget_table_sql = "CREATE TABLE IF NOT EXISTS monthly_budgets (
    user_id INT NOT NULL PRIMARY KEY,
    monthly_limit DECIMAL(12, 2) NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  if (!mysqli_query($con, $budget_table_sql)) {
      die("Could not prepare the monthly budget table: " . mysqli_error($con));
  }

  if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_budget"])) {
      $monthly_limit = filter_input(INPUT_POST, "monthly_limit", FILTER_VALIDATE_FLOAT);
      if ($monthly_limit === false || $monthly_limit === null || $monthly_limit < 0) {
          die("Please enter a valid monthly budget of zero or more.");
      }

      $save_budget = mysqli_prepare(
          $con,
          "INSERT INTO monthly_budgets (user_id, monthly_limit) VALUES (?, ?)
           ON DUPLICATE KEY UPDATE monthly_limit = VALUES(monthly_limit)"
      );
      if (!$save_budget) {
          die("Could not prepare the monthly budget update: " . mysqli_error($con));
      }
      mysqli_stmt_bind_param($save_budget, "id", $userid, $monthly_limit);
      if (!mysqli_stmt_execute($save_budget)) {
          die("Could not save the monthly budget: " . mysqli_stmt_error($save_budget));
      }
      mysqli_stmt_close($save_budget);
      header("Location: index.php");
      exit();
  }

  $budget_query = mysqli_prepare($con, "SELECT monthly_limit FROM monthly_budgets WHERE user_id = ?");
  if (!$budget_query) {
      die("Could not load the monthly budget: " . mysqli_error($con));
  }
  mysqli_stmt_bind_param($budget_query, "i", $userid);
  if (!mysqli_stmt_execute($budget_query)) {
      die("Could not load the monthly budget: " . mysqli_stmt_error($budget_query));
  }
  $budget_result = mysqli_stmt_get_result($budget_query);
  $budget_row = mysqli_fetch_assoc($budget_result);
  $monthly_limit = $budget_row ? (float) $budget_row["monthly_limit"] : 0;
  mysqli_stmt_close($budget_query);

  $month_start = date("Y-m-01");
  $next_month_start = date("Y-m-01", strtotime("first day of next month"));
  $spent_query = mysqli_prepare(
      $con,
      "SELECT COALESCE(SUM(expense), 0) AS monthly_spent
       FROM expenses
       WHERE user_id = ? AND expensedate >= ? AND expensedate < ?"
  );
  if (!$spent_query) {
      die("Could not prepare the monthly spending query: " . mysqli_error($con));
  }
  mysqli_stmt_bind_param($spent_query, "iss", $userid, $month_start, $next_month_start);
  if (!mysqli_stmt_execute($spent_query)) {
      die("Could not load monthly spending: " . mysqli_stmt_error($spent_query));
  }
  $spent_result = mysqli_stmt_get_result($spent_query);
  $monthly_spent = (float) mysqli_fetch_assoc($spent_result)["monthly_spent"];
  mysqli_stmt_close($spent_query);
  $remaining_budget = $monthly_limit - $monthly_spent;
  $budget_progress = $monthly_limit > 0
      ? min(100, ($monthly_spent / $monthly_limit) * 100)
      : 0;

  $one_month_ago = date("Y-m-d", strtotime("-1 month"));
  $exp_category_dc = mysqli_query($con, "SELECT expensecategory FROM expenses WHERE user_id = '$userid' AND expensedate >= '$one_month_ago' GROUP BY expensecategory");
  $exp_amt_dc = mysqli_query($con, "SELECT SUM(expense) FROM expenses WHERE user_id = '$userid' AND expensedate >= '$one_month_ago' GROUP BY expensecategory");
  
  $one_week_ago = date("Y-m-d", strtotime("-1 week"));
  $exp_date_line = mysqli_query($con, "SELECT DATE_FORMAT(expensedate, '%b %d') AS day_month FROM expenses WHERE user_id = '$userid' AND expensedate >= '$one_week_ago' GROUP BY expensedate");
  $exp_amt_line = mysqli_query($con, "SELECT SUM(expense) FROM expenses WHERE user_id = '$userid' AND expensedate >= '$one_week_ago' GROUP BY expensedate");
  
  $yearly_expenses_query = "SELECT YEAR(expensedate) AS year, SUM(expense) AS total_expense
                          FROM expenses
                          WHERE user_id = '$userid'
                          GROUP BY YEAR(expensedate)
                          ORDER BY YEAR(expensedate)";
$yearly_expenses_result = mysqli_query($con, $yearly_expenses_query);
$year_labels = [];
$yearly_expense_data = [];
while ($row = mysqli_fetch_assoc($yearly_expenses_result)) {
    $year_labels[] = $row['year'];
    $yearly_expense_data[] = $row['total_expense'];
}

$monthly_expenses_query = "SELECT DATE_FORMAT(expensedate, '%Y-%m') AS month_year, SUM(expense) AS total_expense
                          FROM expenses
                          WHERE user_id = '$userid'
                          AND expensedate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                          GROUP BY DATE_FORMAT(expensedate, '%Y-%m')
                          ORDER BY expensedate";
$monthly_expenses_result = mysqli_query($con, $monthly_expenses_query);
$monthly_labels = [];
$monthly_expense_data = [];
while ($row = mysqli_fetch_assoc($monthly_expenses_result)) {
    $monthly_labels[] = $row['month_year'];
    $monthly_expense_data[] = $row['total_expense'];
}

$today_expense = mysqli_query($con, "SELECT SUM(expense) FROM expenses WHERE user_id = '$userid' AND expensedate = CURDATE()");
$yesterday_expense = mysqli_query($con, "SELECT SUM(expense) FROM expenses WHERE user_id = '$userid' AND expensedate = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
$this_week_expense = mysqli_query($con, "SELECT SUM(expense) FROM expenses WHERE user_id = '$userid' AND expensedate >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)");
$this_month_expense = mysqli_query($con, "SELECT SUM(expense) FROM expenses WHERE user_id = '$userid' AND expensedate >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
$this_year_expense = mysqli_query($con, "SELECT SUM(expense) FROM expenses WHERE user_id = '$userid' AND expensedate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)");
$total_expense = mysqli_query($con, "SELECT SUM(expense) FROM expenses WHERE user_id = '$userid'");

$today_expense_amount = '0' + mysqli_fetch_assoc($today_expense)['SUM(expense)'];
$yesterday_expense_amount ='0' + mysqli_fetch_assoc($yesterday_expense)['SUM(expense)'];
$this_week_expense_amount = '0' + mysqli_fetch_assoc($this_week_expense)['SUM(expense)'];
$this_month_expense_amount = '0' + mysqli_fetch_assoc($this_month_expense)['SUM(expense)'];
$this_year_expense_amount = '0' + mysqli_fetch_assoc($this_year_expense)['SUM(expense)'];
$total_expense_amount = '0' + mysqli_fetch_assoc($total_expense)['SUM(expense)'];

?>
<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title>Expense Manager - Dashboard</title>

  <!-- Bootstrap core CSS -->
  <link href="css/bootstrap.css" rel="stylesheet">
  
    <!-- Custom styles for this template -->
    <link href="css/style.css" rel="stylesheet">

  <!-- Feather JS for Icons -->
  <script src="js/feather.min.js"></script>
  <style>
    .card a {
      color: #000;
      font-weight: 500;
    }

    .card a:hover {
      color: #28a745;
      text-decoration: dotted;
    }
    .try {
  font-size: 28px; /* Adjust the font size as needed */
  color: #333;    /* Adjust the color as needed */
  padding: 5px 0px 0px 0px;   /* Adjust the padding as needed */
}
.container {
    padding:0px 20px 20px 20px;/* Add padding to the container */
  }
.card.text-center {
    border: 3px solid #ccc;
    padding: 10px;
    margin: 10px;
    background-color: #f8f9fa;
    border-radius: 5px;
  }

  .card-title {
    font-size: 17.5px;
    margin-bottom: 1px ;
    color: #333;
  }

  .card-text {
    font-size: 24px;
    font-weight: bold;
    color: #6c757d;
  }
  
  </style>

</head>

<body>

  <div class="d-flex" id="wrapper">

    <!-- Sidebar -->
    <div class="border-right" id="sidebar-wrapper">
      <div class="user">
        <img class="img img-fluid rounded-circle" src="uploads\default_profile.png" width="120">
        <h5><?php echo $username ?></h5>
        <p><?php echo $useremail ?></p>
      </div>
      <div class="sidebar-heading">Management</div>
      <div class="list-group list-group-flush">
        <a href="index.php" class="list-group-item list-group-item-action sidebar-active"><span data-feather="home"></span> Dashboard</a>
        <a href="add_expense.php" class="list-group-item list-group-item-action "><span data-feather="plus-square"></span> Add Expenses</a>
        <a href="manage_expense.php" class="list-group-item list-group-item-action "><span data-feather="dollar-sign"></span> Manage Expenses</a>
        <a href="expensereport.php" class="list-group-item list-group-item-action"><span data-feather="file-text"></span> Expense Report</a>

      </div>
      <div class="sidebar-heading">Settings </div>
      <div class="list-group list-group-flush">
        <a href="profile.php" class="list-group-item list-group-item-action "><span data-feather="user"></span> Profile</a>
        <a href="logout.php" class="list-group-item list-group-item-action "><span data-feather="power"></span> Logout</a>
      </div>
    </div>
    <!-- /#sidebar-wrapper -->

    <!-- Page Content -->
    <div id="page-content-wrapper">

      <nav class="navbar navbar-expand-lg navbar-light  border-bottom">


        <button class="toggler" type="button" id="menu-toggle" aria-expanded="false">
          <span data-feather="menu"></span>
        </button>
        <div class="col-md-0 text-center">
    <h3 class="try">Dashboard</h3>
</div>
        
      </nav>
      <div class="container-fluid">
        <div class="card mt-4">
          <div class="card-header">
            <h5 class="card-title mb-0">Monthly Budget - <?php echo date("F Y"); ?></h5>
          </div>
          <div class="card-body">
            <form method="post" action="index.php" class="form-inline mb-3">
              <label for="monthly_limit" class="mr-2">Set your monthly spending limit (₹)</label>
              <input
                type="number"
                class="form-control mr-2"
                id="monthly_limit"
                name="monthly_limit"
                min="0"
                step="0.01"
                value="<?php echo htmlspecialchars(number_format($monthly_limit, 2, ".", ""), ENT_QUOTES, "UTF-8"); ?>"
                required
              >
              <button type="submit" name="save_budget" class="btn btn-success">Save Budget</button>
            </form>
            <div class="row text-center">
              <div class="col-md-4 mb-2">
                <strong>Budget:</strong> ₹<?php echo number_format($monthly_limit, 2); ?>
              </div>
              <div class="col-md-4 mb-2">
                <strong>Spent this month:</strong> ₹<?php echo number_format($monthly_spent, 2); ?>
              </div>
              <div class="col-md-4 mb-2">
                <?php if ($monthly_limit <= 0) { ?>
                  <strong>Remaining:</strong> Set a budget to track what you have left.
                <?php } elseif ($remaining_budget >= 0) { ?>
                  <strong>Remaining:</strong> ₹<?php echo number_format($remaining_budget, 2); ?>
                <?php } else { ?>
                  <strong>Over budget by:</strong> ₹<?php echo number_format(abs($remaining_budget), 2); ?>
                <?php } ?>
              </div>
            </div>
            <?php if ($monthly_limit > 0) { ?>
              <div class="progress mt-2" style="height: 20px;">
                <div
                  class="progress-bar <?php echo $monthly_spent > $monthly_limit ? "bg-danger" : "bg-success"; ?>"
                  role="progressbar"
                  style="width: <?php echo $budget_progress; ?>%;"
                  aria-valuenow="<?php echo $budget_progress; ?>"
                  aria-valuemin="0"
                  aria-valuemax="100"
                ><?php echo number_format($budget_progress, 0); ?>%</div>
              </div>
            <?php } ?>
          </div>
        </div>
        <h4 class="mt-4">Full-Expense Report</h4>
        <div class="row">

        <div class="container mt-4">
  <div class="row">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Today's Expense</h5>
          <p class="card-text">₹<?php echo $today_expense_amount; ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Yesterday's Expense</h5>
          <p class="card-text">₹<?php echo $yesterday_expense_amount; ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Last 7Day's Expense</h5>
          <p class="card-text">₹<?php echo $this_week_expense_amount; ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Last 30Day's Expense</h5>
          <p class="card-text">₹<?php echo $this_month_expense_amount; ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Current Year Expense</h5>
          <p class="card-text">₹<?php echo $this_year_expense_amount; ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Total Expense</h5>
          <p class="card-text">₹<?php echo $total_expense_amount; ?></p>
        </div>
      </div>
    </div>
  </div>
</div>

          <!-- Daily Expenses Chart -->
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h5 class="card-title text-center">Daily Expenses</h5>
              </div>
              <div class="card-body">
                <canvas id="expense_line" height="200"></canvas>
              </div>
            </div>
          </div>
          <!-- Expense Category Chart -->
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h5 class="card-title text-center">Expense Category</h5>
              </div>
              <div class="card-body">
                <canvas id="expense_category_pie" height="200"></canvas>
              </div>
            </div>
          </div>
          <!-- Monthly Expenses Chart -->
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h5 class="card-title text-center">Monthly Expenses</h5>
              </div>
              <div class="card-body">
                <canvas id="monthly_expense_line" height="200"></canvas>
              </div>
            </div>
          </div>
          <!-- Yearly Expenses Chart -->
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h5 class="card-title text-center">Yearly Expenses</h5>
              </div>
              <div class="card-body">
                <canvas id="expense_yearly_line" height="200"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- /#page-content-wrapper -->

  </div>
  <!-- /#wrapper -->

  <!-- Bootstrap core JavaScript -->
  <script src="js/jquery.slim.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/Chart.min.js"></script>
  <!-- Menu Toggle Script -->
  <script>
    $("#menu-toggle").click(function(e) {
      e.preventDefault();
      $("#wrapper").toggleClass("toggled");
    });
  </script>
  <script>
    feather.replace()
  </script>
  <script>
    var ctx = document.getElementById('expense_category_pie').getContext('2d');

var categories = [<?php while ($a = mysqli_fetch_array($exp_category_dc)) {
    echo '"' . $a['expensecategory'] . '",';
} ?>];
var expenses = [<?php while ($b = mysqli_fetch_array($exp_amt_dc)) {
    echo '"' . $b['SUM(expense)'] . '",';
} ?>];
var colors = [
    '#6f42c1',
    '#dc3545',
    '#28a745',
    '#007bff',
    '#ffc107',
    '#20c997',
    '#17a2b8',
    '#fd7e14',
    '#e83e8c',
    '#6610f2'
];

var dataset = {
    labels: categories,
    datasets: [{
        label: 'Expense by Category (Last Month)',
        data: expenses,
        backgroundColor: colors,
        borderWidth: 1
    }]
};

var options = {
    scales: {
        x: {
            beginAtZero: true,
            ticks: {
                autoSkip: false,
                maxRotation: 45,
                minRotation: 45
            }
        },
        y: {
            beginAtZero: true
        }
    }
};

var myChart = new Chart(ctx, {
    type: 'bar',
    data: dataset,
    options: options
});


var yearlyColors = [
  '#dc3545',  // Red
    '#28a745',  // Green
    '#007bff',  // Blue
    '#ffc107',  // Yellow
    '#20c997',  // Teal
    '#17a2b8',  // Cyan
    '#fd7e14',  // Orange
    '#e83e8c',  // Pink
    '#6610f2'
    ];

    var yearlyLine = document.getElementById('expense_yearly_line').getContext('2d');
    var yearlyChartData = {
      labels: [<?php echo '"' . implode('","', $year_labels) . '"'; ?>],
      datasets: [{
        label: 'Yearly Expense',
        data: [<?php echo implode(',', $yearly_expense_data); ?>],
        borderColor: yearlyColors,
        backgroundColor: yearlyColors,
        fill: false,
        borderWidth: 2
      }]
    };

    var yearlyExpenseChart = new Chart(yearlyLine, {
      type: 'bar',
      data: yearlyChartData,
      options: {
        scales: {
          x: {
            ticks: {
              autoSkip: false,
              maxRotation: 45,
              minRotation: 45
            }
          }
        }
      }
    });

  var monthlyLine = document.getElementById('monthly_expense_line').getContext('2d');
var monthlyChartData = {
    labels: [<?php echo '"' . implode('","', $monthly_labels) . '"'; ?>],
    datasets: [{
        label: 'Monthly Expense (Last Year)',
        data: [<?php echo implode(',', $monthly_expense_data); ?>],
        borderColor: [
            '#fd7e14'
        ],
        backgroundColor: [
            '#fd7e14'
        ],
        fill: false,
        borderWidth: 2
    }]
};
var monthlyExpenseChart = new Chart(monthlyLine, {
    type: 'line',
    data: monthlyChartData,
    options: {
        scales: {
            x: {
                ticks: {
                    autoSkip: false,
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        }
    }
});


var line = document.getElementById('expense_line').getContext('2d');
var myChart = new Chart(line, {
  type: 'line',
  data: {
    labels: [<?php while ($c = mysqli_fetch_array($exp_date_line)) {
                echo '"' . $c['day_month'] . '",';
              } ?>],
    datasets: [{
      label: 'Expense by Day (Last Week)',
      data: [<?php while ($d = mysqli_fetch_array($exp_amt_line)) {
                echo '"' . $d['SUM(expense)'] . '",';
              } ?>],
      borderColor: [
        '#adb5bd'
      ],
      backgroundColor: [
        '#6f42c1',
        '#dc3545',
        '#28a745',
        '#007bff',
        '#ffc107',
        '#20c997',
        '#17a2b8',
        '#fd7e14',
        '#e83e8c',
        '#6610f2'
      ],
      fill: false,
      borderWidth: 2
    }]
  }
});



    
  </script>
</body>

</html>
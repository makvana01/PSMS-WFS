<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?php echo app_url('index.php'); ?>">
      <i class="fa-solid fa-graduation-cap me-2"></i>PMS
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?php echo ($current_page == 'index.php' && $current_dir !== 'auth' && $current_dir !== 'pages') ? 'active' : ''; ?>" href="<?php echo app_url('index.php'); ?>">
            <i class="fa-solid fa-house me-1"></i>Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo $current_page == 'jobs.php' ? 'active' : ''; ?>" href="<?php echo app_url('pages/jobs.php'); ?>">
            <i class="fa-solid fa-briefcase me-1"></i>Jobs
          </a>
        </li>
      </ul>
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <?php if(isset($_SESSION['user_id'])): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                <i class="fa-solid fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <?php if($_SESSION['user_role'] === 'admin'): ?>
                    <li><a class="dropdown-item" href="<?php echo app_url('pages/admin_dashboard.php'); ?>"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
                <?php elseif($_SESSION['user_role'] === 'student'): ?>
                    <li><a class="dropdown-item" href="<?php echo app_url('pages/student_dashboard.php'); ?>"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
                    <li><a class="dropdown-item" href="<?php echo app_url('pages/profile.php'); ?>"><i class="fa-solid fa-user me-2"></i>My Profile</a></li>
                <?php elseif($_SESSION['user_role'] === 'company'): ?>
                    <li><a class="dropdown-item" href="<?php echo app_url('pages/company_dashboard.php'); ?>"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
                    <li><a class="dropdown-item" href="<?php echo app_url('pages/company_profile.php'); ?>"><i class="fa-solid fa-building me-2"></i>Company Profile</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?php echo app_url('auth/logout.php'); ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
              </ul>
            </li>
        <?php else: ?>
            <li class="nav-item me-lg-2 mb-2 mb-lg-0">
              <a class="btn-navbar-login" href="<?php echo app_url('auth/login.php'); ?>">
                <i class="fa-solid fa-right-to-bracket me-1"></i>Login
              </a>
            </li>
            <li class="nav-item">
              <a class="btn-navbar-register" href="<?php echo app_url('auth/register.php'); ?>">
                <i class="fa-solid fa-user-plus me-1"></i>Register
              </a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

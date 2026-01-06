<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

<aside class="sidebar" aria-label="Sidebar Navigation">
    <div class="brand">
        <div class="logo">VM</div>
        <div>
            <h1>Inventory System</h1>
            <p>Authorized Dashboard</p>
        </div>
    </div>

    <nav class="nav">
        <a href="dashboard.php"
           class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-house"></i></span>
            <span>Dashboard</span>
        </a>

        <a href="vehicle.php"
           class="<?= $currentPage === 'vehicle.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-car"></i></span>
            <span>Vehicles</span>
        </a>

        <a href="add_vehicle.php"
           class="<?= $currentPage === 'add_vehicle.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-plus"></i></span>
            <span>Add Vehicle</span>
        </a>

        <a href="machine.php"
           class="<?= $currentPage === 'machine.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
            <span>Machines</span>
        </a>

        <a href="add_machine.php"
           class="<?= $currentPage === 'add_machine.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-plus"></i></span>
            <span>Add Machine</span>
        </a>

        <a href="approve.php"
           class="<?= $currentPage === 'approve.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-check"></i></span>
            <span>Approvals</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="pill">
            <span>Role</span>
            <span class="badge">AUTHORIZED</span>
        </div>

        <a class="btn secondary"
           href="../logout.php"
           onclick="return confirm('Are you sure you want to log out?');">
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout
        </a>
    </div>
</aside>
<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

<aside class="sidebar" aria-label="Sidebar Navigation">
    <div class="brand">
        <div class="logo">VM</div>
        <div>
            <h1>Inventory System</h1>
            <p>Guest (read-only)</p>
        </div>
    </div>

    <nav class="nav">
        <a href="guest_dashboard.php"
           class="<?= $currentPage === 'guest_dashboard.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-house"></i></span>
            <span>Dashboard</span>
        </a>

        <a href="view_vehicle.php"
           class="<?= $currentPage === 'view_vehicle.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-car"></i></span>
            <span>Vehicles</span>
        </a>

        <a href="add_vehicle.php"
           class="<?= $currentPage === 'add_vehicle.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-plus"></i></span>
            <span>Add Vehicle</span>
        </a>

        <a href="view_machine.php"
           class="<?= $currentPage === 'view_machine.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
            <span>Machines</span>
        </a>

        <a href="add_machine.php"
           class="<?= $currentPage === 'add_machine.php' ? 'active' : '' ?>">
            <span class="icon"><i class="fa-solid fa-plus"></i></span>
            <span>Add Machine</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="pill">
            <span>Access level</span>
            <span class="badge">GUEST</span>
        </div>

        <a class="btn secondary" href="../login.php">
            <i class="fa-solid fa-right-to-bracket"></i>
            Login
        </a>

        <a class="btn secondary" href="../index.php">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>
    </div>
</aside>

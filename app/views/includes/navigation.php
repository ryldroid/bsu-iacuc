<?php

/** @var array|null $user */
$role = $user['role'] ?? '';
$previewMode = $previewMode ?? false;
$pubRoot     = $pubRoot ?? ($previewMode ? ROOT . '/preview' : ROOT);
?>

<link rel="stylesheet" href="<?= asset_css('navigation.css') ?>">

<aside>
    <nav aria-label="Main navigation" class="main-navigation">
        <ul>

            <!-- PERSONNEL (STAFF / REVIEWER) NAVIGATION -->
            <?php if ($role === 'staff' || $role === 'reviewer'): ?>
                <li>
                    <a href="<?= ROOT ?>/personnel/home" aria-label="Dashboard">
                        <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#dashboard-icon" />
                        </svg>
                    </a>
                    <div aria-hidden="true">
                        <span>Dashboard</span>
                    </div>
                </li>

                <?php if ($role === 'staff'): ?>
                    <!-- STAFF ONLY -->
                    <li>
                        <a href="<?= ROOT ?>/personnel/records" aria-label="Records">
                            <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#protocols-icon" />
                            </svg>
                        </a>
                        <div aria-hidden="true">
                            <span>Records</span>
                        </div>
                    </li>

                    <li>
                        <a href="<?= ROOT ?>/personnel/announcements" aria-label="Site Content">
                            <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#edit-icon" />
                            </svg>
                        </a>
                        <div aria-hidden="true">
                            <span>Site Content</span>
                        </div>
                    </li>
                <?php endif; ?>

                <?php if ($role === 'reviewer'): ?>
                    <!-- REVIEWER ONLY -->
                    <li>
                        <a href="<?= ROOT ?>/personnel/records" aria-label="Records">
                            <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#protocols-icon" />
                            </svg>
                        </a>
                        <div aria-hidden="true">
                            <span>Records</span>
                        </div>
                    </li>
                <?php endif; ?>


                <?php if ($role === 'staff'): ?>
                    <li>
                        <a href="<?= ROOT ?>/personnel/accounts" aria-label="Administration">
                            <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#accounts-icon" />
                            </svg>
                        </a>
                        <div aria-hidden="true">
                            <span>Administration</span>
                        </div>
                    </li>
                <?php endif; ?>

            <?php else: ?>
                <!-- PUBLIC / RESEARCHER NAVIGATION -->
                <li>
                    <a href="<?= $pubRoot ?>/home" aria-label="Home">
                        <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#home-icon" />
                        </svg>
                    </a>
                    <div aria-hidden="true">
                        <span>Home</span>
                    </div>
                </li>

                <?php if ($role === 'researcher'): ?>
                    <!-- RESEARCHER ONLY -->
                    <li>
                        <a href="<?= ROOT ?>/submissions" aria-label="My Protocols">
                            <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#protocols-icon" />
                            </svg>
                        </a>
                        <div aria-hidden="true">
                            <span>My Protocols</span>
                        </div>
                    </li>
                <?php endif; ?>

                <li>
                    <a href="<?= $pubRoot ?>/announcements" aria-label="Announcements">
                        <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#announcement-icon" />
                        </svg>
                    </a>
                    <div aria-hidden="true">
                        <span>Announcements</span>
                    </div>
                </li>

                <li>
                    <a href="<?= $pubRoot ?>/contact" aria-label="Contact Us">
                        <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#contact-icon" />
                        </svg>
                    </a>
                    <div aria-hidden="true">
                        <span>Contact Us</span>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- ===== Expand / collapse toggle ===== -->
    <ul>
        <li>
            <button type="button" id="sidebar-toggle" aria-expanded="false" aria-label="Expand sidebar">
                <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#panel-left-icon" />
                </svg>
            </button>
            <div>
                <span>Expand sidebar</span>
            </div>
        </li>
    </ul>
</aside>
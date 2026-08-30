<?php

/** @var array|null $user */
$role = $user['role'] ?? '';
?>

<link rel="stylesheet" href="<?= asset_css('navigation.css') ?>">

<aside>
    <nav aria-label="Main navigation" class="main-navigation">
        <ul>

            <!-- STAFF (ADMIN / REVIEWER) NAVIGATION -->
            <?php if ($role === 'admin' || $role === 'reviewer'): ?>
                <li>
                    <a href="<?= ROOT ?>/admin/home">
                        <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#home-icon" />
                        </svg>
                    </a>
                    <div>
                        <span>Dashboard</span>
                    </div>
                </li>

                <li>
                    <a href="<?= ROOT ?>/admin/records">
                        <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#protocols-icon" />
                        </svg>
                    </a>
                    <div>
                        <span>Records</span>
                    </div>
                </li>

                <?php if ($role === 'admin'): ?>
                    <!-- ADMIN ONLY -->
                    <li>
                        <a href="<?= ROOT ?>/admin/announcements">
                            <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#announcement-icon" />
                            </svg>
                        </a>
                        <div>
                            <span>Announcements</span>
                        </div>
                    </li>

                <?php endif; ?>

                <?php if (in_array($role, ['admin', 'reviewer'])): ?>
                    <li>
                        <a href="<?= ROOT ?>/admin/accounts">
                            <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#accounts-icon" />
                            </svg>
                        </a>
                        <div>
                            <span>Administration</span>
                        </div>
                    </li>
                <?php endif; ?>

            <?php else: ?>
                <!-- PUBLIC / RESEARCHER NAVIGATION -->
                <li>
                    <a href="<?= ROOT ?>/home">
                        <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#home-icon" />
                        </svg>
                    </a>
                    <div>
                        <span>Home</span>
                    </div>
                </li>

                <?php if ($role === 'researcher'): ?>
                    <!-- RESEARCHER ONLY -->
                    <li>
                        <a href="<?= ROOT ?>/submissions">
                            <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#protocols-icon" />
                            </svg>
                        </a>
                        <div>
                            <span>My Protocols</span>
                        </div>
                    </li>
                <?php endif; ?>

                <li>
                    <a href="<?= ROOT ?>/announcements">
                        <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#announcement-icon" />
                        </svg>
                    </a>
                    <div>
                        <span>Announcements</span>
                    </div>
                </li>

                <li>
                    <a href="<?= ROOT ?>/contact">
                        <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#contact-icon" />
                        </svg>
                    </a>
                    <div>
                        <span>Contact</span>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</aside>
<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['staff', 'trainer', 'gym_admin']);
{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Shipping System') - STEP</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Google Fonts - Montserrat -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="icon" type="image/png" href="{{ asset('images/logostep.png') }}">

    <style>
        /* Base styles */
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #000000;
            color: #ffffff;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
            margin: 0 !important;
            padding: 0 !important;
            padding-bottom: 80px !important;
        }

        /* Header Navigation */
        .navbar {
            background-color: #000000;
            border: 3px solid #ffffff;
            padding: 0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
        }

        /* Header Table Structure */
        .table-container {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .table-row {
            display: table-row;
        }

        .table-cell {
            display: table-cell;
            vertical-align: middle;
            padding: 10px;
            text-align: center;
        }

        .table-cell:not(:last-child) {
            border-right: 3px solid white;
        }

        .logo-cell {
            width: 150px;
        }

        .title-cell {
            width: auto;
        }

        .user-cell {
            width: 200px;
        }

        .logo {
            display: block;
            margin: 0 auto;
            width: 120px;
            height: auto;
            cursor: pointer;
        }

        .page-title {
            font-weight: bold;
            font-size: 55px;
            text-align: center;
            margin: 0;
            color: #ffffff;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
            font-size: 14px;
        }

        .user-name {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .user-role {
            font-size: 12px;
            padding: 2px 10px;
            border-radius: 12px;
            background: #fbbf24;
            color: #000;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            padding: 0rem;
            min-height: calc(100vh - 120px);
            background-color: #000000;
        }

        body.preparation-page {
            margin: 0 !important;
            padding: 0 !important;
        }

        body.preparation-page .main-content {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        body.preparation-page .container,
        body.preparation-page .container-fluid {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
        }

        body.preparation-page .table-responsive {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            
        }

        /* ========================================
           Compact Table Styling untuk Preparation
           ======================================== */
        .table-compact {
            border-collapse: collapse !important;
            background-color: #000000;
            color: #ffffff;
            border: 2px solid #ffffff;
        }

        .table-compact thead th {
            font-weight: 700;
            white-space: nowrap;
            text-align: center;
            vertical-align: middle;
            background-color: #1a1a1a;
            color: #ffffff;
            border: 1px solid #ffffff !important;
            letter-spacing: 1px;
        }

        .table-compact tbody td {
            padding: 2px 0px !important;
            white-space: nowrap;
            text-align: center;
            vertical-align: middle;
            background-color: #0a0a0a;
            color: #ffffff;
            border: 1px solid #ffffff !important;
            font-weight: 500;
        }

        .table-compact tbody tr:nth-child(even) td {
            background-color: #0f0f0f;
        }

        .table-compact tbody tr:hover td {
            background-color: #505050;
            transition: background 0.2s ease;
        }

        .table-compact tbody td strong {
            font-weight: 700;
        }

        /* Pagination Wrapper untuk Preparation */
        .pagination-wrapper {
            margin: 0 !important;
            padding: 15px !important;
            background-color: #000000;
            padding-bottom: 80px !important;
        }

        /* Custom CSS untuk nested dropdown */
        .dropdown-menu .dropend .dropdown-toggle::after {
            display: inline-block;
            margin-left: 0.5em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid transparent;
            border-right: 0;
            border-bottom: 0.3em solid transparent;
            border-left: 0.3em solid;
        }

        .dropdown-menu .dropend > .dropdown-menu {
            top: 0;
            left: 100%;
            margin-top: -1px;
        }

        .dropend:hover > .dropdown-menu {
            display: block;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        /* Prevent horizontal scrollbar dengan word wrap */
        .table-responsive {
            overflow-x: auto;
        }

        #preparationsTable {
            width: 100%;
        }

        #preparationsTable th,
        #preparationsTable td {
            white-space: normal;
            word-wrap: break-word;
            word-break: break-word;
            vertical-align: middle;
        }

        /* Kolom actions tetap nowrap biar button ga kebawah */
        #preparationsTable th:nth-child(12),
        #preparationsTable td:nth-child(12) {
            white-space: nowrap;
        }
        
        .badge-delay {
            animation: hard-blink 0.8s infinite;
        }

        @keyframes hard-blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }

        /* Menu styles */
        .menu-container {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-top: -1px;
        }

        .menu-item {
            display: table-cell;
            text-align: center;
            padding: 10px;
            border-top: 3px solid #fff;
            border-left: 3px solid #fff;
            background-color: #000000;
            transition: background-color 0.3s;
            position: relative;
        }
        
        .menu-item:first-child {
            border-left: none;
        }

        .menu-item:hover {
            background-color: #555;
        }

        .menu-item a {
            color: #fff;
            font-weight: 500;
            font-size: 18px;
            text-decoration: none;
        }

        #settingsMenu {
            padding-left: 1px !important;
            padding-right: 1px !important;
        }


        .menu-item a:hover {
            color: #dfe6e9;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background-color: #fff;
            border: 1px solid #ddd;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            padding: 0.5rem 1rem;
            min-width: 150px;
        }

        .dropdown-menu a {
            display: block;
            padding: 0.5rem 0;
            text-decoration: none;
            color: #333;
        }

        .dropdown-menu a:hover {
            background-color: #f1f1f1;
        }

        .menu-item:hover .dropdown-menu {
            display: block;
        }

        .submenu {
            display: none;
            position: absolute;
            left: 0;
            top: 100%;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            min-width: 180px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 50;
        }

        .submenu a {
            display: block;
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }

        .submenu a:last-child {
            border-bottom: none;
        }

        .submenu a:hover {
            background: #f5f5f5;
        }

        .submenu a i {
            width: 20px;
            margin-right: 8px;
            color: #666;
        }

        

        /* Floating User Info */
        .floating-user-info {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.9);
            border: 2px solid #333;
            border-radius: 12px;
            padding: 12px 16px;
            z-index: 9999;
            min-width: 160px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .floating-user-info:hover {
            border-color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.2);
        }

        .floating-user-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .floating-user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #ffffff, #7c7c7c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .floating-user-avatar i {
            color: #000000;
            font-size: 18px;
        }

        .floating-user-details {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .floating-user-name {
            font-weight: 700;
            font-size: 17px;
            color: #ffffff;
            line-height: 1.2;
        }

        .floating-user-role {
            font-size: 15px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            width: fit-content;
        }

        .fixed-running-text {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 9999;
            padding: 8px 0;
            box-shadow: 0 -3px 5px rgba(0,0,0,0.2);
        }

        .running-text-container {
            overflow: hidden;
            white-space: nowrap;
        }

        .running-text-inner {
            display: inline-block;
            padding-left: 100%;
        }

        .speed-normal {
            animation: scroll-left 15s linear infinite;
        }
        .speed-fast {
            animation: scroll-left 8s linear infinite;
        }
        .speed-slow {
            animation: scroll-left 25s linear infinite;
        }

        @keyframes scroll-left {
            from { transform: translateX(0); }
            to { transform: translateX(-100%); }
        }

        .andon-toggle {
            cursor: pointer;
            transition: 0.2s;
        }

        .andon-submenu a {
            display: block;
            padding: 3px 0;
        }

        @keyframes blink-animation {
            0%, 49% { opacity: 1; }
            50%, 99% { opacity: 0.2; }
            100% { opacity: 1; }
        }
        
        
        .blink-badge {
            animation: blink-animation 1s steps(1) infinite;
        }

        
</style>
    </style>

    @stack('styles')
</head>

<body class="@yield('body-class')">
    <!-- Header Navigation -->
    <nav class="navbar navbar-dark">
        <div class="table-container">
            <div class="table-row">
                <!-- Logo -->
                <div class="table-cell logo-cell">
                    {{-- <a href="{{ route('dashboard') }}"> --}}
                        <img src="{{ asset('images/logostep.png') }}" alt="Logo" class="logo">
                    </a>
                </div>
                <!-- Title -->
                <div class="table-cell title-cell">
                    <span class="page-title">
                        @yield('page-title', 'SHIPPING SYSTEM V2')
                    </span>
                </div>
                <!-- User Info -->
                <div class="table-cell user-cell">
                    <div id="clock" style="margin-top: 1px; font-size: 20px; font-weight: 600; color: #ffffff;">
                        <div style="font-size: 20px; margin-top: 2px;">00/00/0000</div>
                        <div style="font-size: 16px;">00:00:00</div>
                    </div>
                </div>
            </div>
        </div>

        @if(auth()->user()->role !== 'lp')
            <!-- Menu below Navbar -->
            <div class="menu-container">
                <div class="menu-item">
                    <a href="{{ route('prep-monitoring.index') }}">Dashboard</a>
                </div>
                <div class="menu-item">
                    <a href="{{ route('preparations.index') }}">Preparation</a>
                </div>
                <div class="menu-item">
                    <a href="{{ route('shippings.index') }}">Shipping</a>
                </div>
                <div class="menu-item">
                    <a href="{{ route('deliveries.index') }}">Delivery</a>
                </div>
                <div class="menu-item">
                    <a href="{{ route('milkruns.index') }}">Milkrun</a>
                </div>
                <div class="menu-item">
                    <a href="{{ route('histories.index') }}">History</a>
                </div>
                <div class="menu-item dropdown">
                    <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                        Kanban
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="{{ route('kanbantmmins.index') }}">
                                TMMIN
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('kanbantmminsp2.index') }}">
                                TMMIN P2
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('kanbanhpms.index') }}">
                                HPM
                            </a>
                        </li>
                        {{-- <li>
                            <a class="dropdown-item" href="{{ route('kanbanadms.index') }}">
                                ADM
                            </a>
                        </li> --}}
                        <li>
                            <a class="dropdown-item" href="{{ route('kanban-split.index') }}">
                                ADM 
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="{{ route('kanban-ntc-split.index') }}">
                                NTC 
                            </a>
                        </li>
                        
                        <li>
                            <a class="dropdown-item" href="{{ route('kanban-fji-split.index') }}">
                                FJI 
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="{{ route('kanban-futaba-split.index') }}">
                                FUTABA
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="{{ route('kanban-hino-split.index') }}">
                                HINO
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="{{ route('kanban-tgi-split.index') }}">
                                TGI
                            </a>
                        </li>
                    </ul>
                </div>
                @if(auth()->user()->role === 'superadmin')
                    <div class="menu-item" id="settingsMenu">
                        <a href="#" id="settingsBtn">More & Settings</a>

                        <div class="submenu text-start" id="submenu">

                            <a href="{{ route('users.index') }}" class="fs-6">
                                <i class="fas fa-users fs-6"></i> Users
                            </a>

                            <a href="#" id="runningTextBtn" class="fs-6" data-bs-toggle="modal" data-bs-target="#runningTextModal">
                                <i class="fas fa-scroll"></i> Running Text
                            </a>

                            <a href="{{ route('advertisements.index') }}" class="fs-6">
                                <i class="fas fa-ad"></i> Iklan
                            </a>

                            <a href="{{ route('addresses.index') }}" class="fs-6">
                                <i class="fas fa-address-book"></i> TMMIN Address
                            </a>

                            <a href="{{ route('hpm-addresses.index') }}" class="fs-6">
                                <i class="fas fa-address-book"></i> HPM Address
                            </a>

                            <a href="{{ route('admadressesv2.index') }}" class="fs-6">
                                <i class="fas fa-address-book"></i> ADM Address
                            </a>

                            <a href="{{ route('ntcaddresses.index') }}" class="fs-6">
                                <i class="fas fa-address-book"></i> NTC Address
                            </a>
                            
                            <a href="{{ route('addressfji.index') }}" class="fs-6">
                                <i class="fas fa-address-book"></i> FJI Address
                            </a>

                            <a href="{{ route('addressfutaba.index') }}" class="fs-6">
                                <i class="fas fa-address-book"></i> Futaba Address
                            </a>

                            <a href="{{ route('addresshino.index') }}" class="fs-6">
                                <i class="fas fa-address-book"></i> Hino Address
                            </a>

                            <a href="{{ route('addresstgi.index') }}" class="fs-6">
                                <i class="fas fa-address-book"></i> TGI Address
                            </a>

                            <a href="{{ route('kanbanbanktgi.index') }}" class="fs-6">
                                <i class="fas fa-address-book"></i> Master PDF TGI
                            </a>

                            <a href="{{ route('arsadms.index') }}" class="fs-6">
                                <i class="fas fa-address-book"></i> ADM ARS
                            </a>

                            {{-- MENU ANDON UTAMA (klik untuk collapse) --}}
                            <a href="#" class="andon-toggle d-flex justify-content-between align-items-center fs-6">
                                <span><i class="fas fa-desktop"></i> Andon</span>
                                <i class="fas fa-chevron-down"></i>
                            </a>

                            {{-- SUBMENU ANDON --}}
                            <div class="andon-submenu mt-1 mb-1" style="display: none; padding-left: 40px;">
                                <a href="{{ route('andon.preparations') }}" target="_blank" style="font-size:15px; opacity:0.7;">
                                    • Preparations
                                </a>
                                <a href="{{ route('andon.prep-monitoring') }}" target="_blank" style="font-size:15px; opacity:0.7;">
                                    • Dashboard
                                </a>
                                <a href="{{ route('andon.shippings.group') }}" target="_blank" style="font-size:15px; opacity:0.7;">
                                    • Shippings
                                </a>
                                <a href="{{ route('andon.deliveries') }}" target="_blank" style="font-size:15px; opacity:0.7;">
                                    • Deliveries
                                </a>
                                <a href="{{ route('andon.milkruns') }}" target="_blank" style="font-size:15px; opacity:0.7;">
                                    • Milkruns
                                </a>
                            </div>

                        </div>
                    </div>
                @endif

                <div class="menu-item">
                    <a href="#" onclick="confirmLogout(event);">Logout</a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </div>

            
        @endif
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        @yield('content')
    </main>

    <!-- Floating User Info -->
    @auth
    <div class="floating-user-info mb-5">
        <div class="floating-user-content">
            <div class="floating-user-avatar">
                @if(auth()->user()->id == 7)
                    <img src="{{ asset('images/user7.png') }}" 
                         alt="User Photo" 
                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">
                @elseif(auth()->user()->id == 1)
                    <img src="{{ asset('images/wa.png') }}" 
                         alt="User Photo" 
                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">
                @else
                    <i class="fas fa-user"></i>
                @endif
            </div>

            <div class="floating-user-details">
                <div class="floating-user-name">{{ auth()->user()->name }}</div>
                @php
                    $role = auth()->user()->role ?? 'user';
                    $roleClass = match($role) {
                        'admin' => 'role-admin',
                        'operator' => 'role-operator',
                        'lp' => 'role-lp',
                        default => 'role-default'
                    };
                @endphp
                <span class="{{ $roleClass }}">{{ $role }}</span>
            </div>
        </div>
    </div>
    @endauth

    @php
        $runningText = \App\Models\RunningText::getActive();
    @endphp

    @if($runningText && $runningText->is_active)
        <div class="running-text-wrapper fixed-running-text fw-bold"
            style="background-color: {{ $runningText->background_color ?? '#1a1a1a' }};">
            <div class="running-text-container">
                <div class="running-text-track">
                    <div class="running-text-inner speed-{{ $runningText->speed ?? 'normal' }}">
                        <span style="color: {{ $runningText->text_color ?? '#fbbf24' }};">
                            {{ $runningText->content }}
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Include Running Text Modal (hanya untuk superadmin) -->
    @if(auth()->check() && auth()->user()->role === 'superadmin')
        @include('runningtext.index')
    @endif

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <!-- Clock Script -->
    <script>
        function updateClock() {
            const now = new Date();
            
            // Format waktu: HH:MM:SS
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const timeString = `${hours}:${minutes}:${seconds}`;
            
            // Format tanggal: DD/MM/YYYY
            const day = String(now.getDate()).padStart(2, '0');
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = now.getFullYear();
            const dateString = `${day}/${month}/${year}`;
            
            // Update DOM
            const clockDiv = document.getElementById('clock');
            if (clockDiv) {
                clockDiv.innerHTML = `
                    <div style="font-size: 25px; font-weight: bold; margin-top: 2px;">${dateString}</div>
                    <div style="font-size: 25px; font-weight: bold;">${timeString}</div>
                `;
            }
        }
        
        // Update setiap detik
        setInterval(updateClock, 1000);
        
        // Jalankan pertama kali
        updateClock();
    </script>

    <!-- SweetAlert Session Handler -->
    <script>
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '{{ session('success') }}',
                confirmButtonColor: '#10b981',
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '{{ session('error') }}',
                confirmButtonColor: '#dc2626'
            });
        @endif

        function confirmLogout(event) {
            event.preventDefault();
            
            Swal.fire({
                title: 'Konfirmasi Logout',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Logout',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('logout-form').submit();
                }
            });
        }

        const btn = document.getElementById('settingsBtn');
        const submenu = document.getElementById('submenu');

        if (btn && submenu) {
            btn.addEventListener('click', function (e) {
                e.preventDefault(); 
                submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
            });

            // Tutup jika klik di luar menu
            document.addEventListener('click', function(e) {
                const settingsMenu = document.getElementById('settingsMenu');
                if (settingsMenu && !settingsMenu.contains(e.target)) {
                    submenu.style.display = 'none';
                }
            });

            // Prevent submenu links from closing immediately
            submenu.addEventListener('click', function(e) {
                if (e.target.tagName === 'A' && !e.target.hasAttribute('data-bs-toggle')) {
                    // Allow navigation for normal links
                } else {
                    e.stopPropagation();
                }
            });
        }

        document.addEventListener("DOMContentLoaded", function() {
            const btn = document.querySelector(".andon-toggle");
            const submenu = document.querySelector(".andon-submenu");

            btn.addEventListener("click", function(e) {
                e.preventDefault();
                submenu.style.display = submenu.style.display === "none" ? "block" : "none";
            });
        });
    </script>

    @include('partials.ad-player')

    @stack('scripts')
</body>
</html>
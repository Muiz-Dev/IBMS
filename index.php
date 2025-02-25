<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice & Billing System - Authentication</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 3px solid #ffffff;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .alert-enter {
            transform: translateY(100%);
            opacity: 0;
        }
        .alert-enter-active {
            transform: translateY(0);
            opacity: 1;
            transition: all 300ms ease-out;
        }
        .alert-exit {
            transform: translateY(0);
            opacity: 1;
        }
        .alert-exit-active {
            transform: translateY(100%);
            opacity: 0;
            transition: all 300ms ease-in;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full space-y-8 bg-white rounded-xl shadow-lg p-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-gray-900">
                    Welcome Back
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Sign in to your account
                </p>
            </div>
            
            <!-- Tab Navigation -->
            <div class="flex justify-center space-x-6 border-b">
                <button id="loginTab" class="text-sm font-medium pb-2 border-b-2 border-transparent transition-colors duration-200">
                    Login
                </button>
                <button id="registerTab" class="text-sm font-medium pb-2 border-b-2 border-transparent transition-colors duration-200">
                    Register
                </button>
            </div>

            <!-- Login Form -->
            <form id="loginForm" class="mt-8 space-y-6" action="login.php" method="POST">
                <input type="hidden" name="remember" value="true">
                <div class="space-y-4">
                    <div>
                        <label for="login-email" class="block text-sm font-medium text-gray-700">Email address</label>
                        <input id="login-email" name="email" type="email" autocomplete="email" required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 
                            focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                            placeholder="Enter your email">
                    </div>
                    <div>
                        <label for="login-password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input id="login-password" name="password" type="password" autocomplete="current-password" required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 
                            focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                            placeholder="Enter your password">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" 
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember-me" class="ml-2 block text-sm text-gray-900">
                            Remember me
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="forgot_password.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Forgot password?
                        </a>
                    </div>
                </div>

                <div>
                    <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium 
                        rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 
                        focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400" xmlns="http://www.w3.org/2000/svg" 
                                viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" 
                                    d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" 
                                    clip-rule="evenodd" />
                            </svg>
                        </span>
                        Sign in
                    </button>
                </div>
            </form>

            <!-- Register Form -->
            <form id="registerForm" class="mt-8 space-y-6 hidden" action="register.php" method="POST">
                <div class="space-y-4">
                    <div>
                        <label for="register-name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input id="register-name" name="full_name" type="text" autocomplete="name" required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 
                            focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                            placeholder="Enter your full name">
                    </div>
                    <div>
                        <label for="register-email" class="block text-sm font-medium text-gray-700">Email address</label>
                        <input id="register-email" name="email" type="email" autocomplete="email" required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 
                            focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                            placeholder="Enter your email">
                    </div>
                    <div>
                        <label for="register-password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input id="register-password" name="password" type="password" autocomplete="new-password" required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 
                            focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                            placeholder="Create a password">
                    </div>
                </div>

                <div>
                    <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium 
                        rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 
                        focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400" xmlns="http://www.w3.org/2000/svg" 
                                viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                            </svg>
                        </span>
                        Register
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Alert Component -->
    <div id="alert" class="fixed bottom-5 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg hidden z-50">
        <div class="flex items-center space-x-3">
            <div id="alertIcon" class="flex-shrink-0"></div>
            <p id="alertMessage" class="text-white font-medium"></p>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="spinner" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-4 flex items-center space-x-3">
            <div class="spinner border-blue-600"></div>
            <p class="text-gray-700 font-medium">Please wait...</p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginTab = document.getElementById('loginTab');
        const registerTab = document.getElementById('registerTab');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const alert = document.getElementById('alert');
        const alertMessage = document.getElementById('alertMessage');
        const alertIcon = document.getElementById('alertIcon');
        const spinner = document.getElementById('spinner');

        // Set initial active tab
        loginTab.classList.add('text-blue-600', 'border-blue-600');

        function switchTab(activeTab, activeForm, inactiveTab, inactiveForm) {
            activeForm.classList.remove('hidden');
            inactiveForm.classList.add('hidden');
            
            activeTab.classList.add('text-blue-600', 'border-blue-600');
            inactiveTab.classList.remove('text-blue-600', 'border-blue-600');
            
            // Clear any existing alerts
            hideAlert();
        }

        loginTab.addEventListener('click', () => 
            switchTab(loginTab, loginForm, registerTab, registerForm));

        registerTab.addEventListener('click', () => 
            switchTab(registerTab, registerForm, loginTab, loginForm));

        function showAlert(message, type) {
            const icons = {
                success: `<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M5 13l4 4L19 7"></path>
                        </svg>`,
                error: `<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>`
            };

            alert.className = `fixed bottom-5 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg z-50 
                ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
            alertIcon.innerHTML = icons[type];
            alertMessage.textContent = message;
            
            alert.classList.remove('hidden');
            alert.classList.add('alert-enter');
            
            setTimeout(() => {
                hideAlert();
            }, 3000);
        }

        function hideAlert() {
            alert.classList.add('alert-exit');
            setTimeout(() => {
                alert.classList.add('hidden');
                alert.classList.remove('alert-exit');
            }, 300);
        }

        function showSpinner() {
            spinner.classList.remove('hidden');
        }

        function hideSpinner() {
            spinner.classList.add('hidden');
        }

        // Handle form submissions
        [loginForm, registerForm].forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                showSpinner();

                try {
                    const response = await fetch(this.action, {
                        method: 'POST',
                        body: new FormData(this)
                    });

                    const data = await response.json();
                    hideSpinner();

                    showAlert(data.message, data.success ? 'success' : 'error');

                    if (data.success && data.redirect) {
                        // Disable form submission
                        form.querySelector('button[type="submit"]').disabled = true;
                        
                        // Show loading state
                        showSpinner();
                        
                        // Redirect after a short delay
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                } catch (error) {
                    hideSpinner();
                    showAlert('An error occurred. Please try again.', 'error');
                }
            });
        });
    });
    </script>
</body>
</html>
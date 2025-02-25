<?php
session_start();
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
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 3px solid #3498db;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full space-y-8 p-10 bg-white rounded-xl shadow-lg">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-bold text-gray-900">
                    Welcome Back
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Sign in to your account
                </p>
            </div>
            <div class="flex justify-center space-x-4">
                <button id="loginTab" class="text-sm font-medium text-gray-700 hover:text-gray-900 focus:outline-none focus:underline">
                    Login
                </button>
                <button id="registerTab" class="text-sm font-medium text-gray-700 hover:text-gray-900 focus:outline-none focus:underline">
                    Register
                </button>
            </div>

            <!-- Login Form -->
            <form id="loginForm" class="mt-8 space-y-6" action="login.php" method="POST">
                <input type="hidden" name="remember" value="true">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="login-email" class="sr-only">Email address</label>
                        <input id="login-email" name="email" type="email" autocomplete="email" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" placeholder="Email address">
                    </div>
                    <div>
                        <label for="login-password" class="sr-only">Password</label>
                        <input id="login-password" name="password" type="password" autocomplete="current-password" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" placeholder="Password">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember-me" class="ml-2 block text-sm text-gray-900">
                            Remember me
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="forgot_password.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Forgot your password?
                        </a>
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        Sign in
                    </button>
                </div>
            </form>

            <!-- Register Form -->
            <form id="registerForm" class="mt-8 space-y-6 hidden" action="register.php" method="POST">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="register-name" class="sr-only">Full Name</label>
                        <input id="register-name" name="full_name" type="text" autocomplete="name" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" placeholder="Full Name">
                    </div>
                    <div>
                        <label for="register-email" class="sr-only">Email address</label>
                        <input id="register-email" name="email" type="email" autocomplete="email" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" placeholder="Email address">
                    </div>
                    <div>
                        <label for="register-password" class="sr-only">Password</label>
                        <input id="register-password" name="password" type="password" autocomplete="new-password" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" placeholder="Password">
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                            </svg>
                        </span>
                        Register
                    </button>
                </div>
            </form>

            <!-- Password Reset Link -->
            <div class="text-sm text-center">
                <a href="forgot_password.php" class="font-medium text-blue-600 hover:text-blue-500">
                    Forgot your password?
                </a>
            </div>
        </div>
    </div>

    <!-- Alert and Spinner -->
    <div id="alert" class="fixed bottom-5 right-5 px-4 py-2 rounded-md shadow-lg hidden"></div>
    <div id="spinner" class="fixed top-0 left-0 w-full h-full flex items-center justify-center bg-gray-900 bg-opacity-50 hidden">
        <div class="spinner"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginTab = document.getElementById('loginTab');
        const registerTab = document.getElementById('registerTab');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const alert = document.getElementById('alert');
        const spinner = document.getElementById('spinner');

        loginTab.addEventListener('click', function() {
            loginForm.classList.remove('hidden');
            registerForm.classList.add('hidden');
            loginTab.classList.add('text-blue-600', 'font-bold');
            registerTab.classList.remove('text-blue-600', 'font-bold');
        });

        registerTab.addEventListener('click', function() {
            loginForm.classList.add('hidden');
            registerForm.classList.remove('hidden');
            registerTab.classList.add('text-blue-600', 'font-bold');
            loginTab.classList.remove('text-blue-600', 'font-bold');
        });

        function showAlert(message, type) {
            alert.textContent = message;
            alert.classList.remove('hidden', 'bg-green-500', 'bg-red-500', 'bg-blue-500');
            alert.classList.add(type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500', 'text-white');
            alert.classList.remove('hidden');
            setTimeout(() => {
                alert.classList.add('hidden');
            }, 3000);
        }

        function showSpinner() {
            spinner.classList.remove('hidden');
        }

        function hideSpinner() {
            spinner.classList.add('hidden');
        }

        // Handle form submissions
        [loginForm, registerForm].forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                showSpinner();

                fetch(this.action, {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(response => response.json())
                .then(data => {
                    hideSpinner();
                    if (data.success) {
                        showAlert(data.message, 'success');
                        if (data.redirect) {
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 1500);
                        }
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    hideSpinner();
                    showAlert('An error occurred. Please try again.', 'error');
                });
            });
        });
    });
    </script>
</body>
</html>